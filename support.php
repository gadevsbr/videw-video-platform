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
    <title>Support | <?= e(config('app.name')); ?></title>
    <meta name="description" content="Get help with account access, Premium billing, legal notices, and platform rules.">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <?php
    $publicNavActive = 'support';
    $publicBarItems = ['Adults only 18+', 'Account and billing help', 'Legal contact'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell">
        <section class="page-intro">
            <div class="page-intro__copy">
                <span class="eyebrow">SUPPORT</span>
                <h1>Help users find the right next step.</h1>
                <p>Use this page for account access, Premium billing questions, legal notices, and general site guidance.</p>
                <div class="hero__actions">
                    <?php if ($supportEmail !== ''): ?>
                        <a class="button" href="mailto:<?= e($supportEmail); ?>">Email support</a>
                    <?php endif; ?>
                    <a class="button button--ghost" href="<?= e(base_url('rules.php')); ?>">Read platform rules</a>
                </div>
            </div>
            <aside class="page-intro__aside">
                <article class="notice-card">
                    <strong>Support email</strong>
                    <?php if ($supportEmail !== ''): ?>
                        <a class="text-link" href="mailto:<?= e($supportEmail); ?>"><?= e($supportEmail); ?></a>
                    <?php else: ?>
                        <p>Add a support email from the admin panel to show contact details here.</p>
                    <?php endif; ?>
                </article>
                <article class="notice-card">
                    <strong>Best for</strong>
                    <p>Account access, Premium payments, policy questions, and takedown or compliance contact.</p>
                </article>
            </aside>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">HELP TOPICS</span>
                    <h2>Choose the right path</h2>
                </div>
                <p>Keep support organized by the job the visitor needs to complete.</p>
            </div>
            <div class="support-grid">
                <article class="support-card">
                    <span class="pill pill--muted">Accounts</span>
                    <h3>Sign in and account access</h3>
                    <p>Use these pages when someone needs to sign in, register, reset a password, or manage account security.</p>
                    <div class="site-footer__links">
                        <a class="text-link" href="<?= e(base_url('login.php')); ?>">Sign in</a>
                        <a class="text-link" href="<?= e(base_url('register.php')); ?>">Create account</a>
                        <a class="text-link" href="<?= e(base_url('forgot-password.php')); ?>">Reset password</a>
                        <?php if ($user): ?>
                            <a class="text-link" href="<?= e(base_url('account.php')); ?>">Open my account</a>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="support-card">
                    <span class="pill">Premium</span>
                    <h3>Plans and billing</h3>
                    <p>Show visitors how Free and Premium access works and where they can manage an active subscription.</p>
                    <div class="site-footer__links">
                        <a class="text-link" href="<?= e(base_url('premium.php')); ?>">View plans</a>
                        <?php if ($user): ?>
                            <a class="text-link" href="<?= e(base_url('account.php#subscription')); ?>">Membership status</a>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="support-card">
                    <span class="pill pill--muted">Policies</span>
                    <h3>Rules and legal information</h3>
                    <p>Use these pages for platform rules, terms, privacy, and cookie information.</p>
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
                <span class="eyebrow">DISCOVERY</span>
                <h2>Still browsing?</h2>
                <p>Head back to the public catalog to browse Free and Premium titles with filters and sorting.</p>
            </div>
            <div class="hero__actions">
                <a class="button" href="<?= e(base_url('browse.php')); ?>">Browse videos</a>
                <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>">See Premium</a>
            </div>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('support')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
