<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$legalKey = 'rules';
$legalPage = legal_page_config('rules');

require ROOT_PATH . '/partials/legal-page.php';
