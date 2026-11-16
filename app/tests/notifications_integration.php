<?php

declare(strict_types=1);

/**
 * Requires an isolated, migrated lume_test_* database; no SMTP connection is made.
 * TEST_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=lume_test_notifications'
 * TEST_DB_USER=... TEST_DB_PASSWORD=... php tests/notifications_integration.php
 */
require_once __DIR__ . '/../src/bootstrap.php';

use Danny\Albums\AlbumNotificationWorker;
use Danny\Albums\Clock;
use Danny\Albums\NotificationSender;

$dsn = getenv('TEST_DB_DSN') ?: '';
if (!preg_match('/(?:^|;)dbname=lume_test_[a-z0-9_]+(?:;|$)/i', $dsn)) {
    fwrite(STDERR, "Use TEST_DB_DSN com um banco isolado e migrado lume_test_*.\n");
    exit(2);
}

function notification_connection(): PDO
{
    return new PDO(getenv('TEST_DB_DSN'), getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
function notification_check(bool $valid, string $reason): void
{
    if (!$valid) throw new RuntimeException($reason);
}
final class NotificationTestClock implements Clock
{
    public function __construct(public DateTimeImmutable $time) {}
    public function now(): DateTimeImmutable { return $this->time; }
}
final class FakeAlbumSender implements NotificationSender
{
    public array $messages = [];
    public bool $fail = false;
    public ?Closure $duringSend = null;
    public function send(string $recipient, array $album, string $kind): void
    {
        if ($this->duringSend) ($this->duringSend)();
        $this->messages[] = compact('recipient', 'album', 'kind');
        if ($this->fail) throw new RuntimeException('Synthetic SMTP failure with a secret that must never be stored.');
    }
}

$pdo = notification_connection();
$clock = new NotificationTestClock(new DateTimeImmutable('2030-06-01 12:00:00', new DateTimeZone('UTC')));
$sender = new FakeAlbumSender();
$worker = new AlbumNotificationWorker($pdo, $sender, $clock);
$ids = [];
$pdo->prepare("INSERT INTO admins (nome,email,telefone,senha_hash,tipo) VALUES (?,?,?,?, 'CLIENTE')")
    ->execute(['Pessoa fictícia de teste', bin2hex(random_bytes(8)) . '@example.test', '00000000000', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
$clientId = (int) $pdo->lastInsertId();
$fixture = function (array $overrides = []) use ($pdo, $clientId, &$ids): array {
    $id = bin2hex(random_bytes(16)); $ids[] = $id;
    $values = $overrides + ['version' => 1, 'event_version' => 1, 'expires_at' => '2030-06-15 12:00:00', 'due_at' => '2030-06-01 12:00:00', 'purge_at' => '2030-06-22 12:00:00'];
    $pdo->prepare("INSERT INTO photo_albums (id,client_id,title,status,version,published_at,expires_at,purge_at) VALUES (?,?,?,'PUBLISHED',?,'2030-06-01 12:00:00',?,?)")
        ->execute([$id,$clientId,'Álbum fictício <teste>',$values['version'],$values['expires_at'],$values['purge_at']]);
    $pdo->prepare("INSERT INTO album_notifications (album_id,kind,album_version,due_at) VALUES (?,'PUBLISHED',?,?)")
        ->execute([$id,$values['event_version'],$values['due_at']]);
    return ['album' => $id, 'event' => (int) $pdo->lastInsertId()];
};
$event = function (int $id) use ($pdo): array {
    $query = $pdo->prepare('SELECT * FROM album_notifications WHERE id=?'); $query->execute([$id]); return $query->fetch();
};

try {
    $sent = $fixture();
    notification_check($worker->run()['sent'] === 1, 'Publicação não notificada.');
    notification_check($event($sent['event'])['status'] === 'SENT' && (int)$event($sent['event'])['attempts'] === 1, 'Estado SENT inválido.');
    notification_check($worker->run()['processed'] === 0 && count($sender->messages) === 1, 'Evento entregue foi reenviado.');
    echo "PASS envio fake, SENT e reexecução sem duplicação\n";

    $retry = $fixture(); $sender->fail = true;
    notification_check($worker->run()['failed'] === 1, 'Falha não registrada.');
    $row = $event($retry['event']);
    notification_check($row['status'] === 'PENDING' && $row['due_at'] === '2030-06-01 12:15:00' && (int)$row['attempts'] === 1, 'Retry não foi adiado por 15 minutos.');
    notification_check(!str_contains($row['last_error'], 'secret'), 'Erro SMTP sensível foi armazenado.');
    notification_check($worker->run()['processed'] === 0, 'Retry executado antes do prazo.');
    for ($attempt = 2; $attempt <= 5; $attempt++) {
        $clock->time = $clock->time->modify('+15 minutes'); $worker->run();
    }
    $row = $event($retry['event']);
    notification_check($row['status'] === 'SKIPPED' && (int)$row['attempts'] === 5 && str_contains($row['last_error'], 'Limite'), 'Limite de tentativas não preservou registro.');
    notification_check($worker->run()['processed'] === 0, 'Evento esgotado foi reexecutado.');
    $sender->fail = false;
    echo "PASS retry de 15 minutos, erro sanitizado e limite de 5 tentativas\n";

    $before = count($sender->messages);
    $stale = $fixture(['version' => 2]);
    $expired = $fixture(['expires_at' => $clock->now()->format('Y-m-d H:i:s')]);
    $future = $fixture(['due_at' => '2031-01-01 00:00:00']);
    notification_check($worker->run()['skipped'] === 2, 'Versão antiga ou álbum vencido não ignorados.');
    notification_check($event($stale['event'])['status'] === 'SKIPPED' && $event($expired['event'])['status'] === 'SKIPPED', 'Eventos obsoletos foram mantidos na fila.');
    notification_check($event($future['event'])['status'] === 'PENDING' && count($sender->messages) === $before, 'Evento futuro enviado.');
    echo "PASS versão obsoleta, expiração no limite e evento futuro\n";

    $locked = $fixture(); $other = notification_connection(); $otherSender = new FakeAlbumSender();
    $sender->duringSend = function () use ($other,$otherSender,$clock,$locked): void {
        notification_check((new AlbumNotificationWorker($other,$otherSender,$clock))->run()['locked'], 'Dois workers adquiriram o mesmo advisory lock.');
        $other->beginTransaction();
        try {
            $query=$other->prepare('SELECT id FROM photo_albums WHERE id=? FOR UPDATE NOWAIT'); $query->execute([$locked['album']]);
            throw new RuntimeException('O álbum não permaneceu bloqueado durante o envio.');
        } catch (PDOException) {
            // Expected: publication/extension/purge cannot update this row yet.
        } finally { $other->rollBack(); }
    };
    notification_check($worker->run()['sent'] === 1 && !$otherSender->messages, 'Proteção concorrente falhou.');
    $sender->duringSend = null;
    $other->prepare('UPDATE photo_albums SET title=title WHERE id=?')->execute([$locked['album']]);
    echo "PASS worker concorrente bloqueado e lock do álbum durante envio\n";

    $before = count($sender->messages);
    for ($index=0; $index<101; $index++) $fixture();
    notification_check($worker->run()['sent'] === 100 && count($sender->messages)-$before === 100, 'Lote excedeu ou não alcançou 100 notificações.');
    notification_check($worker->run()['sent'] === 1, 'Item remanescente não processado.');
    echo "PASS limite de lote de 100 e continuação na próxima execução\n";

    $purge = $fixture(['expires_at'=>'2020-01-01 00:00:00','purge_at'=>'2020-01-08 00:00:00','due_at'=>'2020-01-01 00:00:00']);
    $photoId=bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO album_photos (id,album_id,storage_key,original_name,mime,bytes,width,height) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$photoId,$purge['album'],bin2hex(random_bytes(32)),'ficticia.jpg','image/jpeg',1,1,1]);
    $environment = getenv();
    preg_match('/(?:^|;)dbname=([^;]+)/', substr($dsn,6), $database);
    preg_match('/(?:^|;)host=([^;]+)/', substr($dsn,6), $host);
    preg_match('/(?:^|;)port=([^;]+)/', substr($dsn,6), $port);
    $environment=array_merge($environment,['DB_HOST'=>$host[1]??'127.0.0.1','DB_PORT'=>$port[1]??'3306','DB_DATABASE'=>$database[1],'DB_USERNAME'=>getenv('TEST_DB_USER')?:'root','DB_PASSWORD'=>getenv('TEST_DB_PASSWORD')?:'','MAIL_HOST'=>'127.0.0.1','MAIL_PORT'=>'1']);
    $process=proc_open([PHP_BINARY,__DIR__.'/../scripts/album-jobs.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$environment);
    notification_check(is_resource($process),'Falha ao iniciar dry-run.');
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    notification_check(proc_close($process)===0,'Dry-run falhou: '.$error);
    $preview=json_decode($output,true,512,JSON_THROW_ON_ERROR);
    notification_check($preview['mode']==='dry-run' && $preview['notifications_due']>=1 && $preview['albums_due_for_purge']>=1,'Contagem do dry-run incorreta.');
    notification_check($event($purge['event'])['status']==='PENDING','Dry-run alterou a fila.');
    $query=$pdo->prepare('SELECT COUNT(*) FROM album_photos WHERE id=?');$query->execute([$photoId]);
    notification_check((int)$query->fetchColumn()===1,'Dry-run removeu foto.');
    echo "PASS CLI padrão dry-run conta sem SMTP, alteração da fila ou remoção de fotos\n";
    echo "ALL NOTIFICATION INTEGRATION CHECKS PASSED\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($ids as $id) $pdo->prepare('DELETE FROM photo_albums WHERE id=?')->execute([$id]);
    $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$clientId]);
}
