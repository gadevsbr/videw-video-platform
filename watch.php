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
    <title><?= $video ? e($video['title']) . ' | ' . e(config('app.name')) : e(copy_text('watch.meta_missing_title', 'Video not found')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class()); ?>">
    <?php
    $publicNavActive = 'browse';
    $publicBarItems = copy_items('header.bar.legal');
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell page-shell--watch">
        <?php if (!$video): ?>
            <section class="auth-card">
                <span class="eyebrow"><?= e(copy_text('watch.missing_eyebrow', '404')); ?></span>
                <h1><?= e(copy_text('watch.missing_title', 'Video not found')); ?></h1>
                <p><?= e(copy_text('watch.missing_text', 'This video is unavailable or has been removed.')); ?></p>
                <a class="button" href="<?= e(base_url()); ?>"><?= e(copy_text('watch.missing_cta', 'Back to browse')); ?></a>
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
                            <a class="button button--ghost" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('watch.stage_back_cta', 'Back to browse')); ?></a>
                            <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                                <a class="button" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.stage_premium_cta', 'See Premium')); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                        <div class="watch-placeholder">
                            <img src="<?= e((string) $video['resolved_poster_url']); ?>" alt="<?= e($video['title']); ?>">
                            <div class="watch-placeholder__overlay">
                                <span class="pill"><?= e(copy_text('watch.premium_badge', 'Premium only')); ?></span>
                                <p><?= e(copy_text('watch.premium_text', 'Sign in with a Premium account to watch this video.')); ?></p>
                                <div class="hero__actions">
                                    <?php if ($user): ?>
                                        <a class="button" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.premium_user_cta', 'Upgrade to Premium')); ?></a>
                                    <?php else: ?>
                                        <a class="button" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('watch.premium_guest_cta', 'Sign in')); ?></a>
                                    <?php endif; ?>
                                    <a class="button button--ghost" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.premium_secondary_cta', 'View plans')); ?></a>
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
                                <span class="pill"><?= e(copy_text('watch.preview_badge', 'Preview unavailable')); ?></span>
                                <p><?= e(copy_text('watch.preview_text', 'Playback is not available for this video yet.')); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="watch-stage__details">
                        <h1><?= e($video['title']); ?></h1>
                        <p><?= e($video['synopsis']); ?></p>
                        <div class="watch-stage__meta">
                            <span><strong><?= e(copy_text('watch.meta_creator', 'Creator')); ?></strong> <?= e($video['creator_name']); ?></span>
                            <span><strong><?= e(copy_text('watch.meta_length', 'Length')); ?></strong> <?= e($video['duration_label']); ?></span>
                            <span><strong><?= e(copy_text('watch.meta_published', 'Published')); ?></strong> <?= e($video['published_label']); ?></span>
                        </div>
                    </div>
                </div>
                <aside class="watch-sidebar">
                    <div class="watch-facts">
                        <div>
                            <span><?= e(copy_text('watch.meta_creator', 'Creator')); ?></span>
                            <strong><?= e($video['creator_name']); ?></strong>
                        </div>
                        <div>
                            <span><?= e(copy_text('watch.meta_length', 'Length')); ?></span>
                            <strong><?= e($video['duration_label']); ?></strong>
                        </div>
                        <div>
                            <span><?= e(copy_text('watch.meta_published', 'Published')); ?></span>
                            <strong><?= e($video['published_label']); ?></strong>
                        </div>
                        <div>
                            <span><?= e(copy_text('watch.facts_access', 'Access')); ?></span>
                            <strong><?= e($video['access_label']); ?></strong>
                        </div>
                    </div>
                    <?php if (age_gate_enabled()): ?>
                        <div class="notice-card">
                            <strong><?= e(copy_text('watch.notice_title', '18+ notice')); ?></strong>
                            <p><?= e(copy_text('watch.notice_text', 'This page contains adult material and is only for viewers 18 or older.')); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($videoRequiresPremium): ?>
                        <div class="notice-card">
                            <strong><?= e(copy_text('watch.plan_title', 'Plan required')); ?></strong>
                            <p><?= $canWatchVideo ? e(copy_text('watch.plan_text_allowed', 'This account currently has Premium access.')) : e(copy_text('watch.plan_text_blocked', 'This title is Premium and requires an active Premium account.')); ?></p>
                            <?php if (!$canWatchVideo): ?>
                                <a class="text-link" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.plan_link', 'Open Premium plans')); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="notice-card">
                        <strong><?= e(copy_text('watch.help_title', 'Need help?')); ?></strong>
                        <p><?= e(copy_text('watch.help_text', 'Use the support page for account, billing, or legal contact details.')); ?></p>
                        <a class="text-link" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('watch.help_link', 'Open support')); ?></a>
                    </div>
                    <?php if ($video['original_source_url'] && is_admin()): ?>
                        <div class="notice-card">
                            <strong><?= e(copy_text('watch.original_url_title', 'Original URL')); ?></strong>
                            <a class="text-link" href="<?= e((string) $video['original_source_url']); ?>" target="_blank" rel="noreferrer"><?= e((string) $video['original_source_url']); ?></a>
                        </div>
                    <?php endif; ?>
                </aside>
            </section>

            <?php if ($related): ?>
                <section class="related-section">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow"><?= e(copy_text('watch.related_eyebrow', 'RELATED')); ?></span>
                            <h2><?= e(copy_text('watch.related_title', 'More in this category')); ?></h2>
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
                                        <a class="text-link" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>"><?= e(copy_text('common.watch_now', 'Watch now')); ?></a>
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
