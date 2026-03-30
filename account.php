<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\BillingService;

ensure_logged_in();

$auth = new AuthService();
$billing = new BillingService();
$userRepository = new UserRepository();
$auditLogs = new AuditLogRepository();
$sessionUser = current_user();
$userId = (int) ($sessionUser['id'] ?? 0);
$user = $userRepository->findById($userId) ?? $sessionUser;

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'account_security')) {
        flash('error', 'Security token expired. Try again.');
        redirect('account.php#security');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'start_mfa_setup') {
        $result = $auth->startMfaSetup($userId);

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'account.mfa_setup_started', 'user', $userId, 'Started MFA setup.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('account.php#security');
    }

    if ($action === 'confirm_mfa_setup') {
        $result = $auth->enableMfaFromPendingSetup($userId, (string) ($_POST['code'] ?? ''));

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'account.mfa_enabled', 'user', $userId, 'Enabled MFA on the account.');
            flash('success', $result['message']);

            if (!empty($result['backup_codes'])) {
                flash('mfa_backup_codes', $result['backup_codes']);
            }
        } else {
            flash('error', $result['message']);
        }

        redirect('account.php#security');
    }

    if ($action === 'disable_mfa') {
        $result = $auth->disableMfa($userId, (string) ($_POST['code'] ?? ''));

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'account.mfa_disabled', 'user', $userId, 'Disabled MFA on the account.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('account.php#security');
    }
}

$settings = new SettingsRepository();
$storageSettings = $settings->all();
$pendingMfaSetup = $auth->currentMfaSetup($userId);
$billingConfigured = $billing->isConfigured();
$backupCodes = flash('mfa_backup_codes');
$flashError = flash('error');
$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <?php
    $publicNavActive = '';
    $publicBarItems = ['Signed in', 'Members area', '18+ access'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>
    <main class="page-shell">
        <section class="account-panel">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <?php if ($flashSuccess): ?>
                <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
            <?php endif; ?>
            <div class="section-heading">
                <div>
                    <span class="eyebrow">ACCOUNT</span>
                    <h1><?= e((string) $user['display_name']); ?></h1>
                </div>
                <p>Keep membership, security, and account help grouped in one place.</p>
            </div>
            <div class="account-summary-grid">
                <article class="mini-stat">
                    <span>Plan</span>
                    <strong><?= e(account_tier_label((string) ($user['account_tier'] ?? 'free'))); ?></strong>
                </article>
                <article class="mini-stat">
                    <span>Status</span>
                    <strong><?= e(user_status_label((string) ($user['status'] ?? 'active'))); ?></strong>
                </article>
                <article class="mini-stat">
                    <span>Security</span>
                    <strong><?= (int) ($user['mfa_enabled'] ?? 0) === 1 ? '2FA on' : '2FA off'; ?></strong>
                </article>
            </div>
            <div class="account-grid">
                <article class="compliance-card" id="subscription">
                    <h3>Subscription</h3>
                    <p><strong>Plan:</strong> <?= e(account_tier_label((string) ($user['account_tier'] ?? 'free'))); ?></p>
                    <p><strong>Membership status:</strong> <?= e(subscription_status_label((string) ($user['stripe_subscription_status'] ?? null))); ?></p>
                    <?php if (!empty($user['stripe_current_period_end'])): ?>
                        <p><strong>Access until:</strong> <?= e(format_datetime((string) $user['stripe_current_period_end'], 'Not available')); ?></p>
                    <?php endif; ?>
                    <p><?= e($billing->planCopy()); ?></p>

                    <?php if ($billingConfigured): ?>
                        <?php if (user_has_premium_access($user) || !empty($user['stripe_customer_id'])): ?>
                            <form method="post" action="<?= e(base_url('manage-billing.php')); ?>" class="security-form">
                                <?= csrf_input('billing_portal'); ?>
                                <button class="button" type="submit">Manage plan</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>" class="security-form">
                                <?= csrf_input('billing_checkout'); ?>
                                <button class="button" type="submit">Upgrade to <?= e($billing->planName()); ?></button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="form-note">Premium access is not available right now.</p>
                    <?php endif; ?>

                    <a class="text-link" href="<?= e(base_url('premium.php')); ?>">See plans</a>
                </article>
                <article class="compliance-card">
                    <h3>Profile</h3>
                    <p><strong>Email:</strong> <?= e((string) $user['email']); ?></p>
                    <p><strong>Account type:</strong> <?= e(ucfirst((string) $user['role'])); ?></p>
                    <p><strong>Account status:</strong> <?= e(user_status_label((string) ($user['status'] ?? 'active'))); ?></p>
                    <a class="text-link" href="<?= e(base_url('browse.php')); ?>">Go to browse</a>
                </article>
                <article class="compliance-card" id="security">
                    <h3>Security</h3>
                    <p><strong>2FA:</strong> <?= (int) ($user['mfa_enabled'] ?? 0) === 1 ? 'Enabled' : 'Disabled'; ?></p>
                    <p>Use an authenticator app for extra sign-in protection. Backup codes help you get back in if you lose your device.</p>

                    <?php if ((int) ($user['mfa_enabled'] ?? 0) === 1): ?>
                        <form method="post" class="security-form">
                            <?= csrf_input('account_security'); ?>
                            <input type="hidden" name="action" value="disable_mfa">
                            <label>
                                <span>Authenticator or backup code</span>
                                <input type="text" name="code" inputmode="numeric" required>
                            </label>
                            <button class="button button--ghost" type="submit">Disable 2FA</button>
                        </form>
                    <?php elseif ($pendingMfaSetup): ?>
                        <div class="security-note">
                            <strong>Authenticator key</strong>
                            <code><?= e((string) $pendingMfaSetup['secret']); ?></code>
                            <strong>Setup link</strong>
                            <code><?= e((string) $pendingMfaSetup['otpauth_uri']); ?></code>
                        </div>
                        <form method="post" class="security-form">
                            <?= csrf_input('account_security'); ?>
                            <input type="hidden" name="action" value="confirm_mfa_setup">
                            <label>
                                <span>6-digit code</span>
                                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                            </label>
                            <button class="button" type="submit">Enable 2FA</button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="security-form">
                            <?= csrf_input('account_security'); ?>
                            <input type="hidden" name="action" value="start_mfa_setup">
                            <button class="button" type="submit">Start 2FA setup</button>
                        </form>
                    <?php endif; ?>
                </article>
                <?php if (is_array($backupCodes) && $backupCodes !== []): ?>
                    <article class="compliance-card">
                        <h3>Backup codes</h3>
                        <p>Save these codes now. They are shown only once.</p>
                        <div class="backup-code-list">
                            <?php foreach ($backupCodes as $backupCode): ?>
                                <code><?= e((string) $backupCode); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
                <article class="compliance-card">
                    <h3>Password reset</h3>
                    <p>If you lose access, use the reset page to request a new password link.</p>
                    <a class="text-link" href="<?= e(base_url('forgot-password.php')); ?>">Open password reset</a>
                </article>
                <?php if (is_admin()): ?>
                    <article class="compliance-card">
                        <h3>Admin</h3>
                        <p><strong>Current storage:</strong> <?= e((string) ($storageSettings['upload_driver'] ?? 'local')); ?></p>
                        <p>Open the control panel to manage videos, members, payments, and site settings.</p>
                    </article>
                <?php endif; ?>
            </div>
            <div class="hero__actions">
                <a class="button" href="<?= e(base_url('browse.php')); ?>">Browse videos</a>
                <a class="button button--ghost" href="<?= e(base_url('support.php')); ?>">Get help</a>
                <?php if (is_admin()): ?>
                    <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>">Open admin</a>
                <?php endif; ?>
                <?= logout_button('Sign out'); ?>
            </div>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('account')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
