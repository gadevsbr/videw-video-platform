<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

$repository = new VideoRepository();
$mediaAccess = new MediaAccessService();
$videos = $mediaAccess->decorateVideos($repository->listPublished());
$publicVideos = array_map(static fn (array $video): array => public_catalog_video_payload($video), $videos);
$featured = array_values(array_filter($videos, static fn (array $video): bool => (int) $video['is_featured'] === 1));
$featured = $featured ?: array_slice($videos, 0, 3);
$heroVideo = $featured[0] ?? $videos[0] ?? null;
$heroQueue = array_values(array_filter(
    array_slice($videos, 0, 10),
    static fn (array $video): bool => $heroVideo === null || (string) $video['slug'] !== (string) $heroVideo['slug']
));
$heroQueue = array_slice($heroQueue, 0, 4);
$previewVideos = array_slice($videos, 0, 8);
$shortVideos = array_slice($heroQueue, 0, 5);
$homeCategories = array_slice($repository->categories(), 0, 8);
$stats = $repository->stats();
$bootPayload = default_bootstrap_payload('home', [
    'usingFallback' => $repository->usingFallback(),
    'stats' => $stats,
    'videos' => $publicVideos,
]);
$flashError = flash('error');
$flashSuccess = flash('success');
$user = current_user();
clear_old_input();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app.name')); ?> | <?= e(copy_text('home.title_suffix', 'Video Platform')); ?></title>
    <meta name="description" content="<?= e((string) config('app.description')); ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = 'home';
    $publicBarItems = copy_items('header.bar.home');
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <?php if ($flashError): ?>
        <div class="flash flash--error"><?= e((string) $flashError); ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
    <?php endif; ?>

    <main class="page-shell">
        <section class="shell-strip">
            <a class="chip chip--active" href="<?= e(base_url('browse.php')); ?>">All</a>
            <?php foreach ($homeCategories as $category): ?>
                <a class="chip" href="<?= e(base_url('browse.php?category=' . urlencode($category))); ?>"><?= e($category); ?></a>
            <?php endforeach; ?>
        </section>

        <?php if ($heroVideo): ?>
            <section class="front-hero">
                <a class="front-hero__feature" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $heroVideo['slug']))); ?>">
                    <img src="<?= e((string) ($heroVideo['resolved_listing_poster_url'] ?? $heroVideo['resolved_poster_url'])); ?>" alt="<?= e($heroVideo['title']); ?>" style="object-position: <?= e(poster_object_position($heroVideo)); ?>;">
                    <div class="front-hero__overlay">
                        <span class="front-badge"><?= e(copy_text('home.featured_eyebrow', 'Featured')); ?></span>
                        <div class="front-hero__copy">
                            <h1><?= e($heroVideo['title']); ?></h1>
                            <p><?= e($heroVideo['synopsis']); ?></p>
                        </div>
                        <div class="front-meta-row">
                            <span><?= e($heroVideo['creator_name']); ?></span>
                            <span><?= e($heroVideo['published_label']); ?></span>
                            <span><?= e($heroVideo['duration_label']); ?></span>
                        </div>
                        <div class="front-actions">
                            <span class="front-primary-action"><?= e(copy_text('common.watch_now', 'Watch now')); ?></span>
                            <span class="front-secondary-action"><?= e($heroVideo['access_label']); ?></span>
                        </div>
                    </div>
                </a>
                <aside class="front-hero__side">
                    <div class="front-stat-row">
                        <article class="front-stat">
                            <span>Videos</span>
                            <strong><?= e((string) $stats['videos']); ?></strong>
                        </article>
                        <article class="front-stat">
                            <span>Creators</span>
                            <strong><?= e((string) $stats['creators']); ?></strong>
                        </article>
                        <article class="front-stat">
                            <span>Premium</span>
                            <strong><?= e((string) $stats['premium']); ?></strong>
                        </article>
                    </div>
                    <div class="front-side-list">
                        <?php foreach ($heroQueue as $item): ?>
                            <a class="front-side-item" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                <img src="<?= e((string) ($item['resolved_listing_poster_url'] ?? $item['resolved_poster_url'])); ?>" alt="<?= e($item['title']); ?>" style="object-position: <?= e(poster_object_position($item)); ?>;">
                                <div>
                                    <h3><?= e($item['title']); ?></h3>
                                    <p><?= e($item['creator_name']); ?></p>
                                    <span><?= e($item['duration_label']); ?> • <?= e($item['access_label']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>
        <?php endif; ?>

        <?php if ($repository->usingFallback()): ?>
            <article class="front-banner">
                <strong><?= e(copy_text('home.fallback_notice_title', 'Preview catalog')); ?></strong>
                <p><?= e(copy_text('home.fallback_notice_text', 'A preview selection is showing right now. The full library will appear here when your site is fully connected.')); ?></p>
            </article>
        <?php endif; ?>

        <section class="front-section">
            <div class="front-section__header">
                <div>
                    <h2><?= e(copy_text('home.quick_title', 'Recommended for you')); ?></h2>
                    <p><?= e(copy_text('home.quick_description', 'Open the library when you want the full search and filter experience.')); ?></p>
                </div>
                <a class="front-link" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('home.next_primary_cta', 'Open browse page')); ?></a>
            </div>
            <div class="front-feed-grid">
                <?php foreach ($previewVideos as $video): ?>
                    <article class="front-feed-card">
                        <a class="front-feed-card__thumb" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                            <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                            <span class="front-duration"><?= e($video['duration_label']); ?></span>
                        </a>
                        <div class="front-feed-card__body">
                            <div class="front-avatar"><?= e(mb_strtoupper(mb_substr((string) $video['creator_name'], 0, 1))); ?></div>
                            <div class="front-feed-card__meta">
                                <h3><?= e($video['title']); ?></h3>
                                <p><?= e($video['creator_name']); ?></p>
                                <span><?= e($video['access_label']); ?> • <?= e($video['published_label']); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($shortVideos !== []): ?>
            <section class="front-section">
                <div class="front-section__header">
                    <div>
                        <h2><?= e(copy_text('home.membership_title', 'Quick picks')); ?></h2>
                        <p><?= e(copy_text('home.membership_description', 'Fast vertical picks for discovery, short previews, and rapid browsing.')); ?></p>
                    </div>
                </div>
                <div class="front-shorts-grid">
                    <?php foreach ($shortVideos as $video): ?>
                        <a class="front-short-card" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                            <div class="front-short-card__media">
                                <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                <span class="front-duration"><?= e($video['duration_label']); ?></span>
                            </div>
                            <div class="front-short-card__body">
                                <h3><?= e($video['title']); ?></h3>
                                <p><?= e($video['creator_name']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="front-cta-row">
            <a class="front-cta-card" href="<?= e(base_url('premium.php')); ?>">
                <strong><?= e(copy_text('home.premium_badge', 'Premium')); ?></strong>
                <p><?= e(copy_text('home.premium_text', 'Unlock every Premium video with one plan and manage it from your account.')); ?></p>
            </a>
            <a class="front-cta-card" href="<?= e(base_url('support.php')); ?>">
                <strong><?= e(copy_text('home.next_secondary_cta', 'Need help?')); ?></strong>
                <p><?= e(copy_text('home.next_description', 'Get account, billing, and platform help from one place.')); ?></p>
            </a>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap($bootPayload); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
