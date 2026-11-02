<?php
declare(strict_types=1);

/*
 * « Continuer avec Google » / « Continuer avec Apple » (OpenID Connect, code flow).
 * Nothing to install: the ID token comes straight from the provider's token endpoint
 * over TLS, which OpenID Connect Core (3.1.3.7) accepts in place of a signature check.
 * Each provider appears only once its keys are in config.php (see config.example.php).
 */

function oauth_providers(): array
{
    $out = [];
    if (config('google_client_id') && config('google_client_secret')) {
        $out['google'] = [
            'name' => 'Google',
            'auth' => (string) config('google_auth_url', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token' => (string) config('google_token_url', 'https://oauth2.googleapis.com/token'),
            'issuers' => (array) config('google_issuers', ['https://accounts.google.com', 'accounts.google.com']),
            'client_id' => (string) config('google_client_id'),
            'scope' => 'openid email profile',
            'pkce' => true,
            'form_post' => false,
        ];
    }
    if (config('apple_client_id') && config('apple_team_id') && config('apple_key_id') && config('apple_private_key')) {
        $out['apple'] = [
            'name' => 'Apple',
            'auth' => (string) config('apple_auth_url', 'https://appleid.apple.com/auth/authorize'),
            'token' => (string) config('apple_token_url', 'https://appleid.apple.com/auth/token'),
            'issuers' => (array) config('apple_issuers', ['https://appleid.apple.com']),
            'client_id' => (string) config('apple_client_id'),
            'scope' => 'name email',
            'pkce' => false,
            'form_post' => true, // Apple sends the answer back as a POST when e-mail is asked
        ];
    }
    return $out;
}

function oauth_redirect_uri(string $provider): string
{
    return base_url() . 'oauth-' . $provider . '.php';
}

function oauth_client_secret(string $provider): string
{
    if ($provider === 'google') {
        return (string) config('google_client_secret');
    }
    // Apple: a short JWT signed with the .p8 key of the developer account
    $pem = (string) config('apple_private_key');
    if (is_file($pem)) {
        $pem = (string) file_get_contents($pem);
    }
    $key = openssl_pkey_get_private($pem);
    if ($key === false) {
        throw new RuntimeException('apple_private_key is not a valid .p8 key');
    }
    $jwt = b64u_encode(json_encode(['alg' => 'ES256', 'kid' => (string) config('apple_key_id')]))
        . '.' . b64u_encode(json_encode([
            'iss' => (string) config('apple_team_id'),
            'iat' => now_ts(),
            'exp' => now_ts() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => (string) config('apple_client_id'),
        ], JSON_UNESCAPED_SLASHES));
    openssl_sign($jwt, $der, $key, OPENSSL_ALGO_SHA256);
    return $jwt . '.' . b64u_encode(ecdsa_der_to_raw($der));
}

function oauth_cookie(string $value, int $lifetime): void
{
    // Apple answers with a cross-site POST: only a SameSite=None cookie comes back with it.
    setcookie('kk_oauth', $value, [
        'expires' => $lifetime > 0 ? now_ts() + $lifetime : 1,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => is_https() ? 'None' : 'Lax',
    ]);
}

/** GET api.php?a=oauth_start&provider=google : off to the provider's sign-in page. */
function oauth_start(string $provider): never
{
    $p = oauth_providers()[$provider] ?? null;
    if ($p === null) {
        oauth_fail('indisponible');
    }
    $state = b64u_encode(random_bytes(24));
    $nonce = b64u_encode(random_bytes(24));
    $verifier = b64u_encode(random_bytes(32));
    $device = current_device();
    central()->prepare('DELETE FROM oauth_states WHERE created_at < ?')->execute([now_ts() - 900]);
    central()->prepare('INSERT INTO oauth_states (state, provider, nonce, verifier, device_household, device_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$state, $provider, $nonce, $verifier, $device['household'] ?? null, $device['hash'] ?? null, now_ts()]);
    oauth_cookie($state, 900);
    $params = [
        'response_type' => 'code',
        'client_id' => $p['client_id'],
        'redirect_uri' => oauth_redirect_uri($provider),
        'scope' => $p['scope'],
        'state' => $state,
        'nonce' => $nonce,
    ];
    if ($p['pkce']) {
        $params['code_challenge'] = b64u_encode(hash('sha256', $verifier, true));
        $params['code_challenge_method'] = 'S256';
        $params['prompt'] = 'select_account';
    }
    if ($p['form_post']) {
        $params['response_mode'] = 'form_post';
    }
    header('Location: ' . $p['auth'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

function oauth_fail(string $reason): never
{
    oauth_cookie('', 0);
    header('Location: ' . base_url() . '#/connexion/' . rawurlencode($reason), true, 303);
    exit;
}

/** Called by public/oauth-google.php and public/oauth-apple.php when the provider sends the person back. */
function oauth_callback(string $provider): never
{
    $p = oauth_providers()[$provider] ?? null;
    if ($p === null) {
        oauth_fail('indisponible');
    }
    $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    if (!empty($in['error'])) {
        oauth_fail('annule');
    }
    $state = is_string($in['state'] ?? null) ? $in['state'] : '';
    $cookie = is_string($_COOKIE['kk_oauth'] ?? null) ? $_COOKIE['kk_oauth'] : '';
    $st = central()->prepare('SELECT * FROM oauth_states WHERE state = ? AND provider = ? AND created_at > ?');
    $st->execute([$state, $provider, now_ts() - 900]);
    $row = $st->fetch();
    central()->prepare('DELETE FROM oauth_states WHERE state = ?')->execute([$state]);
    if (!$row || $cookie === '' || !hash_equals($cookie, $state)) {
        oauth_fail('expire');
    }
    oauth_cookie('', 0);

    try {
        $claims = oauth_exchange($provider, $p, (string) ($in['code'] ?? ''), $row);
    } catch (Throwable $e) {
        error_log("KotKot $provider sign-in: " . $e->getMessage());
        oauth_fail('erreur');
    }

    $email = is_string($claims['email'] ?? null) ? trim($claims['email']) : '';
    $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';
    $device = null;
    if ($row['device_hash'] && household_exists((int) $row['device_household'])) {
        use_household((int) $row['device_household']);
        $d = db()->prepare('SELECT person_id FROM devices WHERE token_hash = ?');
        $d->execute([$row['device_hash']]);
        if (($pid = $d->fetchColumn()) !== false) {
            $device = ['household' => (int) $row['device_household'], 'hash' => $row['device_hash'],
                'person' => $pid ? ['id' => (int) $pid] : null];
        }
    }

    $accountId = oauth_account($provider, (string) $claims['sub'], $email, $verified, $device !== null);
    create_account_session($accountId);
    after_sign_in($accountId, $device);
    header('Location: ' . base_url() . '#/', true, 303);
    exit;
}

/** Trades the code for the ID token and checks it is for us, fresh, and for this attempt. */
function oauth_exchange(string $provider, array $p, string $code, array $row): array
{
    if ($code === '' || strlen($code) > 2048) {
        throw new RuntimeException('no code');
    }
    $form = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => oauth_redirect_uri($provider),
        'client_id' => $p['client_id'],
        'client_secret' => oauth_client_secret($provider),
    ];
    if ($p['pkce']) {
        $form['code_verifier'] = $row['verifier'];
    }
    $ch = curl_init($p['token']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($form),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $res = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = is_string($res) ? json_decode($res, true) : null;
    if ($status !== 200 || !is_string($json['id_token'] ?? null)) {
        throw new RuntimeException("token endpoint answered $status");
    }
    $parts = explode('.', $json['id_token']);
    $claims = count($parts) === 3 ? json_decode(b64u_decode($parts[1]), true) : null;
    if (!is_array($claims)) {
        throw new RuntimeException('unreadable id_token');
    }
    $aud = (array) ($claims['aud'] ?? []);
    if (!in_array($claims['iss'] ?? '', $p['issuers'], true)) {
        throw new RuntimeException('wrong issuer');
    }
    if (!in_array($p['client_id'], $aud, true)) {
        throw new RuntimeException('wrong audience');
    }
    if ((int) ($claims['exp'] ?? 0) < now_ts() - 60) {
        throw new RuntimeException('expired id_token');
    }
    if (!hash_equals($row['nonce'], (string) ($claims['nonce'] ?? ''))) {
        throw new RuntimeException('wrong nonce');
    }
    if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
        throw new RuntimeException('no subject');
    }
    return $claims;
}

/** The KotKot account for this Google/Apple identity: already linked, same verified e-mail, or new. */
function oauth_account(string $provider, string $subject, string $email, bool $verified, bool $onFamilyPhone): int
{
    $st = central()->prepare('SELECT account_id FROM account_identities WHERE provider = ? AND subject = ?');
    $st->execute([$provider, $subject]);
    if (($id = $st->fetchColumn()) !== false) {
        return (int) $id;
    }
    if ($email === '' || !$verified || !valid_email($email)) {
        oauth_fail('email');
    }
    $st = central()->prepare('SELECT id FROM accounts WHERE email = ?');
    $st->execute([$email]);
    $id = $st->fetchColumn();
    if ($id === false) {
        if (!config('registration', true) && !$onFamilyPhone) {
            oauth_fail('ferme');
        }
        // no password yet: the person can add one later in « Mon compte »
        central()->prepare('INSERT INTO accounts (email, password_hash, has_password, created_at) VALUES (?, ?, 0, ?)')
            ->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), now_ts()]);
        $id = central()->lastInsertId();
    }
    central()->prepare('INSERT INTO account_identities (provider, subject, account_id, email, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$provider, $subject, (int) $id, $email, now_ts()]);
    return (int) $id;
}
