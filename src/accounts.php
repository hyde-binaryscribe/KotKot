<?php
declare(strict_types=1);

// ================================================================ create / sign in

function require_account(): array
{
    $a = current_account();
    if ($a === null) {
        throw new ApiError('Connectez-vous avec votre e-mail.', 401);
    }
    return $a;
}

/** The signed-in account must own this household. */
function require_owner(int $householdId): array
{
    $a = require_account();
    $st = central()->prepare('SELECT * FROM households WHERE id = ? AND owner_id = ? AND deleted_at IS NULL');
    $st->execute([$householdId, $a['id']]);
    $h = $st->fetch();
    if (!$h) {
        throw new ApiError('Ce foyer n\'est pas à vous.', 403);
    }
    return $h;
}

function new_household(string $name, ?int $ownerId): int
{
    $trial = billing_enabled() ? now_ts() + (int) config('trial_days', 30) * 86400 : 0;
    for ($i = 0; $i < 5; $i++) {
        try {
            central()->prepare('INSERT INTO households (name, owner_id, invite_code, trial_until, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $ownerId, new_invite_code(), $trial, now_ts()]);
            return (int) central()->lastInsertId();
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'UNIQUE')) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Could not create an invite code');
}

function household_name(mixed $v): string
{
    $name = clean_text($v, 60);
    if ($name === '') {
        throw new ApiError('Donnez un nom à votre poulailler.');
    }
    return $name;
}

/** Sign up: account + first household + this phone, in one go. */
function action_account_create(array $in): array
{
    if (!config('registration', true)) {
        throw new ApiError('Les inscriptions sont fermées sur ce site.', 403);
    }
    $key = 'signup:' . client_ip();
    if (($wait = check_lock($key)) !== null) {
        throw new ApiError('Trop de créations de compte. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
    }
    $email = trim((string) ($in['email'] ?? ''));
    if (!valid_email($email)) {
        throw new ApiError('Cette adresse e-mail ne semble pas correcte.');
    }
    if (!valid_password($in['password'] ?? null)) {
        throw new ApiError('Le mot de passe doit faire au moins 8 caractères.');
    }
    $name = household_name($in['householdName'] ?? '');
    $st = central()->prepare('SELECT 1 FROM accounts WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) {
        throw new ApiError('Un compte existe déjà avec cet e-mail. Connectez-vous.', 409);
    }
    // validate the household part before writing anything
    if (!valid_pin($in['pin'] ?? null)) {
        throw new ApiError('Le code de la famille doit faire 4 chiffres.');
    }
    central()->prepare('INSERT INTO accounts (email, password_hash, created_at) VALUES (?, ?, ?)')
        ->execute([$email, password_hash($in['password'], PASSWORD_DEFAULT), now_ts()]);
    $accountId = (int) central()->lastInsertId();
    $hid = new_household($name, $accountId);
    use_household($hid);
    $first = setup_household($in);
    create_account_session($accountId);
    create_device($hid, $first);
    register_failure($key); // counts sign-ups: 5 from one place, then a pause
    return [];
}

/** An account that already exists adds another household (grand-parents' coop, school...). */
function action_household_create(array $in): array
{
    $a = require_account();
    $name = household_name($in['householdName'] ?? '');
    if (!valid_pin($in['pin'] ?? null)) {
        throw new ApiError('Le code de la famille doit faire 4 chiffres.');
    }
    $hid = new_household($name, $a['id']);
    use_household($hid);
    $first = setup_household($in);
    create_device($hid, $first);
    return [];
}

function action_account_login(array $in): array
{
    $email = trim((string) ($in['email'] ?? ''));
    $keys = ['acct:' . mb_strtolower($email), 'acctip:' . client_ip()];
    foreach ($keys as $k) {
        if (($wait = check_lock($k)) !== null) {
            throw new ApiError('Trop d\'essais. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
        }
    }
    $st = central()->prepare('SELECT id, password_hash FROM accounts WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    // same work and same answer whether the e-mail exists or not
    $hash = $row['password_hash'] ?? password_hash('pas-de-compte', PASSWORD_DEFAULT);
    if (!password_verify((string) ($in['password'] ?? ''), $hash) || !$row) {
        array_map('register_failure', $keys);
        throw new ApiError('E-mail ou mot de passe incorrect.', 403);
    }
    array_map('clear_failures', $keys);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        central()->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($in['password'], PASSWORD_DEFAULT), $row['id']]);
    }
    create_account_session((int) $row['id']);
    // only one household and this phone isn't in it yet: go straight in
    $households = account_households((int) $row['id']);
    if (current_device() === null && count($households) === 1) {
        use_household($households[0]['id']);
        create_device($households[0]['id'], people()[0]['id'] ?? null);
    }
    return [];
}

function action_account_logout(): array
{
    end_account_session();
    return [];
}

/** The account holder opens one of their households on this phone (no family code needed). */
function action_household_enter(array $in): array
{
    $h = require_owner((int) ($in['householdId'] ?? 0));
    use_household((int) $h['id']);
    $pid = person_or_null($in['personId'] ?? null) ?? (people()[0]['id'] ?? null);
    if (current_device() !== null) {
        forget_device();
    }
    create_device((int) $h['id'], $pid);
    return [];
}

// ================================================================ family phones

function household_by_code(string $code): ?array
{
    if (strlen($code) !== 8) {
        return null;
    }
    $st = central()->prepare('SELECT * FROM households WHERE invite_code = ? AND deleted_at IS NULL');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

/** Step 1 on a new phone: the invitation code tells which household; we show its people. */
function action_join_lookup(array $in): array
{
    $key = 'join:' . client_ip();
    if (($wait = check_lock($key)) !== null) {
        throw new ApiError('Trop d\'essais. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
    }
    $code = normalize_invite_code($in['code'] ?? '');
    $h = household_by_code($code);
    if (!$h) {
        register_failure($key);
        throw new ApiError('Ce code d\'invitation ne correspond à aucun poulailler.', 404);
    }
    use_household((int) $h['id']);
    return ['join' => ['code' => $code, 'name' => $h['name'], 'people' => people()]];
}

/** Step 2: who you are + the family code. */
function action_join_login(array $in): array
{
    $code = normalize_invite_code($in['code'] ?? '');
    $h = household_by_code($code);
    if (!$h) {
        throw new ApiError('Ce code d\'invitation n\'est plus valable. Demandez le nouveau.', 404);
    }
    $hid = (int) $h['id'];
    $key = 'pin:' . $hid . ':' . client_ip();
    if (($wait = check_lock($key)) !== null) {
        throw new ApiError('Trop d\'essais. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
    }
    use_household($hid);
    $hash = setting('pin_hash');
    $pin = $in['pin'] ?? null;
    if ($hash === null || !valid_pin($pin) || !password_verify($pin, $hash)) {
        register_failure($key);
        throw new ApiError('Ce n\'est pas le bon code.', 403);
    }
    clear_failures($key);
    if (current_device() !== null) {
        forget_device();
    }
    create_device($hid, person_or_null($in['personId'] ?? null));
    return [];
}

function action_invite_regenerate(): array
{
    require_auth();
    for ($i = 0; $i < 5; $i++) {
        try {
            central()->prepare('UPDATE households SET invite_code = ? WHERE id = ?')->execute([new_invite_code(), household_id()]);
            return [];
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'UNIQUE')) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Could not create an invite code');
}

function action_household_rename(array $in): array
{
    require_auth();
    central()->prepare('UPDATE households SET name = ? WHERE id = ?')->execute([household_name($in['name'] ?? ''), household_id()]);
    return [];
}

/**
 * A household from V1 has no responsible account yet. Someone on a connected phone
 * who knows the family code attaches an account to it (new, or existing with its password).
 */
function action_household_claim(array $in): array
{
    require_auth();
    $h = household();
    if ($h['owner_id'] !== null) {
        throw new ApiError('Ce poulailler a déjà un responsable.', 409);
    }
    $key = 'pin:' . $h['id'] . ':' . client_ip();
    if (($wait = check_lock($key)) !== null) {
        throw new ApiError('Trop d\'essais. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
    }
    $pinHash = setting('pin_hash');
    if (!valid_pin($in['pin'] ?? null) || $pinHash === null || !password_verify($in['pin'], $pinHash)) {
        register_failure($key);
        throw new ApiError('Ce n\'est pas le bon code de la famille.', 403);
    }
    $email = trim((string) ($in['email'] ?? ''));
    if (!valid_email($email) || !valid_password($in['password'] ?? null)) {
        throw new ApiError('Indiquez un e-mail valide et un mot de passe d\'au moins 8 caractères.');
    }
    $st = central()->prepare('SELECT id, password_hash FROM accounts WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row) {
        if (!password_verify($in['password'], $row['password_hash'])) {
            register_failure('acct:' . mb_strtolower($email));
            throw new ApiError('Un compte existe avec cet e-mail mais le mot de passe ne correspond pas.', 403);
        }
        $accountId = (int) $row['id'];
    } else {
        central()->prepare('INSERT INTO accounts (email, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$email, password_hash($in['password'], PASSWORD_DEFAULT), now_ts()]);
        $accountId = (int) central()->lastInsertId();
    }
    $trial = billing_enabled() ? now_ts() + (int) config('trial_days', 30) * 86400 : 0;
    central()->prepare('UPDATE households SET owner_id = ?, trial_until = MAX(trial_until, ?) WHERE id = ? AND owner_id IS NULL')
        ->execute([$accountId, $trial, $h['id']]);
    create_account_session($accountId);
    return [];
}

// ================================================================ passwords

function action_password_forgot(array $in): array
{
    $key = 'forgot:' . client_ip();
    if (($wait = check_lock($key)) !== null) {
        throw new ApiError('Trop de demandes. Réessayez dans ' . max(1, (int) ceil($wait / 60)) . ' min.', 429);
    }
    register_failure($key); // at most 5 requests, then a pause
    $email = trim((string) ($in['email'] ?? ''));
    $st = central()->prepare('SELECT id, email FROM accounts WHERE email = ?');
    $st->execute([$email]);
    if ($row = $st->fetch()) {
        $token = bin2hex(random_bytes(32));
        central()->prepare('DELETE FROM password_resets WHERE account_id = ?')->execute([$row['id']]);
        central()->prepare('INSERT INTO password_resets (token_hash, account_id, expires_at) VALUES (?, ?, ?)')
            ->execute([hash('sha256', $token), $row['id'], now_ts() + 3600]);
        $link = base_url() . '#/nouveau-mot-de-passe/' . $token;
        send_mail($row['email'], 'KotKot : choisir un nouveau mot de passe', <<<TXT
        Bonjour,

        Pour choisir un nouveau mot de passe KotKot, ouvrez ce lien (valable 1 heure) :

        $link

        Si vous n'avez rien demandé, ignorez simplement ce message : rien ne change.

        L'équipe KotKot
        TXT);
    }
    // same answer whether the address is known or not
    return ['message' => 'Si un compte existe avec cette adresse, un e-mail vient de partir. Pensez à regarder dans les indésirables.'];
}

function action_password_reset(array $in): array
{
    $token = (string) ($in['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new ApiError('Ce lien n\'est pas valable.', 400);
    }
    if (!valid_password($in['password'] ?? null)) {
        throw new ApiError('Le mot de passe doit faire au moins 8 caractères.');
    }
    $st = central()->prepare('SELECT account_id FROM password_resets WHERE token_hash = ? AND expires_at > ?');
    $st->execute([hash('sha256', $token), now_ts()]);
    $accountId = $st->fetchColumn();
    if ($accountId === false) {
        throw new ApiError('Ce lien a expiré. Refaites une demande.', 410);
    }
    $accountId = (int) $accountId;
    central()->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($in['password'], PASSWORD_DEFAULT), $accountId]);
    central()->prepare('DELETE FROM password_resets WHERE account_id = ?')->execute([$accountId]);
    central()->prepare('DELETE FROM account_sessions WHERE account_id = ?')->execute([$accountId]);
    create_account_session($accountId);
    return [];
}

function action_password_change(array $in): array
{
    $a = require_account();
    $st = central()->prepare('SELECT password_hash FROM accounts WHERE id = ?');
    $st->execute([$a['id']]);
    if (!password_verify((string) ($in['oldPassword'] ?? ''), (string) $st->fetchColumn())) {
        throw new ApiError('L\'ancien mot de passe n\'est pas le bon.', 403);
    }
    if (!valid_password($in['newPassword'] ?? null)) {
        throw new ApiError('Le nouveau mot de passe doit faire au moins 8 caractères.');
    }
    central()->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($in['newPassword'], PASSWORD_DEFAULT), $a['id']]);
    central()->prepare('DELETE FROM account_sessions WHERE account_id = ? AND token_hash != ?')->execute([$a['id'], $a['hash']]);
    return [];
}

function check_account_password(array $a, mixed $password): void
{
    $st = central()->prepare('SELECT password_hash FROM accounts WHERE id = ?');
    $st->execute([$a['id']]);
    if (!password_verify((string) $password, (string) $st->fetchColumn())) {
        throw new ApiError('Mot de passe incorrect.', 403);
    }
}

// ================================================================ export & delete (RGPD)

/** Everything a household has, as a .zip: one JSON file per table + the photos. */
function export_household(int $householdId): void
{
    $h = require_owner($householdId);
    use_household($householdId);
    $zipPath = tempnam(sys_get_temp_dir(), 'kk');
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $tables = ['people', 'eggs', 'tasks', 'task_done', 'hens', 'hen_notes', 'hen_photos', 'door_events', 'health'];
    $data = ['foyer' => ['nom' => $h['name'], 'cree_le' => date('c', (int) $h['created_at'])], 'exporte_le' => date('c', now_ts())];
    foreach ($tables as $t) {
        $data[$t] = db()->query("SELECT * FROM $t")->fetchAll();
    }
    $settings = db()->query("SELECT key, value FROM settings WHERE key != 'pin_hash'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $data['reglages'] = $settings;
    $zip->addFromString('kotkot.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    foreach (glob(photo_dir() . '/*.jpg') ?: [] as $file) {
        $zip->addFile($file, 'photos/' . basename($file));
    }
    $zip->close();
    $filename = 'kotkot-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $h['name']) ?: 'foyer')) . '-' . date('Y-m-d') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . trim($filename, '-') . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    unlink($zipPath);
}

function remove_household_files(int $householdId): void
{
    $dir = household_dir($householdId);
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function delete_household(int $householdId): void
{
    central()->prepare('UPDATE households SET deleted_at = ?, invite_code = NULL, owner_id = NULL, name = ? WHERE id = ?')
        ->execute([now_ts(), '(supprimé)', $householdId]);
    central()->prepare('DELETE FROM push_subscriptions WHERE household_id = ?')->execute([$householdId]);
    central()->prepare('DELETE FROM notifications_sent WHERE household_id = ?')->execute([$householdId]);
    remove_household_files($householdId);
}

function action_household_delete(array $in): array
{
    $a = require_account();
    $h = require_owner((int) ($in['householdId'] ?? 0));
    check_account_password($a, $in['password'] ?? '');
    $device = current_device();
    delete_household((int) $h['id']);
    if ($device && $device['household'] === (int) $h['id']) {
        set_cookie(KK_DEVICE_COOKIE, '', 0);
        current_device(true);
    }
    use_household(null);
    return [];
}

function action_account_delete(array $in): array
{
    $a = require_account();
    check_account_password($a, $in['password'] ?? '');
    foreach (account_households($a['id']) as $h) {
        delete_household($h['id']);
    }
    central()->prepare('DELETE FROM accounts WHERE id = ?')->execute([$a['id']]);
    set_cookie(KK_ACCOUNT_COOKIE, '', 0);
    set_cookie(KK_DEVICE_COOKIE, '', 0);
    current_account(true);
    current_device(true);
    use_household(null);
    return [];
}

/** The account holder may set a new family code without knowing the old one. */
function action_pin_reset(array $in): array
{
    require_auth();
    require_owner(household_id());
    if (!valid_pin($in['newPin'] ?? null)) {
        throw new ApiError('Le nouveau code doit faire 4 chiffres.');
    }
    set_setting('pin_hash', password_hash($in['newPin'], PASSWORD_DEFAULT));
    central()->prepare('DELETE FROM login_attempts WHERE key LIKE ?')->execute(['pin:' . household_id() . ':%']);
    return [];
}
