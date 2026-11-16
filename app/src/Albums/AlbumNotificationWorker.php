<?php

declare(strict_types=1);

namespace Danny\Albums;

use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Durable outbox, at-least-once delivery. SMTP and the database cannot commit
 * atomically: a crash after SMTP acceptance but before commit can cause a repeat.
 * The unique event key prevents duplicate enqueueing; the DB advisory lock and
 * row locks prevent concurrent workers from delivering the same event normally.
 * No photo, publication or expiry is modified by this worker.
 */
final class AlbumNotificationWorker
{
    public const BATCH_SIZE = 100;
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PDO $pdo,
        private readonly NotificationSender $sender,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    /** @return array{processed:int,sent:int,failed:int,skipped:int,exhausted:int,locked:bool} */
    public function run(): array
    {
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'exhausted' => 0, 'locked' => false];
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Execute a fila fora de uma transação existente.');
        }

        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'lume:album-notifications:' . substr(hash('sha256', $database), 0, 32);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        if ((string) $lock->fetchColumn() !== '1') {
            $result['locked'] = true;
            return $result;
        }

        try {
            $now = $this->now();
            // This first read takes no row locks: all mutations lock album first,
            // notification second, matching publication, extension and purge.
            $query = $this->pdo->prepare("SELECT id, album_id FROM album_notifications WHERE status='PENDING' AND due_at<=? ORDER BY due_at,id LIMIT 100");
            $query->execute([$now]);
            $candidates = $query->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidates as $candidate) {
                $outcome = $this->process($candidate);
                if ($outcome !== null) {
                    $result['processed']++;
                    $result[$outcome]++;
                }
            }
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }

        return $result;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** @param array{id:int|string,album_id:string} $candidate */
    private function process(array $candidate): ?string
    {
        $this->pdo->beginTransaction();
        try {
            $findAlbum = $this->pdo->prepare('SELECT * FROM photo_albums WHERE id=? FOR UPDATE');
            $findAlbum->execute([$candidate['album_id']]);
            $album = $findAlbum->fetch(PDO::FETCH_ASSOC);
            if (!$album) {
                $this->pdo->commit();
                return null;
            }

            $findNotification = $this->pdo->prepare(
                'SELECT n.*, u.email AS recipient_email, u.tipo AS recipient_type '
                . 'FROM album_notifications n JOIN photo_albums a ON a.id=n.album_id '
                . 'JOIN admins u ON u.id=a.client_id WHERE n.id=? AND n.album_id=? FOR UPDATE'
            );
            $findNotification->execute([$candidate['id'], $candidate['album_id']]);
            $event = $findNotification->fetch(PDO::FETCH_ASSOC);
            $now = $this->now();
            if (!$event || $event['status'] !== 'PENDING' || $event['due_at'] > $now) {
                $this->pdo->commit();
                return null;
            }

            if ((int) $event['album_version'] !== (int) $album['version']
                || $album['status'] !== 'PUBLISHED' || empty($album['expires_at']) || $album['expires_at'] <= $now) {
                $this->skip((int) $event['id'], 'Evento desatualizado ou álbum indisponível.');
                $this->pdo->commit();
                return 'skipped';
            }
            if ((int) $event['attempts'] >= self::MAX_ATTEMPTS) {
                $this->skip((int) $event['id'], 'Limite de tentativas atingido; requer revisão administrativa.');
                $this->pdo->commit();
                return 'exhausted';
            }

            $recipient = trim((string) $event['recipient_email']);
            if ($event['recipient_type'] !== 'CLIENTE' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
                || str_ends_with(strtolower($recipient), '@sememail.local')) {
                $this->skip((int) $event['id'], 'Destinatário indisponível para notificação.');
                $this->pdo->commit();
                return 'skipped';
            }

            // Hold the album lock throughout SMTP: a concurrent extension/purge
            // waits until this version and deadline have finished being delivered.
            try {
                $this->sender->send($recipient, $album, (string) $event['kind']);
            } catch (Throwable) {
                $attempts = (int) $event['attempts'] + 1;
                $exhausted = $attempts >= self::MAX_ATTEMPTS;
                $retry = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->modify('+15 minutes')->format('Y-m-d H:i:s');
                $this->pdo->prepare('UPDATE album_notifications SET status=?, attempts=?, due_at=?, last_error=? WHERE id=?')
                    ->execute([
                        $exhausted ? 'SKIPPED' : 'PENDING', $attempts, $retry,
                        $exhausted ? 'Limite de tentativas atingido; requer revisão administrativa.' : 'Falha de envio; nova tentativa agendada.',
                        $event['id'],
                    ]);
                $this->pdo->commit();
                return $exhausted ? 'exhausted' : 'failed';
            }

            $this->pdo->prepare("UPDATE album_notifications SET status='SENT',attempts=attempts+1,sent_at=?,last_error=NULL WHERE id=?")
                ->execute([$this->now(), $event['id']]);
            $this->pdo->commit();
            return 'sent';
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function skip(int $id, string $reason): void
    {
        $this->pdo->prepare("UPDATE album_notifications SET status='SKIPPED',last_error=? WHERE id=?")->execute([$reason, $id]);
    }
}
