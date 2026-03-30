<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

$repository = new VideoRepository();
$mediaAccess = new MediaAccessService();
$videos = $mediaAccess->decorateVideos($repository->listPublished());
$publicVideos = array_map(static fn (array $video): array => public_catalog_video_payload($video), $videos);
$stats = $repository->stats();
$featured = array_values(array_filter($videos, static fn (array $video): bool => (int) ($video['is_featured'] ?? 0) === 1));
$featured = $featured ?: array_slice($videos, 0, 3);
$user = current_user();
$bootPayload = default_bootstrap_payload('browse', [
    'usingFallback' => $repository->usingFallback(),
    'videos' => $publicVideos,
    'stats' => $stats,
    'categories' => $repository->categories(),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse | <?= e(config('app.name')); ?></title>
    <meta name="description" content="Browse the full <?= e(config('app.name')); ?> library with filters for title, creator, category, and access level.">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <?php
    $publicNavActive = 'browse';
    $publicBarItems = ['Adults only 18+', 'Search and filters', 'Free and Premium catalog'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell">
        <section class="page-intro">
            <div class="page-intro__copy">
                <span class="eyebrow">BROWSE</span>
                <h1>Find videos faster.</h1>
                <p>Search by title or creator, filter by category, and switch between Free and Premium in one place.</p>
                <div class="hero__actions">
                    <a class="button" href="#catalog-app">Start browsing</a>
                    <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>">See Premium</a>
                </div>
            </div>
            <aside class="page-intro__aside">
                <article class="mini-stat">
                    <span>Total videos</span>
                    <strong><?= e((string) ($stats['videos'] ?? 0)); ?></strong>
                </article>
                <article class="mini-stat">
                    <span>Premium videos</span>
                    <strong><?= e((string) ($stats['premium'] ?? 0)); ?></strong>
                </article>
                <article class="mini-stat">
                    <span>Creators</span>
                    <strong><?= e((string) ($stats['creators'] ?? 0)); ?></strong>
                </article>
            </aside>
        </section>

        <?php if ($featured !== []): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">FEATURED NOW</span>
                        <h2>Quick starts</h2>
                    </div>
                    <p>Start with one of the current highlights, then keep filtering below.</p>
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
                                    <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">Watch now</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">FULL LIBRARY</span>
                    <h2>Search, filter, and sort</h2>
                </div>
                <p>Keep the homepage lighter and use this page when you want the full browsing workflow.</p>
            </div>
            <div id="catalog-app">
                <div class="grid-fallback">
                    <?php foreach (array_slice($videos, 0, 8) as $video): ?>
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
                                    <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">Watch now</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-band__copy">
                <span class="eyebrow">HELP</span>
                <h2>Need account or billing help?</h2>
                <p>Open support for account access, Premium questions, legal contact, and platform rules.</p>
            </div>
            <div class="hero__actions">
                <a class="button" href="<?= e(base_url('support.php')); ?>">Open support</a>
                <?php if ($user): ?>
                    <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">Go to my account</a>
                <?php else: ?>
                    <a class="button button--ghost" href="<?= e(base_url('register.php')); ?>">Create account</a>
                <?php endif; ?>
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
