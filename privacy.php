<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$legalKey = 'privacy';
$legalPage = legal_page_config('privacy');

require ROOT_PATH . '/partials/legal-page.php';
