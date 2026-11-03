<?php
declare(strict_types=1);

/*
 * Web Push without any library: VAPID (RFC 8292) + aes128gcm payload encryption (RFC 8291).
 * The keys of this site are created on first use and kept in the central database.
 */

function b64u_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64u_decode(string $s): string
{
    $s = strtr($s, '-_', '+/');
    return (string) base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

/** PEM public key from a raw uncompressed P-256 point (65 bytes). */
function p256_public_pem(string $raw): string
{
    $der = hex2bin(P256_SPKI_PREFIX) . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** PEM private key from a raw P-256 scalar (32 bytes). */
function p256_private_pem(string $d): string
{
    $der = hex2bin('30310201010420') . $d . hex2bin('a00a06082a8648ce3d030107');
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

function p256_raw_public(OpenSSLAsymmetricKey $key): string
{
    $ec = openssl_pkey_get_details($key)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

function p256_new_key(): OpenSSLAsymmetricKey
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($key === false) {
        throw new RuntimeException('OpenSSL cannot create P-256 keys');
    }
    return $key;
}

// ---------------------------------------------------------------- VAPID keys of this site

function vapid_keys(): array
{
    static $keys = null;
    if ($keys !== null) {
        return $keys;
    }
    $pem = central_setting('vapid_private');
    if ($pem === null) {
        $key = p256_new_key();
        openssl_pkey_export($key, $pem);
        set_central_setting('vapid_private', $pem);
    }
    $key = openssl_pkey_get_private($pem);
    return $keys = ['private' => $key, 'public' => p256_raw_public($key)];
}

function vapid_public_key(): string
{
    return b64u_encode(vapid_keys()['public']);
}

/** DER ECDSA signature -> the 64-byte r||s that JWT ES256 wants. */
function ecdsa_der_to_raw(string $der): string
{
    $pos = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);
        $int = substr($der, $pos + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

function vapid_header(string $endpoint): string
{
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $subject = (string) config('push_subject', 'mailto:' . config('mail_from', 'kotkot@localhost'));
    $jwt = b64u_encode('{"typ":"JWT","alg":"ES256"}') . '.'
        . b64u_encode(json_encode(['aud' => $aud, 'exp' => now_ts() + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));
    openssl_sign($jwt, $der, vapid_keys()['private'], OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $jwt . '.' . b64u_encode(ecdsa_der_to_raw($der)) . ', k=' . vapid_public_key();
}

// ---------------------------------------------------------------- RFC 8291 encryption

/**
 * Encrypts $payload for one browser subscription. $asPrivate and $salt are only
 * given by the tests (RFC 8291 example); normally they are fresh and random.
 */
function webpush_encrypt(string $payload, string $uaPublic, string $authSecret, ?OpenSSLAsymmetricKey $asPrivate = null, ?string $salt = null): string
{
    $as = $asPrivate ?? p256_new_key();
    $asPublic = p256_raw_public($as);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($uaPublic)), $as, 32);
    if ($shared === false) {
        throw new RuntimeException('ECDH failed');
    }
    $salt ??= random_bytes(16);

    $prkKey = hash_hmac('sha256', $shared, $authSecret, true);
    $ikm = hash_hmac('sha256', "WebPush: info\0" . $uaPublic . $asPublic . "\x01", $prkKey, true);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic . $cipher . $tag;
}

/** Only real push services: a family member must not be able to make our server call anything else. */
function push_endpoint_allowed(string $endpoint): bool
{
    $u = parse_url($endpoint);
    if (($u['scheme'] ?? '') !== 'https' || empty($u['host'])) {
        return false;
    }
    $host = strtolower($u['host']);
    foreach ((array) config('push_hosts', ['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com']) as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }
    return false;
}

/** Sends to one subscription. Returns the HTTP status (0 = network error). */
function push_send(array $sub, array $message, int $ttl = 3600): int
{
    if (!push_endpoint_allowed($sub['endpoint'])) {
        return 400;
    }
    $sender = $GLOBALS['kk_push_sender'] ?? null; // tests replace the network
    $body = webpush_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), b64u_decode($sub['p256dh']), b64u_decode($sub['auth']));
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: ' . $ttl,
        'Urgency: high',
        'Authorization: ' . vapid_header($sub['endpoint']),
    ];
    if (is_callable($sender)) {
        return (int) $sender($sub, $headers, $body);
    }
    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status;
}

/** Sends to every phone of the current household (or of one person). Forgets dead subscriptions. */
function push_household(array $message, ?int $personId = null, int $ttl = 3600): int
{
    $sql = 'SELECT * FROM push_subscriptions WHERE household_id = ?' . ($personId !== null ? ' AND person_id = ?' : '');
    $st = central()->prepare($sql);
    $st->execute($personId !== null ? [household_id(), $personId] : [household_id()]);
    $sent = 0;
    foreach ($st->fetchAll() as $sub) {
        $status = push_send($sub, $message, $ttl);
        if (in_array($status, [404, 410], true)) {
            central()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
        } elseif ($status >= 200 && $status < 300) {
            $sent++;
        }
    }
    return $sent;
}

/** Each reminder only once: returns false if it was already sent. */
function mark_notified(string $key): bool
{
    $st = central()->prepare('INSERT OR IGNORE INTO notifications_sent (household_id, key, sent_at) VALUES (?, ?, ?)');
    $st->execute([household_id(), $key, now_ts()]);
    return $st->rowCount() > 0;
}

// ---------------------------------------------------------------- API actions

function action_push_subscribe(array $in): array
{
    require_auth();
    if (!is_premium()) {
        throw new ApiError('Les rappels sur le téléphone font partie de Premium.', 402);
    }
    $endpoint = (string) ($in['endpoint'] ?? '');
    $p256dh = (string) ($in['keys']['p256dh'] ?? '');
    $auth = (string) ($in['keys']['auth'] ?? '');
    if (strlen($endpoint) > 1000 || !push_endpoint_allowed($endpoint) || strlen(b64u_decode($p256dh)) !== 65 || strlen(b64u_decode($auth)) !== 16) {
        throw new ApiError('Ce téléphone ne peut pas recevoir de rappels.');
    }
    central()->prepare('INSERT INTO push_subscriptions (household_id, person_id, endpoint, p256dh, auth, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT(endpoint) DO UPDATE SET household_id = excluded.household_id, person_id = excluded.person_id,
            p256dh = excluded.p256dh, auth = excluded.auth')
        ->execute([household_id(), me_id(), $endpoint, $p256dh, $auth, now_ts()]);
    return [];
}

function action_push_unsubscribe(array $in): array
{
    require_auth();
    central()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ? AND household_id = ?')
        ->execute([(string) ($in['endpoint'] ?? ''), household_id()]);
    return [];
}

/** Gazette rhythm = news per week: 0 never, 1 once a week, 3 a few times, 7 daily, 14 twice a day, 21 three times. */
const GAZETTE_FREQS = [0, 1, 3, 7, 14, 21];
/** Time windows for the Gazette, in minutes after midnight. */
const GAZETTE_WINDOWS = ['matin' => [480, 720], 'journee' => [600, 1140], 'apresmidi' => [780, 1140], 'soir' => [1020, 1260]];

function push_prefs_of(array $sub): array
{
    $window = array_search([(int) $sub['gazette_from'], (int) $sub['gazette_to']], GAZETTE_WINDOWS, true) ?: 'journee';
    return [
        'subscribed' => true,
        'gazette' => (int) $sub['gazette_freq'],
        'window' => $window,
        'doorRepeats' => (int) $sub['door_repeats'],
        'doorGap' => (int) $sub['door_gap'],
    ];
}

function current_subscription(array $in): array
{
    $st = central()->prepare('SELECT * FROM push_subscriptions WHERE endpoint = ? AND household_id = ?');
    $st->execute([(string) ($in['endpoint'] ?? ''), household_id()]);
    $sub = $st->fetch();
    if (!$sub) {
        throw new ApiError('Activez d\'abord les rappels sur ce téléphone.', 404);
    }
    return $sub;
}

/** Only what is sent changes: { gazette, window, doorRepeats, doorGap }. */
function action_push_prefs(array $in): array
{
    require_auth();
    $sub = current_subscription($in);
    if (array_key_exists('gazette', $in)) {
        $sub['gazette_freq'] = in_array((int) $in['gazette'], GAZETTE_FREQS, true) ? (int) $in['gazette'] : 0;
    }
    if (isset($in['window'], GAZETTE_WINDOWS[$in['window']])) {
        [$sub['gazette_from'], $sub['gazette_to']] = GAZETTE_WINDOWS[$in['window']];
    }
    if (array_key_exists('doorRepeats', $in)) {
        $sub['door_repeats'] = max(0, min(3, (int) $in['doorRepeats']));
    }
    if (array_key_exists('doorGap', $in)) {
        $sub['door_gap'] = in_array((int) $in['doorGap'], [15, 30, 60], true) ? (int) $in['doorGap'] : 30;
    }
    central()->prepare('UPDATE push_subscriptions SET gazette_freq = ?, gazette_from = ?, gazette_to = ?, door_repeats = ?, door_gap = ? WHERE id = ?')
        ->execute([$sub['gazette_freq'], $sub['gazette_from'], $sub['gazette_to'], $sub['door_repeats'], $sub['door_gap'], $sub['id']]);
    return ['push' => push_prefs_of($sub)];
}

function action_push_status(array $in): array
{
    require_auth();
    $st = central()->prepare('SELECT * FROM push_subscriptions WHERE endpoint = ? AND household_id = ?');
    $st->execute([(string) ($in['endpoint'] ?? ''), household_id()]);
    $sub = $st->fetch();
    return ['push' => $sub ? push_prefs_of($sub) : ['subscribed' => false]];
}

/**
 * The moments of a day this phone gets Gazette news (minutes after midnight), random
 * but fixed for the day, inside its window. Once a week: one day drawn per week;
 * a few times: about 3 days out of 7; 2 or 3 a day: one in each part of the window.
 */
function gazette_slots(int $subscriptionId, string $day, int $freq, int $from = 600, int $to = 1140): array
{
    if ($freq <= 0 || $to <= $from) {
        return [];
    }
    $ts = strtotime($day . ' 12:00:00 UTC');
    if ($freq === 1) {
        $week = gmdate('o-W', $ts);
        if (crc32("gazette-week|$subscriptionId|$week") % 7 + 1 !== (int) gmdate('N', $ts)) {
            return [];
        }
    } elseif ($freq < 7 && crc32("gazette|$subscriptionId|$day") % 7 >= $freq) {
        return [];
    }
    $count = max(1, intdiv($freq, 7));
    $span = intdiv($to - $from, $count);
    $slots = [];
    for ($i = 0; $i < $count; $i++) {
        $slots[] = $from + $i * $span + crc32("gazette|$subscriptionId|$day|$i") % max(1, $span);
    }
    return $slots;
}

function action_push_test(array $in): array
{
    require_auth();
    $st = central()->prepare('SELECT * FROM push_subscriptions WHERE endpoint = ? AND household_id = ?');
    $st->execute([(string) ($in['endpoint'] ?? ''), household_id()]);
    $sub = $st->fetch();
    if (!$sub) {
        throw new ApiError('Les rappels ne sont pas activés sur ce téléphone.', 404);
    }
    $status = push_send($sub, ['title' => 'KotKot', 'body' => 'Les rappels marchent sur ce téléphone !', 'url' => './#/', 'tag' => 'kotkot-test']);
    if ($status < 200 || $status >= 300) {
        throw new ApiError('Le rappel n\'est pas parti (code ' . $status . '). Réessayez plus tard.', 502);
    }
    return [];
}

// ---------------------------------------------------------------- reminders (tools/cron.php, every 5 minutes)

const HEALTH_LABELS = [
    'vermifuge' => 'Vermifuge',
    'parasites' => 'Traitement anti-poux',
    'vaccin' => 'Vaccin',
    'soin' => 'Soin',
    'veto' => 'Visite chez le vétérinaire',
    'autre' => 'Soin prévu',
];

function hhmm(int $ts): string
{
    $d = (new DateTimeImmutable('@' . $ts))->setTimezone(tz());
    return (int) $d->format('G') . ' h ' . $d->format('i');
}

/** Returns the reminders sent for the current household. */
function run_reminders(): array
{
    if (setting('pin_hash') === null || !is_premium()) {
        return [];
    }
    $sent = [];
    $now = now();
    $settings = [
        'lat' => setting('lat') !== null ? (float) setting('lat') : null,
        'lon' => setting('lon') !== null ? (float) setting('lon') : null,
        'reminderDelay' => (int) setting('reminder_delay', '30'),
    ];

    // Door still open after sunset: at the reminder time, then as many times as each phone
    // asked for (0 to 3 more), spaced as it asked (15, 30 or 60 minutes).
    $door = door_state($now, $settings);
    if ($door['shouldClose']) {
        $late = $now->getTimestamp() - $door['remindAt'];
        $subs = central()->prepare('SELECT * FROM push_subscriptions WHERE household_id = ?');
        $subs->execute([household_id()]);
        foreach ($subs->fetchAll() as $sub) {
            $round = intdiv($late, 60 * max(15, (int) $sub['door_gap']));
            if ($round > (int) $sub['door_repeats']) {
                continue;
            }
            $key = 'door-' . $now->format('Y-m-d') . '-' . $sub['id'] . '-' . $round;
            if (!mark_notified($key)) {
                continue;
            }
            $status = push_send($sub, [
                'title' => $round ? 'La porte est toujours ouverte !' : 'Pensez à fermer la porte',
                'body' => 'Le soleil s\'est couché à ' . hhmm($door['sunset']) . '. Touchez ici quand c\'est fermé.',
                'url' => './#/porte',
                'tag' => 'kotkot-door',
            ], 1800);
            if (in_array($status, [404, 410], true)) {
                central()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
            }
            $sent[] = $key;
        }
    }

    // Treatments: on the day, then every 3 days while late (3 times at most), from 9 am.
    if ((int) $now->format('G') >= 9) {
        foreach (health_due($now, 0) as $item) {
            $late = (int) (new DateTimeImmutable($item['due']))->diff(new DateTimeImmutable($now->format('Y-m-d')))->days;
            $key = 'health-' . $item['id'] . '-' . min(2, intdiv($late, 3));
            if (mark_notified($key)) {
                $who = $item['hen'] ?? 'tout le poulailler';
                push_household([
                    'title' => (HEALTH_LABELS[$item['kind']] ?? 'Soin') . ' à faire',
                    'body' => 'Pour ' . $who . ($late ? ', prévu il y a ' . $late . ' jour' . ($late > 1 ? 's' : '') : ', c\'est aujourd\'hui') . '.',
                    'url' => './#/sante',
                    'tag' => 'kotkot-health-' . $item['id'],
                ], null, 86400);
                $sent[] = $key;
            }
        }
    }

    // The Gazette: each phone that asked for it, at its random moment of the day.
    $minute = (int) $now->format('G') * 60 + (int) $now->format('i');
    $subs = central()->prepare('SELECT * FROM push_subscriptions WHERE household_id = ? AND gazette_freq > 0');
    $subs->execute([household_id()]);
    foreach ($subs->fetchAll() as $sub) {
        $slots = gazette_slots((int) $sub['id'], $now->format('Y-m-d'), (int) $sub['gazette_freq'], (int) $sub['gazette_from'], (int) $sub['gazette_to']);
        // the latest moment already reached (never a burst of late ones), and not long after the window
        $due = array_keys(array_filter($slots, fn ($m) => $minute >= $m));
        if (!$due || $minute > (int) $sub['gazette_to'] + 30) {
            continue;
        }
        $key = 'gazette-' . $sub['id'] . '-' . $now->format('Y-m-d') . '-' . max($due);
        if (!mark_notified($key)) {
            continue;
        }
        // the phone writes the headline itself (same generator as the app); this text is the fallback
        $status = push_send($sub, [
            'type' => 'gazette',
            'title' => 'La Gazette du poulailler',
            'body' => 'Du nouveau au poulailler ! Touchez pour lire.',
            'url' => './#/gazette',
            'tag' => 'kotkot-gazette',
        ], 4 * 3600);
        if (in_array($status, [404, 410], true)) {
            central()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
        }
        $sent[] = $key;
    }
    return $sent;
}

function run_all_reminders(): array
{
    $report = [];
    $ids = central()->query('SELECT id FROM households WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        use_household((int) $id);
        try {
            $sent = run_reminders();
            if ($sent) {
                $report[(int) $id] = $sent;
            }
        } catch (Throwable $e) {
            error_log("KotKot reminders, household $id: " . $e->getMessage());
        }
    }
    use_household(null);
    // keep the table small
    central()->prepare('DELETE FROM notifications_sent WHERE sent_at < ?')->execute([now_ts() - 30 * 86400]);
    return $report;
}
