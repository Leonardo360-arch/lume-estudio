<?php

declare(strict_types=1);

namespace Danny\Albums;

/** Mail transport boundary: tests inject a sender that never opens a connection. */
interface NotificationSender
{
    /** @param array{id:string,title:string,expires_at:string} $album */
    public function send(string $recipient, array $album, string $kind): void;
}
