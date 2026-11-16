<?php

declare(strict_types=1);

/**
 * Default: read-only dry run. No mail is sent and no photo is removed.
 * After reviewing environment, backups and SMTP configuration, cron example:
 * */
// */10 * * * * cd /var/www/html && php scripts/album-jobs.php --execute >> /var/log/lume-album-jobs.log 2>&1
// Each invocation processes up to 100 notifications and 100 due albums.
// SMTP retries: 15 minutes, at most 5 attempts; failed rows remain for review.
// SMTP delivery is at-least-once: a crash after acceptance can cause duplicates.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';

use Danny\Albums\AlbumFactory;
use Danny\Albums\AlbumNotificationWorker;
use Danny\Albums\MailAlbumNotificationSender;
use Danny\Albums\SystemClock;
use Danny\Database;

$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true)) {
    fwrite(STDOUT, "Uso: php scripts/album-jobs.php [--dry-run | --execute]\nPadrão: --dry-run. --execute envia notificações e remove fotos após purge_at.\n");
    exit(0);
}
if (array_diff($arguments, ['--dry-run', '--execute']) || count($arguments) > 1) {
    fwrite(STDERR, "Argumentos inválidos. Use --help.\n");
    exit(2);
}

try {
    $pdo = Database::connection();
    $clock = new SystemClock();
    $now = $clock->now()->format('Y-m-d H:i:s');
    $notifications = $pdo->prepare("SELECT COUNT(*) FROM album_notifications WHERE status='PENDING' AND due_at<=?");
    $notifications->execute([$now]);
    $albums = $pdo->prepare("SELECT COUNT(*) FROM photo_albums WHERE status='PUBLISHED' AND purge_at<=?");
    $albums->execute([$now]);
    $preview = ['mode' => 'dry-run', 'as_of_utc' => $now, 'notifications_due' => (int) $notifications->fetchColumn(), 'albums_due_for_purge' => (int) $albums->fetchColumn()];

    if (!in_array('--execute', $arguments, true)) {
        fwrite(STDOUT, json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    $result = (new AlbumNotificationWorker($pdo, new MailAlbumNotificationSender(), $clock))->run();
    $purged = AlbumFactory::service()->purgeExpired(true);
    fwrite(STDOUT, json_encode(['mode' => 'execute', 'notifications' => $result, 'albums_purged' => $purged], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) {
    // SMTP/DB exception messages can contain credentials or connection details.
    fwrite(STDERR, "Rotina de álbuns interrompida. Verifique configuração e conectividade; registros pendentes foram preservados.\n");
    exit(1);
}
