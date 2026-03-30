<?php

declare(strict_types=1);

$publicNavActive = $publicNavActive ?? '';
$publicBarItems = $publicBarItems ?? copy_items('header.bar.home');
$user = $user ?? current_user();
$navigationItems = [
    'home' => ['label' => copy_text('header.nav.home', 'Home'), 'href' => base_url()],
    'browse' => ['label' => copy_text('header.nav.browse', 'Browse'), 'href' => base_url('browse.php')],
    'premium' => ['label' => copy_text('header.nav.premium', 'Premium'), 'href' => base_url('premium.php')],
    'support' => ['label' => copy_text('header.nav.support', 'Support'), 'href' => base_url('support.php')],
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
            <a href="<?= e(base_url('admin.php')); ?>"><?= e(copy_text('header.nav.admin', 'Admin')); ?></a>
        <?php endif; ?>
    </nav>
    <div class="site-nav__actions">
        <?php if ($user): ?>
            <span class="pill pill--muted"><?= e((string) ($user['display_name'] ?? copy_text('header.nav.member_fallback', 'Member'))); ?></span>
            <?php if (is_admin()): ?>
                <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>"><?= e(copy_text('header.nav.admin', 'Admin')); ?></a>
            <?php endif; ?>
            <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>"><?= e(copy_text('header.nav.account', 'My account')); ?></a>
            <?= logout_button(copy_text('header.nav.log_out', 'Log out')); ?>
        <?php else: ?>
            <a class="button button--ghost" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('header.nav.sign_in', 'Sign in')); ?></a>
            <a class="button" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('header.nav.join', 'Join')); ?></a>
        <?php endif; ?>
    </div>
</header>
