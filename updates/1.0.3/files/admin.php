<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Repositories\AdRepository;
use App\Repositories\CreatorApplicationRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoAnalyticsRepository;
use App\Repositories\VideoRepository;
use App\Services\AdminBackupService;
use App\Services\AdminExportService;
use App\Services\AdService;
use App\Services\AdminVideoService;
use App\Services\BillingService;
use App\Services\DatabaseVersionService;
use App\Services\GitHubReleaseService;
use App\Services\MediaAccessService;

ensure_admin();

$settingsRepository = new SettingsRepository();
$adsRepository = new AdRepository();
$auditLogs = new AuditLogRepository();
$creatorApplications = new CreatorApplicationRepository();
$usersRepository = new UserRepository();
$videoAnalytics = new VideoAnalyticsRepository();
$videoRepository = new VideoRepository();
$adService = new AdService();
$adminVideos = new AdminVideoService();
$mediaAccess = new MediaAccessService();
$billing = new BillingService();
$databaseVersions = new DatabaseVersionService();
$backupService = new AdminBackupService();
$exportService = new AdminExportService();
$releaseService = new GitHubReleaseService();
$dbReady = $settingsRepository->dbReady();
$validScreens = ['overview', 'analytics', 'storage', 'billing', 'publish', 'library', 'moderation', 'creator_requests', 'users', 'settings', 'ads', 'copy', 'legal', 'activity'];
$screen = (string) ($_GET['screen'] ?? 'overview');
$screen = in_array($screen, $validScreens, true) ? $screen : 'overview';
$screenUrl = static fn (string $target): string => base_url('admin.php?screen=' . urlencode($target));
$actorId = (int) (current_user()['id'] ?? 0);

if (is_post_request()) {
    if (request_exceeded_post_max_size()) {
        flash(
            'error',
            'The upload is larger than the server limit. Increase post_max_size and upload_max_filesize or upload a smaller file. Current limits: post_max_size '
            . ini_size_label('post_max_size')
            . ' and upload_max_filesize '
            . ini_size_label('upload_max_filesize')
            . '.'
        );
        redirect('admin.php?screen=' . $screen);
    }

    if (!verify_csrf($_POST['_csrf'] ?? null, 'admin')) {
        flash('error', 'Security token expired. Try again.');
        redirect('admin.php?screen=' . $screen);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_storage') {
        try {
            $currentWasabiAccessKey = (string) config('storage.wasabi_access_key', '');
            $currentWasabiSecretKey = (string) config('storage.wasabi_secret_key', '');
            $submittedWasabiAccessKey = trim((string) ($_POST['wasabi_access_key'] ?? ''));
            $submittedWasabiSecretKey = trim((string) ($_POST['wasabi_secret_key'] ?? ''));
            $storageSettings = [
                'upload_driver' => in_array((string) ($_POST['upload_driver'] ?? 'local'), ['local', 'wasabi'], true)
                    ? (string) $_POST['upload_driver']
                    : 'local',
                'wasabi_endpoint' => trim((string) ($_POST['wasabi_endpoint'] ?? '')),
                'wasabi_region' => trim((string) ($_POST['wasabi_region'] ?? '')),
                'wasabi_bucket' => trim((string) ($_POST['wasabi_bucket'] ?? '')),
                'wasabi_access_key' => $submittedWasabiAccessKey !== '' ? $submittedWasabiAccessKey : $currentWasabiAccessKey,
                'wasabi_secret_key' => $submittedWasabiSecretKey !== '' ? $submittedWasabiSecretKey : $currentWasabiSecretKey,
                'wasabi_public_base_url' => trim((string) ($_POST['wasabi_public_base_url'] ?? '')),
                'wasabi_path_prefix' => trim((string) ($_POST['wasabi_path_prefix'] ?? 'videw')),
                'wasabi_private_bucket' => (string) (($_POST['wasabi_private_bucket'] ?? '') === '1' ? '1' : '0'),
                'wasabi_signed_url_ttl_seconds' => trim((string) ($_POST['wasabi_signed_url_ttl_seconds'] ?? '900')),
                'wasabi_multipart_threshold_mb' => trim((string) ($_POST['wasabi_multipart_threshold_mb'] ?? '64')),
                'wasabi_multipart_part_size_mb' => trim((string) ($_POST['wasabi_multipart_part_size_mb'] ?? '16')),
            ];

            write_env_file_values(ROOT_PATH . '/.env', storage_settings_to_env_values($storageSettings));
            $settingsRepository->putMany($storageSettings);
            $auditLogs->record($actorId ?: null, 'storage.saved', 'settings', null, 'Updated storage settings.', [
                'driver' => $storageSettings['upload_driver'],
                'private_bucket' => $storageSettings['wasabi_private_bucket'],
            ]);
            flash('success', 'Storage settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=storage');
    }

    if ($action === 'save_billing_settings') {
        try {
            $currentStripeSecretKey = (string) config('billing.stripe_secret_key', '');
            $currentStripeWebhookSecret = (string) config('billing.stripe_webhook_secret', '');
            $submittedStripeSecretKey = trim((string) ($_POST['stripe_secret_key'] ?? ''));
            $submittedStripeWebhookSecret = trim((string) ($_POST['stripe_webhook_secret'] ?? ''));
            $billingSettings = [
                'stripe_secret_key' => $submittedStripeSecretKey !== '' ? $submittedStripeSecretKey : $currentStripeSecretKey,
                'stripe_publishable_key' => trim((string) ($_POST['stripe_publishable_key'] ?? (string) config('billing.stripe_publishable_key'))),
                'stripe_webhook_secret' => $submittedStripeWebhookSecret !== '' ? $submittedStripeWebhookSecret : $currentStripeWebhookSecret,
                'premium_price_id' => trim((string) ($_POST['premium_price_id'] ?? (string) config('billing.premium_price_id'))),
                'premium_plan_name' => trim((string) ($_POST['premium_plan_name'] ?? (string) config('billing.premium_plan_name'))),
                'premium_plan_copy' => trim((string) ($_POST['premium_plan_copy'] ?? (string) config('billing.premium_plan_copy'))),
                'premium_price_label' => trim((string) ($_POST['premium_price_label'] ?? (string) config('billing.premium_price_label'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', billing_settings_to_env_values($billingSettings));
            $auditLogs->record($actorId ?: null, 'billing.saved', 'settings', null, 'Updated payment settings.', [
                'price_id' => $billingSettings['premium_price_id'],
                'plan_name' => $billingSettings['premium_plan_name'],
                'webhook_configured' => $billingSettings['stripe_webhook_secret'] !== '' ? 1 : 0,
            ]);
            flash('success', 'Payment settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=billing');
    }

    if ($action === 'retry_billing_webhook') {
        $eventId = trim((string) ($_POST['event_id'] ?? ''));

        try {
            if ($eventId === '') {
                throw new RuntimeException('Webhook event ID is required for retry.');
            }

            $result = $billing->retryWebhookEvent($eventId);
            $auditLogs->record($actorId ?: null, 'billing.webhook_retried', 'settings', null, 'Retried a failed Stripe webhook event.', [
                'type' => $result['type'] ?? '',
                'event_id' => $result['event_id'] ?? $eventId,
                'status' => $result['status'] ?? 'processed',
            ]);
            flash('success', 'Webhook event retried successfully.');
        } catch (RuntimeException $exception) {
            $auditLogs->record($actorId ?: null, 'billing.webhook_retry_failed', 'settings', null, 'Failed to retry a Stripe webhook event.', [
                'event_id' => $eventId,
                'error' => $exception->getMessage(),
            ]);
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=billing');
    }

    if ($action === 'save_app_settings') {
        try {
            $appSettings = [
                'app_name' => trim((string) ($_POST['app_name'] ?? (string) config('app.name'))),
                'app_description' => trim((string) ($_POST['app_description'] ?? (string) config('app.description'))),
                'brand_kicker' => trim((string) ($_POST['brand_kicker'] ?? brand_kicker())),
                'brand_title' => trim((string) ($_POST['brand_title'] ?? brand_title())),
                'age_gate_enabled' => isset($_POST['age_gate_enabled']) ? '1' : '0',
                'base_url' => trim((string) ($_POST['base_url'] ?? base_url())),
                'support_email' => trim((string) ($_POST['support_email'] ?? (string) config('app.support_email'))),
                'exit_url' => trim((string) ($_POST['exit_url'] ?? (string) config('app.exit_url'))),
                'public_head_scripts' => trim((string) ($_POST['public_head_scripts'] ?? (string) config('app.public_head_scripts'))),
                'github_repository' => trim((string) ($_POST['github_repository'] ?? (string) config('updates.github_repository'))),
                'current_version' => trim((string) ($_POST['current_version'] ?? (string) config('updates.current_version'))),
                'timezone' => trim((string) ($_POST['timezone'] ?? (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', app_settings_to_env_values($appSettings));
            $auditLogs->record($actorId ?: null, 'app.saved', 'settings', null, 'Updated site settings.', [
                'app_name' => $appSettings['app_name'],
                'brand' => $appSettings['brand_kicker'] . ' ' . $appSettings['brand_title'],
                'github_repository' => $appSettings['github_repository'],
                'current_version' => $appSettings['current_version'],
            ]);
            flash('success', 'Site settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=settings');
    }

    if ($action === 'save_ad_slot') {
        try {
            $slotKey = trim((string) ($_POST['slot_key'] ?? ''));
            $savedAd = $adService->saveSlot($slotKey, $_POST, $_FILES, $adsRepository->findBySlot($slotKey));

            $auditLogs->record($actorId ?: null, 'ad.saved', 'ad_slot', null, 'Updated an ad slot.', [
                'slot' => $slotKey,
                'type' => $savedAd['ad_type'] ?? 'placeholder',
                'active' => !empty($savedAd['is_active']) ? 1 : 0,
            ]);
            flash('success', 'Ad slot saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        $target = trim((string) ($_POST['return_screen'] ?? 'ads')) ?: 'ads';
        redirect('admin.php?screen=' . urlencode($target) . '&slot=' . urlencode($slotKey));
    }

    if ($action === 'save_copy_settings') {
        try {
            $submittedCopy = is_array($_POST['copy'] ?? null) ? $_POST['copy'] : [];
            $copySettings = current_copy_settings();

            foreach (array_keys($copySettings) as $copyKey) {
                $formKey = str_replace('.', '__', (string) $copyKey);
                $copySettings[(string) $copyKey] = trim((string) ($submittedCopy[$formKey] ?? $copySettings[(string) $copyKey]));
            }

            write_env_file_values(ROOT_PATH . '/.env', copy_settings_to_env_values($copySettings));
            $auditLogs->record($actorId ?: null, 'copy.saved', 'settings', null, 'Updated public text settings.', [
                'sections' => count(copy_admin_sections()),
                'fields' => count($copySettings),
            ]);
            flash('success', 'Public text settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=copy');
    }

    if ($action === 'save_legal_settings') {
        try {
            $legalSettings = [
                'footer_tagline' => trim((string) ($_POST['footer_tagline'] ?? (string) config('footer.tagline'))),
                'footer_useful_title' => trim((string) ($_POST['footer_useful_title'] ?? (string) config('footer.useful_title'))),
                'footer_legal_title' => trim((string) ($_POST['footer_legal_title'] ?? (string) config('footer.legal_title'))),
                'footer_support_title' => trim((string) ($_POST['footer_support_title'] ?? (string) config('footer.support_title'))),
                'footer_support_copy' => trim((string) ($_POST['footer_support_copy'] ?? (string) config('footer.support_copy'))),
                'footer_useful_link_1_label' => trim((string) ($_POST['footer_useful_link_1_label'] ?? '')),
                'footer_useful_link_1_url' => trim((string) ($_POST['footer_useful_link_1_url'] ?? '')),
                'footer_useful_link_2_label' => trim((string) ($_POST['footer_useful_link_2_label'] ?? '')),
                'footer_useful_link_2_url' => trim((string) ($_POST['footer_useful_link_2_url'] ?? '')),
                'footer_useful_link_3_label' => trim((string) ($_POST['footer_useful_link_3_label'] ?? '')),
                'footer_useful_link_3_url' => trim((string) ($_POST['footer_useful_link_3_url'] ?? '')),
                'footer_legal_link_1_label' => trim((string) ($_POST['footer_legal_link_1_label'] ?? '')),
                'footer_legal_link_1_url' => trim((string) ($_POST['footer_legal_link_1_url'] ?? '')),
                'footer_legal_link_2_label' => trim((string) ($_POST['footer_legal_link_2_label'] ?? '')),
                'footer_legal_link_2_url' => trim((string) ($_POST['footer_legal_link_2_url'] ?? '')),
                'footer_legal_link_3_label' => trim((string) ($_POST['footer_legal_link_3_label'] ?? '')),
                'footer_legal_link_3_url' => trim((string) ($_POST['footer_legal_link_3_url'] ?? '')),
                'footer_legal_link_4_label' => trim((string) ($_POST['footer_legal_link_4_label'] ?? '')),
                'footer_legal_link_4_url' => trim((string) ($_POST['footer_legal_link_4_url'] ?? '')),
                'rules_nav_label' => trim((string) ($_POST['rules_nav_label'] ?? rules_nav_label())),
                'rules_kicker' => trim((string) ($_POST['rules_kicker'] ?? (string) config('legal.rules.kicker'))),
                'rules_title' => trim((string) ($_POST['rules_title'] ?? (string) config('legal.rules.title'))),
                'rules_intro' => trim((string) ($_POST['rules_intro'] ?? (string) config('legal.rules.intro'))),
                'rules_card_1_title' => trim((string) ($_POST['rules_card_1_title'] ?? '')),
                'rules_card_1_text' => trim((string) ($_POST['rules_card_1_text'] ?? '')),
                'rules_card_2_title' => trim((string) ($_POST['rules_card_2_title'] ?? '')),
                'rules_card_2_text' => trim((string) ($_POST['rules_card_2_text'] ?? '')),
                'rules_card_3_title' => trim((string) ($_POST['rules_card_3_title'] ?? '')),
                'rules_card_3_text' => trim((string) ($_POST['rules_card_3_text'] ?? '')),
                'rules_card_4_title' => trim((string) ($_POST['rules_card_4_title'] ?? '')),
                'rules_card_4_text' => trim((string) ($_POST['rules_card_4_text'] ?? '')),
                'terms_kicker' => trim((string) ($_POST['terms_kicker'] ?? (string) config('legal.terms.kicker'))),
                'terms_title' => trim((string) ($_POST['terms_title'] ?? (string) config('legal.terms.title'))),
                'terms_intro' => trim((string) ($_POST['terms_intro'] ?? (string) config('legal.terms.intro'))),
                'terms_content' => trim((string) ($_POST['terms_content'] ?? (string) config('legal.terms.content'))),
                'privacy_kicker' => trim((string) ($_POST['privacy_kicker'] ?? (string) config('legal.privacy.kicker'))),
                'privacy_title' => trim((string) ($_POST['privacy_title'] ?? (string) config('legal.privacy.title'))),
                'privacy_intro' => trim((string) ($_POST['privacy_intro'] ?? (string) config('legal.privacy.intro'))),
                'privacy_content' => trim((string) ($_POST['privacy_content'] ?? (string) config('legal.privacy.content'))),
                'cookies_kicker' => trim((string) ($_POST['cookies_kicker'] ?? (string) config('legal.cookies.kicker'))),
                'cookies_title' => trim((string) ($_POST['cookies_title'] ?? (string) config('legal.cookies.title'))),
                'cookies_intro' => trim((string) ($_POST['cookies_intro'] ?? (string) config('legal.cookies.intro'))),
                'cookies_content' => trim((string) ($_POST['cookies_content'] ?? (string) config('legal.cookies.content'))),
                'cookie_notice_enabled' => (string) (($_POST['cookie_notice_enabled'] ?? '') === '1' ? '1' : '0'),
                'cookie_notice_title' => trim((string) ($_POST['cookie_notice_title'] ?? (string) config('cookie_notice.title'))),
                'cookie_notice_text' => trim((string) ($_POST['cookie_notice_text'] ?? (string) config('cookie_notice.text'))),
                'cookie_notice_accept_label' => trim((string) ($_POST['cookie_notice_accept_label'] ?? (string) config('cookie_notice.accept_label'))),
                'cookie_notice_link_label' => trim((string) ($_POST['cookie_notice_link_label'] ?? (string) config('cookie_notice.link_label'))),
                'cookie_notice_link_url' => trim((string) ($_POST['cookie_notice_link_url'] ?? (string) config('cookie_notice.link_url'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', legal_settings_to_env_values($legalSettings));
            $auditLogs->record($actorId ?: null, 'legal.saved', 'settings', null, 'Updated legal pages, footer links, and cookie notice settings.', [
                'rules_title' => $legalSettings['rules_title'],
                'terms_title' => $legalSettings['terms_title'],
                'privacy_title' => $legalSettings['privacy_title'],
                'cookies_title' => $legalSettings['cookies_title'],
                'cookie_notice_enabled' => $legalSettings['cookie_notice_enabled'],
            ]);
            flash('success', 'Legal pages and footer settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=legal');
    }

    if ($action === 'publish_video') {
        remember_input([
            'title' => trim((string) ($_POST['title'] ?? '')),
            'creator_name' => trim((string) ($_POST['creator_name'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'access_level' => trim((string) ($_POST['access_level'] ?? 'free')),
            'duration_minutes' => trim((string) ($_POST['duration_minutes'] ?? '0')),
            'synopsis' => trim((string) ($_POST['synopsis'] ?? '')),
            'source_mode' => trim((string) ($_POST['source_mode'] ?? '')),
            'external_url' => trim((string) ($_POST['external_url'] ?? '')),
            'poster_source_mode' => trim((string) ($_POST['poster_source_mode'] ?? '')),
            'poster_external_url' => trim((string) ($_POST['poster_external_url'] ?? '')),
            'is_featured' => (string) ($_POST['is_featured'] ?? ''),
            'moderation_status' => trim((string) ($_POST['moderation_status'] ?? 'draft')),
            'moderation_reason' => normalize_moderation_reason((string) ($_POST['moderation_reason'] ?? '')),
            'moderation_notes' => trim((string) ($_POST['moderation_notes'] ?? '')),
        ]);

        $result = $adminVideos->publish($_POST, $_FILES);

        if ($result['success']) {
            clear_old_input();
            $auditLogs->record($actorId ?: null, 'video.created', 'video', (int) ($result['video_id'] ?? 0), 'Created a video.', [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'status' => trim((string) ($_POST['moderation_status'] ?? 'draft')),
                'reason' => normalize_moderation_reason((string) ($_POST['moderation_reason'] ?? '')),
            ]);
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=publish');
    }

    if ($action === 'update_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $result = $adminVideos->update($videoId, $_POST, $_FILES);

        if ($result['success']) {
            $auditLogs->record($actorId ?: null, 'video.updated', 'video', $videoId, 'Updated a video.', [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'status' => trim((string) ($_POST['moderation_status'] ?? 'draft')),
                'reason' => normalize_moderation_reason((string) ($_POST['moderation_reason'] ?? '')),
            ]);
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=library&edit=' . $videoId);
    }

    if ($action === 'moderate_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $status = trim((string) ($_POST['moderation_status'] ?? 'draft'));
        $reason = normalize_moderation_reason((string) ($_POST['moderation_reason'] ?? ''));
        $notes = trim((string) ($_POST['moderation_notes'] ?? ''));

        try {
            $videoRepository->updateModeration($videoId, $status, $reason !== '' ? $reason : null, $notes !== '' ? $notes : null);
            $auditLogs->record($actorId ?: null, 'video.moderated', 'video', $videoId, 'Updated moderation status.', [
                'status' => $status,
                'reason' => $reason,
                'notes' => $notes,
            ]);
            flash('success', 'Moderation updated.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=moderation');
    }

    if ($action === 'toggle_featured') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $nextValue = (int) ($_POST['next_value'] ?? 0) === 1;

        try {
            $videoRepository->setFeatured($videoId, $nextValue);
            $auditLogs->record($actorId ?: null, 'video.featured', 'video', $videoId, $nextValue ? 'Marked video as featured.' : 'Removed video from featured.', [
                'is_featured' => $nextValue ? 1 : 0,
            ]);
            flash('success', 'Featured state updated.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=library');
    }

    if ($action === 'delete_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $result = $adminVideos->delete($videoId);

        if ($result['success']) {
            $auditLogs->record($actorId ?: null, 'video.deleted', 'video', $videoId, 'Deleted a video and cleaned stored assets.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=library');
    }

    if ($action === 'bulk_library_action') {
        $videoIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['video_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $bulkAction = trim((string) ($_POST['bulk_action'] ?? ''));

        if ($videoIds === []) {
            flash('error', 'Select at least one video first.');
            redirect('admin.php?screen=library');
        }

        try {
            $count = match ($bulkAction) {
                'approve' => $videoRepository->bulkUpdateModeration($videoIds, 'approved'),
                'draft' => $videoRepository->bulkUpdateModeration($videoIds, 'draft'),
                'flagged' => $videoRepository->bulkUpdateModeration($videoIds, 'flagged'),
                'feature' => $videoRepository->bulkSetFeatured($videoIds, true),
                'unfeature' => $videoRepository->bulkSetFeatured($videoIds, false),
                'delete' => $adminVideos->bulkDelete($videoIds),
                default => throw new RuntimeException('Invalid bulk action.'),
            };

            $auditLogs->record($actorId ?: null, 'video.bulk', 'video', null, 'Applied a bulk action in the library.', [
                'action' => $bulkAction,
                'video_ids' => $videoIds,
                'count' => $count,
            ]);
            flash('success', 'Bulk action applied to ' . $count . ' videos.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=library');
    }

    if ($action === 'bulk_moderation_action') {
        $videoIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['video_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $bulkAction = trim((string) ($_POST['bulk_action'] ?? ''));
        $bulkReason = normalize_moderation_reason((string) ($_POST['bulk_reason'] ?? ''));
        $bulkNotes = trim((string) ($_POST['bulk_notes'] ?? ''));

        if ($videoIds === []) {
            flash('error', 'Select at least one video first.');
            redirect('admin.php?screen=moderation');
        }

        try {
            $count = match ($bulkAction) {
                'approve' => $videoRepository->bulkUpdateModeration($videoIds, 'approved', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'draft' => $videoRepository->bulkUpdateModeration($videoIds, 'draft', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'flagged' => $videoRepository->bulkUpdateModeration($videoIds, 'flagged', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'delete' => $adminVideos->bulkDelete($videoIds),
                default => throw new RuntimeException('Invalid bulk moderation action.'),
            };

            $auditLogs->record($actorId ?: null, 'video.bulk_moderation', 'video', null, 'Applied a bulk moderation action.', [
                'action' => $bulkAction,
                'video_ids' => $videoIds,
                'reason' => $bulkReason,
                'notes' => $bulkNotes,
                'count' => $count,
            ]);
            flash('success', 'Bulk moderation applied to ' . $count . ' videos.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=moderation');
    }

    if ($action === 'review_creator_application') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $reviewStatus = trim((string) ($_POST['review_status'] ?? 'pending'));
        $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));

        try {
            $application = $creatorApplications->findById($applicationId);

            if (!$application) {
                throw new RuntimeException('Creator request not found.');
            }

            $requestUser = $usersRepository->findById((int) ($application['user_id'] ?? 0));

            if (!$requestUser) {
                throw new RuntimeException('Creator account not found.');
            }

            if ($reviewStatus === 'approved') {
                $resolvedSlug = $usersRepository->generateUniqueCreatorSlug((string) ($application['requested_slug'] ?? ''), (int) $requestUser['id']);

                $usersRepository->updateCreatorProfile((int) $requestUser['id'], [
                    'creator_display_name' => (string) ($application['requested_display_name'] ?? creator_public_name($requestUser)),
                    'creator_slug' => $resolvedSlug,
                    'creator_bio' => (string) ($application['requested_bio'] ?? ($requestUser['creator_bio'] ?? '')),
                    'creator_avatar_url' => $requestUser['creator_avatar_url'] ?? null,
                    'creator_avatar_path' => $requestUser['creator_avatar_path'] ?? null,
                    'creator_avatar_storage_provider' => $requestUser['creator_avatar_storage_provider'] ?? null,
                    'creator_banner_url' => $requestUser['creator_banner_url'] ?? null,
                    'creator_banner_path' => $requestUser['creator_banner_path'] ?? null,
                    'creator_banner_storage_provider' => $requestUser['creator_banner_storage_provider'] ?? null,
                ]);
                $usersRepository->updateAdminFields((int) $requestUser['id'], 'creator', (string) ($requestUser['status'] ?? 'active'));
                $videoRepository->syncCreatorIdentity((int) $requestUser['id'], (string) ($application['requested_display_name'] ?? creator_public_name($requestUser)));
            }

            $creatorApplications->updateStatus($applicationId, $reviewStatus, $reviewNotes !== '' ? $reviewNotes : null);
            $auditLogs->record($actorId ?: null, 'creator.reviewed', 'creator_application', $applicationId, 'Reviewed a creator request.', [
                'status' => $reviewStatus,
                'user_id' => (int) ($application['user_id'] ?? 0),
            ]);
            flash('success', $reviewStatus === 'approved' ? 'Creator request approved.' : ($reviewStatus === 'rejected' ? 'Creator request rejected.' : 'Creator request updated.'));
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=creator_requests');
    }

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? 'member'));
        $status = trim((string) ($_POST['status'] ?? 'active'));

        try {
            $managedUser = $usersRepository->findById($userId);

            if (!$managedUser) {
                throw new RuntimeException('User not found.');
            }

            $removingActiveAdmin = (string) ($managedUser['role'] ?? '') === 'admin'
                && (string) ($managedUser['status'] ?? 'active') === 'active'
                && ($role !== 'admin' || $status !== 'active');

            if ($userId === $actorId && ($role !== 'admin' || $status !== 'active')) {
                throw new RuntimeException('You cannot remove your own active admin access.');
            }

            if ($removingActiveAdmin && $usersRepository->activeAdminCount() <= 1) {
                throw new RuntimeException('At least one active admin must remain.');
            }

            $usersRepository->updateAdminFields($userId, $role, $status);
            $auditLogs->record($actorId ?: null, 'user.updated', 'user', $userId, 'Updated user role or status.', [
                'role' => $role,
                'status' => $status,
            ]);
            flash('success', 'User updated.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=users');
    }
}

$settings = $settingsRepository->all();
$allVideos = $mediaAccess->decorateVideos($videoRepository->listPublished());
$activityFilters = [
    'action' => trim((string) ($_GET['activity_action'] ?? '')),
    'target_type' => trim((string) ($_GET['activity_target_type'] ?? '')),
    'actor' => trim((string) ($_GET['activity_actor'] ?? '')),
    'from' => trim((string) ($_GET['activity_from'] ?? '')),
    'to' => trim((string) ($_GET['activity_to'] ?? '')),
];
$queryUrl = static function (array $overrides = []) use ($screen): string {
    $query = $_GET;
    $query['screen'] = $screen;

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }

        $query[$key] = (string) $value;
    }

    return base_url('admin.php?' . http_build_query($query));
};
$streamCsv = static function (string $filename, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');

    if (is_resource($output)) {
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }
};
$librarySearch = trim((string) ($_GET['library_search'] ?? ''));
$libraryStatus = trim((string) ($_GET['library_status'] ?? ''));
$librarySource = trim((string) ($_GET['library_source_type'] ?? ''));
$libraryStorage = trim((string) ($_GET['library_storage'] ?? ''));
$libraryPage = max(1, (int) ($_GET['library_page'] ?? 1));
$libraryPagination = $dbReady ? $videoRepository->paginateForAdmin([
    'search' => $librarySearch,
    'status' => $libraryStatus,
    'source_type' => $librarySource,
    'storage_provider' => $libraryStorage,
], $libraryPage, 9) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 9, 'total_pages' => 1];
$libraryVideos = $dbReady ? $mediaAccess->decorateVideos($libraryPagination['items']) : [];
$moderationStatusFilter = trim((string) ($_GET['moderation_status'] ?? 'draft'));
$moderationReasonFilter = normalize_moderation_reason((string) ($_GET['moderation_reason'] ?? ''));
$moderationPage = max(1, (int) ($_GET['moderation_page'] ?? 1));
$moderationPagination = $dbReady ? $videoRepository->paginateForAdmin([
    'status' => in_array($moderationStatusFilter, ['draft', 'approved', 'flagged'], true) ? $moderationStatusFilter : 'draft',
    'reason' => $moderationReasonFilter,
], $moderationPage, 8) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'total_pages' => 1];
$moderationVideos = $dbReady ? $mediaAccess->decorateVideos($moderationPagination['items']) : [];
$moderationReasonOptions = moderation_reason_options();
$editingVideoId = (int) ($_GET['edit'] ?? 0);
$editingVideo = $editingVideoId > 0 ? $videoRepository->findById($editingVideoId) : null;
$moderationHistoryByVideo = $auditLogs->recentForTargets('video', array_map(static fn (array $video): int => (int) ($video['id'] ?? 0), $moderationVideos), 3, ['video.created', 'video.updated', 'video.moderated', 'video.bulk_moderation']);
$editingVideoHistory = $editingVideo ? ($auditLogs->recentForTargets('video', [(int) ($editingVideo['id'] ?? 0)], 6, ['video.created', 'video.updated', 'video.moderated', 'video.bulk_moderation'])[(int) ($editingVideo['id'] ?? 0)] ?? []) : [];
$recentVideos = array_slice($allVideos, 0, 8);
$stats = $videoRepository->stats();
$adminStats = $videoRepository->adminStats();
$creatorRequestStatus = trim((string) ($_GET['creator_request_status'] ?? 'pending'));
$creatorRequestsPage = max(1, (int) ($_GET['creator_requests_page'] ?? 1));
$creatorRequestsPagination = $dbReady ? $creatorApplications->paginate($creatorRequestStatus !== '' ? $creatorRequestStatus : 'pending', $creatorRequestsPage, 8) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'total_pages' => 1];
$creatorRequests = $creatorRequestsPagination['items'];
$creatorRequestStats = $creatorApplications->stats();
$userSearch = trim((string) ($_GET['user_search'] ?? ''));
$usersPage = max(1, (int) ($_GET['users_page'] ?? 1));
$usersPagination = $dbReady ? $usersRepository->paginateAll($userSearch, $usersPage, 10) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1];
$users = $dbReady ? $usersPagination['items'] : [];
$userStats = $usersRepository->stats();
$adminAnalyticsOverview = $videoAnalytics->adminOverview();
$adminAnalyticsSeries = $videoAnalytics->adminDailySeries(14);
$adminAnalyticsTopVideos = $videoAnalytics->adminTopVideos(10);
$adminAnalyticsTopCreators = $videoAnalytics->topCreators(8);
$adminAnalyticsMaxTrendViews = 1;

foreach ($adminAnalyticsSeries as $point) {
    $adminAnalyticsMaxTrendViews = max($adminAnalyticsMaxTrendViews, (int) ($point['views'] ?? 0));
}

$activityPage = max(1, (int) ($_GET['activity_page'] ?? 1));
$activityPagination = $auditLogs->paginate($activityFilters, $activityPage, 20);
$activityItems = $activityPagination['items'];
$appSettings = [
    'app_name' => (string) config('app.name'),
    'app_description' => (string) config('app.description'),
    'brand_kicker' => brand_kicker(),
    'brand_title' => brand_title(),
    'age_gate_enabled' => age_gate_enabled(),
    'base_url' => base_url(),
    'support_email' => (string) config('app.support_email'),
    'exit_url' => (string) config('app.exit_url'),
    'public_head_scripts' => (string) config('app.public_head_scripts'),
    'github_repository' => (string) config('updates.github_repository'),
    'current_version' => (string) config('updates.current_version'),
    'timezone' => (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'),
];
$releaseStatus = $releaseService->updateStatus(
    (string) config('updates.github_repository', ''),
    (string) config('updates.current_version', '')
);
$releaseStatusTitle = match ((string) $releaseStatus['status']) {
    'update_available' => 'Update available',
    'up_to_date' => 'Up to date',
    'ahead_or_custom' => 'Custom or ahead',
    'installed_version_unknown' => 'Version not set',
    'comparison_unknown' => 'Comparison unavailable',
    'error' => 'Release check failed',
    default => 'Release monitoring disabled',
};
$releaseStatusDetail = match ((string) $releaseStatus['status']) {
    'update_available' => 'Installed ' . ((string) ($releaseStatus['installed_version'] ?? '') !== '' ? (string) $releaseStatus['installed_version'] : 'unknown')
        . ' / latest ' . ((string) ($releaseStatus['latest_version'] ?? '') !== '' ? (string) $releaseStatus['latest_version'] : 'unknown') . '.',
    'up_to_date' => 'Installed ' . ((string) ($releaseStatus['installed_version'] ?? '') !== '' ? (string) $releaseStatus['installed_version'] : 'unknown')
        . ' matches the latest GitHub release.',
    'ahead_or_custom' => 'Installed ' . ((string) ($releaseStatus['installed_version'] ?? '') !== '' ? (string) $releaseStatus['installed_version'] : 'unknown')
        . ' is newer than or different from the latest tagged release '
        . (((string) ($releaseStatus['latest_version'] ?? '') !== '') ? (string) $releaseStatus['latest_version'] : 'available on GitHub') . '.',
    'installed_version_unknown' => 'GitHub release found, but the current installed version is not set.',
    'comparison_unknown' => 'GitHub release found, but its version string could not be compared automatically.',
    'error' => (string) ($releaseStatus['error'] ?? 'The GitHub API request did not complete successfully.'),
    default => 'Set a GitHub repository in settings to enable update visibility on this dashboard.',
};
$releasePublishedLabel = format_datetime(
    is_string($releaseStatus['published_at'] ?? null) ? (string) $releaseStatus['published_at'] : null,
    'Not available'
);
$releaseCheckedLabel = format_datetime(
    is_string($releaseStatus['checked_at'] ?? null) ? (string) $releaseStatus['checked_at'] : null,
    'Not available'
);
$adSlots = ad_slot_definitions();
$adsBySlot = $dbReady ? $adsRepository->allBySlot() : [];
$adStats = $dbReady ? $adsRepository->stats() : ['slots' => count($adSlots), 'active' => 0, 'configured' => 0];
$firstAdSlot = array_key_first($adSlots);
$requestedAdSlot = trim((string) ($_GET['slot'] ?? ''));
$activeAdSlot = ($requestedAdSlot !== '' && isset($adSlots[$requestedAdSlot])) ? $requestedAdSlot : ($firstAdSlot ?? '');
$copySections = copy_admin_sections();
$copySettings = current_copy_settings();
$copyHandledKeys = [];

foreach ($copySections as $copySection) {
    foreach ($copySection['fields'] as $field) {
        $copyHandledKeys[] = (string) $field['key'];
    }
}

$copyExtraFields = [];

foreach ($copySettings as $copyKey => $copyValue) {
    if (in_array((string) $copyKey, $copyHandledKeys, true)) {
        continue;
    }

    $copyExtraFields[] = [
        'key' => (string) $copyKey,
        'label' => ucwords(str_replace(['.', '_'], [' ', ' '], (string) $copyKey)),
        'type' => strlen((string) $copyValue) > 100 ? 'textarea' : 'text',
        'rows' => 4,
    ];
}

$copySectionTabs = [];

foreach ($copySections as $index => $copySection) {
    $copySectionTabs[] = [
        'id' => 'copy-section-' . ($index + 1),
        'title' => (string) $copySection['title'],
        'description' => (string) $copySection['description'],
    ];
}

if ($copyExtraFields !== []) {
    $copySectionTabs[] = [
        'id' => 'copy-section-extra',
        'title' => 'Additional copy keys',
        'description' => 'Extra text keys detected automatically from the public text system.',
    ];
}

$billingSettings = [
    'stripe_secret_key' => (string) config('billing.stripe_secret_key'),
    'stripe_publishable_key' => (string) config('billing.stripe_publishable_key'),
    'stripe_webhook_secret' => (string) config('billing.stripe_webhook_secret'),
    'premium_price_id' => (string) config('billing.premium_price_id'),
    'premium_plan_name' => (string) config('billing.premium_plan_name'),
    'premium_plan_copy' => (string) config('billing.premium_plan_copy'),
    'premium_price_label' => (string) config('billing.premium_price_label'),
];
$legalSettings = [
    'footer_tagline' => (string) config('footer.tagline'),
    'footer_useful_title' => (string) config('footer.useful_title'),
    'footer_legal_title' => (string) config('footer.legal_title'),
    'footer_support_title' => (string) config('footer.support_title'),
    'footer_support_copy' => (string) config('footer.support_copy'),
    'footer_useful_link_1_label' => (string) config('footer.useful_links.0.label'),
    'footer_useful_link_1_url' => (string) config('footer.useful_links.0.url'),
    'footer_useful_link_2_label' => (string) config('footer.useful_links.1.label'),
    'footer_useful_link_2_url' => (string) config('footer.useful_links.1.url'),
    'footer_useful_link_3_label' => (string) config('footer.useful_links.2.label'),
    'footer_useful_link_3_url' => (string) config('footer.useful_links.2.url'),
    'footer_legal_link_1_label' => (string) config('footer.legal_links.0.label'),
    'footer_legal_link_1_url' => (string) config('footer.legal_links.0.url'),
    'footer_legal_link_2_label' => (string) config('footer.legal_links.1.label'),
    'footer_legal_link_2_url' => (string) config('footer.legal_links.1.url'),
    'footer_legal_link_3_label' => (string) config('footer.legal_links.2.label'),
    'footer_legal_link_3_url' => (string) config('footer.legal_links.2.url'),
    'footer_legal_link_4_label' => (string) config('footer.legal_links.3.label'),
    'footer_legal_link_4_url' => (string) config('footer.legal_links.3.url'),
    'rules_nav_label' => rules_nav_label(),
    'rules_kicker' => (string) config('legal.rules.kicker'),
    'rules_title' => (string) config('legal.rules.title'),
    'rules_intro' => (string) config('legal.rules.intro'),
    'rules_card_1_title' => (string) config('legal.rules.items.0.title'),
    'rules_card_1_text' => (string) config('legal.rules.items.0.copy'),
    'rules_card_2_title' => (string) config('legal.rules.items.1.title'),
    'rules_card_2_text' => (string) config('legal.rules.items.1.copy'),
    'rules_card_3_title' => (string) config('legal.rules.items.2.title'),
    'rules_card_3_text' => (string) config('legal.rules.items.2.copy'),
    'rules_card_4_title' => (string) config('legal.rules.items.3.title'),
    'rules_card_4_text' => (string) config('legal.rules.items.3.copy'),
    'terms_kicker' => (string) config('legal.terms.kicker'),
    'terms_title' => (string) config('legal.terms.title'),
    'terms_intro' => (string) config('legal.terms.intro'),
    'terms_content' => (string) config('legal.terms.content'),
    'privacy_kicker' => (string) config('legal.privacy.kicker'),
    'privacy_title' => (string) config('legal.privacy.title'),
    'privacy_intro' => (string) config('legal.privacy.intro'),
    'privacy_content' => (string) config('legal.privacy.content'),
    'cookies_kicker' => (string) config('legal.cookies.kicker'),
    'cookies_title' => (string) config('legal.cookies.title'),
    'cookies_intro' => (string) config('legal.cookies.intro'),
    'cookies_content' => (string) config('legal.cookies.content'),
    'cookie_notice_enabled' => config('cookie_notice.enabled', true) ? '1' : '0',
    'cookie_notice_title' => (string) config('cookie_notice.title'),
    'cookie_notice_text' => (string) config('cookie_notice.text'),
    'cookie_notice_accept_label' => (string) config('cookie_notice.accept_label'),
    'cookie_notice_link_label' => (string) config('cookie_notice.link_label'),
    'cookie_notice_link_url' => (string) config('cookie_notice.link_url'),
];
$wasabiEnabled = (string) ($settings['upload_driver'] ?? 'local') === 'wasabi';
$privateDelivery = (string) ($settings['wasabi_private_bucket'] ?? '0') === '1';
$wasabiBucket = (string) ($settings['wasabi_bucket'] ?? '');
$embedCount = count(array_filter($allVideos, static fn (array $video): bool => (string) ($video['source_type'] ?? '') === 'embed'));
$externalCount = count(array_filter($allVideos, static fn (array $video): bool => in_array((string) ($video['source_type'] ?? ''), ['embed', 'external_file'], true)));
$wasabiCount = count(array_filter($allVideos, static fn (array $video): bool => (string) ($video['storage_provider'] ?? '') === 'wasabi'));
$featuredCount = count(array_filter($allVideos, static fn (array $video): bool => (int) ($video['is_featured'] ?? 0) === 1));
$premiumVideoCount = count(array_filter($allVideos, static fn (array $video): bool => video_requires_premium($video)));
$freeVideoCount = max(0, count($allVideos) - $premiumVideoCount);
$billingConfigured = $billing->isConfigured();
$webhookConfigured = $billing->webhookConfigured();
$webhookUrl = $billing->webhookUrl();
$webhookSnapshot = $billing->webhookStatusSnapshot();
$recentWebhookEvents = $billing->webhookRecentEvents(8);
$failedWebhookEvents = $billing->webhookRecentEvents(5, 'failed');
$latestWebhookRecord = is_array($webhookSnapshot['latest'] ?? null) ? $webhookSnapshot['latest'] : null;
$latestWebhookStatus = (string) ($latestWebhookRecord['status'] ?? '');
$latestWebhookType = (string) ($latestWebhookRecord['type'] ?? '');
$latestWebhookError = (string) ($latestWebhookRecord['last_error'] ?? '');
$latestWebhookUpdatedAt = format_datetime(
    is_string($latestWebhookRecord['updated_at'] ?? null) ? (string) $latestWebhookRecord['updated_at'] : null,
    'Not available'
);
$webhookLatestLabel = match ($latestWebhookStatus) {
    'processed' => 'Latest webhook processed successfully',
    'failed' => 'Latest webhook failed',
    'processing' => 'A webhook event is still marked as processing',
    default => 'No webhook events recorded yet',
};
$databaseVersionStatus = $databaseVersions->status();
$databaseLatestMigration = is_array($databaseVersionStatus['latest_migration'] ?? null) ? $databaseVersionStatus['latest_migration'] : null;
$databaseLatestMigrationAppliedAt = format_datetime(
    is_string($databaseLatestMigration['applied_at'] ?? null) ? (string) $databaseLatestMigration['applied_at'] : null,
    'Not available'
);
$databaseSchemaLabel = !$databaseVersionStatus['db_connected']
    ? 'Unavailable'
    : (!$databaseVersionStatus['tracking_ready']
        ? 'Tracking missing'
        : (($databaseVersionStatus['db_version'] ?? '') !== '' ? (string) $databaseVersionStatus['db_version'] : 'Unknown'));
$recentAdminActivity = $auditLogs->recent(6);
$internalApiHealthy = $dbReady && is_file(ROOT_PATH . '/api/videos.php') && is_file(ROOT_PATH . '/api/session.php');
$wasabiConfigured = $wasabiEnabled
    && trim((string) ($settings['wasabi_endpoint'] ?? '')) !== ''
    && trim((string) ($settings['wasabi_region'] ?? '')) !== ''
    && trim((string) ($settings['wasabi_bucket'] ?? '')) !== ''
    && trim((string) config('storage.wasabi_access_key', '')) !== ''
    && trim((string) config('storage.wasabi_secret_key', '')) !== '';
$storageHealthLabel = $wasabiEnabled
    ? ($wasabiConfigured ? 'Wasabi ready' : 'Wasabi incomplete')
    : 'Local storage';
$billingHealthLabel = $billingConfigured
    ? ($webhookConfigured ? 'Checkout + webhook ready' : 'Checkout ready, webhook pending')
    : 'Billing not configured';

if ((string) ($_GET['export'] ?? '') === 'backup') {
    $payload = $backupService->buildPayload();
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $backupService->filename() . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($screen === 'settings' && (string) ($_GET['export'] ?? '') === 'catalog_json') {
    $payload = $exportService->buildCatalogPayload($videoRepository->exportForAdmin(), []);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $exportService->filename('catalog', 'json') . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($screen === 'settings' && (string) ($_GET['export'] ?? '') === 'catalog_csv') {
    $streamCsv($exportService->filename('catalog', 'csv'), $exportService->catalogCsvRows($videoRepository->exportForAdmin()));
    exit;
}

if ($screen === 'settings' && (string) ($_GET['export'] ?? '') === 'users_json') {
    $payload = $exportService->buildUsersPayload($usersRepository->exportAll(), '');
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $exportService->filename('users', 'json') . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($screen === 'settings' && (string) ($_GET['export'] ?? '') === 'users_csv') {
    $streamCsv($exportService->filename('users', 'csv'), $exportService->usersCsvRows($usersRepository->exportAll()));
    exit;
}

if ($screen === 'library' && (string) ($_GET['export'] ?? '') === 'catalog_json') {
    $filters = [
        'search' => $librarySearch,
        'status' => $libraryStatus,
        'source_type' => $librarySource,
        'storage_provider' => $libraryStorage,
    ];
    $payload = $exportService->buildCatalogPayload($videoRepository->exportForAdmin($filters), array_filter($filters, static fn (string $value): bool => $value !== ''));
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $exportService->filename('catalog-filtered', 'json') . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($screen === 'library' && (string) ($_GET['export'] ?? '') === 'catalog_csv') {
    $filters = [
        'search' => $librarySearch,
        'status' => $libraryStatus,
        'source_type' => $librarySource,
        'storage_provider' => $libraryStorage,
    ];
    $streamCsv(
        $exportService->filename('catalog-filtered', 'csv'),
        $exportService->catalogCsvRows($videoRepository->exportForAdmin($filters))
    );
    exit;
}

if ($screen === 'users' && (string) ($_GET['export'] ?? '') === 'users_json') {
    $payload = $exportService->buildUsersPayload($usersRepository->exportAll($userSearch), $userSearch);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $exportService->filename('users-filtered', 'json') . '"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($screen === 'users' && (string) ($_GET['export'] ?? '') === 'users_csv') {
    $streamCsv(
        $exportService->filename('users-filtered', 'csv'),
        $exportService->usersCsvRows($usersRepository->exportAll($userSearch))
    );
    exit;
}

if ($screen === 'activity' && (string) ($_GET['export'] ?? '') === 'csv') {
    $exportItems = $auditLogs->filtered($activityFilters, 2000);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="videw-activity-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'wb');

    if (is_resource($output)) {
        fputcsv($output, ['id', 'action', 'target_type', 'target_id', 'summary', 'actor_name', 'created_at']);

        foreach ($exportItems as $item) {
            fputcsv($output, [
                $item['id'] ?? '',
                $item['action'] ?? '',
                $item['target_type'] ?? '',
                $item['target_id'] ?? '',
                $item['summary'] ?? '',
                $item['actor_name'] ?? '',
                $item['created_at'] ?? '',
            ]);
        }

        fclose($output);
    }

    exit;
}

$flashError = flash('error');
$flashSuccess = flash('success');
$screenMeta = [
    'overview' => [
        'eyebrow' => 'ADMIN',
        'title' => 'Control panel.',
        'copy' => 'Each screen handles one part of running the site.',
        'primary' => ['label' => 'Open storage', 'href' => $screenUrl('storage')],
        'secondary' => ['label' => 'New video', 'href' => $screenUrl('publish')],
    ],
    'analytics' => [
        'eyebrow' => 'ANALYTICS',
        'title' => 'Platform traffic and content performance.',
        'copy' => 'Follow total traffic, trend lines, top videos, and which creators are pulling the most attention.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open library', 'href' => $screenUrl('library')],
    ],
    'storage' => [
        'eyebrow' => 'STORAGE',
        'title' => 'Upload and delivery settings.',
        'copy' => 'Choose where uploads go and how protected playback is delivered.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open library', 'href' => $screenUrl('library')],
    ],
    'billing' => [
        'eyebrow' => 'BILLING',
        'title' => 'Premium subscription settings.',
        'copy' => 'Set up Premium pricing, payments, and member access from one place.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open users', 'href' => $screenUrl('users')],
    ],
    'publish' => [
        'eyebrow' => 'PUBLISH',
        'title' => 'Create a new video.',
        'copy' => 'Add a new video from an upload or a supported link.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open library', 'href' => $screenUrl('library')],
    ],
    'library' => [
        'eyebrow' => 'LIBRARY',
        'title' => 'Latest uploads and source status.',
        'copy' => 'Review what is live, what is featured, and which delivery method each item is using.',
        'primary' => ['label' => 'Publish new video', 'href' => $screenUrl('publish')],
        'secondary' => ['label' => 'Storage settings', 'href' => $screenUrl('storage')],
    ],
    'moderation' => [
        'eyebrow' => 'MODERATION',
        'title' => 'Review video status.',
        'copy' => 'Move items between draft, approved, and flagged with admin notes.',
        'primary' => ['label' => 'Open library', 'href' => $screenUrl('library')],
        'secondary' => ['label' => 'Open activity', 'href' => $screenUrl('activity')],
    ],
    'creator_requests' => [
        'eyebrow' => 'CREATORS',
        'title' => 'Review creator requests.',
        'copy' => 'Approve or reject creator applications and decide who gets studio access.',
        'primary' => ['label' => 'Open users', 'href' => $screenUrl('users')],
        'secondary' => ['label' => 'Open activity', 'href' => $screenUrl('activity')],
    ],
    'users' => [
        'eyebrow' => 'USERS',
        'title' => 'Manage roles and account status.',
        'copy' => 'Promote creators, keep admins controlled, and suspend accounts when needed.',
        'primary' => ['label' => 'Open settings', 'href' => $screenUrl('settings')],
        'secondary' => ['label' => 'Open legal', 'href' => $screenUrl('legal')],
    ],
    'settings' => [
        'eyebrow' => 'SETTINGS',
        'title' => 'General app settings.',
        'copy' => 'Update the public name, support details, links, and timezone.',
        'primary' => ['label' => 'Open ads', 'href' => $screenUrl('ads')],
        'secondary' => ['label' => 'Open copy', 'href' => $screenUrl('copy')],
    ],
    'ads' => [
        'eyebrow' => 'ADS',
        'title' => 'Ad slots and sponsored placements.',
        'copy' => 'Manage image, script, and text ads across the public site. Premium members never see these placements.',
        'primary' => ['label' => 'Open copy', 'href' => $screenUrl('copy')],
        'secondary' => ['label' => 'Open legal', 'href' => $screenUrl('legal')],
    ],
    'copy' => [
        'eyebrow' => 'COPY',
        'title' => 'Public text and messaging.',
        'copy' => 'Edit the visible public-facing text across the site from one screen.',
        'primary' => ['label' => 'Open settings', 'href' => $screenUrl('settings')],
        'secondary' => ['label' => 'Open legal', 'href' => $screenUrl('legal')],
    ],
    'legal' => [
        'eyebrow' => 'LEGAL',
        'title' => 'Footer, policies, and cookie notice.',
        'copy' => 'Control the public rules page, policy text, cookie copy, and footer links from one screen.',
        'primary' => ['label' => 'Open settings', 'href' => $screenUrl('settings')],
        'secondary' => ['label' => 'Preview rules page', 'href' => base_url('rules.php')],
    ],
    'activity' => [
        'eyebrow' => 'ACTIVITY',
        'title' => 'Recent admin actions.',
        'copy' => 'Review the latest changes made across videos, users, and settings.',
        'primary' => ['label' => 'Open moderation', 'href' => $screenUrl('moderation')],
        'secondary' => ['label' => 'Open users', 'href' => $screenUrl('users')],
    ],
];
$screenLabels = [
    'overview' => 'Overview',
    'analytics' => 'Analytics',
    'storage' => 'Storage',
    'billing' => 'Billing',
    'publish' => 'Publish',
    'library' => 'Library',
    'moderation' => 'Moderation',
    'creator_requests' => 'Creator requests',
    'users' => 'Users',
    'settings' => 'Settings',
    'ads' => 'Ads',
    'copy' => 'Copy',
    'legal' => 'Legal',
    'activity' => 'Activity',
];
$adminNavGroups = [
    'Control center' => ['overview', 'analytics'],
    'Content' => ['publish', 'library', 'moderation'],
    'Members and revenue' => ['creator_requests', 'users', 'billing'],
    'Site' => ['settings', 'ads', 'copy', 'legal'],
    'System' => ['storage', 'activity'],
];
$currentScreen = $screenMeta[$screen];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="<?= e(trim(page_lock_class('admin-layout-page', false))); ?>">
    <div class="legal-bar">
        <span>Admin workspace</span>
        <span><?= e($screenLabels[$screen] ?? 'Overview'); ?></span>
        <span><?= e($dbReady ? (($databaseVersionStatus['db_version'] ?? '') !== '' ? 'DB ' . (string) $databaseVersionStatus['db_version'] : 'Database ready') : 'Database pending'); ?></span>
    </div>
    <header class="site-header admin-topbar">
        <div class="admin-topbar__brand">
            <a class="shell-brand" href="<?= e(base_url()); ?>">
                <span class="shell-brand__icon"></span>
                <span class="shell-brand__wordmark">
                    <?= e(config('app.name')); ?>
                    <?php if (brand_title() !== ''): ?>
                        <small><?= e(brand_title()); ?></small>
                    <?php endif; ?>
                </span>
            </a>
            <span class="pill pill--muted">Admin</span>
        </div>
        <nav class="site-nav admin-topbar__nav">
            <a href="<?= e(base_url()); ?>">Home</a>
            <a href="<?= e(base_url('browse.php')); ?>">Browse</a>
            <a href="<?= e(base_url('studio.php')); ?>">Studio</a>
            <a href="<?= e(base_url('support.php')); ?>">Support</a>
        </nav>
        <div class="site-nav__actions admin-topbar__actions">
            <a class="button button--ghost" href="<?= e(base_url('account.php')); ?>">My account</a>
            <a class="button button--ghost" href="<?= e(base_url()); ?>">View site</a>
            <?= logout_button('Log out'); ?>
        </div>
    </header>

    <main class="page-shell admin-shell">
        <?php if ($flashError): ?>
            <div class="flash flash--error"><?= e((string) $flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
        <?php endif; ?>

        <div class="admin-layout">
            <aside class="admin-sidebar">
                <div class="admin-sidebar__intro">
                    <span class="eyebrow">ADMIN</span>
                    <strong><?= e(config('app.name')); ?></strong>
                    <p>Run content, members, billing, and site settings from one workspace.</p>
                </div>

                <div class="admin-sidebar__utility">
                    <a class="button" href="<?= e($screenUrl('publish')); ?>">New video</a>
                    <a class="button button--ghost" href="<?= e($screenUrl('library')); ?>">Open library</a>
                </div>

                <?php if (!$dbReady): ?>
                    <div class="notice-card">
                        <strong>Setup still in progress</strong>
                        <p>Publishing and user actions will work fully once the site database is available.</p>
                    </div>
                <?php endif; ?>
                <?php if ($dbReady && !($databaseVersionStatus['tracking_ready'] ?? false)): ?>
                    <div class="notice-card">
                        <strong>Schema tracking missing</strong>
                        <p>Apply the upgrade SQL files in <code>updates/1.0.3/sql/</code> to enable database version tracking on this install.</p>
                    </div>
                <?php endif; ?>
                <?php if ($dbReady && ($databaseVersionStatus['upgrade_required'] ?? false)): ?>
                    <div class="notice-card">
                        <strong>Database upgrade pending</strong>
                        <p><?= e((string) ($databaseVersionStatus['message'] ?? 'Apply the pending upgrade SQL files.')); ?></p>
                    </div>
                <?php endif; ?>

                <div class="admin-sidebar__status">
                    <article class="mini-stat">
                        <span>Upload driver</span>
                        <strong><?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Library</span>
                        <strong><?= e((string) $adminStats['total']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Delivery</span>
                        <strong><?= $privateDelivery ? 'Signed' : 'Public'; ?></strong>
                    </article>
                </div>

                <?php foreach ($adminNavGroups as $groupTitle => $items): ?>
                    <section class="admin-sidebar__group">
                        <span class="admin-sidebar__group-title"><?= e($groupTitle); ?></span>
                        <div class="admin-sidebar__links">
                            <?php foreach ($items as $key): ?>
                                <a class="<?= $screen === $key ? 'admin-sidebar__link is-active' : 'admin-sidebar__link'; ?>" href="<?= e($screenUrl($key)); ?>">
                                    <span><?= e($screenLabels[$key] ?? ucfirst($key)); ?></span>
                                    <?php if ($screen === $key): ?>
                                        <span class="pill">Open</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </aside>

            <div class="admin-main">
                <section class="admin-page-header">
                    <div class="admin-page-header__copy">
                        <span class="eyebrow"><?= e($currentScreen['eyebrow']); ?></span>
                        <h1><?= e($currentScreen['title']); ?></h1>
                        <p><?= e($currentScreen['copy']); ?></p>
                    </div>
                    <div class="admin-page-header__actions">
                        <a class="button" href="<?= e($currentScreen['primary']['href']); ?>"><?= e($currentScreen['primary']['label']); ?></a>
                        <a class="button button--ghost" href="<?= e($currentScreen['secondary']['href']); ?>"><?= e($currentScreen['secondary']['label']); ?></a>
                    </div>
                </section>

        <?php if ($screen === 'overview'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">OVERVIEW</span>
                        <h2>Platform KPIs</h2>
                    </div>
                    <p>Start here for catalog, member, billing, and operations visibility.</p>
                </div>
                <div class="admin-kpi-grid">
                    <article class="mini-stat admin-kpi-card">
                        <span>Videos</span>
                        <strong><?= e((string) $adminStats['total']); ?></strong>
                        <small><?= e((string) $adminStats['approved']); ?> live</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Draft queue</span>
                        <strong><?= e((string) $adminStats['draft']); ?></strong>
                        <small>Waiting for review</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Creators</span>
                        <strong><?= e((string) $userStats['creators']); ?></strong>
                        <small><?= e((string) $creatorRequestStats['pending']); ?> requests pending</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Members</span>
                        <strong><?= e((string) $userStats['users']); ?></strong>
                        <small><?= e((string) ($userStats['premium'] ?? 0)); ?> premium</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Premium videos</span>
                        <strong><?= e((string) $premiumVideoCount); ?></strong>
                        <small><?= e((string) $freeVideoCount); ?> free</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Featured</span>
                        <strong><?= e((string) $featuredCount); ?></strong>
                        <small>Homepage and curated spots</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Active ads</span>
                        <strong><?= e((string) ($adStats['active'] ?? 0)); ?></strong>
                        <small><?= e((string) ($adStats['configured'] ?? 0)); ?> configured slots</small>
                    </article>
                    <article class="mini-stat admin-kpi-card">
                        <span>Suspended</span>
                        <strong><?= e((string) $userStats['suspended']); ?></strong>
                        <small>Accounts under restriction</small>
                    </article>
                </div>
            </section>

            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">OPERATIONS</span>
                        <h2>Health, billing, and pending work</h2>
                    </div>
                    <p>Use the dashboard to verify service readiness, revenue setup, and what still needs attention.</p>
                </div>
                <div class="admin-overview-shell">
                    <div class="admin-overview-main">
                        <article class="admin-overview-panel">
                            <div class="admin-overview-panel__header">
                                <div>
                                    <span class="eyebrow">HEALTH</span>
                                    <h3>Platform status</h3>
                                </div>
                                <a class="text-link" href="<?= e($screenUrl('activity')); ?>">Open activity</a>
                            </div>
                            <div class="admin-health-grid">
                                <article class="mini-stat admin-health-card">
                                    <span>Internal API</span>
                                    <strong><?= $internalApiHealthy ? 'Healthy' : 'Attention'; ?></strong>
                                    <small><?= $dbReady ? 'Database and core API files available' : 'Database unavailable'; ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Storage</span>
                                    <strong><?= e($wasabiEnabled ? 'Wasabi' : 'Local'); ?></strong>
                                    <small><?= e($storageHealthLabel); ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Wasabi delivery</span>
                                    <strong><?= $privateDelivery ? 'Signed' : 'Public'; ?></strong>
                                    <small><?= e($wasabiBucket !== '' ? $wasabiBucket : 'Bucket not set'); ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Stripe</span>
                                    <strong><?= $billingConfigured ? 'Ready' : 'Pending'; ?></strong>
                                    <small><?= e($webhookConfigured ? $webhookLatestLabel : $billingHealthLabel); ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Database schema</span>
                                    <strong><?= e($databaseSchemaLabel); ?></strong>
                                    <small><?= e((string) ($databaseVersionStatus['message'] ?? '')); ?></small>
                                </article>
                            </div>
                        </article>

                        <article class="admin-overview-panel">
                            <div class="admin-overview-panel__header">
                                <div>
                                    <span class="eyebrow">BILLING</span>
                                    <h3>Revenue setup snapshot</h3>
                                </div>
                                <a class="text-link" href="<?= e($screenUrl('billing')); ?>">Open billing</a>
                            </div>
                            <div class="admin-billing-grid">
                                <article class="mini-stat admin-health-card">
                                    <span>Plan</span>
                                    <strong><?= e((string) $billingSettings['premium_plan_name']); ?></strong>
                                    <small><?= e((string) $billingSettings['premium_price_label']); ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Premium members</span>
                                    <strong><?= e((string) ($userStats['premium'] ?? 0)); ?></strong>
                                    <small>Accounts with paid access</small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Webhook</span>
                                    <strong><?= $webhookConfigured ? 'Connected' : 'Pending'; ?></strong>
                                    <small><?= e($webhookUrl); ?></small>
                                </article>
                                <article class="mini-stat admin-health-card">
                                    <span>Premium catalog</span>
                                    <strong><?= e((string) $premiumVideoCount); ?></strong>
                                    <small><?= e((string) $freeVideoCount); ?> free titles still available</small>
                                </article>
                            </div>
                        </article>
                    </div>

                    <aside class="admin-overview-side">
                        <article class="admin-overview-panel">
                            <div class="admin-overview-panel__header">
                                <div>
                                    <span class="eyebrow">PENDING</span>
                                    <h3>Pending items</h3>
                                </div>
                            </div>
                            <div class="admin-overview-activity">
                                <a class="admin-overview-activity__item" href="<?= e($screenUrl('moderation')); ?>">
                                    <strong>Review moderation queue</strong>
                                    <span><?= e((string) $adminStats['draft']); ?> drafts currently need a decision.</span>
                                </a>
                                <a class="admin-overview-activity__item" href="<?= e($screenUrl('creator_requests')); ?>">
                                    <strong>Review creator applications</strong>
                                    <span><?= e((string) $creatorRequestStats['pending']); ?> creator requests are waiting.</span>
                                </a>
                                <a class="admin-overview-activity__item" href="<?= e($screenUrl('billing')); ?>">
                                    <strong><?= $webhookConfigured ? 'Review billing setup' : 'Finish Stripe setup'; ?></strong>
                                    <span><?= $webhookConfigured ? 'Checkout is configured. Review pricing and account health.' : 'Add the webhook and keys to finish paid access setup.'; ?></span>
                                </a>
                                <a class="admin-overview-activity__item" href="<?= e($screenUrl('ads')); ?>">
                                    <strong><?= (int) ($adStats['active'] ?? 0) > 0 ? 'Review active ads' : 'Set up ad slots'; ?></strong>
                                    <span><?= (int) ($adStats['active'] ?? 0) > 0 ? e((string) ($adStats['active'] ?? 0)) . ' ad slots are currently active.' : 'No ad slots are active yet.'; ?></span>
                                </a>
                                <a class="admin-overview-activity__item" href="<?= e($screenUrl('storage')); ?>">
                                    <strong><?= $wasabiEnabled ? 'Check storage delivery' : 'Storage uses local uploads'; ?></strong>
                                    <span><?= e($storageHealthLabel); ?></span>
                                </a>
                            </div>
                        </article>

                        <article class="admin-overview-panel">
                            <div class="admin-overview-panel__header">
                                <div>
                                    <span class="eyebrow">UPDATES</span>
                                    <h3>Release monitor</h3>
                                </div>
                                <a class="text-link" href="<?= e($screenUrl('settings')); ?>">Open settings</a>
                            </div>
                            <div class="admin-overview-activity">
                                <article class="admin-overview-activity__item">
                                    <strong><?= e($releaseStatusTitle); ?></strong>
                                    <span><?= e($releaseStatusDetail); ?></span>
                                </article>
                                <article class="admin-overview-activity__item">
                                    <strong><?= e((string) ($releaseStatus['release_name'] !== '' ? $releaseStatus['release_name'] : (($releaseStatus['latest_version'] ?? '') !== '' ? 'Latest release ' . $releaseStatus['latest_version'] : 'GitHub release'))); ?></strong>
                                    <span>
                                        Repo <?= e((string) ($releaseStatus['repository'] !== '' ? $releaseStatus['repository'] : 'not configured')); ?>
                                        <?php if ((string) ($releaseStatus['latest_version'] ?? '') !== ''): ?>
                                            | Published <?= e($releasePublishedLabel); ?>
                                        <?php endif; ?>
                                        | Checked <?= e($releaseCheckedLabel); ?>
                                    </span>
                                    <?php if ((string) ($releaseStatus['summary'] ?? '') !== ''): ?>
                                        <span><?= e((string) $releaseStatus['summary']); ?></span>
                                    <?php endif; ?>
                                    <?php if ((string) ($releaseStatus['release_url'] ?? '') !== ''): ?>
                                        <a class="text-link" href="<?= e((string) $releaseStatus['release_url']); ?>" target="_blank" rel="noreferrer">Open release</a>
                                    <?php endif; ?>
                                </article>
                            </div>
                        </article>

                        <article class="admin-overview-panel">
                            <div class="admin-overview-panel__header">
                                <div>
                                    <span class="eyebrow">ACTIVITY</span>
                                    <h3>Latest actions</h3>
                                </div>
                                <a class="text-link" href="<?= e($screenUrl('activity')); ?>">Open full log</a>
                            </div>
                            <div class="admin-overview-activity">
                                <?php foreach ($recentAdminActivity as $item): ?>
                                    <article class="admin-overview-activity__item">
                                        <strong><?= e((string) ($item['summary'] ?? 'Activity')); ?></strong>
                                        <span><?= e((string) (($item['actor_name'] ?? '') !== '' ? $item['actor_name'] : ($item['actor_email'] ?? 'System'))); ?> • <?= e((string) ($item['created_at'] ?? '')); ?></span>
                                    </article>
                                <?php endforeach; ?>
                                <?php if ($recentAdminActivity === []): ?>
                                    <article class="admin-overview-activity__item">
                                        <strong>No recent activity yet</strong>
                                        <span>Admin actions will appear here as soon as the workspace is used.</span>
                                    </article>
                                <?php endif; ?>
                            </div>
                        </article>
                    </aside>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'storage'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">STORAGE</span>
                        <h2>Upload driver and delivery</h2>
                    </div>
                    <p>Choose where uploaded files live and how protected playback is delivered.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_storage">
                        <?= csrf_input('admin'); ?>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Upload driver</h3>
                                <p>Choose where new video and poster files will be saved.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Driver</span>
                                    <select name="upload_driver">
                                        <option value="local" <?= ($settings['upload_driver'] ?? 'local') === 'local' ? 'selected' : ''; ?>>This server</option>
                                        <option value="wasabi" <?= ($settings['upload_driver'] ?? '') === 'wasabi' ? 'selected' : ''; ?>>Wasabi cloud storage</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Folder prefix</span>
                                    <input type="text" name="wasabi_path_prefix" value="<?= e((string) ($settings['wasabi_path_prefix'] ?? 'videw')); ?>" placeholder="videw">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Wasabi connection</h3>
                                <p>Enter the bucket details used for Wasabi uploads.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Wasabi endpoint</span>
                                    <input type="text" name="wasabi_endpoint" value="<?= e((string) ($settings['wasabi_endpoint'] ?? 'https://s3.wasabisys.com')); ?>" placeholder="https://s3.wasabisys.com">
                                </label>
                                <label>
                                    <span>Region</span>
                                    <input type="text" name="wasabi_region" value="<?= e((string) ($settings['wasabi_region'] ?? 'us-east-1')); ?>" placeholder="us-east-1">
                                </label>
                                <label>
                                    <span>Bucket</span>
                                    <input type="text" name="wasabi_bucket" value="<?= e((string) ($settings['wasabi_bucket'] ?? '')); ?>" placeholder="my-bucket">
                                </label>
                                <label>
                                    <span>Public base URL</span>
                                    <input type="text" name="wasabi_public_base_url" value="<?= e((string) ($settings['wasabi_public_base_url'] ?? '')); ?>" placeholder="https://s3.us-east-1.wasabisys.com/my-bucket">
                                </label>
                                <label>
                                    <span>Access key</span>
                                    <input type="text" name="wasabi_access_key" value="" placeholder="<?= trim((string) ($settings['wasabi_access_key'] ?? '')) !== '' ? 'Leave blank to keep current key' : 'Paste a new access key'; ?>" autocomplete="off">
                                </label>
                                <label>
                                    <span>Secret key</span>
                                    <input type="password" name="wasabi_secret_key" value="" placeholder="<?= trim((string) ($settings['wasabi_secret_key'] ?? '')) !== '' ? 'Leave blank to keep current secret' : 'Paste a new secret key'; ?>" autocomplete="new-password">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Delivery rules</h3>
                                <p>Choose whether Wasabi files stay public or open through time-limited links.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label class="checkbox-line">
                                    <input type="checkbox" name="wasabi_private_bucket" value="1" <?= ($settings['wasabi_private_bucket'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span>Keep the bucket private and use time-limited playback links</span>
                                </label>
                                <div class="admin-empty-slot"></div>
                                <label>
                                    <span>Signed URL TTL (seconds)</span>
                                    <input type="number" min="60" max="604800" name="wasabi_signed_url_ttl_seconds" value="<?= e((string) ($settings['wasabi_signed_url_ttl_seconds'] ?? '900')); ?>">
                                </label>
                                <label>
                                    <span>Multipart threshold (MB)</span>
                                    <input type="number" min="5" name="wasabi_multipart_threshold_mb" value="<?= e((string) ($settings['wasabi_multipart_threshold_mb'] ?? '64')); ?>">
                                </label>
                                <label>
                                    <span>Part size (MB)</span>
                                    <input type="number" min="5" name="wasabi_multipart_part_size_mb" value="<?= e((string) ($settings['wasabi_multipart_part_size_mb'] ?? '16')); ?>">
                                </label>
                            </div>
                        </section>

                        <button class="button" type="submit">Save storage settings</button>
                    </form>

                    <div class="admin-sidebar-stack">
                        <article class="admin-guide">
                            <div class="admin-guide__header">
                                <span class="eyebrow">HOW IT WORKS</span>
                                <h3>Storage flow</h3>
                                <p>A quick guide to how uploads and playback work on the site.</p>
                            </div>
                            <div class="admin-steps">
                                <article class="admin-step">
                                    <strong>This server</strong>
                                    <p>Uploaded files stay on the same hosting account as your site.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Wasabi</strong>
                                    <p>Uploaded files are sent to your Wasabi bucket using the saved connection details.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Private settings</strong>
                                    <p>Connection details are saved privately for your site and do not appear on the public pages.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Multipart</strong>
                                    <p>Large files are uploaded in parts to make big transfers more reliable.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Private playback</strong>
                                    <p>When the bucket is private, videos and posters open through protected time-limited links.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>External link</strong>
                                    <p>You can also publish supported video links without uploading a file.</p>
                                </article>
                            </div>
                        </article>

                        <article class="compliance-card">
                            <h3>Current setup</h3>
                            <p><strong>Driver:</strong> <?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></p>
                            <p><strong>Bucket:</strong> <?= e($wasabiBucket !== '' ? $wasabiBucket : 'not set'); ?></p>
                            <p><strong>Delivery:</strong> <?= $privateDelivery ? 'Signed URLs' : 'Public files'; ?></p>
                            <p><strong>Threshold:</strong> <?= e((string) ($settings['wasabi_multipart_threshold_mb'] ?? '64')); ?> MB</p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'analytics'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">ANALYTICS</span>
                        <h2>Platform performance</h2>
                    </div>
                    <p>Track aggregate traffic, highlight the videos pulling the most attention, and see which creators are currently driving views.</p>
                </div>

                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Total views</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['views_total'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Views in 7 days</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['views_7d'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Views in 30 days</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['views_30d'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Unique viewers in 30 days</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['unique_viewers_30d'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Active videos in 30 days</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['active_videos_30d'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Active creators in 30 days</span>
                        <strong><?= e((string) ($adminAnalyticsOverview['active_creators_30d'] ?? 0)); ?></strong>
                    </article>
                </div>

                <div class="studio-analytics-grid">
                    <article class="compliance-card">
                        <h3>Last 14 days</h3>
                        <?php if ($adminAnalyticsSeries !== []): ?>
                            <div class="creator-trend">
                                <?php foreach ($adminAnalyticsSeries as $point): ?>
                                    <div class="creator-trend__row">
                                        <span><?= e((string) $point['label']); ?></span>
                                        <div class="creator-trend__bar">
                                            <i style="width: <?= e((string) max(6, (int) round(((int) $point['views'] / $adminAnalyticsMaxTrendViews) * 100))); ?>%;"></i>
                                        </div>
                                        <strong><?= e((string) $point['views']); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="form-note">Traffic will appear here after public videos start getting views.</p>
                        <?php endif; ?>
                    </article>

                    <article class="compliance-card">
                        <h3>Top videos</h3>
                        <?php if ($adminAnalyticsTopVideos !== []): ?>
                            <div class="creator-analytics-list">
                                <?php foreach ($adminAnalyticsTopVideos as $video): ?>
                                    <div class="creator-analytics-list__row">
                                        <div>
                                            <strong><?= e((string) $video['title']); ?></strong>
                                            <p>
                                                <?= e((string) (($video['creator_name'] ?? '') !== '' ? $video['creator_name'] : 'Unknown creator')); ?>
                                                / <?= e((string) $video['moderation_label']); ?>
                                                / <?= e((string) $video['access_label']); ?>
                                            </p>
                                        </div>
                                        <div class="creator-analytics-list__stats">
                                            <span><?= e((string) $video['total_views']); ?> total</span>
                                            <span><?= e((string) $video['views_30d']); ?> / 30d</span>
                                            <span><?= e((string) $video['last_view_label']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="form-note">Top-video rankings will appear here after public playback starts generating views.</p>
                        <?php endif; ?>
                    </article>
                </div>

                <div class="admin-screen-grid">
                    <article class="compliance-card">
                        <h3>Top creators</h3>
                        <?php if ($adminAnalyticsTopCreators !== []): ?>
                            <div class="creator-analytics-list">
                                <?php foreach ($adminAnalyticsTopCreators as $creator): ?>
                                    <div class="creator-analytics-list__row">
                                        <div>
                                            <strong><?= e((string) $creator['name']); ?></strong>
                                            <p><?= e((string) $creator['videos_with_views']); ?> videos with recorded views</p>
                                            <?php if ((string) ($creator['channel_url'] ?? '') !== ''): ?>
                                                <a class="text-link" href="<?= e((string) $creator['channel_url']); ?>" target="_blank" rel="noreferrer">Open channel</a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="creator-analytics-list__stats">
                                            <span><?= e((string) $creator['total_views']); ?> total</span>
                                            <span><?= e((string) $creator['views_30d']); ?> / 30d</span>
                                            <span><?= e((string) $creator['last_view_label']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="form-note">Creator rankings will appear here after the platform records channel traffic.</p>
                        <?php endif; ?>
                    </article>

                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>Analytics scope</h3>
                            <p>This screen uses the local `video_views` table, so it reflects first-party traffic recorded by the app.</p>
                            <p>It does not yet include retention, external acquisition source, or revenue attribution metrics.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>Next useful expansions</h3>
                            <p>Add per-video admin drilldown, exportable analytics, and acquisition segmentation when the data model grows.</p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'billing'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">BILLING</span>
                        <h2>Premium plan and payments</h2>
                    </div>
                    <p>Set the Premium offer, connect payments, and control member access.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_billing_settings">
                        <?= csrf_input('admin'); ?>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Payment connection</h3>
                                <p>Add your payment keys here. Leave secret fields empty if you want to keep the current values.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Secret key</span>
                                    <input type="password" name="stripe_secret_key" value="" placeholder="<?= trim((string) $billingSettings['stripe_secret_key']) !== '' ? 'Leave blank to keep current secret key' : 'sk_live_...'; ?>" autocomplete="new-password">
                                </label>
                                <label>
                                    <span>Public key</span>
                                    <input type="text" name="stripe_publishable_key" value="<?= e($billingSettings['stripe_publishable_key']); ?>" placeholder="pk_live_...">
                                </label>
                                <label>
                                    <span>Event signing secret</span>
                                    <input type="password" name="stripe_webhook_secret" value="" placeholder="<?= trim((string) $billingSettings['stripe_webhook_secret']) !== '' ? 'Leave blank to keep the current signing secret' : 'whsec_...'; ?>" autocomplete="new-password">
                                </label>
                                <label>
                                    <span>Premium price ID</span>
                                    <input type="text" name="premium_price_id" value="<?= e($billingSettings['premium_price_id']); ?>" placeholder="price_...">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Public plan copy</h3>
                                <p>This text appears on the public premium page and the account screen.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Plan name</span>
                                    <input type="text" name="premium_plan_name" value="<?= e($billingSettings['premium_plan_name']); ?>" placeholder="Premium">
                                </label>
                                <label>
                                    <span>Price label</span>
                                    <input type="text" name="premium_price_label" value="<?= e($billingSettings['premium_price_label']); ?>" placeholder="$9.99 / month">
                                </label>
                                <label>
                                    <span>Plan copy</span>
                                    <textarea name="premium_plan_copy" rows="5" placeholder="Unlock the full catalog with an active subscription."><?= e($billingSettings['premium_plan_copy']); ?></textarea>
                                </label>
                                <div class="compliance-card">
                                    <h3>Current access split</h3>
                                    <p><strong>Free videos:</strong> <?= e((string) $freeVideoCount); ?></p>
                                    <p><strong>Premium videos:</strong> <?= e((string) $premiumVideoCount); ?></p>
                                    <p><strong>Premium users:</strong> <?= e((string) ($userStats['premium'] ?? 0)); ?></p>
                                </div>
                            </div>
                        </section>

                        <button class="button" type="submit">Save payment settings</button>
                    </form>

                    <div class="admin-sidebar-stack">
                        <article class="admin-guide">
                            <div class="admin-guide__header">
                                <span class="eyebrow">PREMIUM FLOW</span>
                                <h3>How Premium works</h3>
                                <p>This is the path your members follow from free access to Premium.</p>
                            </div>
                            <div class="admin-steps">
                                <article class="admin-step">
                                    <strong>1. Create the plan</strong>
                                    <p>Create the recurring Premium plan in Stripe, then paste the price ID here.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>2. Save your keys</strong>
                                    <p>Add your payment keys and plan copy on this screen.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>3. Connect automatic updates</strong>
                                    <p>Add the event URL below in Stripe so successful payments, renewals, and cancellations update member access automatically.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>4. Gate playback</strong>
                                    <p>Videos marked Premium will play only for signed-in members with an active Premium plan.</p>
                                </article>
                            </div>
                        </article>

                        <article class="compliance-card">
                            <h3>Configuration status</h3>
                            <p><strong>Payments ready:</strong> <?= $billingConfigured ? 'Yes' : 'No'; ?></p>
                            <p><strong>Automatic updates ready:</strong> <?= $webhookConfigured ? 'Yes' : 'No'; ?></p>
                            <p><strong>Plans page:</strong> <a class="text-link" href="<?= e(base_url('premium.php')); ?>" target="_blank" rel="noreferrer">Open public premium page</a></p>
                        </article>

                        <article class="compliance-card">
                            <h3>Webhook diagnostics</h3>
                            <p><strong>Latest status:</strong> <?= e($latestWebhookStatus !== '' ? ucfirst($latestWebhookStatus) : 'No events yet'); ?></p>
                            <p><strong>Latest event:</strong> <?= e($latestWebhookType !== '' ? $latestWebhookType : 'Not available'); ?></p>
                            <p><strong>Updated:</strong> <?= e($latestWebhookUpdatedAt); ?></p>
                            <p><strong>Processed:</strong> <?= e((string) ($webhookSnapshot['processed'] ?? 0)); ?> / <strong>Failed:</strong> <?= e((string) ($webhookSnapshot['failed'] ?? 0)); ?></p>
                            <p><strong>Duplicates ignored:</strong> <?= e((string) ($webhookSnapshot['duplicates'] ?? 0)); ?></p>
                            <?php if ($latestWebhookError !== ''): ?>
                                <p class="form-note"><?= e($latestWebhookError); ?></p>
                            <?php endif; ?>
                        </article>

                        <article class="compliance-card">
                            <h3>Failed webhook queue</h3>
                            <?php if ($failedWebhookEvents !== []): ?>
                                <?php foreach ($failedWebhookEvents as $event): ?>
                                    <div class="admin-overview-activity__item">
                                        <strong><?= e((string) (($event['type'] ?? '') !== '' ? $event['type'] : ($event['event_id'] ?? 'Webhook event'))); ?></strong>
                                        <span>
                                            <?= e((string) ($event['event_id'] ?? '')); ?>
                                            <?php if ((string) ($event['updated_at'] ?? '') !== ''): ?>
                                                | Updated <?= e(format_datetime((string) $event['updated_at'], 'Not available')); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            Failures <?= e((string) ((int) ($event['failure_count'] ?? 0))); ?>
                                            | Retries <?= e((string) ((int) ($event['retry_count'] ?? 0))); ?>
                                        </span>
                                        <?php if ((string) ($event['last_error'] ?? '') !== ''): ?>
                                            <span><?= e((string) $event['last_error']); ?></span>
                                        <?php endif; ?>
                                        <form method="post">
                                            <input type="hidden" name="action" value="retry_billing_webhook">
                                            <input type="hidden" name="event_id" value="<?= e((string) ($event['event_id'] ?? '')); ?>">
                                            <?= csrf_input('admin'); ?>
                                            <button class="button button--ghost" type="submit">Retry event</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No failed webhook events are waiting for manual retry.</p>
                            <?php endif; ?>
                        </article>

                        <article class="compliance-card">
                            <h3>Recent webhook history</h3>
                            <?php if ($recentWebhookEvents !== []): ?>
                                <?php foreach ($recentWebhookEvents as $event): ?>
                                    <div class="admin-overview-activity__item">
                                        <strong><?= e((string) (($event['type'] ?? '') !== '' ? $event['type'] : ($event['event_id'] ?? 'Webhook event'))); ?></strong>
                                        <span>
                                            Status <?= e(ucfirst((string) ($event['status'] ?? 'unknown'))); ?>
                                            <?php if ((string) ($event['updated_at'] ?? '') !== ''): ?>
                                                | Updated <?= e(format_datetime((string) $event['updated_at'], 'Not available')); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            Duplicates <?= e((string) ((int) ($event['duplicate_count'] ?? 0))); ?>
                                            | Retries <?= e((string) ((int) ($event['retry_count'] ?? 0))); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No webhook history has been recorded yet.</p>
                            <?php endif; ?>
                        </article>

                        <article class="compliance-card">
                            <h3>Automatic updates URL</h3>
                            <p>Add this URL in Stripe so payments and renewals update accounts automatically:</p>
                            <code><?= e($webhookUrl); ?></code>
                            <p class="form-note">Use your real site URL here.</p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'publish'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">PUBLISH</span>
                        <h2>New video form</h2>
                    </div>
                    <p>Use files or supported external sources.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                        <input type="hidden" name="action" value="publish_video">
                        <?= csrf_input('admin'); ?>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Basic details</h3>
                                <p>Start with the title, creator, category, and short description.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Title</span>
                                    <input type="text" name="title" value="<?= e(old('title')); ?>" required>
                                </label>
                                <label>
                                    <span>Creator</span>
                                    <input type="text" name="creator_name" value="<?= e(old('creator_name')); ?>" required>
                                </label>
                                <label>
                                    <span>Category</span>
                                    <input type="text" name="category" value="<?= e(old('category')); ?>" required>
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
                                <h3>Visibility</h3>
                                <p>Choose who can watch the item and whether it should appear on the home page.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Access</span>
                                    <select name="access_level">
                                        <option value="free" <?= old('access_level', 'free') === 'free' ? 'selected' : ''; ?>>Free</option>
                                        <option value="premium" <?= old('access_level') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                    </select>
                                </label>
                                <label class="checkbox-line">
                                    <input type="checkbox" name="is_featured" value="1" <?= old('is_featured') === '1' ? 'checked' : ''; ?>>
                                    <span>Show on home</span>
                                </label>
                                <label>
                                    <span>Moderation status</span>
                                    <select name="moderation_status">
                                        <option value="draft" <?= old('moderation_status', 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="approved" <?= old('moderation_status') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="flagged" <?= old('moderation_status') === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Moderation reason</span>
                                    <select name="moderation_reason">
                                        <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                            <option value="<?= e($value); ?>" <?= old('moderation_reason') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <label>
                                <span>Moderation notes</span>
                                <textarea name="moderation_notes" rows="4" placeholder="Optional internal notes"><?= e(old('moderation_notes')); ?></textarea>
                            </label>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Media source</h3>
                                <p>Start by choosing how you want to add the video and the poster.</p>
                                <p>Current server upload limits: video/poster file max <?= e(ini_size_label('upload_max_filesize')); ?>, full request max <?= e(ini_size_label('post_max_size')); ?>.</p>
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
                                    </label>
                                </div>
                                <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="<?= old('poster_source_mode') === 'url' ? '' : 'display:none;'; ?>">
                                    <label>
                                        <span>Poster URL</span>
                                        <input type="url" name="poster_external_url" value="<?= e(old('poster_external_url')); ?>" placeholder="https://...">
                                    </label>
                                </div>
                            </div>
                        </section>

                        <button class="button" type="submit" <?= !$dbReady ? 'disabled' : ''; ?>>Publish video</button>
                    </form>

                    <div class="admin-sidebar-stack">
                        <article class="admin-guide">
                            <div class="admin-guide__header">
                                <span class="eyebrow">CHECKLIST</span>
                                <h3>Before you publish</h3>
                                <p>Keep the form simple and check only the fields that matter for the current source type.</p>
                            </div>
                            <div class="admin-steps">
                                <article class="admin-step">
                                    <strong>File upload</strong>
                                    <p>Use this when the video should be stored on the local server or in Wasabi.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>External URL</strong>
                                    <p>Use this for direct media files or supported embed providers.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Poster</strong>
                                    <p>Choose whether to upload a poster, link to one, or keep the default artwork.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Featured</strong>
                                    <p>Turn this on only for items that should appear in the home spotlight.</p>
                                </article>
                            </div>
                        </article>

                        <article class="compliance-card">
                            <h3>Current defaults</h3>
                            <p><strong>Upload driver:</strong> <?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></p>
                            <p><strong>Playback:</strong> <?= $privateDelivery ? 'Signed URLs' : 'Public delivery'; ?></p>
                            <p><strong>Site data:</strong> <?= $dbReady ? 'Ready' : 'Not connected'; ?></p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'library'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">LIBRARY</span>
                        <h2>Manage videos</h2>
                    </div>
                    <p>Search, edit, feature, moderate, and delete videos.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Total videos</span>
                        <strong><?= e((string) $adminStats['total']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Approved</span>
                        <strong><?= e((string) $adminStats['approved']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Draft</span>
                        <strong><?= e((string) $adminStats['draft']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Flagged</span>
                        <strong><?= e((string) $adminStats['flagged']); ?></strong>
                    </article>
                </div>

                <form method="get" class="admin-toolbar">
                    <input type="hidden" name="screen" value="library">
                    <label>
                        <span>Search</span>
                        <input type="search" name="library_search" value="<?= e($librarySearch); ?>" placeholder="Title, creator, category">
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="library_status">
                            <option value="">All</option>
                            <option value="draft" <?= $libraryStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="approved" <?= $libraryStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="flagged" <?= $libraryStatus === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                        </select>
                    </label>
                    <label>
                        <span>Source</span>
                        <select name="library_source_type">
                            <option value="">All</option>
                            <option value="upload" <?= $librarySource === 'upload' ? 'selected' : ''; ?>>Upload</option>
                            <option value="external_file" <?= $librarySource === 'external_file' ? 'selected' : ''; ?>>External file</option>
                            <option value="embed" <?= $librarySource === 'embed' ? 'selected' : ''; ?>>Embed</option>
                        </select>
                    </label>
                    <label>
                        <span>Storage</span>
                        <select name="library_storage">
                            <option value="">All</option>
                            <option value="local" <?= $libraryStorage === 'local' ? 'selected' : ''; ?>>Local</option>
                            <option value="wasabi" <?= $libraryStorage === 'wasabi' ? 'selected' : ''; ?>>Wasabi</option>
                            <option value="external" <?= $libraryStorage === 'external' ? 'selected' : ''; ?>>External</option>
                        </select>
                    </label>
                    <button class="button" type="submit">Filter</button>
                    <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_json', 'library_page' => null])); ?>">Export JSON</a>
                    <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_csv', 'library_page' => null])); ?>">Export CSV</a>
                </form>
                <form method="post" id="library-bulk-form" class="admin-toolbar admin-toolbar--bulk">
                    <input type="hidden" name="action" value="bulk_library_action">
                    <?= csrf_input('admin'); ?>
                    <label>
                        <span>Bulk action</span>
                        <select name="bulk_action">
                            <option value="approve">Approve selected</option>
                            <option value="draft">Move to draft</option>
                            <option value="flagged">Flag selected</option>
                            <option value="feature">Feature selected</option>
                            <option value="unfeature">Unfeature selected</option>
                            <option value="delete">Delete selected</option>
                        </select>
                    </label>
                    <button class="button" type="submit">Run bulk action</button>
                    <p class="form-note">Select videos below to apply the action.</p>
                </form>

                <?php if ($editingVideo): ?>
                    <div class="admin-screen-grid">
                        <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                            <input type="hidden" name="action" value="update_video">
                            <input type="hidden" name="video_id" value="<?= e((string) $editingVideo['id']); ?>">
                            <?= csrf_input('admin'); ?>
                            <section class="admin-form-section">
                                <div class="admin-form-section__header">
                                    <h3>Edit video</h3>
                                    <p>Update metadata, source and moderation for this item.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Title</span>
                                        <input type="text" name="title" value="<?= e((string) $editingVideo['title']); ?>" required>
                                    </label>
                                    <label>
                                        <span>Creator</span>
                                        <input type="text" name="creator_name" value="<?= e((string) $editingVideo['creator_name']); ?>" required>
                                    </label>
                                    <label>
                                        <span>Category</span>
                                        <input type="text" name="category" value="<?= e((string) $editingVideo['category']); ?>" required>
                                    </label>
                                    <label>
                                        <span>Length (minutes)</span>
                                        <input type="number" min="0" name="duration_minutes" value="<?= e((string) $editingVideo['duration_minutes']); ?>">
                                    </label>
                                    <label>
                                        <span>Access</span>
                                        <select name="access_level">
                                            <?php foreach (['free' => 'Free', 'premium' => 'Premium'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) $editingVideo['access_level'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Source</span>
                                        <select name="source_mode" data-media-switch="video">
                                            <option value="" <?= !isset($_POST['source_mode']) ? 'selected' : ''; ?>>Keep current video</option>
                                            <option value="file" <?= (string) ($_POST['source_mode'] ?? '') === 'file' ? 'selected' : ''; ?>>Upload a new file</option>
                                            <option value="url" <?= (string) ($_POST['source_mode'] ?? '') === 'url' ? 'selected' : ''; ?>>Use a video URL</option>
                                        </select>
                                    </label>
                                </div>
                                <label>
                                    <span>Description</span>
                                    <textarea name="synopsis" rows="4" required><?= e((string) $editingVideo['synopsis']); ?></textarea>
                                </label>
                            </section>
                            <section class="admin-form-section">
                                <div class="admin-form-section__header">
                                    <h3>State</h3>
                                    <p>Control moderation and homepage placement.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Moderation status</span>
                                        <select name="moderation_status">
                                            <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) $editingVideo['moderation_status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Moderation reason</span>
                                        <select name="moderation_reason">
                                            <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) ($editingVideo['moderation_reason'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="is_featured" value="1" <?= (int) $editingVideo['is_featured'] === 1 ? 'checked' : ''; ?>>
                                        <span>Show on home</span>
                                    </label>
                                </div>
                                <label>
                                    <span>Moderation notes</span>
                                    <textarea name="moderation_notes" rows="4"><?= e((string) $editingVideo['moderation_notes']); ?></textarea>
                                </label>
                            </section>
                            <section class="admin-form-section">
                                <div class="admin-form-section__header">
                                    <h3>Replace media</h3>
                                    <p>Choose what you want to replace. If you keep the current option selected, nothing changes.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>Poster source</span>
                                        <select name="poster_source_mode" data-media-switch="poster">
                                            <option value="" <?= !isset($_POST['poster_source_mode']) ? 'selected' : ''; ?>>Keep current poster</option>
                                            <option value="upload" <?= (string) ($_POST['poster_source_mode'] ?? '') === 'upload' ? 'selected' : ''; ?>>Upload a new image</option>
                                            <option value="url" <?= (string) ($_POST['poster_source_mode'] ?? '') === 'url' ? 'selected' : ''; ?>>Use a poster URL</option>
                                        </select>
                                    </label>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="remove_poster" value="1">
                                        <span>Remove current poster and use the fallback art</span>
                                    </label>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="url" style="<?= (string) ($_POST['source_mode'] ?? '') === 'url' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Video URL</span>
                                            <input type="url" name="external_url" value="<?= e((string) ($_POST['external_url'] ?? ($editingVideo['original_source_url'] ?? ''))); ?>" placeholder="https://...">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="file" style="<?= (string) ($_POST['source_mode'] ?? '') === 'file' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Video file</span>
                                            <input type="file" name="video_file" accept="video/*">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="upload" style="<?= (string) ($_POST['poster_source_mode'] ?? '') === 'upload' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Poster image</span>
                                            <input type="file" name="poster_file" accept="image/*">
                                        </label>
                                    </div>
                                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="<?= (string) ($_POST['poster_source_mode'] ?? '') === 'url' ? '' : 'display:none;'; ?>">
                                        <label>
                                            <span>Poster URL</span>
                                            <input type="url" name="poster_external_url" value="<?= e((string) ($_POST['poster_external_url'] ?? (empty($editingVideo['poster_path']) ? ($editingVideo['stored_poster_url'] ?? '') : ''))); ?>" placeholder="https://...">
                                        </label>
                                    </div>
                                </div>
                            </section>
                            <button class="button" type="submit">Save changes</button>
                        </form>
                        <div class="admin-sidebar-stack">
                            <article class="compliance-card">
                                <h3>Editing</h3>
                                <p><strong>Source:</strong> <?= e((string) $editingVideo['source_type']); ?></p>
                                <p><strong>Storage:</strong> <?= e((string) $editingVideo['storage_provider']); ?></p>
                                <p><strong>Status:</strong> <?= e((string) $editingVideo['moderation_label']); ?></p>
                                <p><strong>Reason:</strong> <?= e((string) ($editingVideo['moderation_reason_label'] ?? moderation_reason_label(''))); ?></p>
                                <p><strong>Published:</strong> <?= e((string) $editingVideo['published_label']); ?></p>
                            </article>
                            <article class="compliance-card">
                                <h3>Moderation history</h3>
                                <?php if ($editingVideoHistory !== []): ?>
                                    <div class="creator-analytics-list">
                                        <?php foreach ($editingVideoHistory as $historyItem): ?>
                                            <?php $historyMeta = json_decode((string) ($historyItem['metadata_json'] ?? ''), true); ?>
                                            <div class="creator-analytics-list__row">
                                                <div>
                                                    <strong><?= e((string) ($historyItem['summary'] ?? 'History')); ?></strong>
                                                    <p><?= e((string) (($historyItem['actor_name'] ?? '') !== '' ? $historyItem['actor_name'] : ($historyItem['actor_email'] ?? 'System'))); ?></p>
                                                </div>
                                                <div class="creator-analytics-list__stats">
                                                    <?php if (is_array($historyMeta) && !empty($historyMeta['status'])): ?>
                                                        <span><?= e(moderation_label((string) $historyMeta['status'])); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (is_array($historyMeta) && array_key_exists('reason', $historyMeta)): ?>
                                                        <span><?= e(moderation_reason_label((string) ($historyMeta['reason'] ?? ''))); ?></span>
                                                    <?php endif; ?>
                                                    <span><?= e(format_datetime((string) ($historyItem['created_at'] ?? null))); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="form-note">Moderation history will appear here after the item is reviewed or updated.</p>
                                <?php endif; ?>
                            </article>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($libraryVideos === []): ?>
                    <div class="notice-card">
                        <strong>No videos found</strong>
                        <p>Adjust the filters or publish a new item.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-library-grid">
                        <?php foreach ($libraryVideos as $video): ?>
                            <article class="admin-library-card">
                                <a class="admin-library-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                                    <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                    <div class="admin-library-card__overlay">
                                        <div class="admin-library-card__badges">
                                            <span class="pill"><?= e((string) $video['storage_provider']); ?></span>
                                            <span class="pill pill--muted"><?= e((string) $video['source_type']); ?></span>
                                        </div>
                                        <span class="admin-library-card__duration"><?= e((string) $video['duration_label']); ?></span>
                                    </div>
                                </a>
                                <div class="admin-library-card__body">
                                    <div class="admin-library-card__header">
                                        <label class="bulk-select admin-library-card__select">
                                            <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="library-bulk-form">
                                            <span>Select</span>
                                        </label>
                                        <a class="text-link" href="<?= e(base_url('admin.php?screen=library&edit=' . urlencode((string) $video['id']))); ?>">Edit</a>
                                    </div>
                                    <div class="admin-library-card__identity">
                                        <h3><?= e($video['title']); ?></h3>
                                        <div class="admin-library-card__meta">
                                            <span class="pill pill--muted"><?= e((string) $video['moderation_label']); ?></span>
                                            <span class="pill pill--muted"><?= e((string) $video['access_label']); ?></span>
                                            <?php if ((int) ($video['is_featured'] ?? 0) === 1): ?>
                                                <span class="pill">Featured</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="admin-library-card__summary"><?= e($video['synopsis']); ?></p>
                                    <div class="admin-library-card__stats">
                                        <span><strong>Creator</strong> <?= e($video['creator_name']); ?></span>
                                        <span><strong>Category</strong> <?= e((string) $video['category']); ?></span>
                                        <span><strong>Published</strong> <?= e((string) ($video['published_label'] ?? 'No date')); ?></span>
                                    </div>
                                </div>
                                <div class="admin-library-card__aside">
                                    <div class="admin-library-card__actions">
                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle_featured">
                                            <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                            <input type="hidden" name="next_value" value="<?= (int) $video['is_featured'] === 1 ? '0' : '1'; ?>">
                                            <?= csrf_input('admin'); ?>
                                            <button class="button button--ghost" type="submit"><?= (int) $video['is_featured'] === 1 ? 'Unfeature' : 'Feature'; ?></button>
                                        </form>
                                        <a class="button button--ghost" href="<?= e(base_url('admin.php?screen=moderation')); ?>">Moderate</a>
                                        <form method="post">
                                            <input type="hidden" name="action" value="delete_video">
                                            <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                            <?= csrf_input('admin'); ?>
                                            <button class="button button--ghost" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($libraryPagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="pagination">
                            <?php for ($pageNumber = 1; $pageNumber <= (int) $libraryPagination['total_pages']; $pageNumber++): ?>
                                <a class="<?= (int) $libraryPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['library_page' => $pageNumber, 'edit' => null])); ?>"><?= e((string) $pageNumber); ?></a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'moderation'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">MODERATION</span>
                        <h2>Moderation queue</h2>
                    </div>
                    <p>Review video state and leave internal notes.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Draft</span>
                        <strong><?= e((string) $adminStats['draft']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Approved</span>
                        <strong><?= e((string) $adminStats['approved']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Flagged</span>
                        <strong><?= e((string) $adminStats['flagged']); ?></strong>
                    </article>
                </div>
                <div class="admin-screen-nav">
                    <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
                        <a class="<?= $moderationStatusFilter === $value ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['moderation_status' => $value, 'moderation_page' => 1])); ?>"><?= e($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="get" class="admin-toolbar">
                    <input type="hidden" name="screen" value="moderation">
                    <input type="hidden" name="moderation_status" value="<?= e($moderationStatusFilter); ?>">
                    <label>
                        <span>Reason</span>
                        <select name="moderation_reason">
                            <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                <option value="<?= e($value); ?>" <?= $moderationReasonFilter === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button" type="submit">Filter queue</button>
                    <a class="button button--ghost" href="<?= e($queryUrl(['moderation_reason' => null, 'moderation_page' => 1])); ?>">Clear reason filter</a>
                </form>
                <form method="post" id="moderation-bulk-form" class="admin-toolbar admin-toolbar--bulk">
                    <input type="hidden" name="action" value="bulk_moderation_action">
                    <?= csrf_input('admin'); ?>
                    <label>
                        <span>Bulk action</span>
                        <select name="bulk_action">
                            <option value="approve">Approve selected</option>
                            <option value="draft">Move to draft</option>
                            <option value="flagged">Flag selected</option>
                            <option value="delete">Delete selected</option>
                        </select>
                    </label>
                    <label>
                        <span>Reason</span>
                        <select name="bulk_reason">
                            <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                <option value="<?= e($value); ?>"><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Notes</span>
                        <input type="text" name="bulk_notes" placeholder="Optional moderation note">
                    </label>
                    <button class="button" type="submit">Run moderation action</button>
                </form>
                <?php if ($moderationVideos === []): ?>
                    <div class="notice-card">
                        <strong>No items in this queue</strong>
                        <p>Pick another status filter or publish a new draft.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-worklist">
                        <?php foreach ($moderationVideos as $video): ?>
                            <article class="admin-workrow">
                                <label class="bulk-select">
                                    <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="moderation-bulk-form">
                                    <span>Select</span>
                                </label>
                                <div class="admin-workrow__thumb">
                                    <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                                </div>
                                <div class="admin-workrow__main">
                                    <div class="admin-workrow__header">
                                        <div class="meta-row">
                                            <span class="pill"><?= e($video['category']); ?></span>
                                            <span class="pill pill--muted"><?= e((string) $video['moderation_label']); ?></span>
                                            <?php if ((string) ($video['moderation_reason'] ?? '') !== ''): ?>
                                                <span class="pill pill--muted"><?= e((string) $video['moderation_reason_label']); ?></span>
                                            <?php endif; ?>
                                            <span class="pill pill--muted"><?= e((string) $video['access_label']); ?></span>
                                        </div>
                                        <h3><?= e($video['title']); ?></h3>
                                    </div>
                                    <p class="admin-workrow__summary"><?= e($video['creator_name']); ?> / <?= e($video['duration_label']); ?> / <?= e((string) $video['storage_provider']); ?></p>
                                    <div class="admin-workrow__meta">
                                        <span class="form-note">Source: <?= e((string) $video['source_type']); ?></span>
                                        <span class="form-note">Published: <?= e((string) ($video['published_label'] ?? 'No date')); ?></span>
                                    </div>
                                </div>
                                <form method="post" class="admin-workrow__form">
                                    <input type="hidden" name="action" value="moderate_video">
                                    <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                    <?= csrf_input('admin'); ?>
                                    <label>
                                        <span>Status</span>
                                        <select name="moderation_status">
                                            <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) $video['moderation_status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Reason</span>
                                        <select name="moderation_reason">
                                            <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) ($video['moderation_reason'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Notes</span>
                                        <textarea name="moderation_notes" rows="3"><?= e((string) $video['moderation_notes']); ?></textarea>
                                    </label>
                                    <?php $videoHistory = $moderationHistoryByVideo[(int) ($video['id'] ?? 0)] ?? []; ?>
                                    <?php if ($videoHistory !== []): ?>
                                        <div class="creator-analytics-list">
                                            <?php foreach ($videoHistory as $historyItem): ?>
                                                <?php $historyMeta = json_decode((string) ($historyItem['metadata_json'] ?? ''), true); ?>
                                                <div class="creator-analytics-list__row">
                                                    <div>
                                                        <strong><?= e((string) ($historyItem['summary'] ?? 'History')); ?></strong>
                                                        <p><?= e((string) (($historyItem['actor_name'] ?? '') !== '' ? $historyItem['actor_name'] : ($historyItem['actor_email'] ?? 'System'))); ?></p>
                                                    </div>
                                                    <div class="creator-analytics-list__stats">
                                                        <?php if (is_array($historyMeta) && !empty($historyMeta['status'])): ?>
                                                            <span><?= e(moderation_label((string) $historyMeta['status'])); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (is_array($historyMeta) && array_key_exists('reason', $historyMeta)): ?>
                                                            <span><?= e(moderation_reason_label((string) ($historyMeta['reason'] ?? ''))); ?></span>
                                                        <?php endif; ?>
                                                        <span><?= e(format_datetime((string) ($historyItem['created_at'] ?? null))); ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <button class="button" type="submit">Save moderation</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($moderationPagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="pagination">
                            <?php for ($pageNumber = 1; $pageNumber <= (int) $moderationPagination['total_pages']; $pageNumber++): ?>
                                <a class="<?= (int) $moderationPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['moderation_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'creator_requests'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">CREATORS</span>
                        <h2>Creator request queue</h2>
                    </div>
                    <p>Approve or reject requests before creator studio access is unlocked.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Pending</span>
                        <strong><?= e((string) ($creatorRequestStats['pending'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Approved</span>
                        <strong><?= e((string) ($creatorRequestStats['approved'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Rejected</span>
                        <strong><?= e((string) ($creatorRequestStats['rejected'] ?? 0)); ?></strong>
                    </article>
                </div>
                <div class="admin-screen-nav">
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?>
                        <a class="<?= $creatorRequestStatus === $value ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['creator_request_status' => $value, 'creator_requests_page' => 1])); ?>"><?= e($label); ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if ($creatorRequests === []): ?>
                    <div class="notice-card">
                        <strong>No creator requests in this queue</strong>
                        <p>Switch the filter or wait for a new creator application.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-worklist">
                        <?php foreach ($creatorRequests as $request): ?>
                            <article class="admin-workrow">
                                <div class="admin-workrow__main">
                                    <div class="admin-workrow__header">
                                        <div class="meta-row">
                                            <span class="pill"><?= e((string) ($request['status'] ?? 'pending')); ?></span>
                                            <span class="pill pill--muted"><?= e((string) ($request['created_label'] ?? '')); ?></span>
                                        </div>
                                        <h3><?= e((string) ($request['requested_display_name'] ?? '')); ?></h3>
                                    </div>
                                    <p class="admin-workrow__summary"><?= e((string) ($request['user_display_name'] ?? 'Member')); ?> / <?= e((string) ($request['user_email'] ?? '')); ?></p>
                                    <div class="admin-workrow__meta">
                                        <span class="form-note">Channel link: <?= e((string) ($request['requested_slug'] ?? '')); ?></span>
                                    </div>
                                    <?php if (!empty($request['requested_bio'])): ?>
                                        <p class="form-note"><?= e((string) $request['requested_bio']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($request['review_notes'])): ?>
                                        <p class="form-note"><strong>Review notes:</strong> <?= e((string) $request['review_notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="post" class="admin-workrow__form">
                                    <input type="hidden" name="action" value="review_creator_application">
                                    <input type="hidden" name="application_id" value="<?= e((string) $request['id']); ?>">
                                    <?= csrf_input('admin'); ?>
                                    <label>
                                        <span>Decision</span>
                                        <select name="review_status">
                                            <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Keep pending'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) $request['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Notes</span>
                                        <textarea name="review_notes" rows="3"><?= e((string) ($request['review_notes'] ?? '')); ?></textarea>
                                    </label>
                                    <button class="button" type="submit">Save review</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (($creatorRequestsPagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="pagination">
                            <?php for ($pageNumber = 1; $pageNumber <= (int) $creatorRequestsPagination['total_pages']; $pageNumber++): ?>
                                <a class="<?= (int) $creatorRequestsPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['creator_requests_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'users'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">USERS</span>
                        <h2>Accounts and roles</h2>
                    </div>
                    <p>Manage role assignment and account status.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Total users</span>
                        <strong><?= e((string) $userStats['users']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Admins</span>
                        <strong><?= e((string) $userStats['admins']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Creators</span>
                        <strong><?= e((string) $userStats['creators']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Suspended</span>
                        <strong><?= e((string) $userStats['suspended']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>MFA enabled</span>
                        <strong><?= e((string) $userStats['mfa_enabled']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Premium users</span>
                        <strong><?= e((string) ($userStats['premium'] ?? 0)); ?></strong>
                    </article>
                </div>
                <form method="get" class="admin-toolbar">
                    <input type="hidden" name="screen" value="users">
                    <label>
                        <span>Search</span>
                        <input type="search" name="user_search" value="<?= e($userSearch); ?>" placeholder="Name or email">
                    </label>
                    <button class="button" type="submit">Filter</button>
                    <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_json', 'users_page' => null])); ?>">Export JSON</a>
                    <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_csv', 'users_page' => null])); ?>">Export CSV</a>
                </form>
                <?php if ($users === []): ?>
                    <div class="notice-card">
                        <strong>No users found</strong>
                        <p>Try a different search or create a new account from the public register page.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-worklist">
                        <?php foreach ($users as $managedUser): ?>
                            <article class="admin-user-row">
                                <div class="admin-user-row__main">
                                    <div class="admin-user-row__meta">
                                        <span class="pill"><?= e((string) $managedUser['role']); ?></span>
                                        <span class="pill pill--muted"><?= e(user_status_label((string) ($managedUser['status'] ?? 'active'))); ?></span>
                                        <span class="pill"><?= e(account_tier_label((string) ($managedUser['account_tier'] ?? 'free'))); ?></span>
                                        <?php if ((int) ($managedUser['mfa_enabled'] ?? 0) === 1): ?>
                                            <span class="pill">2FA</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3><?= e((string) $managedUser['display_name']); ?></h3>
                                    <p><?= e((string) $managedUser['email']); ?></p>
                                    <?php if (!empty($managedUser['stripe_subscription_status'])): ?>
                                        <p class="form-note">Membership status: <?= e(subscription_status_label((string) $managedUser['stripe_subscription_status'])); ?></p>
                                    <?php endif; ?>
                                    <p class="form-note">Joined <?= e(format_datetime((string) ($managedUser['created_at'] ?? null))); ?><?php if (!empty($managedUser['last_login_at'])): ?> / Last login <?= e(format_datetime((string) $managedUser['last_login_at'])); ?><?php endif; ?></p>
                                </div>
                                <form method="post" class="admin-user-row__form">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" value="<?= e((string) $managedUser['id']); ?>">
                                    <?= csrf_input('admin'); ?>
                                    <label>
                                        <span>Role</span>
                                        <select name="role">
                                            <?php foreach (['member' => 'Member', 'creator' => 'Creator', 'admin' => 'Admin'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) $managedUser['role'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Status</span>
                                        <select name="status">
                                            <?php foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) ($managedUser['status'] ?? 'active') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button class="button" type="submit">Save user</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($usersPagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="pagination">
                            <?php for ($pageNumber = 1; $pageNumber <= (int) $usersPagination['total_pages']; $pageNumber++): ?>
                                <a class="<?= (int) $usersPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['users_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'settings'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">SETTINGS</span>
                        <h2>General application settings</h2>
                    </div>
                    <p>Update the public name, support details, main site links, and optional head scripts.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_app_settings">
                        <?= csrf_input('admin'); ?>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Branding</h3>
                                <p>Control the visible product name and short lockup. Leave Brand title empty to hide the yellow tag.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>App name</span>
                                    <input type="text" name="app_name" value="<?= e($appSettings['app_name']); ?>">
                                </label>
                                <label>
                                    <span>Description</span>
                                    <input type="text" name="app_description" value="<?= e($appSettings['app_description']); ?>">
                                </label>
                                <label>
                                    <span>Brand kicker</span>
                                    <input type="text" name="brand_kicker" value="<?= e($appSettings['brand_kicker']); ?>">
                                </label>
                                <label>
                                    <span>Brand title</span>
                                    <input type="text" name="brand_title" value="<?= e($appSettings['brand_title']); ?>">
                                </label>
                            </div>
                        </section>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Entry notice</h3>
                                <p>Choose whether the 18+ entry warning should appear before visitors continue into the site.</p>
                            </div>
                            <label class="checkbox-line">
                                <input type="checkbox" name="age_gate_enabled" value="1" <?= !empty($appSettings['age_gate_enabled']) ? 'checked' : ''; ?>>
                                <span>Show the 18+ entry notice on public pages</span>
                            </label>
                            <p class="form-note">When this is off, the modal warning is hidden. When this is on, visitors will see the entry notice again until they confirm.</p>
                        </section>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>URLs and support</h3>
                                <p>Keep the public URLs and contact points under admin control.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Base URL</span>
                                    <input type="text" name="base_url" value="<?= e($appSettings['base_url']); ?>">
                                </label>
                                <label>
                                    <span>Support email</span>
                                    <input type="email" name="support_email" value="<?= e($appSettings['support_email']); ?>">
                                </label>
                                <label>
                                    <span>Exit URL</span>
                                    <input type="text" name="exit_url" value="<?= e($appSettings['exit_url']); ?>">
                                </label>
                                <label>
                                    <span>Timezone</span>
                                    <input type="text" name="timezone" value="<?= e($appSettings['timezone']); ?>">
                                </label>
                            </div>
                        </section>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Release monitoring</h3>
                                <p>Show update information in the admin overview by comparing this install against GitHub releases.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>GitHub repository</span>
                                    <input type="text" name="github_repository" value="<?= e($appSettings['github_repository']); ?>" placeholder="owner/repository">
                                </label>
                                <label>
                                    <span>Installed version</span>
                                    <input type="text" name="current_version" value="<?= e($appSettings['current_version']); ?>" placeholder="1.0.2">
                                </label>
                            </div>
                            <p class="form-note">Leave the repository empty to disable release checks. Leave Installed version empty to fall back to the top version in <code>CHANGELOG.md</code>.</p>
                        </section>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Public head scripts</h3>
                                <p>Paste analytics, monetization, verification, or tracking scripts that should load inside the <head> of public pages.</p>
                            </div>
                            <label>
                                <span>Scripts rendered on public pages</span>
                                <textarea name="public_head_scripts" rows="10" placeholder="<script async src=&quot;https://www.googletagmanager.com/gtag/js?id=...&quot;></script><?= PHP_EOL; ?><script>/* analytics or adsense code */</script>"><?= e($appSettings['public_head_scripts']); ?></textarea>
                            </label>
                            <p class="form-note">These scripts are injected as-is into public page heads. Admin and installer screens are excluded. Only paste trusted snippets because they run with full access to the public frontend.</p>
                        </section>
                        <button class="button" type="submit">Save site settings</button>
                    </form>
                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>Private site settings</h3>
                            <p>These details are saved privately for this site and are not shown on the public pages.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>18+ notice</h3>
                            <p><strong>Status:</strong> <?= !empty($appSettings['age_gate_enabled']) ? 'Enabled' : 'Disabled'; ?></p>
                            <p>The public entry warning follows this setting.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>Head script status</h3>
                            <p><strong>Custom scripts:</strong> <?= trim($appSettings['public_head_scripts']) !== '' ? 'Enabled' : 'Disabled'; ?></p>
                            <p>Useful for Google Analytics, AdSense, verification tags, pixels, and other monetization or measurement platforms.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>Release monitoring</h3>
                            <p><strong>Status:</strong> <?= e($releaseStatusTitle); ?></p>
                            <p><?= e($releaseStatusDetail); ?></p>
                        </article>
                        <article class="compliance-card">
                            <h3>Backup export</h3>
                            <p>Download a JSON snapshot of app, storage, billing, legal, copy, and ad-slot admin settings.</p>
                            <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'backup'])); ?>">Download backup JSON</a>
                            <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_json'])); ?>">Full catalog JSON</a>
                            <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_csv'])); ?>">Full catalog CSV</a>
                            <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_json'])); ?>">Full users JSON</a>
                            <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_csv'])); ?>">Full users CSV</a>
                            <p class="form-note">User exports include account and creator profile fields, but do not include password hashes, MFA secrets, or backup codes.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>Versioning</h3>
                            <p><strong>App version:</strong> <?= e((string) (($databaseVersionStatus['code_version'] ?? '') !== '' ? $databaseVersionStatus['code_version'] : 'Not set')); ?></p>
                            <p><strong>DB version:</strong> <?= e((string) (($databaseVersionStatus['db_version'] ?? '') !== '' ? $databaseVersionStatus['db_version'] : 'Unknown')); ?></p>
                            <p><strong>Latest migration:</strong> <?= e((string) (($databaseLatestMigration['filename'] ?? '') !== '' ? $databaseLatestMigration['filename'] : 'Not available')); ?></p>
                            <p><strong>Applied:</strong> <?= e($databaseLatestMigrationAppliedAt); ?></p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'ads'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">ADS</span>
                        <h2>Sponsored placements</h2>
                    </div>
                    <p>Manage image, script, and text ads across the public site. Premium members never see these placements.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Total slots</span>
                        <strong><?= e((string) ($adStats['slots'] ?? count($adSlots))); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Configured</span>
                        <strong><?= e((string) ($adStats['configured'] ?? 0)); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Active</span>
                        <strong><?= e((string) ($adStats['active'] ?? 0)); ?></strong>
                    </article>
                </div>
                <div class="copy-editor-controls" data-ad-slot-browser>
                    <label class="copy-editor-controls__label">
                        <span>Ad slot</span>
                        <select data-ad-slot-selector>
                            <?php foreach ($adSlots as $slotKey => $slotDefinition): ?>
                                <option value="<?= e($slotKey); ?>" <?= $activeAdSlot === $slotKey ? 'selected' : ''; ?>><?= e((string) $slotDefinition['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="copy-editor-controls__summary" data-ad-slot-summary>
                        <strong><?= e((string) (($activeAdSlot !== '' && isset($adSlots[$activeAdSlot]['title'])) ? $adSlots[$activeAdSlot]['title'] : 'Ad slot')); ?></strong>
                        <p><?= e((string) (($activeAdSlot !== '' && isset($adSlots[$activeAdSlot]['description'])) ? $adSlots[$activeAdSlot]['description'] : 'Manage one ad slot at a time.')); ?></p>
                    </div>
                </div>
                <div class="admin-ads-grid">
                    <?php foreach ($adSlots as $slotKey => $slotDefinition): ?>
                        <?php
                        $slotAd = $adsBySlot[$slotKey] ?? null;
                        $slotType = (string) ($slotAd['ad_type'] ?? 'placeholder');
                          $slotTypes = is_array($slotDefinition['types'] ?? null) ? $slotDefinition['types'] : ['placeholder', 'image', 'script', 'text'];
                          $slotPreviewUrl = is_array($slotAd)
                              ? resolve_ad_media_asset(
                                  $slotKey,
                                  isset($slotAd['image_url']) ? (string) $slotAd['image_url'] : null,
                                  isset($slotAd['image_path']) ? (string) $slotAd['image_path'] : null,
                                  isset($slotAd['image_storage_provider']) ? (string) $slotAd['image_storage_provider'] : null
                              )
                              : '';
                          $slotPreviewVideoUrl = is_array($slotAd)
                              ? resolve_ad_video_asset(
                                  $slotKey,
                                  isset($slotAd['video_url']) ? (string) $slotAd['video_url'] : null,
                                  isset($slotAd['video_path']) ? (string) $slotAd['video_path'] : null,
                                  isset($slotAd['video_storage_provider']) ? (string) $slotAd['video_storage_provider'] : null
                              )
                            : '';
                        ?>
                        <article class="admin-ad-card" data-ad-slot-panel="<?= e($slotKey); ?>" data-ad-slot-title="<?= e((string) $slotDefinition['title']); ?>" data-ad-slot-description="<?= e((string) $slotDefinition['description'] . ' ' . $slotDefinition['placeholder_text']); ?>"<?= $slotKey === $activeAdSlot ? '' : ' hidden'; ?> style="<?= $slotKey === $activeAdSlot ? '' : 'display:none;'; ?>">
                            <div class="admin-ad-card__preview">
                                <?php if ($slotType === 'image' && $slotPreviewUrl !== ''): ?>
                                    <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--admin-preview">
                                        <span class="ad-slot__eyebrow">Sponsored</span>
                                        <div class="ad-slot__media">
                                            <img src="<?= e($slotPreviewUrl); ?>" alt="<?= e((string) ($slotAd['title'] ?? $slotDefinition['title'])); ?>">
                                        </div>
                                    </div>
                                <?php elseif ($slotType === 'text' && is_array($slotAd) && ((string) ($slotAd['title'] ?? '') !== '' || (string) ($slotAd['body_text'] ?? '') !== '')): ?>
                                    <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--text ad-slot--admin-preview">
                                        <span class="ad-slot__eyebrow">Sponsored</span>
                                        <strong><?= e((string) ($slotAd['title'] ?? $slotDefinition['placeholder_title'])); ?></strong>
                                        <p><?= e((string) ($slotAd['body_text'] ?? 'Text ad preview')); ?></p>
                                    </div>
                                <?php elseif ($slotType === 'script' && is_array($slotAd) && trim((string) ($slotAd['script_code'] ?? '')) !== ''): ?>
                                    <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--placeholder ad-slot--admin-preview">
                                        <span class="ad-slot__eyebrow">Script ad</span>
                                        <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                                        <p><?= e(strlen((string) $slotAd['script_code']) . ' characters saved. Script ads run only on public pages.'); ?></p>
                                    </div>
                                <?php elseif ($slotType === 'video' && is_array($slotAd) && $slotPreviewVideoUrl !== ''): ?>
                                    <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--video ad-slot--admin-preview">
                                        <span class="ad-slot__eyebrow">Video pre-roll</span>
                                        <div class="ad-slot__media">
                                            <video src="<?= e($slotPreviewVideoUrl); ?>" muted playsinline preload="metadata"></video>
                                        </div>
                                        <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                                        <p><?= e('Skip after ' . max(0, (int) ($slotAd['skip_after_seconds'] ?? 5)) . 's'); ?></p>
                                    </div>
                                <?php elseif ($slotType === 'vast' && is_array($slotAd) && trim((string) ($slotAd['vast_tag_url'] ?? '')) !== ''): ?>
                                    <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--placeholder ad-slot--admin-preview">
                                        <span class="ad-slot__eyebrow">VAST pre-roll</span>
                                        <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                                        <p><?= e('VAST tag saved. Skip after ' . max(0, (int) ($slotAd['skip_after_seconds'] ?? 5)) . 's unless the tag defines its own offset.'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?= render_public_ad_slot($slotKey, 'ad-slot--admin-preview'); ?>
                                <?php endif; ?>
                            </div>
                            <form method="post" enctype="multipart/form-data" class="admin-form-shell admin-ad-card__form" data-ad-editor>
                                <input type="hidden" name="action" value="save_ad_slot">
                                <input type="hidden" name="slot_key" value="<?= e($slotKey); ?>">
                                <input type="hidden" name="return_screen" value="ads">
                                <?= csrf_input('admin'); ?>
                                <section class="admin-form-section">
                                    <div class="admin-form-section__header">
                                        <h3><?= e((string) $slotDefinition['title']); ?></h3>
                                        <p><?= e((string) $slotDefinition['description']); ?> <?= e((string) $slotDefinition['placeholder_text']); ?></p>
                                    </div>
                                    <div class="admin-fields admin-fields--two">
                                        <label class="checkbox-line">
                                            <input type="checkbox" name="is_active" value="1" <?= !empty($slotAd['is_active']) ? 'checked' : ''; ?>>
                                            <span>Show this ad slot on public pages</span>
                                        </label>
                                        <label>
                                            <span>Ad type</span>
                                            <select name="ad_type" data-ad-type-selector>
                                                <?php foreach ($slotTypes as $supportedType): ?>
                                                    <?php
                                                    $typeLabels = [
                                                        'placeholder' => 'Placeholder only',
                                                        'image' => 'Image ad',
                                                        'script' => 'Script embed',
                                                        'text' => 'Text ad',
                                                        'video' => 'Video pre-roll',
                                                        'vast' => 'VAST pre-roll',
                                                    ];
                                                    ?>
                                                    <option value="<?= e((string) $supportedType); ?>" <?= $slotType === $supportedType ? 'selected' : ''; ?>><?= e($typeLabels[(string) $supportedType] ?? ucfirst((string) $supportedType)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>
                                            <span>Headline / label</span>
                                            <input type="text" name="title" value="<?= e((string) ($slotAd['title'] ?? '')); ?>" placeholder="Optional ad title">
                                        </label>
                                        <label>
                                            <span>Click URL</span>
                                            <input type="text" name="click_url" value="<?= e((string) ($slotAd['click_url'] ?? '')); ?>" placeholder="https://partner.example">
                                        </label>
                                    </div>
                                </section>

                                <section class="admin-form-section" data-ad-type-group="image"<?= $slotType === 'image' ? '' : ' hidden'; ?>>
                                    <div class="admin-form-section__header">
                                        <h3>Image ad</h3>
                                        <p>Upload the creative here. The click URL above will be used when visitors click the image.</p>
                                    </div>
                                    <div class="admin-fields admin-fields--two">
                                        <label>
                                            <span>Image upload</span>
                                            <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                                        </label>
                                        <label class="checkbox-line">
                                            <input type="checkbox" name="remove_image" value="1">
                                            <span>Remove the current uploaded image</span>
                                        </label>
                                    </div>
                                </section>

                                <section class="admin-form-section" data-ad-type-group="video"<?= $slotType === 'video' ? '' : ' hidden'; ?>>
                                    <div class="admin-form-section__header">
                                        <h3>Video pre-roll</h3>
                                        <p>Use an uploaded ad video or a direct HTTPS video file URL. This slot plays before the main video for non-Premium viewers.</p>
                                    </div>
                                    <div class="admin-fields admin-fields--two">
                                        <label>
                                            <span>Direct video URL</span>
                                            <input type="text" name="video_url" value="<?= e((string) ($slotAd['video_url'] ?? '')); ?>" placeholder="https://cdn.example.com/preroll.mp4">
                                        </label>
                                        <label>
                                            <span>Skip after (seconds)</span>
                                            <input type="number" name="skip_after_seconds" min="0" max="30" step="1" value="<?= e((string) ($slotAd['skip_after_seconds'] ?? 5)); ?>">
                                        </label>
                                        <label>
                                            <span>Video upload</span>
                                            <input type="file" name="video_file" accept=".mp4,.m4v,.webm,.mov,video/mp4,video/webm,video/quicktime">
                                        </label>
                                        <label class="checkbox-line">
                                            <input type="checkbox" name="remove_video" value="1">
                                            <span>Remove the current uploaded video</span>
                                        </label>
                                    </div>
                                </section>

                                <section class="admin-form-section" data-ad-type-group="vast"<?= $slotType === 'vast' ? '' : ' hidden'; ?>>
                                    <div class="admin-form-section__header">
                                        <h3>VAST pre-roll</h3>
                                        <p>Paste a public HTTPS VAST tag URL. The player resolves the tag on the backend and plays the first supported media file before the video starts.</p>
                                    </div>
                                    <div class="admin-fields admin-fields--two">
                                        <label>
                                            <span>VAST tag URL</span>
                                            <input type="text" name="vast_tag_url" value="<?= e((string) ($slotAd['vast_tag_url'] ?? '')); ?>" placeholder="https://ads.example.com/vast.xml">
                                        </label>
                                        <label>
                                            <span>Fallback skip after (seconds)</span>
                                            <input type="number" name="skip_after_seconds" min="0" max="30" step="1" value="<?= e((string) ($slotAd['skip_after_seconds'] ?? 5)); ?>">
                                        </label>
                                    </div>
                                </section>

                                <section class="admin-form-section" data-ad-type-group="script"<?= $slotType === 'script' ? '' : ' hidden'; ?>>
                                    <div class="admin-form-section__header">
                                        <h3>Script ad</h3>
                                        <p>Paste the ad network code exactly as required. Premium members still will not see this slot.</p>
                                    </div>
                                    <label>
                                        <span>Script code</span>
                                        <textarea name="script_code" rows="8" placeholder="<script async src=&quot;...&quot;></script><?= PHP_EOL; ?><script>/* ad code */</script>"><?= e((string) ($slotAd['script_code'] ?? '')); ?></textarea>
                                    </label>
                                </section>

                                <section class="admin-form-section" data-ad-type-group="text"<?= $slotType === 'text' ? '' : ' hidden'; ?>>
                                    <div class="admin-form-section__header">
                                        <h3>Text ad</h3>
                                        <p>Use this for internal promos, affiliate callouts, or sponsor text blocks.</p>
                                    </div>
                                    <label>
                                        <span>Text content</span>
                                        <textarea name="body_text" rows="6" placeholder="Simple sponsored message or promo copy."><?= e((string) ($slotAd['body_text'] ?? '')); ?></textarea>
                                    </label>
                                </section>

                                <button class="button" type="submit">Save ad slot</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="admin-sidebar-stack">
                    <article class="compliance-card">
                        <h3>Visibility rule</h3>
                        <p>Ads are hidden only for signed-in Premium members. Admins still see the public placements so every slot can be reviewed in context.</p>
                    </article>
                    <article class="compliance-card">
                        <h3>Pre-roll note</h3>
                        <p>Pre-roll slots play only on the watch page. Uploaded videos use a true pre-roll gate, while embeds delay the iframe until the ad completes or is skipped.</p>
                    </article>
                    <article class="compliance-card">
                        <h3>Script note</h3>
                        <p>Script ads run only on public pages. If your ad network needs extra script origins, update the Content Security Policy in production too.</p>
                    </article>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'copy'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">COPY</span>
                        <h2>Public text editor</h2>
                    </div>
                    <p>Edit the visible public copy used across the homepage, browse, plans, support, watch, account, auth, and age-gate flows.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_copy_settings">
                        <?= csrf_input('admin'); ?>
                        <div class="copy-editor-controls" data-copy-editor>
                            <label class="copy-editor-controls__label">
                                <span>Section</span>
                                <select data-copy-section-selector>
                                    <?php foreach ($copySectionTabs as $tab): ?>
                                        <option value="<?= e($tab['id']); ?>"><?= e($tab['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="copy-editor-controls__summary" data-copy-section-summary>
                                <strong><?= e($copySectionTabs[0]['title'] ?? 'Copy'); ?></strong>
                                <p><?= e($copySectionTabs[0]['description'] ?? 'Edit the text used across public pages.'); ?></p>
                            </div>
                        </div>
                        <?php foreach ($copySections as $index => $section): ?>
                            <?php $panelId = 'copy-section-' . ($index + 1); ?>
                            <section class="admin-form-section copy-editor-panel" data-copy-section-panel="<?= e($panelId); ?>"<?= $index === 0 ? '' : ' hidden'; ?>>
                                <div class="admin-form-section__header">
                                    <h3><?= e((string) $section['title']); ?></h3>
                                    <p><?= e((string) $section['description']); ?></p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <?php foreach ($section['fields'] as $field): ?>
                                        <?php $formKey = str_replace('.', '__', (string) $field['key']); ?>
                                        <label>
                                            <span><?= e((string) $field['label']); ?></span>
                                            <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                                                <textarea name="copy[<?= e($formKey); ?>]" rows="<?= e((string) ($field['rows'] ?? 3)); ?>"><?= e($copySettings[(string) $field['key']] ?? ''); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" name="copy[<?= e($formKey); ?>]" value="<?= e($copySettings[(string) $field['key']] ?? ''); ?>">
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                        <?php if ($copyExtraFields !== []): ?>
                            <section class="admin-form-section copy-editor-panel" data-copy-section-panel="copy-section-extra" hidden>
                                <div class="admin-form-section__header">
                                    <h3>Additional copy keys</h3>
                                    <p>These keys are generated automatically from the text system so every remaining public text stays editable too.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <?php foreach ($copyExtraFields as $field): ?>
                                        <?php $formKey = str_replace('.', '__', (string) $field['key']); ?>
                                        <label>
                                            <span><?= e((string) $field['label']); ?></span>
                                            <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                                                <textarea name="copy[<?= e($formKey); ?>]" rows="<?= e((string) ($field['rows'] ?? 4)); ?>"><?= e($copySettings[(string) $field['key']] ?? ''); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" name="copy[<?= e($formKey); ?>]" value="<?= e($copySettings[(string) $field['key']] ?? ''); ?>">
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                        <button class="button" type="submit">Save public text</button>
                    </form>
                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>What this covers</h3>
                            <p>Use this screen for the public-facing product copy. Footer, legal pages, cookie notice, billing plan content, and branding still stay in their dedicated admin sections.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>How it saves</h3>
                            <p>These values are written to the `.env` file as one centralized text payload, so the project stays easy to deploy on shared hosting and VPS setups.</p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'legal'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">LEGAL</span>
                        <h2>Public legal pages and footer content</h2>
                    </div>
                    <p>Edit the pages and footer links visitors see across the site.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_legal_settings">
                        <?= csrf_input('admin'); ?>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Footer</h3>
                                <p>Control the public footer text and the link groups shown across the site.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Footer tagline</span>
                                    <textarea name="footer_tagline" rows="4"><?= e($legalSettings['footer_tagline']); ?></textarea>
                                </label>
                                <label>
                                    <span>Support copy</span>
                                    <textarea name="footer_support_copy" rows="4"><?= e($legalSettings['footer_support_copy']); ?></textarea>
                                </label>
                                <label>
                                    <span>Useful links title</span>
                                    <input type="text" name="footer_useful_title" value="<?= e($legalSettings['footer_useful_title']); ?>">
                                </label>
                                <label>
                                    <span>Legal links title</span>
                                    <input type="text" name="footer_legal_title" value="<?= e($legalSettings['footer_legal_title']); ?>">
                                </label>
                                <label>
                                    <span>Support title</span>
                                    <input type="text" name="footer_support_title" value="<?= e($legalSettings['footer_support_title']); ?>">
                                </label>
                                <div class="admin-empty-slot"></div>
                                <label>
                                    <span>Useful link 1 label</span>
                                    <input type="text" name="footer_useful_link_1_label" value="<?= e($legalSettings['footer_useful_link_1_label']); ?>">
                                </label>
                                <label>
                                    <span>Useful link 1 URL</span>
                                    <input type="text" name="footer_useful_link_1_url" value="<?= e($legalSettings['footer_useful_link_1_url']); ?>">
                                </label>
                                <label>
                                    <span>Useful link 2 label</span>
                                    <input type="text" name="footer_useful_link_2_label" value="<?= e($legalSettings['footer_useful_link_2_label']); ?>">
                                </label>
                                <label>
                                    <span>Useful link 2 URL</span>
                                    <input type="text" name="footer_useful_link_2_url" value="<?= e($legalSettings['footer_useful_link_2_url']); ?>">
                                </label>
                                <label>
                                    <span>Useful link 3 label</span>
                                    <input type="text" name="footer_useful_link_3_label" value="<?= e($legalSettings['footer_useful_link_3_label']); ?>">
                                </label>
                                <label>
                                    <span>Useful link 3 URL</span>
                                    <input type="text" name="footer_useful_link_3_url" value="<?= e($legalSettings['footer_useful_link_3_url']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 1 label</span>
                                    <input type="text" name="footer_legal_link_1_label" value="<?= e($legalSettings['footer_legal_link_1_label']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 1 URL</span>
                                    <input type="text" name="footer_legal_link_1_url" value="<?= e($legalSettings['footer_legal_link_1_url']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 2 label</span>
                                    <input type="text" name="footer_legal_link_2_label" value="<?= e($legalSettings['footer_legal_link_2_label']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 2 URL</span>
                                    <input type="text" name="footer_legal_link_2_url" value="<?= e($legalSettings['footer_legal_link_2_url']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 3 label</span>
                                    <input type="text" name="footer_legal_link_3_label" value="<?= e($legalSettings['footer_legal_link_3_label']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 3 URL</span>
                                    <input type="text" name="footer_legal_link_3_url" value="<?= e($legalSettings['footer_legal_link_3_url']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 4 label</span>
                                    <input type="text" name="footer_legal_link_4_label" value="<?= e($legalSettings['footer_legal_link_4_label']); ?>">
                                </label>
                                <label>
                                    <span>Legal link 4 URL</span>
                                    <input type="text" name="footer_legal_link_4_url" value="<?= e($legalSettings['footer_legal_link_4_url']); ?>">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Rules page</h3>
                                <p>Keep the rules page clear and easy to understand for visitors.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Nav label</span>
                                    <input type="text" name="rules_nav_label" value="<?= e($legalSettings['rules_nav_label']); ?>">
                                </label>
                                <label>
                                    <span>Kicker</span>
                                    <input type="text" name="rules_kicker" value="<?= e($legalSettings['rules_kicker']); ?>">
                                </label>
                                <label>
                                    <span>Page title</span>
                                    <input type="text" name="rules_title" value="<?= e($legalSettings['rules_title']); ?>">
                                </label>
                                <label>
                                    <span>Page intro</span>
                                    <textarea name="rules_intro" rows="4"><?= e($legalSettings['rules_intro']); ?></textarea>
                                </label>
                                <label>
                                    <span>Card 1 title</span>
                                    <input type="text" name="rules_card_1_title" value="<?= e($legalSettings['rules_card_1_title']); ?>">
                                </label>
                                <label>
                                    <span>Card 1 text</span>
                                    <textarea name="rules_card_1_text" rows="3"><?= e($legalSettings['rules_card_1_text']); ?></textarea>
                                </label>
                                <label>
                                    <span>Card 2 title</span>
                                    <input type="text" name="rules_card_2_title" value="<?= e($legalSettings['rules_card_2_title']); ?>">
                                </label>
                                <label>
                                    <span>Card 2 text</span>
                                    <textarea name="rules_card_2_text" rows="3"><?= e($legalSettings['rules_card_2_text']); ?></textarea>
                                </label>
                                <label>
                                    <span>Card 3 title</span>
                                    <input type="text" name="rules_card_3_title" value="<?= e($legalSettings['rules_card_3_title']); ?>">
                                </label>
                                <label>
                                    <span>Card 3 text</span>
                                    <textarea name="rules_card_3_text" rows="3"><?= e($legalSettings['rules_card_3_text']); ?></textarea>
                                </label>
                                <label>
                                    <span>Card 4 title</span>
                                    <input type="text" name="rules_card_4_title" value="<?= e($legalSettings['rules_card_4_title']); ?>">
                                </label>
                                <label>
                                    <span>Card 4 text</span>
                                    <textarea name="rules_card_4_text" rows="3"><?= e($legalSettings['rules_card_4_text']); ?></textarea>
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Terms page</h3>
                                <p>Write this in plain language for visitors.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Kicker</span>
                                    <input type="text" name="terms_kicker" value="<?= e($legalSettings['terms_kicker']); ?>">
                                </label>
                                <label>
                                    <span>Page title</span>
                                    <input type="text" name="terms_title" value="<?= e($legalSettings['terms_title']); ?>">
                                </label>
                                <label>
                                    <span>Page intro</span>
                                    <textarea name="terms_intro" rows="4"><?= e($legalSettings['terms_intro']); ?></textarea>
                                </label>
                                <label>
                                    <span>Content</span>
                                    <textarea name="terms_content" rows="10"><?= e($legalSettings['terms_content']); ?></textarea>
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Privacy page</h3>
                                <p>Explain privacy in simple language visitors can understand.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Kicker</span>
                                    <input type="text" name="privacy_kicker" value="<?= e($legalSettings['privacy_kicker']); ?>">
                                </label>
                                <label>
                                    <span>Page title</span>
                                    <input type="text" name="privacy_title" value="<?= e($legalSettings['privacy_title']); ?>">
                                </label>
                                <label>
                                    <span>Page intro</span>
                                    <textarea name="privacy_intro" rows="4"><?= e($legalSettings['privacy_intro']); ?></textarea>
                                </label>
                                <label>
                                    <span>Content</span>
                                    <textarea name="privacy_content" rows="10"><?= e($legalSettings['privacy_content']); ?></textarea>
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Cookies page and banner</h3>
                                <p>Control both the cookie page and the banner shown on public pages.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Kicker</span>
                                    <input type="text" name="cookies_kicker" value="<?= e($legalSettings['cookies_kicker']); ?>">
                                </label>
                                <label>
                                    <span>Page title</span>
                                    <input type="text" name="cookies_title" value="<?= e($legalSettings['cookies_title']); ?>">
                                </label>
                                <label>
                                    <span>Page intro</span>
                                    <textarea name="cookies_intro" rows="4"><?= e($legalSettings['cookies_intro']); ?></textarea>
                                </label>
                                <label>
                                    <span>Content</span>
                                    <textarea name="cookies_content" rows="10"><?= e($legalSettings['cookies_content']); ?></textarea>
                                </label>
                                <label class="checkbox-line">
                                    <input type="checkbox" name="cookie_notice_enabled" value="1" <?= $legalSettings['cookie_notice_enabled'] === '1' ? 'checked' : ''; ?>>
                                    <span>Show the cookie notice banner on public pages.</span>
                                </label>
                                <div class="admin-empty-slot"></div>
                                <label>
                                    <span>Notice title</span>
                                    <input type="text" name="cookie_notice_title" value="<?= e($legalSettings['cookie_notice_title']); ?>">
                                </label>
                                <label>
                                    <span>Notice text</span>
                                    <textarea name="cookie_notice_text" rows="4"><?= e($legalSettings['cookie_notice_text']); ?></textarea>
                                </label>
                                <label>
                                    <span>Accept button label</span>
                                    <input type="text" name="cookie_notice_accept_label" value="<?= e($legalSettings['cookie_notice_accept_label']); ?>">
                                </label>
                                <label>
                                    <span>Policy link label</span>
                                    <input type="text" name="cookie_notice_link_label" value="<?= e($legalSettings['cookie_notice_link_label']); ?>">
                                </label>
                                <label>
                                    <span>Policy link URL</span>
                                    <input type="text" name="cookie_notice_link_url" value="<?= e($legalSettings['cookie_notice_link_url']); ?>">
                                </label>
                            </div>
                        </section>

                        <button class="button" type="submit">Save legal pages and footer</button>
                    </form>
                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>Private site settings</h3>
                            <p>Footer copy, policies, and cookie notice text are saved privately for this site.</p>
                        </article>
                        <article class="compliance-card">
                            <h3>Public previews</h3>
                            <p><a class="text-link" href="<?= e(base_url('rules.php')); ?>" target="_blank" rel="noreferrer">Open rules page</a></p>
                            <p><a class="text-link" href="<?= e(base_url('terms.php')); ?>" target="_blank" rel="noreferrer">Open terms page</a></p>
                            <p><a class="text-link" href="<?= e(base_url('privacy.php')); ?>" target="_blank" rel="noreferrer">Open privacy page</a></p>
                            <p><a class="text-link" href="<?= e(base_url('cookies.php')); ?>" target="_blank" rel="noreferrer">Open cookie page</a></p>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($screen === 'activity'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">ACTIVITY</span>
                        <h2>Recent admin actions</h2>
                    </div>
                    <p>Audit trail for storage, videos, users, and settings.</p>
                </div>
                <form method="get" class="admin-toolbar">
                    <input type="hidden" name="screen" value="activity">
                    <label>
                        <span>Action</span>
                        <input type="search" name="activity_action" value="<?= e($activityFilters['action']); ?>" placeholder="video.updated">
                    </label>
                    <label>
                        <span>Target type</span>
                        <select name="activity_target_type">
                            <option value="">All</option>
                            <option value="video" <?= $activityFilters['target_type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                            <option value="user" <?= $activityFilters['target_type'] === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="settings" <?= $activityFilters['target_type'] === 'settings' ? 'selected' : ''; ?>>Settings</option>
                        </select>
                    </label>
                    <label>
                        <span>Actor</span>
                        <input type="search" name="activity_actor" value="<?= e($activityFilters['actor']); ?>" placeholder="Admin name or email">
                    </label>
                    <label>
                        <span>From</span>
                        <input type="date" name="activity_from" value="<?= e($activityFilters['from']); ?>">
                    </label>
                    <label>
                        <span>To</span>
                        <input type="date" name="activity_to" value="<?= e($activityFilters['to']); ?>">
                    </label>
                    <button class="button" type="submit">Filter</button>
                    <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'csv', 'activity_page' => null])); ?>">Export CSV</a>
                </form>
                <?php if ($activityItems === []): ?>
                    <div class="notice-card">
                        <strong>No activity recorded yet</strong>
                        <p>Admin actions will start appearing here after the first changes are saved.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-worklist">
                        <?php foreach ($activityItems as $item): ?>
                            <article class="admin-activity-row">
                                <div class="admin-activity-row__main">
                                    <div class="admin-activity-row__meta">
                                        <span class="pill"><?= e((string) $item['action']); ?></span>
                                        <span class="pill pill--muted"><?= e((string) $item['target_type']); ?><?php if (!empty($item['target_id'])): ?> #<?= e((string) $item['target_id']); ?><?php endif; ?></span>
                                    </div>
                                    <h3><?= e((string) $item['summary']); ?></h3>
                                    <?php if (!empty($item['metadata_json'])): ?>
                                        <p><?= e((string) $item['metadata_json']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-activity-row__aside">
                                    <strong><?= e((string) ($item['actor_name'] ?: $item['actor_email'] ?: 'System')); ?></strong>
                                    <div><?= e(format_datetime((string) ($item['created_at'] ?? null))); ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($activityPagination['total_pages'] ?? 1) > 1): ?>
                        <nav class="pagination">
                            <?php for ($pageNumber = 1; $pageNumber <= (int) $activityPagination['total_pages']; $pageNumber++): ?>
                                <a class="<?= (int) $activityPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['activity_page' => $pageNumber, 'export' => null])); ?>"><?= e((string) $pageNumber); ?></a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('admin')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
