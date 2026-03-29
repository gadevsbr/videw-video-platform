<?php

declare(strict_types=1);

$legalKey = $legalKey ?? 'terms';
$legalPage = $legalPage ?? legal_page_config($legalKey);
$legalTitle = (string) ($legalPage['title'] ?? 'Legal page');
$legalIntro = (string) ($legalPage['intro'] ?? '');
$legalKicker = (string) ($legalPage['kicker'] ?? 'Legal');
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($legalTitle); ?> | <?= e(config('app.name')); ?></title>
    <meta name="description" content="<?= e($legalIntro !== '' ? $legalIntro : (string) config('app.description')); ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <div class="legal-bar">
        <span>Adults only 18+</span>
        <span>Legal information</span>
        <span>Public site pages</span>
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
            <a href="<?= e(base_url('account.php')); ?>">Account</a>
            <?php if (is_admin()): ?>
                <a href="<?= e(base_url('admin.php')); ?>">Admin</a>
            <?php endif; ?>
        </nav>
        <div class="site-nav__actions">
            <?php if ($user): ?>
                <?php if (is_admin()): ?>
                    <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>">Admin</a>
                <?php endif; ?>
                <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">Dashboard</a>
            <?php else: ?>
                <a class="button button--ghost" href="<?= e(base_url('login.php')); ?>">Sign in</a>
                <a class="button" href="<?= e(base_url('register.php')); ?>">Join</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="page-shell">
        <section class="hero legal-hero">
            <div class="hero__copy">
                <span class="eyebrow"><?= e($legalKicker); ?></span>
                <h1><?= e($legalTitle); ?></h1>
                <p><?= e($legalIntro); ?></p>
                <div class="hero__actions">
                    <a class="button" href="<?= e(base_url('index.php#catalog')); ?>">Back to browse</a>
                    <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">Account</a>
                </div>
            </div>
            <aside class="hero__aside legal-hero__aside">
                <article class="notice-card">
                    <strong><?= e(config('app.name')); ?></strong>
                    <p><?= e((string) config('app.description')); ?></p>
                </article>
                <article class="notice-card">
                    <strong>Support</strong>
                    <p><?= e((string) config('footer.support_copy', 'Questions, takedowns, and legal notices.')); ?></p>
                    <a class="text-link" href="mailto:<?= e(config('app.support_email')); ?>"><?= e(config('app.support_email')); ?></a>
                </article>
            </aside>
        </section>

        <?php if ($legalKey === 'rules'): ?>
            <section class="legal-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= e($legalKicker); ?></span>
                        <h2><?= e($legalTitle); ?></h2>
                    </div>
                    <p><?= e($legalIntro); ?></p>
                </div>
                <div class="compliance-grid">
                    <?php foreach (rules_page_items() as $item): ?>
                        <article class="compliance-card">
                            <h3><?= e($item['title']); ?></h3>
                            <p><?= e($item['copy']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="legal-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= e($legalKicker); ?></span>
                        <h2><?= e($legalTitle); ?></h2>
                    </div>
                    <p><?= e($legalIntro); ?></p>
                </div>
                <article class="legal-copy">
                    <?= render_text_blocks((string) ($legalPage['content'] ?? '')); ?>
                </article>
            </section>
        <?php endif; ?>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload($legalKey)); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
