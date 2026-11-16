<?php
declare(strict_types=1);
namespace Danny\Albums;
interface PhotoStorage {
    /** Validates and stores an actual HTTP upload; returns metadata, never a public URL. */
    public function storeUpload(array $upload): array;
    public function path(string $key): string;
    public function delete(string $key): void;
}
