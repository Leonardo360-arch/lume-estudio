<?php
declare(strict_types=1);
namespace Danny\Albums;
interface Clock { public function now(): \DateTimeImmutable; }
