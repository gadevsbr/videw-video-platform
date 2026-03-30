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
    <?php
    $publicNavActive = 'support';
    $publicBarItems = ['Adults only 18+', 'Policies and support', 'Public information'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell">
        <section class="hero legal-hero">
            <div class="hero__copy">
                <span class="eyebrow"><?= e($legalKicker); ?></span>
                <h1><?= e($legalTitle); ?></h1>
                <p><?= e($legalIntro); ?></p>
                <div class="hero__actions">
                    <a class="button" href="<?= e(base_url('support.php')); ?>">Open support</a>
                    <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>">Browse videos</a>
                </div>
            </div>
            <aside class="hero__aside legal-hero__aside">
                <article class="notice-card">
                    <strong><?= e(config('app.name')); ?></strong>
                    <p><?= e((string) config('app.description')); ?></p>
                </article>
                <article class="notice-card">
                    <strong>Support</strong>
                    <p><?= e((string) config('footer.support_copy', 'Questions, account help, legal notices, and takedown requests.')); ?></p>
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
