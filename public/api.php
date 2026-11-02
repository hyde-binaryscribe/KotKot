<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/state.php';
require __DIR__ . '/../src/actions.php';
require __DIR__ . '/../src/accounts.php';
require __DIR__ . '/../src/push.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$action = is_string($_GET['a'] ?? null) ? $_GET['a'] : 'state';

// Hen photos are images, not JSON.
if ($action === 'photo') {
    if (current_device() === null) {
        http_response_code(401);
        exit;
    }
    $path = photo_path((int) ($_GET['id'] ?? 0));
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=31536000, immutable');
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Export: a file download for the account holder.
if ($action === 'export') {
    try {
        export_household((int) ($_GET['id'] ?? 0));
    } catch (ApiError $e) {
        http_response_code($e->status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
    }
    exit;
}

$routes = [
    // comptes et foyers
    'account_create' => 'action_account_create',
    'account_login' => 'action_account_login',
    'account_logout' => 'action_account_logout',
    'account_delete' => 'action_account_delete',
    'household_create' => 'action_household_create',
    'household_enter' => 'action_household_enter',
    'household_rename' => 'action_household_rename',
    'household_claim' => 'action_household_claim',
    'household_delete' => 'action_household_delete',
    'join_lookup' => 'action_join_lookup',
    'join_login' => 'action_join_login',
    'invite_regenerate' => 'action_invite_regenerate',
    'password_forgot' => 'action_password_forgot',
    'password_reset' => 'action_password_reset',
    'password_change' => 'action_password_change',
    'pin_reset' => 'action_pin_reset',
    // la vie du poulailler
    'logout' => 'action_logout',
    'switch_person' => 'action_switch_person',
    'egg' => 'action_egg',
    'egg_undo' => 'action_egg_undo',
    'door' => 'action_door',
    'door_undo' => 'action_door_undo',
    'task_set' => 'action_task_set',
    'task_save' => 'action_task_save',
    'task_remove' => 'action_task_remove',
    'hen_save' => 'action_hen_save',
    'hen_status' => 'action_hen_status',
    'hen_remove' => 'action_hen_remove',
    'hen_notes' => 'action_hen_notes',
    'hen_note_add' => 'action_hen_note_add',
    'hen_note_remove' => 'action_hen_note_remove',
    'hen_photo' => 'action_hen_photo',
    'hen_photos' => 'action_hen_photos',
    'hen_photo_remove' => 'action_hen_photo_remove',
    'settings_save' => 'action_settings_save',
    'pin_change' => 'action_pin_change',
    'person_save' => 'action_person_save',
    'person_remove' => 'action_person_remove',
    'health_add' => 'action_health_add',
    'health_remove' => 'action_health_remove',
    'push_subscribe' => 'action_push_subscribe',
    'push_unsubscribe' => 'action_push_unsubscribe',
    'push_test' => 'action_push_test',
];

try {
    if ($action === 'state') {
        echo json_encode(['ok' => true, 'state' => app_state()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!isset($routes[$action])) {
        throw new ApiError('Action inconnue.', 404);
    }
    // Changes only by POST, and only from our own page: a custom header cannot be
    // sent cross-site without a CORS preflight, which we never allow.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_KOTKOT'] ?? '') !== '1') {
        throw new ApiError('Requête refusée.', 405);
    }
    $input = [];
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new ApiError('Requête illisible.');
        }
    }
    $result = $routes[$action]($input);
    echo json_encode(['ok' => true] + $result + ['state' => app_state()], JSON_UNESCAPED_UNICODE);
} catch (ApiError $e) {
    http_response_code($e->status);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('KotKot: ' . $e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Petit souci technique, réessayez.'], JSON_UNESCAPED_UNICODE);
}
