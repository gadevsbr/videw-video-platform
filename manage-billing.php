<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Services\BillingService;

ensure_logged_in();

if (!is_post_request() || !verify_csrf($_POST['_csrf'] ?? null, 'billing_portal')) {
    flash('error', 'Security token expired. Try again.');
    redirect('account.php#subscription');
}

$user = current_user(true);

if (!$user) {
    flash('error', 'Sign in to continue.');
    redirect('login.php');
}

$billing = new BillingService();
$auditLogs = new AuditLogRepository();

try {
    $portalUrl = $billing->billingPortalUrl($user);
    $auditLogs->record((int) ($user['id'] ?? 0), 'billing.portal_opened', 'user', (int) ($user['id'] ?? 0), 'Opened the Stripe Billing Portal.', [
        'plan' => $billing->planName(),
    ]);
    header('Location: ' . $portalUrl, true, 302);
    exit;
} catch (RuntimeException $exception) {
    flash('error', $exception->getMessage());
    redirect('account.php#subscription');
}
