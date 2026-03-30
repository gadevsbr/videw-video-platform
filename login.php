<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (is_authenticated()) {
    redirect('account.php');
}

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'login')) {
        flash('error', 'Security token expired. Try again.');
        redirect('login.php');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    remember_input(['email' => $email]);

    $auth = new AuthService();
    $result = $auth->attemptLogin($email, $password);

    if ($result['success']) {
        clear_old_input();
        flash('success', $result['message']);

        if (!empty($result['requires_mfa'])) {
            redirect('mfa-challenge.php');
        }

        redirect('');
    }

    flash('error', $result['message']);
}

$flashError = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(copy_text('auth.login.title', 'Sign in')); ?> | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
    <?= public_head_markup(); ?>
</head>
<body class="<?= e(page_lock_class('auth-body')); ?>">
    <main class="auth-layout">
        <section class="auth-intro">
            <span class="eyebrow"><?= e(copy_text('auth.login.eyebrow', 'ACCESS')); ?></span>
            <h1><?= e(copy_text('auth.login.heading', 'Sign in')); ?></h1>
            <p><?= e(copy_text('auth.login.text', 'Sign in to watch premium videos and manage your account.')); ?></p>
            <a class="text-link" href="<?= e(base_url()); ?>"><?= e(copy_text('common.back_to_home', 'Back to home')); ?></a>
        </section>
        <section class="auth-card">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <?= csrf_input('login'); ?>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e(old('email')); ?>" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" required>
                </label>
                <button class="button" type="submit"><?= e(copy_text('auth.login.submit', 'Sign in')); ?></button>
                <p class="form-note"><a class="text-link" href="<?= e(base_url('forgot-password.php')); ?>"><?= e(copy_text('auth.login.forgot', 'Forgot your password?')); ?></a></p>
                <p class="form-note"><?= e(copy_text('auth.login.register_prompt', 'No account yet?')); ?> <a class="text-link" href="<?= e(base_url('register.php')); ?>"><?= e(copy_text('auth.login.register_link', 'Create one')); ?></a></p>
            </form>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('login')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
