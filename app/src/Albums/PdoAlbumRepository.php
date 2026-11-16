<?php
declare(strict_types=1);
namespace Danny\Albums;
use PDO;
final class PdoAlbumRepository implements AlbumRepository {
    public function __construct(private PDO $pdo) {}
    public function find(string $id, bool $lock = false): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM photo_albums WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    public function forClient(int $clientId): array {
        $stmt = $this->pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) photo_count FROM photo_albums a WHERE client_id=? AND status <> 'DRAFT' ORDER BY created_at DESC");
        $stmt->execute([$clientId]); return $stmt->fetchAll();
    }
    public function all(): array {
        return $this->pdo->query('SELECT a.*, u.nome client_name, (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) photo_count FROM photo_albums a JOIN admins u ON u.id=a.client_id ORDER BY a.created_at DESC')->fetchAll();
    }
    public function create(string $id, int $clientId, ?int $bookingId, string $title, string $description): void {
        $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE id=? AND tipo='CLIENTE'"); $stmt->execute([$clientId]);
        if (!$stmt->fetch()) { throw new \InvalidArgumentException('Cliente inválido.'); }
        if ($bookingId !== null) {
            $stmt = $this->pdo->prepare('SELECT id FROM agendamentos WHERE id=? AND admin_id=?'); $stmt->execute([$bookingId, $clientId]);
            if (!$stmt->fetch()) { throw new \InvalidArgumentException('O agendamento não pertence ao cliente.'); }
        }
        $this->pdo->prepare('INSERT INTO photo_albums (id,client_id,booking_id,title,description) VALUES (?,?,?,?,?)')->execute([$id,$clientId,$bookingId,$title,$description]);
    }
    public function photos(string $albumId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM album_photos WHERE album_id=? ORDER BY created_at,id'); $stmt->execute([$albumId]); return $stmt->fetchAll();
    }
    public function photo(string $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM album_photos WHERE id=?'); $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    public function addPhoto(string $albumId, array $p): void {
        $this->pdo->prepare('INSERT INTO album_photos (id,album_id,storage_key,thumbnail_key,original_name,mime,bytes,width,height) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([bin2hex(random_bytes(16)),$albumId,$p['storage_key'],$p['thumbnail_key'],$p['original_name'],$p['mime'],$p['bytes'],$p['width'],$p['height']]);
    }
    public function publish(string $id, string $publishedAt, string $expiresAt, string $purgeAt, bool $extension): void {
        $this->pdo->prepare("UPDATE photo_albums SET status='PUBLISHED', published_at=COALESCE(published_at,?),expires_at=?,purge_at=?,version=version+1 WHERE id=?")->execute([$publishedAt,$expiresAt,$purgeAt,$id]);
        $album = $this->find($id);
        $this->pdo->prepare("UPDATE album_notifications SET status='SKIPPED' WHERE album_id=? AND status='PENDING'")->execute([$id]);
        $expiry = new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'));
        $events = [[$extension ? 'EXTENDED' : 'PUBLISHED', $publishedAt],['REMINDER_7',$expiry->modify('-7 days')->format('Y-m-d H:i:s')],['REMINDER_2',$expiry->modify('-2 days')->format('Y-m-d H:i:s')]];
        foreach ($events as [$kind,$due]) {
            if ($due < $publishedAt) { continue; }
            $this->pdo->prepare('INSERT INTO album_notifications (album_id,kind,album_version,due_at) VALUES (?,?,?,?)')->execute([$id,$kind,$album['version'],$due]);
        }
    }
    public function dueForPurge(string $now): array {
        $stmt = $this->pdo->prepare("SELECT * FROM photo_albums WHERE status='PUBLISHED' AND purge_at<=? ORDER BY purge_at LIMIT 100"); $stmt->execute([$now]); return $stmt->fetchAll();
    }
    public function markPurged(string $id): void {
        $this->pdo->prepare('DELETE FROM album_photos WHERE album_id=?')->execute([$id]);
        $this->pdo->prepare("UPDATE photo_albums SET status='PURGED' WHERE id=?")->execute([$id]);
        $this->pdo->prepare("UPDATE album_notifications SET status='SKIPPED' WHERE album_id=? AND status='PENDING'")->execute([$id]);
    }
    public function transaction(callable $operation): mixed {
        $this->pdo->beginTransaction();
        try { $result=$operation(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
}
