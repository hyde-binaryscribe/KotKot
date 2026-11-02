<?php
declare(strict_types=1);

/**
 * Reminders sent by tools/cron.php, and the upgrade of a V1 installation.
 *
 *   php tests/reminders_test.php
 */

$data = sys_get_temp_dir() . '/kotkot-rem-' . bin2hex(random_bytes(4));
mkdir($data);
putenv("KOTKOT_DATA=$data");
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg($data)));

$failures = 0;
function check(string $what, bool $ok): void
{
    global $failures;
    echo ($ok ? '  ok  ' : '  FAIL ') . $what . "\n";
    $failures += $ok ? 0 : 1;
}

// --- a V1 installation, as it was on disk: data/kotkot.sqlite + data/photos/
$v1 = new PDO("sqlite:$data/kotkot.sqlite");
$v1->exec(<<<SQL
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE people (id INTEGER PRIMARY KEY, name TEXT NOT NULL, color TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL);
CREATE TABLE devices (token_hash TEXT PRIMARY KEY, person_id INTEGER, created_at INTEGER NOT NULL, last_seen INTEGER NOT NULL);
CREATE TABLE login_attempts (ip TEXT PRIMARY KEY, failures INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0);
CREATE TABLE eggs (id INTEGER PRIMARY KEY, client_id TEXT UNIQUE, day TEXT NOT NULL, delta INTEGER NOT NULL, person_id INTEGER, created_at INTEGER NOT NULL);
CREATE TABLE tasks (id INTEGER PRIMARY KEY, label TEXT NOT NULL, frequency TEXT NOT NULL, weekday INTEGER, active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0);
CREATE TABLE task_done (task_id INTEGER NOT NULL, period TEXT NOT NULL, person_id INTEGER, done_at INTEGER NOT NULL, PRIMARY KEY (task_id, period));
CREATE TABLE hens (id INTEGER PRIMARY KEY, name TEXT NOT NULL, breed TEXT NOT NULL DEFAULT '', arrived TEXT NOT NULL DEFAULT '', mark TEXT NOT NULL DEFAULT '', egg_color TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'ok', photo INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL);
CREATE TABLE hen_photos (id INTEGER PRIMARY KEY, hen_id INTEGER NOT NULL, taken_at INTEGER NOT NULL, person_id INTEGER, created_at INTEGER NOT NULL);
CREATE TABLE hen_notes (id INTEGER PRIMARY KEY, hen_id INTEGER NOT NULL, person_id INTEGER, text TEXT NOT NULL, created_at INTEGER NOT NULL);
CREATE TABLE door_events (id INTEGER PRIMARY KEY, client_id TEXT UNIQUE, action TEXT NOT NULL, person_id INTEGER, at INTEGER NOT NULL);
PRAGMA user_version = 1;
SQL);
$token = str_repeat('ab', 32);
$v1->prepare('INSERT INTO settings VALUES (?, ?)')->execute(['pin_hash', password_hash('1234', PASSWORD_DEFAULT)]);
$v1->exec("INSERT INTO settings VALUES ('lat', '48.11'), ('lon', '-1.68')");
$v1->exec("INSERT INTO people (name, color, created_at) VALUES ('Léa', '#1E3A5F', 0)");
$v1->exec("INSERT INTO hens (name, created_at) VALUES ('Cocotte', 0)");
$v1->exec("INSERT INTO eggs (day, delta, created_at) VALUES ('2026-10-01', 1, 0), ('2026-10-01', 1, 0)");
$v1->prepare('INSERT INTO devices VALUES (?, 1, 0, 0)')->execute([hash('sha256', $token)]);
$v1 = null;
mkdir("$data/photos");
file_put_contents("$data/photos/1.jpg", 'jpeg');

// Monday 2 November 2026, 18:30 in Rennes: the sun has set at 17:48.
putenv('KOTKOT_NOW=2026-11-02 18:30:00 Europe/Paris');
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/state.php';
require __DIR__ . '/../src/push.php';

echo "Passage de la V1 à la V2\n";
$_COOKIE[KK_DEVICE_COOKIE] = $token; // phone connected before the upgrade
$device = current_device();
check('the V1 phone stays connected, in household no. 1', $device !== null && $device['household'] === 1 && $device['person']['name'] === 'Léa');
check('the eggs, hens and photos moved with it', (int) db()->query('SELECT SUM(delta) FROM eggs')->fetchColumn() === 2
    && db()->query('SELECT name FROM hens')->fetchColumn() === 'Cocotte' && is_file("$data/foyers/1/photos/1.jpg"));
check('the old files are gone from data/', !is_file("$data/kotkot.sqlite") && !is_dir("$data/photos"));
check('the household got an invitation code, and no owner yet', strlen((string) household()['invite_code']) === 8 && household()['owner_id'] === null);
check('the health table was added', (int) db()->query('PRAGMA user_version')->fetchColumn() === 2);

echo "Rappels\n";
$sent = [];
$GLOBALS['kk_push_sender'] = function (array $sub, array $headers, string $body) use (&$sent) {
    $sent[] = ['endpoint' => $sub['endpoint'], 'headers' => $headers, 'size' => strlen($body)];
    return str_contains($sub['endpoint'], 'gone') ? 410 : 201;
};
$ua = p256_new_key();
foreach (['https://fcm.googleapis.com/fcm/send/lea', 'https://fcm.googleapis.com/fcm/send/gone'] as $endpoint) {
    central()->prepare('INSERT INTO push_subscriptions (household_id, person_id, endpoint, p256dh, auth, created_at) VALUES (1, 1, ?, ?, ?, 0)')
        ->execute([$endpoint, b64u_encode(p256_raw_public($ua)), b64u_encode(random_bytes(16))]);
}
db()->prepare("INSERT INTO door_events (action, person_id, at) VALUES ('open', 1, ?)")->execute([strtotime('2026-11-02 08:00:00 Europe/Paris')]);

putenv('KOTKOT_NOW=2026-11-02 18:10:00 Europe/Paris');
check('18:10, the sun set at 17:48: not yet (reminder at 18:18)', run_all_reminders() === []);
putenv('KOTKOT_NOW=2026-11-02 18:20:00 Europe/Paris');
$report = run_all_reminders();
check('18:20, the door is still open: reminder sent', ($report[1] ?? []) === ['door-2026-11-02-0'] && count($sent) === 2);
check('encrypted, signed, urgent', in_array('Content-Encoding: aes128gcm', $sent[0]['headers'], true)
    && str_starts_with(implode("\n", $sent[0]['headers']), 'Content-Type') && str_contains(implode("\n", $sent[0]['headers']), 'Authorization: vapid t=')
    && in_array('Urgency: high', $sent[0]['headers'], true));
check('a phone that uninstalled the app is forgotten', (int) central()->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn() === 1);
putenv('KOTKOT_NOW=2026-11-02 18:25:00 Europe/Paris');
check('5 minutes later: no second message', run_all_reminders() === []);
putenv('KOTKOT_NOW=2026-11-02 18:50:00 Europe/Paris');
check('30 minutes later: second reminder', (run_all_reminders()[1] ?? []) === ['door-2026-11-02-1']);
use_household(1);
db()->prepare("INSERT INTO door_events (action, person_id, at) VALUES ('close', 1, ?)")->execute([now_ts()]);
putenv('KOTKOT_NOW=2026-11-02 19:20:00 Europe/Paris');
check('closed: no more reminders', run_all_reminders() === []);

use_household(1);
db()->exec("INSERT INTO health (kind, day, next_due, created_at) VALUES ('vermifuge', '2026-05-01', '2026-11-03', 0)");
putenv('KOTKOT_NOW=2026-11-03 08:00:00 Europe/Paris');
check('treatment due today: nothing before 9 am', run_all_reminders() === []);
putenv('KOTKOT_NOW=2026-11-03 09:05:00 Europe/Paris');
check('treatment due today: reminder at 9 am', (run_all_reminders()[1] ?? []) === ['health-1-0']);
putenv('KOTKOT_NOW=2026-11-04 09:05:00 Europe/Paris');
check('the next day: no repeat', run_all_reminders() === []);
putenv('KOTKOT_NOW=2026-11-06 09:05:00 Europe/Paris');
check('3 days late: a new reminder', (run_all_reminders()[1] ?? []) === ['health-1-1']);

echo $failures ? "\n$failures échec(s)\n" : "\nTout est bon.\n";
exit($failures ? 1 : 0);
