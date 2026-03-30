<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (is_authenticated()) {
    redirect('account.php');
}

if (is_post_request()) {
    if (!verify_csrf($_POST['_csrf'] ?? null, 'register')) {
        flash('error', 'Security token expired. Try again.');
        redirect('register.php');
    }

    $input = [
        'display_name' => trim((string) ($_POST['display_name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'birth_date' => (string) ($_POST['birth_date'] ?? ''),
        'adult_terms' => (string) ($_POST['adult_terms'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
        'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
    ];

    remember_input([
        'display_name' => $input['display_name'],
        'email' => $input['email'],
        'birth_date' => $input['birth_date'],
        'adult_terms' => $input['adult_terms'],
    ]);

    $auth = new AuthService();
    $result = $auth->registerMember($input);

    if ($result['success']) {
        clear_old_input();
        $_SESSION['age_verified_at'] = (new DateTimeImmutable())->format(DATE_ATOM);
        flash('success', $result['message']);
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
    <title>Create account | <?= e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')); ?>">
</head>
<body class="auth-body <?= !is_age_verified() ? 'is-locked' : ''; ?>">
    <main class="auth-layout">
        <section class="auth-intro">
            <span class="eyebrow">JOIN 18+</span>
            <h1>Create your account</h1>
            <p>Create a free account to save your access and upgrade anytime.</p>
            <a class="text-link" href="<?= e(base_url()); ?>">Back to home</a>
        </section>
        <section class="auth-card">
            <?php if ($flashError): ?>
                <div class="flash flash--error"><?= e((string) $flashError); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <?= csrf_input('register'); ?>
                <label>
                    <span>Display name</span>
                    <input type="text" name="display_name" value="<?= e(old('display_name')); ?>" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e(old('email')); ?>" required>
                </label>
                <label>
                    <span>Birth date</span>
                    <input type="date" name="birth_date" value="<?= e(old('birth_date')); ?>" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" required>
                </label>
                <label>
                    <span>Confirm password</span>
                    <input type="password" name="password_confirmation" required>
                </label>
                <label class="checkbox-line">
                    <input type="checkbox" name="adult_terms" value="1" <?= old('adult_terms') === '1' ? 'checked' : ''; ?>>
                    <span>I confirm that I am 18+ and I accept the adult content policy.</span>
                </label>
                <button class="button" type="submit">Create account</button>
            </form>
        </section>
    </main>

    <?php require ROOT_PATH . '/partials/public-footer.php'; ?>

    <div id="cookie-notice-root"></div>
    <div id="age-gate-root"></div>

    <script>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('register')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
