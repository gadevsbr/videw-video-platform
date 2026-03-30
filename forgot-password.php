<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (is_authenticated()) {
    redirect('account.php');
}

$flashError = null;
$flashSuccess = null;
$resetUrl = null;
$showResetLink = (bool) config('security.expose_reset_links', false);

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'forgot_password')) {
        $flashError = 'Security token expired. Try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        remember_input(['email' => $email]);

        $auth = new AuthService();
        $result = $auth->requestPasswordReset($email);

        if ($result['success']) {
            clear_old_input();
            $flashSuccess = $result['message'];
            $resetUrl = $result['reset_url'] ?? null;
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
    <title><?= e(copy_text('auth.forgot.title', 'Reset password')); ?> | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('auth-body')); ?>">
    <main class="auth-layout">
        <section class="auth-intro">
            <span class="eyebrow"><?= e(copy_text('auth.forgot.eyebrow', 'PASSWORD RESET')); ?></span>
            <h1><?= e(copy_text('auth.forgot.heading', 'Reset access')); ?></h1>
            <p><?= e(copy_text('auth.forgot.text', 'Enter your email and we will help you reset your password.')); ?></p>
            <a class="text-link" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('common.back_to_sign_in', 'Back to sign in')); ?></a>
        </section>
        <section class="auth-card">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <?php if ($flashSuccess): ?>
                <div class="flash flash--success"><?= e((string) $flashSuccess); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <?= csrf_input('forgot_password'); ?>
                <label>
                    <span><?= e(copy_text('auth.forgot.email', 'Email')); ?></span>
                    <input type="email" name="email" value="<?= e(old('email')); ?>" required>
                </label>
                <button class="button" type="submit"><?= e(copy_text('auth.forgot.submit', 'Send reset link')); ?></button>
                <?php if ($showResetLink): ?>
                    <p class="form-note"><?= e(copy_text('auth.forgot.preview_enabled', 'Reset link preview is enabled for this site right now.')); ?></p>
                <?php else: ?>
                    <p class="form-note"><?= e(copy_text('auth.forgot.preview_disabled', 'If the email matches an account, the next step will be sent to you.')); ?></p>
                <?php endif; ?>
            </form>
            <?php if ($showResetLink && $resetUrl): ?>
                <div class="security-note">
                    <strong><?= e(copy_text('auth.forgot.preview_title', 'Reset link')); ?></strong>
                    <code><?= e($resetUrl); ?></code>
                    <a class="text-link" href="<?= e($resetUrl); ?>"><?= e(copy_text('auth.forgot.preview_link', 'Open reset page')); ?></a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('forgot-password')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
