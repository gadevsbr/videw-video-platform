<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

if (!is_post_request() || !verify_csrf($_POST['_csrf'] ?? null, 'logout')) {
    http_response_code(405);
    redirect('');
}

$auth = new AuthService();
$auth->logout();
flash('success', 'Session ended.');
redirect('');
