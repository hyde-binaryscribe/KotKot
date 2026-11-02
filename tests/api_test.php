<?php
declare(strict_types=1);

/**
 * End-to-end check of the API: starts PHP's built-in server on a temporary
 * database and walks through a family's day.
 *
 *   php tests/api_test.php
 */

$port = 8765;
$data = sys_get_temp_dir() . '/kotkot-test-' . bin2hex(random_bytes(4));
mkdir($data);
$root = dirname(__DIR__);
$cmd = sprintf('KOTKOT_DATA=%s exec php -S 127.0.0.1:%d -t %s', escapeshellarg($data), $port, escapeshellarg($root . '/public'));
$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server, $data) {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($data));
});
usleep(400000);

$cookie = '';
$failures = 0;

function req(string $action, ?array $body = null, array $extraHeaders = [], ?string $rawBody = null, string $ctype = 'application/json'): array
{
    global $port, $cookie;
    $headers = ['X-KotKot: 1', ...$extraHeaders];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $opts = ['http' => ['ignore_errors' => true, 'method' => $body === null && $rawBody === null ? 'GET' : 'POST']];
    if ($body !== null || $rawBody !== null) {
        $headers[] = 'Content-Type: ' . $ctype;
        $opts['http']['content'] = $rawBody ?? json_encode($body);
    }
    $opts['http']['header'] = implode("\r\n", $headers);
    $res = file_get_contents("http://127.0.0.1:$port/api.php?a=$action", false, stream_context_create($opts));
    $status = 0;
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $status = (int) $m[1];
        }
        if (preg_match('/^Set-Cookie: (kk_device=[^;]*)/i', $h, $m)) {
            $cookie = str_ends_with($m[1], '=') || str_contains($m[1], 'deleted') ? '' : $m[1];
        }
    }
    return [$status, json_decode((string) $res, true)];
}

function check(string $what, bool $ok): void
{
    global $failures;
    echo ($ok ? "  ok  " : "  FAIL ") . $what . "\n";
    if (!$ok) {
        $failures++;
    }
}

echo "KotKot API\n";

[$s, $r] = req('state');
check('fresh install asks for setup', $s === 200 && $r['state']['setup'] === false);

[$s, $r] = req('setup', ['pin' => '12a4', 'people' => ['Léa']]);
check('setup refuses a bad code', $s === 400);

[$s, $r] = req('setup', ['pin' => '1234', 'people' => ['Léa', 'Mamie', ''], 'city' => 'Rennes', 'lat' => 48.11, 'lon' => -1.68]);
check('setup works and logs this phone in', $s === 200 && $r['state']['auth'] === true && $r['state']['me']['name'] === 'Léa');
check('two people created', count($r['state']['people']) === 2);
check('default tasks created', count($r['state']['tasks']) === 4);
check('sun times computed', is_int($r['state']['door']['sunrise']) && $r['state']['door']['sunset'] > $r['state']['door']['sunrise']);

[$s] = req('setup', ['pin' => '9999', 'people' => ['X']]);
check('setup cannot run twice', $s === 409);

// eggs, with undo and idempotent replays (offline queue)
req('egg', ['delta' => 1, 'clientId' => 'egg-0001-aaaa']);
req('egg', ['delta' => 1, 'clientId' => 'egg-0001-aaaa']);
[, $r] = req('egg', ['delta' => 1, 'clientId' => 'egg-0002-bbbb']);
check('eggs counted once per click, even if sent twice', $r['state']['eggs']['today'] === 2);
[, $r] = req('egg_undo', ['clientId' => 'egg-0002-bbbb']);
check('Annuler removes the egg', $r['state']['eggs']['today'] === 1);
req('egg', ['delta' => -1, 'clientId' => 'egg-0003-cccc']);
[, $r] = req('egg', ['delta' => -1, 'clientId' => 'egg-0004-dddd']);
check('count never goes below zero', $r['state']['eggs']['today'] === 0);

// door
[, $r] = req('door', ['action' => 'open', 'clientId' => 'door-0001-aaaa']);
check('door opened', $r['state']['door']['state'] === 'open' && $r['state']['door']['history'][0]['who'] === 'Léa');
[, $r] = req('door_undo', ['clientId' => 'door-0001-aaaa']);
check('door undo', $r['state']['door']['state'] === 'unknown');

// tasks
$taskId = $r['state']['tasks'][0]['id'];
[, $r] = req('task_set', ['taskId' => $taskId, 'done' => true]);
check('task done by Léa', $r['state']['tasks'][0]['done'] === true && $r['state']['tasks'][0]['doneBy'] === 'Léa');
[, $r] = req('task_set', ['taskId' => $taskId, 'done' => false]);
check('task unchecked', $r['state']['tasks'][0]['done'] === false);
[, $r] = req('task_save', ['label' => 'Changer la paille', 'frequency' => 'weekly', 'weekday' => 3]);
check('weekly task added', end($r['state']['tasks'])['label'] === 'Changer la paille');

// hens: no limit
for ($i = 1; $i <= 12; $i++) {
    [, $r] = req('hen_save', ['name' => "Poule $i", 'breed' => 'Rousse']);
}
check('12 hens, no limit', count($r['state']['hens']) === 12);
$henId = $r['id'];
[, $r] = req('hen_status', ['id' => $henId, 'status' => 'watch']);
check('hen marked « à surveiller »', array_values(array_filter($r['state']['hens'], fn ($h) => $h['id'] === $henId))[0]['status'] === 'watch');
[, $r] = req('hen_note_add', ['id' => $henId, 'text' => 'Mange bien']);
check('note added', $r['notes'][0]['text'] === 'Mange bien' && $r['notes'][0]['who'] === 'Léa');

// photo album: two dated photos, oldest first
$img = imagecreatetruecolor(1200, 900);
ob_start();
imagejpeg($img);
$jpeg = ob_get_clean();
$photo = function (string $date) use ($henId, $jpeg) {
    global $port, $cookie;
    $b = '----kk' . bin2hex(random_bytes(6));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"date\"\r\n\r\n$date\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"p.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n$jpeg\r\n--$b--\r\n";
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true,
        'header' => "X-KotKot: 1\r\nCookie: $cookie\r\nContent-Type: multipart/form-data; boundary=$b", 'content' => $body]]);
    return json_decode((string) file_get_contents("http://127.0.0.1:$port/api.php?a=hen_photo&id=$henId", false, $ctx), true);
};
$photo('2026-09-01');
$r = $photo('2026-03-01');
check('album sorted by date', count($r['photos']) === 2 && $r['photos'][0]['at'] < $r['photos'][1]['at']);
$latest = $r['photos'][1]['id'];
$hen = array_values(array_filter($r['state']['hens'], fn ($h) => $h['id'] === $henId))[0];
check('portrait is the most recent photo', $hen['photo'] === $latest);
$size = getimagesizefromstring((string) file_get_contents("http://127.0.0.1:$port/api.php?a=photo&id=$latest", false,
    stream_context_create(['http' => ['header' => "Cookie: $cookie"]])));
check('photo resized to an 800 px square', $size[0] === 800 && $size[1] === 800);

// security
[$s] = req('egg', ['delta' => 1], [], null, 'application/json');
$saved = $cookie;
$cookie = '';
[$s] = req('egg', ['delta' => 1]);
check('no cookie, no change', $s === 401);
$ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true,
    'header' => "Cookie: $saved\r\nContent-Type: application/json", 'content' => '{"delta":1}']]);
file_get_contents("http://127.0.0.1:$port/api.php?a=egg", false, $ctx);
check('a cross-site form (no X-KotKot header) is refused', str_contains($http_response_header[0], '405'));
for ($i = 0; $i < 5; $i++) {
    req('login', ['pin' => '0000']);
}
[$s, $r] = req('login', ['pin' => '1234']);
check('after 5 wrong codes, even the right code waits', $s === 429);

echo $failures ? "\n$failures échec(s)\n" : "\nTout est bon.\n";
exit($failures ? 1 : 0);
