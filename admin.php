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
    $adminController = new \App\Controllers\AdminController(
        $settingsRepository,
        $adsRepository,
        $auditLogs,
        $creatorApplications,
        $usersRepository,
        $videoRepository,
        $adService,
        $adminVideos,
        $billing,
        $actorId
    );
    $adminController->handle((string) ($_POST['action'] ?? ''), $_POST, $_FILES);
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
<?php
require_once __DIR__ . '/partials/admin/layout_header.php';

// Render the active screen
$screenPartial = __DIR__ . '/partials/admin/' . $screen . '.php';
if (file_exists($screenPartial)) {
    require_once $screenPartial;
} else {
    echo '<div class="notice-card"><strong>Screen not found</strong><p>The requested screen partial does not exist.</p></div>';
}

require_once __DIR__ . '/partials/admin/layout_footer.php';
