<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (is_authenticated()) {
    redirect('account.php');
}

if (empty($_SESSION['pending_mfa_user_id'])) {
    flash('error', copy_text('messages.auth.mfa_page_sign_in_again', 'Sign in again to continue.'));
    redirect('login.php');
}

$flashError = flash('error');

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'mfa_challenge')) {
        $flashError = copy_text('messages.common.security_token_expired', 'Security token expired. Try again.');
    } else {
        $auth = new AuthService();
        $result = $auth->completePendingMfa((string) ($_POST['code'] ?? ''));

        if ($result['success']) {
            flash('success', $result['message']);
            redirect('');
        }

        $flashError = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(copy_text('auth.mfa.title', '2FA challenge')); ?> | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('auth-body')); ?>">
    <main class="auth-layout">
        <section class="auth-intro">
            <span class="eyebrow"><?= e(copy_text('auth.mfa.eyebrow', '2FA')); ?></span>
            <h1><?= e(copy_text('auth.mfa.heading', 'Enter your code')); ?></h1>
            <p><?= e(copy_text('auth.mfa.text', 'Use the 6-digit code from your authenticator app or one of your backup codes.')); ?></p>
            <a class="text-link" href="<?= e(base_url('login.php')); ?>"><?= e(copy_text('common.back_to_sign_in', 'Back to sign in')); ?></a>
        </section>
        <section class="auth-card">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <?= csrf_input('mfa_challenge'); ?>
                <label>
                    <span><?= e(copy_text('auth.mfa.code_label', 'Authenticator or backup code')); ?></span>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                </label>
                <button class="button" type="submit"><?= e(copy_text('auth.mfa.submit', 'Verify code')); ?></button>
                <p class="form-note"><?= e(copy_text('auth.mfa.note', 'Backup codes can be entered with or without hyphens.')); ?></p>
            </form>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('mfa-challenge')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
