<?php
declare(strict_types=1);
namespace Danny\Albums;
interface AlbumRepository {
    public function find(string $id, bool $lock = false): ?array;
    public function forClient(int $clientId): array;
    public function all(): array;
    public function create(string $id, int $clientId, ?int $bookingId, string $title, string $description): void;
    public function photos(string $albumId): array;
    public function photo(string $id): ?array;
    public function addPhoto(string $albumId, array $photo): void;
    public function publish(string $id, string $publishedAt, string $expiresAt, string $purgeAt, bool $extension): void;
    public function dueForPurge(string $now): array;
    public function markPurged(string $id): void;
    public function transaction(callable $operation): mixed;
}
