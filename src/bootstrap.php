<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/src/Support/env.php';
load_env_file(ROOT_PATH . '/.env');
load_env_file(ROOT_PATH . '/.env.local');

date_default_timezone_set((string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo'));
$GLOBALS['app_config'] = require ROOT_PATH . '/config/app.php';
require ROOT_PATH . '/src/Support/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) config('session.name', 'videw_session'));
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => (int) config('session.cookie_lifetime', 0),
        'path' => '/',
        'secure' => (bool) config('session.cookie_secure', false),
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
