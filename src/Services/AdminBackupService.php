<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdRepository;
use App\Repositories\SettingsRepository;

final class AdminBackupService
{
    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly AdRepository $ads = new AdRepository()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(): array
    {
        $settings = $this->settings->all();
        $adSlots = ad_slot_definitions();

        return [
            'meta' => [
                'generated_at' => gmdate('c'),
                'format' => 'videw-admin-backup',
                'version' => 1,
                'app_name' => (string) config('app.name', 'VIDEW'),
                'app_version' => trim((string) config('updates.current_version', '')),
                'contains_secrets' => true,
            ],
            'app' => [
                'name' => (string) config('app.name', ''),
                'description' => (string) config('app.description', ''),
                'brand_kicker' => brand_kicker(),
                'brand_title' => brand_title(),
                'age_gate_enabled' => age_gate_enabled(),
                'base_url' => base_url(),
                'support_email' => (string) config('app.support_email', ''),
                'exit_url' => (string) config('app.exit_url', ''),
                'public_head_scripts' => (string) config('app.public_head_scripts', ''),
                'timezone' => (string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'),
            ],
            'updates' => [
                'github_repository' => (string) config('updates.github_repository', ''),
                'current_version' => (string) config('updates.current_version', ''),
            ],
            'storage' => [
                'upload_driver' => (string) ($settings['upload_driver'] ?? config('storage.default_driver', 'local')),
                'wasabi_endpoint' => (string) ($settings['wasabi_endpoint'] ?? config('storage.wasabi_endpoint', '')),
                'wasabi_region' => (string) ($settings['wasabi_region'] ?? config('storage.wasabi_region', '')),
                'wasabi_bucket' => (string) ($settings['wasabi_bucket'] ?? config('storage.wasabi_bucket', '')),
                'wasabi_access_key' => (string) ($settings['wasabi_access_key'] ?? config('storage.wasabi_access_key', '')),
                'wasabi_secret_key' => (string) ($settings['wasabi_secret_key'] ?? config('storage.wasabi_secret_key', '')),
                'wasabi_public_base_url' => (string) ($settings['wasabi_public_base_url'] ?? config('storage.wasabi_public_base_url', '')),
                'wasabi_path_prefix' => (string) ($settings['wasabi_path_prefix'] ?? config('storage.wasabi_path_prefix', 'videw')),
                'wasabi_private_bucket' => (string) ($settings['wasabi_private_bucket'] ?? config('storage.wasabi_private_bucket', '0')),
                'wasabi_signed_url_ttl_seconds' => (string) ($settings['wasabi_signed_url_ttl_seconds'] ?? config('storage.wasabi_signed_url_ttl_seconds', '900')),
                'wasabi_multipart_threshold_mb' => (string) ($settings['wasabi_multipart_threshold_mb'] ?? config('storage.wasabi_multipart_threshold_mb', '64')),
                'wasabi_multipart_part_size_mb' => (string) ($settings['wasabi_multipart_part_size_mb'] ?? config('storage.wasabi_multipart_part_size_mb', '16')),
            ],
            'billing' => [
                'stripe_secret_key' => (string) config('billing.stripe_secret_key', ''),
                'stripe_publishable_key' => (string) config('billing.stripe_publishable_key', ''),
                'stripe_webhook_secret' => (string) config('billing.stripe_webhook_secret', ''),
                'premium_price_id' => (string) config('billing.premium_price_id', ''),
                'premium_plan_name' => (string) config('billing.premium_plan_name', ''),
                'premium_plan_copy' => (string) config('billing.premium_plan_copy', ''),
                'premium_price_label' => (string) config('billing.premium_price_label', ''),
            ],
            'footer' => config('footer', []),
            'legal' => config('legal', []),
            'cookie_notice' => config('cookie_notice', []),
            'copy' => current_copy_settings(),
            'ads' => [
                'slots' => $adSlots,
                'by_slot' => $this->settings->dbReady() ? $this->ads->allBySlot() : [],
            ],
        ];
    }

    public function filename(): string
    {
        return 'videw-admin-backup-' . gmdate('Ymd-His') . '.json';
    }
}
