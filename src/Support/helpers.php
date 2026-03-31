<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = $GLOBALS['app_config'] ?? [];

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function base_url(string $path = ''): string
{
    $configuredBaseUrl = rtrim((string) config('app.base_url', ''), '/');
    $baseUrl = runtime_should_use_request_origin($configuredBaseUrl)
        ? request_url()
        : $configuredBaseUrl;

    if ($path === '') {
        return $baseUrl;
    }

    if ($baseUrl === '') {
        return request_url($path);
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return base_url($path);
}

function request_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        return '';
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $https = $https || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host;
}

function request_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($scriptName === '') {
        return '';
    }

    $directory = dirname($scriptName);

    if ($directory === '/' || $directory === '.' || $directory === '\\') {
        return '';
    }

    return rtrim($directory, '/');
}

function request_url(string $path = ''): string
{
    $origin = request_origin();
    $basePath = request_base_path();
    $baseUrl = $origin . $basePath;

    if ($path === '') {
        return rtrim($baseUrl, '/');
    }

    if ($baseUrl === '') {
        return '/' . ltrim($path, '/');
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function runtime_should_use_request_origin(string $configuredUrl): bool
{
    $configuredUrl = trim($configuredUrl);

    if ($configuredUrl === '') {
        return true;
    }

    $requestOrigin = request_origin();

    if ($requestOrigin === '') {
        return false;
    }

    $configuredOrigin = url_origin($configuredUrl);

    return $configuredOrigin !== '' && $configuredOrigin !== $requestOrigin;
}

function url_origin(string $url): string
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);

    if ($scheme === '' || $host === '') {
        return '';
    }

    return $scheme . '://' . $host . ($port ? ':' . $port : '');
}

function local_storage_public_base_url(?string $configuredUrl = null): string
{
    $configuredUrl = rtrim(trim((string) $configuredUrl), '/');

    if ($configuredUrl === '' || runtime_should_use_request_origin($configuredUrl)) {
        return base_url('storage/uploads');
    }

    return $configuredUrl;
}

function gui_runtime_tags(): string
{
    $guiRuntimeUrl = asset('assets/vendor/gui/index.js');

    if (!is_file(ROOT_PATH . '/assets/vendor/gui/index.js')) {
        $guiRuntimeUrl = asset('node_modules/@bragamateus/gui/gui/index.js');
    }

    $importMap = json_encode([
        'imports' => [
            '@bragamateus/gui' => $guiRuntimeUrl,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($importMap) || $importMap === '') {
        $importMap = '{"imports":{}}';
    }

    return '<script type="importmap">' . $importMap . '</script>'
        . "\n"
        . '<script type="module" src="' . e(asset('assets/js/app.js')) . '"></script>';
}

function brand_kicker(): string
{
    return (string) config('app.brand_kicker', 'VIDEW');
}

function brand_title(): string
{
    return trim((string) config('app.brand_title', ''));
}

function brand_lockup(): string
{
    return trim(brand_kicker() . ' ' . brand_title());
}

function age_gate_enabled(): bool
{
    return (bool) config('app.age_gate_enabled', false);
}

function public_head_markup(): string
{
    $scripts = trim((string) config('app.public_head_scripts', ''));

    if ($scripts === '') {
        return '';
    }

    return $scripts . "\n";
}

function creator_avatar_fallback(string $name): string
{
    $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? $name : 'V', 0, 1));

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">
  <defs>
    <linearGradient id="avatarBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f6b73c"/>
      <stop offset="100%" stop-color="#ff8f1f"/>
    </linearGradient>
  </defs>
  <rect width="160" height="160" rx="80" fill="url(#avatarBg)"/>
  <text x="80" y="102" text-anchor="middle" fill="#0a0a0c" font-family="Arial, sans-serif" font-size="70" font-weight="700">{$initial}</text>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function creator_banner_fallback(string $name): string
{
    $safeName = e($name !== '' ? $name : 'Creator');

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1440" height="480" viewBox="0 0 1440 480">
  <defs>
    <linearGradient id="bannerBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0a0a0c"/>
      <stop offset="100%" stop-color="#17171d"/>
    </linearGradient>
    <linearGradient id="bannerAccent" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#ffb12a"/>
      <stop offset="100%" stop-color="#ff8f1f"/>
    </linearGradient>
  </defs>
  <rect width="1440" height="480" fill="url(#bannerBg)"/>
  <circle cx="1140" cy="110" r="220" fill="rgba(255,255,255,0.04)"/>
  <circle cx="1280" cy="140" r="160" fill="rgba(255,255,255,0.03)"/>
  <rect x="88" y="112" width="122" height="8" rx="4" fill="url(#bannerAccent)"/>
  <text x="88" y="228" fill="#ffffff" font-family="Arial, sans-serif" font-size="68" font-weight="700">{$safeName}</text>
  <text x="88" y="282" fill="rgba(255,255,255,0.58)" font-family="Arial, sans-serif" font-size="24">Creator channel</text>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function resolve_creator_media_asset(?string $url, ?string $path, ?string $provider, string $fallback): string
{
    $normalizedProvider = in_array($provider, ['local', 'wasabi', 'external'], true) ? $provider : null;
    $normalizedUrl = trim((string) $url);
    $normalizedPath = trim((string) $path);

    if ($normalizedProvider === 'wasabi' && $normalizedPath !== '' && (string) config('storage.wasabi_private_bucket', '0') === '1') {
        try {
            $storage = new \App\Services\StorageManager();
            $ttl = max(60, min(604800, (int) config('storage.wasabi_signed_url_ttl_seconds', 900)));

            return $storage->wasabiClient()->presignGetObject($normalizedPath, $ttl);
        } catch (Throwable) {
            return $normalizedUrl !== '' ? $normalizedUrl : $fallback;
        }
    }

    if ($normalizedProvider === 'local' && $normalizedPath !== '' && $normalizedUrl === '') {
        return rtrim(local_storage_public_base_url((string) config('storage.local_public_base_url', '')), '/') . '/' . ltrim($normalizedPath, '/');
    }

    return $normalizedUrl !== '' ? $normalizedUrl : $fallback;
}

function creator_public_name(?array $user, string $fallback = 'Creator'): string
{
    if (!is_array($user)) {
        return $fallback;
    }

    $creatorName = trim((string) ($user['creator_display_name'] ?? ''));

    if ($creatorName !== '') {
        return $creatorName;
    }

    $displayName = trim((string) ($user['display_name'] ?? ''));

    return $displayName !== '' ? $displayName : $fallback;
}

function creator_profile_url(?array $user): ?string
{
    if (!is_array($user)) {
        return null;
    }

    $slug = trim((string) ($user['creator_slug'] ?? ''));

    return $slug !== '' ? base_url('channel.php?creator=' . urlencode($slug)) : null;
}

function copy_text(string $key, string $default = ''): string
{
    return (string) config('copy.' . $key, $default);
}

/**
 * @return array<int, string>
 */
function copy_items(string $key): array
{
    $items = config('copy.' . $key, []);

    if (!is_array($items)) {
        return [];
    }

    $result = [];

    foreach ($items as $item) {
        $value = trim((string) $item);

        if ($value !== '') {
            $result[] = $value;
        }
    }

    return $result;
}

function page_lock_class(string $classes = '', bool $lockForAgeGate = true): string
{
    $tokens = preg_split('/\s+/', trim($classes)) ?: [];

    if ($lockForAgeGate && age_gate_enabled() && !is_age_verified()) {
        $tokens[] = 'is-locked';
    }

    $tokens = array_values(array_unique(array_filter($tokens, static fn (string $item): bool => $item !== '')));

    return implode(' ', $tokens);
}

/**
 * @return array<int, array{title:string, description:string, fields:array<int, array{key:string,label:string,type:string,rows?:int}>}>
 */
function copy_admin_sections(): array
{
    return [
        [
            'title' => 'Header and navigation',
            'description' => 'Edit the public nav labels and the slim bar shown at the top of public pages.',
            'fields' => [
                ['key' => 'header.nav.home', 'label' => 'Nav: Home', 'type' => 'text'],
                ['key' => 'header.nav.browse', 'label' => 'Nav: Browse', 'type' => 'text'],
                ['key' => 'header.nav.premium', 'label' => 'Nav: Premium', 'type' => 'text'],
                ['key' => 'header.nav.support', 'label' => 'Nav: Support', 'type' => 'text'],
                ['key' => 'header.nav.studio', 'label' => 'Nav: Studio', 'type' => 'text'],
                ['key' => 'header.nav.channel', 'label' => 'Nav: My channel', 'type' => 'text'],
                ['key' => 'header.nav.admin', 'label' => 'Nav: Admin', 'type' => 'text'],
                ['key' => 'header.nav.account', 'label' => 'Nav: My account', 'type' => 'text'],
                ['key' => 'header.nav.sign_in', 'label' => 'Nav: Sign in', 'type' => 'text'],
                ['key' => 'header.nav.join', 'label' => 'Nav: Join', 'type' => 'text'],
                ['key' => 'header.nav.member_fallback', 'label' => 'Member fallback label', 'type' => 'text'],
                ['key' => 'header.nav.log_out', 'label' => 'Nav: Log out', 'type' => 'text'],
                ['key' => 'header.bar.home.item_1', 'label' => 'Home top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.home.item_2', 'label' => 'Home top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.home.item_3', 'label' => 'Home top bar 3', 'type' => 'text'],
                ['key' => 'header.bar.browse.item_1', 'label' => 'Browse top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.browse.item_2', 'label' => 'Browse top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.browse.item_3', 'label' => 'Browse top bar 3', 'type' => 'text'],
                ['key' => 'header.bar.premium.item_1', 'label' => 'Premium top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.premium.item_2', 'label' => 'Premium top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.premium.item_3', 'label' => 'Premium top bar 3', 'type' => 'text'],
                ['key' => 'header.bar.support.item_1', 'label' => 'Support top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.support.item_2', 'label' => 'Support top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.support.item_3', 'label' => 'Support top bar 3', 'type' => 'text'],
                ['key' => 'header.bar.legal.item_1', 'label' => 'Legal top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.legal.item_2', 'label' => 'Legal top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.legal.item_3', 'label' => 'Legal top bar 3', 'type' => 'text'],
                ['key' => 'header.bar.account.item_1', 'label' => 'Account top bar 1', 'type' => 'text'],
                ['key' => 'header.bar.account.item_2', 'label' => 'Account top bar 2', 'type' => 'text'],
                ['key' => 'header.bar.account.item_3', 'label' => 'Account top bar 3', 'type' => 'text'],
            ],
        ],
        [
            'title' => 'Shared actions',
            'description' => 'These labels are reused across multiple public pages and cards.',
            'fields' => [
                ['key' => 'common.watch_now', 'label' => 'Watch now', 'type' => 'text'],
                ['key' => 'common.open_support', 'label' => 'Open support', 'type' => 'text'],
                ['key' => 'common.browse_videos', 'label' => 'Browse videos', 'type' => 'text'],
                ['key' => 'common.see_premium', 'label' => 'See Premium', 'type' => 'text'],
                ['key' => 'common.view_plans', 'label' => 'View plans', 'type' => 'text'],
                ['key' => 'common.create_account', 'label' => 'Create account', 'type' => 'text'],
                ['key' => 'common.go_to_account', 'label' => 'Go to my account', 'type' => 'text'],
                ['key' => 'common.back_to_home', 'label' => 'Back to home', 'type' => 'text'],
                ['key' => 'common.back_to_sign_in', 'label' => 'Back to sign in', 'type' => 'text'],
                ['key' => 'common.back_to_browse', 'label' => 'Back to browse', 'type' => 'text'],
            ],
        ],
        [
            'title' => 'Home page',
            'description' => 'Control the homepage hero, discovery sections, and membership messaging.',
            'fields' => [
                ['key' => 'home.hero_eyebrow', 'label' => 'Hero eyebrow', 'type' => 'text'],
                ['key' => 'home.hero_title', 'label' => 'Hero title', 'type' => 'text'],
                ['key' => 'home.hero_description', 'label' => 'Hero description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.hero_primary_cta', 'label' => 'Hero primary CTA', 'type' => 'text'],
                ['key' => 'home.hero_secondary_cta', 'label' => 'Hero secondary CTA', 'type' => 'text'],
                ['key' => 'home.fallback_notice_title', 'label' => 'Fallback notice title', 'type' => 'text'],
                ['key' => 'home.fallback_notice_text', 'label' => 'Fallback notice text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.featured_eyebrow', 'label' => 'Featured eyebrow', 'type' => 'text'],
                ['key' => 'home.featured_title', 'label' => 'Featured title', 'type' => 'text'],
                ['key' => 'home.featured_description', 'label' => 'Featured description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.quick_eyebrow', 'label' => 'Quick browse eyebrow', 'type' => 'text'],
                ['key' => 'home.quick_title', 'label' => 'Quick browse title', 'type' => 'text'],
                ['key' => 'home.quick_description', 'label' => 'Quick browse description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.next_eyebrow', 'label' => 'Next step eyebrow', 'type' => 'text'],
                ['key' => 'home.next_title', 'label' => 'Next step title', 'type' => 'text'],
                ['key' => 'home.next_description', 'label' => 'Next step description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.next_primary_cta', 'label' => 'Next step primary CTA', 'type' => 'text'],
                ['key' => 'home.next_secondary_cta', 'label' => 'Next step secondary CTA', 'type' => 'text'],
                ['key' => 'home.membership_eyebrow', 'label' => 'Membership eyebrow', 'type' => 'text'],
                ['key' => 'home.membership_title', 'label' => 'Membership title', 'type' => 'text'],
                ['key' => 'home.membership_description', 'label' => 'Membership description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.free_badge', 'label' => 'Free badge', 'type' => 'text'],
                ['key' => 'home.free_title', 'label' => 'Free card title', 'type' => 'text'],
                ['key' => 'home.free_text', 'label' => 'Free card text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.free_cta', 'label' => 'Free card CTA', 'type' => 'text'],
                ['key' => 'home.premium_badge', 'label' => 'Premium badge', 'type' => 'text'],
                ['key' => 'home.premium_title', 'label' => 'Premium card title', 'type' => 'text'],
                ['key' => 'home.premium_text', 'label' => 'Premium card text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'home.premium_primary_cta', 'label' => 'Premium card primary CTA', 'type' => 'text'],
                ['key' => 'home.premium_secondary_cta', 'label' => 'Premium card secondary CTA', 'type' => 'text'],
            ],
        ],
        [
            'title' => 'Browse and catalog',
            'description' => 'Edit the browse page intro, catalog UI labels, and support CTA block.',
            'fields' => [
                ['key' => 'browse.meta_title', 'label' => 'Browse page title', 'type' => 'text'],
                ['key' => 'browse.meta_description', 'label' => 'Browse meta description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'browse.hero_eyebrow', 'label' => 'Browse eyebrow', 'type' => 'text'],
                ['key' => 'browse.hero_title', 'label' => 'Browse hero title', 'type' => 'text'],
                ['key' => 'browse.hero_description', 'label' => 'Browse hero description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'browse.hero_primary_cta', 'label' => 'Browse hero primary CTA', 'type' => 'text'],
                ['key' => 'browse.hero_secondary_cta', 'label' => 'Browse hero secondary CTA', 'type' => 'text'],
                ['key' => 'browse.featured_eyebrow', 'label' => 'Browse featured eyebrow', 'type' => 'text'],
                ['key' => 'browse.featured_title', 'label' => 'Browse featured title', 'type' => 'text'],
                ['key' => 'browse.featured_description', 'label' => 'Browse featured description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'browse.library_eyebrow', 'label' => 'Library eyebrow', 'type' => 'text'],
                ['key' => 'browse.library_title', 'label' => 'Library title', 'type' => 'text'],
                ['key' => 'browse.library_description', 'label' => 'Library description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'browse.help_eyebrow', 'label' => 'Help eyebrow', 'type' => 'text'],
                ['key' => 'browse.help_title', 'label' => 'Help title', 'type' => 'text'],
                ['key' => 'browse.help_description', 'label' => 'Help description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'browse.help_primary_cta', 'label' => 'Help primary CTA', 'type' => 'text'],
                ['key' => 'browse.help_secondary_signed_in', 'label' => 'Help CTA for signed-in users', 'type' => 'text'],
                ['key' => 'browse.help_secondary_guest', 'label' => 'Help CTA for guests', 'type' => 'text'],
                ['key' => 'browse.catalog.search_label', 'label' => 'Catalog search label', 'type' => 'text'],
                ['key' => 'browse.catalog.search_placeholder', 'label' => 'Catalog search placeholder', 'type' => 'text'],
                ['key' => 'browse.catalog.results_label', 'label' => 'Catalog results label', 'type' => 'text'],
                ['key' => 'browse.catalog.premium_label', 'label' => 'Catalog premium stat label', 'type' => 'text'],
                ['key' => 'browse.catalog.total_label', 'label' => 'Catalog total stat label', 'type' => 'text'],
                ['key' => 'browse.catalog.category_label', 'label' => 'Catalog category label', 'type' => 'text'],
                ['key' => 'browse.catalog.access_label', 'label' => 'Catalog access label', 'type' => 'text'],
                ['key' => 'browse.catalog.sort_label', 'label' => 'Catalog sort label', 'type' => 'text'],
                ['key' => 'browse.catalog.access_all', 'label' => 'Access filter: all', 'type' => 'text'],
                ['key' => 'browse.catalog.access_free', 'label' => 'Access filter: free', 'type' => 'text'],
                ['key' => 'browse.catalog.access_premium', 'label' => 'Access filter: premium', 'type' => 'text'],
                ['key' => 'browse.catalog.sort_recent', 'label' => 'Sort: recent', 'type' => 'text'],
                ['key' => 'browse.catalog.sort_duration', 'label' => 'Sort: duration', 'type' => 'text'],
                ['key' => 'browse.catalog.sort_title', 'label' => 'Sort: title', 'type' => 'text'],
                ['key' => 'browse.catalog.results_one', 'label' => 'Results singular', 'type' => 'text'],
                ['key' => 'browse.catalog.results_many', 'label' => 'Results plural', 'type' => 'text'],
                ['key' => 'browse.catalog.summary_fallback', 'label' => 'Fallback summary', 'type' => 'text'],
                ['key' => 'browse.catalog.summary_live', 'label' => 'Live summary', 'type' => 'text'],
                ['key' => 'browse.catalog.empty_eyebrow', 'label' => 'Empty state eyebrow', 'type' => 'text'],
                ['key' => 'browse.catalog.empty_title', 'label' => 'Empty state title', 'type' => 'text'],
                ['key' => 'browse.catalog.empty_text', 'label' => 'Empty state text', 'type' => 'textarea', 'rows' => 3],
            ],
        ],
        [
            'title' => 'Premium and support pages',
            'description' => 'Control pricing, support, and guidance text on the plans and support pages.',
            'fields' => [
                ['key' => 'premium.hero_eyebrow', 'label' => 'Premium eyebrow', 'type' => 'text'],
                ['key' => 'premium.hero_primary_cta', 'label' => 'Premium hero CTA', 'type' => 'text'],
                ['key' => 'premium.hero_manage_cta', 'label' => 'Premium hero manage CTA', 'type' => 'text'],
                ['key' => 'premium.hero_guest_cta', 'label' => 'Premium guest CTA', 'type' => 'text'],
                ['key' => 'premium.hero_secondary_cta', 'label' => 'Premium secondary CTA', 'type' => 'text'],
                ['key' => 'premium.disabled_title', 'label' => 'Premium unavailable title', 'type' => 'text'],
                ['key' => 'premium.disabled_text_public', 'label' => 'Premium unavailable text (public)', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.disabled_text_admin', 'label' => 'Premium unavailable text (admin)', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.disabled_link', 'label' => 'Premium unavailable link', 'type' => 'text'],
                ['key' => 'premium.plans_eyebrow', 'label' => 'Plans eyebrow', 'type' => 'text'],
                ['key' => 'premium.plans_title', 'label' => 'Plans title', 'type' => 'text'],
                ['key' => 'premium.plans_description', 'label' => 'Plans description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.free_title', 'label' => 'Free plan title', 'type' => 'text'],
                ['key' => 'premium.free_text', 'label' => 'Free plan text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.premium_checkout_cta', 'label' => 'Premium checkout CTA', 'type' => 'text'],
                ['key' => 'premium.premium_guest_cta', 'label' => 'Premium guest CTA', 'type' => 'text'],
                ['key' => 'premium.rules_eyebrow', 'label' => 'Rules eyebrow', 'type' => 'text'],
                ['key' => 'premium.rules_title', 'label' => 'Access rules title', 'type' => 'text'],
                ['key' => 'premium.rules_description', 'label' => 'Access rules description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.support_eyebrow', 'label' => 'Support eyebrow', 'type' => 'text'],
                ['key' => 'premium.support_title', 'label' => 'Premium support title', 'type' => 'text'],
                ['key' => 'premium.support_text', 'label' => 'Premium support text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'premium.support_cta', 'label' => 'Premium support CTA', 'type' => 'text'],
                ['key' => 'support.meta_title', 'label' => 'Support page title', 'type' => 'text'],
                ['key' => 'support.meta_description', 'label' => 'Support meta description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.hero_eyebrow', 'label' => 'Support eyebrow', 'type' => 'text'],
                ['key' => 'support.hero_title', 'label' => 'Support hero title', 'type' => 'text'],
                ['key' => 'support.hero_description', 'label' => 'Support hero description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.hero_primary_cta', 'label' => 'Support primary CTA', 'type' => 'text'],
                ['key' => 'support.hero_secondary_cta', 'label' => 'Support secondary CTA', 'type' => 'text'],
                ['key' => 'support.email_title', 'label' => 'Support email title', 'type' => 'text'],
                ['key' => 'support.email_empty', 'label' => 'Support email empty state', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.best_for_title', 'label' => 'Best for title', 'type' => 'text'],
                ['key' => 'support.best_for_text', 'label' => 'Best for text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.topics_eyebrow', 'label' => 'Support topics eyebrow', 'type' => 'text'],
                ['key' => 'support.accounts_badge', 'label' => 'Accounts badge', 'type' => 'text'],
                ['key' => 'support.accounts_title', 'label' => 'Accounts title', 'type' => 'text'],
                ['key' => 'support.accounts_text', 'label' => 'Accounts text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.billing_badge', 'label' => 'Billing badge', 'type' => 'text'],
                ['key' => 'support.billing_title', 'label' => 'Billing title', 'type' => 'text'],
                ['key' => 'support.billing_text', 'label' => 'Billing text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.billing_link_signed_in', 'label' => 'Billing signed-in link', 'type' => 'text'],
                ['key' => 'support.policies_badge', 'label' => 'Policies badge', 'type' => 'text'],
                ['key' => 'support.policies_title', 'label' => 'Policies title', 'type' => 'text'],
                ['key' => 'support.policies_text', 'label' => 'Policies text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.topics_title', 'label' => 'Support topics title', 'type' => 'text'],
                ['key' => 'support.topics_description', 'label' => 'Support topics description', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.discovery_eyebrow', 'label' => 'Discovery eyebrow', 'type' => 'text'],
                ['key' => 'support.discovery_title', 'label' => 'Support discovery title', 'type' => 'text'],
                ['key' => 'support.discovery_text', 'label' => 'Support discovery text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'support.discovery_primary_cta', 'label' => 'Support discovery primary CTA', 'type' => 'text'],
                ['key' => 'support.discovery_secondary_cta', 'label' => 'Support discovery secondary CTA', 'type' => 'text'],
            ],
        ],
        [
            'title' => 'Watch, account, and auth',
            'description' => 'Edit the player page, account area, sign-in, register, reset, and 2FA flows.',
            'fields' => [
                ['key' => 'watch.missing_title', 'label' => 'Watch missing title', 'type' => 'text'],
                ['key' => 'watch.missing_eyebrow', 'label' => 'Watch missing eyebrow', 'type' => 'text'],
                ['key' => 'watch.missing_text', 'label' => 'Watch missing text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.missing_cta', 'label' => 'Watch missing CTA', 'type' => 'text'],
                ['key' => 'watch.stage_back_cta', 'label' => 'Watch back CTA', 'type' => 'text'],
                ['key' => 'watch.stage_premium_cta', 'label' => 'Watch stage premium CTA', 'type' => 'text'],
                ['key' => 'watch.premium_badge', 'label' => 'Watch premium badge', 'type' => 'text'],
                ['key' => 'watch.premium_text', 'label' => 'Watch premium text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.premium_user_cta', 'label' => 'Watch premium CTA for users', 'type' => 'text'],
                ['key' => 'watch.premium_guest_cta', 'label' => 'Watch premium CTA for guests', 'type' => 'text'],
                ['key' => 'watch.premium_secondary_cta', 'label' => 'Watch premium secondary CTA', 'type' => 'text'],
                ['key' => 'watch.preview_badge', 'label' => 'Watch preview badge', 'type' => 'text'],
                ['key' => 'watch.preview_text', 'label' => 'Watch preview unavailable text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.notice_title', 'label' => 'Watch notice title', 'type' => 'text'],
                ['key' => 'watch.notice_text', 'label' => 'Watch notice text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.plan_title', 'label' => 'Watch plan title', 'type' => 'text'],
                ['key' => 'watch.plan_text_blocked', 'label' => 'Watch plan text (blocked)', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.help_title', 'label' => 'Watch help title', 'type' => 'text'],
                ['key' => 'watch.help_text', 'label' => 'Watch help text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'watch.related_eyebrow', 'label' => 'Watch related eyebrow', 'type' => 'text'],
                ['key' => 'watch.related_title', 'label' => 'Watch related title', 'type' => 'text'],
                ['key' => 'account.eyebrow', 'label' => 'Account eyebrow', 'type' => 'text'],
                ['key' => 'account.intro', 'label' => 'Account intro', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'account.subscription_title', 'label' => 'Account subscription title', 'type' => 'text'],
                ['key' => 'account.security_title', 'label' => 'Account security title', 'type' => 'text'],
                ['key' => 'account.password_reset_title', 'label' => 'Account password reset title', 'type' => 'text'],
                ['key' => 'account.password_reset_text', 'label' => 'Account password reset text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'account.primary_cta', 'label' => 'Account primary CTA', 'type' => 'text'],
                ['key' => 'account.secondary_cta', 'label' => 'Account secondary CTA', 'type' => 'text'],
                ['key' => 'account.admin_cta', 'label' => 'Account admin CTA', 'type' => 'text'],
                ['key' => 'account.logout_cta', 'label' => 'Account logout CTA', 'type' => 'text'],
                ['key' => 'auth.login.eyebrow', 'label' => 'Login eyebrow', 'type' => 'text'],
                ['key' => 'auth.login.heading', 'label' => 'Login heading', 'type' => 'text'],
                ['key' => 'auth.login.text', 'label' => 'Login text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.login.email', 'label' => 'Login: email label', 'type' => 'text'],
                ['key' => 'auth.login.password', 'label' => 'Login: password label', 'type' => 'text'],
                ['key' => 'auth.login.submit', 'label' => 'Login submit', 'type' => 'text'],
                ['key' => 'auth.login.forgot', 'label' => 'Login forgot link', 'type' => 'text'],
                ['key' => 'auth.login.register_prompt', 'label' => 'Login register prompt', 'type' => 'text'],
                ['key' => 'auth.login.register_link', 'label' => 'Login register link', 'type' => 'text'],
                ['key' => 'auth.register.eyebrow', 'label' => 'Register eyebrow', 'type' => 'text'],
                ['key' => 'auth.register.heading', 'label' => 'Register heading', 'type' => 'text'],
                ['key' => 'auth.register.text', 'label' => 'Register text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.register.display_name', 'label' => 'Register: display name label', 'type' => 'text'],
                ['key' => 'auth.register.email', 'label' => 'Register: email label', 'type' => 'text'],
                ['key' => 'auth.register.birth_date', 'label' => 'Register: birth date label', 'type' => 'text'],
                ['key' => 'auth.register.password', 'label' => 'Register: password label', 'type' => 'text'],
                ['key' => 'auth.register.password_confirmation', 'label' => 'Register: password confirmation label', 'type' => 'text'],
                ['key' => 'auth.register.terms', 'label' => 'Register terms text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.register.submit', 'label' => 'Register submit', 'type' => 'text'],
                ['key' => 'auth.forgot.heading', 'label' => 'Forgot heading', 'type' => 'text'],
                ['key' => 'auth.forgot.text', 'label' => 'Forgot text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.forgot.email', 'label' => 'Forgot: email label', 'type' => 'text'],
                ['key' => 'auth.forgot.submit', 'label' => 'Forgot submit', 'type' => 'text'],
                ['key' => 'auth.forgot.preview_enabled', 'label' => 'Forgot preview enabled text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.forgot.preview_disabled', 'label' => 'Forgot preview disabled text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.forgot.preview_title', 'label' => 'Forgot preview title', 'type' => 'text'],
                ['key' => 'auth.forgot.preview_link', 'label' => 'Forgot preview link', 'type' => 'text'],
                ['key' => 'auth.reset.heading', 'label' => 'Reset heading', 'type' => 'text'],
                ['key' => 'auth.reset.text', 'label' => 'Reset text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.reset.password', 'label' => 'Reset: password label', 'type' => 'text'],
                ['key' => 'auth.reset.password_confirmation', 'label' => 'Reset: password confirmation label', 'type' => 'text'],
                ['key' => 'auth.reset.submit', 'label' => 'Reset submit', 'type' => 'text'],
                ['key' => 'auth.reset.success_cta', 'label' => 'Reset success CTA', 'type' => 'text'],
                ['key' => 'auth.reset.invalid_title', 'label' => 'Reset invalid title', 'type' => 'text'],
                ['key' => 'auth.reset.invalid_text', 'label' => 'Reset invalid text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.mfa.heading', 'label' => 'MFA heading', 'type' => 'text'],
                ['key' => 'auth.mfa.text', 'label' => 'MFA text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'auth.mfa.code_label', 'label' => 'MFA code label', 'type' => 'text'],
                ['key' => 'auth.mfa.submit', 'label' => 'MFA submit', 'type' => 'text'],
                ['key' => 'auth.mfa.note', 'label' => 'MFA note', 'type' => 'textarea', 'rows' => 3],
            ],
        ],
        [
            'title' => 'User feedback messages',
            'description' => 'Control the success and error messages shown during sign-in, account recovery, security, and Premium billing flows.',
            'fields' => [
                ['key' => 'messages.common.security_token_expired', 'label' => 'Security token expired message', 'type' => 'text'],
                ['key' => 'messages.common.sign_in_required', 'label' => 'Sign-in required message', 'type' => 'text'],
                ['key' => 'messages.auth.login_unavailable', 'label' => 'Login unavailable message', 'type' => 'text'],
                ['key' => 'messages.auth.invalid_credentials', 'label' => 'Invalid credentials message', 'type' => 'text'],
                ['key' => 'messages.auth.account_suspended', 'label' => 'Suspended account message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_required', 'label' => 'MFA required message', 'type' => 'text'],
                ['key' => 'messages.auth.login_success', 'label' => 'Login success message', 'type' => 'text'],
                ['key' => 'messages.auth.required_fields', 'label' => 'Required fields message', 'type' => 'text'],
                ['key' => 'messages.auth.invalid_email', 'label' => 'Invalid email message', 'type' => 'text'],
                ['key' => 'messages.auth.password_too_short', 'label' => 'Password length message', 'type' => 'text'],
                ['key' => 'messages.auth.password_confirmation_mismatch', 'label' => 'Password confirmation message', 'type' => 'text'],
                ['key' => 'messages.auth.age_confirmation_required', 'label' => 'Age confirmation message', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'messages.auth.invalid_birth_date', 'label' => 'Invalid birth date message', 'type' => 'text'],
                ['key' => 'messages.auth.registration_age_restricted', 'label' => 'Registration age restriction message', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'messages.auth.email_exists', 'label' => 'Existing email message', 'type' => 'text'],
                ['key' => 'messages.auth.register_success_admin', 'label' => 'First account success message', 'type' => 'text'],
                ['key' => 'messages.auth.register_success_member', 'label' => 'Account created message', 'type' => 'text'],
                ['key' => 'messages.auth.reset_requested', 'label' => 'Reset requested message', 'type' => 'text'],
                ['key' => 'messages.auth.reset_preview_ready', 'label' => 'Reset preview ready message', 'type' => 'text'],
                ['key' => 'messages.auth.reset_invalid_token', 'label' => 'Invalid reset token message', 'type' => 'text'],
                ['key' => 'messages.auth.reset_expired', 'label' => 'Expired reset link message', 'type' => 'text'],
                ['key' => 'messages.auth.reset_success', 'label' => 'Password updated message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_session_expired', 'label' => 'Expired MFA session message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_invalid_code', 'label' => 'Invalid 2FA code message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_backup_success', 'label' => 'Backup code success message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_success', 'label' => 'MFA verified message', 'type' => 'text'],
                ['key' => 'messages.auth.account_not_found', 'label' => 'Account not found message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_already_enabled', 'label' => 'MFA already enabled message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_setup_started', 'label' => 'MFA setup started message', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'messages.auth.mfa_start_first', 'label' => 'Start MFA first message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_invalid_authenticator_code', 'label' => 'Invalid authenticator code message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_enabled_success', 'label' => 'MFA enabled message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_not_enabled', 'label' => 'MFA not enabled message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_disabled_success', 'label' => 'MFA disabled message', 'type' => 'text'],
                ['key' => 'messages.auth.mfa_page_sign_in_again', 'label' => 'MFA page sign-in again message', 'type' => 'text'],
                ['key' => 'messages.auth.throttle_one', 'label' => 'Throttle message (1 minute)', 'type' => 'text'],
                ['key' => 'messages.auth.throttle_many', 'label' => 'Throttle message (multiple minutes, use {minutes})', 'type' => 'text'],
                ['key' => 'messages.billing.premium_unavailable', 'label' => 'Premium unavailable message', 'type' => 'text'],
                ['key' => 'messages.billing.already_premium', 'label' => 'Already premium message', 'type' => 'text'],
                ['key' => 'messages.billing.checkout_start_failed', 'label' => 'Checkout start failed message', 'type' => 'text'],
                ['key' => 'messages.billing.portal_unavailable', 'label' => 'Plan management unavailable message', 'type' => 'text'],
                ['key' => 'messages.billing.portal_open_failed', 'label' => 'Open plan management failed message', 'type' => 'text'],
                ['key' => 'messages.billing.checkout_confirm_failed', 'label' => 'Checkout confirmation failed message', 'type' => 'text'],
                ['key' => 'messages.billing.checkout_account_mismatch', 'label' => 'Checkout account mismatch message', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'messages.billing.checkout_success', 'label' => 'Premium activated message', 'type' => 'text'],
                ['key' => 'messages.billing.checkout_success_guest', 'label' => 'Guest checkout success message', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'messages.billing.checkout_canceled', 'label' => 'Checkout canceled message', 'type' => 'text'],
            ],
        ],
        [
            'title' => 'Age gate',
            'description' => 'When the age gate is enabled, these texts will be used in the modal entry notice.',
            'fields' => [
                ['key' => 'age_gate.eyebrow', 'label' => 'Age gate eyebrow', 'type' => 'text'],
                ['key' => 'age_gate.title', 'label' => 'Age gate title', 'type' => 'text'],
                ['key' => 'age_gate.text', 'label' => 'Age gate text', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'age_gate.item_1', 'label' => 'Age gate bullet 1', 'type' => 'text'],
                ['key' => 'age_gate.item_2', 'label' => 'Age gate bullet 2', 'type' => 'text'],
                ['key' => 'age_gate.item_3', 'label' => 'Age gate bullet 3', 'type' => 'text'],
                ['key' => 'age_gate.confirm', 'label' => 'Age gate confirm button', 'type' => 'text'],
                ['key' => 'age_gate.leave', 'label' => 'Age gate leave button', 'type' => 'text'],
                ['key' => 'age_gate.saving', 'label' => 'Age gate saving status', 'type' => 'text'],
                ['key' => 'age_gate.save_error', 'label' => 'Age gate save error', 'type' => 'text'],
                ['key' => 'age_gate.error', 'label' => 'Age gate generic error', 'type' => 'text'],
            ],
        ],
    ];
}

/**
 * @return array<string, string>
 */
function current_copy_settings(): array
{
    return flatten_copy_settings((array) config('copy', []));
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string>
 */
function flatten_copy_settings(array $source, string $prefix = ''): array
{
    $result = [];

    foreach ($source as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

        if (is_array($value)) {
            $result += flatten_copy_settings($value, $path);
            continue;
        }

        $result[$path] = is_scalar($value) ? (string) $value : '';
    }

    return $result;
}

/**
 * @param array<string, string> $settings
 * @return array<string, string>
 */
function copy_settings_to_env_values(array $settings): array
{
    $payload = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('Could not encode the text settings payload.');
    }

    return [
        'VIDEW_COPY_OVERRIDES_B64' => base64_encode($payload),
    ];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, mixed $value = null): mixed
{
    if (func_num_args() === 2) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function csrf_token(string $key = 'default'): string
{
    $_SESSION['_csrf'] ??= [];

    if (!isset($_SESSION['_csrf'][$key]) || !is_string($_SESSION['_csrf'][$key])) {
        $_SESSION['_csrf'][$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'][$key];
}

function csrf_input(string $key = 'default'): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token($key)) . '">';
}

function logout_button(string $label = 'Log out', string $classes = 'button button--ghost'): string
{
    return '<form method="post" action="' . e(base_url('logout.php')) . '" class="inline-form">'
        . csrf_input('logout')
        . '<button class="' . e($classes) . '" type="submit">' . e($label) . '</button>'
        . '</form>';
}

function ini_size_to_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($unit) {
        'g' => (int) round($number * 1024 * 1024 * 1024),
        'm' => (int) round($number * 1024 * 1024),
        'k' => (int) round($number * 1024),
        default => (int) round($number),
    };
}

function ini_size_label(string $directive): string
{
    $value = trim((string) ini_get($directive));
    return $value !== '' ? strtoupper($value) : 'server default';
}

function request_exceeded_post_max_size(): bool
{
    if (!is_post_request()) {
        return false;
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($contentLength <= 0) {
        return false;
    }

    $postMaxSize = ini_size_to_bytes((string) ini_get('post_max_size'));

    return $postMaxSize > 0 && $contentLength > $postMaxSize;
}

function verify_csrf(?string $token, string $key = 'default'): bool
{
    $sessionToken = $_SESSION['_csrf'][$key] ?? null;

    return is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function require_csrf(string $key = 'default'): void
{
    if (!verify_csrf($_POST['_csrf'] ?? null, $key)) {
        flash('error', 'Security token expired. Try again.');
        redirect('admin.php');
    }
}

function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function remember_input(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function client_ip_address(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ip !== '' ? $ip : 'unknown';
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post_request(): bool
{
    return request_method() === 'POST';
}

function current_user(bool $fresh = false): ?array
{
    $sessionUser = $_SESSION['auth_user'] ?? null;

    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        return null;
    }

    static $resolvedUser = null;
    static $resolvedUserId = null;

    $sessionUserId = (int) $sessionUser['id'];

    if (!$fresh && is_array($resolvedUser) && $resolvedUserId === $sessionUserId) {
        return $resolvedUser;
    }

    try {
        $repository = new \App\Repositories\UserRepository();
        $freshUser = $repository->findById($sessionUserId);

        if (is_array($freshUser)) {
            if ((string) ($freshUser['status'] ?? 'active') !== 'active') {
                unset($_SESSION['auth_user']);
                $resolvedUser = null;
                $resolvedUserId = null;

                return null;
            }

            $_SESSION['auth_user'] = [
                'id' => $freshUser['id'] ?? $sessionUserId,
                'display_name' => $freshUser['display_name'] ?? ($sessionUser['display_name'] ?? 'Account'),
                'email' => $freshUser['email'] ?? ($sessionUser['email'] ?? null),
                'role' => $freshUser['role'] ?? ($sessionUser['role'] ?? 'member'),
                'status' => $freshUser['status'] ?? ($sessionUser['status'] ?? 'active'),
                'account_tier' => $freshUser['account_tier'] ?? ($sessionUser['account_tier'] ?? 'free'),
                'stripe_customer_id' => $freshUser['stripe_customer_id'] ?? ($sessionUser['stripe_customer_id'] ?? null),
                'stripe_subscription_id' => $freshUser['stripe_subscription_id'] ?? ($sessionUser['stripe_subscription_id'] ?? null),
                'stripe_subscription_status' => $freshUser['stripe_subscription_status'] ?? ($sessionUser['stripe_subscription_status'] ?? null),
                'mfa_enabled' => (int) ($freshUser['mfa_enabled'] ?? ($sessionUser['mfa_enabled'] ?? 0)),
                'creator_display_name' => $freshUser['creator_display_name'] ?? ($sessionUser['creator_display_name'] ?? null),
                'creator_slug' => $freshUser['creator_slug'] ?? ($sessionUser['creator_slug'] ?? null),
                'creator_bio' => $freshUser['creator_bio'] ?? ($sessionUser['creator_bio'] ?? null),
                'creator_avatar_url' => $freshUser['creator_avatar_url'] ?? ($sessionUser['creator_avatar_url'] ?? null),
                'creator_avatar_path' => $freshUser['creator_avatar_path'] ?? ($sessionUser['creator_avatar_path'] ?? null),
                'creator_avatar_storage_provider' => $freshUser['creator_avatar_storage_provider'] ?? ($sessionUser['creator_avatar_storage_provider'] ?? null),
                'creator_banner_url' => $freshUser['creator_banner_url'] ?? ($sessionUser['creator_banner_url'] ?? null),
                'creator_banner_path' => $freshUser['creator_banner_path'] ?? ($sessionUser['creator_banner_path'] ?? null),
                'creator_banner_storage_provider' => $freshUser['creator_banner_storage_provider'] ?? ($sessionUser['creator_banner_storage_provider'] ?? null),
                'resolved_creator_avatar_url' => $freshUser['resolved_creator_avatar_url'] ?? ($sessionUser['resolved_creator_avatar_url'] ?? null),
                'resolved_creator_banner_url' => $freshUser['resolved_creator_banner_url'] ?? ($sessionUser['resolved_creator_banner_url'] ?? null),
            ];
            $sessionUser = $_SESSION['auth_user'];
        }
    } catch (Throwable) {
        // Fall back to the session copy when the database is unavailable.
    }

    $resolvedUser = $sessionUser;
    $resolvedUserId = $sessionUserId;

    return $sessionUser;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function is_age_verified(): bool
{
    return isset($_SESSION['age_verified_at']);
}

function ensure_logged_in(): void
{
    if (!is_authenticated()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function is_admin(): bool
{
    return (string) (current_user()['role'] ?? '') === 'admin';
}

function is_creator(): bool
{
    $role = (string) (current_user()['role'] ?? '');

    return in_array($role, ['creator', 'admin'], true);
}

function ensure_admin(): void
{
    ensure_logged_in();

    if (!is_admin()) {
        flash('error', 'You do not have access to this area.');
        redirect('account.php');
    }
}

function ensure_creator(): void
{
    ensure_logged_in();

    if (!is_creator()) {
        flash('error', 'You do not have access to creator tools.');
        redirect('account.php');
    }
}

function years_between(DateTimeImmutable $date): int
{
    $today = new DateTimeImmutable('today');
    return $today->diff($date)->y;
}

function format_datetime(?string $value, string $fallback = 'Not available'): string
{
    if (!$value) {
        return $fallback;
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $fallback;
    }
}

function duration_label(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    if ($hours > 0) {
        return sprintf('%dh %02dmin', $hours, $remaining);
    }

    return sprintf('%dmin', $remaining);
}

function access_label(string $accessLevel): string
{
    return match (normalize_access_level($accessLevel)) {
        'premium' => 'Premium',
        default => 'Free',
    };
}

function normalize_access_level(string $accessLevel): string
{
    return $accessLevel === 'subscriber' ? 'premium' : $accessLevel;
}

function account_tier_label(string $tier): string
{
    return match ($tier) {
        'premium' => 'Premium',
        default => 'Free',
    };
}

function subscription_status_label(?string $status): string
{
    return match (trim((string) $status)) {
        'active' => 'Active',
        'trialing' => 'Trialing',
        'past_due' => 'Past due',
        'canceled' => 'Canceled',
        'unpaid' => 'Unpaid',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Expired',
        default => 'Not subscribed',
    };
}

function user_has_premium_access(?array $user = null): bool
{
    $user ??= current_user();

    if (!$user) {
        return false;
    }

    if ((string) ($user['status'] ?? 'active') !== 'active') {
        return false;
    }

    if ((string) ($user['role'] ?? '') === 'admin') {
        return true;
    }

    return (string) ($user['account_tier'] ?? 'free') === 'premium';
}

function video_requires_premium(array $video): bool
{
    return normalize_access_level((string) ($video['access_level'] ?? 'free')) === 'premium';
}

function can_watch_video(array $video, ?array $user = null): bool
{
    if (!video_requires_premium($video)) {
        return true;
    }

    if (
        is_array($user)
        && !empty($video['creator_user_id'])
        && (int) ($video['creator_user_id'] ?? 0) === (int) ($user['id'] ?? 0)
    ) {
        return true;
    }

    return user_has_premium_access($user);
}

function moderation_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'flagged' => 'Flagged',
        default => 'Draft',
    };
}

function user_status_label(string $status): string
{
    return match ($status) {
        'suspended' => 'Suspended',
        default => 'Active',
    };
}

function poster_listing_data_url(string $title, string $category, int $tone = 0): string
{
    $palettes = [
        ['#0b0b0d', '#1a1b1f', '#ffb12a'],
        ['#0d1014', '#20242b', '#ff9f1c'],
        ['#101010', '#22211c', '#f8b84e'],
        ['#0b0f10', '#1c2629', '#ffb955'],
    ];
    $palette = $palettes[$tone % count($palettes)];

    $initials = mb_strtoupper(mb_substr($title, 0, 1) . mb_substr($category, 0, 1));

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$palette[0]}"/>
      <stop offset="100%" stop-color="{$palette[1]}"/>
    </linearGradient>
    <linearGradient id="shade" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="rgba(0,0,0,0.15)"/>
      <stop offset="100%" stop-color="rgba(0,0,0,0.82)"/>
    </linearGradient>
  </defs>
  <rect width="1280" height="720" fill="url(#bg)"/>
  <circle cx="1040" cy="170" r="220" fill="rgba(255,255,255,0.05)"/>
  <circle cx="1110" cy="130" r="150" fill="rgba(255,255,255,0.03)"/>
  <rect x="0" y="0" width="1280" height="720" fill="url(#shade)"/>
  <rect x="48" y="48" width="1184" height="624" rx="24" fill="none" stroke="rgba(255,255,255,0.08)"/>
  <rect x="84" y="86" width="154" height="14" rx="7" fill="rgba(255,255,255,0.08)"/>
  <rect x="84" y="110" width="108" height="14" rx="7" fill="rgba(255,255,255,0.05)"/>
  <rect x="84" y="518" width="232" height="16" rx="8" fill="{$palette[2]}"/>
  <rect x="84" y="554" width="156" height="16" rx="8" fill="rgba(255,255,255,0.38)"/>
  <text x="80" y="270" fill="rgba(255,255,255,0.12)" font-family="Arial, sans-serif" font-size="240" font-weight="700">{$initials}</text>
  <circle cx="1068" cy="556" r="74" fill="rgba(255,255,255,0.04)"/>
  <circle cx="1068" cy="556" r="56" fill="rgba(255,255,255,0.06)"/>
  <polygon points="1050,528 1102,556 1050,584" fill="#ffffff"/>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function poster_data_url(string $title, string $category, int $tone = 0): string
{
    $palettes = [
        ['#0b0b0d', '#1a1b1f', '#ffb12a'],
        ['#0d1014', '#20242b', '#ff9f1c'],
        ['#101010', '#22211c', '#f8b84e'],
        ['#0b0f10', '#1c2629', '#ffb955'],
    ];
    $palette = $palettes[$tone % count($palettes)];
    $safeBrandLockup = e(brand_lockup());
    $basePoster = poster_listing_data_url($title, $category, $tone);
    $decodedPoster = rawurldecode(substr($basePoster, strlen('data:image/svg+xml;charset=UTF-8,')));

    $svg = str_replace(
        '<rect x="84" y="86" width="154" height="14" rx="7" fill="rgba(255,255,255,0.08)"/>'
        . "\n"
        . '  <rect x="84" y="110" width="108" height="14" rx="7" fill="rgba(255,255,255,0.05)"/>',
        '<rect x="72" y="74" width="190" height="52" rx="10" fill="#ffffff"/>'
        . "\n"
        . '  <rect x="270" y="74" width="124" height="52" rx="10" fill="' . $palette[2] . '"/>'
        . "\n"
        . '  <text x="96" y="108" fill="#0b0b0d" font-family="Arial, sans-serif" font-size="24" font-weight="700" letter-spacing="5">' . $safeBrandLockup . '</text>',
        $decodedPoster
    );

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function page_bootstrap(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json ?: '{}';
}

/**
 * @param array<string, mixed> $video
 * @return array<string, mixed>
 */
function public_catalog_video_payload(array $video): array
{
    return [
        'slug' => (string) ($video['slug'] ?? ''),
        'title' => (string) ($video['title'] ?? ''),
        'synopsis' => (string) ($video['synopsis'] ?? ''),
        'creator_user_id' => $video['creator_user_id'] ?? null,
        'creator_name' => (string) ($video['creator_name'] ?? ''),
        'category' => (string) ($video['category'] ?? ''),
        'access_level' => (string) ($video['access_level'] ?? 'free'),
        'access_label' => (string) ($video['access_label'] ?? 'Free'),
        'duration_minutes' => (int) ($video['duration_minutes'] ?? 0),
        'duration_label' => (string) ($video['duration_label'] ?? '0min'),
        'resolved_poster_url' => (string) (($video['resolved_poster_url'] ?? $video['poster_url']) ?? ''),
        'resolved_listing_poster_url' => (string) (($video['resolved_listing_poster_url'] ?? $video['listing_poster_url'] ?? $video['poster_url']) ?? ''),
        'poster_focus_x' => normalize_poster_focus($video['poster_focus_x'] ?? 50),
        'poster_focus_y' => normalize_poster_focus($video['poster_focus_y'] ?? 50),
        'poster_object_position' => poster_object_position($video),
        'published_at' => $video['published_at'] ?? null,
    ];
}

function normalize_poster_focus(mixed $value): int
{
    $focus = (int) $value;

    if ($focus < 0) {
        return 0;
    }

    if ($focus > 100) {
        return 100;
    }

    return $focus;
}

/**
 * @param array<string, mixed> $video
 */
function poster_object_position(array $video): string
{
    $focusX = normalize_poster_focus($video['poster_focus_x'] ?? 50);
    $focusY = normalize_poster_focus($video['poster_focus_y'] ?? 50);

    return $focusX . '% ' . $focusY . '%';
}

function slugify(string $value): string
{
    $value = trim($value);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $transliterated));
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'video';
}

function uploaded_file_present(array $file): bool
{
    return isset($file['error']) && (int) $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name']);
}

function viewer_session_key(?array $user = null): string
{
    $sessionId = session_id();

    if ($sessionId === '') {
        try {
            $sessionId = bin2hex(random_bytes(16));
        } catch (Throwable) {
            $sessionId = uniqid('viewer-', true);
        }
        $_SESSION['viewer_session_key'] = $sessionId;
    }

    $userId = (string) ($user['id'] ?? '');
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    return hash('sha256', $sessionId . '|' . client_ip_address() . '|' . $userAgent . '|' . $userId);
}

/**
 * @param array<string, string> $settings
 * @return array<string, string>
 */
function storage_settings_to_env_values(array $settings): array
{
    return [
        'VIDEW_STORAGE_DRIVER' => (string) ($settings['upload_driver'] ?? 'local'),
        'VIDEW_WASABI_ENDPOINT' => (string) ($settings['wasabi_endpoint'] ?? ''),
        'VIDEW_WASABI_REGION' => (string) ($settings['wasabi_region'] ?? ''),
        'VIDEW_WASABI_BUCKET' => (string) ($settings['wasabi_bucket'] ?? ''),
        'VIDEW_WASABI_ACCESS_KEY' => (string) ($settings['wasabi_access_key'] ?? ''),
        'VIDEW_WASABI_SECRET_KEY' => (string) ($settings['wasabi_secret_key'] ?? ''),
        'VIDEW_WASABI_PUBLIC_BASE_URL' => (string) ($settings['wasabi_public_base_url'] ?? ''),
        'VIDEW_WASABI_PATH_PREFIX' => (string) ($settings['wasabi_path_prefix'] ?? 'videw'),
        'VIDEW_WASABI_PRIVATE_BUCKET' => (string) ($settings['wasabi_private_bucket'] ?? '0'),
        'VIDEW_WASABI_SIGNED_URL_TTL_SECONDS' => (string) ($settings['wasabi_signed_url_ttl_seconds'] ?? '900'),
        'VIDEW_WASABI_MULTIPART_THRESHOLD_MB' => (string) ($settings['wasabi_multipart_threshold_mb'] ?? '64'),
        'VIDEW_WASABI_MULTIPART_PART_SIZE_MB' => (string) ($settings['wasabi_multipart_part_size_mb'] ?? '16'),
    ];
}

/**
 * @param array<string, string> $settings
 * @return array<string, string>
 */
function app_settings_to_env_values(array $settings): array
{
    return [
        'VIDEW_APP_NAME' => (string) ($settings['app_name'] ?? config('app.name', 'VIDEW')),
        'VIDEW_APP_DESCRIPTION' => (string) ($settings['app_description'] ?? config('app.description', '')),
        'VIDEW_BRAND_KICKER' => (string) ($settings['brand_kicker'] ?? config('app.brand_kicker', 'VIDEW')),
        'VIDEW_BRAND_TITLE' => (string) ($settings['brand_title'] ?? config('app.brand_title', '')),
        'VIDEW_AGE_GATE_ENABLED' => (string) ($settings['age_gate_enabled'] ?? (age_gate_enabled() ? '1' : '0')),
        'VIDEW_BASE_URL' => (string) ($settings['base_url'] ?? config('app.base_url', '')),
        'VIDEW_SUPPORT_EMAIL' => (string) ($settings['support_email'] ?? config('app.support_email', '')),
        'VIDEW_EXIT_URL' => (string) ($settings['exit_url'] ?? config('app.exit_url', 'https://www.google.com')),
        'VIDEW_PUBLIC_HEAD_SCRIPTS' => (string) ($settings['public_head_scripts'] ?? config('app.public_head_scripts', '')),
        'VIDEW_TIMEZONE' => (string) ($settings['timezone'] ?? env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo')),
    ];
}

/**
 * @param array<string, string> $settings
 * @return array<string, string>
 */
function billing_settings_to_env_values(array $settings): array
{
    return [
        'VIDEW_STRIPE_SECRET_KEY' => (string) ($settings['stripe_secret_key'] ?? config('billing.stripe_secret_key', '')),
        'VIDEW_STRIPE_PUBLISHABLE_KEY' => (string) ($settings['stripe_publishable_key'] ?? config('billing.stripe_publishable_key', '')),
        'VIDEW_STRIPE_WEBHOOK_SECRET' => (string) ($settings['stripe_webhook_secret'] ?? config('billing.stripe_webhook_secret', '')),
        'VIDEW_STRIPE_PREMIUM_PRICE_ID' => (string) ($settings['premium_price_id'] ?? config('billing.premium_price_id', '')),
        'VIDEW_STRIPE_PREMIUM_PLAN_NAME' => (string) ($settings['premium_plan_name'] ?? config('billing.premium_plan_name', 'Premium')),
        'VIDEW_STRIPE_PREMIUM_PLAN_COPY' => (string) ($settings['premium_plan_copy'] ?? config('billing.premium_plan_copy', '')),
        'VIDEW_STRIPE_PREMIUM_PRICE_LABEL' => (string) ($settings['premium_price_label'] ?? config('billing.premium_price_label', '')),
    ];
}

/**
 * @param array<string, string> $settings
 * @return array<string, string>
 */
function legal_settings_to_env_values(array $settings): array
{
    return [
        'VIDEW_FOOTER_TAGLINE' => (string) ($settings['footer_tagline'] ?? config('footer.tagline', config('app.description', ''))),
        'VIDEW_FOOTER_USEFUL_TITLE' => (string) ($settings['footer_useful_title'] ?? config('footer.useful_title', 'Useful links')),
        'VIDEW_FOOTER_LEGAL_TITLE' => (string) ($settings['footer_legal_title'] ?? config('footer.legal_title', 'Legal')),
        'VIDEW_FOOTER_SUPPORT_TITLE' => (string) ($settings['footer_support_title'] ?? config('footer.support_title', 'Support')),
        'VIDEW_FOOTER_SUPPORT_COPY' => (string) ($settings['footer_support_copy'] ?? config('footer.support_copy', '')),
        'VIDEW_FOOTER_USEFUL_LINK_1_LABEL' => (string) ($settings['footer_useful_link_1_label'] ?? ''),
        'VIDEW_FOOTER_USEFUL_LINK_1_URL' => (string) ($settings['footer_useful_link_1_url'] ?? ''),
        'VIDEW_FOOTER_USEFUL_LINK_2_LABEL' => (string) ($settings['footer_useful_link_2_label'] ?? ''),
        'VIDEW_FOOTER_USEFUL_LINK_2_URL' => (string) ($settings['footer_useful_link_2_url'] ?? ''),
        'VIDEW_FOOTER_USEFUL_LINK_3_LABEL' => (string) ($settings['footer_useful_link_3_label'] ?? ''),
        'VIDEW_FOOTER_USEFUL_LINK_3_URL' => (string) ($settings['footer_useful_link_3_url'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_1_LABEL' => (string) ($settings['footer_legal_link_1_label'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_1_URL' => (string) ($settings['footer_legal_link_1_url'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_2_LABEL' => (string) ($settings['footer_legal_link_2_label'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_2_URL' => (string) ($settings['footer_legal_link_2_url'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_3_LABEL' => (string) ($settings['footer_legal_link_3_label'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_3_URL' => (string) ($settings['footer_legal_link_3_url'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_4_LABEL' => (string) ($settings['footer_legal_link_4_label'] ?? ''),
        'VIDEW_FOOTER_LEGAL_LINK_4_URL' => (string) ($settings['footer_legal_link_4_url'] ?? ''),
        'VIDEW_RULES_NAV_LABEL' => (string) ($settings['rules_nav_label'] ?? config('legal.rules.nav_label', 'Rules')),
        'VIDEW_RULES_KICKER' => (string) ($settings['rules_kicker'] ?? config('legal.rules.kicker', 'Rules')),
        'VIDEW_RULES_PAGE_TITLE' => (string) ($settings['rules_title'] ?? config('legal.rules.title', '')),
        'VIDEW_RULES_PAGE_INTRO' => (string) ($settings['rules_intro'] ?? config('legal.rules.intro', '')),
        'VIDEW_RULES_CARD_1_TITLE' => (string) ($settings['rules_card_1_title'] ?? ''),
        'VIDEW_RULES_CARD_1_TEXT' => (string) ($settings['rules_card_1_text'] ?? ''),
        'VIDEW_RULES_CARD_2_TITLE' => (string) ($settings['rules_card_2_title'] ?? ''),
        'VIDEW_RULES_CARD_2_TEXT' => (string) ($settings['rules_card_2_text'] ?? ''),
        'VIDEW_RULES_CARD_3_TITLE' => (string) ($settings['rules_card_3_title'] ?? ''),
        'VIDEW_RULES_CARD_3_TEXT' => (string) ($settings['rules_card_3_text'] ?? ''),
        'VIDEW_RULES_CARD_4_TITLE' => (string) ($settings['rules_card_4_title'] ?? ''),
        'VIDEW_RULES_CARD_4_TEXT' => (string) ($settings['rules_card_4_text'] ?? ''),
        'VIDEW_TERMS_KICKER' => (string) ($settings['terms_kicker'] ?? config('legal.terms.kicker', 'Terms')),
        'VIDEW_TERMS_TITLE' => (string) ($settings['terms_title'] ?? config('legal.terms.title', 'Terms of Use')),
        'VIDEW_TERMS_INTRO' => (string) ($settings['terms_intro'] ?? config('legal.terms.intro', '')),
        'VIDEW_TERMS_CONTENT' => (string) ($settings['terms_content'] ?? config('legal.terms.content', '')),
        'VIDEW_PRIVACY_KICKER' => (string) ($settings['privacy_kicker'] ?? config('legal.privacy.kicker', 'Privacy')),
        'VIDEW_PRIVACY_TITLE' => (string) ($settings['privacy_title'] ?? config('legal.privacy.title', 'Privacy Policy')),
        'VIDEW_PRIVACY_INTRO' => (string) ($settings['privacy_intro'] ?? config('legal.privacy.intro', '')),
        'VIDEW_PRIVACY_CONTENT' => (string) ($settings['privacy_content'] ?? config('legal.privacy.content', '')),
        'VIDEW_COOKIES_KICKER' => (string) ($settings['cookies_kicker'] ?? config('legal.cookies.kicker', 'Cookies')),
        'VIDEW_COOKIES_TITLE' => (string) ($settings['cookies_title'] ?? config('legal.cookies.title', 'Cookie Policy')),
        'VIDEW_COOKIES_INTRO' => (string) ($settings['cookies_intro'] ?? config('legal.cookies.intro', '')),
        'VIDEW_COOKIES_CONTENT' => (string) ($settings['cookies_content'] ?? config('legal.cookies.content', '')),
        'VIDEW_COOKIE_NOTICE_ENABLED' => (string) ($settings['cookie_notice_enabled'] ?? (config('cookie_notice.enabled', true) ? '1' : '0')),
        'VIDEW_COOKIE_NOTICE_TITLE' => (string) ($settings['cookie_notice_title'] ?? config('cookie_notice.title', 'Cookie notice')),
        'VIDEW_COOKIE_NOTICE_TEXT' => (string) ($settings['cookie_notice_text'] ?? config('cookie_notice.text', '')),
        'VIDEW_COOKIE_NOTICE_ACCEPT_LABEL' => (string) ($settings['cookie_notice_accept_label'] ?? config('cookie_notice.accept_label', 'Accept')),
        'VIDEW_COOKIE_NOTICE_LINK_LABEL' => (string) ($settings['cookie_notice_link_label'] ?? config('cookie_notice.link_label', 'Read cookie policy')),
        'VIDEW_COOKIE_NOTICE_LINK_URL' => (string) ($settings['cookie_notice_link_url'] ?? config('cookie_notice.link_url', 'cookies.php')),
    ];
}

function resolve_site_url(?string $value, string $fallback = ''): string
{
    $candidate = trim((string) $value);

    if ($candidate === '') {
        $candidate = trim($fallback);
    }

    if ($candidate === '') {
        return '';
    }

    if (
        preg_match('/^(?:https?:)?\/\//i', $candidate) === 1
        || preg_match('/^(?:mailto:|tel:|#)/i', $candidate) === 1
    ) {
        return $candidate;
    }

    return base_url(ltrim($candidate, '/'));
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function default_bootstrap_payload(string $page, array $overrides = []): array
{
    return array_replace([
        'page' => $page,
        'baseUrl' => base_url(),
        'exitUrl' => (string) config('app.exit_url'),
        'ageVerified' => is_age_verified(),
        'ageGate' => [
            'enabled' => $page !== 'admin' && age_gate_enabled(),
            'eyebrow' => copy_text('age_gate.eyebrow', '18+ ONLY'),
            'title' => copy_text('age_gate.title', 'Before you enter'),
            'text' => copy_text('age_gate.text', 'This site contains age-restricted content and is only for people who are 18 or older.'),
            'item1' => copy_text('age_gate.item_1', 'By entering, you confirm that you are 18 or older.'),
            'item2' => copy_text('age_gate.item_2', 'Restricted content stays clearly marked across the site.'),
            'item3' => copy_text('age_gate.item_3', 'You can leave this page at any time.'),
            'confirmLabel' => copy_text('age_gate.confirm', 'I am 18+'),
            'leaveLabel' => copy_text('age_gate.leave', 'Leave'),
            'savingLabel' => copy_text('age_gate.saving', 'Saving your confirmation...'),
            'saveErrorLabel' => copy_text('age_gate.save_error', 'We could not save your confirmation.'),
            'errorLabel' => copy_text('age_gate.error', 'Something went wrong. Please try again.'),
        ],
        'catalogCopy' => [
            'watchNow' => copy_text('common.watch_now', 'Watch now'),
            'searchLabel' => copy_text('browse.catalog.search_label', 'Search by title or creator'),
            'searchPlaceholder' => copy_text('browse.catalog.search_placeholder', 'Search titles or creators'),
            'resultsLabel' => copy_text('browse.catalog.results_label', 'Results'),
            'premiumLabel' => copy_text('browse.catalog.premium_label', 'Premium videos'),
            'totalLabel' => copy_text('browse.catalog.total_label', 'Total library'),
            'categoryLabel' => copy_text('browse.catalog.category_label', 'Category'),
            'accessLabel' => copy_text('browse.catalog.access_label', 'Access'),
            'sortLabel' => copy_text('browse.catalog.sort_label', 'Sort'),
            'accessAll' => copy_text('browse.catalog.access_all', 'All'),
            'accessFree' => copy_text('browse.catalog.access_free', 'Free'),
            'accessPremium' => copy_text('browse.catalog.access_premium', 'Premium'),
            'sortRecent' => copy_text('browse.catalog.sort_recent', 'Newest'),
            'sortDuration' => copy_text('browse.catalog.sort_duration', 'Longest'),
            'sortTitle' => copy_text('browse.catalog.sort_title', 'A-Z'),
            'resultsOne' => copy_text('browse.catalog.results_one', 'video found'),
            'resultsMany' => copy_text('browse.catalog.results_many', 'videos found'),
            'summaryFallback' => copy_text('browse.catalog.summary_fallback', 'Preview catalog is active.'),
            'summaryLive' => copy_text('browse.catalog.summary_live', 'Browse the latest videos below.'),
            'emptyEyebrow' => copy_text('browse.catalog.empty_eyebrow', 'NO RESULTS'),
            'emptyTitle' => copy_text('browse.catalog.empty_title', 'No videos match your filters.'),
            'emptyText' => copy_text('browse.catalog.empty_text', 'Try another word or change one of the filters.'),
        ],
        'videos' => [],
        'stats' => [],
        'categories' => [],
        'cookieNotice' => cookie_notice_payload(),
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function cookie_notice_payload(): array
{
    return [
        'enabled' => (bool) config('cookie_notice.enabled', true),
        'title' => (string) config('cookie_notice.title', 'Cookie notice'),
        'text' => (string) config('cookie_notice.text', ''),
        'acceptLabel' => (string) config('cookie_notice.accept_label', 'Accept'),
        'linkLabel' => (string) config('cookie_notice.link_label', 'Read cookie policy'),
        'linkUrl' => resolve_site_url((string) config('cookie_notice.link_url', 'cookies.php'), 'cookies.php'),
        'storageKey' => 'videw_cookie_notice',
    ];
}

function rules_nav_label(): string
{
    return (string) config('legal.rules.nav_label', 'Rules');
}

/**
 * @return array<string, array{title: string, links: array<int, array{label: string, url: string}>}>
 */
function footer_navigation_groups(): array
{
    $groups = [
        'useful' => [
            'title' => (string) config('footer.useful_title', 'Useful links'),
            'links' => [],
        ],
        'legal' => [
            'title' => (string) config('footer.legal_title', 'Legal'),
            'links' => [],
        ],
    ];

    foreach ((array) config('footer.useful_links', []) as $link) {
        $label = trim((string) ($link['label'] ?? ''));
        $url = resolve_site_url((string) ($link['url'] ?? ''));

        if ($label !== '' && $url !== '') {
            $groups['useful']['links'][] = ['label' => $label, 'url' => $url];
        }
    }

    foreach ((array) config('footer.legal_links', []) as $link) {
        $label = trim((string) ($link['label'] ?? ''));
        $url = resolve_site_url((string) ($link['url'] ?? ''));

        if ($label !== '' && $url !== '') {
            $groups['legal']['links'][] = ['label' => $label, 'url' => $url];
        }
    }

    return $groups;
}

/**
 * @return array<string, string>
 */
function footer_support_panel(): array
{
    $supportEmail = (string) config('app.support_email', '');

    return [
        'title' => (string) config('footer.support_title', 'Support'),
        'copy' => (string) config('footer.support_copy', ''),
        'email' => $supportEmail,
        'email_href' => $supportEmail !== '' ? 'mailto:' . $supportEmail : '',
    ];
}

/**
 * @return array<string, mixed>
 */
function legal_page_config(string $page): array
{
    $config = config('legal.' . $page, []);
    return is_array($config) ? $config : [];
}

/**
 * @return array<int, array{title: string, copy: string}>
 */
function rules_page_items(): array
{
    $items = [];

    foreach ((array) config('legal.rules.items', []) as $item) {
        $title = trim((string) ($item['title'] ?? ''));
        $copy = trim((string) ($item['copy'] ?? ''));

        if ($title !== '' || $copy !== '') {
            $items[] = ['title' => $title, 'copy' => $copy];
        }
    }

    return $items;
}

function render_text_blocks(string $content): string
{
    $content = trim($content);

    if ($content === '') {
        return '';
    }

    $blocks = preg_split('/(?:\r\n|\n|\r){2,}/', $content) ?: [];
    $html = [];

    foreach ($blocks as $block) {
        $block = trim($block);

        if ($block === '') {
            continue;
        }

        $lines = preg_split('/\R/', $block) ?: [];
        $isList = $lines !== [] && array_reduce(
            $lines,
            static fn (bool $carry, string $line): bool => $carry && preg_match('/^\s*[-*]\s+/', $line) === 1,
            true
        );

        if ($isList) {
            $items = array_map(
                static fn (string $line): string => '<li>' . e((string) preg_replace('/^\s*[-*]\s+/', '', trim($line))) . '</li>',
                $lines
            );
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        $html[] = '<p>' . nl2br(e($block)) . '</p>';
    }

    return implode("\n", $html);
}
