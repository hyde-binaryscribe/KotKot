<?php
declare(strict_types=1);

/**
 * Everyone's own account (e-mail, Google, Apple) and live sync.
 * Google and Apple are replaced by tests/mock_oidc.php.
 *
 *   php tests/accounts_test.php
 */

$port = 8775;
$mockPort = 8776;
$data = sys_get_temp_dir() . '/kotkot-acc-' . bin2hex(random_bytes(4));
mkdir($data);
$mockLog = "$data/mock.log";
$root = dirname(__DIR__);

// a throwaway Apple key, like the .p8 file of a developer account
$appleKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
openssl_pkey_export($appleKey, $applePem);
file_put_contents("$data/apple.p8", $applePem);
$applePublic = openssl_pkey_get_details($appleKey)['key'];
file_put_contents("$data/config.php", '<?php return ' . var_export([
    'base_url' => "http://127.0.0.1:$port/",
    'google_client_id' => 'kotkot-test.apps.googleusercontent.com',
    'google_client_secret' => 'google-secret',
    'google_auth_url' => 'https://accounts.example/auth',
    'google_token_url' => "http://127.0.0.1:$mockPort/token",
    'apple_client_id' => 'fr.exemple.kotkot',
    'apple_team_id' => 'TEAM123456',
    'apple_key_id' => 'KEY1234567',
    'apple_private_key' => "$data/apple.p8",
    'apple_token_url' => "http://127.0.0.1:$mockPort/token",
], true) . ';');

$env = sprintf('KOTKOT_DATA=%s KOTKOT_CONFIG=%s KOTKOT_MOCK_LOG=%s', escapeshellarg($data), escapeshellarg("$data/config.php"), escapeshellarg($mockLog));
$procs = [
    proc_open("$env exec php -S 127.0.0.1:$port -t " . escapeshellarg("$root/public"), [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p1),
    proc_open("$env exec php -S 127.0.0.1:$mockPort " . escapeshellarg("$root/tests/mock_oidc.php"), [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p2),
];
register_shutdown_function(function () use ($procs, $data) {
    array_map('proc_terminate', $procs);
    exec('rm -rf ' . escapeshellarg($data));
});
usleep(500000);

$jars = [];
$failures = 0;

function http(string $jar, string $method, string $url, array $headers = [], ?string $body = null, ?array $only = null): array
{
    global $port, $jars;
    $cookies = $jars[$jar] ?? [];
    if ($only !== null) {
        $cookies = array_intersect_key($cookies, array_flip($only));
    }
    if ($cookies) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    $opts = ['http' => ['ignore_errors' => true, 'method' => $method, 'header' => implode("\r\n", $headers), 'follow_location' => 0]];
    if ($body !== null) {
        $opts['http']['content'] = $body;
    }
    $res = file_get_contents("http://127.0.0.1:$port/$url", false, stream_context_create($opts));
    $status = 0;
    $location = null;
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $status = (int) $m[1];
        }
        if (preg_match('/^Location: (.+)$/i', $h, $m)) {
            $location = trim($m[1]);
        }
        if (preg_match('/^Set-Cookie: (kk_\w+)=([^;]*);.*?expires=([^;]+)/i', $h, $m)) {
            if ($m[2] === '' || $m[2] === 'deleted' || strtotime($m[3]) < time()) {
                unset($jars[$jar][$m[1]]);
            } else {
                $jars[$jar][$m[1]] = $m[2];
            }
        }
    }
    return [$status, (string) $res, $location];
}

function req(string $jar, string $action, ?array $body = null): array
{
    [$s, $r] = $body === null
        ? http($jar, 'GET', "api.php?a=$action")
        : http($jar, 'POST', "api.php?a=$action", ['X-KotKot: 1', 'Content-Type: application/json'], json_encode($body));
    return [$s, json_decode($r, true)];
}

function check(string $what, bool $ok): void
{
    global $failures;
    echo ($ok ? '  ok  ' : '  FAIL ') . $what . "\n";
    $failures += $ok ? 0 : 1;
}

$b64u = fn (string $b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');

/** Runs a whole « Continuer avec … » on a phone: start, provider answer, back to KotKot. */
function sign_in_with(string $jar, string $provider, array $claims, bool $crossSitePost = false): array
{
    global $b64u;
    [$s, , $loc] = http($jar, 'GET', "api.php?a=oauth_start&provider=$provider");
    parse_str((string) parse_url((string) $loc, PHP_URL_QUERY), $q);
    $claims += ['nonce' => $q['nonce'] ?? '', 'exp' => time() + 600];
    $code = $b64u(json_encode($claims));
    if ($crossSitePost) {
        // Apple posts back from its own site: the browser only sends the SameSite=None cookie
        return [$s, $q, http($jar, 'POST', "oauth-$provider.php", ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['code' => $code, 'state' => $q['state'] ?? '']), ['kk_oauth'])];
    }
    return [$s, $q, http($jar, 'GET', "oauth-$provider.php?" . http_build_query(['code' => $code, 'state' => $q['state'] ?? '']))];
}

$family = ['householdName' => 'Les Martin', 'people' => ['Léa', 'Mamie'], 'pin' => '1234'];

echo "Chacun son compte\n";
[, $r] = req('lea', 'account_create', ['email' => 'lea@example.org', 'password' => 'poules-2026'] + $family);
$hid = $r['state']['household']['id'];
$invite = $r['state']['household']['inviteCode'];
check('the owner is linked to her person', $r['state']['account']['households'] === [['id' => $hid, 'name' => 'Les Martin', 'role' => 'owner', 'personId' => 1]]);
check('Google and Apple are offered once configured', $r['state']['oauth'] === ['google' => 'Google', 'apple' => 'Apple']);

[, $r] = req('mamie-tel', 'join_lookup', ['code' => $invite]);
req('mamie-tel', 'join_login', ['code' => $invite, 'personId' => 2, 'pin' => '1234']);
[$s, $r] = req('mamie-tel', 'account_register', ['email' => 'mamie@example.org', 'password' => 'tricot-1950']);
check('Mamie creates her account on her phone: linked to Mamie', $s === 200 && $r['state']['account']['households'][0]['personId'] === 2
    && $r['state']['account']['households'][0]['role'] === 'member');
check('…she is not responsible', $r['state']['household']['isOwner'] === false);

[$s, $r] = req('mamie-tablette', 'account_login', ['email' => 'mamie@example.org', 'password' => 'tricot-1950']);
check('on her tablet, her account is enough: in as Mamie, no codes', $s === 200 && $r['state']['auth'] && $r['state']['me']['name'] === 'Mamie');
[$s] = http('mamie-tablette', 'GET', "api.php?a=export&id=$hid");
check('a member cannot export', $s === 403);
[$s] = req('mamie-tablette', 'household_delete', ['householdId' => $hid, 'password' => 'tricot-1950']);
check('nor delete the household', $s === 403);

[, $r] = req('papi-tel', 'account_register', ['email' => 'papi@example.org', 'password' => 'jardin-1948']);
check('an account alone, without household, is possible', $r['state']['auth'] === false && $r['state']['account']['households'] === []);
req('papi-tel', 'join_lookup', ['code' => $invite]);
[, $r] = req('papi-tel', 'join_login', ['code' => $invite, 'personId' => 1, 'pin' => '1234']);
check('joining while signed in links the account', count($r['state']['account']['households']) === 1);
[, $r] = req('papi-tel', 'membership_leave', ['householdId' => $hid]);
check('a member can stop following a household', $r['state']['account']['households'] === []);
[$s] = req('lea', 'membership_leave', ['householdId' => $hid]);
check('the owner cannot just leave', $s === 409);

echo "Synchro en direct\n";
[, $r] = req('lea', 'rev');
$rev = $r['rev'];
req('lea', 'state');
[, $r] = req('lea', 'rev');
check('reading changes nothing', $r['rev'] === $rev);
req('mamie-tel', 'egg', ['delta' => 1, 'clientId' => 'egg-mamie-0001']);
[, $r] = req('lea', 'rev');
check('Mamie adds an egg: Léa\'s phone sees the counter move', $r['rev'] === $rev + 1);
[, $r] = req('lea', 'state');
check('…and gets the egg', $r['state']['eggs']['today'] === 1 && $r['state']['household']['rev'] === $rev + 1);
[$s] = req('inconnu', 'rev');
check('the counter is private', $s === 401);

echo "Continuer avec Google\n";
[$s, $q, [$s2, , $loc]] = sign_in_with('nouveau-tel', 'google', ['iss' => 'https://accounts.google.com', 'aud' => 'kotkot-test.apps.googleusercontent.com', 'sub' => 'g-111', 'email' => 'lea@example.org', 'email_verified' => true]);
check('off to Google with state, nonce and PKCE', $s === 302 && strlen($q['state']) > 20 && $q['code_challenge_method'] === 'S256'
    && $q['redirect_uri'] === "http://127.0.0.1:$port/oauth-google.php" && $q['scope'] === 'openid email profile');
check('back in KotKot', $s2 === 303 && str_ends_with((string) $loc, '#/'));
$sent = json_decode(trim((string) shell_exec('tail -1 ' . escapeshellarg($mockLog))), true);
check('KotKot proved the PKCE verifier and its secret', ($sent['post']['client_secret'] ?? '') === 'google-secret'
    && $b64u(hash('sha256', $sent['post']['code_verifier'] ?? '', true)) === $q['code_challenge']);
[, $r] = req('nouveau-tel', 'state');
check('same verified e-mail: Léa\'s existing account, her household opened', $r['state']['account']['email'] === 'lea@example.org' && $r['state']['auth'] && $r['state']['me']['name'] === 'Léa');
req('nouveau-tel', 'account_logout', []);
sign_in_with('nouveau-tel', 'google', ['iss' => 'https://accounts.google.com', 'aud' => 'kotkot-test.apps.googleusercontent.com', 'sub' => 'g-111', 'email' => 'autre@example.org', 'email_verified' => true]);
[, $r] = req('nouveau-tel', 'state');
check('next time, recognised by Google id even if the e-mail changed', $r['state']['account']['email'] === 'lea@example.org');

[, , [$s, , $loc]] = sign_in_with('pirate', 'google', ['iss' => 'https://accounts.google.com', 'aud' => 'autre-appli', 'sub' => 'g-999', 'email' => 'lea@example.org', 'email_verified' => true]);
[, $r] = req('pirate', 'state');
check('a token made for another app is refused', str_contains((string) $loc, '#/connexion/erreur') && $r['state']['account'] === null);
[, , [, , $loc]] = sign_in_with('pirate', 'google', ['iss' => 'https://accounts.google.com', 'aud' => 'kotkot-test.apps.googleusercontent.com', 'sub' => 'g-998', 'email' => 'lea@example.org', 'email_verified' => false]);
check('an unverified e-mail cannot take over an account', str_contains((string) $loc, '#/connexion/email'));
[, $q] = sign_in_with('pirate', 'google', ['iss' => 'https://accounts.google.com', 'aud' => 'kotkot-test.apps.googleusercontent.com', 'sub' => 'g-997', 'email' => 'x@example.org', 'email_verified' => true]);
[, , $loc] = http('victime', 'GET', 'oauth-google.php?' . http_build_query(['code' => 'x', 'state' => $q['state']]));
check('a sign-in link sent to someone else does not work (state tied to the browser)', str_contains((string) $loc, '#/connexion/expire'));
[, , $loc] = http('pirate', 'GET', 'oauth-google.php?' . http_build_query(['code' => 'x', 'state' => $q['state']]));
check('a sign-in answer works only once', str_contains((string) $loc, '#/connexion/expire'));

echo "Continuer avec Apple\n";
[, $r] = req('papi-tel', 'state');
check('Papi\'s phone is still in the household, without account link', $r['state']['auth'] === true);
req('papi-tel', 'account_logout', []);
[$s, $q, [$s2, , $loc]] = sign_in_with('papi-tel', 'apple', ['iss' => 'https://appleid.apple.com', 'aud' => 'fr.exemple.kotkot', 'sub' => 'a-222', 'email' => 'abc@privaterelay.appleid.com', 'email_verified' => 'true'], true);
check('Apple: answer posted back (form_post)', $s === 302 && $q['response_mode'] === 'form_post' && !isset($q['code_challenge']) && $s2 === 303);
$sent = json_decode(trim((string) shell_exec('tail -1 ' . escapeshellarg($mockLog))), true);
[$h64, $p64, $s64] = explode('.', (string) ($sent['post']['client_secret'] ?? '..'));
$claims = json_decode(base64_decode(strtr($p64, '-_', '+/')), true);
$raw = base64_decode(strtr($s64, '-_', '+/'));
$int = fn ($b) => (ord(ltrim($b, "\0")[0] ?? "\0") & 0x80 ? "\0" : '') . ltrim($b, "\0");
$r1 = $int(substr($raw, 0, 32));
$s1 = $int(substr($raw, 32));
$der = "\x30" . chr(4 + strlen($r1) + strlen($s1)) . "\x02" . chr(strlen($r1)) . $r1 . "\x02" . chr(strlen($s1)) . $s1;
check('Apple client secret: a JWT signed with the .p8 key', ($claims['iss'] ?? '') === 'TEAM123456' && ($claims['sub'] ?? '') === 'fr.exemple.kotkot'
    && openssl_verify("$h64.$p64", $der, $applePublic, OPENSSL_ALGO_SHA256) === 1);
[, $r] = req('papi-tel', 'state');
check('new account (Apple private e-mail), linked to the phone\'s household', $r['state']['account']['email'] === 'abc@privaterelay.appleid.com'
    && $r['state']['account']['hasPassword'] === false && count($r['state']['account']['households']) === 1);
[$s] = req('papi-tel', 'password_change', ['newPassword' => 'mon-premier-mdp']);
check('an Apple account can add a password without an old one', $s === 200);
[$s] = req('autre-tel', 'account_login', ['email' => 'abc@privaterelay.appleid.com', 'password' => 'mon-premier-mdp']);
check('…and then sign in with it', $s === 200);

echo $failures ? "\n$failures échec(s)\n" : "\nTout est bon.\n";
exit($failures ? 1 : 0);
