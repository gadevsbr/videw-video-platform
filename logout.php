<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\AuthService;

$auth = new AuthService();
$auth->logout();
flash('success', 'Sessão encerrada.');
redirect('');
