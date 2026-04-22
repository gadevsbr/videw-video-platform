<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= e(trim(page_lock_class('admin-layout-page', false))); ?>">
    <div class="legal-bar">
        <span>Admin workspace</span>
        <span><?= e($screenLabels[$screen] ?? 'Overview'); ?></span>
        <span><?= e($dbReady ? (($databaseVersionStatus['db_version'] ?? '') !== '' ? 'DB ' . (string) $databaseVersionStatus['db_version'] : 'Database ready') : 'Database pending'); ?></span>
    </div>
    <header class="site-header admin-topbar">
        <div class="admin-topbar__brand">
            <a class="shell-brand" href="<?= e(base_url()); ?>">
                <span class="shell-brand__icon"></span>
                <span class="shell-brand__wordmark">
                    <?= e(config('app.name')); ?>
                    <?php if (brand_title() !== ''): ?>
                        <small><?= e(brand_title()); ?></small>
                    <?php endif; ?>
                </span>
            </a>
            <span class="pill pill--muted">Admin</span>
        </div>
        <nav class="site-nav admin-topbar__nav">
            <a href="<?= e(base_url()); ?>">Home</a>
            <a href="<?= e(base_url('browse.php')); ?>">Browse</a>
            <a href="<?= e(base_url('studio.php')); ?>">Studio</a>
            <a href="<?= e(base_url('support.php')); ?>">Support</a>
        </nav>
        <div class="site-nav__actions admin-topbar__actions">
            <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">My account</a>
            <a class="button button--ghost" href="<?= e(base_url()); ?>">View site</a>
            <?= logout_button('Log out'); ?>
        </div>
    </header>

    <main class="page-shell admin-shell">
        <?php if ($flashError): ?>
            <div class="flash flash--error"><?= e((string) $flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
        <?php endif; ?>

        <div class="admin-layout">
            <aside class="admin-sidebar">
                <div class="admin-sidebar__intro">
                    <span class="eyebrow">ADMIN</span>
                    <strong><?= e(config('app.name')); ?></strong>
                    <p>Run content, members, billing, and site settings from one workspace.</p>
                </div>

                <div class="admin-sidebar__utility">
                    <a class="button" href="<?= e($screenUrl('publish')); ?>">New video</a>
                    <a class="button button--ghost" href="<?= e($screenUrl('library')); ?>">Open library</a>
                </div>

                <?php if (!$dbReady): ?>
                    <div class="notice-card">
                        <strong>Setup still in progress</strong>
                        <p>Publishing and user actions will work fully once the site database is available.</p>
                    </div>
                <?php endif; ?>
                <?php if ($dbReady && !($databaseVersionStatus['tracking_ready'] ?? false)): ?>
                    <div class="notice-card">
                        <strong>Schema tracking missing</strong>
                        <p>Apply the upgrade SQL files in <code>updates/1.0.3/sql/</code> to enable database version tracking on this install.</p>
                    </div>
                <?php endif; ?>
                <?php if ($dbReady && ($databaseVersionStatus['upgrade_required'] ?? false)): ?>
                    <div class="notice-card">
                        <strong>Database upgrade pending</strong>
                        <p><?= e((string) ($databaseVersionStatus['message'] ?? 'Apply the pending upgrade SQL files.')); ?></p>
                    </div>
                <?php endif; ?>

                <div class="admin-sidebar__status">
                    <article class="mini-stat">
                        <span>Upload driver</span>
                        <strong><?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Library</span>
                        <strong><?= e((string) $adminStats['total']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Delivery</span>
                        <strong><?= $privateDelivery ? 'Signed' : 'Public'; ?></strong>
                    </article>
                </div>

                <?php foreach ($adminNavGroups as $groupTitle => $items): ?>
                    <section class="admin-sidebar__group">
                        <span class="admin-sidebar__group-title"><?= e($groupTitle); ?></span>
                        <div class="admin-sidebar__links">
                            <?php foreach ($items as $key): ?>
                                <a class="<?= $screen === $key ? 'admin-sidebar__link is-active' : 'admin-sidebar__link'; ?>" href="<?= e($screenUrl($key)); ?>">
                                    <span><?= e($screenLabels[$key] ?? ucfirst($key)); ?></span>
                                    <?php if ($screen === $key): ?>
                                        <span class="pill">Open</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </aside>

            <div class="admin-main">
                <section class="admin-page-header">
                    <div class="admin-page-header__copy">
                        <span class="eyebrow"><?= e($currentScreen['eyebrow']); ?></span>
                        <h1><?= e($currentScreen['title']); ?></h1>
                        <p><?= e($currentScreen['copy']); ?></p>
                    </div>
                    <div class="admin-page-header__actions">
                        <a class="button" href="<?= e($currentScreen['primary']['href']); ?>"><?= e($currentScreen['primary']['label']); ?></a>
                        <a class="button button--ghost" href="<?= e($currentScreen['secondary']['href']); ?>"><?= e($currentScreen['secondary']['label']); ?></a>
                    </div>
                </section>
