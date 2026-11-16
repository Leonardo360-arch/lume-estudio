<?php

declare(strict_types=1);

namespace Danny\Albums;

use Danny\Mailer;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/** Authenticated album links contain no bearer token and never trust HTTP_HOST. */
final class MailAlbumNotificationSender implements NotificationSender
{
    public function __construct(private readonly Mailer $mailer = new Mailer())
    {
    }

    public function send(string $recipient, array $album, string $kind): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !preg_match('/^[a-f0-9]{32}$/D', (string) ($album['id'] ?? ''))) {
            throw new InvalidArgumentException('Notificação de álbum inválida.');
        }

        [$subject, $intro] = match ($kind) {
            'PUBLISHED' => ['Seu álbum está disponível — Lume Estúdio', 'Seu álbum foi publicado e já pode ser acessado.'],
            'EXTENDED' => ['Prazo do álbum atualizado — Lume Estúdio', 'O prazo de acesso ao seu álbum foi prorrogado.'],
            'REMINDER_7' => ['Lembrete sobre seu álbum — Lume Estúdio', 'Lembre-se de acessar seu álbum e guardar suas fotos antes do encerramento do prazo.'],
            'REMINDER_2' => ['O prazo do seu álbum está chegando ao fim — Lume Estúdio', 'O prazo para acessar seu álbum está chegando ao fim. Guarde suas fotos antes do encerramento.'],
            default => throw new InvalidArgumentException('Tipo de notificação inválido.'),
        };

        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) ($album['expires_at'] ?? ''), new DateTimeZone('UTC'));
        if (!$expiry || $expiry->format('Y-m-d H:i:s') !== ($album['expires_at'] ?? '')) {
            throw new InvalidArgumentException('Prazo do álbum inválido.');
        }

        $deadline = $expiry->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y \à\s H:i');
        $url = canonical_app_url() . '/albums.php?id=' . rawurlencode($album['id']);
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = (string) ($album['title'] ?? 'Seu álbum');
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body>'
            . '<h1>' . $escape($subject) . '</h1><p>' . $escape($intro) . '</p>'
            . '<p><strong>Álbum:</strong> ' . $escape($title) . '</p>'
            . '<p><strong>Disponível até:</strong> ' . $escape($deadline) . ' (horário de Brasília).</p>'
            . '<p><a href="' . $escape($url) . '">Entrar para acessar meu álbum</a></p>'
            . '<p>Entre com sua conta de cliente. Este link não concede acesso a outras pessoas.</p>'
            . '<p>Após o prazo, o acesso é encerrado. Baixe e mantenha sua própria cópia das fotos.</p>'
            . '</body></html>';
        $plain = $intro . "\n\nÁlbum: " . $title . "\nDisponível até: " . $deadline
            . " (horário de Brasília).\n\nEntre com sua conta de cliente:\n" . $url
            . "\n\nApós o prazo, o acesso é encerrado. Guarde sua própria cópia das fotos.";

        if (!$this->mailer->send($recipient, $subject, $html, $plain)) {
            throw new RuntimeException('Não foi possível enviar a notificação.');
        }
    }
}
