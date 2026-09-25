<?php

declare(strict_types=1);

use Symfony\Component\ErrorHandler\ErrorHandler;

ErrorHandler::register(null, false);

if (file_exists(dirname(__DIR__, 3) . '/vendor/autoload.php')) {
    $loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
    $loader->addPsr4('Pentiminax\\UX\\DataTables\\Tests\\', __DIR__ . '/');
}
