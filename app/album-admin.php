<?php
declare(strict_types=1);
require_once __DIR__.'/src/bootstrap.php';
use Danny\Albums\AlbumFactory;
use Danny\Database;
require_admin();
function ae(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$id=(string)($_GET['id']??$_POST['album_id']??''); $error=null; $selected=null; $photos=[]; $albums=[]; $clients=[]; $bookings=[];
try {
    $repository=AlbumFactory::repository(); $service=AlbumFactory::service(); $policy=AlbumFactory::policy();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $action=(string)($_POST['action']??''); $message='';
        switch ($action) {
            case 'create':
                $booking=(int)($_POST['booking_id']??0);
                $id=$service->create((int)($_POST['client_id']??0),$booking>0?$booking:null,(string)($_POST['title']??''),(string)($_POST['description']??''));
                $message='Rascunho criado. Envie as fotos e publique quando estiver pronto.'; break;
            case 'upload':
                $files=$_FILES['photos']??[];
                if (!isset($files['name']) || !is_array($files['name']) || count($files['name'])>20) { throw new InvalidArgumentException('Envie de 1 a 20 fotos por lote.'); }
                $success=0; $failures=[];
                foreach ($files['name'] as $index=>$name) {
                    try {
                        $service->upload($id,['name'=>$name,'tmp_name'=>$files['tmp_name'][$index]??'','error'=>$files['error'][$index]??UPLOAD_ERR_NO_FILE]); $success++;
                    } catch (InvalidArgumentException|DomainException $e) { $failures[]=(string)$name.': '.$e->getMessage(); }
                }
                $message=$success.' foto(s) enviada(s).'.($failures?' Falhas: '.implode(' · ',$failures):' Revise antes de publicar.');
                break;
            case 'publish':
                if (($_POST['confirm_publish']??'')!=='1') { throw new InvalidArgumentException('Confirme a publicação e o início do prazo.'); }
                $service->publish($id); $message='Álbum publicado por 14 dias. Aviso adicionado à fila de e-mail.'; break;
            case 'extend':
                $service->extend($id,(int)($_POST['days']??0)); $message='Prazo prorrogado. Novos lembretes foram agendados.'; break;
            default: throw new InvalidArgumentException('Ação inválida.');
        }
        if (($_GET['format']??'')==='json') { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true,'message'=>$message,'url'=>'album-admin.php?id='.$id],JSON_UNESCAPED_UNICODE); exit; }
        $_SESSION['album_message']=$message; header('Location: album-admin.php?id='.rawurlencode($id),true,303); exit;
    }
    $albums=$repository->all();
    if ($id!=='') { $selected=$repository->find($id); if (!$selected) { http_response_code(404); $error='Álbum não encontrado.'; } else { $photos=$repository->photos($id); } }
    $clients=Database::connection()->query("SELECT id,nome,email FROM admins WHERE tipo='CLIENTE' ORDER BY nome")->fetchAll();
    $bookings=Database::connection()->query('SELECT a.id,a.admin_id,a.data,u.nome FROM agendamentos a JOIN admins u ON u.id=a.admin_id ORDER BY a.data DESC LIMIT 500')->fetchAll();
} catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { error_log('Album admin: '.$e->getMessage()); http_response_code(503); $error='Não foi possível concluir. Confira a instalação e tente novamente.'; }
if ($error && ($_GET['format']??'')==='json') { http_response_code(422); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'message'=>$error],JSON_UNESCAPED_UNICODE); exit; }
$message=$_SESSION['album_message']??null; unset($_SESSION['album_message']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entregas de fotos — Lume Estúdio</title><link rel="stylesheet" href="css/albums.css"><script src="js/albums.js" defer></script></head>
<body><header class="app-header"><a class="brand" href="index.php">lume<span>ESTÚDIO</span></a><nav aria-label="Administração"><a href="admin.php">Agenda</a><a href="album-admin.php" aria-current="page">Entregas de fotos</a><form action="logout.php" method="post"><?= csrf_field() ?><button class="text-button">Sair</button></form></nav></header>
<main class="page"><p class="eyebrow">ADMINISTRAÇÃO · ENTREGA PRIVADA</p><h1>Memórias prontas para entregar.</h1><p class="intro">Crie o álbum, revise as fotos e publique. O prazo começa somente na publicação.</p>
<?php if ($error): ?><p role="alert" class="notice notice--error"><?= ae($error) ?></p><?php endif ?><?php if ($message): ?><p role="status" class="notice"><?= ae($message) ?></p><?php endif ?>
<?php if ($selected): ?><section class="panel"><a href="album-admin.php">← Todos os álbuns</a><h2><?= ae($selected['title']) ?></h2><p><?= ae($selected['description']) ?></p><p>Status: <strong><?= ae(['DRAFT'=>'Rascunho','PUBLISHED'=>'Publicado','PURGED'=>'Arquivos excluídos'][$selected['status']]) ?></strong> · <?= count($photos) ?> fotos</p>
<?php if ($selected['expires_at']): ?><p>Downloads até <?= ae($policy->localDate($selected['expires_at'])) ?> · Exclusão prevista a partir de <?= ae($policy->localDate($selected['purge_at'])) ?>, horário de Brasília.</p><?php endif ?>
<?php if ($selected['status']==='DRAFT'): ?>
<form action="album-admin.php?id=<?= ae($id) ?>" method="post" enctype="multipart/form-data" data-upload-form><?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="album_id" value="<?= ae($id) ?>"><label for="photos">Adicionar fotos (até 20 por lote, <?= (int)env('ALBUM_PHOTO_MAX_MB',25) ?> MB cada)</label><input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required><p>JPEG, PNG ou WebP. Limite do lote: 100 MB. Revise as fotos enviadas antes de publicar.</p><progress max="100" value="0" hidden aria-label="Progresso do envio"></progress><p role="status" data-upload-status></p><button class="button">Enviar fotos</button></form>
<form method="post" class="publish-form"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="album_id" value="<?= ae($id) ?>"><label class="checkbox"><input type="checkbox" name="confirm_publish" value="1" required> Revisei as fotos e quero liberar o acesso ao cliente por 14 dias.</label><button class="button" <?= !$photos?'disabled':'' ?>>Publicar álbum</button></form>
<?php elseif ($selected['status']==='PUBLISHED' && $policy->recoverable($selected)): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="extend"><input type="hidden" name="album_id" value="<?= ae($id) ?>"><label for="days">Acrescentar dias ao prazo (se já venceu, contar a partir de agora)</label><input id="days" type="number" name="days" min="1" max="90" value="7" required><button class="button">Prorrogar acesso</button></form>
<?php endif ?>
<?php if ($selected['status']==='DRAFT' || ($selected['status']==='PUBLISHED' && $policy->recoverable($selected))): ?><div class="photo-grid"><?php foreach ($photos as $photo): ?><figure><img src="album-photo.php?id=<?= ae($photo['id']) ?>&amp;thumbnail=1" alt="<?= ae($photo['original_name']) ?>" width="640" height="480" loading="lazy"><figcaption><?= ae($photo['original_name']) ?></figcaption></figure><?php endforeach ?></div><?php endif ?></section>
<?php else: ?>
<section class="panel"><h2>Criar álbum privado</h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div class="form-grid"><div><label for="client_id">Cliente</label><select id="client_id" name="client_id" required><option value="">Selecione o cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int)$client['id'] ?>"><?= ae($client['nome'].' · '.$client['email']) ?></option><?php endforeach ?></select></div><div><label for="booking_id">Agendamento (opcional)</label><select id="booking_id" name="booking_id"><option value="">Sem vínculo</option><?php foreach ($bookings as $booking): ?><option value="<?= (int)$booking['id'] ?>" data-client="<?= (int)$booking['admin_id'] ?>">#<?= (int)$booking['id'] ?> · <?= ae($booking['nome'].' · '.$booking['data']) ?></option><?php endforeach ?></select></div></div><label for="title">Título do álbum</label><input id="title" name="title" maxlength="160" placeholder="Ensaio em família — Novembro" required><label for="description">Mensagem para o cliente (opcional)</label><textarea id="description" name="description" maxlength="1000" rows="3"></textarea><button class="button">Criar rascunho</button></form></section>
<h2>Álbuns do estúdio</h2><?php if (!$albums): ?><p class="empty">Nenhum álbum criado. Cadastre um cliente na agenda e prepare sua primeira entrega.</p><?php endif ?><div class="album-grid"><?php foreach ($albums as $album): ?><article class="album-card"><span class="tag"><?= ae(['DRAFT'=>'Rascunho','PUBLISHED'=>'Publicado','PURGED'=>'Encerrado'][$album['status']]) ?></span><h3><?= ae($album['title']) ?></h3><p><?= ae($album['client_name']) ?> · <?= (int)$album['photo_count'] ?> fotos</p><?php if ($album['expires_at']): ?><p>Prazo: <?= ae($policy->localDate($album['expires_at'])) ?></p><?php endif ?><a href="album-admin.php?id=<?= ae($album['id']) ?>">Gerenciar entrega →</a></article><?php endforeach ?></div>
<?php endif ?></main><footer class="app-footer">Lume Estúdio · Entrega temporária de fotografias</footer></body></html>
