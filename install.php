<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);

require ROOT_PATH . '/src/Support/env.php';

$envPath = ROOT_PATH . '/.env';
$lockFile = ROOT_PATH . '/storage/runtime/install.lock';
$schemaPath = ROOT_PATH . '/db/schema.sql';
$demoSeedPath = ROOT_PATH . '/db/seed-demo.sql';

load_env_file($envPath);
load_env_file(ROOT_PATH . '/.env.local');

$checks = installer_checks($envPath, $schemaPath, $demoSeedPath);
$formData = [
    'app_name' => trim((string) env_value('VIDEW_APP_NAME', 'VIDEW 18+')),
    'base_url' => trim((string) env_value('VIDEW_BASE_URL', installer_detect_base_url())),
    'support_email' => trim((string) env_value('VIDEW_SUPPORT_EMAIL', '')),
    'timezone' => trim((string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo')),
    'db_host' => trim((string) env_value('VIDEW_DB_HOST', '127.0.0.1')),
    'db_port' => trim((string) env_value('VIDEW_DB_PORT', '3306')),
    'db_database' => trim((string) env_value('VIDEW_DB_DATABASE', 'videw')),
    'db_username' => trim((string) env_value('VIDEW_DB_USERNAME', 'root')),
    'db_password' => (string) env_value('VIDEW_DB_PASSWORD', ''),
    'import_demo' => '1',
];
$errors = [];
$successMessages = [];
$installed = is_file($lockFile);
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($requestMethod === 'POST' && !$installed) {
    $formData = [
        'app_name' => trim((string) ($_POST['app_name'] ?? '')),
        'base_url' => trim((string) ($_POST['base_url'] ?? '')),
        'support_email' => trim((string) ($_POST['support_email'] ?? '')),
        'timezone' => trim((string) ($_POST['timezone'] ?? 'America/Sao_Paulo')),
        'db_host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
        'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
        'db_database' => trim((string) ($_POST['db_database'] ?? '')),
        'db_username' => trim((string) ($_POST['db_username'] ?? '')),
        'db_password' => (string) ($_POST['db_password'] ?? ''),
        'import_demo' => isset($_POST['import_demo']) ? '1' : '0',
    ];

    $errors = installer_validate($formData);

    if (!installer_can_run($checks)) {
        $errors[] = 'The server does not meet the minimum installer requirements yet.';
    }

    if ($errors === []) {
        try {
            $successMessages = installer_run($formData, $envPath, $lockFile, $schemaPath, $demoSeedPath);
            $installed = true;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$title = 'VIDEW Installer';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title); ?></title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0a0b0d;
            --panel: #15171c;
            --panel-soft: #1c1f26;
            --border: rgba(255, 255, 255, 0.08);
            --text: #f5f6f8;
            --muted: #a5acb8;
            --accent: #f4ae2b;
            --danger: #ff6b6b;
            --success: #35c27b;
            --shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(244, 174, 43, 0.1), transparent 28%),
                linear-gradient(180deg, #050607 0%, #0a0b0d 100%);
            color: var(--text);
        }

        a {
            color: var(--accent);
        }

        .shell {
            width: min(1120px, calc(100% - 32px));
            margin: 32px auto;
            display: grid;
            gap: 24px;
        }

        .hero,
        .card {
            background: rgba(21, 23, 28, 0.94);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 28px;
            display: grid;
            gap: 12px;
        }

        .hero__eyebrow {
            margin: 0;
            font-size: 12px;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 700;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 6vw, 58px);
            line-height: 0.95;
        }

        .hero p {
            margin: 0;
            max-width: 760px;
            color: var(--muted);
            line-height: 1.6;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 24px;
        }

        .card {
            padding: 24px;
        }

        .card h2,
        .card h3 {
            margin: 0 0 16px;
        }

        .stack {
            display: grid;
            gap: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        label {
            display: grid;
            gap: 8px;
            font-size: 14px;
            color: var(--muted);
        }

        input,
        select {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: var(--panel-soft);
            color: var(--text);
            padding: 14px 14px;
            border-radius: 14px;
            font: inherit;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: rgba(244, 174, 43, 0.7);
            box-shadow: 0 0 0 3px rgba(244, 174, 43, 0.15);
        }

        .checkbox {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .checkbox input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            background: var(--accent);
            color: #0a0b0d;
            font-weight: 800;
            padding: 14px 20px;
            border-radius: 999px;
            cursor: pointer;
            font: inherit;
        }

        .button[disabled] {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .button--ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid transparent;
            line-height: 1.6;
        }

        .alert--error {
            background: rgba(255, 107, 107, 0.08);
            border-color: rgba(255, 107, 107, 0.25);
            color: #ffd4d4;
        }

        .alert--success {
            background: rgba(53, 194, 123, 0.08);
            border-color: rgba(53, 194, 123, 0.26);
            color: #d8ffec;
        }

        .checklist {
            display: grid;
            gap: 10px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .checklist li {
            display: grid;
            gap: 4px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .status--ok {
            color: var(--success);
        }

        .status--error {
            color: var(--danger);
        }

        .status--warn {
            color: var(--accent);
        }

        .muted {
            color: var(--muted);
        }

        code {
            font-family: Consolas, monospace;
            color: #ffe0a6;
        }

        @media (max-width: 920px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <p class="hero__eyebrow">Installer</p>
            <h1>Prepare VIDEW for production hosting</h1>
            <p>This setup writes the local <code>.env</code>, imports the clean schema, optionally imports demo catalog entries, prepares storage folders, and locks the installer after success.</p>
        </section>

        <section class="layout">
            <div class="card">
                <div class="stack">
                    <div>
                        <h2>Install</h2>
                        <p class="muted">Use this once on a fresh environment. The first account created after setup becomes the initial admin.</p>
                    </div>

                    <?php if ($installed): ?>
                        <div class="alert alert--success">
                            <strong>Installer locked.</strong><br>
                            This instance already finished installation. Remove <code><?= h(installer_relative_path($lockFile)); ?></code> only if you intentionally need to reinstall.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert--error"><?= h($error); ?></div>
                    <?php endforeach; ?>

                    <?php foreach ($successMessages as $message): ?>
                        <div class="alert alert--success"><?= h($message); ?></div>
                    <?php endforeach; ?>

                    <form method="post" class="stack">
                        <div class="grid">
                            <label>
                                <span>App name</span>
                                <input type="text" name="app_name" value="<?= h($formData['app_name']); ?>" placeholder="VIDEW 18+" <?= $installed ? 'disabled' : ''; ?>>
                            </label>
                            <label>
                                <span>Base URL</span>
                                <input type="url" name="base_url" value="<?= h($formData['base_url']); ?>" placeholder="https://your-domain.example" <?= $installed ? 'disabled' : ''; ?>>
                            </label>
                            <label>
                                <span>Support email</span>
                                <input type="email" name="support_email" value="<?= h($formData['support_email']); ?>" placeholder="support@example.com" <?= $installed ? 'disabled' : ''; ?>>
                            </label>
                            <label>
                                <span>Timezone</span>
                                <input type="text" name="timezone" value="<?= h($formData['timezone']); ?>" placeholder="America/Sao_Paulo" <?= $installed ? 'disabled' : ''; ?>>
                            </label>
                        </div>

                        <div>
                            <h3>Database</h3>
                            <div class="grid">
                                <label>
                                    <span>Host</span>
                                    <input type="text" name="db_host" value="<?= h($formData['db_host']); ?>" placeholder="127.0.0.1" <?= $installed ? 'disabled' : ''; ?>>
                                </label>
                                <label>
                                    <span>Port</span>
                                    <input type="text" name="db_port" value="<?= h($formData['db_port']); ?>" placeholder="3306" <?= $installed ? 'disabled' : ''; ?>>
                                </label>
                                <label>
                                    <span>Database</span>
                                    <input type="text" name="db_database" value="<?= h($formData['db_database']); ?>" placeholder="videw" <?= $installed ? 'disabled' : ''; ?>>
                                </label>
                                <label>
                                    <span>Username</span>
                                    <input type="text" name="db_username" value="<?= h($formData['db_username']); ?>" placeholder="root" <?= $installed ? 'disabled' : ''; ?>>
                                </label>
                                <label style="grid-column: 1 / -1;">
                                    <span>Password</span>
                                    <input type="password" name="db_password" value="<?= h($formData['db_password']); ?>" placeholder="Database password" <?= $installed ? 'disabled' : ''; ?>>
                                </label>
                            </div>
                        </div>

                        <label class="checkbox">
                            <input type="checkbox" name="import_demo" value="1" <?= $formData['import_demo'] === '1' ? 'checked' : ''; ?> <?= $installed ? 'disabled' : ''; ?>>
                            <span>
                                <strong>Import demo catalog</strong><br>
                                <span class="muted">Adds example video records without uploading any real media files.</span>
                            </span>
                        </label>

                        <div class="actions">
                            <button class="button" type="submit" <?= $installed || !installer_can_run($checks) ? 'disabled' : ''; ?>>Run installer</button>
                            <a class="button button--ghost" href="<?= h(installer_detect_base_url() . '/README.md'); ?>" target="_blank" rel="noreferrer">Open README</a>
                        </div>
                    </form>
                </div>
            </div>

            <aside class="card">
                <div class="stack">
                    <div>
                        <h2>Environment Checks</h2>
                        <p class="muted">Required checks must pass before the installer can run.</p>
                    </div>
                    <ul class="checklist">
                        <?php foreach ($checks as $check): ?>
                            <li>
                                <span class="status status--<?= h($check['ok'] ? 'ok' : ($check['required'] ? 'error' : 'warn')); ?>">
                                    <?= $check['ok'] ? 'Ready' : ($check['required'] ? 'Missing' : 'Optional'); ?>
                                </span>
                                <strong><?= h($check['label']); ?></strong>
                                <span class="muted"><?= h($check['message']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div>
                        <h3>After install</h3>
                        <ul class="checklist">
                            <li>
                                <strong>Create the first account</strong>
                                <span class="muted">The first registration becomes the initial admin.</span>
                            </li>
                            <li>
                                <strong>Review storage and billing</strong>
                                <span class="muted">Use the admin suite to configure Wasabi and Stripe when needed.</span>
                            </li>
                            <li>
                                <strong>Keep secrets private</strong>
                                <span class="muted">Do not commit the generated <code>.env</code> file.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>
        </section>
    </main>
</body>
</html>
<?php

/**
 * @return array<int, array{label:string,message:string,ok:bool,required:bool}>
 */
function installer_checks(string $envPath, string $schemaPath, string $demoSeedPath): array
{
    $rootWritable = is_file($envPath) ? is_writable($envPath) : is_writable(ROOT_PATH);
    $runtimeParent = is_dir(ROOT_PATH . '/storage') ? is_writable(ROOT_PATH . '/storage') : is_writable(ROOT_PATH);

    return [
        [
            'label' => 'PHP 8.1 or newer',
            'message' => 'Current: ' . PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'required' => true,
        ],
        [
            'label' => 'PDO MySQL extension',
            'message' => 'Required for the application database connection.',
            'ok' => extension_loaded('pdo_mysql'),
            'required' => true,
        ],
        [
            'label' => 'cURL extension',
            'message' => 'Required for Stripe, Wasabi, and premium media proxying.',
            'ok' => extension_loaded('curl'),
            'required' => true,
        ],
        [
            'label' => 'mbstring extension',
            'message' => 'Used for text and poster helpers.',
            'ok' => extension_loaded('mbstring'),
            'required' => true,
        ],
        [
            'label' => 'fileinfo extension',
            'message' => 'Required for upload MIME detection.',
            'ok' => extension_loaded('fileinfo'),
            'required' => true,
        ],
        [
            'label' => '.env write access',
            'message' => $rootWritable
                ? 'The installer can write the environment file.'
                : 'Make the project root or existing .env file writable before running setup.',
            'ok' => $rootWritable,
            'required' => true,
        ],
        [
            'label' => 'Storage write access',
            'message' => $runtimeParent
                ? 'The installer can prepare storage/runtime directories.'
                : 'The installer needs write access to create storage/runtime folders.',
            'ok' => $runtimeParent,
            'required' => true,
        ],
        [
            'label' => 'Schema file',
            'message' => installer_relative_path($schemaPath),
            'ok' => is_file($schemaPath),
            'required' => true,
        ],
        [
            'label' => 'Demo seed file',
            'message' => installer_relative_path($demoSeedPath),
            'ok' => is_file($demoSeedPath),
            'required' => false,
        ],
    ];
}

/**
 * @param array<int, array{ok:bool,required:bool}> $checks
 */
function installer_can_run(array $checks): bool
{
    foreach ($checks as $check) {
        if ($check['required'] && !$check['ok']) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, string> $data
 * @return array<int, string>
 */
function installer_validate(array $data): array
{
    $errors = [];

    if ($data['app_name'] === '') {
        $errors[] = 'App name is required.';
    }

    $baseUrl = installer_normalize_base_url($data['base_url']);

    if ($baseUrl === null) {
        $errors[] = 'Enter a valid base URL, including http:// or https://.';
    }

    if ($data['support_email'] !== '' && !filter_var($data['support_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Support email is invalid.';
    }

    if ($data['timezone'] === '' || !in_array($data['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Timezone is invalid.';
    }

    if ($data['db_host'] === '' || $data['db_port'] === '' || $data['db_database'] === '' || $data['db_username'] === '') {
        $errors[] = 'Fill in all database fields except password if your database uses an empty password.';
    }

    if (!ctype_digit($data['db_port']) || (int) $data['db_port'] <= 0 || (int) $data['db_port'] > 65535) {
        $errors[] = 'Database port must be a valid TCP port.';
    }

    return $errors;
}

/**
 * @param array<string, string> $data
 * @return array<int, string>
 */
function installer_run(array $data, string $envPath, string $lockFile, string $schemaPath, string $demoSeedPath): array
{
    $baseUrl = installer_normalize_base_url($data['base_url']);

    if ($baseUrl === null) {
        throw new RuntimeException('Base URL normalization failed.');
    }

    $pdo = installer_connect($data);
    $messages = [];

    installer_import_sql_file($pdo, $schemaPath);
    $messages[] = 'Imported the base database schema.';

    if ($data['import_demo'] === '1' && is_file($demoSeedPath)) {
        installer_import_sql_file($pdo, $demoSeedPath);
        $messages[] = 'Imported the optional demo catalog.';
    }

    installer_prepare_directories();
    $messages[] = 'Prepared storage and runtime directories.';

    $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
    $envValues = [
        'VIDEW_APP_NAME' => $data['app_name'],
        'VIDEW_BASE_URL' => $baseUrl,
        'VIDEW_SUPPORT_EMAIL' => $data['support_email'],
        'VIDEW_TIMEZONE' => $data['timezone'],
        'VIDEW_DB_HOST' => $data['db_host'],
        'VIDEW_DB_PORT' => $data['db_port'],
        'VIDEW_DB_DATABASE' => $data['db_database'],
        'VIDEW_DB_USERNAME' => $data['db_username'],
        'VIDEW_DB_PASSWORD' => $data['db_password'],
        'VIDEW_LOCAL_STORAGE_BASE_URL' => rtrim($baseUrl, '/') . '/storage/uploads',
        'VIDEW_SESSION_SECURE_COOKIE' => strtolower((string) $scheme) === 'https' ? '1' : '0',
    ];

    write_env_file_values($envPath, $envValues);
    $messages[] = 'Wrote deployment configuration into .env.';

    installer_lock($lockFile);
    $messages[] = 'Locked the installer. Create the first account on register.php to claim admin access.';

    return $messages;
}

/**
 * @param array<string, string> $data
 */
function installer_connect(array $data): PDO
{
    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $data['db_host'],
                (int) $data['db_port'],
                $data['db_database']
            ),
            $data['db_username'],
            $data['db_password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $exception) {
        throw new RuntimeException('Could not connect to MySQL with the provided database settings. ' . $exception->getMessage());
    }

    return $pdo;
}

function installer_import_sql_file(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing SQL file: ' . installer_relative_path($path));
    }

    $sql = file_get_contents($path);

    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('SQL file is empty: ' . installer_relative_path($path));
    }

    foreach (installer_split_sql($sql) as $statement) {
        $trimmed = trim($statement);

        if ($trimmed === '') {
            continue;
        }

        $pdo->exec($trimmed);
    }
}

/**
 * @return array<int, string>
 */
function installer_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $escaped = false;

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : null;

        if (!$inSingle && !$inDouble) {
            if ($character === '-' && $next === '-') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                continue;
            }

            if ($character === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                continue;
            }

            if ($character === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }
        }

        $buffer .= $character;

        if ($character === '\\' && ($inSingle || $inDouble)) {
            $escaped = !$escaped;
            continue;
        }

        if ($character === "'" && !$inDouble && !$escaped) {
            $inSingle = !$inSingle;
        } elseif ($character === '"' && !$inSingle && !$escaped) {
            $inDouble = !$inDouble;
        }

        $escaped = false;
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

function installer_prepare_directories(): void
{
    foreach ([
        ROOT_PATH . '/storage',
        ROOT_PATH . '/storage/uploads',
        ROOT_PATH . '/storage/runtime',
        ROOT_PATH . '/storage/runtime/ratelimits',
    ] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create directory: ' . installer_relative_path($directory));
        }
    }

    $gitkeep = ROOT_PATH . '/storage/uploads/.gitkeep';

    if (!is_file($gitkeep) && file_put_contents($gitkeep, '') === false) {
        throw new RuntimeException('Could not create storage/uploads/.gitkeep.');
    }
}

function installer_lock(string $lockFile): void
{
    $directory = dirname($lockFile);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the installer lock directory.');
    }

    $payload = json_encode([
        'installed_at' => gmdate('c'),
        'php_version' => PHP_VERSION,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!is_string($payload) || file_put_contents($lockFile, $payload . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the installer lock file.');
    }
}

function installer_detect_base_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        return 'http://localhost';
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $https = $https || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $https ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
    $directory = rtrim(dirname($scriptName), '/.');

    return $scheme . '://' . $host . ($directory !== '' ? $directory : '');
}

function installer_normalize_base_url(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $filtered = filter_var($value, FILTER_VALIDATE_URL);

    if (!is_string($filtered)) {
        return null;
    }

    $scheme = strtolower((string) parse_url($filtered, PHP_URL_SCHEME));

    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return rtrim($filtered, '/');
}

function installer_relative_path(string $path): string
{
    $normalizedRoot = str_replace('\\', '/', ROOT_PATH);
    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, $normalizedRoot)) {
        return ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/') ?: '.';
    }

    return $normalizedPath;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
