<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$helpersPath = __DIR__ . '/helpers.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} elseif (file_exists($helpersPath)) {
    require_once $helpersPath;
}

if (class_exists(Dotenv\Dotenv::class)) {
    // Some SAPIs omit process variables from $_ENV/$_SERVER. Read getenv()
    // when checking immutability so .env never overrides Docker/CI settings.
    // This adapter is reader-only: no process-wide putenv() writes are used.
    $environmentRepository = Dotenv\Repository\RepositoryBuilder::createWithDefaultAdapters()
        ->addReader(Dotenv\Repository\Adapter\PutenvAdapter::class)
        ->immutable()
        ->make();
    Dotenv\Dotenv::create($environmentRepository, dirname(__DIR__))->safeLoad();
}

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

require_once __DIR__ . '/Security.php';

secure_session_start();
protect_http_request();
