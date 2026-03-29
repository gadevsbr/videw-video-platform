<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Repositories\AuditLogRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Services\AdminVideoService;
use App\Services\BillingService;
use App\Services\MediaAccessService;

ensure_admin();

$settingsRepository = new SettingsRepository();
$auditLogs = new AuditLogRepository();
$usersRepository = new UserRepository();
$videoRepository = new VideoRepository();
$adminVideos = new AdminVideoService();
$mediaAccess = new MediaAccessService();
$billing = new BillingService();
$dbReady = $settingsRepository->dbReady();
$validScreens = ['overview', 'storage', 'billing', 'publish', 'library', 'moderation', 'users', 'settings', 'legal', 'activity'];
$screen = (string) ($_GET['screen'] ?? 'overview');
$screen = in_array($screen, $validScreens, true) ? $screen : 'overview';
$screenUrl = static fn (string $target): string => base_url('admin.php?screen=' . urlencode($target));
$actorId = (int) (current_user()['id'] ?? 0);

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'admin')) {
        flash('error', 'Security token expired. Try again.');
        redirect('admin.php?screen=' . $screen);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_storage') {
        try {
            $storageSettings = [
                'upload_driver' => in_array((string) ($_POST['upload_driver'] ?? 'local'), ['local', 'wasabi'], true)
                    ? (string) $_POST['upload_driver']
                    : 'local',
                'wasabi_endpoint' => trim((string) ($_POST['wasabi_endpoint'] ?? '')),
                'wasabi_region' => trim((string) ($_POST['wasabi_region'] ?? '')),
                'wasabi_bucket' => trim((string) ($_POST['wasabi_bucket'] ?? '')),
                'wasabi_access_key' => trim((string) ($_POST['wasabi_access_key'] ?? '')),
                'wasabi_secret_key' => trim((string) ($_POST['wasabi_secret_key'] ?? '')),
                'wasabi_public_base_url' => trim((string) ($_POST['wasabi_public_base_url'] ?? '')),
                'wasabi_path_prefix' => trim((string) ($_POST['wasabi_path_prefix'] ?? 'videw')),
                'wasabi_private_bucket' => (string) (($_POST['wasabi_private_bucket'] ?? '') === '1' ? '1' : '0'),
                'wasabi_signed_url_ttl_seconds' => trim((string) ($_POST['wasabi_signed_url_ttl_seconds'] ?? '900')),
                'wasabi_multipart_threshold_mb' => trim((string) ($_POST['wasabi_multipart_threshold_mb'] ?? '64')),
                'wasabi_multipart_part_size_mb' => trim((string) ($_POST['wasabi_multipart_part_size_mb'] ?? '16')),
            ];

            write_env_file_values(ROOT_PATH . '/.env', storage_settings_to_env_values($storageSettings));
            $settingsRepository->putMany($storageSettings);
            $auditLogs->record($actorId ?: null, 'storage.saved', 'settings', null, 'Updated storage and Wasabi settings.', [
                'driver' => $storageSettings['upload_driver'],
                'private_bucket' => $storageSettings['wasabi_private_bucket'],
            ]);
            flash('success', 'Storage settings saved to .env.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=storage');
    }

    if ($action === 'save_billing_settings') {
        try {
            $billingSettings = [
                'stripe_secret_key' => trim((string) ($_POST['stripe_secret_key'] ?? (string) config('billing.stripe_secret_key'))),
                'stripe_publishable_key' => trim((string) ($_POST['stripe_publishable_key'] ?? (string) config('billing.stripe_publishable_key'))),
                'stripe_webhook_secret' => trim((string) ($_POST['stripe_webhook_secret'] ?? (string) config('billing.stripe_webhook_secret'))),
                'premium_price_id' => trim((string) ($_POST['premium_price_id'] ?? (string) config('billing.premium_price_id'))),
                'premium_plan_name' => trim((string) ($_POST['premium_plan_name'] ?? (string) config('billing.premium_plan_name'))),
                'premium_plan_copy' => trim((string) ($_POST['premium_plan_copy'] ?? (string) config('billing.premium_plan_copy'))),
                'premium_price_label' => trim((string) ($_POST['premium_price_label'] ?? (string) config('billing.premium_price_label'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', billing_settings_to_env_values($billingSettings));
            $auditLogs->record($actorId ?: null, 'billing.saved', 'settings', null, 'Updated Stripe billing settings.', [
                'price_id' => $billingSettings['premium_price_id'],
                'plan_name' => $billingSettings['premium_plan_name'],
                'webhook_configured' => $billingSettings['stripe_webhook_secret'] !== '' ? 1 : 0,
            ]);
            flash('success', 'Stripe billing settings saved to .env.');
        } catch (RuntimeException $exception) {
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
                'base_url' => trim((string) ($_POST['base_url'] ?? base_url())),
                'support_email' => trim((string) ($_POST['support_email'] ?? (string) config('app.support_email'))),
                'exit_url' => trim((string) ($_POST['exit_url'] ?? (string) config('app.exit_url'))),
                'timezone' => trim((string) ($_POST['timezone'] ?? (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', app_settings_to_env_values($appSettings));
            $auditLogs->record($actorId ?: null, 'app.saved', 'settings', null, 'Updated general application settings.', [
                'app_name' => $appSettings['app_name'],
                'brand' => $appSettings['brand_kicker'] . ' ' . $appSettings['brand_title'],
            ]);
            flash('success', 'App settings saved to .env.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=settings');
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
            $auditLogs->record($actorId ?: null, 'legal.saved', 'settings', null, 'Updated public legal pages, footer links, and cookie notice settings.', [
                'rules_title' => $legalSettings['rules_title'],
                'terms_title' => $legalSettings['terms_title'],
                'privacy_title' => $legalSettings['privacy_title'],
                'cookies_title' => $legalSettings['cookies_title'],
                'cookie_notice_enabled' => $legalSettings['cookie_notice_enabled'],
            ]);
            flash('success', 'Legal content and footer settings saved to .env.');
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
            'source_mode' => trim((string) ($_POST['source_mode'] ?? 'file')),
            'external_url' => trim((string) ($_POST['external_url'] ?? '')),
            'is_featured' => (string) ($_POST['is_featured'] ?? ''),
            'moderation_status' => trim((string) ($_POST['moderation_status'] ?? 'draft')),
            'moderation_notes' => trim((string) ($_POST['moderation_notes'] ?? '')),
        ]);

        $result = $adminVideos->publish($_POST, $_FILES);

        if ($result['success']) {
            clear_old_input();
            $auditLogs->record($actorId ?: null, 'video.created', 'video', (int) ($result['video_id'] ?? 0), 'Created a video.', [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'status' => trim((string) ($_POST['moderation_status'] ?? 'draft')),
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
        $notes = trim((string) ($_POST['moderation_notes'] ?? ''));

        try {
            $videoRepository->updateModeration($videoId, $status, $notes !== '' ? $notes : null);
            $auditLogs->record($actorId ?: null, 'video.moderated', 'video', $videoId, 'Updated moderation status.', [
                'status' => $status,
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
        $bulkNotes = trim((string) ($_POST['bulk_notes'] ?? ''));

        if ($videoIds === []) {
            flash('error', 'Select at least one video first.');
            redirect('admin.php?screen=moderation');
        }

        try {
            $count = match ($bulkAction) {
                'approve' => $videoRepository->bulkUpdateModeration($videoIds, 'approved', $bulkNotes !== '' ? $bulkNotes : null),
                'draft' => $videoRepository->bulkUpdateModeration($videoIds, 'draft', $bulkNotes !== '' ? $bulkNotes : null),
                'flagged' => $videoRepository->bulkUpdateModeration($videoIds, 'flagged', $bulkNotes !== '' ? $bulkNotes : null),
                'delete' => $adminVideos->bulkDelete($videoIds),
                default => throw new RuntimeException('Invalid bulk moderation action.'),
            };

            $auditLogs->record($actorId ?: null, 'video.bulk_moderation', 'video', null, 'Applied a bulk moderation action.', [
                'action' => $bulkAction,
                'video_ids' => $videoIds,
                'notes' => $bulkNotes,
                'count' => $count,
            ]);
            flash('success', 'Bulk moderation applied to ' . $count . ' videos.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=moderation');
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
$moderationPage = max(1, (int) ($_GET['moderation_page'] ?? 1));
$moderationPagination = $dbReady ? $videoRepository->paginateForAdmin([
    'status' => in_array($moderationStatusFilter, ['draft', 'approved', 'flagged'], true) ? $moderationStatusFilter : 'draft',
], $moderationPage, 8) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'total_pages' => 1];
$moderationVideos = $dbReady ? $mediaAccess->decorateVideos($moderationPagination['items']) : [];
$editingVideoId = (int) ($_GET['edit'] ?? 0);
$editingVideo = $editingVideoId > 0 ? $videoRepository->findById($editingVideoId) : null;
$recentVideos = array_slice($allVideos, 0, 8);
$stats = $videoRepository->stats();
$adminStats = $videoRepository->adminStats();
$userSearch = trim((string) ($_GET['user_search'] ?? ''));
$usersPage = max(1, (int) ($_GET['users_page'] ?? 1));
$usersPagination = $dbReady ? $usersRepository->paginateAll($userSearch, $usersPage, 10) : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1];
$users = $dbReady ? $usersPagination['items'] : [];
$userStats = $usersRepository->stats();
$activityPage = max(1, (int) ($_GET['activity_page'] ?? 1));
$activityPagination = $auditLogs->paginate($activityFilters, $activityPage, 20);
$activityItems = $activityPagination['items'];
$appSettings = [
    'app_name' => (string) config('app.name'),
    'app_description' => (string) config('app.description'),
    'brand_kicker' => brand_kicker(),
    'brand_title' => brand_title(),
    'base_url' => base_url(),
    'support_email' => (string) config('app.support_email'),
    'exit_url' => (string) config('app.exit_url'),
    'timezone' => (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'),
];
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

if ($screen === 'activity' && (string) ($_GET['export'] ?? '') === 'csv') {
    $exportItems = $auditLogs->filtered($activityFilters, 2000);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="videw-activity-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'wb');

    if (is_resource($output)) {
        fputcsv($output, ['id', 'action', 'target_type', 'target_id', 'summary', 'actor_name', 'actor_email', 'created_at', 'metadata_json']);

        foreach ($exportItems as $item) {
            fputcsv($output, [
                $item['id'] ?? '',
                $item['action'] ?? '',
                $item['target_type'] ?? '',
                $item['target_id'] ?? '',
                $item['summary'] ?? '',
                $item['actor_name'] ?? '',
                $item['actor_email'] ?? '',
                $item['created_at'] ?? '',
                $item['metadata_json'] ?? '',
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
        'copy' => 'Use one screen for each job: overview, storage, publishing, and library.',
        'primary' => ['label' => 'Open storage', 'href' => $screenUrl('storage')],
        'secondary' => ['label' => 'New video', 'href' => $screenUrl('publish')],
    ],
    'storage' => [
        'eyebrow' => 'STORAGE',
        'title' => 'Upload and delivery settings.',
        'copy' => 'Pick the upload driver, set Wasabi access, and choose public or signed playback.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open library', 'href' => $screenUrl('library')],
    ],
    'billing' => [
        'eyebrow' => 'BILLING',
        'title' => 'Premium subscription settings.',
        'copy' => 'Store Stripe keys in `.env`, define the premium price, and publish the webhook endpoint for shared hosting or VPS.',
        'primary' => ['label' => 'Back to overview', 'href' => $screenUrl('overview')],
        'secondary' => ['label' => 'Open users', 'href' => $screenUrl('users')],
    ],
    'publish' => [
        'eyebrow' => 'PUBLISH',
        'title' => 'Create a new video.',
        'copy' => 'Post from local uploads, Wasabi-backed uploads, or supported external URLs.',
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
        'copy' => 'Update branding, support info, URLs, and timezone from the admin panel.',
        'primary' => ['label' => 'Open storage', 'href' => $screenUrl('storage')],
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
<body class="<?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <div class="legal-bar">
        <span>Admin panel</span>
        <span>Local or Wasabi uploads</span>
        <span>External embeds</span>
    </div>
    <header class="site-header">
        <a class="brandmark" href="<?= e(base_url()); ?>">
            <span class="brandmark__kicker"><?= e(brand_kicker()); ?></span>
            <span class="brandmark__title"><?= e(brand_title()); ?></span>
        </a>
        <nav class="site-nav">
            <a href="<?= e(base_url()); ?>">Home</a>
            <a href="<?= e($screenUrl('overview')); ?>">Overview</a>
            <a href="<?= e($screenUrl('storage')); ?>">Storage</a>
            <a href="<?= e($screenUrl('billing')); ?>">Billing</a>
            <a href="<?= e($screenUrl('publish')); ?>">Publish</a>
            <a href="<?= e($screenUrl('library')); ?>">Library</a>
            <a href="<?= e($screenUrl('moderation')); ?>">Moderation</a>
            <a href="<?= e($screenUrl('users')); ?>">Users</a>
            <a href="<?= e($screenUrl('settings')); ?>">Settings</a>
            <a href="<?= e($screenUrl('legal')); ?>">Legal</a>
            <a href="<?= e($screenUrl('activity')); ?>">Activity</a>
            <a href="<?= e(base_url('account.php')); ?>">Account</a>
        </nav>
        <div class="site-nav__actions">
            <span class="pill pill--muted">admin</span>
            <a class="button button--ghost" href="<?= e(base_url('logout.php')); ?>">Log out</a>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($flashError): ?>
            <div class="flash flash--error"><?= e((string) $flashError); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
        <?php endif; ?>

        <section class="hero admin-hero">
            <div class="hero__copy">
                <span class="eyebrow"><?= e($currentScreen['eyebrow']); ?></span>
                <h1><?= e($currentScreen['title']); ?></h1>
                <p><?= e($currentScreen['copy']); ?></p>
                <div class="hero__actions">
                    <a class="button" href="<?= e($currentScreen['primary']['href']); ?>"><?= e($currentScreen['primary']['label']); ?></a>
                    <a class="button button--ghost" href="<?= e($currentScreen['secondary']['href']); ?>"><?= e($currentScreen['secondary']['label']); ?></a>
                </div>
                <div class="admin-screen-nav">
                    <?php foreach ([
                        'overview' => 'Overview',
                        'storage' => 'Storage',
                        'billing' => 'Billing',
                        'publish' => 'Publish',
                        'library' => 'Library',
                        'moderation' => 'Moderation',
                        'users' => 'Users',
                        'settings' => 'Settings',
                        'legal' => 'Legal',
                        'activity' => 'Activity',
                    ] as $key => $label): ?>
                        <a class="<?= $screen === $key ? 'chip chip--active' : 'chip'; ?>" href="<?= e($screenUrl($key)); ?>"><?= e($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php if (!$dbReady): ?>
                    <div class="notice-card">
                        <strong>Database offline</strong>
                        <p>Publishing needs MySQL. Storage settings still save to .env.</p>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="hero__aside admin-hero__aside">
                <div class="stat-card">
                    <span class="stat-card__label">Driver</span>
                    <strong><?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></strong>
                    <span>used for new uploads</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__label">Library</span>
                    <strong><?= e((string) $adminStats['total']); ?></strong>
                    <span><?= e((string) $adminStats['approved']); ?> approved items</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__label">Delivery</span>
                    <strong><?= $privateDelivery ? 'Signed' : 'Public'; ?></strong>
                    <span>TTL <?= e((string) ($settings['wasabi_signed_url_ttl_seconds'] ?? '900')); ?>s</span>
                </div>
            </aside>
        </section>

        <?php if ($screen === 'overview'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">WORKFLOWS</span>
                        <h2>One screen for each admin job</h2>
                    </div>
                    <p>Jump straight to the task you need.</p>
                </div>
                <div class="admin-overview-grid">
                    <a class="admin-link-card" href="<?= e($screenUrl('storage')); ?>">
                        <span class="eyebrow">STORAGE</span>
                        <strong>Upload driver and Wasabi access</strong>
                        <p>Switch between local and Wasabi, then tune signed playback and multipart uploads.</p>
                        <span class="text-link">Open storage</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('billing')); ?>">
                        <span class="eyebrow">BILLING</span>
                        <strong>Stripe keys and premium access</strong>
                        <p>Set the Stripe secrets, premium price ID, webhook secret, and public plan copy.</p>
                        <span class="text-link">Open billing</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('publish')); ?>">
                        <span class="eyebrow">PUBLISH</span>
                        <strong>New uploads and external URLs</strong>
                        <p>Create new items from files, direct media URLs, or supported embed links.</p>
                        <span class="text-link">Open publish</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('library')); ?>">
                        <span class="eyebrow">LIBRARY</span>
                        <strong>Recent items and delivery status</strong>
                        <p>Check which items are featured, which are using Wasabi, and which are external.</p>
                        <span class="text-link">Open library</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('moderation')); ?>">
                        <span class="eyebrow">MODERATION</span>
                        <strong>Review drafts and flagged items</strong>
                        <p>Move items between draft, approved, and flagged with internal notes.</p>
                        <span class="text-link">Open moderation</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('users')); ?>">
                        <span class="eyebrow">USERS</span>
                        <strong>Roles, creators, and suspensions</strong>
                        <p>Manage account roles, creator access, and suspended users.</p>
                        <span class="text-link">Open users</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('settings')); ?>">
                        <span class="eyebrow">SETTINGS</span>
                        <strong>Branding and deployment values</strong>
                        <p>Update app branding, support email, URLs and timezone through `.env`.</p>
                        <span class="text-link">Open settings</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('legal')); ?>">
                        <span class="eyebrow">LEGAL</span>
                        <strong>Footer, policies, and cookie copy</strong>
                        <p>Edit the public rules page, policy text, cookie notice, and footer navigation.</p>
                        <span class="text-link">Open legal</span>
                    </a>
                    <a class="admin-link-card" href="<?= e($screenUrl('activity')); ?>">
                        <span class="eyebrow">ACTIVITY</span>
                        <strong>Audit trail of admin actions</strong>
                        <p>Review recent changes across videos, users, storage, and settings.</p>
                        <span class="text-link">Open activity</span>
                    </a>
                </div>
            </section>

            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">STATUS</span>
                        <h2>Quick numbers</h2>
                    </div>
                    <p>High-level counts across the whole catalog.</p>
                </div>
                <div class="admin-summary-grid">
                    <article class="mini-stat">
                        <span>Published</span>
                        <strong><?= e((string) $stats['videos']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Creators</span>
                        <strong><?= e((string) $stats['creators']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Featured</span>
                        <strong><?= e((string) $featuredCount); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Premium videos</span>
                        <strong><?= e((string) $premiumVideoCount); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>External</span>
                        <strong><?= e((string) $externalCount); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Embeds</span>
                        <strong><?= e((string) $embedCount); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Wasabi</span>
                        <strong><?= e((string) $wasabiCount); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Draft queue</span>
                        <strong><?= e((string) $adminStats['draft']); ?></strong>
                    </article>
                    <article class="mini-stat">
                        <span>Users</span>
                        <strong><?= e((string) $userStats['users']); ?></strong>
                    </article>
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
                    <p>External URLs skip the upload driver and stay marked as external.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_storage">
                        <?= csrf_input('admin'); ?>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Upload driver</h3>
                                <p>Choose where new uploaded files will be stored.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Driver</span>
                                    <select name="upload_driver">
                                        <option value="local" <?= ($settings['upload_driver'] ?? 'local') === 'local' ? 'selected' : ''; ?>>Local hosting</option>
                                        <option value="wasabi" <?= ($settings['upload_driver'] ?? '') === 'wasabi' ? 'selected' : ''; ?>>Wasabi API</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Path prefix</span>
                                    <input type="text" name="wasabi_path_prefix" value="<?= e((string) ($settings['wasabi_path_prefix'] ?? 'videw')); ?>" placeholder="videw">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Wasabi connection</h3>
                                <p>Set the endpoint, bucket, and credentials used for Wasabi uploads.</p>
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
                                    <input type="text" name="wasabi_access_key" value="<?= e((string) ($settings['wasabi_access_key'] ?? '')); ?>">
                                </label>
                                <label>
                                    <span>Secret key</span>
                                    <input type="password" name="wasabi_secret_key" value="<?= e((string) ($settings['wasabi_secret_key'] ?? '')); ?>">
                                </label>
                            </div>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Delivery rules</h3>
                                <p>Control signed URLs and multipart upload thresholds.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label class="checkbox-line">
                                    <input type="checkbox" name="wasabi_private_bucket" value="1" <?= ($settings['wasabi_private_bucket'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span>Private bucket with signed playback URLs</span>
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

                        <button class="button" type="submit">Save settings</button>
                    </form>

                    <div class="admin-sidebar-stack">
                        <article class="admin-guide">
                            <div class="admin-guide__header">
                                <span class="eyebrow">HOW IT WORKS</span>
                                <h3>Storage flow</h3>
                                <p>The guide now sits in its own block with more spacing and clearer steps.</p>
                            </div>
                            <div class="admin-steps">
                                <article class="admin-step">
                                    <strong>Local</strong>
                                    <p>Stores uploaded files inside `storage/uploads` on the hosting server.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Wasabi</strong>
                                    <p>Sends uploaded files to your S3-compatible Wasabi bucket using the saved credentials.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>.env</strong>
                                    <p>Credentials and storage values are saved to `/.env` from the admin panel.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Multipart</strong>
                                    <p>Large files are split into parts, uploaded separately, and completed in the bucket.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>Private playback</strong>
                                    <p>When the bucket is private, posters and videos are served with signed URLs.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>External URL</strong>
                                    <p>Direct `.mp4/.webm/.m3u8` links and YouTube, Vimeo, or Dailymotion embeds stay marked as external.</p>
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

        <?php if ($screen === 'billing'): ?>
            <section class="catalog-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">BILLING</span>
                        <h2>Stripe and Premium plan</h2>
                    </div>
                    <p>Hosted Checkout for upgrades, Billing Portal for self-service, and webhooks for account sync.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_billing_settings">
                        <?= csrf_input('admin'); ?>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Stripe credentials</h3>
                                <p>Keep live or test credentials out of the repository and write them into `.env` from the admin panel.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Secret key</span>
                                    <input type="password" name="stripe_secret_key" value="<?= e($billingSettings['stripe_secret_key']); ?>" placeholder="sk_live_...">
                                </label>
                                <label>
                                    <span>Publishable key</span>
                                    <input type="text" name="stripe_publishable_key" value="<?= e($billingSettings['stripe_publishable_key']); ?>" placeholder="pk_live_...">
                                </label>
                                <label>
                                    <span>Webhook signing secret</span>
                                    <input type="password" name="stripe_webhook_secret" value="<?= e($billingSettings['stripe_webhook_secret']); ?>" placeholder="whsec_...">
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

                        <button class="button" type="submit">Save billing settings</button>
                    </form>

                    <div class="admin-sidebar-stack">
                        <article class="admin-guide">
                            <div class="admin-guide__header">
                                <span class="eyebrow">CHECKOUT FLOW</span>
                                <h3>How Premium works</h3>
                                <p>The site uses Stripe Hosted Checkout and the Stripe Billing Portal, which fit shared hosting and VPS deployments well.</p>
                            </div>
                            <div class="admin-steps">
                                <article class="admin-step">
                                    <strong>1. Create product and price</strong>
                                    <p>Create the recurring Premium product in Stripe Dashboard, then paste the `price_...` ID here.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>2. Save secrets in .env</strong>
                                    <p>The admin form writes keys and billing copy into `/.env` so the public repository stays clean.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>3. Configure the webhook</strong>
                                    <p>Point Stripe to the endpoint below and subscribe at least to `checkout.session.completed`, `invoice.paid`, and `invoice.payment_failed`.</p>
                                </article>
                                <article class="admin-step">
                                    <strong>4. Gate playback</strong>
                                    <p>`Free` videos play for everyone. `Premium` videos require a logged-in account with an active Stripe-backed Premium tier.</p>
                                </article>
                            </div>
                        </article>

                        <article class="compliance-card">
                            <h3>Configuration status</h3>
                            <p><strong>Billing ready:</strong> <?= $billingConfigured ? 'Yes' : 'No'; ?></p>
                            <p><strong>Webhook ready:</strong> <?= $webhookConfigured ? 'Yes' : 'No'; ?></p>
                            <p><strong>Plans page:</strong> <a class="text-link" href="<?= e(base_url('premium.php')); ?>" target="_blank" rel="noreferrer">Open public premium page</a></p>
                        </article>

                        <article class="compliance-card">
                            <h3>Webhook endpoint</h3>
                            <p>Use this URL in Stripe Workbench or the Dashboard webhook settings:</p>
                            <code><?= e($webhookUrl); ?></code>
                            <p class="form-note">Use HTTPS in production and keep `VIDEW_BASE_URL` pointed at the real domain.</p>
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
                    <form method="post" enctype="multipart/form-data" class="admin-form-shell">
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
                            </div>
                            <label>
                                <span>Moderation notes</span>
                                <textarea name="moderation_notes" rows="4" placeholder="Optional internal notes"><?= e(old('moderation_notes')); ?></textarea>
                            </label>
                        </section>

                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Media source</h3>
                                <p>Use one of the supported source modes below.</p>
                            </div>
                            <div class="admin-fields admin-fields--two">
                                <label>
                                    <span>Source</span>
                                    <select name="source_mode">
                                        <option value="file" <?= old('source_mode', 'file') === 'file' ? 'selected' : ''; ?>>File upload</option>
                                        <option value="url" <?= old('source_mode') === 'url' ? 'selected' : ''; ?>>External URL</option>
                                    </select>
                                </label>
                                <label>
                                    <span>External URL</span>
                                    <input type="url" name="external_url" value="<?= e(old('external_url')); ?>" placeholder="https://...">
                                </label>
                                <label>
                                    <span>Video file</span>
                                    <input type="file" name="video_file" accept="video/*">
                                </label>
                                <label>
                                    <span>Poster image</span>
                                    <input type="file" name="poster_file" accept="image/*">
                                </label>
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
                                    <p>Add a custom poster if you do not want the built-in fallback artwork.</p>
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
                            <p><strong>Database:</strong> <?= $dbReady ? 'Ready' : 'Offline'; ?></p>
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
                        <form method="post" enctype="multipart/form-data" class="admin-form-shell">
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
                                        <select name="source_mode">
                                            <option value="file" <?= (string) $editingVideo['source_type'] === 'upload' ? 'selected' : ''; ?>>File upload</option>
                                            <option value="url" <?= (string) $editingVideo['source_type'] !== 'upload' ? 'selected' : ''; ?>>External URL</option>
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
                                    <p>Leave upload fields empty to keep the current media.</p>
                                </div>
                                <div class="admin-fields admin-fields--two">
                                    <label>
                                        <span>External URL</span>
                                        <input type="url" name="external_url" value="<?= e((string) ($editingVideo['original_source_url'] ?? '')); ?>" placeholder="https://...">
                                    </label>
                                    <label>
                                        <span>Video file</span>
                                        <input type="file" name="video_file" accept="video/*">
                                    </label>
                                    <label>
                                        <span>Poster image</span>
                                        <input type="file" name="poster_file" accept="image/*">
                                    </label>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="remove_poster" value="1">
                                        <span>Remove current poster and use the fallback art</span>
                                    </label>
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
                                <p><strong>Published:</strong> <?= e((string) $editingVideo['published_label']); ?></p>
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
                    <div class="grid-fallback">
                        <?php foreach ($libraryVideos as $video): ?>
                            <article class="video-card">
                                <a class="video-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                                    <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>">
                                    <div class="video-card__overlay">
                                        <div class="meta-row">
                                            <span class="pill"><?= e((string) $video['storage_provider']); ?></span>
                                            <span class="pill pill--muted"><?= e((string) $video['source_type']); ?></span>
                                        </div>
                                        <span class="video-card__duration"><?= e((string) $video['duration_label']); ?></span>
                                    </div>
                                </a>
                                <div class="video-card__body">
                                    <label class="bulk-select">
                                        <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="library-bulk-form">
                                        <span>Select</span>
                                    </label>
                                    <h3><?= e($video['title']); ?></h3>
                                    <p><?= e($video['synopsis']); ?></p>
                                    <p class="form-note">Status: <?= e((string) $video['moderation_label']); ?></p>
                                    <div class="video-card__footer">
                                        <span><?= e($video['creator_name']); ?></span>
                                        <a class="text-link" href="<?= e(base_url('admin.php?screen=library&edit=' . urlencode((string) $video['id']))); ?>">Edit</a>
                                    </div>
                                    <div class="admin-card-actions">
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
                    <div class="admin-list">
                        <?php foreach ($moderationVideos as $video): ?>
                            <article class="admin-list-item">
                                <div class="admin-list-item__media">
                                    <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>">
                                </div>
                                <div class="admin-list-item__body">
                                    <label class="bulk-select">
                                        <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="moderation-bulk-form">
                                        <span>Select</span>
                                    </label>
                                    <div class="meta-row">
                                        <span class="pill"><?= e($video['category']); ?></span>
                                        <span class="pill pill--muted"><?= e((string) $video['moderation_label']); ?></span>
                                    </div>
                                    <h3><?= e($video['title']); ?></h3>
                                    <p><?= e($video['creator_name']); ?> / <?= e($video['duration_label']); ?> / <?= e((string) $video['storage_provider']); ?></p>
                                    <form method="post" class="admin-inline-form">
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
                                            <span>Notes</span>
                                            <textarea name="moderation_notes" rows="3"><?= e((string) $video['moderation_notes']); ?></textarea>
                                        </label>
                                        <button class="button" type="submit">Save moderation</button>
                                    </form>
                                </div>
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
                </form>
                <?php if ($users === []): ?>
                    <div class="notice-card">
                        <strong>No users found</strong>
                        <p>Try a different search or create a new account from the public register page.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list">
                        <?php foreach ($users as $managedUser): ?>
                            <article class="admin-list-item">
                                <div class="admin-list-item__body">
                                <div class="meta-row">
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
                                        <p class="form-note">Stripe status: <?= e(subscription_status_label((string) $managedUser['stripe_subscription_status'])); ?></p>
                                    <?php endif; ?>
                                    <p class="form-note">Joined <?= e(format_datetime((string) ($managedUser['created_at'] ?? null))); ?><?php if (!empty($managedUser['last_login_at'])): ?> / Last login <?= e(format_datetime((string) $managedUser['last_login_at'])); ?><?php endif; ?></p>
                                    <form method="post" class="admin-inline-form admin-inline-form--compact">
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
                                </div>
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
                    <p>These values are saved back into `.env` for deployment consistency.</p>
                </div>
                <div class="admin-screen-grid">
                    <form method="post" class="admin-form-shell">
                        <input type="hidden" name="action" value="save_app_settings">
                        <?= csrf_input('admin'); ?>
                        <section class="admin-form-section">
                            <div class="admin-form-section__header">
                                <h3>Branding</h3>
                                <p>Control the visible product name and short lockup.</p>
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
                        <button class="button" type="submit">Save app settings</button>
                    </form>
                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>Managed in .env</h3>
                            <p>General branding and deployment-facing settings stay in `.env` so the public repository never stores live values.</p>
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
                    <p>Everything here is written back to `.env` for safe public-repo workflows.</p>
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
                                <p>Move the platform rules out of the homepage and keep them on their own page.</p>
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
                                <p>Use plain text with blank lines to split paragraphs.</p>
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
                                <p>Keep the privacy explanation editable for public publishing.</p>
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
                                <p>Control both the cookie policy page and the cookie notice shown on public pages.</p>
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
                                    <span>Enable the cookie notice banner on public pages.</span>
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

                        <button class="button" type="submit">Save legal settings</button>
                    </form>
                    <div class="admin-sidebar-stack">
                        <article class="compliance-card">
                            <h3>Managed in .env</h3>
                            <p>Footer copy, policy text, and cookie notice settings are stored in `.env` so you can keep the repository public without shipping live values inside templates.</p>
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
                    <div class="admin-list">
                        <?php foreach ($activityItems as $item): ?>
                            <article class="admin-list-item">
                                <div class="admin-list-item__body">
                                    <div class="meta-row">
                                        <span class="pill"><?= e((string) $item['action']); ?></span>
                                        <span class="pill pill--muted"><?= e((string) $item['target_type']); ?><?php if (!empty($item['target_id'])): ?> #<?= e((string) $item['target_id']); ?><?php endif; ?></span>
                                    </div>
                                    <h3><?= e((string) $item['summary']); ?></h3>
                                    <p><?= e((string) ($item['actor_name'] ?: $item['actor_email'] ?: 'System')); ?> / <?= e(format_datetime((string) ($item['created_at'] ?? null))); ?></p>
                                    <?php if (!empty($item['metadata_json'])): ?>
                                        <p class="form-note"><?= e((string) $item['metadata_json']); ?></p>
                                    <?php endif; ?>
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
    </main>

    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('admin')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
