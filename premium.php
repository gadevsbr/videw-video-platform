<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Services\BillingService;

$billing = new BillingService();
$userRepository = new UserRepository();
$videoRepository = new VideoRepository();
$user = current_user();
$user = $user ? ($userRepository->findById((int) ($user['id'] ?? 0)) ?? $user) : null;
$checkoutState = trim((string) ($_GET['checkout'] ?? ''));
$checkoutSessionId = trim((string) ($_GET['session_id'] ?? ''));

if ($checkoutState === 'success' && $checkoutSessionId !== '') {
    $result = $billing->syncSuccessfulCheckout($checkoutSessionId, $user ? (int) ($user['id'] ?? 0) : null);

    if ($result['success']) {
        flash('success', $user ? $result['message'] : 'Your payment is confirmed. Sign in to refresh your account.');
    } else {
        flash('error', $result['message']);
    }

    redirect('premium.php');
}

if ($checkoutState === 'cancel') {
    flash('error', 'Checkout was canceled before payment was completed.');
    redirect('premium.php');
}

$stats = $videoRepository->stats();
$billingConfigured = $billing->isConfigured();
$flashError = flash('error');
$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($billing->planName()); ?> | <?= e(config('app.name')); ?></title>
    <meta name="description" content="<?= e($billing->planCopy()); ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <?php
    $publicNavActive = 'premium';
    $publicBarItems = ['Adults only 18+', 'Secure checkout', 'Free and Premium access'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <?php if ($flashError): ?>
        <div class="flash flash--error"><?= e((string) $flashError); ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
    <?php endif; ?>

    <main class="page-shell">
        <section class="hero legal-hero">
            <div class="hero__copy">
                <span class="eyebrow">PREMIUM ACCESS</span>
                <h1><?= e($billing->planName()); ?></h1>
                <p><?= e($billing->planCopy()); ?></p>
                <div class="hero__actions">
                    <?php if ($billingConfigured && $user && !user_has_premium_access($user)): ?>
                        <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>">
                            <?= csrf_input('billing_checkout'); ?>
                            <button class="button" type="submit">Upgrade now</button>
                        </form>
                    <?php elseif ($billingConfigured && $user && (user_has_premium_access($user) || !empty($user['stripe_customer_id']))): ?>
                        <form method="post" action="<?= e(base_url('manage-billing.php')); ?>">
                            <?= csrf_input('billing_portal'); ?>
                            <button class="button" type="submit">Manage plan</button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a class="button" href="<?= e(base_url('register.php')); ?>">Create free account</a>
                    <?php endif; ?>
                    <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>">Browse videos</a>
                </div>
                <?php if (!$billingConfigured): ?>
                    <div class="notice-card">
                        <strong>Premium access is not available right now</strong>
                        <p><?php if (is_admin()): ?>Finish the payment setup in the admin panel to open Premium memberships.<?php else: ?>Please check back soon.<?php endif; ?></p>
                        <?php if (is_admin()): ?>
                            <a class="text-link" href="<?= e(base_url('admin.php?screen=billing')); ?>">Open payment settings</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="hero__aside legal-hero__aside">
                <article class="notice-card">
                    <strong>Price</strong>
                    <p><?= e($billing->planPriceLabel()); ?></p>
                </article>
                <article class="notice-card">
                    <strong>Catalog split</strong>
                    <p><?= e((string) ($stats['premium'] ?? 0)); ?> premium videos and <?= e((string) max(0, ((int) ($stats['videos'] ?? 0)) - ((int) ($stats['premium'] ?? 0)))); ?> free videos.</p>
                </article>
                <?php if ($user): ?>
                    <article class="notice-card">
                        <strong>Your account</strong>
                        <p><strong>Plan:</strong> <?= e(account_tier_label((string) ($user['account_tier'] ?? 'free'))); ?></p>
                        <p><strong>Membership status:</strong> <?= e(subscription_status_label((string) ($user['stripe_subscription_status'] ?? null))); ?></p>
                        <?php if (!empty($user['stripe_current_period_end'])): ?>
                            <p><strong>Access until:</strong> <?= e(format_datetime((string) $user['stripe_current_period_end'], 'Not available')); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>
            </aside>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">PLANS</span>
                    <h2>Free vs Premium</h2>
                </div>
                <p>Free videos stay open to everyone. Premium videos require a signed-in Premium member.</p>
            </div>
            <div class="pricing-grid">
                <article class="pricing-card">
                    <span class="pill pill--muted">Free</span>
                    <h3>Free account</h3>
                    <p>Watch every video marked as free with no payment required.</p>
                    <ul class="pricing-list">
                        <li>No login required for free videos</li>
                        <li>Create an account to manage security and upgrades</li>
                        <li>Upgrade anytime from your account</li>
                    </ul>
                    <?php if ($user): ?>
                        <a class="text-link" href="<?= e(base_url('account.php')); ?>">Open account</a>
                    <?php else: ?>
                        <a class="text-link" href="<?= e(base_url('register.php')); ?>">Create account</a>
                    <?php endif; ?>
                </article>
                <article class="pricing-card pricing-card--accent">
                    <span class="pill"><?= e($billing->planName()); ?></span>
                    <h3><?= e($billing->planPriceLabel()); ?></h3>
                    <p><?= e($billing->planCopy()); ?></p>
                    <ul class="pricing-list">
                        <li>Required for every video marked Premium</li>
                        <li>Manage payment details and cancel anytime</li>
                        <li>Your access updates automatically after payment</li>
                    </ul>
                    <?php if ($billingConfigured && $user && !user_has_premium_access($user)): ?>
                        <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>">
                            <?= csrf_input('billing_checkout'); ?>
                            <button class="button" type="submit">Start secure checkout</button>
                        </form>
                    <?php elseif ($billingConfigured && $user && (user_has_premium_access($user) || !empty($user['stripe_customer_id']))): ?>
                        <form method="post" action="<?= e(base_url('manage-billing.php')); ?>">
                            <?= csrf_input('billing_portal'); ?>
                            <button class="button" type="submit">Manage plan</button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a class="button" href="<?= e(base_url('login.php')); ?>">Sign in to upgrade</a>
                    <?php else: ?>
                        <p class="form-note">Premium access is temporarily unavailable.</p>
                    <?php endif; ?>
                </article>
            </div>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">ACCESS RULES</span>
                    <h2>What Premium changes</h2>
                </div>
                <p>Keep the difference simple so visitors understand what each label means before checkout.</p>
            </div>
            <div class="compliance-grid">
                <article class="compliance-card">
                    <h3>Free</h3>
                    <p>Videos marked as free can be watched by any visitor, even without a login.</p>
                </article>
                <article class="compliance-card">
                    <h3>Premium</h3>
                    <p>Videos marked as Premium only play for signed-in members with an active Premium plan.</p>
                </article>
                <article class="compliance-card">
                    <h3>Checkout</h3>
                    <p>The upgrade flow opens a secure payment page and returns here when payment is complete.</p>
                </article>
                <article class="compliance-card">
                    <h3>Manage plan</h3>
                    <p>Existing subscribers can update payment details or cancel their plan from their account.</p>
                </article>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-band__copy">
                <span class="eyebrow">SUPPORT</span>
                <h2>Need help before upgrading?</h2>
                <p>Use the support page for account access, billing help, and legal contact information.</p>
            </div>
            <div class="hero__actions">
                <a class="button button--ghost" href="<?= e(base_url('support.php')); ?>">Open support</a>
            </div>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('premium')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
