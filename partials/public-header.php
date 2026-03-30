<?php

declare(strict_types=1);

$publicNavActive = $publicNavActive ?? '';
$publicBarItems = $publicBarItems ?? ['Adults only 18+', 'Verified access', 'Clean playback'];
$user = $user ?? current_user();
$navigationItems = [
    'home' => ['label' => 'Home', 'href' => base_url()],
    'browse' => ['label' => 'Browse', 'href' => base_url('browse.php')],
    'premium' => ['label' => 'Premium', 'href' => base_url('premium.php')],
    'support' => ['label' => 'Support', 'href' => base_url('support.php')],
];
?>
<div class="legal-bar">
    <?php foreach ($publicBarItems as $item): ?>
        <span><?= e((string) $item); ?></span>
    <?php endforeach; ?>
</div>
<header class="site-header">
    <a class="brandmark" href="<?= e(base_url()); ?>">
        <span class="brandmark__kicker"><?= e(brand_kicker()); ?></span>
        <?php if (brand_title() !== ''): ?>
            <span class="brandmark__title"><?= e(brand_title()); ?></span>
        <?php endif; ?>
    </a>
    <nav class="site-nav">
        <?php foreach ($navigationItems as $key => $item): ?>
            <a class="<?= $publicNavActive === $key ? 'is-active' : ''; ?>" href="<?= e((string) $item['href']); ?>"><?= e((string) $item['label']); ?></a>
        <?php endforeach; ?>
        <?php if (is_admin()): ?>
            <a href="<?= e(base_url('admin.php')); ?>">Admin</a>
        <?php endif; ?>
    </nav>
    <div class="site-nav__actions">
        <?php if ($user): ?>
            <span class="pill pill--muted"><?= e((string) ($user['display_name'] ?? 'Member')); ?></span>
            <?php if (is_admin()): ?>
                <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>">Admin</a>
            <?php endif; ?>
            <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">My account</a>
            <?= logout_button('Log out'); ?>
        <?php else: ?>
            <a class="button button--ghost" href="<?= e(base_url('login.php')); ?>">Sign in</a>
            <a class="button" href="<?= e(base_url('register.php')); ?>">Join</a>
        <?php endif; ?>
    </div>
</header>
