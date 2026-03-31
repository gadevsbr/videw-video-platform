<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Repositories\CreatorApplicationRepository;
use App\Repositories\UserRepository;
use App\Services\CreatorService;

ensure_logged_in();

$userRepository = new UserRepository();
$applicationRepository = new CreatorApplicationRepository();
$auditLogs = new AuditLogRepository();
$creatorService = new CreatorService();
$sessionUser = current_user(true);
$userId = (int) ($sessionUser['id'] ?? 0);
$user = $userRepository->findById($userId) ?? $sessionUser;

if (is_creator()) {
    redirect('studio.php');
}

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'creator_apply')) {
        flash('error', 'Security token expired. Try again.');
        redirect('become-creator.php');
    }

    $result = $creatorService->submitApplication($userId, [
        'requested_display_name' => trim((string) ($_POST['requested_display_name'] ?? '')),
        'requested_slug' => trim((string) ($_POST['requested_slug'] ?? '')),
        'requested_bio' => trim((string) ($_POST['requested_bio'] ?? '')),
    ]);

    if ($result['success']) {
        $auditLogs->record($userId ?: null, 'creator.application_submitted', 'creator_application', (int) ($result['application_id'] ?? 0), 'Submitted a creator application.');
        flash('success', $result['message']);
    } else {
        flash('error', $result['message']);
    }

    redirect('become-creator.php');
}

$application = $applicationRepository->latestForUser($userId);
$flashError = flash('error');
$flashSuccess = flash('success');
$requestedDisplayName = $application['requested_display_name'] ?? creator_public_name($user);
$requestedSlug = $application['requested_slug'] ?? ($user['creator_slug'] ?? slugify((string) ($user['display_name'] ?? 'creator')));
$requestedBio = $application['requested_bio'] ?? ($user['creator_bio'] ?? '');
$applicationStatus = (string) ($application['status'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become Creator | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('public-layout')); ?>">
    <?php
    $publicNavActive = '';
    require ROOT_PATH . '/partials/public-header.php';
    ?>

    <main class="page-shell creator-apply-page">
        <?php if ($flashError): ?>
            <div class="flash flash--error"><?= e((string) $flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
        <?php endif; ?>

        <section class="creator-apply-hero">
            <div>
                <span class="eyebrow">CREATOR ACCESS</span>
                <h1>Apply to publish on your own channel.</h1>
                <p>Send your channel details for review. After approval, this account gets a creator studio with publishing, video management, analytics, and public profile tools.</p>
            </div>
            <div class="creator-apply-hero__actions">
                <a class="front-secondary-action" href="<?= e(base_url('account.php')); ?>">Back to account</a>
                <a class="front-secondary-action" href="<?= e(base_url('support.php')); ?>">Need help?</a>
            </div>
        </section>

        <div class="creator-apply-layout">
            <section class="compliance-card">
                <div class="section-heading section-heading--tight">
                    <div>
                        <span class="eyebrow">REQUEST</span>
                        <h2>Creator application</h2>
                    </div>
                    <p>Use the public channel name, link, and profile text you want people to see.</p>
                </div>

                <form method="post" class="security-form creator-form">
                    <?= csrf_input('creator_apply'); ?>
                    <label>
                        <span>Channel name</span>
                        <input type="text" name="requested_display_name" value="<?= e((string) $requestedDisplayName); ?>" required>
                    </label>
                    <label>
                        <span>Channel link</span>
                        <div class="input-prefix">
                            <span><?= e(base_url('channel.php?creator=')); ?></span>
                            <input type="text" name="requested_slug" value="<?= e((string) $requestedSlug); ?>" required>
                        </div>
                    </label>
                    <label>
                        <span>Channel bio</span>
                        <textarea name="requested_bio" rows="6" placeholder="Tell viewers what your channel is about."><?= e((string) $requestedBio); ?></textarea>
                    </label>
                    <button class="button" type="submit"><?= $applicationStatus === 'rejected' ? 'Send a new request' : ($applicationStatus === 'pending' ? 'Update request' : 'Send request'); ?></button>
                </form>
            </section>

            <aside class="creator-apply-status">
                <article class="compliance-card">
                    <span class="eyebrow">STATUS</span>
                    <h3><?= $applicationStatus === 'pending' ? 'Waiting for review' : ($applicationStatus === 'approved' ? 'Approved' : ($applicationStatus === 'rejected' ? 'Needs changes' : 'No request yet')); ?></h3>
                    <p>
                        <?php if ($applicationStatus === 'pending'): ?>
                            Your request is in the review queue. You can still update the name, link, and bio while it is pending.
                        <?php elseif ($applicationStatus === 'approved'): ?>
                            Your creator request has been approved. Open the studio to start building your channel.
                        <?php elseif ($applicationStatus === 'rejected'): ?>
                            Review notes are below. Update the request and send it again when ready.
                        <?php else: ?>
                            Send your request to unlock publishing tools, creator analytics, and a public channel page.
                        <?php endif; ?>
                    </p>
                    <?php if ($application): ?>
                        <p><strong>Sent:</strong> <?= e((string) ($application['created_label'] ?? '')); ?></p>
                        <?php if (!empty($application['review_notes'])): ?>
                            <div class="creator-note">
                                <strong>Review notes</strong>
                                <p><?= nl2br(e((string) $application['review_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>

                <article class="compliance-card">
                    <span class="eyebrow">WHAT YOU GET</span>
                    <ul class="creator-checklist">
                        <li>Creator studio with publish and manage screens</li>
                        <li>Detailed video analytics and recent performance</li>
                        <li>Public channel page with avatar, banner, and bio</li>
                        <li>Separate creator workflow without using the admin panel</li>
                    </ul>
                </article>
            </aside>
        </div>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('become-creator')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
