<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$legalKey = 'terms';
$legalPage = legal_page_config('terms');

require ROOT_PATH . '/partials/legal-page.php';
