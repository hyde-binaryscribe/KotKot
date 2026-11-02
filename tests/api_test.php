<?php
declare(strict_types=1);

/**
 * End-to-end check of the API: starts PHP's built-in server on a temporary
 * data folder and walks through families' days.
 *
 *   php tests/api_test.php
 */

$port = 8765;
$data = sys_get_temp_dir() . '/kotkot-test-' . bin2hex(random_bytes(4));
$mailLog = $data . '/mails.txt';
mkdir($data);
$root = dirname(__DIR__);
$cmd = sprintf('KOTKOT_DATA=%s KOTKOT_MAIL_LOG=%s exec php -S 127.0.0.1:%d -t %s',
    escapeshellarg($data), escapeshellarg($mailLog), $port, escapeshellarg($root . '/public'));
$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server, $data) {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($data));
});
usleep(400000);

/** One cookie jar per phone. */
$jars = [];
$failures = 0;

function http(string $jar, string $method, string $url, array $headers = [], ?string $body = null): array
{
    global $port, $jars;
    $cookies = $jars[$jar] ?? [];
    if ($cookies) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    $opts = ['http' => ['ignore_errors' => true, 'method' => $method, 'header' => implode("\r\n", $headers)]];
    if ($body !== null) {
        $opts['http']['content'] = $body;
    }
    $res = file_get_contents("http://127.0.0.1:$port/$url", false, stream_context_create($opts));
    $status = 0;
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $status = (int) $m[1];
        }
        if (preg_match('/^Set-Cookie: (kk_\w+)=([^;]*);.*?expires=([^;]+)/i', $h, $m)) {
            if ($m[2] === '' || $m[2] === 'deleted' || strtotime($m[3]) < time()) {
                unset($jars[$jar][$m[1]]);
            } else {
                $jars[$jar][$m[1]] = $m[2];
            }
        }
    }
    return [$status, (string) $res];
}

function req(string $jar, string $action, ?array $body = null, array $headers = ['X-KotKot: 1']): array
{
    if ($body === null) {
        [$s, $r] = http($jar, 'GET', "api.php?a=$action");
    } else {
        [$s, $r] = http($jar, 'POST', "api.php?a=$action", [...$headers, 'Content-Type: application/json'], json_encode($body));
    }
    return [$s, json_decode($r, true)];
}

function check(string $what, bool $ok): void
{
    global $failures;
    echo ($ok ? '  ok  ' : '  FAIL ') . $what . "\n";
    $failures += $ok ? 0 : 1;
}

function upload_photo(string $jar, int $henId, string $date): array
{
    $img = imagecreatetruecolor(1200, 900);
    ob_start();
    imagejpeg($img);
    $jpeg = ob_get_clean();
    $b = '----kk' . bin2hex(random_bytes(6));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"date\"\r\n\r\n$date\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"p.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n$jpeg\r\n--$b--\r\n";
    [$s, $r] = http($jar, 'POST', "api.php?a=hen_photo&id=$henId", ['X-KotKot: 1', "Content-Type: multipart/form-data; boundary=$b"], $body);
    return [$s, json_decode($r, true)];
}

$family = ['householdName' => 'Les Martin', 'people' => ['Léa', 'Mamie', ''], 'pin' => '1234', 'city' => 'Rennes', 'lat' => 48.11, 'lon' => -1.68];

echo "Comptes et foyers\n";
[$s, $r] = req('lea', 'state');
check('a new visitor is not connected', $s === 200 && $r['state']['auth'] === false && $r['state']['account'] === null);

[$s] = req('lea', 'account_create', ['email' => 'pas-un-email', 'password' => 'motdepasse'] + $family);
check('a bad e-mail is refused', $s === 400);
[$s] = req('lea', 'account_create', ['email' => 'lea@example.org', 'password' => 'court'] + $family);
check('a short password is refused', $s === 400);
[$s, $r] = req('lea', 'account_create', ['email' => 'lea@example.org', 'password' => 'poules-2026'] + $family);
$st = $r['state'];
check('sign-up creates the household and connects this phone', $s === 200 && $st['auth'] && $st['household']['name'] === 'Les Martin' && $st['me']['name'] === 'Léa');
check('the account holder owns it', $st['household']['isOwner'] === true && $st['account']['email'] === 'lea@example.org');
check('an 8-character invitation code exists', strlen((string) $st['household']['inviteCode']) === 8);
check('two people and the default tasks', count($st['people']) === 2 && count($st['tasks']) === 4);
check('premium included without billing (own server)', $st['household']['premium'] === true);
$invite = $st['household']['inviteCode'];
[$s] = req('autre', 'account_create', ['email' => 'LEA@example.org', 'password' => 'poules-2026'] + $family);
check('one account per e-mail (case-insensitive)', $s === 409);

echo "La vie du poulailler\n";
req('lea', 'egg', ['delta' => 1, 'clientId' => 'egg-0001-aaaa']);
req('lea', 'egg', ['delta' => 1, 'clientId' => 'egg-0001-aaaa']);
[, $r] = req('lea', 'egg', ['delta' => 1, 'clientId' => 'egg-0002-bbbb']);
check('eggs counted once per click, even if sent twice', $r['state']['eggs']['today'] === 2);
[, $r] = req('lea', 'egg_undo', ['clientId' => 'egg-0002-bbbb']);
check('Annuler removes the egg', $r['state']['eggs']['today'] === 1);
$tz = new DateTimeZone('Europe/Paris');
$ago = fn ($d) => (new DateTimeImmutable('today', new DateTimeZone('Europe/Paris')))->modify((-$d) . ' days')->format('Y-m-d');
$d3 = (new DateTimeImmutable('today', $tz))->modify('-3 days')->format('Y-m-d');
$month3 = substr($d3, 0, 7);
[$s] = req('lea', 'egg_set', ['day' => (new DateTimeImmutable('tomorrow', $tz))->format('Y-m-d'), 'count' => 4]);
check('calendar: no eggs in the future', $s === 400);
[$s] = req('lea', 'egg_set', ['day' => '2020-01-01', 'count' => 4]);
check('calendar: not more than two years back', $s === 400);
[, $r] = req('lea', 'egg_set', ['day' => $d3, 'count' => 6, 'clientId' => 'set-0001-aaaa']);
check('calendar: 6 eggs three days ago', $r['state']['eggs']['last7'] === 7 && $r['state']['eggs']['today'] === 1);
req('lea', 'egg_set', ['day' => $d3, 'count' => 6, 'clientId' => 'set-0001-aaaa']);
[, $r] = req('lea', 'egg_set', ['day' => $d3, 'count' => 5, 'clientId' => 'set-0002-bbbb']);
[, $m] = req('lea', 'egg_month', ['month' => $month3]);
check('corrected to 5, a replay counted once', $m['month']['days'][$d3] === 5 && $r['state']['eggs']['last7'] === 6);
[, $r] = req('lea', 'egg_undo', ['clientId' => 'set-0002-bbbb']);
[, $m] = req('lea', 'egg_month', ['month' => $month3]);
check('Annuler gives the 6 back', $m['month']['days'][$d3] === 6);
req('lea', 'egg_set', ['day' => $d3, 'count' => 0]);
[, $m] = req('lea', 'egg_month', ['month' => $month3]);
check('0 empties the day', !isset($m['month']['days'][$d3]));
req('lea', 'egg_set', ['day' => $d3, 'count' => 6]);

[, $r] = req('lea', 'door', ['action' => 'open', 'clientId' => 'door-0001-aaaa']);
check('door opened by Léa', $r['state']['door']['state'] === 'open' && $r['state']['door']['history'][0]['who'] === 'Léa');
$taskId = $r['state']['tasks'][0]['id'];
[, $r] = req('lea', 'task_set', ['taskId' => $taskId, 'done' => true]);
check('task done', $r['state']['tasks'][0]['done'] === true);
for ($i = 1; $i <= 9; $i++) {
    [, $r] = req('lea', 'hen_save', ['name' => "Poule $i", 'breed' => 'Rousse']);
}
check('no limit on hens', count($r['state']['hens']) === 9);
$henId = $r['id'];
[$s, $r] = req('lea', 'hen_save', ['name' => 'Caillette', 'species' => 'caille', 'bornOn' => $ago(90), 'arrivedOn' => $ago(30)]);
$quail = array_values(array_filter($r['state']['hens'], fn ($h) => $h['name'] === 'Caillette'))[0];
check('a quail, with birth and arrival dates', $s === 200 && $quail['species'] === 'caille' && $quail['bornOn'] === $ago(90) && $quail['arrivedOn'] === $ago(30));
[$s] = req('lea', 'hen_save', ['name' => 'Futur', 'bornOn' => $ago(-3)]);
check('no birth in the future', $s === 400);
[$s] = req('lea', 'hen_save', ['name' => 'Bizarre', 'bornOn' => $ago(10), 'arrivedOn' => $ago(20)]);
check('no arrival before birth', $s === 400);
[, $r] = req('lea', 'hen_save', ['name' => 'Inconnue', 'species' => 'licorne']);
check('unknown kind becomes a hen', array_values(array_filter($r['state']['hens'], fn ($h) => $h['name'] === 'Inconnue'))[0]['species'] === 'poule');
req('lea', 'egg', ['delta' => 1, 'species' => 'caille', 'clientId' => 'egg-caille-01']);
[, $r] = req('lea', 'egg', ['delta' => 1, 'species' => 'caille', 'clientId' => 'egg-caille-02']);
check('quail eggs counted apart, total includes them', $r['state']['eggs']['todayBySpecies']['caille'] === 2 && $r['state']['eggs']['today'] === 3);
[, $r] = req('lea', 'egg', ['delta' => -1, 'species' => 'poule', 'clientId' => 'egg-poule-m1']);
[, $r] = req('lea', 'egg', ['delta' => -1, 'species' => 'poule', 'clientId' => 'egg-poule-m2']);
check('−1 on hens never takes a quail egg', $r['state']['eggs']['todayBySpecies']['caille'] === 2 && ($r['state']['eggs']['todayBySpecies']['poule'] ?? 0) === 0);
req('lea', 'egg_set', ['day' => $d3, 'count' => 10, 'species' => 'caille']);
[, $m] = req('lea', 'egg_month', ['month' => $month3]);
check('calendar: per kind and total', $m['month']['bySpecies'][$d3] === ['caille' => 10, 'poule' => 6] && $m['month']['days'][$d3] === 16);
req('lea', 'hen_remove', ['id' => $quail['id']]);
req('lea', 'hen_remove', ['id' => array_values(array_filter($r['state']['hens'], fn ($h) => $h['name'] === 'Inconnue'))[0]['id']]);
upload_photo('lea', $henId, '2026-09-01');
[$s, $r] = upload_photo('lea', $henId, '2026-03-01');
check('photo album sorted by date', $s === 200 && count($r['photos']) === 2 && $r['photos'][0]['at'] < $r['photos'][1]['at']);
$photoId = $r['photos'][1]['id'];

echo "Carnet santé\n";
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
[$s] = req('lea', 'health_add', ['kind' => 'magie']);
check('unknown kind refused', $s === 400);
[$s] = req('lea', 'health_add', ['kind' => 'vermifuge', 'day' => $ago(200), 'nextDue' => $ago(220)]);
check('next reminder must be after the treatment', $s === 400);
req('lea', 'health_add', ['kind' => 'vermifuge', 'day' => $ago(200), 'nextDue' => $ago(20)]);
[, $r] = req('lea', 'health_add', ['kind' => 'parasites', 'henId' => $henId, 'day' => $ago(1), 'nextDue' => $ago(-30), 'note' => 'Poudre']);
$due = $r['state']['health']['due'];
check('overdue deworming of the whole coop shows up', count($due) === 1 && $due[0]['kind'] === 'vermifuge' && $due[0]['overdue'] && $due[0]['hen'] === null);
[, $r] = req('lea', 'health_add', ['kind' => 'vermifuge', 'day' => $today, 'nextDue' => $ago(-180)]);
check('doing it again clears the reminder', count($r['state']['health']['due']) === 0 && count($r['state']['health']['history']) === 3);

echo "Rejoindre le foyer depuis un autre téléphone\n";
[$s] = req('mamie', 'join_lookup', ['code' => 'ZZZZ2222']);
check('a wrong invitation code is refused', $s === 404);
[$s, $r] = req('mamie', 'join_lookup', ['code' => strtolower(substr($invite, 0, 4) . '-' . substr($invite, 4))]);
check('the code (any case, with a dash) shows the household and its people', $s === 200 && $r['join']['name'] === 'Les Martin' && count($r['join']['people']) === 2);
check('…but no data before the family code', !isset($r['state']['eggs']));
$mamieId = $r['join']['people'][1]['id'];
[$s] = req('mamie', 'join_login', ['code' => $invite, 'personId' => $mamieId, 'pin' => '9999']);
check('wrong family code refused', $s === 403);
[$s, $r] = req('mamie', 'join_login', ['code' => $invite, 'personId' => $mamieId, 'pin' => '1234']);
check('Mamie is in, and sees the same eggs', $s === 200 && $r['state']['me']['name'] === 'Mamie' && $r['state']['eggs']['today'] === 2);
check('Mamie has no account powers', $r['state']['account'] === null && $r['state']['household']['isOwner'] === false);
[$s] = http('mamie', 'GET', "api.php?a=export&id={$r['state']['household']['id']}");
check('only the account holder can export', $s === 401);

echo "Cloisonnement entre foyers\n";
[$s, $r] = req('voisin', 'account_create', ['email' => 'voisin@example.org', 'password' => 'voisin-2026', 'householdName' => 'Chez Paul', 'people' => ['Paul'], 'pin' => '4321']);
check('another family signs up', $s === 200 && $r['state']['household']['name'] === 'Chez Paul');
check('it starts empty', $r['state']['eggs']['today'] === 0 && $r['state']['hens'] === []);
[$s] = http('voisin', 'GET', "api.php?a=photo&id=$photoId");
check('it cannot see the other family\'s photos', $s === 404);
[$s] = req('voisin', 'hen_status', ['id' => $henId, 'status' => 'watch']);
[, $r] = req('lea', 'state');
$hen = array_values(array_filter($r['state']['hens'], fn ($h) => $h['id'] === $henId))[0];
check('nor change the other family\'s hens', $hen['status'] === 'ok');
[$s] = req('voisin', 'household_enter', ['householdId' => $r['state']['household']['id']]);
check('nor open the other family\'s household', $s === 403);

echo "Connexion, mot de passe\n";
req('lea', 'account_logout', []);
[, $r] = req('lea', 'state');
check('logging out of the account keeps the phone in the household', $r['state']['auth'] === true && $r['state']['account'] === null);
[$s] = req('lea', 'account_login', ['email' => 'lea@example.org', 'password' => 'mauvais']);
check('wrong password refused', $s === 403);
[$s, $r] = req('nouveau', 'account_login', ['email' => 'lea@example.org', 'password' => 'poules-2026']);
check('on a new phone, the account opens its only household', $s === 200 && $r['state']['auth'] === true && $r['state']['household']['name'] === 'Les Martin');
[$s, $r] = req('lea', 'password_forgot', ['email' => 'inconnu@example.org']);
check('forgotten password: same answer for unknown e-mails', $s === 200 && str_contains($r['message'], 'Si un compte existe'));
req('lea', 'password_forgot', ['email' => 'lea@example.org']);
$mail = (string) @file_get_contents($mailLog);
check('a reset e-mail is sent', preg_match('~#/nouveau-mot-de-passe/([a-f0-9]{64})~', $mail, $m) === 1 && str_contains($mail, 'To: lea@example.org'));
[$s] = req('lea', 'password_reset', ['token' => $m[1] ?? '', 'password' => 'nouveau-mdp-26']);
check('the link sets a new password', $s === 200);
[$s] = req('lea', 'password_reset', ['token' => $m[1] ?? '', 'password' => 'encore-un-26']);
check('the link works only once', $s === 410);
[$s] = req('autre', 'account_login', ['email' => 'lea@example.org', 'password' => 'poules-2026']);
check('the old password no longer works', $s === 403);
[$s, $r] = req('lea', 'state');
check('the account is connected after the reset', $r['state']['account']['email'] === 'lea@example.org');

echo "Invitation, export, suppression\n";
[, $r] = req('lea', 'invite_regenerate', []);
$newInvite = $r['state']['household']['inviteCode'];
[$s] = req('autre', 'join_lookup', ['code' => $invite]);
check('a new invitation code cancels the old one', $newInvite !== $invite && $s === 404);
$hid = $r['state']['household']['id'];
[$s, $zip] = http('lea', 'GET', "api.php?a=export&id=$hid");
$tmp = tempnam(sys_get_temp_dir(), 'kkz');
file_put_contents($tmp, $zip);
$za = new ZipArchive();
$json = $za->open($tmp) === true ? json_decode((string) $za->getFromName('kotkot.json'), true) : null;
check('export: a zip with all the data and photos', $s === 200 && count($json['hens'] ?? []) === 11 && $za->numFiles === 3 && !isset($json['reglages']['pin_hash']));
unlink($tmp);
[$s] = req('lea', 'household_delete', ['householdId' => $hid, 'password' => 'faux']);
check('deleting asks for the password', $s === 403);
[$s, $r] = req('lea', 'household_delete', ['householdId' => $hid, 'password' => 'nouveau-mdp-26']);
check('household deleted', $s === 200 && $r['state']['auth'] === false && $r['state']['account']['households'] === []);
check('its files are gone', !is_dir("$data/foyers/$hid"));
[, $r] = req('mamie', 'state');
check('Mamie\'s phone is disconnected too', $r['state']['auth'] === false);

echo "Sécurité\n";
[$s] = req('voisin', 'egg', ['delta' => 1], ['Origin: https://evil.example']);
check('a cross-site form (no X-KotKot header) is refused', $s === 405);
[$s] = req('anonyme', 'egg', ['delta' => 1]);
check('no cookie, no change', $s === 401);
[, $r] = req('voisin', 'state');
$paulInvite = $r['state']['household']['inviteCode'];
for ($i = 0; $i < 5; $i++) {
    req('intrus', 'join_login', ['code' => $paulInvite, 'pin' => sprintf('%04d', $i)]);
}
[$s] = req('intrus', 'join_login', ['code' => $paulInvite, 'pin' => '4321']);
check('after 5 wrong family codes, even the right one waits', $s === 429);
[$s] = req('voisin', 'push_subscribe', ['endpoint' => 'https://127.0.0.1/steal', 'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('A', 22)]]);
check('push: only real push services', $s === 400);
$ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$ec = openssl_pkey_get_details($ua)['ec'];
$b64u = fn ($b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
[$s] = req('voisin', 'push_subscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-1', 'keys' => ['p256dh' => $b64u("\x04" . $ec['x'] . $ec['y']), 'auth' => $b64u(random_bytes(16))]]);
check('push: a phone can subscribe', $s === 200);
[$s, $r] = req('voisin', 'state');
check('the app gets the public push key', strlen($r['state']['push']['publicKey']) === 87);

echo $failures ? "\n$failures échec(s)\n" : "\nTout est bon.\n";
exit($failures ? 1 : 0);
