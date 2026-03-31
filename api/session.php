<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_post_request()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '{}', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['_csrf'] ?? $_POST['_csrf'] ?? '');

if (!verify_csrf($csrfToken, 'session_api')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Security token expired. Reload the page and try again.']);
    exit;
}

$action = $payload['action'] ?? '';

if ($action === 'verify-age') {
    $_SESSION['age_verified_at'] = (new DateTimeImmutable())->format(DATE_ATOM);
    echo json_encode(['ok' => true, 'ageVerified' => true]);
    exit;
}

if ($action === 'clear-age') {
    unset($_SESSION['age_verified_at']);
    echo json_encode(['ok' => true, 'ageVerified' => false]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
