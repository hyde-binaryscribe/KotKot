<?php
declare(strict_types=1);

final class ApiError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}

function require_auth(): array
{
    $d = current_device();
    if ($d === null) {
        throw new ApiError('Merci de taper le code de la famille.', 401);
    }
    return $d;
}

function me_id(): ?int
{
    return current_device()['person']['id'] ?? null;
}

/** A timestamp sent by the phone (it may have been offline); kept only if plausible. */
function client_time(mixed $ms): int
{
    $t = is_numeric($ms) ? intdiv((int) $ms, 1000) : 0;
    $now = now()->getTimestamp();
    return ($t > $now - 7 * 86400 && $t <= $now + 300) ? min($t, $now) : $now;
}

function client_id(mixed $v): ?string
{
    return (is_string($v) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $v)) ? $v : null;
}

const DEFAULT_TASKS = [
    ['Donner à manger', 'daily', null],
    ["Changer l'eau", 'daily', null],
    ['Ramasser les œufs', 'daily', null],
    ['Nettoyer le pondoir', 'weekly', 6],
];

/** Fills a brand-new household: people, family code, place, default tasks. Returns the first person. */
function setup_household(array $in): int
{
    $pin = $in['pin'] ?? null;
    if (!valid_pin($pin)) {
        throw new ApiError('Le code de la famille doit faire 4 chiffres.');
    }
    $names = array_values(array_filter(array_map(fn ($n) => clean_text($n, 40), (array) ($in['people'] ?? []))));
    if (!$names) {
        throw new ApiError('Ajoutez au moins un prénom.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    set_setting('pin_hash', password_hash($pin, PASSWORD_DEFAULT));
    save_location($in);
    $first = null;
    foreach ($names as $i => $name) {
        $pdo->prepare('INSERT INTO people (name, color, created_at) VALUES (?, ?, ?)')
            ->execute([$name, KK_COLORS[$i % count(KK_COLORS)], now_ts()]);
        $first ??= (int) $pdo->lastInsertId();
    }
    foreach (DEFAULT_TASKS as $i => [$label, $freq, $wd]) {
        $pdo->prepare('INSERT INTO tasks (label, frequency, weekday, sort) VALUES (?, ?, ?, ?)')
            ->execute([$label, $freq, $wd, $i]);
    }
    $pdo->commit();
    return $first;
}

function save_location(array $in): void
{
    if (array_key_exists('city', $in)) {
        set_setting('city', clean_text($in['city'], 80));
    }
    $lat = $in['lat'] ?? null;
    $lon = $in['lon'] ?? null;
    if (is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180) {
        set_setting('lat', (string) round((float) $lat, 4));
        set_setting('lon', (string) round((float) $lon, 4));
    }
}

function person_or_null(mixed $id): ?int
{
    if (!is_numeric($id)) {
        return null;
    }
    $st = db()->prepare('SELECT id FROM people WHERE id = ? AND active = 1');
    $st->execute([(int) $id]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int) $v;
}

function action_switch_person(array $in): array
{
    $d = require_auth();
    $pid = person_or_null($in['personId'] ?? null);
    if ($pid === null) {
        throw new ApiError('Personne inconnue.');
    }
    db()->prepare('UPDATE devices SET person_id = ? WHERE token_hash = ?')->execute([$pid, $d['hash']]);
    current_device(true);
    return [];
}

function action_logout(): array
{
    forget_device();
    return [];
}

function action_egg(array $in): array
{
    require_auth();
    $delta = (int) ($in['delta'] ?? 0);
    if ($delta !== 1 && $delta !== -1) {
        throw new ApiError('Valeur invalide.');
    }
    $at = client_time($in['at'] ?? null);
    $day = local_day($at);
    if ($delta === -1) {
        $st = db()->prepare('SELECT COALESCE(SUM(delta), 0) FROM eggs WHERE day = ?');
        $st->execute([$day]);
        if ((int) $st->fetchColumn() <= 0) {
            return []; // never below zero
        }
    }
    db()->prepare('INSERT OR IGNORE INTO eggs (client_id, day, delta, person_id, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([client_id($in['clientId'] ?? null), $day, $delta, me_id(), $at]);
    return [];
}

/** A past day the family may still fill in: up to two years back, never in the future. */
function egg_day(mixed $v): string
{
    $day = valid_day($v);
    $today = now()->format('Y-m-d');
    if ($day === null || $day > $today || $day < now()->modify('-730 days')->format('Y-m-d')) {
        throw new ApiError('Choisissez un jour passé (deux ans au plus).');
    }
    return $day;
}

/**
 * Calendar: « that day, we picked N eggs ». Stored as the difference with what was
 * already counted, so the history (who, when) stays and Annuler can remove just this change.
 */
function action_egg_set(array $in): array
{
    require_auth();
    $day = egg_day($in['day'] ?? null);
    $count = $in['count'] ?? null;
    if (!is_int($count) && !(is_string($count) && ctype_digit($count))) {
        throw new ApiError('Indiquez un nombre d\'œufs.');
    }
    $count = (int) $count;
    if ($count < 0 || $count > 500) {
        throw new ApiError('Ce nombre d\'œufs semble faux.');
    }
    $cid = client_id($in['clientId'] ?? null);
    if ($cid !== null) {
        $st = db()->prepare('SELECT 1 FROM eggs WHERE client_id = ?');
        $st->execute([$cid]);
        if ($st->fetchColumn()) {
            return []; // already received (sent again after being offline)
        }
    }
    $st = db()->prepare('SELECT COALESCE(SUM(delta), 0) FROM eggs WHERE day = ?');
    $st->execute([$day]);
    $delta = $count - max(0, (int) $st->fetchColumn());
    if ($delta !== 0) {
        db()->prepare('INSERT INTO eggs (client_id, day, delta, person_id, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$cid, $day, $delta, me_id(), now_ts()]);
    }
    return [];
}

/** One month of eggs for the calendar. */
function action_egg_month(array $in): array
{
    require_auth();
    $month = is_string($in['month'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $in['month']) ? $in['month'] : now()->format('Y-m');
    $st = db()->prepare('SELECT day, SUM(delta) AS n FROM eggs WHERE day LIKE ? GROUP BY day');
    $st->execute([$month . '-%']);
    $days = [];
    foreach ($st->fetchAll() as $r) {
        if ((int) $r['n'] > 0) {
            $days[$r['day']] = (int) $r['n'];
        }
    }
    return ['month' => ['month' => $month, 'days' => $days, 'total' => array_sum($days)]];
}

function action_egg_undo(array $in): array
{
    require_auth();
    $cid = client_id($in['clientId'] ?? null);
    if ($cid !== null) {
        db()->prepare('DELETE FROM eggs WHERE client_id = ?')->execute([$cid]);
    }
    return [];
}

function action_door(array $in): array
{
    require_auth();
    $action = $in['action'] ?? '';
    if ($action !== 'open' && $action !== 'close') {
        throw new ApiError('Action inconnue.');
    }
    db()->prepare('INSERT OR IGNORE INTO door_events (client_id, action, person_id, at) VALUES (?, ?, ?, ?)')
        ->execute([client_id($in['clientId'] ?? null), $action, me_id(), client_time($in['at'] ?? null)]);
    return [];
}

function action_door_undo(array $in): array
{
    require_auth();
    $cid = client_id($in['clientId'] ?? null);
    if ($cid !== null) {
        db()->prepare('DELETE FROM door_events WHERE client_id = ?')->execute([$cid]);
    }
    return [];
}

function action_task_set(array $in): array
{
    require_auth();
    $st = db()->prepare('SELECT id, frequency FROM tasks WHERE id = ? AND active = 1');
    $st->execute([(int) ($in['taskId'] ?? 0)]);
    $task = $st->fetch();
    if (!$task) {
        throw new ApiError('Tâche introuvable.', 404);
    }
    $at = client_time($in['at'] ?? null);
    $when = (new DateTimeImmutable('@' . $at))->setTimezone(tz());
    $period = $task['frequency'] === 'daily' ? $when->format('Y-m-d') : week_key($when);
    if (!empty($in['done'])) {
        db()->prepare('INSERT OR IGNORE INTO task_done (task_id, period, person_id, done_at) VALUES (?, ?, ?, ?)')
            ->execute([(int) $task['id'], $period, me_id(), $at]);
    } else {
        db()->prepare('DELETE FROM task_done WHERE task_id = ? AND period = ?')->execute([(int) $task['id'], $period]);
    }
    return [];
}

function action_task_save(array $in): array
{
    require_auth();
    $label = clean_text($in['label'] ?? '', 80);
    if ($label === '') {
        throw new ApiError('Donnez un nom à la tâche.');
    }
    $freq = ($in['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
    $wd = $freq === 'weekly' ? max(1, min(7, (int) ($in['weekday'] ?? 6))) : null;
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE tasks SET label = ?, frequency = ?, weekday = ? WHERE id = ?')->execute([$label, $freq, $wd, $id]);
    } else {
        $sort = (int) db()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM tasks')->fetchColumn();
        db()->prepare('INSERT INTO tasks (label, frequency, weekday, sort) VALUES (?, ?, ?, ?)')->execute([$label, $freq, $wd, $sort]);
    }
    return [];
}

function action_task_remove(array $in): array
{
    require_auth();
    db()->prepare('UPDATE tasks SET active = ? WHERE id = ?')
        ->execute([empty($in['restore']) ? 0 : 1, (int) ($in['id'] ?? 0)]);
    return [];
}

function action_hen_save(array $in): array
{
    require_auth();
    $name = clean_text($in['name'] ?? '', 40);
    if ($name === '') {
        throw new ApiError('Donnez un nom à la poule.');
    }
    $fields = [
        $name,
        clean_text($in['breed'] ?? '', 60),
        clean_text($in['arrived'] ?? '', 30),
        clean_text($in['mark'] ?? '', 80),
        clean_text($in['eggColor'] ?? '', 30),
    ];
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE hens SET name = ?, breed = ?, arrived = ?, mark = ?, egg_color = ? WHERE id = ?')
            ->execute([...$fields, $id]);
    } else {
        db()->prepare('INSERT INTO hens (name, breed, arrived, mark, egg_color, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([...$fields, now_ts()]);
        $id = (int) db()->lastInsertId();
    }
    return ['id' => $id];
}

function action_hen_status(array $in): array
{
    require_auth();
    $status = ($in['status'] ?? 'ok') === 'watch' ? 'watch' : 'ok';
    db()->prepare('UPDATE hens SET status = ? WHERE id = ?')->execute([$status, (int) ($in['id'] ?? 0)]);
    return [];
}

function action_hen_remove(array $in): array
{
    require_auth();
    db()->prepare('UPDATE hens SET active = ? WHERE id = ?')
        ->execute([empty($in['restore']) ? 0 : 1, (int) ($in['id'] ?? 0)]);
    return [];
}

function action_hen_notes(array $in): array
{
    require_auth();
    $st = db()->prepare('SELECT n.id, n.text, n.created_at, p.name AS who FROM hen_notes n
        LEFT JOIN people p ON p.id = n.person_id WHERE n.hen_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT 50');
    $st->execute([(int) ($in['id'] ?? 0)]);
    return ['notes' => array_map(fn ($r) => [
        'id' => (int) $r['id'], 'text' => $r['text'], 'at' => (int) $r['created_at'], 'who' => $r['who'],
    ], $st->fetchAll())];
}

function action_hen_note_add(array $in): array
{
    require_auth();
    $text = trim(mb_substr(is_string($in['text'] ?? null) ? $in['text'] : '', 0, 1000));
    if ($text === '') {
        throw new ApiError('La note est vide.');
    }
    db()->prepare('INSERT INTO hen_notes (hen_id, person_id, text, created_at) VALUES (?, ?, ?, ?)')
        ->execute([(int) ($in['id'] ?? 0), me_id(), $text, now_ts()]);
    return action_hen_notes($in);
}

function action_hen_note_remove(array $in): array
{
    require_auth();
    db()->prepare('DELETE FROM hen_notes WHERE id = ?')->execute([(int) ($in['noteId'] ?? 0)]);
    return action_hen_notes($in);
}

function photo_dir(): string
{
    return household_dir(household_id()) . '/photos';
}

function photo_path(int $photoId): string
{
    return photo_dir() . '/' . $photoId . '.jpg';
}

/** Album of a hen, oldest first, so growth reads left to right. */
function action_hen_photos(array $in): array
{
    require_auth();
    $st = db()->prepare('SELECT ph.id, ph.taken_at, p.name AS who FROM hen_photos ph
        LEFT JOIN people p ON p.id = ph.person_id WHERE ph.hen_id = ? ORDER BY ph.taken_at, ph.id');
    $st->execute([(int) ($in['id'] ?? 0)]);
    return ['photos' => array_map(fn ($r) => [
        'id' => (int) $r['id'], 'at' => (int) $r['taken_at'], 'who' => $r['who'],
    ], $st->fetchAll())];
}

function action_hen_photo(): array
{
    require_auth();
    $id = (int) ($_GET['id'] ?? 0);
    $st = db()->prepare('SELECT id FROM hens WHERE id = ?');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        throw new ApiError('Poule introuvable.', 404);
    }
    if (!is_premium()) {
        $count = db()->prepare('SELECT COUNT(*) FROM hen_photos WHERE hen_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() >= FREE_PHOTOS_PER_HEN) {
            throw new ApiError('La version gratuite garde ' . FREE_PHOTOS_PER_HEN . ' photos par poule. Passez à Premium pour un album illimité.', 402);
        }
    }
    $file = $_FILES['photo'] ?? null;
    if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > 15 * 1024 * 1024) {
        throw new ApiError('La photo n\'a pas pu être envoyée.');
    }
    $data = file_get_contents($file['tmp_name']);
    $img = $data !== false ? @imagecreatefromstring($data) : false;
    if (!$img) {
        throw new ApiError('Ce fichier n\'est pas une photo.');
    }
    $exif = function_exists('exif_read_data') ? (@exif_read_data($file['tmp_name']) ?: []) : [];
    $img = orient_image($img, (int) ($exif['Orientation'] ?? 1));

    // Date of the photo: the date chosen in the app, else the camera's date, else now.
    $takenAt = now_ts();
    $chosen = $_POST['date'] ?? '';
    if (is_string($chosen) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $chosen)) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $chosen . ' 12:00', tz());
        if ($d && $d->getTimestamp() <= now_ts() + 86400) {
            $takenAt = $d->getTimestamp();
        }
    } elseif (!empty($exif['DateTimeOriginal'])) {
        $d = DateTimeImmutable::createFromFormat('Y:m:d H:i:s', (string) $exif['DateTimeOriginal'], tz());
        if ($d && $d->getTimestamp() <= now_ts() + 86400) {
            $takenAt = $d->getTimestamp();
        }
    }

    // square crop, 800 px: light for a phone, sharp enough to compare growth
    $w = imagesx($img);
    $h = imagesy($img);
    $side = min($w, $h);
    $out = imagecreatetruecolor(800, 800);
    imagecopyresampled($out, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 800, 800, $side, $side);
    if (!is_dir(photo_dir())) {
        mkdir(photo_dir(), 0775, true);
    }
    db()->prepare('INSERT INTO hen_photos (hen_id, taken_at, person_id, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$id, $takenAt, me_id(), now_ts()]);
    $photoId = (int) db()->lastInsertId();
    if (!imagejpeg($out, photo_path($photoId), 82)) {
        db()->prepare('DELETE FROM hen_photos WHERE id = ?')->execute([$photoId]);
        throw new ApiError('La photo n\'a pas pu être enregistrée.', 500);
    }
    refresh_hen_avatar($id);
    return action_hen_photos(['id' => $id]);
}

function action_hen_photo_remove(array $in): array
{
    require_auth();
    $henId = (int) ($in['id'] ?? 0);
    $photoId = (int) ($in['photoId'] ?? 0);
    $st = db()->prepare('DELETE FROM hen_photos WHERE id = ? AND hen_id = ?');
    $st->execute([$photoId, $henId]);
    if ($st->rowCount() > 0 && is_file(photo_path($photoId))) {
        unlink(photo_path($photoId));
    }
    refresh_hen_avatar($henId);
    return action_hen_photos(['id' => $henId]);
}

/** The most recent photo is the hen's portrait. */
function refresh_hen_avatar(int $henId): void
{
    $st = db()->prepare('SELECT id FROM hen_photos WHERE hen_id = ? ORDER BY taken_at DESC, id DESC LIMIT 1');
    $st->execute([$henId]);
    db()->prepare('UPDATE hens SET photo = ? WHERE id = ?')->execute([(int) ($st->fetchColumn() ?: 0), $henId]);
}

function orient_image(GdImage $img, int $orientation): GdImage
{
    $angle = match ($orientation) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };
    return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
}

function action_settings_save(array $in): array
{
    require_auth();
    save_location($in);
    if (isset($in['reminderDelay'])) {
        set_setting('reminder_delay', (string) max(0, min(180, (int) $in['reminderDelay'])));
    }
    if (isset($in['timezone']) && in_array($in['timezone'], DateTimeZone::listIdentifiers(), true)) {
        set_setting('timezone', $in['timezone']);
    }
    return [];
}

function action_pin_change(array $in): array
{
    require_auth();
    $hash = setting('pin_hash');
    $old = $in['oldPin'] ?? null;
    if (!valid_pin($old) || $hash === null || !password_verify($old, $hash)) {
        throw new ApiError('L\'ancien code n\'est pas le bon.', 403);
    }
    if (!valid_pin($in['newPin'] ?? null)) {
        throw new ApiError('Le nouveau code doit faire 4 chiffres.');
    }
    set_setting('pin_hash', password_hash($in['newPin'], PASSWORD_DEFAULT));
    return [];
}

function action_person_save(array $in): array
{
    require_auth();
    $name = clean_text($in['name'] ?? '', 40);
    if ($name === '') {
        throw new ApiError('Donnez un prénom.');
    }
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE people SET name = ?, color = ? WHERE id = ?')
            ->execute([$name, clean_color($in['color'] ?? null, $id), $id]);
    } else {
        $n = (int) db()->query('SELECT COUNT(*) FROM people')->fetchColumn();
        db()->prepare('INSERT INTO people (name, color, created_at) VALUES (?, ?, ?)')
            ->execute([$name, clean_color($in['color'] ?? null, $n), now_ts()]);
    }
    return [];
}

function action_person_remove(array $in): array
{
    require_auth();
    $restore = !empty($in['restore']);
    if (!$restore && (int) db()->query('SELECT COUNT(*) FROM people WHERE active = 1')->fetchColumn() <= 1) {
        throw new ApiError('Il faut garder au moins une personne.');
    }
    db()->prepare('UPDATE people SET active = ? WHERE id = ?')->execute([$restore ? 1 : 0, (int) ($in['id'] ?? 0)]);
    return [];
}

// ================================================================ carnet santé

const HEALTH_KINDS = ['vermifuge', 'parasites', 'vaccin', 'soin', 'veto', 'autre'];

function valid_day(mixed $v): ?string
{
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}

function action_health_add(array $in): array
{
    require_auth();
    $kind = $in['kind'] ?? '';
    if (!in_array($kind, HEALTH_KINDS, true)) {
        throw new ApiError('Type de soin inconnu.');
    }
    $henId = null;
    if (!empty($in['henId'])) {
        $st = db()->prepare('SELECT id FROM hens WHERE id = ?');
        $st->execute([(int) $in['henId']]);
        $henId = $st->fetchColumn() ?: null;
        if ($henId === null) {
            throw new ApiError('Poule introuvable.', 404);
        }
    }
    $day = valid_day($in['day'] ?? null) ?? now()->format('Y-m-d');
    $next = valid_day($in['nextDue'] ?? null);
    if ($next !== null && $next <= $day) {
        throw new ApiError('Le prochain rappel doit être après la date du soin.');
    }
    db()->prepare('INSERT INTO health (hen_id, kind, day, next_due, note, person_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$henId, $kind, $day, $next, clean_text($in['note'] ?? '', 500), me_id(), now_ts()]);
    return [];
}

function action_health_remove(array $in): array
{
    require_auth();
    db()->prepare('DELETE FROM health WHERE id = ?')->execute([(int) ($in['id'] ?? 0)]);
    return [];
}
