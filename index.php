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
    array_slice($videos, 0, 5),
    static fn (array $video): bool => $heroVideo === null || (string) $video['slug'] !== (string) $heroVideo['slug']
));
$heroQueue = array_slice($heroQueue, 0, 3);
$previewVideos = array_slice($videos, 0, 6);
$stats = $repository->stats();
$bootPayload = default_bootstrap_payload('home', [
    'usingFallback' => $repository->usingFallback(),
    'stats' => $stats,
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
<body class="<?= e(page_lock_class()); ?>">
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
        <section class="hero hero--landing">
            <div class="hero__copy">
                <span class="eyebrow"><?= e(copy_text('home.hero_eyebrow', 'VIDEO PLATFORM')); ?></span>
                <h1><?= e(copy_text('home.hero_title', 'Launch a clean video experience.')); ?></h1>
                <p><?= e(copy_text('home.hero_description', 'Show free and premium videos with a simpler browsing flow, cleaner labels, and a faster path to playback.')); ?></p>
                <div class="hero__actions">
                    <a class="button" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('home.hero_primary_cta', 'Browse videos')); ?></a>
                    <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('home.hero_secondary_cta', 'See Premium')); ?></a>
                </div>
                <div class="hero-metrics">
                    <article class="hero-metric">
                        <span class="stat-card__label"><?= e(copy_text('home.stats_library_label', 'Library')); ?></span>
                        <strong><?= e((string) $stats['videos']); ?></strong>
                        <span><?= e(copy_text('home.stats_library_value_note', 'published videos')); ?></span>
                    </article>
                    <article class="hero-metric">
                        <span class="stat-card__label"><?= e(copy_text('home.stats_creators_label', 'Creators')); ?></span>
                        <strong><?= e((string) $stats['creators']); ?></strong>
                        <span><?= e(copy_text('home.stats_creators_value_note', 'active profiles')); ?></span>
                    </article>
                    <article class="hero-metric">
                        <span class="stat-card__label"><?= e(copy_text('home.stats_premium_label', 'Premium')); ?></span>
                        <strong><?= e((string) $stats['premium']); ?></strong>
                        <span><?= e(copy_text('home.stats_premium_value_note', 'paid items')); ?></span>
                    </article>
                </div>
                <?php if ($repository->usingFallback()): ?>
                    <div class="notice-card">
                        <strong><?= e(copy_text('home.fallback_notice_title', 'Catalog preview')); ?></strong>
                        <p><?= e(copy_text('home.fallback_notice_text', 'A preview selection is showing right now. The full library will appear here when your site is fully connected.')); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="hero__aside hero__aside--media">
                <?php if ($heroVideo): ?>
                    <a class="hero-feature" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $heroVideo['slug']))); ?>">
                        <img src="<?= e((string) ($heroVideo['resolved_listing_poster_url'] ?? $heroVideo['resolved_poster_url'])); ?>" alt="<?= e($heroVideo['title']); ?>">
                        <div class="hero-feature__overlay">
                            <div class="meta-row">
                                <span class="pill"><?= e($heroVideo['category']); ?></span>
                                <span class="pill pill--muted"><?= e($heroVideo['access_label']); ?></span>
                            </div>
                            <h2><?= e($heroVideo['title']); ?></h2>
                            <div class="hero-feature__meta">
                                <span><?= e($heroVideo['creator_name']); ?></span>
                                <span><?= e($heroVideo['duration_label']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($heroQueue): ?>
                    <div class="hero-queue">
                        <?php foreach ($heroQueue as $item): ?>
                            <a class="hero-queue__item" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                <img src="<?= e((string) ($item['resolved_listing_poster_url'] ?? $item['resolved_poster_url'])); ?>" alt="<?= e($item['title']); ?>">
                                <div class="hero-queue__body">
                                    <span class="pill"><?= e($item['category']); ?></span>
                                    <strong><?= e($item['title']); ?></strong>
                                    <span><?= e($item['creator_name']); ?> / <?= e($item['duration_label']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <section class="featured-strip">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= e(copy_text('home.featured_eyebrow', 'FEATURED')); ?></span>
                    <h2><?= e(copy_text('home.featured_title', 'Start with the featured picks')); ?></h2>
                </div>
                <p><?= e(copy_text('home.featured_description', 'Use the home page for quick discovery, then jump into the full browse view when you want filters.')); ?></p>
            </div>
            <div class="featured-grid">
                <?php foreach (array_slice($featured, 0, 3) as $video): ?>
                    <article class="feature-card">
                        <a class="feature-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                            <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>">
                            <div class="feature-card__overlay">
                                <div class="meta-row">
                                    <span class="pill"><?= e($video['category']); ?></span>
                                    <span class="pill pill--muted"><?= e($video['access_label']); ?></span>
                                </div>
                                <span class="video-card__duration"><?= e($video['duration_label']); ?></span>
                            </div>
                        </a>
                        <div class="feature-card__body">
                            <h3><?= e($video['title']); ?></h3>
                            <p><?= e($video['synopsis']); ?></p>
                            <div class="video-card__footer">
                                <span><?= e($video['creator_name']); ?></span>
                                    <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>"><?= e(copy_text('common.watch_now', 'Watch now')); ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= e(copy_text('home.quick_eyebrow', 'QUICK BROWSE')); ?></span>
                    <h2><?= e(copy_text('home.quick_title', 'Preview the latest uploads')); ?></h2>
                </div>
                <p><?= e(copy_text('home.quick_description', 'Keep browsing on the dedicated library page for search, filters, and sorting.')); ?></p>
            </div>
            <div class="grid-fallback">
                <?php foreach ($previewVideos as $video): ?>
                    <article class="video-card">
                        <a class="video-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                            <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>">
                            <div class="video-card__overlay">
                                <div class="meta-row">
                                    <span class="pill"><?= e($video['category']); ?></span>
                                    <span class="pill pill--muted"><?= e($video['access_label']); ?></span>
                                </div>
                                <span class="video-card__duration"><?= e($video['duration_label']); ?></span>
                            </div>
                        </a>
                        <div class="video-card__body">
                            <h3><?= e($video['title']); ?></h3>
                            <p><?= e($video['synopsis']); ?></p>
                            <div class="video-card__footer">
                                <span><?= e($video['creator_name']); ?></span>
                                <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>"><?= e(copy_text('common.watch_now', 'Watch now')); ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="cta-band">
                <div class="cta-band__copy">
                    <span class="eyebrow"><?= e(copy_text('home.next_eyebrow', 'NEXT STEP')); ?></span>
                    <h2><?= e(copy_text('home.next_title', 'Open the full library')); ?></h2>
                    <p><?= e(copy_text('home.next_description', 'Search by title, creator, category, or access level on the dedicated browse page.')); ?></p>
                </div>
                <div class="hero__actions">
                    <a class="button" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('home.next_primary_cta', 'Open browse page')); ?></a>
                    <a class="button button--ghost" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('home.next_secondary_cta', 'Need help?')); ?></a>
                </div>
            </div>
        </section>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= e(copy_text('home.membership_eyebrow', 'MEMBERSHIP')); ?></span>
                    <h2><?= e(copy_text('home.membership_title', 'Choose how you want to watch')); ?></h2>
                </div>
                <p><?= e(copy_text('home.membership_description', 'Free videos stay open to everyone. Premium videos stay reserved for paying members.')); ?></p>
            </div>
            <div class="pricing-grid">
                <article class="pricing-card">
                    <span class="pill pill--muted"><?= e(copy_text('home.free_badge', 'Free')); ?></span>
                    <h3><?= e(copy_text('home.free_title', 'Open access videos')); ?></h3>
                    <p><?= e(copy_text('home.free_text', 'Watch any video marked Free without payment. Create an account only when you want saved access, billing, or added security.')); ?></p>
                    <div class="hero__actions">
                        <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('home.free_cta', 'Browse free videos')); ?></a>
                    </div>
                </article>
                <article class="pricing-card pricing-card--accent">
                    <span class="pill"><?= e(copy_text('home.premium_badge', 'Premium')); ?></span>
                    <h3><?= e(copy_text('home.premium_title', 'One plan for every Premium title')); ?></h3>
                    <p><?= e(copy_text('home.premium_text', 'Use one membership to unlock all Premium videos, then manage billing from your account whenever you need.')); ?></p>
                    <div class="hero__actions">
                        <a class="button" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('home.premium_primary_cta', 'View plans')); ?></a>
                        <a class="button button--ghost" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('home.premium_secondary_cta', 'Payment help')); ?></a>
                    </div>
                </article>
            </div>
        </section>

    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap($bootPayload); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
