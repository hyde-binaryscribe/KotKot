<?php
declare(strict_types=1);

/*
 * Two ways in:
 *  - a family PHONE ("device"): invitation code of the household + who you are + family code (4 digits).
 *    It stays connected for a year. Cookie kk_device = "<household>.<token>".
 *  - the RESPONSIBLE ADULT's ACCOUNT: e-mail + password, to create households, invite,
 *    pay and export. Cookie kk_account, 30 days, renewed while used.
 */

const KK_DEVICE_COOKIE = 'kk_device';
const KK_ACCOUNT_COOKIE = 'kk_account';

function current_device(bool $reset = false): ?array
{
    static $cache = false;
    if ($reset) {
        $cache = false;
    }
    if ($cache !== false) {
        return $cache;
    }
    $raw = $_COOKIE[KK_DEVICE_COOKIE] ?? '';
    if (!is_string($raw)) {
        return $cache = null;
    }
    if (preg_match('/^(\d{1,9})\.([a-f0-9]{64})$/', $raw, $m)) {
        $hid = (int) $m[1];
        $token = $m[2];
    } elseif (preg_match('/^[a-f0-9]{64}$/', $raw) && central_setting('legacy_household') === '1') {
        $hid = 1; // phone connected before V2
        $token = $raw;
    } else {
        return $cache = null;
    }
    if (!household_exists($hid)) {
        return $cache = null;
    }
    use_household($hid);
    $hash = hash('sha256', $token);
    $st = db()->prepare('SELECT d.person_id, d.last_seen, p.name, p.color, p.active
        FROM devices d LEFT JOIN people p ON p.id = d.person_id WHERE d.token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) {
        use_household(null);
        return $cache = null;
    }
    if (now_ts() - (int) $row['last_seen'] > 3600) {
        db()->prepare('UPDATE devices SET last_seen = ? WHERE token_hash = ?')->execute([now_ts(), $hash]);
    }
    $person = ($row['person_id'] && (int) $row['active'] === 1)
        ? ['id' => (int) $row['person_id'], 'name' => $row['name'], 'color' => $row['color']]
        : null;
    return $cache = ['household' => $hid, 'hash' => $hash, 'person' => $person];
}

function household_exists(int $id): bool
{
    $st = central()->prepare('SELECT 1 FROM households WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$id]);
    return (bool) $st->fetchColumn();
}

function create_device(int $householdId, ?int $personId): void
{
    use_household($householdId);
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO devices (token_hash, person_id, created_at, last_seen) VALUES (?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $personId, now_ts(), now_ts()]);
    set_cookie(KK_DEVICE_COOKIE, $householdId . '.' . $token, 400 * 86400);
    current_device(true);
}

function forget_device(): void
{
    $d = current_device();
    if ($d) {
        db()->prepare('DELETE FROM devices WHERE token_hash = ?')->execute([$d['hash']]);
    }
    set_cookie(KK_DEVICE_COOKIE, '', 0);
    current_device(true);
}

// ---------------------------------------------------------------- accounts

function current_account(bool $reset = false): ?array
{
    static $cache = false;
    if ($reset) {
        $cache = false;
    }
    if ($cache !== false) {
        return $cache;
    }
    $token = $_COOKIE[KK_ACCOUNT_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return $cache = null;
    }
    $hash = hash('sha256', $token);
    $st = central()->prepare('SELECT a.id, a.email, a.has_password, s.last_seen FROM account_sessions s
        JOIN accounts a ON a.id = s.account_id WHERE s.token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row || now_ts() - (int) $row['last_seen'] > 30 * 86400) {
        return $cache = null;
    }
    if (now_ts() - (int) $row['last_seen'] > 86400) {
        // sliding session: renewed once a day while used
        central()->prepare('UPDATE account_sessions SET last_seen = ? WHERE token_hash = ?')->execute([now_ts(), $hash]);
        set_cookie(KK_ACCOUNT_COOKIE, $token, 30 * 86400);
    }
    return $cache = ['id' => (int) $row['id'], 'email' => $row['email'], 'hasPassword' => (bool) $row['has_password'], 'hash' => $hash];
}

function create_account_session(int $accountId): void
{
    $token = bin2hex(random_bytes(32));
    central()->prepare('INSERT INTO account_sessions (token_hash, account_id, created_at, last_seen) VALUES (?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $accountId, now_ts(), now_ts()]);
    central()->prepare('UPDATE accounts SET last_login = ? WHERE id = ?')->execute([now_ts(), $accountId]);
    set_cookie(KK_ACCOUNT_COOKIE, $token, 30 * 86400);
    current_account(true);
}

function end_account_session(): void
{
    $a = current_account();
    if ($a) {
        central()->prepare('DELETE FROM account_sessions WHERE token_hash = ?')->execute([$a['hash']]);
    }
    set_cookie(KK_ACCOUNT_COOKIE, '', 0);
    current_account(true);
}

/** Households this account belongs to: as their responsible adult ("owner") or as a family member. */
function account_households(int $accountId): array
{
    $st = central()->prepare('SELECT h.id, h.name, m.role, m.person_id FROM memberships m
        JOIN households h ON h.id = m.household_id
        WHERE m.account_id = ? AND h.deleted_at IS NULL ORDER BY h.id');
    $st->execute([$accountId]);
    return array_map(fn ($h) => [
        'id' => (int) $h['id'],
        'name' => $h['name'],
        'role' => $h['role'],
        'personId' => $h['person_id'] !== null ? (int) $h['person_id'] : null,
    ], $st->fetchAll());
}

function valid_email(mixed $v): bool
{
    return is_string($v) && strlen($v) <= 190 && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_password(mixed $v): bool
{
    return is_string($v) && mb_strlen($v) >= 8 && strlen($v) <= 200;
}

// ---------------------------------------------------------------- mail

function send_mail(string $to, string $subject, string $text): bool
{
    $log = getenv('KOTKOT_MAIL_LOG');
    if ($log) {
        // tests and local development: write the mail instead of sending it
        file_put_contents($log, "To: $to\nSubject: $subject\n\n$text\n----\n", FILE_APPEND);
        return true;
    }
    $from = (string) config('mail_from', 'kotkot@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $headers = [
        'From' => 'KotKot <' . $from . '>',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $text, $headers);
}

function base_url(): string
{
    $configured = (string) config('base_url', '');
    if ($configured !== '') {
        return rtrim($configured, '/') . '/';
    }
    // SERVER_NAME comes from the web server, not from the visitor (no Host-header tricks
    // in password-reset links). Set base_url in config.php to be explicit.
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    if ($port && !in_array($port, [80, 443], true)) {
        $host .= ':' . $port;
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return (is_https() ? 'https' : 'http') . '://' . $host . $dir . '/';
}
