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
        flash('success', $user ? $result['message'] : copy_text('messages.billing.checkout_success_guest', 'Your payment is confirmed. Sign in to refresh your account.'));
    } else {
        flash('error', $result['message']);
    }

    redirect('premium.php');
}

if ($checkoutState === 'cancel') {
    flash('error', copy_text('messages.billing.checkout_canceled', 'Checkout was canceled before payment was completed.'));
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
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = 'premium';
    $publicBarItems = copy_items('header.bar.premium');
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
                <span class="eyebrow"><?= e(copy_text('premium.hero_eyebrow', 'PREMIUM ACCESS')); ?></span>
                <h1><?= e($billing->planName()); ?></h1>
                <p><?= e($billing->planCopy()); ?></p>
                <div class="hero__actions">
                    <?php if ($billingConfigured && $user && !user_has_premium_access($user)): ?>
                        <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>">
                            <?= csrf_input('billing_checkout'); ?>
                            <button class="button" type="submit"><?= e(copy_text('premium.hero_primary_cta', 'Upgrade now')); ?></button>
                        </form>
                    <?php elseif ($billingConfigured && $user && (user_has_premium_access($user) || !empty($user['stripe_customer_id']))): ?>
                        <form method="post" action="<?= e(base_url('manage-billing.php')); ?>">
                            <?= csrf_input('billing_portal'); ?>
                            <button class="button" type="submit"><?= e(copy_text('premium.hero_manage_cta', 'Manage plan')); ?></button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a class="button" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('premium.hero_guest_cta', 'Create free account')); ?></a>
                    <?php endif; ?>
                    <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('premium.hero_secondary_cta', 'Browse videos')); ?></a>
                </div>
                <?php if (!$billingConfigured): ?>
                    <div class="notice-card">
                        <strong><?= e(copy_text('premium.disabled_title', 'Premium access is not available right now')); ?></strong>
                        <p><?php if (is_admin()): ?><?= e(copy_text('premium.disabled_text_admin', 'Finish the payment setup in the admin panel to open Premium memberships.')); ?><?php else: ?><?= e(copy_text('premium.disabled_text_public', 'Please check back soon.')); ?><?php endif; ?></p>
                        <?php if (is_admin()): ?>
                            <a class="text-link" href="<?= e(base_url('admin.php?screen=billing')); ?>"><?= e(copy_text('premium.disabled_link', 'Open payment settings')); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="hero__aside legal-hero__aside">
                <article class="notice-card">
                    <strong><?= e(copy_text('premium.bar_price_title', 'Price')); ?></strong>
                    <p><?= e($billing->planPriceLabel()); ?></p>
                </article>
                <article class="notice-card">
                    <strong><?= e(copy_text('premium.bar_split_title', 'Catalog split')); ?></strong>
                    <p><?= e((string) ($stats['premium'] ?? 0)); ?> premium videos and <?= e((string) max(0, ((int) ($stats['videos'] ?? 0)) - ((int) ($stats['premium'] ?? 0)))); ?> free videos.</p>
                </article>
                <?php if ($user): ?>
                    <article class="notice-card">
                        <strong><?= e(copy_text('premium.bar_account_title', 'Your account')); ?></strong>
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
                    <span class="eyebrow"><?= e(copy_text('premium.plans_eyebrow', 'PLANS')); ?></span>
                    <h2><?= e(copy_text('premium.plans_title', 'Free vs Premium')); ?></h2>
                </div>
                <p><?= e(copy_text('premium.plans_description', 'Free videos stay open to everyone. Premium videos require a signed-in Premium member.')); ?></p>
            </div>
            <div class="pricing-grid">
                <article class="pricing-card">
                    <span class="pill pill--muted"><?= e(copy_text('premium.free_badge', 'Free')); ?></span>
                    <h3><?= e(copy_text('premium.free_title', 'Free account')); ?></h3>
                    <p><?= e(copy_text('premium.free_text', 'Watch every video marked as free with no payment required.')); ?></p>
                    <ul class="pricing-list">
                        <li><?= e(copy_text('premium.free_item_1', 'No login required for free videos')); ?></li>
                        <li><?= e(copy_text('premium.free_item_2', 'Create an account to manage security and upgrades')); ?></li>
                        <li><?= e(copy_text('premium.free_item_3', 'Upgrade anytime from your account')); ?></li>
                    </ul>
                    <?php if ($user): ?>
                        <a class="text-link" href="<?= e(base_url('account.php')); ?>"><?= e(copy_text('premium.free_link_signed_in', 'Open account')); ?></a>
                    <?php else: ?>
                        <a class="text-link" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('premium.free_link_guest', 'Create account')); ?></a>
                    <?php endif; ?>
                </article>
                <article class="pricing-card pricing-card--accent">
                    <span class="pill"><?= e($billing->planName()); ?></span>
                    <h3><?= e($billing->planPriceLabel()); ?></h3>
                    <p><?= e($billing->planCopy()); ?></p>
                    <ul class="pricing-list">
                        <li><?= e(copy_text('premium.premium_item_1', 'Required for every video marked Premium')); ?></li>
                        <li><?= e(copy_text('premium.premium_item_2', 'Manage payment details and cancel anytime')); ?></li>
                        <li><?= e(copy_text('premium.premium_item_3', 'Your access updates automatically after payment')); ?></li>
                    </ul>
                    <?php if ($billingConfigured && $user && !user_has_premium_access($user)): ?>
                        <form method="post" action="<?= e(base_url('start-premium-checkout.php')); ?>">
                            <?= csrf_input('billing_checkout'); ?>
                            <button class="button" type="submit"><?= e(copy_text('premium.premium_checkout_cta', 'Start secure checkout')); ?></button>
                        </form>
                    <?php elseif ($billingConfigured && $user && (user_has_premium_access($user) || !empty($user['stripe_customer_id']))): ?>
                        <form method="post" action="<?= e(base_url('manage-billing.php')); ?>">
                            <?= csrf_input('billing_portal'); ?>
                            <button class="button" type="submit"><?= e(copy_text('premium.premium_manage_cta', 'Manage plan')); ?></button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a class="button" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('premium.premium_guest_cta', 'Sign in to upgrade')); ?></a>
                    <?php else: ?>
                        <p class="form-note"><?= e(copy_text('premium.premium_unavailable_note', 'Premium access is temporarily unavailable.')); ?></p>
                    <?php endif; ?>
                </article>
            </div>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= e(copy_text('premium.rules_eyebrow', 'ACCESS RULES')); ?></span>
                    <h2><?= e(copy_text('premium.rules_title', 'What Premium changes')); ?></h2>
                </div>
                <p><?= e(copy_text('premium.rules_description', 'Keep the difference simple so visitors understand what each label means before checkout.')); ?></p>
            </div>
            <div class="compliance-grid">
                <article class="compliance-card">
                    <h3><?= e(copy_text('premium.rule_free_title', 'Free')); ?></h3>
                    <p><?= e(copy_text('premium.rule_free_text', 'Videos marked as free can be watched by any visitor, even without a login.')); ?></p>
                </article>
                <article class="compliance-card">
                    <h3><?= e(copy_text('premium.rule_premium_title', 'Premium')); ?></h3>
                    <p><?= e(copy_text('premium.rule_premium_text', 'Videos marked as Premium only play for signed-in members with an active Premium plan.')); ?></p>
                </article>
                <article class="compliance-card">
                    <h3><?= e(copy_text('premium.rule_checkout_title', 'Checkout')); ?></h3>
                    <p><?= e(copy_text('premium.rule_checkout_text', 'The upgrade flow opens a secure payment page and returns here when payment is complete.')); ?></p>
                </article>
                <article class="compliance-card">
                    <h3><?= e(copy_text('premium.rule_manage_title', 'Manage plan')); ?></h3>
                    <p><?= e(copy_text('premium.rule_manage_text', 'Existing subscribers can update payment details or cancel their plan from their account.')); ?></p>
                </article>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-band__copy">
                <span class="eyebrow"><?= e(copy_text('premium.support_eyebrow', 'SUPPORT')); ?></span>
                <h2><?= e(copy_text('premium.support_title', 'Need help before upgrading?')); ?></h2>
                <p><?= e(copy_text('premium.support_text', 'Use the support page for account access, billing help, and legal contact information.')); ?></p>
            </div>
            <div class="hero__actions">
                <a class="button button--ghost" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('premium.support_cta', 'Open support')); ?></a>
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
