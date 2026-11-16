<?php

declare(strict_types=1);

// Schema changes belong to explicit CLI migrations, never an HTTP request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

fwrite(STDERR, "Use o comando de migração documentado em scripts/; a correção por HTTP foi desativada.\n");
exit(1);
