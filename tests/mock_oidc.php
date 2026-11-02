<?php
declare(strict_types=1);

/*
 * A fake Google/Apple token endpoint for tests/accounts_test.php.
 * The "code" is the base64url JSON of the claims the test wants in the ID token.
 * Every request is written to $KOTKOT_MOCK_LOG so the test can check what KotKot sent.
 */

$b64u = fn (string $b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents((string) getenv('KOTKOT_MOCK_LOG'), json_encode(['path' => $path, 'post' => $_POST]) . "\n", FILE_APPEND);

if ($path === '/token') {
    $claims = json_decode((string) base64_decode(strtr((string) ($_POST['code'] ?? ''), '-_', '+/')), true);
    if (!is_array($claims)) {
        http_response_code(400);
        echo '{"error":"invalid_grant"}';
        return true;
    }
    header('Content-Type: application/json');
    echo json_encode(['access_token' => 'x', 'id_token' => $b64u('{"alg":"RS256"}') . '.' . $b64u(json_encode($claims)) . '.c2ln']);
    return true;
}
http_response_code(404);
return true;
