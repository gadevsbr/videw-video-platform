<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$user = current_user();
$supportEmail = (string) config('app.support_email');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(copy_text('support.meta_title', 'Support')); ?> | <?= e(config('app.name')); ?></title>
    <meta name="description" content="<?= e(copy_text('support.meta_description', 'Get help with account access, Premium billing, legal notices, and platform rules.')); ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = 'support';
    $publicBarItems = copy_items('header.bar.support');
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell">
        <section class="page-intro page-intro--single">
            <div class="page-intro__copy">
                <span class="eyebrow"><?= e(copy_text('support.hero_eyebrow', 'SUPPORT')); ?></span>
                <h1><?= e(copy_text('support.hero_title', 'Help users find the right next step.')); ?></h1>
                <p><?= e(copy_text('support.hero_description', 'Use this page for account access, Premium billing questions, legal notices, and general site guidance.')); ?></p>
                <div class="hero__actions">
                    <?php if ($supportEmail !== ''): ?>
                        <a class="button" href="mailto:<?= e($supportEmail); ?>"><?= e(copy_text('support.hero_primary_cta', 'Email support')); ?></a>
                    <?php endif; ?>
                    <a class="button button--ghost" href="<?= e(base_url('rules.php')); ?>"><?= e(copy_text('support.hero_secondary_cta', 'Read platform rules')); ?></a>
                </div>
            </div>
        </section>

        <?= render_public_ad_slot('support_inline', 'front-section__ad'); ?>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= e(copy_text('support.topics_eyebrow', 'HELP TOPICS')); ?></span>
                    <h2><?= e(copy_text('support.topics_title', 'Choose the right path')); ?></h2>
                </div>
                <p><?= e(copy_text('support.topics_description', 'Keep support organized by the job the visitor needs to complete.')); ?></p>
            </div>
            <div class="support-grid">
                <article class="support-card">
                    <span class="pill pill--muted"><?= e(copy_text('support.accounts_badge', 'Accounts')); ?></span>
                    <h3><?= e(copy_text('support.accounts_title', 'Sign in and account access')); ?></h3>
                    <p><?= e(copy_text('support.accounts_text', 'Use these pages when someone needs to sign in, register, reset a password, or manage account security.')); ?></p>
                    <div class="site-footer__links">
                        <a class="text-link" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('header.nav.sign_in', 'Sign in')); ?></a>
                        <a class="text-link" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('common.create_account', 'Create account')); ?></a>
                        <a class="text-link" href="<?= e(base_url('forgot-password.php')); ?>"><?= e(copy_text('auth.forgot.title', 'Reset password')); ?></a>
                        <?php if ($user): ?>
                            <a class="text-link" href="<?= e(base_url('account.php')); ?>"><?= e(copy_text('header.nav.account', 'My account')); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="support-card">
                    <span class="pill"><?= e(copy_text('support.billing_badge', 'Premium')); ?></span>
                    <h3><?= e(copy_text('support.billing_title', 'Plans and billing')); ?></h3>
                    <p><?= e(copy_text('support.billing_text', 'Show visitors how Free and Premium access works and where they can manage an active subscription.')); ?></p>
                    <div class="site-footer__links">
                        <a class="text-link" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('common.view_plans', 'View plans')); ?></a>
                        <?php if ($user): ?>
                            <a class="text-link" href="<?= e(base_url('account.php#subscription')); ?>"><?= e(copy_text('support.billing_link_signed_in', 'Membership status')); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="support-card">
                    <span class="pill pill--muted"><?= e(copy_text('support.policies_badge', 'Policies')); ?></span>
                    <h3><?= e(copy_text('support.policies_title', 'Rules and legal information')); ?></h3>
                    <p><?= e(copy_text('support.policies_text', 'Use these pages for platform rules, terms, privacy, and cookie information.')); ?></p>
                    <div class="site-footer__links">
                        <a class="text-link" href="<?= e(base_url('rules.php')); ?>">Platform rules</a>
                        <a class="text-link" href="<?= e(base_url('terms.php')); ?>">Terms of use</a>
                        <a class="text-link" href="<?= e(base_url('privacy.php')); ?>">Privacy policy</a>
                        <a class="text-link" href="<?= e(base_url('cookies.php')); ?>">Cookie policy</a>
                    </div>
                </article>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-band__copy">
                <span class="eyebrow"><?= e(copy_text('support.discovery_eyebrow', 'DISCOVERY')); ?></span>
                <h2><?= e(copy_text('support.discovery_title', 'Still browsing?')); ?></h2>
                <p><?= e(copy_text('support.discovery_text', 'Head back to the public catalog to browse Free and Premium titles with filters and sorting.')); ?></p>
            </div>
            <div class="hero__actions">
                <a class="button" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('support.discovery_primary_cta', 'Browse videos')); ?></a>
                <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('support.discovery_secondary_cta', 'See Premium')); ?></a>
            </div>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('support')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
