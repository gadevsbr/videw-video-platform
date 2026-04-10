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
    $action = match ((string) ($result['status'] ?? 'processed')) {
        'duplicate_processed', 'duplicate_processing' => 'billing.webhook_duplicate',
        'ignored' => 'billing.webhook_ignored',
        default => 'billing.webhook_processed',
    };
    $summary = match ((string) ($result['status'] ?? 'processed')) {
        'duplicate_processed' => 'Ignored a duplicate Stripe webhook event that was already processed.',
        'duplicate_processing' => 'Ignored a duplicate Stripe webhook event that is already in progress.',
        'ignored' => 'Received a Stripe webhook event with no local action required.',
        default => 'Processed a Stripe webhook event.',
    };
    $auditLogs->record(null, $action, 'settings', null, $summary, [
        'type' => $result['type'] ?? '',
        'event_id' => $result['event_id'] ?? '',
        'status' => $result['status'] ?? 'processed',
    ]);
    echo json_encode([
        'received' => true,
        'type' => $result['type'] ?? '',
        'status' => $result['status'] ?? 'processed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (RuntimeException $exception) {
    $message = $exception->getMessage();
    $statusCode = str_contains(strtolower($message), 'signature') || str_contains(strtolower($message), 'payload')
        ? 400
        : 500;

    $auditLogs->record(null, 'billing.webhook_failed', 'settings', null, 'Stripe webhook processing failed.', [
        'error' => $message,
    ]);
    error_log('[VIDEW][Stripe webhook] ' . $message);
    http_response_code($statusCode);
    echo json_encode([
        'received' => false,
        'error' => $statusCode === 400 ? 'Webhook payload rejected.' : 'Webhook processing failed.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
