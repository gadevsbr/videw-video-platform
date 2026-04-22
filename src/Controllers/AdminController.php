<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CreatorApplicationRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Services\AdService;
use App\Services\AdminVideoService;
use App\Services\BillingService;
use RuntimeException;

final class AdminController
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AdRepository $ads,
        private readonly AuditLogRepository $auditLogs,
        private readonly CreatorApplicationRepository $creatorApplications,
        private readonly UserRepository $users,
        private readonly VideoRepository $videos,
        private readonly AdService $adService,
        private readonly AdminVideoService $adminVideos,
        private readonly BillingService $billing,
        private readonly int $actorId
    ) {
    }

    public function handle(string $action, array $post, array $files): void
    {
        if (request_exceeded_post_max_size()) {
            flash(
                'error',
                'The upload is larger than the server limit. Increase post_max_size and upload_max_filesize or upload a smaller file. Current limits: post_max_size '
                . ini_size_label('post_max_size')
                . ' and upload_max_filesize '
                . ini_size_label('upload_max_filesize')
                . '.'
            );
            redirect('admin.php?screen=' . (string) ($_GET['screen'] ?? 'overview'));
        }

        if (!verify_csrf($post['_csrf'] ?? null, 'admin')) {
            flash('error', 'Security token expired. Try again.');
            redirect('admin.php?screen=' . (string) ($_GET['screen'] ?? 'overview'));
        }

        match ($action) {
            'save_storage' => $this->saveStorage($post),
            'save_billing_settings' => $this->saveBillingSettings($post),
            'retry_billing_webhook' => $this->retryBillingWebhook($post),
            'save_app_settings' => $this->saveAppSettings($post),
            'save_ad_slot' => $this->saveAdSlot($post, $files),
            'save_copy_settings' => $this->saveCopySettings($post),
            'save_legal_settings' => $this->saveLegalSettings($post),
            'publish_video' => $this->publishVideo($post, $files),
            'update_video' => $this->updateVideo($post, $files),
            'moderate_video' => $this->moderateVideo($post),
            'toggle_featured' => $this->toggleFeatured($post),
            'delete_video' => $this->deleteVideo($post),
            'bulk_library_action' => $this->bulkLibraryAction($post),
            'bulk_moderation_action' => $this->bulkModerationAction($post),
            'review_creator_application' => $this->reviewCreatorApplication($post),
            'update_user' => $this->updateUser($post),
            default => null,
        };
    }

    private function saveStorage(array $post): void
    {
        try {
            $currentWasabiAccessKey = (string) config('storage.wasabi_access_key', '');
            $currentWasabiSecretKey = (string) config('storage.wasabi_secret_key', '');
            $submittedWasabiAccessKey = trim((string) ($post['wasabi_access_key'] ?? ''));
            $submittedWasabiSecretKey = trim((string) ($post['wasabi_secret_key'] ?? ''));
            $storageSettings = [
                'upload_driver' => in_array((string) ($post['upload_driver'] ?? 'local'), ['local', 'wasabi'], true)
                    ? (string) $post['upload_driver']
                    : 'local',
                'wasabi_endpoint' => trim((string) ($post['wasabi_endpoint'] ?? '')),
                'wasabi_region' => trim((string) ($post['wasabi_region'] ?? '')),
                'wasabi_bucket' => trim((string) ($post['wasabi_bucket'] ?? '')),
                'wasabi_access_key' => $submittedWasabiAccessKey !== '' ? $submittedWasabiAccessKey : $currentWasabiAccessKey,
                'wasabi_secret_key' => $submittedWasabiSecretKey !== '' ? $submittedWasabiSecretKey : $currentWasabiSecretKey,
                'wasabi_public_base_url' => trim((string) ($post['wasabi_public_base_url'] ?? '')),
                'wasabi_path_prefix' => trim((string) ($post['wasabi_path_prefix'] ?? 'videw')),
                'wasabi_private_bucket' => (string) (($post['wasabi_private_bucket'] ?? '') === '1' ? '1' : '0'),
                'wasabi_signed_url_ttl_seconds' => trim((string) ($post['wasabi_signed_url_ttl_seconds'] ?? '900')),
                'wasabi_multipart_threshold_mb' => trim((string) ($post['wasabi_multipart_threshold_mb'] ?? '64')),
                'wasabi_multipart_part_size_mb' => trim((string) ($post['wasabi_multipart_part_size_mb'] ?? '16')),
            ];

            write_env_file_values(ROOT_PATH . '/.env', storage_settings_to_env_values($storageSettings));
            $this->settings->putMany($storageSettings);
            $this->auditLogs->record($this->actorId ?: null, 'storage.saved', 'settings', null, 'Updated storage settings.', [
                'driver' => $storageSettings['upload_driver'],
                'private_bucket' => $storageSettings['wasabi_private_bucket'],
            ]);
            flash('success', 'Storage settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=storage');
    }

    private function saveBillingSettings(array $post): void
    {
        try {
            $currentStripeSecretKey = (string) config('billing.stripe_secret_key', '');
            $currentStripeWebhookSecret = (string) config('billing.stripe_webhook_secret', '');
            $submittedStripeSecretKey = trim((string) ($post['stripe_secret_key'] ?? ''));
            $submittedStripeWebhookSecret = trim((string) ($post['stripe_webhook_secret'] ?? ''));
            $billingSettings = [
                'stripe_secret_key' => $submittedStripeSecretKey !== '' ? $submittedStripeSecretKey : $currentStripeSecretKey,
                'stripe_publishable_key' => trim((string) ($post['stripe_publishable_key'] ?? (string) config('billing.stripe_publishable_key'))),
                'stripe_webhook_secret' => $submittedStripeWebhookSecret !== '' ? $submittedStripeWebhookSecret : $currentStripeWebhookSecret,
                'premium_price_id' => trim((string) ($post['premium_price_id'] ?? (string) config('billing.premium_price_id'))),
                'premium_plan_name' => trim((string) ($post['premium_plan_name'] ?? (string) config('billing.premium_plan_name'))),
                'premium_plan_copy' => trim((string) ($post['premium_plan_copy'] ?? (string) config('billing.premium_plan_copy'))),
                'premium_price_label' => trim((string) ($post['premium_price_label'] ?? (string) config('billing.premium_price_label'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', billing_settings_to_env_values($billingSettings));
            $this->auditLogs->record($this->actorId ?: null, 'billing.saved', 'settings', null, 'Updated payment settings.', [
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

    private function retryBillingWebhook(array $post): void
    {
        $eventId = trim((string) ($post['event_id'] ?? ''));

        try {
            if ($eventId === '') {
                throw new RuntimeException('Webhook event ID is required for retry.');
            }

            $result = $this->billing->retryWebhookEvent($eventId);
            $this->auditLogs->record($this->actorId ?: null, 'billing.webhook_retried', 'settings', null, 'Retried a failed Stripe webhook event.', [
                'type' => $result['type'] ?? '',
                'event_id' => $result['event_id'] ?? $eventId,
                'status' => $result['status'] ?? 'processed',
            ]);
            flash('success', 'Webhook event retried successfully.');
        } catch (RuntimeException $exception) {
            $this->auditLogs->record($this->actorId ?: null, 'billing.webhook_retry_failed', 'settings', null, 'Failed to retry a Stripe webhook event.', [
                'event_id' => $eventId,
                'error' => $exception->getMessage(),
            ]);
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=billing');
    }

    private function saveAppSettings(array $post): void
    {
        try {
            $appSettings = [
                'app_name' => trim((string) ($post['app_name'] ?? (string) config('app.name'))),
                'app_description' => trim((string) ($post['app_description'] ?? (string) config('app.description'))),
                'brand_kicker' => trim((string) ($post['brand_kicker'] ?? brand_kicker())),
                'brand_title' => trim((string) ($post['brand_title'] ?? brand_title())),
                'age_gate_enabled' => isset($post['age_gate_enabled']) ? '1' : '0',
                'base_url' => trim((string) ($post['base_url'] ?? base_url())),
                'support_email' => trim((string) ($post['support_email'] ?? (string) config('app.support_email'))),
                'exit_url' => trim((string) ($post['exit_url'] ?? (string) config('app.exit_url'))),
                'public_head_scripts' => trim((string) ($post['public_head_scripts'] ?? (string) config('app.public_head_scripts'))),
                'github_repository' => trim((string) ($post['github_repository'] ?? (string) config('updates.github_repository'))),
                'current_version' => trim((string) ($post['current_version'] ?? (string) config('updates.current_version'))),
                'timezone' => trim((string) ($post['timezone'] ?? (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', app_settings_to_env_values($appSettings));
            $this->auditLogs->record($this->actorId ?: null, 'app.saved', 'settings', null, 'Updated site settings.', [
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

    private function saveAdSlot(array $post, array $files): void
    {
        try {
            $slotKey = trim((string) ($post['slot_key'] ?? ''));
            $savedAd = $this->adService->saveSlot($slotKey, $post, $files, $this->ads->findBySlot($slotKey));

            $this->auditLogs->record($this->actorId ?: null, 'ad.saved', 'ad_slot', null, 'Updated an ad slot.', [
                'slot' => $slotKey,
                'type' => $savedAd['ad_type'] ?? 'placeholder',
                'active' => !empty($savedAd['is_active']) ? 1 : 0,
            ]);
            flash('success', 'Ad slot saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        $target = trim((string) ($post['return_screen'] ?? 'ads')) ?: 'ads';
        redirect('admin.php?screen=' . urlencode($target) . '&slot=' . urlencode($slotKey));
    }

    private function saveCopySettings(array $post): void
    {
        try {
            $submittedCopy = is_array($post['copy'] ?? null) ? $post['copy'] : [];
            $copySettings = current_copy_settings();

            foreach (array_keys($copySettings) as $copyKey) {
                $formKey = str_replace('.', '__', (string) $copyKey);
                $copySettings[(string) $copyKey] = trim((string) ($submittedCopy[$formKey] ?? $copySettings[(string) $copyKey]));
            }

            write_env_file_values(ROOT_PATH . '/.env', copy_settings_to_env_values($copySettings));
            $this->auditLogs->record($this->actorId ?: null, 'copy.saved', 'settings', null, 'Updated public text settings.', [
                'sections' => count(copy_admin_sections()),
                'fields' => count($copySettings),
            ]);
            flash('success', 'Public text settings saved.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=copy');
    }

    private function saveLegalSettings(array $post): void
    {
        try {
            $legalSettings = [
                'footer_tagline' => trim((string) ($post['footer_tagline'] ?? (string) config('footer.tagline'))),
                'footer_useful_title' => trim((string) ($post['footer_useful_title'] ?? (string) config('footer.useful_title'))),
                'footer_legal_title' => trim((string) ($post['footer_legal_title'] ?? (string) config('footer.legal_title'))),
                'footer_support_title' => trim((string) ($post['footer_support_title'] ?? (string) config('footer.support_title'))),
                'footer_support_copy' => trim((string) ($post['footer_support_copy'] ?? (string) config('footer.support_copy'))),
                'footer_useful_link_1_label' => trim((string) ($post['footer_useful_link_1_label'] ?? '')),
                'footer_useful_link_1_url' => trim((string) ($post['footer_useful_link_1_url'] ?? '')),
                'footer_useful_link_2_label' => trim((string) ($post['footer_useful_link_2_label'] ?? '')),
                'footer_useful_link_2_url' => trim((string) ($post['footer_useful_link_2_url'] ?? '')),
                'footer_useful_link_3_label' => trim((string) ($post['footer_useful_link_3_label'] ?? '')),
                'footer_useful_link_3_url' => trim((string) ($post['footer_useful_link_3_url'] ?? '')),
                'footer_legal_link_1_label' => trim((string) ($post['footer_legal_link_1_label'] ?? '')),
                'footer_legal_link_1_url' => trim((string) ($post['footer_legal_link_1_url'] ?? '')),
                'footer_legal_link_2_label' => trim((string) ($post['footer_legal_link_2_label'] ?? '')),
                'footer_legal_link_2_url' => trim((string) ($post['footer_legal_link_2_url'] ?? '')),
                'footer_legal_link_3_label' => trim((string) ($post['footer_legal_link_3_label'] ?? '')),
                'footer_legal_link_3_url' => trim((string) ($post['footer_legal_link_3_url'] ?? '')),
                'footer_legal_link_4_label' => trim((string) ($post['footer_legal_link_4_label'] ?? '')),
                'footer_legal_link_4_url' => trim((string) ($post['footer_legal_link_4_url'] ?? '')),
                'rules_nav_label' => trim((string) ($post['rules_nav_label'] ?? rules_nav_label())),
                'rules_kicker' => trim((string) ($post['rules_kicker'] ?? (string) config('legal.rules.kicker'))),
                'rules_title' => trim((string) ($post['rules_title'] ?? (string) config('legal.rules.title'))),
                'rules_intro' => trim((string) ($post['rules_intro'] ?? (string) config('legal.rules.intro'))),
                'rules_card_1_title' => trim((string) ($post['rules_card_1_title'] ?? '')),
                'rules_card_1_text' => trim((string) ($post['rules_card_1_text'] ?? '')),
                'rules_card_2_title' => trim((string) ($post['rules_card_2_title'] ?? '')),
                'rules_card_2_text' => trim((string) ($post['rules_card_2_text'] ?? '')),
                'rules_card_3_title' => trim((string) ($post['rules_card_3_title'] ?? '')),
                'rules_card_3_text' => trim((string) ($post['rules_card_3_text'] ?? '')),
                'rules_card_4_title' => trim((string) ($post['rules_card_4_title'] ?? '')),
                'rules_card_4_text' => trim((string) ($post['rules_card_4_text'] ?? '')),
                'terms_kicker' => trim((string) ($post['terms_kicker'] ?? (string) config('legal.terms.kicker'))),
                'terms_title' => trim((string) ($post['terms_title'] ?? (string) config('legal.terms.title'))),
                'terms_intro' => trim((string) ($post['terms_intro'] ?? (string) config('legal.terms.intro'))),
                'terms_content' => trim((string) ($post['terms_content'] ?? (string) config('legal.terms.content'))),
                'privacy_kicker' => trim((string) ($post['privacy_kicker'] ?? (string) config('legal.privacy.kicker'))),
                'privacy_title' => trim((string) ($post['privacy_title'] ?? (string) config('legal.privacy.title'))),
                'privacy_intro' => trim((string) ($post['privacy_intro'] ?? (string) config('legal.privacy.intro'))),
                'privacy_content' => trim((string) ($post['privacy_content'] ?? (string) config('legal.privacy.content'))),
                'cookies_kicker' => trim((string) ($post['cookies_kicker'] ?? (string) config('legal.cookies.kicker'))),
                'cookies_title' => trim((string) ($post['cookies_title'] ?? (string) config('legal.cookies.title'))),
                'cookies_intro' => trim((string) ($post['cookies_intro'] ?? (string) config('legal.cookies.intro'))),
                'cookies_content' => trim((string) ($post['cookies_content'] ?? (string) config('legal.cookies.content'))),
                'cookie_notice_enabled' => (string) (($post['cookie_notice_enabled'] ?? '') === '1' ? '1' : '0'),
                'cookie_notice_title' => trim((string) ($post['cookie_notice_title'] ?? (string) config('cookie_notice.title'))),
                'cookie_notice_text' => trim((string) ($post['cookie_notice_text'] ?? (string) config('cookie_notice.text'))),
                'cookie_notice_accept_label' => trim((string) ($post['cookie_notice_accept_label'] ?? (string) config('cookie_notice.accept_label'))),
                'cookie_notice_link_label' => trim((string) ($post['cookie_notice_link_label'] ?? (string) config('cookie_notice.link_label'))),
                'cookie_notice_link_url' => trim((string) ($post['cookie_notice_link_url'] ?? (string) config('cookie_notice.link_url'))),
            ];

            write_env_file_values(ROOT_PATH . '/.env', legal_settings_to_env_values($legalSettings));
            $this->auditLogs->record($this->actorId ?: null, 'legal.saved', 'settings', null, 'Updated legal pages, footer links, and cookie notice settings.', [
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

    private function publishVideo(array $post, array $files): void
    {
        remember_input([
            'title' => trim((string) ($post['title'] ?? '')),
            'creator_name' => trim((string) ($post['creator_name'] ?? '')),
            'category' => trim((string) ($post['category'] ?? '')),
            'access_level' => trim((string) ($post['access_level'] ?? 'free')),
            'duration_minutes' => trim((string) ($post['duration_minutes'] ?? '0')),
            'synopsis' => trim((string) ($post['synopsis'] ?? '')),
            'source_mode' => trim((string) ($post['source_mode'] ?? '')),
            'external_url' => trim((string) ($post['external_url'] ?? '')),
            'poster_source_mode' => trim((string) ($post['poster_source_mode'] ?? '')),
            'poster_external_url' => trim((string) ($post['poster_external_url'] ?? '')),
            'is_featured' => (string) ($post['is_featured'] ?? ''),
            'moderation_status' => trim((string) ($post['moderation_status'] ?? 'draft')),
            'moderation_reason' => normalize_moderation_reason((string) ($post['moderation_reason'] ?? '')),
            'moderation_notes' => trim((string) ($post['moderation_notes'] ?? '')),
        ]);

        $result = $this->adminVideos->publish($post, $files);

        if ($result['success']) {
            clear_old_input();
            $this->auditLogs->record($this->actorId ?: null, 'video.created', 'video', (int) ($result['video_id'] ?? 0), 'Created a video.', [
                'title' => trim((string) ($post['title'] ?? '')),
                'status' => trim((string) ($post['moderation_status'] ?? 'draft')),
                'reason' => normalize_moderation_reason((string) ($post['moderation_reason'] ?? '')),
            ]);
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=publish');
    }

    private function updateVideo(array $post, array $files): void
    {
        $videoId = (int) ($post['video_id'] ?? 0);
        $result = $this->adminVideos->update($videoId, $post, $files);

        if ($result['success']) {
            $this->auditLogs->record($this->actorId ?: null, 'video.updated', 'video', $videoId, 'Updated a video.', [
                'title' => trim((string) ($post['title'] ?? '')),
                'status' => trim((string) ($post['moderation_status'] ?? 'draft')),
                'reason' => normalize_moderation_reason((string) ($post['moderation_reason'] ?? '')),
            ]);
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=library&edit=' . $videoId);
    }

    private function moderateVideo(array $post): void
    {
        $videoId = (int) ($post['video_id'] ?? 0);
        $status = trim((string) ($post['moderation_status'] ?? 'draft'));
        $reason = normalize_moderation_reason((string) ($post['moderation_reason'] ?? ''));
        $notes = trim((string) ($post['moderation_notes'] ?? ''));

        try {
            $this->videos->updateModeration($videoId, $status, $reason !== '' ? $reason : null, $notes !== '' ? $notes : null);
            $this->auditLogs->record($this->actorId ?: null, 'video.moderated', 'video', $videoId, 'Updated moderation status.', [
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

    private function toggleFeatured(array $post): void
    {
        $videoId = (int) ($post['video_id'] ?? 0);
        $nextValue = (int) ($post['next_value'] ?? 0) === 1;

        try {
            $this->videos->setFeatured($videoId, $nextValue);
            $this->auditLogs->record($this->actorId ?: null, 'video.featured', 'video', $videoId, $nextValue ? 'Marked video as featured.' : 'Removed video from featured.', [
                'is_featured' => $nextValue ? 1 : 0,
            ]);
            flash('success', 'Featured state updated.');
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=library');
    }

    private function deleteVideo(array $post): void
    {
        $videoId = (int) ($post['video_id'] ?? 0);
        $result = $this->adminVideos->delete($videoId);

        if ($result['success']) {
            $this->auditLogs->record($this->actorId ?: null, 'video.deleted', 'video', $videoId, 'Deleted a video and cleaned stored assets.');
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect('admin.php?screen=library');
    }

    private function bulkLibraryAction(array $post): void
    {
        $videoIds = array_values(array_unique(array_filter(array_map('intval', (array) ($post['video_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $bulkAction = trim((string) ($post['bulk_action'] ?? ''));

        if ($videoIds === []) {
            flash('error', 'Select at least one video first.');
            redirect('admin.php?screen=library');
        }

        try {
            $count = match ($bulkAction) {
                'approve' => $this->videos->bulkUpdateModeration($videoIds, 'approved'),
                'draft' => $this->videos->bulkUpdateModeration($videoIds, 'draft'),
                'flagged' => $this->videos->bulkUpdateModeration($videoIds, 'flagged'),
                'feature' => $this->videos->bulkSetFeatured($videoIds, true),
                'unfeature' => $this->videos->bulkSetFeatured($videoIds, false),
                'delete' => $this->adminVideos->bulkDelete($videoIds),
                default => throw new RuntimeException('Invalid bulk action.'),
            };

            $this->auditLogs->record($this->actorId ?: null, 'video.bulk', 'video', null, 'Applied a bulk action in the library.', [
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

    private function bulkModerationAction(array $post): void
    {
        $videoIds = array_values(array_unique(array_filter(array_map('intval', (array) ($post['video_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $bulkAction = trim((string) ($post['bulk_action'] ?? ''));
        $bulkReason = normalize_moderation_reason((string) ($post['bulk_reason'] ?? ''));
        $bulkNotes = trim((string) ($post['bulk_notes'] ?? ''));

        if ($videoIds === []) {
            flash('error', 'Select at least one video first.');
            redirect('admin.php?screen=moderation');
        }

        try {
            $count = match ($bulkAction) {
                'approve' => $this->videos->bulkUpdateModeration($videoIds, 'approved', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'draft' => $this->videos->bulkUpdateModeration($videoIds, 'draft', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'flagged' => $this->videos->bulkUpdateModeration($videoIds, 'flagged', $bulkReason !== '' ? $bulkReason : null, $bulkNotes !== '' ? $bulkNotes : null),
                'delete' => $this->adminVideos->bulkDelete($videoIds),
                default => throw new RuntimeException('Invalid bulk moderation action.'),
            };

            $this->auditLogs->record($this->actorId ?: null, 'video.bulk_moderation', 'video', null, 'Applied a bulk moderation action.', [
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

    private function reviewCreatorApplication(array $post): void
    {
        $applicationId = (int) ($post['application_id'] ?? 0);
        $reviewStatus = trim((string) ($post['review_status'] ?? 'pending'));
        $reviewNotes = trim((string) ($post['review_notes'] ?? ''));

        try {
            $application = $this->creatorApplications->findById($applicationId);

            if (!$application) {
                throw new RuntimeException('Creator request not found.');
            }

            $requestUser = $this->users->findById((int) ($application['user_id'] ?? 0));

            if (!$requestUser) {
                throw new RuntimeException('Creator account not found.');
            }

            if ($reviewStatus === 'approved') {
                $resolvedSlug = $this->users->generateUniqueCreatorSlug((string) ($application['requested_slug'] ?? ''), (int) $requestUser['id']);

                $this->users->updateCreatorProfile((int) $requestUser['id'], [
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
                $this->users->updateAdminFields((int) $requestUser['id'], 'creator', (string) ($requestUser['status'] ?? 'active'));
                $this->videos->syncCreatorIdentity((int) $requestUser['id'], (string) ($application['requested_display_name'] ?? creator_public_name($requestUser)));
            }

            $this->creatorApplications->updateStatus($applicationId, $reviewStatus, $reviewNotes !== '' ? $reviewNotes : null);
            $this->auditLogs->record($this->actorId ?: null, 'creator.reviewed', 'creator_application', $applicationId, 'Reviewed a creator request.', [
                'status' => $reviewStatus,
                'user_id' => (int) ($application['user_id'] ?? 0),
            ]);
            flash('success', $reviewStatus === 'approved' ? 'Creator request approved.' : ($reviewStatus === 'rejected' ? 'Creator request rejected.' : 'Creator request updated.'));
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('admin.php?screen=creator_requests');
    }

    private function updateUser(array $post): void
    {
        $userId = (int) ($post['user_id'] ?? 0);
        $role = trim((string) ($post['role'] ?? 'member'));
        $status = trim((string) ($post['status'] ?? 'active'));

        try {
            $managedUser = $this->users->findById($userId);

            if (!$managedUser) {
                throw new RuntimeException('User not found.');
            }

            $removingActiveAdmin = (string) ($managedUser['role'] ?? '') === 'admin'
                && (string) ($managedUser['status'] ?? 'active') === 'active'
                && ($role !== 'admin' || $status !== 'active');

            if ($userId === $this->actorId && ($role !== 'admin' || $status !== 'active')) {
                throw new RuntimeException('You cannot remove your own active admin access.');
            }

            if ($removingActiveAdmin && $this->users->activeAdminCount() <= 1) {
                throw new RuntimeException('At least one active admin must remain.');
            }

            $this->users->updateAdminFields($userId, $role, $status);
            $this->auditLogs->record($this->actorId ?: null, 'user.updated', 'user', $userId, 'Updated user role or status.', [
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
