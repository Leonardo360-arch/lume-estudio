<?php
declare(strict_types=1);
namespace Danny\Albums;
final class SystemClock implements Clock {
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
}
