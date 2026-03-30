<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Services\BillingService;

ensure_logged_in();

if (!is_post_request() || !verify_csrf($_POST['_csrf'] ?? null, 'billing_checkout')) {
    flash('error', copy_text('messages.common.security_token_expired', 'Security token expired. Try again.'));
    redirect('premium.php');
}

$user = current_user(true);

if (!$user) {
    flash('error', copy_text('messages.common.sign_in_required', 'Sign in to continue.'));
    redirect('login.php');
}

$billing = new BillingService();
$auditLogs = new AuditLogRepository();

try {
    $checkoutUrl = $billing->checkoutUrl($user);
    $auditLogs->record((int) ($user['id'] ?? 0), 'billing.checkout_started', 'user', (int) ($user['id'] ?? 0), 'Started a Stripe Checkout session for Premium.', [
        'plan' => $billing->planName(),
        'price_id' => $billing->premiumPriceId(),
    ]);
    header('Location: ' . $checkoutUrl, true, 302);
    exit;
} catch (RuntimeException $exception) {
    flash('error', $exception->getMessage());
    redirect('premium.php');
}
