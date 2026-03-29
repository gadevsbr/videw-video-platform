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
    $baseUrl = config('app.base_url', '');

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return base_url($path);
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
    return (string) config('app.brand_title', '18+');
}

function brand_lockup(): string
{
    return trim(brand_kicker() . ' ' . brand_title());
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
        flash('error', 'Sign in to access this area.');
        redirect('login.php');
    }
}

function is_admin(): bool
{
    return (string) (current_user()['role'] ?? '') === 'admin';
}

function ensure_admin(): void
{
    ensure_logged_in();

    if (!is_admin()) {
        flash('error', 'Admin access only.');
        redirect('account.php');
    }
}

function years_between(DateTimeImmutable $date): int
{
    $today = new DateTimeImmutable('today');
    return $today->diff($date)->y;
}

function format_datetime(?string $value, string $fallback = 'No date'): string
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
        'creator_name' => (string) ($video['creator_name'] ?? ''),
        'category' => (string) ($video['category'] ?? ''),
        'access_level' => (string) ($video['access_level'] ?? 'free'),
        'access_label' => (string) ($video['access_label'] ?? 'Free'),
        'duration_minutes' => (int) ($video['duration_minutes'] ?? 0),
        'duration_label' => (string) ($video['duration_label'] ?? '0min'),
        'resolved_poster_url' => (string) (($video['resolved_poster_url'] ?? $video['poster_url']) ?? ''),
        'resolved_listing_poster_url' => (string) (($video['resolved_listing_poster_url'] ?? $video['listing_poster_url'] ?? $video['poster_url']) ?? ''),
        'published_at' => $video['published_at'] ?? null,
    ];
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
        'VIDEW_APP_NAME' => (string) ($settings['app_name'] ?? config('app.name', 'VIDEW 18+')),
        'VIDEW_APP_DESCRIPTION' => (string) ($settings['app_description'] ?? config('app.description', '')),
        'VIDEW_BRAND_KICKER' => (string) ($settings['brand_kicker'] ?? config('app.brand_kicker', 'VIDEW')),
        'VIDEW_BRAND_TITLE' => (string) ($settings['brand_title'] ?? config('app.brand_title', '18+')),
        'VIDEW_BASE_URL' => (string) ($settings['base_url'] ?? config('app.base_url', '')),
        'VIDEW_SUPPORT_EMAIL' => (string) ($settings['support_email'] ?? config('app.support_email', '')),
        'VIDEW_EXIT_URL' => (string) ($settings['exit_url'] ?? config('app.exit_url', 'https://www.google.com')),
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
