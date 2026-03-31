<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoAnalyticsRepository;
use App\Services\MediaAccessService;

$slug = (string) ($_GET['slug'] ?? '');
$repository = new VideoRepository();
$users = new UserRepository();
$videoAnalytics = new VideoAnalyticsRepository();
$mediaAccess = new MediaAccessService();
$video = $repository->findBySlug($slug);
$video = $video ? $mediaAccess->decorateVideo($video) : null;
$videoRequiresPremium = $video ? video_requires_premium($video) : false;
$canWatchVideo = $video ? can_watch_video($video, current_user()) : false;
$creatorProfile = $video && !empty($video['creator_user_id']) ? $users->findById((int) $video['creator_user_id']) : null;

if ($video && $canWatchVideo && !empty($video['creator_user_id'])) {
    $videoAnalytics->recordView(
        (int) $video['id'],
        (int) $video['creator_user_id'],
        current_user() ? (int) (current_user()['id'] ?? 0) : null,
        viewer_session_key(current_user())
    );
}

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
<body class="<?= e(page_lock_class('public-layout')); ?>">
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
            <section class="watch-page">
                <div class="watch-page__main">
                    <div class="watch-player-shell">
                        <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                            <div class="watch-placeholder">
                                <img src="<?= e((string) $video['resolved_poster_url']); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                <div class="watch-placeholder__overlay">
                                    <span class="front-badge"><?= e(copy_text('watch.premium_badge', 'Premium only')); ?></span>
                                    <h2><?= e($video['title']); ?></h2>
                                    <p><?= e(copy_text('watch.premium_text', 'Sign in with a Premium account to watch this video.')); ?></p>
                                    <div class="front-actions">
                                        <?php if ($user): ?>
                                            <a class="front-primary-action" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.premium_user_cta', 'Upgrade to Premium')); ?></a>
                                        <?php else: ?>
                                            <a class="front-primary-action" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('watch.premium_guest_cta', 'Sign in')); ?></a>
                                        <?php endif; ?>
                                        <a class="front-secondary-action" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.premium_secondary_cta', 'View plans')); ?></a>
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
                                <img src="<?= e((string) $video['resolved_poster_url']); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                <div class="watch-placeholder__overlay">
                                    <span class="front-badge"><?= e(copy_text('watch.preview_badge', 'Preview unavailable')); ?></span>
                                    <p><?= e(copy_text('watch.preview_text', 'Playback is not available for this video yet.')); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="watch-title-block">
                        <h1><?= e($video['title']); ?></h1>
                        <div class="front-meta-row">
                            <?php if ($creatorProfile && !empty($creatorProfile['creator_slug'])): ?>
                                <a class="front-link" href="<?= e(base_url('channel.php?creator=' . urlencode((string) $creatorProfile['creator_slug']))); ?>"><?= e($video['creator_name']); ?></a>
                            <?php else: ?>
                                <span><?= e($video['creator_name']); ?></span>
                            <?php endif; ?>
                            <span><?= e($video['published_label']); ?></span>
                            <span><?= e($video['duration_label']); ?></span>
                            <span><?= e($video['access_label']); ?></span>
                        </div>
                        <div class="front-actions">
                            <a class="front-secondary-action" href="<?= e(base_url('browse.php')); ?>"><?= e(copy_text('watch.stage_back_cta', 'Back to browse')); ?></a>
                            <?php if ($videoRequiresPremium && !$canWatchVideo): ?>
                                <a class="front-primary-action" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.stage_premium_cta', 'See Premium')); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <article class="watch-description-card">
                        <div class="watch-channel-line">
                            <?php if ($creatorProfile && !empty($creatorProfile['resolved_creator_avatar_url'])): ?>
                                <img class="watch-channel-line__avatar" src="<?= e((string) $creatorProfile['resolved_creator_avatar_url']); ?>" alt="<?= e($video['creator_name']); ?>">
                            <?php else: ?>
                                <div class="front-avatar"><?= e(mb_strtoupper(mb_substr((string) $video['creator_name'], 0, 1))); ?></div>
                            <?php endif; ?>
                            <div>
                                <?php if ($creatorProfile && !empty($creatorProfile['creator_slug'])): ?>
                                    <a class="front-link" href="<?= e(base_url('channel.php?creator=' . urlencode((string) $creatorProfile['creator_slug']))); ?>"><?= e($video['creator_name']); ?></a>
                                <?php else: ?>
                                    <strong><?= e($video['creator_name']); ?></strong>
                                <?php endif; ?>
                                <span><?= e($video['category']); ?></span>
                            </div>
                        </div>
                        <p><?= e($video['synopsis']); ?></p>
                        <?php if (age_gate_enabled()): ?>
                            <p class="watch-inline-note"><?= e(copy_text('watch.notice_text', 'This page contains age-restricted content.')); ?></p>
                        <?php endif; ?>
                    </article>
                </div>

                <aside class="watch-page__side">
                    <article class="watch-side-card">
                        <strong><?= e(copy_text('watch.help_title', 'Need help?')); ?></strong>
                        <p><?= e(copy_text('watch.help_text', 'Use the support page for account, billing, or legal contact details.')); ?></p>
                        <a class="front-link" href="<?= e(base_url('support.php')); ?>"><?= e(copy_text('watch.help_link', 'Open support')); ?></a>
                    </article>
                    <?php if ($videoRequiresPremium): ?>
                        <article class="watch-side-card">
                            <strong><?= e(copy_text('watch.plan_title', 'Access')); ?></strong>
                            <p><?= $canWatchVideo ? e(copy_text('watch.plan_text_allowed', 'This account currently has Premium access.')) : e(copy_text('watch.plan_text_blocked', 'This title requires an active Premium account.')); ?></p>
                            <?php if (!$canWatchVideo): ?>
                                <a class="front-link" href="<?= e(base_url('premium.php')); ?>"><?= e(copy_text('watch.plan_link', 'Open Premium plans')); ?></a>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                    <?php if ($related): ?>
                        <section class="watch-next-list">
                            <h2><?= e(copy_text('watch.related_title', 'Up next')); ?></h2>
                            <?php foreach ($related as $item): ?>
                                <a class="watch-next-item" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                    <div class="watch-next-item__thumb">
                                        <img src="<?= e((string) ($item['resolved_listing_poster_url'] ?? $item['resolved_poster_url'])); ?>" alt="<?= e($item['title']); ?>" style="object-position: <?= e(poster_object_position($item)); ?>;">
                                        <span class="front-duration"><?= e($item['duration_label']); ?></span>
                                    </div>
                                    <div class="watch-next-item__meta">
                                        <h3><?= e($item['title']); ?></h3>
                                        <p><?= e($item['creator_name']); ?></p>
                                        <span><?= e($item['access_label']); ?> • <?= e($item['published_label']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                </aside>
            </section>
        <?php endif; ?>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('watch')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
