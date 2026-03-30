<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (is_authenticated()) {
    redirect('account.php');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$flashError = null;
$flashSuccess = null;

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'reset_password')) {
        $flashError = 'Security token expired. Try again.';
    } else {
        $auth = new AuthService();
        $result = $auth->resetPassword(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );

        if ($result['success']) {
            $flashSuccess = $result['message'];
        } else {
            $flashError = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New password | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="auth-body <?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <main class="auth-layout">
        <section class="auth-intro">
            <span class="eyebrow">RESET</span>
            <h1>Choose a new password</h1>
            <p>Use a strong password with at least 8 characters.</p>
            <a class="text-link" href="<?= e(base_url('login.php')); ?>">Back to sign in</a>
        </section>
        <section class="auth-card">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <?php if ($flashSuccess): ?>
                <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
                <a class="button" href="<?= e(base_url('login.php')); ?>">Go to sign in</a>
            <?php elseif ($token === ''): ?>
                <div class="notice-card">
                    <strong>Reset link unavailable</strong>
                    <p>This reset link is not valid anymore. Request a new one and try again.</p>
                </div>
            <?php else: ?>
                <form method="post" class="auth-form">
                    <?= csrf_input('reset_password'); ?>
                    <input type="hidden" name="token" value="<?= e($token); ?>">
                    <label>
                        <span>New password</span>
                        <input type="password" name="password" required>
                    </label>
                    <label>
                        <span>Confirm new password</span>
                        <input type="password" name="password_confirmation" required>
                    </label>
                    <button class="button" type="submit">Save new password</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('reset-password')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
