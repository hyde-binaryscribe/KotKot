<?php
declare(strict_types=1);

const KK_VERSION = '2.0.0';
define('KK_ROOT', dirname(__DIR__));
define('KK_DATA', rtrim(getenv('KOTKOT_DATA') ?: KK_ROOT . '/data', '/'));

const KK_COLORS = ['#1E3A5F', '#B5470B', '#1F6B3A', '#6B3A8A', '#8A5A12', '#5B6B57', '#9B1C4A', '#1B2419'];

/**
 * Settings of this installation: copy config.example.php to config.php.
 * Every key has a safe default, so KotKot also runs without the file.
 */
function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = getenv('KOTKOT_CONFIG') ?: KK_ROOT . '/config.php';
        $cfg = is_file($file) ? (array) require $file : [];
    }
    return $cfg[$key] ?? $default;
}

function now_ts(): int
{
    $fake = getenv('KOTKOT_NOW');
    return $fake ? (int) strtotime($fake) : time();
}

// ================================================================ central database
// Accounts, households, invitations, push subscriptions. One file for the whole site.

function central(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(KK_DATA)) {
        mkdir(KK_DATA, 0775, true);
    }
    $pdo = open_sqlite(KK_DATA . '/central.sqlite');
    migrate_central($pdo);
    migrate_legacy_v1($pdo);
    return $pdo;
}

function open_sqlite(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function migrate_central(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 1) {
        $pdo->exec(<<<SQL
        CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_login INTEGER
        );
        CREATE TABLE households (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            owner_id INTEGER REFERENCES accounts(id),
            invite_code TEXT UNIQUE,
            trial_until INTEGER NOT NULL DEFAULT 0,
            premium_until INTEGER NOT NULL DEFAULT 0,
            stripe_customer TEXT,
            created_at INTEGER NOT NULL,
            deleted_at INTEGER
        );
        CREATE TABLE account_sessions (
            token_hash TEXT PRIMARY KEY,
            account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
            created_at INTEGER NOT NULL,
            last_seen INTEGER NOT NULL
        );
        CREATE TABLE password_resets (
            token_hash TEXT PRIMARY KEY,
            account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
            expires_at INTEGER NOT NULL
        );
        CREATE TABLE login_attempts (
            key TEXT PRIMARY KEY,
            failures INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE push_subscriptions (
            id INTEGER PRIMARY KEY,
            household_id INTEGER NOT NULL REFERENCES households(id) ON DELETE CASCADE,
            person_id INTEGER,
            endpoint TEXT NOT NULL UNIQUE,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE notifications_sent (
            household_id INTEGER NOT NULL,
            key TEXT NOT NULL,
            sent_at INTEGER NOT NULL,
            PRIMARY KEY (household_id, key)
        );
        PRAGMA user_version = 1;
        SQL);
    }
}

/** A V1 installation had a single data/kotkot.sqlite: it becomes household no. 1. */
function migrate_legacy_v1(PDO $pdo): void
{
    $legacy = KK_DATA . '/kotkot.sqlite';
    if (!is_file($legacy) || (int) $pdo->query('SELECT COUNT(*) FROM households')->fetchColumn() > 0) {
        return;
    }
    $pdo->prepare('INSERT INTO households (id, name, invite_code, created_at) VALUES (1, ?, ?, ?)')
        ->execute(['Mon poulailler', new_invite_code(), now_ts()]);
    $dir = household_dir(1);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    foreach (['', '-wal', '-shm'] as $suffix) {
        if (is_file($legacy . $suffix)) {
            rename($legacy . $suffix, $dir . '/kotkot.sqlite' . $suffix);
        }
    }
    if (is_dir(KK_DATA . '/photos')) {
        rename(KK_DATA . '/photos', $dir . '/photos');
    }
    set_central_setting('legacy_household', '1');
}

function central_setting(string $key, ?string $default = null): ?string
{
    $st = central()->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function set_central_setting(string $key, string $value): void
{
    central()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

/** Readable, hard to guess: 8 characters without 0/O/1/I/L. */
function new_invite_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function normalize_invite_code(mixed $v): string
{
    return is_string($v) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v) ?? '') : '';
}

// ================================================================ household databases
// Each household has its own folder: data/foyers/<id>/kotkot.sqlite + photos/.
// Nothing can leak from one family to another by a forgotten WHERE clause.

$GLOBALS['kk_household'] = null;

function household_dir(int $id): string
{
    return KK_DATA . '/foyers/' . $id;
}

function use_household(?int $id): void
{
    $GLOBALS['kk_household'] = $id;
}

function household_id(): int
{
    $id = $GLOBALS['kk_household'];
    if (!is_int($id)) {
        throw new LogicException('No household selected');
    }
    return $id;
}

function household(): array
{
    $st = central()->prepare('SELECT * FROM households WHERE id = ? AND deleted_at IS NULL');
    $st->execute([household_id()]);
    $h = $st->fetch();
    if (!$h) {
        throw new LogicException('Unknown household');
    }
    return $h;
}

function db(): PDO
{
    static $pool = [];
    $id = household_id();
    if (!isset($pool[$id])) {
        $dir = household_dir($id);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pool[$id] = open_sqlite($dir . '/kotkot.sqlite');
        migrate_household($pool[$id]);
    }
    return $pool[$id];
}

function migrate_household(PDO $pdo): void
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
        $version = 1;
    }
    if ($version < 2) {
        // V2: carnet santé
        $pdo->exec(<<<SQL
        CREATE TABLE health (
            id INTEGER PRIMARY KEY,
            hen_id INTEGER REFERENCES hens(id),
            kind TEXT NOT NULL,
            day TEXT NOT NULL,
            next_due TEXT,
            note TEXT NOT NULL DEFAULT '',
            person_id INTEGER REFERENCES people(id),
            created_at INTEGER NOT NULL
        );
        CREATE INDEX health_hen ON health(hen_id, kind, day);
        PRAGMA user_version = 2;
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
    return (new DateTimeImmutable('@' . now_ts()))->setTimezone(tz());
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

function people(): array
{
    return array_map(fn ($p) => [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'color' => $p['color'],
    ], db()->query('SELECT id, name, color FROM people WHERE active = 1 ORDER BY id')->fetchAll());
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

function valid_pin(mixed $pin): bool
{
    return is_string($pin) && preg_match('/^\d{4}$/', $pin) === 1;
}

// ================================================================ Premium

/** Without billing configured (a family's own server), everything is included. */
function billing_enabled(): bool
{
    return (bool) config('billing', false);
}

function is_premium(?array $h = null): bool
{
    if (!billing_enabled()) {
        return true;
    }
    $h ??= household();
    return max((int) $h['trial_until'], (int) $h['premium_until']) > now_ts();
}

const FREE_PHOTOS_PER_HEN = 3;

// ================================================================ cookies

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function set_cookie(string $name, string $value, int $lifetime): void
{
    setcookie($name, $value, [
        'expires' => $lifetime > 0 ? now_ts() + $lifetime : 1,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($lifetime > 0) {
        $_COOKIE[$name] = $value;
    } else {
        unset($_COOKIE[$name]);
    }
}

// ================================================================ rate limiting

function check_lock(string $key): ?int
{
    $st = central()->prepare('SELECT locked_until FROM login_attempts WHERE key = ?');
    $st->execute([$key]);
    $until = (int) ($st->fetchColumn() ?: 0);
    return $until > now_ts() ? $until - now_ts() : null;
}

function register_failure(string $key): void
{
    central()->prepare('INSERT INTO login_attempts (key, failures) VALUES (?, 1)
        ON CONFLICT(key) DO UPDATE SET failures = failures + 1')->execute([$key]);
    $st = central()->prepare('SELECT failures FROM login_attempts WHERE key = ?');
    $st->execute([$key]);
    $failures = (int) $st->fetchColumn();
    if ($failures >= 5 && $failures % 5 === 0) {
        // 5 errors: wait 5 minutes, then 15, 45...
        $wait = 300 * (3 ** min(4, intdiv($failures - 5, 5)));
        central()->prepare('UPDATE login_attempts SET locked_until = ? WHERE key = ?')->execute([now_ts() + $wait, $key]);
    }
}

function clear_failures(string $key): void
{
    central()->prepare('DELETE FROM login_attempts WHERE key = ?')->execute([$key]);
}
