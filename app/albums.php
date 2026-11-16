<?php
declare(strict_types=1);
require_once __DIR__.'/src/bootstrap.php';
use Danny\Albums\AlbumFactory;
$clientId=require_client();
function ae(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$policy=AlbumFactory::policy(); $albums=[]; $selected=null; $photos=[]; $error=null;
try {
    $repository=AlbumFactory::repository(); $albums=$repository->forClient($clientId);
    if (isset($_GET['id'])) { $selected=AlbumFactory::service()->authorize((string)$_GET['id'],$clientId); $photos=$repository->photos($selected['id']); }
} catch (DomainException) { http_response_code(404); $error='Este álbum não está disponível. Confira o prazo ou fale com o estúdio.'; }
catch (Throwable $e) { error_log('Album client: '.$e->getMessage()); http_response_code(503); $error='Não foi possível carregar os álbuns. Tente novamente em instantes.'; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meus álbuns — Lume Estúdio</title><link rel="stylesheet" href="css/albums.css"></head>
<body><header class="app-header"><a class="brand" href="index.php">lume<span>ESTÚDIO</span></a><nav aria-label="Área do cliente"><a href="cliente.php">Meus agendamentos</a><a href="albums.php" aria-current="page">Meus álbuns</a><form action="logout-cliente.php" method="post"><?= csrf_field() ?><button class="text-button">Sair</button></form></nav></header>
<main class="page"><p class="eyebrow">SUAS HISTÓRIAS, BEM GUARDADAS</p><h1><?= $selected ? ae($selected['title']) : 'Meus álbuns' ?></h1>
<?php if ($error): ?><p class="notice notice--error" role="alert"><?= ae($error) ?></p><?php endif ?>
<?php if ($selected): ?>
<a href="albums.php">← Todos os álbuns</a><p><?= nl2br(ae($selected['description'])) ?></p>
<div class="notice"><strong>Disponível até <?= ae($policy->localDate($selected['expires_at'])) ?> (horário de Brasília).</strong><p>Baixe suas fotos e guarde uma cópia. O acesso à galeria e aos downloads termina nessa data.</p><a class="button" href="album-download.php?album=<?= ae($selected['id']) ?>">Baixar álbum completo (.zip)</a></div>
<p><?= count($photos) ?> fotos · Clique para visualizar em tamanho completo.</p><div class="photo-grid">
<?php foreach ($photos as $photo): ?><figure><a href="album-photo.php?id=<?= ae($photo['id']) ?>" target="_blank" rel="noopener"><img src="album-photo.php?id=<?= ae($photo['id']) ?>&amp;thumbnail=1" alt="<?= ae($photo['original_name']) ?>" width="640" height="480" loading="lazy"></a><figcaption><span><?= ae($photo['original_name']) ?></span><a href="album-photo.php?id=<?= ae($photo['id']) ?>&amp;download=1" aria-label="Baixar <?= ae($photo['original_name']) ?>">Baixar foto ↓</a></figcaption></figure><?php endforeach ?>
</div>
<?php else: ?>
<p class="intro">Um espaço só seu para revisitar e baixar as fotografias do seu ensaio.</p><p>Álbuns publicados ficam disponíveis por 14 dias. Você verá o prazo de cada entrega aqui.</p>
<?php if (!$albums && !$error): ?><div class="empty"><h2>Suas próximas memórias chegam aqui.</h2><p>Quando o estúdio publicar um álbum, ele aparecerá nesta página e você receberá um aviso por e-mail.</p><a class="button" href="cliente.php">Acompanhar meus ensaios</a></div><?php endif ?>
<div class="album-grid"><?php foreach ($albums as $album): $active=$policy->readable($album,$clientId); ?><article class="album-card"><span class="tag"><?= $active ? 'Disponível' : ($album['status']==='PURGED' ? 'Entrega encerrada' : 'Prazo encerrado') ?></span><h2><?= ae($album['title']) ?></h2><p><?= ae($album['description']) ?></p><p><?= (int)$album['photo_count'] ?> fotos</p><?php if ($album['expires_at']): ?><p>Disponível até <?= ae($policy->localDate($album['expires_at'])) ?></p><?php endif ?><?php if ($active): ?><a class="button" href="albums.php?id=<?= ae($album['id']) ?>">Abrir meu álbum →</a><?php else: ?><p>Se precisar de mais tempo, solicite uma prorrogação ao estúdio.</p><?php endif ?></article><?php endforeach ?></div>
<?php endif ?></main><footer class="app-footer">Lume Estúdio · Área privada do cliente</footer></body></html>
