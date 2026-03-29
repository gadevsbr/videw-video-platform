<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_post_request()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método inválido.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '{}', true);

if (!is_array($payload)) {
    $payload = $_POST;
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
echo json_encode(['ok' => false, 'message' => 'Ação inválida.']);
