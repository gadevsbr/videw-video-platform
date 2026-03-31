<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\UserRepository;
use App\Repositories\VideoAnalyticsRepository;
use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

$slug = trim((string) ($_GET['creator'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

$users = new UserRepository();
$videos = new VideoRepository();
$analytics = new VideoAnalyticsRepository();
$mediaAccess = new MediaAccessService();

$creator = $slug !== '' ? $users->findByCreatorSlug($slug) : null;

if (!$creator || !in_array((string) ($creator['role'] ?? ''), ['creator', 'admin'], true)) {
    http_response_code(404);
}

$videoPagination = $creator
    ? $videos->paginatePublishedByCreator((int) $creator['id'], $page, 12)
    : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'total_pages' => 1];
$channelVideos = $creator ? $mediaAccess->decorateVideos($videoPagination['items']) : [];
$channelAnalytics = $creator ? $analytics->overview((int) $creator['id']) : ['views_total' => 0, 'views_30d' => 0];
$channelName = $creator ? creator_public_name($creator) : 'Channel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $creator ? e($channelName . ' | ' . config('app.name')) : 'Channel not found'; ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = '';
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell channel-page">
        <?php if (!$creator): ?>
            <section class="auth-card">
                <span class="eyebrow">404</span>
                <h1>Channel not found.</h1>
                <p>This creator profile is unavailable right now.</p>
                <a class="button" href="<?= e(base_url('browse.php')); ?>">Back to browse</a>
            </section>
        <?php else: ?>
            <section class="channel-hero">
                <div class="channel-hero__banner">
                    <img src="<?= e((string) ($creator['resolved_creator_banner_url'] ?? creator_banner_fallback($channelName))); ?>" alt="<?= e($channelName); ?> banner">
                </div>
                <div class="channel-hero__body">
                    <div class="channel-hero__avatar">
                        <img src="<?= e((string) ($creator['resolved_creator_avatar_url'] ?? creator_avatar_fallback($channelName))); ?>" alt="<?= e($channelName); ?> avatar">
                    </div>
                    <div class="channel-hero__copy">
                        <span class="eyebrow">CHANNEL</span>
                        <h1><?= e($channelName); ?></h1>
                        <p><?= e((string) ($creator['creator_bio'] ?? 'This creator has not added a channel bio yet.')); ?></p>
                    </div>
                    <div class="channel-hero__meta">
                        <article class="mini-stat">
                            <span>Videos</span>
                            <strong><?= e((string) ($videoPagination['total'] ?? 0)); ?></strong>
                        </article>
                        <article class="mini-stat">
                            <span>Total views</span>
                            <strong><?= e((string) ($channelAnalytics['views_total'] ?? 0)); ?></strong>
                        </article>
                        <article class="mini-stat">
                            <span>Views in 30 days</span>
                            <strong><?= e((string) ($channelAnalytics['views_30d'] ?? 0)); ?></strong>
                        </article>
                    </div>
                </div>
            </section>

            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">UPLOADS</span>
                        <h2>Latest videos</h2>
                    </div>
                    <p>Public videos from this creator channel.</p>
                </div>

                <?php if ($channelVideos): ?>
                    <div class="front-grid front-grid--channel">
                        <?php foreach ($channelVideos as $video): ?>
                            <article class="front-card front-card--channel">
                                <a class="front-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                                    <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                    <span class="front-duration"><?= e($video['duration_label']); ?></span>
                                </a>
                                <div class="front-card__body">
                                    <h3><?= e($video['title']); ?></h3>
                                    <p><?= e($video['synopsis']); ?></p>
                                    <div class="front-meta-row">
                                        <span><?= e($video['access_label']); ?></span>
                                        <span><?= e($video['published_label']); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <article class="empty-state">
                        <span class="eyebrow">CHANNEL</span>
                        <h3>No public videos yet.</h3>
                        <p>New uploads will appear here after they are published.</p>
                    </article>
                <?php endif; ?>

                <?php if (($videoPagination['total_pages'] ?? 1) > 1): ?>
                    <div class="pagination-row">
                        <?php for ($index = 1; $index <= (int) $videoPagination['total_pages']; $index++): ?>
                            <a class="<?= $index === (int) $videoPagination['page'] ? 'button' : 'button button--ghost'; ?>" href="<?= e(base_url('channel.php?creator=' . urlencode($slug) . '&page=' . $index)); ?>"><?= e((string) $index); ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('channel')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
