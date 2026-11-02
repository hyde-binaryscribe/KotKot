<?php
declare(strict_types=1);

const KK_VERSION = '1.0.0';
define('KK_ROOT', dirname(__DIR__));
define('KK_DATA', rtrim(getenv('KOTKOT_DATA') ?: KK_ROOT . '/data', '/'));

const KK_COLORS = ['#1E3A5F', '#B5470B', '#1F6B3A', '#6B3A8A', '#8A5A12', '#5B6B57', '#9B1C4A', '#1B2419'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(KK_DATA)) {
        mkdir(KK_DATA, 0775, true);
    }
    $pdo = new PDO('sqlite:' . KK_DATA . '/kotkot.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 1) {
        $pdo->exec(<<<SQL
        CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE people (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            color TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE devices (
            token_hash TEXT PRIMARY KEY,
            person_id INTEGER REFERENCES people(id),
            created_at INTEGER NOT NULL,
            last_seen INTEGER NOT NULL
        );
        CREATE TABLE login_attempts (
            ip TEXT PRIMARY KEY,
            failures INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE eggs (
            id INTEGER PRIMARY KEY,
            client_id TEXT UNIQUE,
            day TEXT NOT NULL,
            delta INTEGER NOT NULL,
            person_id INTEGER REFERENCES people(id),
            created_at INTEGER NOT NULL
        );
        CREATE INDEX eggs_day ON eggs(day);
        CREATE TABLE tasks (
            id INTEGER PRIMARY KEY,
            label TEXT NOT NULL,
            frequency TEXT NOT NULL CHECK (frequency IN ('daily', 'weekly')),
            weekday INTEGER,
            active INTEGER NOT NULL DEFAULT 1,
            sort INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE task_done (
            task_id INTEGER NOT NULL REFERENCES tasks(id),
            period TEXT NOT NULL,
            person_id INTEGER REFERENCES people(id),
            done_at INTEGER NOT NULL,
            PRIMARY KEY (task_id, period)
        );
        CREATE TABLE hens (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            breed TEXT NOT NULL DEFAULT '',
            arrived TEXT NOT NULL DEFAULT '',
            mark TEXT NOT NULL DEFAULT '',
            egg_color TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'ok' CHECK (status IN ('ok', 'watch')),
            photo INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE hen_photos (
            id INTEGER PRIMARY KEY,
            hen_id INTEGER NOT NULL REFERENCES hens(id),
            taken_at INTEGER NOT NULL,
            person_id INTEGER REFERENCES people(id),
            created_at INTEGER NOT NULL
        );
        CREATE INDEX hen_photos_hen ON hen_photos(hen_id, taken_at);
        CREATE TABLE hen_notes (
            id INTEGER PRIMARY KEY,
            hen_id INTEGER NOT NULL REFERENCES hens(id),
            person_id INTEGER REFERENCES people(id),
            text TEXT NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE door_events (
            id INTEGER PRIMARY KEY,
            client_id TEXT UNIQUE,
            action TEXT NOT NULL CHECK (action IN ('open', 'close')),
            person_id INTEGER REFERENCES people(id),
            at INTEGER NOT NULL
        );
        PRAGMA user_version = 1;
        SQL);
    }
}

function setting(string $key, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function tz(): DateTimeZone
{
    try {
        return new DateTimeZone(setting('timezone', 'Europe/Paris'));
    } catch (Exception) {
        return new DateTimeZone('Europe/Paris');
    }
}

function now(): DateTimeImmutable
{
    $fake = getenv('KOTKOT_NOW');
    return new DateTimeImmutable($fake ?: 'now', tz());
}

function local_day(int $ts): string
{
    return (new DateTimeImmutable('@' . $ts))->setTimezone(tz())->format('Y-m-d');
}

/** Week key (ISO year + week) used to know whether a weekly task was done this week. */
function week_key(DateTimeImmutable $d): string
{
    return $d->format('o-\WW');
}

function people(bool $withInactive = false): array
{
    $sql = 'SELECT id, name, color, active FROM people' . ($withInactive ? '' : ' WHERE active = 1') . ' ORDER BY id';
    return array_map(fn ($p) => [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'color' => $p['color'],
    ], db()->query($sql)->fetchAll());
}

function clean_text(mixed $v, int $max = 200): string
{
    $s = trim(is_string($v) ? $v : '');
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    return mb_substr($s, 0, $max);
}

function clean_color(mixed $v, int $fallbackIndex = 0): string
{
    if (is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
        return strtoupper($v);
    }
    return KK_COLORS[$fallbackIndex % count(KK_COLORS)];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// ---------------------------------------------------------------- devices / auth

const KK_COOKIE = 'kk_device';

function current_device(bool $reset = false): ?array
{
    static $cache = false;
    if ($reset) {
        $cache = false;
    }
    if ($cache !== false) {
        return $cache;
    }
    $token = $_COOKIE[KK_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return $cache = null;
    }
    $hash = hash('sha256', $token);
    $st = db()->prepare('SELECT d.token_hash, d.person_id, d.last_seen, p.name, p.color, p.active
        FROM devices d LEFT JOIN people p ON p.id = d.person_id WHERE d.token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) {
        return $cache = null;
    }
    if (time() - (int) $row['last_seen'] > 3600) {
        db()->prepare('UPDATE devices SET last_seen = ? WHERE token_hash = ?')->execute([time(), $hash]);
    }
    $person = ($row['person_id'] && (int) $row['active'] === 1)
        ? ['id' => (int) $row['person_id'], 'name' => $row['name'], 'color' => $row['color']]
        : null;
    return $cache = ['hash' => $hash, 'person' => $person];
}

function set_device_cookie(string $token, int $lifetime): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(KK_COOKIE, $token, [
        'expires' => $lifetime > 0 ? time() + $lifetime : time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function create_device(?int $personId): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO devices (token_hash, person_id, created_at, last_seen) VALUES (?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $personId, time(), time()]);
    set_device_cookie($token, 400 * 86400);
    $_COOKIE[KK_COOKIE] = $token;
    current_device(true);
}

function check_pin_lock(): ?int
{
    $st = db()->prepare('SELECT locked_until FROM login_attempts WHERE ip = ?');
    $st->execute([client_ip()]);
    $until = (int) ($st->fetchColumn() ?: 0);
    return $until > time() ? $until - time() : null;
}

function register_pin_failure(): void
{
    $ip = client_ip();
    db()->prepare('INSERT INTO login_attempts (ip, failures, locked_until) VALUES (?, 1, 0)
        ON CONFLICT(ip) DO UPDATE SET failures = failures + 1')->execute([$ip]);
    $st = db()->prepare('SELECT failures FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    $failures = (int) $st->fetchColumn();
    if ($failures >= 5) {
        // 5 wrong codes: wait 5 minutes, then 15, 45...
        $wait = 300 * (3 ** min(4, intdiv($failures - 5, 5)));
        db()->prepare('UPDATE login_attempts SET locked_until = ? WHERE ip = ?')->execute([time() + $wait, $ip]);
    }
}

function clear_pin_failures(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
}

function valid_pin(mixed $pin): bool
{
    return is_string($pin) && preg_match('/^\d{4}$/', $pin) === 1;
}
