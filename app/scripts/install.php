<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/MigrationRunner.php';

try {
    (new MigrationRunner(Danny\Database::connection(), dirname(__DIR__)))->install();
    fwrite(STDOUT, "Instalação concluída. Configure o administrador pelo comando indicado no README.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Instalação interrompida: " . $exception->getMessage() . "\n");
    exit(1);
}
