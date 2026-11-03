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
    $species = species($in['species'] ?? null);
    if ($delta === -1) {
        $st = db()->prepare("SELECT COALESCE(SUM(delta), 0) FROM eggs WHERE day = ? AND species = ? AND quality = 'bon'");
        $st->execute([$day, $species]);
        if ((int) $st->fetchColumn() <= 0) {
            return []; // never below zero
        }
    }
    db()->prepare('INSERT OR IGNORE INTO eggs (client_id, day, delta, person_id, created_at, species) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([client_id($in['clientId'] ?? null), $day, $delta, me_id(), $at, $species]);
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
    $species = species($in['species'] ?? null);
    $st = db()->prepare("SELECT COALESCE(SUM(CASE WHEN quality = 'bon' THEN delta END), 0), COALESCE(SUM(CASE WHEN quality != 'bon' THEN delta END), 0)
        FROM eggs WHERE day = ? AND species = ?");
    $st->execute([$day, $species]);
    [$good, $bad] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
    if ($count < $bad) {
        throw new ApiError("Ce jour-là, $bad œuf" . ($bad > 1 ? 's ont été notés mauvais' : ' a été noté mauvais') . ' : le total ne peut pas être plus petit.');
    }
    // the count is the day's total; bad eggs stay as they are, the difference is in good eggs
    $delta = $count - $bad - max(0, $good);
    if ($delta !== 0) {
        db()->prepare('INSERT INTO eggs (client_id, day, delta, person_id, created_at, species) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$cid, $day, $delta, me_id(), now_ts(), $species]);
    }
    return [];
}

/** One month of eggs for the calendar. */
function action_egg_month(array $in): array
{
    require_auth();
    $month = is_string($in['month'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $in['month']) ? $in['month'] : now()->format('Y-m');
    $st = db()->prepare('SELECT day, species, quality, SUM(delta) AS n FROM eggs WHERE day LIKE ? GROUP BY day, species, quality');
    $st->execute([$month . '-%']);
    $days = [];
    $bySpecies = [];
    $bad = [];
    foreach ($st->fetchAll() as $r) {
        $n = (int) $r['n'];
        if ($n > 0) {
            $days[$r['day']] = ($days[$r['day']] ?? 0) + $n;
            $bySpecies[$r['day']][$r['species']] = ($bySpecies[$r['day']][$r['species']] ?? 0) + $n;
            if ($r['quality'] !== 'bon') {
                $bad[$r['day']] = ($bad[$r['day']] ?? 0) + $n;
            }
        }
    }
    return ['month' => ['month' => $month, 'days' => $days, 'bySpecies' => $bySpecies, 'bad' => $bad,
        'badTotal' => array_sum($bad), 'total' => array_sum($days)]];
}

function action_egg_undo(array $in): array
{
    require_auth();
    $cid = client_id($in['clientId'] ?? null);
    if ($cid !== null) {
        // an evaluation that re-classified a counted egg has a second line « <id>-r »
        db()->prepare('DELETE FROM eggs WHERE client_id IN (?, ?)')->execute([$cid, $cid . '-r']);
    }
    return [];
}

/**
 * Evaluates one egg: good or bad, criteria, comment. A new egg by default;
 * with « recount », an egg already counted as good today becomes this evaluation.
 */
function action_egg_eval(array $in): array
{
    require_auth();
    $quality = ($in['quality'] ?? 'mauvais') === 'bon' ? 'bon' : 'mauvais';
    $defects = array_values(array_unique(array_filter((array) ($in['defects'] ?? []), fn ($d) => in_array($d, EGG_CRITERIA, true))));
    $note = trim(mb_substr(is_string($in['note'] ?? null) ? $in['note'] : '', 0, 500));
    if ($quality === 'bon' && !$defects && $note === '') {
        throw new ApiError('Cochez au moins un critère ou écrivez un commentaire.');
    }
    $species = species($in['species'] ?? null);
    $at = client_time($in['at'] ?? null);
    $day = isset($in['day']) ? egg_day($in['day']) : local_day($at);
    $cid = client_id($in['clientId'] ?? null) ?? bin2hex(random_bytes(8));
    $st = db()->prepare('SELECT 1 FROM eggs WHERE client_id = ?');
    $st->execute([$cid]);
    if ($st->fetchColumn()) {
        return []; // already received
    }
    $pdo = db();
    $pdo->beginTransaction();
    if (!empty($in['recount'])) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(delta), 0) FROM eggs WHERE day = ? AND species = ? AND quality = 'bon'");
        $st->execute([$day, $species]);
        if ((int) $st->fetchColumn() <= 0) {
            $pdo->rollBack();
            throw new ApiError('Aucun bon œuf compté ce jour-là à requalifier : décochez « déjà compté ».');
        }
        $pdo->prepare("INSERT INTO eggs (client_id, day, delta, person_id, created_at, species, quality) VALUES (?, ?, -1, ?, ?, ?, 'bon')")
            ->execute([$cid . '-r', $day, me_id(), $at, $species]);
    }
    $pdo->prepare('INSERT INTO eggs (client_id, day, delta, person_id, created_at, species, quality, defects, note) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)')
        ->execute([$cid, $day, me_id(), $at, $species, $quality, implode(',', $defects), $note]);
    $pdo->commit();
    return [];
}

function action_door(array $in): array
{
    require_auth();
    $action = $in['action'] ?? '';
    if ($action !== 'open' && $action !== 'close') {
        throw new ApiError('Action inconnue.');
    }
    // « chosen »: the time was picked by hand (forgot to note it): checked, never silently moved
    $at = !empty($in['chosen']) ? door_time($in['at'] ?? null) : client_time($in['at'] ?? null);
    db()->prepare('INSERT OR IGNORE INTO door_events (client_id, action, person_id, at) VALUES (?, ?, ?, ?)')
        ->execute([client_id($in['clientId'] ?? null), $action, me_id(), $at]);
    return [];
}

/** A time chosen by hand for the door (forgotten on the moment): the last 7 days, not in the future. */
function door_time(mixed $ms): int
{
    $t = is_numeric($ms) ? intdiv((int) $ms, 1000) : 0;
    if ($t < now_ts() - 7 * 86400 || $t > now_ts() + 300) {
        throw new ApiError('Choisissez une heure dans les 7 derniers jours, pas dans le futur.');
    }
    return min($t, now_ts());
}

function action_door_edit(array $in): array
{
    require_auth();
    $at = door_time($in['at'] ?? null);
    $st = db()->prepare('UPDATE door_events SET at = ? WHERE id = ?');
    $st->execute([$at, (int) ($in['id'] ?? 0)]);
    if ($st->rowCount() === 0) {
        throw new ApiError('Cette ligne n\'existe plus.', 404);
    }
    return [];
}

function action_door_remove(array $in): array
{
    require_auth();
    db()->prepare('DELETE FROM door_events WHERE id = ?')->execute([(int) ($in['id'] ?? 0)]);
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
    $period = match ($task['frequency']) {
        'weekly' => week_key($when),
        'monthly' => $when->format('Y-m'),
        'twice' => $when->format('Y-m-d') . '#' . ((int) ($in['slot'] ?? 1) === 2 ? 2 : 1),
        default => $when->format('Y-m-d'),
    };
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
    $freq = in_array($in['frequency'] ?? '', ['daily', 'twice', 'days', 'weekly', 'monthly'], true) ? $in['frequency'] : 'daily';
    $wd = $freq === 'weekly' ? max(1, min(7, (int) ($in['weekday'] ?? 6))) : null;
    $interval = $freq === 'days' ? max(2, min(60, (int) ($in['interval'] ?? 2))) : null;
    $md = $freq === 'monthly' ? max(1, min(28, (int) ($in['monthday'] ?? 1))) : null;
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE tasks SET label = ?, frequency = ?, weekday = ?, interval_days = ?, monthday = ? WHERE id = ?')
            ->execute([$label, $freq, $wd, $interval, $md, $id]);
    } else {
        $sort = (int) db()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM tasks')->fetchColumn();
        db()->prepare('INSERT INTO tasks (label, frequency, weekday, interval_days, monthday, sort) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$label, $freq, $wd, $interval, $md, $sort]);
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
        throw new ApiError('Donnez-lui un nom.');
    }
    $today = now()->format('Y-m-d');
    $date = function (string $key, string $what) use ($in, $today): ?string {
        $v = $in[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        $d = valid_day($v);
        if ($d === null || $d > $today || $d < '1990-01-01') {
            throw new ApiError("La date $what n'est pas possible.");
        }
        return $d;
    };
    $born = $date('bornOn', 'de naissance');
    $arrived = $date('arrivedOn', 'd\'arrivée');
    if ($born !== null && $arrived !== null && $arrived < $born) {
        throw new ApiError('Elle ne peut pas être arrivée avant sa naissance.');
    }
    $lays = in_array($in['lays'] ?? '', ['oui', 'non', 'inconnu'], true) ? $in['lays'] : 'inconnu';
    $fields = [
        $name,
        species($in['species'] ?? null),
        clean_text($in['breed'] ?? '', 60),
        $arrived,
        $born,
        clean_text($in['mark'] ?? '', 80),
        clean_text($in['eggColor'] ?? '', 30),
    ];
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        // « does not lay yet » → « lays »: that is her first egg, today (the Gazette will tell)
        $st = db()->prepare('SELECT lays FROM hens WHERE id = ?');
        $st->execute([$id]);
        $before = $st->fetchColumn();
        db()->prepare('UPDATE hens SET name = ?, species = ?, breed = ?, arrived_on = ?, born_on = ?, mark = ?, egg_color = ?, lays = ? WHERE id = ?')
            ->execute([...$fields, $lays, $id]);
        if ($before === 'non' && $lays === 'oui') {
            db()->prepare('UPDATE hens SET laying_since = ? WHERE id = ?')->execute([now()->format('Y-m-d'), $id]);
        }
    } else {
        db()->prepare('INSERT INTO hens (name, species, breed, arrived_on, born_on, mark, egg_color, lays, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([...$fields, $lays, now_ts()]);
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
    if (!$file) {
        // over post_max_size, PHP drops the whole request body
        throw (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            ? new ApiError('La photo est trop lourde pour le serveur (limite ' . ini_get('post_max_size') . '). Voir le guide d\'installation, étape 5.', 413)
            : new ApiError('Aucune photo reçue. Réessayez.');
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > 15 * 1024 * 1024) {
        throw new ApiError('La photo est trop lourde pour le serveur (limite ' . ini_get('upload_max_filesize') . '). Voir le guide d\'installation, étape 5.', 413);
    }
    if ($error !== UPLOAD_ERR_OK) {
        error_log("KotKot photo upload error $error");
        throw new ApiError('La photo n\'a pas pu être envoyée (erreur ' . $error . ' du serveur). Réessayez.');
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

    // the whole photo, 1600 px at most: the album shows it whole, the profile picture is framed from it
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, 1600 / max($w, $h));
    $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
    imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
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
    // the chosen profile photo is gone: back to « the latest one »
    db()->prepare('UPDATE hens SET profile_photo = NULL WHERE id = ? AND profile_photo = ?')->execute([$henId, $photoId]);
    refresh_hen_avatar($henId);
    return action_hen_photos(['id' => $henId]);
}

function avatar_path(int $henId): string
{
    return photo_dir() . '/avatar-' . $henId . '.jpg';
}

/**
 * The profile picture: the chosen photo, or else the latest one; framed as chosen
 * (if the framing was made on that photo), or else a centered square. Saved as a
 * 400 px square so lists stay light.
 */
function refresh_hen_avatar(int $henId): void
{
    $st = db()->prepare('SELECT profile_photo, crop_photo, crop FROM hens WHERE id = ?');
    $st->execute([$henId]);
    $hen = $st->fetch();
    if (!$hen) {
        return;
    }
    $photoId = 0;
    if ($hen['profile_photo']) {
        $st = db()->prepare('SELECT id FROM hen_photos WHERE id = ? AND hen_id = ?');
        $st->execute([(int) $hen['profile_photo'], $henId]);
        $photoId = (int) ($st->fetchColumn() ?: 0);
    }
    if (!$photoId) {
        $st = db()->prepare('SELECT id FROM hen_photos WHERE hen_id = ? ORDER BY taken_at DESC, id DESC LIMIT 1');
        $st->execute([$henId]);
        $photoId = (int) ($st->fetchColumn() ?: 0);
    }
    $rev = 0;
    if ($photoId && ($img = @imagecreatefromjpeg(photo_path($photoId)))) {
        $w = imagesx($img);
        $h = imagesy($img);
        $crop = (int) $hen['crop_photo'] === $photoId ? json_decode((string) $hen['crop'], true) : null;
        $side = (int) round(min($w, $h) * (is_array($crop) ? $crop['size'] : 1));
        $x = is_array($crop) ? (int) round($crop['x'] * $w) : intdiv($w - $side, 2);
        $y = is_array($crop) ? (int) round($crop['y'] * $h) : intdiv($h - $side, 2);
        $side = max(1, min($side, $w, $h));
        $x = max(0, min($x, $w - $side));
        $y = max(0, min($y, $h - $side));
        $out = imagecreatetruecolor(400, 400);
        imagecopyresampled($out, $img, 0, 0, $x, $y, 400, 400, $side, $side);
        if (!is_dir(photo_dir())) {
            mkdir(photo_dir(), 0775, true);
        }
        imagejpeg($out, avatar_path($henId), 85);
        $rev = now_ts() * 100 + random_int(0, 99);
    } elseif (is_file(avatar_path($henId))) {
        unlink(avatar_path($henId));
    }
    db()->prepare('UPDATE hens SET photo = ?, avatar_rev = ? WHERE id = ?')->execute([$photoId, $rev, $henId]);
}

/** Choose the profile photo (photoId), or go back to « always the latest » (photoId null). */
function action_hen_profile(array $in): array
{
    require_auth();
    $henId = (int) ($in['id'] ?? 0);
    $photoId = isset($in['photoId']) ? (int) $in['photoId'] : null;
    if ($photoId !== null) {
        $st = db()->prepare('SELECT 1 FROM hen_photos WHERE id = ? AND hen_id = ?');
        $st->execute([$photoId, $henId]);
        if (!$st->fetchColumn()) {
            throw new ApiError('Photo introuvable.', 404);
        }
    }
    db()->prepare('UPDATE hens SET profile_photo = ? WHERE id = ?')->execute([$photoId, $henId]);
    refresh_hen_avatar($henId);
    return [];
}

/**
 * Frame the profile picture: a square inside the photo, as fractions
 * (x, y = top-left corner of the photo's width/height; size = side / shortest side).
 */
function action_hen_crop(array $in): array
{
    require_auth();
    $henId = (int) ($in['id'] ?? 0);
    $photoId = (int) ($in['photoId'] ?? 0);
    $st = db()->prepare('SELECT 1 FROM hen_photos WHERE id = ? AND hen_id = ?');
    $st->execute([$photoId, $henId]);
    if (!$st->fetchColumn()) {
        throw new ApiError('Photo introuvable.', 404);
    }
    $num = fn ($k, $min, $max) => is_numeric($in[$k] ?? null) ? max($min, min($max, (float) $in[$k])) : throw new ApiError('Cadrage invalide.');
    $crop = ['x' => $num('x', 0, 1), 'y' => $num('y', 0, 1), 'size' => $num('size', 0.1, 1)];
    db()->prepare('UPDATE hens SET crop_photo = ?, crop = ? WHERE id = ?')->execute([$photoId, json_encode($crop), $henId]);
    refresh_hen_avatar($henId);
    return [];
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

// ================================================================ La Gazette du Poulailler

/** Facts with some history behind them, for the funny news of the coop. */
function action_gazette(): array
{
    require_auth();
    $now = now();
    $today = $now->format('Y-m-d');
    $record = db()->query("SELECT day, SUM(delta) AS n FROM eggs GROUP BY day HAVING n > 0 ORDER BY n DESC, day DESC LIMIT 1")->fetch();
    $total = (int) db()->query('SELECT COALESCE(SUM(delta), 0) FROM eggs')->fetchColumn();
    $sumBetween = function (string $from, string $to): int {
        $st = db()->prepare('SELECT COALESCE(SUM(delta), 0) FROM eggs WHERE day >= ? AND day <= ?');
        $st->execute([$from, $to]);
        return max(0, (int) $st->fetchColumn());
    };
    $monthStart = $now->format('Y-m-01');
    $prevStart = $now->modify('first day of last month')->format('Y-m-d');
    $prevSameDay = min($now->modify('first day of last month')->modify('+' . ((int) $now->format('j') - 1) . ' days')->format('Y-m-d'),
        $now->modify('last day of last month')->format('Y-m-d'));
    $st = db()->prepare('SELECT p.id, p.hen_id, p.taken_at, h.name FROM hen_photos p JOIN hens h ON h.id = p.hen_id
        WHERE h.active = 1 AND p.created_at >= ? ORDER BY p.created_at DESC LIMIT 6');
    $st->execute([now_ts() - 7 * 86400]);
    $photos = array_map(fn ($r) => ['photoId' => (int) $r['id'], 'henId' => (int) $r['hen_id'], 'name' => $r['name'], 'at' => (int) $r['taken_at']], $st->fetchAll());
    $st = db()->prepare("SELECT p.name, COUNT(*) AS n FROM task_done d JOIN people p ON p.id = d.person_id
        WHERE d.done_at >= ? GROUP BY p.id ORDER BY n DESC LIMIT 1");
    $st->execute([now_ts() - 7 * 86400]);
    $worker = $st->fetch();
    $st = db()->prepare("SELECT COUNT(*) FROM eggs WHERE delta > 0 AND quality != 'bon' AND day >= ?");
    $st->execute([$now->modify('-29 days')->format('Y-m-d')]);
    return ['gazette' => [
        'record' => $record ? ['day' => $record['day'], 'count' => (int) $record['n']] : null,
        'total' => max(0, $total),
        'month' => $sumBetween($monthStart, $today),
        'prevMonthSoFar' => $sumBetween($prevStart, $prevSameDay),
        'photos' => $photos,
        'worker' => $worker ? ['name' => $worker['name'], 'tasks' => (int) $worker['n']] : null,
        'bad30' => (int) $st->fetchColumn(),
    ]];
}
