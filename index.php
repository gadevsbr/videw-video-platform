<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

$repository = new VideoRepository();
$mediaAccess = new MediaAccessService();
$videos = $mediaAccess->decorateVideos($repository->listPublished());
$featured = array_values(array_filter($videos, static fn (array $video): bool => (int) $video['is_featured'] === 1));
$featured = $featured ?: array_slice($videos, 0, 3);
$heroVideo = $featured[0] ?? $videos[0] ?? null;
$heroQueue = array_values(array_filter(
    array_slice($videos, 0, 5),
    static fn (array $video): bool => $heroVideo === null || (string) $video['slug'] !== (string) $heroVideo['slug']
));
$heroQueue = array_slice($heroQueue, 0, 3);
$stats = $repository->stats();
$bootPayload = default_bootstrap_payload('home', [
    'usingFallback' => $repository->usingFallback(),
    'videos' => $videos,
    'stats' => $stats,
    'categories' => $repository->categories(),
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
    <title><?= e(config('app.name')); ?> | Adult Video Platform</title>
    <meta name="description" content="<?= e((string) config('app.description')); ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <div class="legal-bar">
        <span>Adults only 18+</span>
        <span>Verified creators</span>
        <span>ID and consent required</span>
    </div>

    <header class="site-header">
        <a class="brandmark" href="<?= e(base_url()); ?>">
            <span class="brandmark__kicker"><?= e(brand_kicker()); ?></span>
            <span class="brandmark__title"><?= e(brand_title()); ?></span>
        </a>
        <nav class="site-nav">
            <a href="#catalog">Browse</a>
            <a href="<?= e(base_url('premium.php')); ?>">Premium</a>
            <a href="<?= e(base_url('rules.php')); ?>"><?= e(rules_nav_label()); ?></a>
            <a href="<?= e(base_url('account.php')); ?>">Account</a>
            <?php if (is_admin()): ?>
                <a href="<?= e(base_url('admin.php')); ?>">Admin</a>
            <?php endif; ?>
        </nav>
        <div class="site-nav__actions">
            <?php if ($user): ?>
                <span class="pill pill--muted"><?= e($user['display_name']); ?></span>
                <?php if (is_admin()): ?>
                    <a class="button button--ghost" href="<?= e(base_url('admin.php')); ?>">Admin</a>
                <?php endif; ?>
                <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">Dashboard</a>
                <a class="button button--ghost" href="<?= e(base_url('logout.php')); ?>">Log out</a>
            <?php else: ?>
                <a class="button button--ghost" href="<?= e(base_url('login.php')); ?>">Sign in</a>
                <a class="button" href="<?= e(base_url('register.php')); ?>">Join</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flashError): ?>
        <div class="flash flash--error"><?= e((string) $flashError); ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
    <?php endif; ?>

    <main class="page-shell">
        <section class="hero hero--landing">
            <div class="hero__copy">
                <span class="eyebrow">ADULT VIDEO HUB</span>
                <h1>Watch verified adult videos.</h1>
                <p>Fast browsing, clean layout, local uploads, Wasabi storage, and external embeds.</p>
                <div class="hero__actions">
                    <a class="button" href="#catalog">Browse videos</a>
                    <a class="button button--ghost" href="<?= e(base_url('rules.php')); ?>">Read rules</a>
                </div>
                <div class="hero-metrics">
                    <article class="hero-metric">
                        <span class="stat-card__label">Library</span>
                        <strong><?= e((string) $stats['videos']); ?></strong>
                        <span>published videos</span>
                    </article>
                    <article class="hero-metric">
                        <span class="stat-card__label">Creators</span>
                        <strong><?= e((string) $stats['creators']); ?></strong>
                        <span>active profiles</span>
                    </article>
                    <article class="hero-metric">
                        <span class="stat-card__label">Premium</span>
                        <strong><?= e((string) $stats['premium']); ?></strong>
                        <span>paid items</span>
                    </article>
                </div>
                <?php if ($repository->usingFallback()): ?>
                    <div class="notice-card">
                        <strong>Demo mode</strong>
                        <p>The database is offline. The front end is using local fallback data.</p>
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
                    <span class="eyebrow">FEATURED</span>
                    <h2>Top picks right now</h2>
                </div>
                <p>Simple layout, clear labels, and fast browsing.</p>
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
                                <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">Open video</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="catalog" class="catalog-section">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">BROWSE</span>
                    <h2>Search and filter</h2>
                </div>
                <p>Filter by title, creator, category, or access.</p>
            </div>
            <div id="catalog-app">
                <div class="grid-fallback">
                    <?php foreach (array_slice($videos, 0, 6) as $video): ?>
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
                                    <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">View details</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
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
