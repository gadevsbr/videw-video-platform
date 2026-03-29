<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$legalKey = 'cookies';
$legalPage = legal_page_config('cookies');

require ROOT_PATH . '/partials/legal-page.php';
