<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/src/Support/env.php';
load_env_file(ROOT_PATH . '/.env');
load_env_file(ROOT_PATH . '/.env.local');

date_default_timezone_set((string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'));
$GLOBALS['app_config'] = require ROOT_PATH . '/config/app.php';
require ROOT_PATH . '/src/Support/helpers.php';

if (
    (bool) config('security.force_https', false)
    && !request_is_https()
    && strcasecmp(PHP_SAPI, 'cli') !== 0
    && !headers_sent()
) {
    $target = rtrim((string) config('app.base_url', ''), '/');

    if ($target !== '' && strtolower((string) parse_url($target, PHP_URL_SCHEME)) === 'https') {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $target . $requestUri, true, 301);
        exit;
    }
}

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if ((bool) config('security.csp_enabled', true)) {
        $cspHeaderName = (bool) config('security.csp_report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        header($cspHeaderName . ': ' . content_security_policy_header());
    }

    if ((bool) config('security.hsts_enabled', false) && request_is_https()) {
        header('Strict-Transport-Security: max-age=' . max(0, (int) config('security.hsts_max_age', 31536000)) . '; includeSubDomains');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) config('session.name', 'videw_session'));
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    $cookieSecure = (bool) config('session.cookie_secure', false);

    if (!$cookieSecure && request_is_https()) {
        $cookieSecure = true;
    }

    session_set_cookie_params([
        'lifetime' => (int) config('session.cookie_lifetime', 0),
        'path' => '/',
        'secure' => $cookieSecure,
        'httponly' => (bool) config('session.cookie_http_only', true),
        'samesite' => (string) config('session.cookie_same_site', 'Lax'),
    ]);
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDirectory = ROOT_PATH . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDirectory . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
