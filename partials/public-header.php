<?php

declare(strict_types=1);

$publicNavActive = $publicNavActive ?? '';
$user = $user ?? current_user();
$publicSearchValue = trim((string) ($publicSearchValue ?? ($_GET['q'] ?? '')));
$navigationItems = [
    'home' => ['label' => copy_text('header.nav.home', 'Home'), 'href' => base_url()],
    'browse' => ['label' => copy_text('header.nav.browse', 'Browse'), 'href' => base_url('browse.php')],
    'premium' => ['label' => copy_text('header.nav.premium', 'Premium'), 'href' => base_url('premium.php')],
    'support' => ['label' => copy_text('header.nav.support', 'Support'), 'href' => base_url('support.php')],
];

if ($user && is_creator()) {
    $navigationItems['studio'] = ['label' => copy_text('header.nav.studio', 'Studio'), 'href' => base_url('studio.php')];
}
$legalItems = [
    ['label' => rules_nav_label(), 'href' => base_url('rules.php')],
    ['label' => (string) (legal_page_config('terms')['title'] ?? 'Terms'), 'href' => base_url('terms.php')],
    ['label' => (string) (legal_page_config('privacy')['title'] ?? 'Privacy'), 'href' => base_url('privacy.php')],
    ['label' => (string) (legal_page_config('cookies')['title'] ?? 'Cookies'), 'href' => base_url('cookies.php')],
];
?>
<header class="site-header shell-header">
    <div class="shell-header__brand">
        <button class="shell-menu-toggle" type="button" data-shell-menu aria-label="Open navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <a class="shell-brand" href="<?= e(base_url()); ?>">
            <span class="shell-brand__icon"></span>
            <span class="shell-brand__wordmark">
                <?= e(config('app.name')); ?>
                <?php if (brand_title() !== ''): ?>
                    <small><?= e(brand_title()); ?></small>
                <?php endif; ?>
            </span>
        </a>
    </div>
    <form class="shell-search" method="get" action="<?= e(base_url('browse.php')); ?>">
        <label class="shell-search__field">
            <input
                type="search"
                name="q"
                value="<?= e($publicSearchValue); ?>"
                placeholder="<?= e(copy_text('browse.catalog.search_placeholder', 'Search titles or creators')); ?>"
            >
        </label>
        <button class="shell-search__submit" type="submit">Search</button>
    </form>
    <div class="shell-header__actions">
        <a class="shell-quick-link <?= $publicNavActive === 'browse' ? 'is-active' : ''; ?>" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('header.nav.browse', 'Browse')); ?></a>
        <a class="shell-quick-link <?= $publicNavActive === 'premium' ? 'is-active' : ''; ?>" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('header.nav.premium', 'Premium')); ?></a>
        <a class="shell-quick-link <?= $publicNavActive === 'support' ? 'is-active' : ''; ?>" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('header.nav.support', 'Support')); ?></a>
        <?php if ($user): ?>
            <?php if (is_creator()): ?>
                <a class="shell-quick-link <?= $publicNavActive === 'studio' ? 'is-active' : ''; ?>" href="<?= e(base_url('studio.php')); ?>"><?= e(copy_text('header.nav.studio', 'Studio')); ?></a>
            <?php endif; ?>
            <span class="shell-user-badge">
                <?php if (is_creator() && !empty($user['resolved_creator_avatar_url'])): ?>
                    <img src="<?= e((string) $user['resolved_creator_avatar_url']); ?>" alt="<?= e((string) ($user['display_name'] ?? 'Account')); ?>">
                <?php endif; ?>
                <?= e((string) ($user['display_name'] ?? copy_text('header.nav.member_fallback', 'Member'))); ?>
            </span>
            <?php if (is_admin()): ?>
                <a class="shell-action shell-action--ghost" href="<?= e(base_url('admin.php')); ?>"><?= e(copy_text('header.nav.admin', 'Admin')); ?></a>
            <?php endif; ?>
            <a class="shell-action shell-action--ghost" href="<?= e(base_url('account.php')); ?>"><?= e(copy_text('header.nav.account', 'My account')); ?></a>
            <?= logout_button(copy_text('header.nav.log_out', 'Log out'), 'shell-action shell-action--ghost'); ?>
        <?php else: ?>
            <a class="shell-action shell-action--ghost" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('header.nav.sign_in', 'Sign in')); ?></a>
            <a class="shell-action" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('header.nav.join', 'Join')); ?></a>
        <?php endif; ?>
    </div>
</header>
<aside class="shell-sidebar" data-shell-sidebar>
    <nav class="shell-sidebar__nav">
        <section class="shell-sidebar__group">
            <span class="shell-sidebar__label">Discover</span>
            <?php foreach ($navigationItems as $key => $item): ?>
                <a class="shell-sidebar__link <?= $publicNavActive === $key ? 'is-active' : ''; ?>" href="<?= e((string) $item['href']); ?>">
                    <span><?= e((string) $item['label']); ?></span>
                    <?php if ($publicNavActive === $key): ?>
                        <span class="shell-sidebar__status">Live</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
        <section class="shell-sidebar__group">
            <span class="shell-sidebar__label">Policies</span>
            <?php foreach ($legalItems as $item): ?>
                <a class="shell-sidebar__link" href="<?= e((string) $item['href']); ?>">
                    <span><?= e((string) $item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </section>
        <section class="shell-sidebar__group">
            <span class="shell-sidebar__label"><?= $user ? 'Account' : 'Access'; ?></span>
            <?php if ($user): ?>
                <a class="shell-sidebar__link" href="<?= e(base_url('account.php')); ?>">
                    <span><?= e(copy_text('header.nav.account', 'My account')); ?></span>
                    <span class="shell-sidebar__status"><?= e((string) ($user['account_tier'] ?? 'free')); ?></span>
                </a>
                <?php if (is_creator()): ?>
                    <a class="shell-sidebar__link <?= $publicNavActive === 'studio' ? 'is-active' : ''; ?>" href="<?= e(base_url('studio.php')); ?>">
                        <span><?= e(copy_text('header.nav.studio', 'Studio')); ?></span>
                    </a>
                <?php endif; ?>
                <?php if (!empty($user['creator_slug'])): ?>
                    <a class="shell-sidebar__link" href="<?= e(base_url('channel.php?creator=' . urlencode((string) $user['creator_slug']))); ?>">
                        <span><?= e(copy_text('header.nav.channel', 'My channel')); ?></span>
                    </a>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <a class="shell-sidebar__link" href="<?= e(base_url('admin.php')); ?>">
                        <span><?= e(copy_text('header.nav.admin', 'Admin')); ?></span>
                    </a>
                <?php endif; ?>
                <a class="shell-sidebar__link" href="<?= e(base_url('support.php')); ?>">
                    <span><?= e(copy_text('common.open_support', 'Open support')); ?></span>
                </a>
            <?php else: ?>
                <a class="shell-sidebar__link" href="<?= e(base_url('login.php')); ?>">
                    <span><?= e(copy_text('header.nav.sign_in', 'Sign in')); ?></span>
                </a>
                <a class="shell-sidebar__link" href="<?= e(base_url('register.php')); ?>">
                    <span><?= e(copy_text('header.nav.join', 'Join')); ?></span>
                </a>
                <a class="shell-sidebar__link" href="<?= e(base_url('premium.php')); ?>">
                    <span><?= e(copy_text('common.view_plans', 'View plans')); ?></span>
                </a>
            <?php endif; ?>
        </section>
    </nav>
</aside>
<div class="shell-overlay" data-shell-overlay></div>
