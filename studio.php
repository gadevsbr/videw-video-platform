<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoAnalyticsRepository;
use App\Repositories\VideoRepository;
use App\Services\AdminVideoService;
use App\Services\CreatorService;
use App\Services\MediaAccessService;

ensure_creator();

$users = new UserRepository();
$videos = new VideoRepository();
$analytics = new VideoAnalyticsRepository();
$auditLogs = new AuditLogRepository();
$videoService = new AdminVideoService();
$creatorService = new CreatorService();
$mediaAccess = new MediaAccessService();

$sessionUser = current_user(true);
$userId = (int) ($sessionUser['id'] ?? 0);
$creator = $users->findById($userId) ?? $sessionUser;
$channelName = creator_public_name($creator);
$channelUrl = creator_profile_url($creator);
$validScreens = ['overview', 'publish', 'videos', 'analytics', 'profile'];
$screen = (string) ($_GET['screen'] ?? 'overview');
$screen = in_array($screen, $validScreens, true) ? $screen : 'overview';
$screenUrl = static fn (string $target, array $extra = []): string => base_url('studio.php?' . http_build_query(array_merge(['screen' => $target], $extra)));

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'studio')) {
        flash('error', 'Security token expired. Try again.');
        redirect('studio.php?screen=' . $screen);
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'publish_creator_video') {
        remember_input([
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'access_level' => trim((string) ($_POST['access_level'] ?? 'free')),
            'duration_minutes' => trim((string) ($_POST['duration_minutes'] ?? '0')),
            'synopsis' => trim((string) ($_POST['synopsis'] ?? '')),
            'source_mode' => trim((string) ($_POST['source_mode'] ?? '')),
            'external_url' => trim((string) ($_POST['external_url'] ?? '')),
            'poster_source_mode' => trim((string) ($_POST['poster_source_mode'] ?? '')),
            'poster_external_url' => trim((string) ($_POST['poster_external_url'] ?? '')),
            'poster_focus_x' => trim((string) ($_POST['poster_focus_x'] ?? '50')),
            'poster_focus_y' => trim((string) ($_POST['poster_focus_y'] ?? '50')),
        ]);

        $result = $videoService->publish($_POST, $_FILES, [
            'creator_user_id' => $userId,
            'creator_name' => $channelName,
            'allow_creator_name_input' => false,
            'allow_featured' => false,
            'allowed_moderation_statuses' => ['draft'],
            'default_moderation_status' => 'draft',
        ]);

        if ($result['success']) {
            clear_old_input();
            $auditLogs->record($userId ?: null, 'creator.video_created', 'video', (int) ($result['video_id'] ?? 0), 'Creator submitted a new video.', [
                'title' => trim((string) ($_POST['title'] ?? '')),
            ]);
            flash('success', 'Video submitted. It is now waiting for review.');
        } else {
            flash('error', $result['message']);
        }

        redirect('studio.php?screen=publish');
    }

    if ($action === 'update_creator_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $existing = $videos->findOwnedById($videoId, $userId);

        if (!$existing) {
            flash('error', 'Video not found.');
            redirect('studio.php?screen=videos');
        }

        $result = $videoService->update($videoId, $_POST, $_FILES, [
            'creator_user_id' => $userId,
            'creator_name' => $channelName,
            'allow_creator_name_input' => false,
            'allow_featured' => false,
            'allowed_moderation_statuses' => ['draft'],
            'default_moderation_status' => (string) ($existing['moderation_status'] ?? 'draft'),
        ]);

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'creator.video_updated', 'video', $videoId, 'Creator updated one of their videos.', [
                'title' => trim((string) ($_POST['title'] ?? '')),
            ]);
            flash('success', 'Video updated.');
        } else {
            flash('error', $result['message']);
        }

        redirect('studio.php?screen=videos&edit=' . $videoId);
    }

    if ($action === 'delete_creator_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $existing = $videos->findOwnedById($videoId, $userId);

        if (!$existing) {
            flash('error', 'Video not found.');
            redirect('studio.php?screen=videos');
        }

        $result = $videoService->delete($videoId);

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'creator.video_deleted', 'video', $videoId, 'Creator deleted one of their videos.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('studio.php?screen=videos');
    }

    if ($action === 'save_creator_profile') {
        $result = $creatorService->updateProfile($userId, $_POST, $_FILES);

        if ($result['success']) {
            $auditLogs->record($userId ?: null, 'creator.profile_updated', 'user', $userId, 'Creator updated channel profile.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('studio.php?screen=profile');
    }
}

$creator = $users->findById($userId) ?? $creator;
$channelName = creator_public_name($creator);
$channelUrl = creator_profile_url($creator);
$creatorStats = $videos->creatorStats($userId);
$analyticsOverview = $analytics->overview($userId);
$analyticsSeries = $analytics->dailySeries($userId, 14);
$topVideos = $analytics->topVideos($userId, 10);
$overviewVideos = $mediaAccess->decorateVideos($videos->paginateForCreator($userId, [], 1, 4)['items']);
$videoSearch = trim((string) ($_GET['video_search'] ?? ''));
$videosPage = max(1, (int) ($_GET['videos_page'] ?? 1));
$creatorVideosPagination = $videos->paginateForCreator($userId, ['search' => $videoSearch], $videosPage, 8);
$creatorVideos = $mediaAccess->decorateVideos($creatorVideosPagination['items']);
$editingVideoId = (int) ($_GET['edit'] ?? 0);
$editingVideo = $editingVideoId > 0 ? $videos->findOwnedById($editingVideoId, $userId) : null;
$flashError = flash('error');
$flashSuccess = flash('success');
$maxTrendViews = 1;

foreach ($analyticsSeries as $point) {
    $maxTrendViews = max($maxTrendViews, (int) ($point['views'] ?? 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creator Studio | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = 'studio';
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell studio-shell">
        <?php if ($flashError): ?>
            <div class="flash flash--error"><?= e((string) $flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
        <?php endif; ?>

        <section class="studio-topbar">
            <div class="studio-topbar__copy">
                <span class="eyebrow">CREATOR STUDIO</span>
                <h1><?= e($channelName); ?></h1>
                <p>Manage uploads, review performance, and shape the public look of your channel from one place.</p>
            </div>
            <div class="studio-topbar__actions">
                <?php if ($channelUrl): ?>
                    <a class="front-secondary-action" href="<?= e($channelUrl); ?>" target="_blank" rel="noreferrer">View channel</a>
                <?php endif; ?>
                <a class="front-primary-action" href="<?= e($screenUrl('publish')); ?>">Upload video</a>
            </div>
        </section>

        <div class="studio-layout">
            <div class="studio-main">
                <section class="studio-workspace-bar">
                    <div class="studio-workspace-bar__copy">
                        <span class="eyebrow">WORKSPACE</span>
                        <h2><?= e(match ($screen) {
                            'publish' => 'Publish videos',
                            'videos' => 'Manage your library',
                            'analytics' => 'Channel analytics',
                            'profile' => 'Channel profile',
                            default => 'Creator overview',
                        }); ?></h2>
                        <p><?= e(match ($screen) {
                            'publish' => 'Upload a new video and send it to review.',
                            'videos' => 'Edit, review, and organize your uploaded videos.',
                            'analytics' => 'Follow traffic, top videos, and recent performance.',
                            'profile' => 'Update your public avatar, banner, bio, and link.',
                            default => 'Track your channel, latest videos, and current status.',
                        }); ?></p>
                    </div>
                    <nav class="studio-workspace-nav" aria-label="Studio navigation">
                        <?php foreach (['overview' => 'Overview', 'publish' => 'Publish', 'videos' => 'Manage videos', 'analytics' => 'Analytics', 'profile' => 'Profile'] as $key => $label): ?>
                            <a class="<?= $screen === $key ? 'studio-workspace-nav__link is-active' : 'studio-workspace-nav__link'; ?>" href="<?= e($screenUrl($key)); ?>"><?= e($label); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </section>

                <?php if ($screen === 'overview'): ?>
                    <section class="catalog-section">
                        <div class="admin-summary-grid">
                            <article class="mini-stat">
                                <span>All videos</span>
                                <strong><?= e((string) ($creatorStats['total'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>Live</span>
                                <strong><?= e((string) ($creatorStats['published'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>In review</span>
                                <strong><?= e((string) ($creatorStats['draft'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>Total views</span>
                                <strong><?= e((string) ($analyticsOverview['views_total'] ?? 0)); ?></strong>
                            </article>
                        </div>
                    </section>

                    <section class="catalog-section">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">RECENT UPLOADS</span>
                                <h2>Latest videos</h2>
                            </div>
                            <p>Your newest items and their current review status.</p>
                        </div>
                        <div class="creator-video-stack">
                            <?php if ($overviewVideos): ?>
                                <?php foreach ($overviewVideos as $video): ?>
                                    <article class="creator-video-row">
                                        <div class="creator-video-row__thumb">
                                            <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                        </div>
                                        <div class="creator-video-row__body">
                                            <div>
                                                <h3><?= e($video['title']); ?></h3>
                                                <p><?= e($video['synopsis']); ?></p>
                                            </div>
                                            <div class="front-meta-row">
                                                <span><?= e($video['access_label']); ?></span>
                                                <span><?= e($video['moderation_label']); ?></span>
                                                <span><?= e($video['duration_label']); ?></span>
                                            </div>
                                        </div>
                                        <a class="front-secondary-action" href="<?= e($screenUrl('videos', ['edit' => $video['id']])); ?>">Edit</a>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <article class="empty-state">
                                    <span class="eyebrow">STUDIO</span>
                                    <h3>No uploads yet.</h3>
                                    <p>Use the publish screen to submit your first video.</p>
                                </article>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($screen === 'publish'): ?>
                    <section class="catalog-section">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">PUBLISH</span>
                                <h2>Submit a new video</h2>
                            </div>
                            <p>New creator uploads go into review before they appear publicly.</p>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                            <input type="hidden" name="action" value="publish_creator_video">
                            <?= csrf_input('studio'); ?>

                            <section class="admin-form-section">
                                <div class="admin-form-section__header">
                                    <h3>Basic details</h3>
                                    <p>Set the title, category, duration, and short description.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Title</span>
                                        <input type="text" name="title" value="<?= e(old('title')); ?>" required>
                                    </label>
                                    <label>
                                        <span>Category</span>
                                        <input type="text" name="category" value="<?= e(old('category')); ?>" required>
                                    </label>
                                    <label>
                                        <span>Access</span>
                                        <select name="access_level">
                                            <option value="free" <?= old('access_level', 'free') === 'free' ? 'selected' : ''; ?>>Free</option>
                                            <option value="premium" <?= old('access_level') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Length (minutes)</span>
                                        <input type="number" min="0" name="duration_minutes" value="<?= e(old('duration_minutes', '0')); ?>">
                                    </label>
                                </div>
                                <label>
                                    <span>Description</span>
                                    <textarea name="synopsis" rows="5" required><?= e(old('synopsis')); ?></textarea>
                                </label>
                            </section>

                            <section class="admin-form-section">
                                <div class="admin-form-section__header">
                                    <h3>Media</h3>
                                    <p>Choose how to upload the video and poster artwork.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Video source</span>
                                        <select name="source_mode" data-media-switch="video">
                                            <option value="" <?= old('source_mode', '') === '' ? 'selected' : ''; ?>>Choose how to add the video</option>
                                            <option value="file" <?= old('source_mode') === 'file' ? 'selected' : ''; ?>>Upload a file</option>
                                            <option value="url" <?= old('source_mode') === 'url' ? 'selected' : ''; ?>>External URL</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Poster source</span>
                                        <select name="poster_source_mode" data-media-switch="poster">
                                            <option value="" <?= old('poster_source_mode', '') === '' ? 'selected' : ''; ?>>Use fallback artwork</option>
                                            <option value="upload" <?= old('poster_source_mode') === 'upload' ? 'selected' : ''; ?>>Upload an image</option>
                                            <option value="url" <?= old('poster_source_mode') === 'url' ? 'selected' : ''; ?>>Poster URL</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="url" style="<?= old('source_mode') === 'url' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Video URL</span>
                                            <input type="url" name="external_url" value="<?= e(old('external_url')); ?>" placeholder="https://...">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="file" style="<?= old('source_mode') === 'file' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Video file</span>
                                            <input type="file" name="video_file" accept="video/*">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="upload" style="<?= old('poster_source_mode') === 'upload' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Poster image</span>
                                            <input type="file" name="poster_file" accept="image/*">
                                            <small class="form-note">Recommended poster: 1600x900 or larger in 16:9. JPG, PNG, or WebP.</small>
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="<?= old('poster_source_mode') === 'url' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Poster URL</span>
                                            <input type="url" name="poster_external_url" value="<?= e(old('poster_external_url')); ?>" placeholder="https://...">
                                            <small class="form-note">Use a direct image URL in 16:9 when possible. Recommended size: 1600x900 or larger.</small>
                                        </label>
                                    </div>
                                </div>
                                <section class="studio-poster-framing" data-poster-framing data-current-poster="<?= e(old('poster_source_mode') === 'url' ? old('poster_external_url') : ''); ?>">
                                    <div class="studio-poster-framing__header">
                                        <h4>Poster framing</h4>
                                        <p>Pick which part of the image stays visible in cards and the featured banner.</p>
                                    </div>
                                    <div class="studio-poster-framing__grid">
                                        <div class="studio-poster-framing__preview">
                                            <span class="studio-poster-framing__label">Featured preview</span>
                                            <div class="studio-poster-frame studio-poster-frame--hero">
                                                <img src="" alt="Poster framing preview" data-poster-preview-image>
                                            </div>
                                        </div>
                                        <div class="studio-poster-framing__preview">
                                            <span class="studio-poster-framing__label">Card preview</span>
                                            <div class="studio-poster-frame studio-poster-frame--card">
                                                <img src="" alt="Poster card preview" data-poster-preview-image>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="admin-fields admin-fields--two studio-poster-framing__controls">
                                        <label>
                                            <span class="studio-poster-framing__range-label">
                                                <span>Horizontal focus</span>
                                                <strong data-poster-focus-value="x"><?= e(old('poster_focus_x', '50')); ?>%</strong>
                                            </span>
                                            <input type="range" name="poster_focus_x" min="0" max="100" step="1" value="<?= e(old('poster_focus_x', '50')); ?>" data-poster-focus="x">
                                            <small class="studio-poster-framing__range-note">Move left or right to choose what stays centered in the featured banner.</small>
                                        </label>
                                        <label>
                                            <span class="studio-poster-framing__range-label">
                                                <span>Vertical focus</span>
                                                <strong data-poster-focus-value="y"><?= e(old('poster_focus_y', '50')); ?>%</strong>
                                            </span>
                                            <input type="range" name="poster_focus_y" min="0" max="100" step="1" value="<?= e(old('poster_focus_y', '50')); ?>" data-poster-focus="y">
                                            <small class="studio-poster-framing__range-note">Move up or down to keep faces and key details visible in smaller cards.</small>
                                        </label>
                                    </div>
                                </section>
                            </section>

                            <button class="button" type="submit">Submit for review</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if ($screen === 'videos'): ?>
                    <section class="catalog-section">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">VIDEOS</span>
                                <h2>Manage uploads</h2>
                            </div>
                            <p>Search and update your existing videos.</p>
                        </div>

                        <form method="get" class="admin-toolbar">
                            <input type="hidden" name="screen" value="videos">
                            <label>
                                <span>Search</span>
                                <input type="search" name="video_search" value="<?= e($videoSearch); ?>" placeholder="Title or category">
                            </label>
                            <button class="button button--ghost" type="submit">Filter</button>
                        </form>

                        <div class="creator-video-stack">
                            <?php if ($creatorVideos): ?>
                                <?php foreach ($creatorVideos as $video): ?>
                                    <article class="creator-video-row">
                                        <div class="creator-video-row__thumb">
                                            <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                        </div>
                                        <div class="creator-video-row__body">
                                            <div>
                                                <h3><?= e($video['title']); ?></h3>
                                                <p><?= e($video['synopsis']); ?></p>
                                            </div>
                                            <div class="front-meta-row">
                                                <span><?= e($video['access_label']); ?></span>
                                                <span><?= e($video['moderation_label']); ?></span>
                                                <span><?= e($video['published_label']); ?></span>
                                            </div>
                                        </div>
                                        <div class="creator-video-row__actions">
                                            <a class="front-secondary-action" href="<?= e($screenUrl('videos', ['edit' => $video['id'], 'video_search' => $videoSearch, 'videos_page' => $videosPage])); ?>">Edit</a>
                                            <form method="post" onsubmit="return confirm('Delete this video?');">
                                                <?= csrf_input('studio'); ?>
                                                <input type="hidden" name="action" value="delete_creator_video">
                                                <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                                <button class="front-secondary-action" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <article class="empty-state">
                                    <span class="eyebrow">VIDEOS</span>
                                    <h3>No matching videos.</h3>
                                    <p>Try another search or publish a new upload.</p>
                                </article>
                            <?php endif; ?>
                        </div>

                        <?php if (($creatorVideosPagination['total_pages'] ?? 1) > 1): ?>
                            <div class="pagination-row">
                                <?php for ($index = 1; $index <= (int) $creatorVideosPagination['total_pages']; $index++): ?>
                                    <a class="<?= $index === (int) $creatorVideosPagination['page'] ? 'button' : 'button button--ghost'; ?>" href="<?= e($screenUrl('videos', ['videos_page' => $index, 'video_search' => $videoSearch])); ?>"><?= e((string) $index); ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ($editingVideo): ?>
                        <section class="catalog-section">
                            <div class="section-heading">
                                <div>
                                    <span class="eyebrow">EDIT</span>
                                    <h2>Edit video</h2>
                                </div>
                                <p>Changes stay on your video record. Review status remains controlled by the moderation team.</p>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                                <input type="hidden" name="action" value="update_creator_video">
                                <input type="hidden" name="video_id" value="<?= e((string) $editingVideo['id']); ?>">
                                <?= csrf_input('studio'); ?>

                                <section class="admin-form-section">
                                    <div class="admin-fields admin-fields--two">
                                        <label>
                                            <span>Title</span>
                                            <input type="text" name="title" value="<?= e((string) $editingVideo['title']); ?>" required>
                                        </label>
                                        <label>
                                            <span>Category</span>
                                            <input type="text" name="category" value="<?= e((string) $editingVideo['category']); ?>" required>
                                        </label>
                                        <label>
                                            <span>Access</span>
                                            <select name="access_level">
                                                <option value="free" <?= (string) $editingVideo['access_level'] === 'free' ? 'selected' : ''; ?>>Free</option>
                                                <option value="premium" <?= (string) $editingVideo['access_level'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                            </select>
                                        </label>
                                        <label>
                                            <span>Length (minutes)</span>
                                            <input type="number" min="0" name="duration_minutes" value="<?= e((string) $editingVideo['duration_minutes']); ?>">
                                        </label>
                                    </div>
                                    <label>
                                        <span>Description</span>
                                        <textarea name="synopsis" rows="5" required><?= e((string) $editingVideo['synopsis']); ?></textarea>
                                    </label>
                                </section>

                                <section class="admin-form-section">
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Video source</span>
                                            <select name="source_mode" data-media-switch="video">
                                                <option value="">Keep current video</option>
                                                <option value="file">Upload a new file</option>
                                                <option value="url">Replace with external URL</option>
                                            </select>
                                        </label>
                                    <label>
                                        <span>Poster source</span>
                                        <select name="poster_source_mode" data-media-switch="poster">
                                            <option value="">Keep current poster</option>
                                            <option value="upload">Upload a new image</option>
                                            <option value="url">Replace with poster URL</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="admin-fields admin-fields--two">
                                        <div class="admin-conditional-field" data-media-group="video" data-media-mode="url" style="display:none;">
                                            <label>
                                                <span>Video URL</span>
                                                <input type="url" name="external_url" placeholder="https://...">
                                            </label>
                                        </div>
                                        <div class="admin-conditional-field" data-media-group="video" data-media-mode="file" style="display:none;">
                                            <label>
                                                <span>Video file</span>
                                                <input type="file" name="video_file" accept="video/*">
                                            </label>
                                        </div>
                                        <div class="admin-conditional-field" data-media-group="poster" data-media-mode="upload" style="display:none;">
                                            <label>
                                                <span>Poster image</span>
                                                <input type="file" name="poster_file" accept="image/*">
                                                <small class="form-note">Recommended poster: 1600x900 or larger in 16:9. JPG, PNG, or WebP.</small>
                                            </label>
                                        </div>
                                        <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="display:none;">
                                            <label>
                                                <span>Poster URL</span>
                                                <input type="url" name="poster_external_url" placeholder="https://...">
                                                <small class="form-note">Use a direct image URL in 16:9 when possible. Recommended size: 1600x900 or larger.</small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php if (!empty($editingVideo['stored_poster_url']) || !empty($editingVideo['poster_path'])): ?>
                                        <label class="checkbox-line">
                                            <input type="checkbox" name="remove_poster" value="1">
                                            <span>Remove current poster</span>
                                        </label>
                                    <?php endif; ?>
                                    <section class="studio-poster-framing" data-poster-framing data-current-poster="<?= e((string) ($editingVideo['resolved_listing_poster_url'] ?? $editingVideo['resolved_poster_url'] ?? '')); ?>">
                                        <div class="studio-poster-framing__header">
                                            <h4>Poster framing</h4>
                                            <p>Choose which part of the poster should stay visible in the featured area and video cards.</p>
                                        </div>
                                        <div class="studio-poster-framing__grid">
                                            <div class="studio-poster-framing__preview">
                                                <span class="studio-poster-framing__label">Featured preview</span>
                                                <div class="studio-poster-frame studio-poster-frame--hero">
                                                    <img src="<?= e((string) ($editingVideo['resolved_listing_poster_url'] ?? $editingVideo['resolved_poster_url'] ?? '')); ?>" alt="Poster framing preview" style="object-position: <?= e(poster_object_position($editingVideo)); ?>;" data-poster-preview-image>
                                                </div>
                                            </div>
                                            <div class="studio-poster-framing__preview">
                                                <span class="studio-poster-framing__label">Card preview</span>
                                                <div class="studio-poster-frame studio-poster-frame--card">
                                                    <img src="<?= e((string) ($editingVideo['resolved_listing_poster_url'] ?? $editingVideo['resolved_poster_url'] ?? '')); ?>" alt="Poster card preview" style="object-position: <?= e(poster_object_position($editingVideo)); ?>;" data-poster-preview-image>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="admin-fields admin-fields--two studio-poster-framing__controls">
                                            <label>
                                                <span class="studio-poster-framing__range-label">
                                                    <span>Horizontal focus</span>
                                                    <strong data-poster-focus-value="x"><?= e((string) ($_POST['poster_focus_x'] ?? ($editingVideo['poster_focus_x'] ?? 50))); ?>%</strong>
                                                </span>
                                                <input type="range" name="poster_focus_x" min="0" max="100" step="1" value="<?= e((string) ($_POST['poster_focus_x'] ?? ($editingVideo['poster_focus_x'] ?? 50))); ?>" data-poster-focus="x">
                                                <small class="studio-poster-framing__range-note">Move left or right to choose what stays centered in the featured banner.</small>
                                            </label>
                                            <label>
                                                <span class="studio-poster-framing__range-label">
                                                    <span>Vertical focus</span>
                                                    <strong data-poster-focus-value="y"><?= e((string) ($_POST['poster_focus_y'] ?? ($editingVideo['poster_focus_y'] ?? 50))); ?>%</strong>
                                                </span>
                                                <input type="range" name="poster_focus_y" min="0" max="100" step="1" value="<?= e((string) ($_POST['poster_focus_y'] ?? ($editingVideo['poster_focus_y'] ?? 50))); ?>" data-poster-focus="y">
                                                <small class="studio-poster-framing__range-note">Move up or down to keep faces and key details visible in smaller cards.</small>
                                            </label>
                                        </div>
                                    </section>
                                </section>

                                <button class="button" type="submit">Save video</button>
                            </form>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($screen === 'analytics'): ?>
                    <section class="catalog-section">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">ANALYTICS</span>
                                <h2>Channel performance</h2>
                            </div>
                            <p>Track recent views and see which uploads are pulling the most attention.</p>
                        </div>

                        <div class="admin-summary-grid">
                            <article class="mini-stat">
                                <span>Total views</span>
                                <strong><?= e((string) ($analyticsOverview['views_total'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>Views in 7 days</span>
                                <strong><?= e((string) ($analyticsOverview['views_7d'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>Views in 30 days</span>
                                <strong><?= e((string) ($analyticsOverview['views_30d'] ?? 0)); ?></strong>
                            </article>
                            <article class="mini-stat">
                                <span>Unique viewers in 30 days</span>
                                <strong><?= e((string) ($analyticsOverview['unique_viewers_30d'] ?? 0)); ?></strong>
                            </article>
                        </div>

                        <div class="studio-analytics-grid">
                            <article class="compliance-card">
                                <h3>Last 14 days</h3>
                                <div class="creator-trend">
                                    <?php foreach ($analyticsSeries as $point): ?>
                                        <div class="creator-trend__row">
                                            <span><?= e((string) $point['label']); ?></span>
                                            <div class="creator-trend__bar">
                                                <i style="width: <?= e((string) max(6, (int) round(((int) $point['views'] / $maxTrendViews) * 100))); ?>%;"></i>
                                            </div>
                                            <strong><?= e((string) $point['views']); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </article>

                            <article class="compliance-card">
                                <h3>Top videos</h3>
                                <?php if ($topVideos): ?>
                                    <div class="creator-analytics-list">
                                        <?php foreach ($topVideos as $video): ?>
                                            <div class="creator-analytics-list__row">
                                                <div>
                                                    <strong><?= e($video['title']); ?></strong>
                                                    <p><?= e($video['moderation_label']); ?> / <?= e($video['access_label']); ?></p>
                                                </div>
                                                <div class="creator-analytics-list__stats">
                                                    <span><?= e((string) $video['total_views']); ?> total</span>
                                                    <span><?= e((string) $video['views_30d']); ?> / 30d</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="form-note">Views will appear here after your public videos start getting traffic.</p>
                                <?php endif; ?>
                            </article>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($screen === 'profile'): ?>
                    <section class="catalog-section">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">PROFILE</span>
                                <h2>Channel appearance</h2>
                            </div>
                            <p>Update the public channel name, bio, avatar, and banner.</p>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                            <input type="hidden" name="action" value="save_creator_profile">
                            <?= csrf_input('studio'); ?>

                            <section class="admin-form-section">
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Channel name</span>
                                        <input type="text" name="creator_display_name" value="<?= e((string) creator_public_name($creator)); ?>" required>
                                    </label>
                                    <label>
                                        <span>Channel link</span>
                                        <input type="text" name="creator_slug" value="<?= e((string) ($creator['creator_slug'] ?? slugify($channelName))); ?>" required>
                                    </label>
                                </div>
                                <label>
                                    <span>Channel bio</span>
                                    <textarea name="creator_bio" rows="6" placeholder="Tell viewers what your channel is about."><?= e((string) ($creator['creator_bio'] ?? '')); ?></textarea>
                                </label>
                            </section>

                            <section class="admin-form-section">
                                <div class="studio-profile-preview">
                                    <img class="studio-profile-preview__banner" src="<?= e((string) ($creator['resolved_creator_banner_url'] ?? creator_banner_fallback($channelName))); ?>" alt="<?= e($channelName); ?> banner">
                                    <img class="studio-profile-preview__avatar" src="<?= e((string) ($creator['resolved_creator_avatar_url'] ?? creator_avatar_fallback($channelName))); ?>" alt="<?= e($channelName); ?> avatar">
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Avatar source</span>
                                        <select name="avatar_source_mode" data-media-switch="avatar">
                                            <option value="">Keep current avatar</option>
                                            <option value="upload">Upload an image</option>
                                            <option value="url">Avatar URL</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Banner source</span>
                                        <select name="banner_source_mode" data-media-switch="banner">
                                            <option value="">Keep current banner</option>
                                            <option value="upload">Upload an image</option>
                                            <option value="url">Banner URL</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <div class="admin-conditional-field" data-media-group="avatar" data-media-mode="upload" style="display:none;">
                                        <label>
                                            <span>Avatar image</span>
                                            <input type="file" name="avatar_file" accept="image/*">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="avatar" data-media-mode="url" style="display:none;">
                                        <label>
                                            <span>Avatar URL</span>
                                            <input type="url" name="avatar_external_url" placeholder="https://...">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="banner" data-media-mode="upload" style="display:none;">
                                        <label>
                                            <span>Banner image</span>
                                            <input type="file" name="banner_file" accept="image/*">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="banner" data-media-mode="url" style="display:none;">
                                        <label>
                                            <span>Banner URL</span>
                                            <input type="url" name="banner_external_url" placeholder="https://...">
                                        </label>
                                    </div>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="remove_avatar" value="1">
                                        <span>Remove current avatar</span>
                                    </label>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="remove_banner" value="1">
                                        <span>Remove current banner</span>
                                    </label>
                                </div>
                            </section>

                            <button class="button" type="submit">Save channel profile</button>
                        </form>
                    </section>
                <?php endif; ?>

            </div>

            <aside class="studio-sidebar">
                <div class="studio-profile-card">
                    <img class="studio-profile-card__banner" src="<?= e((string) ($creator['resolved_creator_banner_url'] ?? creator_banner_fallback($channelName))); ?>" alt="<?= e($channelName); ?> banner">
                    <div class="studio-profile-card__body">
                        <img class="studio-profile-card__avatar" src="<?= e((string) ($creator['resolved_creator_avatar_url'] ?? creator_avatar_fallback($channelName))); ?>" alt="<?= e($channelName); ?> avatar">
                        <strong><?= e($channelName); ?></strong>
                        <p><?= e((string) ($creator['creator_slug'] ?? slugify($channelName))); ?></p>
                    </div>
                </div>

                <article class="studio-side-summary">
                    <span class="eyebrow">CHANNEL SNAPSHOT</span>
                    <div class="studio-side-summary__stats">
                        <div>
                            <span>Videos</span>
                            <strong><?= e((string) ($creatorStats['total'] ?? 0)); ?></strong>
                        </div>
                        <div>
                            <span>Live</span>
                            <strong><?= e((string) ($creatorStats['published'] ?? 0)); ?></strong>
                        </div>
                        <div>
                            <span>In review</span>
                            <strong><?= e((string) ($creatorStats['draft'] ?? 0)); ?></strong>
                        </div>
                        <div>
                            <span>Views</span>
                            <strong><?= e((string) ($analyticsOverview['views_total'] ?? 0)); ?></strong>
                        </div>
                    </div>
                    <?php if ($channelUrl): ?>
                        <a class="front-secondary-action" href="<?= e($channelUrl); ?>" target="_blank" rel="noreferrer">Open public channel</a>
                    <?php endif; ?>
                </article>
            </aside>
        </div>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('studio')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
