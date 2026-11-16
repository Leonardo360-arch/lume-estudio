<?php
declare(strict_types=1);
require_once __DIR__.'/src/bootstrap.php';
use Danny\Albums\AlbumFactory;
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
try {
    $admin=!empty($_SESSION['admin_autorizado']) && (int)($_SESSION['admin_id']??0)>0;
    $clientId=(int)($_SESSION['cliente_id']??0);
    if (!$admin && $clientId<1) { throw new DomainException(); }
    $photo=AlbumFactory::repository()->photo((string)($_GET['id']??''));
    if (!$photo) { throw new DomainException(); }
    AlbumFactory::service()->authorize($photo['album_id'],$clientId,$admin);
    $thumbnail=isset($_GET['thumbnail']) && !empty($photo['thumbnail_key']);
    $path=AlbumFactory::storage()->path($thumbnail?$photo['thumbnail_key']:$photo['storage_key']);
    if (!is_file($path)) { throw new DomainException(); }
    $file=fopen($path,'rb'); if ($file===false) { throw new RuntimeException(); }
    session_write_close();
    $mime=$thumbnail?'image/webp':$photo['mime'];
    $extension=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
    $name='lume-'.$photo['id'].'.'.$extension;
    header('Content-Type: '.$mime); header('Content-Length: '.filesize($path));
    header('Content-Disposition: '.(isset($_GET['download'])?'attachment':'inline').'; filename="'.$name.'"');
    if ($_SERVER['REQUEST_METHOD']!=='HEAD') { fpassthru($file); } fclose($file);
} catch (DomainException) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Foto indisponível.'; }
catch (Throwable $e) { error_log('Album photo: '.$e->getMessage()); http_response_code(503); header('Content-Type: text/plain; charset=UTF-8'); echo 'Não foi possível carregar a foto.'; }
