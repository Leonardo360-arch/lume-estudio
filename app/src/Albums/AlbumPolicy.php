<?php
declare(strict_types=1);
namespace Danny\Albums;
final class AlbumPolicy {
    public const ACCESS_DAYS = 14;
    public const RECOVERY_DAYS = 7;
    public function __construct(private Clock $clock) {}
    public function readable(array $album, int $clientId): bool {
        return (int)$album['client_id'] === $clientId && $album['status'] === 'PUBLISHED'
            && !empty($album['expires_at']) && $this->clock->now() < $this->date($album['expires_at']);
    }
    public function recoverable(array $album): bool {
        return $album['status'] === 'PUBLISHED' && !empty($album['purge_at'])
            && $this->clock->now() < $this->date($album['purge_at']);
    }
    public function date(string $value): \DateTimeImmutable { return new \DateTimeImmutable($value, new \DateTimeZone('UTC')); }
    public function localDate(string $value): string {
        return $this->date($value)->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('d/m/Y \à\s H:i');
    }
}
