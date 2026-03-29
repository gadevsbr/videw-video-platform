<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Services\BillingService;

header('Content-Type: application/json; charset=UTF-8');

if (request_method() !== 'POST') {
    http_response_code(405);
    echo json_encode(['received' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$billing = new BillingService();
$auditLogs = new AuditLogRepository();

try {
    $result = $billing->handleWebhook(is_string($payload) ? $payload : '', $signature);
    $auditLogs->record(null, 'billing.webhook', 'settings', null, 'Processed a Stripe webhook event.', [
        'type' => $result['type'] ?? '',
    ]);
    echo json_encode([
        'received' => true,
        'type' => $result['type'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (RuntimeException $exception) {
    $message = $exception->getMessage();
    $statusCode = str_contains(strtolower($message), 'signature') || str_contains(strtolower($message), 'payload')
        ? 400
        : 500;

    http_response_code($statusCode);
    echo json_encode([
        'received' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
