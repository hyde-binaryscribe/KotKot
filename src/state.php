<?php
declare(strict_types=1);

/**
 * Everything the screens need, in one payload. A household has little data,
 * so the app simply reloads this after each change.
 */
function app_state(): array
{
    $account = current_account();
    $device = current_device();
    $out = [
        'version' => KK_VERSION,
        'billing' => billing_enabled(),
        'registration' => (bool) config('registration', true),
        'account' => $account ? [
            'email' => $account['email'],
            'hasPassword' => $account['hasPassword'],
            'households' => account_households($account['id']),
        ] : null,
        'oauth' => array_map(fn ($p) => $p['name'], oauth_providers()),
        'auth' => $device !== null,
    ];
    if ($device === null) {
        return $out;
    }

    $h = household();
    $now = now();
    $premiumUntil = max((int) $h['trial_until'], (int) $h['premium_until']);
    $out['household'] = [
        'id' => (int) $h['id'],
        'name' => $h['name'],
        'inviteCode' => $h['invite_code'],
        'hasOwner' => $h['owner_id'] !== null,
        'isOwner' => $account !== null && (int) $h['owner_id'] === $account['id'],
        'rev' => (int) $h['rev'],
        'premium' => is_premium($h),
        'trial' => billing_enabled() && (int) $h['premium_until'] <= now_ts() && (int) $h['trial_until'] > now_ts(),
        'premiumUntil' => billing_enabled() && $premiumUntil > 0 ? $premiumUntil : null,
        'freePhotos' => FREE_PHOTOS_PER_HEN,
    ];
    $out['people'] = people();
    $out['me'] = $device['person'];
    $out['today'] = $now->format('Y-m-d');
    $out['now'] = $now->getTimestamp();
    $out['settings'] = [
        'city' => setting('city', ''),
        'lat' => setting('lat') !== null ? (float) setting('lat') : null,
        'lon' => setting('lon') !== null ? (float) setting('lon') : null,
        'reminderDelay' => (int) setting('reminder_delay', '30'),
        'timezone' => tz()->getName(),
    ];
    $out['eggs'] = eggs_state($now);
    $out['door'] = door_state($now, $out['settings']);
    $out['tasks'] = tasks_state($now);
    $out['hens'] = hens_list();
    $out['health'] = health_state($now);
    $out['push'] = ['publicKey' => vapid_public_key()];
    return $out;
}

/** Treatments to plan: for each hen (or the whole coop) and each kind, the latest entry decides. */
function health_due(DateTimeImmutable $now, int $withinDays = 7): array
{
    $today = $now->format('Y-m-d');
    $limit = $now->modify("+$withinDays days")->format('Y-m-d');
    $rows = db()->query('SELECT h.id, h.hen_id, h.kind, h.day, h.next_due, hn.name AS hen
        FROM (SELECT *, ROW_NUMBER() OVER (PARTITION BY COALESCE(hen_id, 0), kind ORDER BY day DESC, id DESC) AS rn
              FROM health) h
        LEFT JOIN hens hn ON hn.id = h.hen_id
        WHERE h.rn = 1
          AND h.next_due IS NOT NULL
          AND (h.hen_id IS NULL OR hn.active = 1)
        ORDER BY h.next_due')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        if ($r['next_due'] > $limit) {
            continue;
        }
        $out[] = [
            'id' => (int) $r['id'],
            'henId' => $r['hen_id'] !== null ? (int) $r['hen_id'] : null,
            'hen' => $r['hen'],
            'kind' => $r['kind'],
            'lastDay' => $r['day'],
            'due' => $r['next_due'],
            'overdue' => $r['next_due'] < $today,
            'today' => $r['next_due'] === $today,
        ];
    }
    return $out;
}

function health_state(DateTimeImmutable $now): array
{
    $rows = db()->query('SELECT h.id, h.hen_id, h.kind, h.day, h.next_due, h.note, hn.name AS hen, p.name AS who
        FROM health h LEFT JOIN hens hn ON hn.id = h.hen_id LEFT JOIN people p ON p.id = h.person_id
        ORDER BY h.day DESC, h.id DESC LIMIT 60')->fetchAll();
    return [
        'due' => health_due($now),
        'history' => array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'henId' => $r['hen_id'] !== null ? (int) $r['hen_id'] : null,
            'hen' => $r['hen'],
            'kind' => $r['kind'],
            'day' => $r['day'],
            'nextDue' => $r['next_due'],
            'note' => $r['note'],
            'who' => $r['who'],
        ], $rows),
    ];
}

function eggs_state(DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $from = $now->modify('-29 days')->format('Y-m-d');
    $st = db()->prepare('SELECT day, SUM(delta) AS n FROM eggs WHERE day >= ? AND day <= ? GROUP BY day');
    $st->execute([$from, $today]);
    $byDay = [];
    foreach ($st->fetchAll() as $r) {
        $byDay[$r['day']] = max(0, (int) $r['n']);
    }
    $sum = function (int $days) use ($now, $byDay): int {
        $t = 0;
        for ($i = 0; $i < $days; $i++) {
            $t += $byDay[$now->modify("-$i days")->format('Y-m-d')] ?? 0;
        }
        return $t;
    };
    $last14 = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = $now->modify("-$i days")->format('Y-m-d');
        $last14[] = ['day' => $d, 'count' => $byDay[$d] ?? 0];
    }
    $st = db()->prepare('SELECT species, quality, SUM(delta) AS n FROM eggs WHERE day = ? GROUP BY species, quality');
    $st->execute([$today]);
    $todayBySpecies = [];
    $todayGoodBySpecies = [];
    $todayBad = 0;
    foreach ($st->fetchAll() as $r) {
        $n = max(0, (int) $r['n']);
        $todayBySpecies[$r['species']] = ($todayBySpecies[$r['species']] ?? 0) + $n;
        if ($r['quality'] === 'bon') {
            $todayGoodBySpecies[$r['species']] = $n;
        } else {
            $todayBad += $n;
        }
    }
    return [
        'today' => $byDay[$today] ?? 0,
        'todayBySpecies' => $todayBySpecies,
        'todayGoodBySpecies' => $todayGoodBySpecies,
        'todayBad' => $todayBad,
        'quality' => egg_quality($now),
        'last7' => $sum(7),
        'last30' => $sum(30),
        'days' => $last14,
    ];
}

/** Good and bad eggs over 30 days, the criteria seen most, the latest evaluations. */
function egg_quality(DateTimeImmutable $now): array
{
    $from = $now->modify('-29 days')->format('Y-m-d');
    $st = db()->prepare('SELECT quality, SUM(delta) AS n FROM eggs WHERE day >= ? GROUP BY quality');
    $st->execute([$from]);
    $good = 0;
    $bad = 0;
    foreach ($st->fetchAll() as $r) {
        $r['quality'] === 'bon' ? $good += max(0, (int) $r['n']) : $bad += max(0, (int) $r['n']);
    }
    $st = db()->prepare("SELECT defects FROM eggs WHERE day >= ? AND delta > 0 AND defects != ''");
    $st->execute([$from]);
    $criteria = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $list) {
        foreach (explode(',', $list) as $d) {
            $criteria[$d] = ($criteria[$d] ?? 0) + 1;
        }
    }
    arsort($criteria);
    $st = db()->prepare("SELECT e.client_id, e.day, e.species, e.quality, e.defects, e.note, e.created_at, p.name AS who
        FROM eggs e LEFT JOIN people p ON p.id = e.person_id
        WHERE e.delta > 0 AND (e.quality != 'bon' OR e.defects != '' OR e.note != '')
        ORDER BY e.day DESC, e.id DESC LIMIT 15");
    $st->execute();
    return [
        'good30' => $good,
        'bad30' => $bad,
        'criteria' => $criteria,
        'recent' => array_map(fn ($r) => [
            'clientId' => $r['client_id'],
            'day' => $r['day'],
            'species' => $r['species'],
            'quality' => $r['quality'],
            'defects' => $r['defects'] === '' ? [] : explode(',', $r['defects']),
            'note' => $r['note'],
            'who' => $r['who'],
            'at' => (int) $r['created_at'],
        ], $st->fetchAll()),
    ];
}

function sun_times(DateTimeImmutable $now, ?float $lat, ?float $lon): ?array
{
    if ($lat === null || $lon === null) {
        return null;
    }
    $info = date_sun_info($now->setTime(12, 0)->getTimestamp(), $lat, $lon);
    if (!is_int($info['sunrise'] ?? null) || !is_int($info['sunset'] ?? null)) {
        return null; // polar day or night
    }
    return ['sunrise' => $info['sunrise'], 'sunset' => $info['sunset']];
}

function door_state(DateTimeImmutable $now, array $settings): array
{
    $rows = db()->query('SELECT e.id, e.client_id, e.action, e.at, p.name AS who
        FROM door_events e LEFT JOIN people p ON p.id = e.person_id
        ORDER BY e.at DESC, e.id DESC LIMIT 10')->fetchAll();
    $history = array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'clientId' => $r['client_id'],
        'action' => $r['action'],
        'at' => (int) $r['at'],
        'who' => $r['who'],
    ], $rows);

    $state = $history ? ($history[0]['action'] === 'open' ? 'open' : 'closed') : 'unknown';
    $sun = sun_times($now, $settings['lat'], $settings['lon']);
    $t = $now->getTimestamp();
    $remindAt = $sun ? $sun['sunset'] + 60 * $settings['reminderDelay'] : null;

    return [
        'state' => $state,
        'history' => $history,
        'sunrise' => $sun['sunrise'] ?? null,
        'sunset' => $sun['sunset'] ?? null,
        'remindAt' => $remindAt,
        // still open long after sunset: the app nags until someone closes it
        'shouldClose' => $state === 'open' && $remindAt !== null && $t >= $remindAt,
        // daylight and still closed: a gentle hint, not an alarm
        'shouldOpen' => $state !== 'open' && $sun !== null && $t >= $sun['sunrise'] && $t < $sun['sunset'],
    ];
}

function tasks_state(DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $week = week_key($now);
    $weekday = (int) $now->format('N');
    $rows = db()->query('SELECT id, label, frequency, weekday FROM tasks WHERE active = 1 ORDER BY sort, id')->fetchAll();
    $st = db()->prepare('SELECT d.done_at, p.name FROM task_done d LEFT JOIN people p ON p.id = d.person_id
        WHERE d.task_id = ? AND d.period = ?');
    $out = [];
    foreach ($rows as $r) {
        $daily = $r['frequency'] === 'daily';
        $st->execute([(int) $r['id'], $daily ? $today : $week]);
        $done = $st->fetch();
        $wd = $r['weekday'] !== null ? (int) $r['weekday'] : null;
        $out[] = [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'frequency' => $r['frequency'],
            'weekday' => $wd,
            'done' => (bool) $done,
            'doneBy' => $done['name'] ?? null,
            'doneAt' => $done ? (int) $done['done_at'] : null,
            'dueToday' => $daily || $wd === null || $wd <= $weekday,
            'lateDays' => (!$daily && !$done && $wd !== null && $weekday > $wd) ? $weekday - $wd : 0,
        ];
    }
    return $out;
}

function hens_list(): array
{
    $rows = db()->query("SELECT id, name, species, breed, arrived, arrived_on, born_on, mark, egg_color, status, photo,
            profile_photo, crop_photo, crop, avatar_rev
        FROM hens WHERE active = 1 ORDER BY name COLLATE NOCASE")->fetchAll();
    return array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'species' => $r['species'],
        'breed' => $r['breed'],
        'arrived' => $r['arrived'],
        'arrivedOn' => $r['arrived_on'],
        'bornOn' => $r['born_on'],
        'mark' => $r['mark'],
        'eggColor' => $r['egg_color'],
        'status' => $r['status'],
        'photo' => (int) $r['photo'],
        'profilePhoto' => $r['profile_photo'] !== null ? (int) $r['profile_photo'] : null,
        'crop' => (int) $r['crop_photo'] === (int) $r['photo'] && $r['crop'] ? json_decode($r['crop'], true) : null,
        'avatar' => (int) $r['avatar_rev'],
    ], $rows);
}
