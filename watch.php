<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

$slug = (string) ($_GET['slug'] ?? '');
$repository = new VideoRepository();
$mediaAccess = new MediaAccessService();
$video = $repository->findBySlug($slug);
$video = $video ? $mediaAccess->decorateVideo($video) : null;
$videoRequiresPremium = $video ? video_requires_premium($video) : false;
$canWatchVideo = $video ? can_watch_video($video, current_user()) : false;

if (!$video) {
    http_response_code(404);
}

$related = $video ? $mediaAccess->decorateVideos($repository->related((string) $video['slug'], (string) $video['category'])) : [];
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $video ? e($video['title']) . ' | ' . e(config('app.name')) : 'Video not found'; ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <?php
    $publicNavActive = 'browse';
    $publicBarItems = ['Adults only 18+', 'Age restricted', 'Adult content'];
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell page-shell--watch">
        <?php if (!$video): ?>
            <section class="auth-card">
                <span class="eyebrow">404</span>
                <h1>Video not found</h1>
                <p>This video is unavailable or has been removed.</p>
                <a class="button" href="<?= e(base_url()); ?>">Back to browse</a>
            </section>
        <?php else: ?>
            <section class="watch-layout">
                <div class="watch-stage">
                    <div class="watch-stage__header">
                        <div class="meta-row">
                            <span class="pill"><?= e($video['category']); ?></span>
                            <span class="pill pill--muted"><?= e($video['access_label']); ?></span>
                        </div>
                        <div class="hero__actions">
                            <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>">Back to browse</a>
                            <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                                <a class="button" href="<?= e(base_url('premium.php')); ?>">See Premium</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                        <div class="watch-placeholder">
                            <img src="<?= e((string) $video['resolved_poster_url']); ?>" alt="<?= e($video['title']); ?>">
                            <div class="watch-placeholder__overlay">
                                <span class="pill">Premium only</span>
                                <p>Sign in with a Premium account to watch this video.</p>
                                <div class="hero__actions">
                                    <?php if ($user): ?>
                                        <a class="button" href="<?= e(base_url('premium.php')); ?>">Upgrade to Premium</a>
                                    <?php else: ?>
                                        <a class="button" href="<?= e(base_url('login.php')); ?>">Sign in</a>
                                    <?php endif; ?>
                                    <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>">View plans</a>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($video['source_type'] === 'embed' && $video['embed_url']): ?>
                        <div class="watch-embed-shell">
                            <iframe
                                class="watch-embed"
                                src="<?= e((string) $video['embed_url']); ?>"
                                title="<?= e($video['title']); ?>"
                                loading="lazy"
                                allow="autoplay; fullscreen; picture-in-picture"
                                allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                            ></iframe>
                        </div>
                    <?php elseif ($video['video_url'] || $video['trailer_url']): ?>
                        <video class="watch-player" controls preload="metadata" poster="<?= e((string) $video['resolved_poster_url']); ?>">
                            <source src="<?= e((string) ($video['resolved_video_url'] ?: $video['trailer_url'])); ?>" type="<?= e((string) ($video['mime_type'] ?: 'video/mp4')); ?>">
                        </video>
                    <?php else: ?>
                        <div class="watch-placeholder">
                            <img src="<?= e((string) $video['resolved_poster_url']); ?>" alt="<?= e($video['title']); ?>">
                            <div class="watch-placeholder__overlay">
                                <span class="pill">Preview unavailable</span>
                                <p>Playback is not available for this video yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="watch-stage__details">
                        <h1><?= e($video['title']); ?></h1>
                        <p><?= e($video['synopsis']); ?></p>
                        <div class="watch-stage__meta">
                            <span><strong>Creator</strong> <?= e($video['creator_name']); ?></span>
                            <span><strong>Length</strong> <?= e($video['duration_label']); ?></span>
                            <span><strong>Published</strong> <?= e($video['published_label']); ?></span>
                        </div>
                    </div>
                </div>
                <aside class="watch-sidebar">
                    <div class="watch-facts">
                        <div>
                            <span>Creator</span>
                            <strong><?= e($video['creator_name']); ?></strong>
                        </div>
                        <div>
                            <span>Length</span>
                            <strong><?= e($video['duration_label']); ?></strong>
                        </div>
                        <div>
                            <span>Published</span>
                            <strong><?= e($video['published_label']); ?></strong>
                        </div>
                        <div>
                            <span>Access</span>
                            <strong><?= e($video['access_label']); ?></strong>
                        </div>
                    </div>
                    <div class="notice-card">
                        <strong>18+ notice</strong>
                        <p>This page contains adult material and is only for viewers 18 or older.</p>
                    </div>
                    <?php if ($videoRequiresPremium): ?>
                        <div class="notice-card">
                            <strong>Plan required</strong>
                            <p><?= $canWatchVideo ? 'This account currently has Premium access.' : 'This title is Premium and requires an active Premium account.'; ?></p>
                            <?php if (!$canWatchVideo): ?>
                                <a class="text-link" href="<?= e(base_url('premium.php')); ?>">Open Premium plans</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="notice-card">
                        <strong>Need help?</strong>
                        <p>Use the support page for account, billing, or legal contact details.</p>
                        <a class="text-link" href="<?= e(base_url('support.php')); ?>">Open support</a>
                    </div>
                    <?php if ($video['original_source_url'] && is_admin()): ?>
                        <div class="notice-card">
                            <strong>Original URL</strong>
                            <a class="text-link" href="<?= e((string) $video['original_source_url']); ?>" target="_blank" rel="noreferrer"><?= e((string) $video['original_source_url']); ?></a>
                        </div>
                    <?php endif; ?>
                </aside>
            </section>

            <?php if ($related): ?>
                <section class="related-section">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">RELATED</span>
                            <h2>More in this category</h2>
                        </div>
                    </div>
                    <div class="grid-fallback">
                        <?php foreach ($related as $item): ?>
                            <article class="video-card">
                                <a class="video-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                    <img src="<?= e((string) ($item['resolved_listing_poster_url'] ?? $item['resolved_poster_url'])); ?>" alt="<?= e($item['title']); ?>">
                                    <div class="video-card__overlay">
                                        <div class="meta-row">
                                            <span class="pill"><?= e($item['category']); ?></span>
                                            <span class="pill pill--muted"><?= e($item['access_label']); ?></span>
                                        </div>
                                        <span class="video-card__duration"><?= e($item['duration_label']); ?></span>
                                    </div>
                                </a>
                                <div class="video-card__body">
                                    <h3><?= e($item['title']); ?></h3>
                                    <p><?= e($item['synopsis']); ?></p>
                                    <div class="video-card__footer">
                                        <span><?= e($item['creator_name']); ?></span>
                                        <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>">Watch now</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('watch')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
