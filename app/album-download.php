<?php
declare(strict_types=1);
require_once __DIR__.'/src/bootstrap.php';
use Danny\Albums\AlbumFactory;
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$temporary=null;
try {
    $clientId=(int)($_SESSION['cliente_id']??0);
    if ($clientId<1) { throw new DomainException(); }
    $album=AlbumFactory::service()->authorize((string)($_GET['album']??''),$clientId);
    $photos=AlbumFactory::repository()->photos($album['id']);
    // ZIP is built on disk, not held in PHP memory; large deliveries use individual downloads.
    if (!$photos || array_sum(array_column($photos,'bytes'))>500*1024*1024) { throw new LengthException('Para álbuns acima de 500 MB, baixe as fotos individualmente.'); }
    $temporary=tempnam(sys_get_temp_dir(),'lume_zip_'); if ($temporary===false) { throw new RuntimeException(); }
    chmod($temporary,0600); register_shutdown_function(static function () use ($temporary) { if (is_file($temporary)) { unlink($temporary); } });
    $zip=new ZipArchive(); if ($zip->open($temporary,ZipArchive::OVERWRITE)!==true) { throw new RuntimeException(); }
    $storage=AlbumFactory::storage();
    foreach ($photos as $index=>$photo) {
        $extension=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$photo['mime']];
        $path=$storage->path($photo['storage_key']);
        if (!is_file($path) || !$zip->addFile($path,sprintf('%03d-lume-%s.%s',$index+1,$photo['id'],$extension))) { $zip->close(); throw new RuntimeException(); }
    }
    if (!$zip->close()) { throw new RuntimeException(); }
    // Building the archive may take time; recheck expiry immediately before serving bytes.
    AlbumFactory::service()->authorize($album['id'],$clientId);
    session_write_close(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="lume-album-'.$album['id'].'.zip"'); header('Content-Length: '.filesize($temporary));
    if ($_SERVER['REQUEST_METHOD']!=='HEAD') { readfile($temporary); }
} catch (DomainException) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Álbum indisponível.'; }
catch (LengthException $e) { http_response_code(413); header('Content-Type: text/plain; charset=UTF-8'); echo $e->getMessage(); }
catch (Throwable $e) { error_log('Album zip: '.$e->getMessage()); http_response_code(503); header('Content-Type: text/plain; charset=UTF-8'); echo 'Não foi possível preparar o download. Tente as fotos individuais.'; }
finally { if ($temporary && is_file($temporary)) { unlink($temporary); } }
