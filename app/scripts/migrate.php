<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/MigrationRunner.php';

try {
    (new MigrationRunner(Danny\Database::connection(), dirname(__DIR__)))->migrate();
    fwrite(STDOUT, "Schema atualizado.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Migração interrompida: " . $exception->getMessage() . "\n");
    exit(1);
}
