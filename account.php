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
    <div class="legal-bar">
        <span>Signed in</span>
        <span>Adult platform</span>
        <span>Restricted area</span>
    </div>
    <header class="site-header">
        <a class="brandmark" href="<?= e(base_url()); ?>">
            <span class="brandmark__kicker"><?= e(brand_kicker()); ?></span>
            <span class="brandmark__title"><?= e(brand_title()); ?></span>
        </a>
        <nav class="site-nav">
            <a href="<?= e(base_url()); ?>">Home</a>
            <a href="<?= e(base_url('index.php#catalog')); ?>">Browse</a>
            <a href="<?= e(base_url('premium.php')); ?>">Premium</a>
            <a href="<?= e(base_url('rules.php')); ?>"><?= e(rules_nav_label()); ?></a>
            <?php if (is_admin()): ?>
                <a href="<?= e(base_url('admin.php')); ?>">Admin</a>
            <?php endif; ?>
        </nav>
        <div class="site-nav__actions">
            <span class="pill pill--muted"><?= e((string) $user['display_name']); ?></span>
            <a class="button button--ghost" href="<?= e(base_url('logout.php')); ?>">Log out</a>
        </div>
    </header>
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
            </div>
            <div class="account-grid">
                <article class="compliance-card">
                    <h3>Profile</h3>
                    <p><strong>Email:</strong> <?= e((string) $user['email']); ?></p>
                    <p><strong>Role:</strong> <?= e((string) $user['role']); ?></p>
                    <p><strong>Status:</strong> <?= e(user_status_label((string) ($user['status'] ?? 'active'))); ?></p>
                </article>
                <article class="compliance-card" id="subscription">
                    <h3>Subscription</h3>
                    <p><strong>Plan:</strong> <?= e(account_tier_label((string) ($user['account_tier'] ?? 'free'))); ?></p>
                    <p><strong>Stripe status:</strong> <?= e(subscription_status_label((string) ($user['stripe_subscription_status'] ?? null))); ?></p>
                    <?php if (!empty($user['stripe_current_period_end'])): ?>
                        <p><strong>Current period end:</strong> <?= e(format_datetime((string) $user['stripe_current_period_end'], 'Unknown')); ?></p>
                    <?php endif; ?>
                    <p><?= e($billing->planCopy()); ?></p>

                    <?php if ($billingConfigured): ?>
                        <?php if (user_has_premium_access($user) || !empty($user['stripe_customer_id'])): ?>
                            <form method="post" action="<?= e(base_url('manage-billing.php')); ?>" class="security-form">
                                <?= csrf_input('billing_portal'); ?>
                                <button class="button" type="submit">Manage subscription</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>" class="security-form">
                                <?= csrf_input('billing_checkout'); ?>
                                <button class="button" type="submit">Upgrade to <?= e($billing->planName()); ?></button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="form-note">Premium checkout is not configured yet.</p>
                    <?php endif; ?>

                    <a class="text-link" href="<?= e(base_url('premium.php')); ?>">Open plans page</a>
                </article>
                <article class="compliance-card" id="security">
                    <h3>Security</h3>
                    <p><strong>2FA:</strong> <?= (int) ($user['mfa_enabled'] ?? 0) === 1 ? 'Enabled' : 'Disabled'; ?></p>
                    <p>Use an authenticator app for login codes. Backup codes work as a fallback.</p>

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
                            <strong>Setup key</strong>
                            <code><?= e((string) $pendingMfaSetup['secret']); ?></code>
                            <strong>Authenticator URI</strong>
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
                        <p>Save these codes now. They will not be shown again.</p>
                        <div class="backup-code-list">
                            <?php foreach ($backupCodes as $backupCode): ?>
                                <code><?= e((string) $backupCode); ?></code>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
                <article class="compliance-card">
                    <h3>Password reset</h3>
                    <p>If you lose access, generate a one-time reset link from the public reset flow.</p>
                    <a class="text-link" href="<?= e(base_url('forgot-password.php')); ?>">Open password reset</a>
                </article>
                <?php if (is_admin()): ?>
                    <article class="compliance-card">
                        <h3>Admin</h3>
                        <p><strong>Active driver:</strong> <?= e((string) ($storageSettings['upload_driver'] ?? 'local')); ?></p>
                        <p>Use the admin panel to switch storage and publish videos.</p>
                    </article>
                <?php endif; ?>
            </div>
            <div class="hero__actions">
                <a class="button" href="<?= e(base_url()); ?>">Go to browse</a>
                <?php if (is_admin()): ?>
                    <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>">Open admin</a>
                <?php endif; ?>
                <a class="button button--ghost" href="<?= e(base_url('logout.php')); ?>">End session</a>
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
