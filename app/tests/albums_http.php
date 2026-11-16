<?php
/** Real HTTP + MySQL acceptance checks. Uses only the disposable lume_test_albums database.
 * ALBUM_TEST_DB_HOST=127.0.0.1 ALBUM_TEST_DB_PORT=33306 MYSQL_CLIENT=mysql php tests/albums_http.php
 * The database is recreated and removed; application data is never used.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('zend.exception_ignore_args','1');
$app=dirname(__DIR__);
$checks=0;
function album_check(bool $condition,string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function album_http(string $base,string $path,string $jar,string $method='GET',array $fields=[],bool $multipart=false): array {
    $curl=curl_init($base.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15]);
    if ($method!=='GET') { curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$method); curl_setopt($curl,CURLOPT_POSTFIELDS,$multipart?$fields:http_build_query($fields)); }
    if ($method!=='GET' && !$multipart) { curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type: application/x-www-form-urlencoded']); }
    $response=curl_exec($curl);
    if ($response===false) { $error=curl_error($curl); curl_close($curl); return ['status'=>0,'body'=>'','headers'=>[],'error'=>$error]; }
    $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $headerSize=curl_getinfo($curl,CURLINFO_HEADER_SIZE);
    $headers=[];
    foreach (explode("\r\n",substr($response,0,$headerSize)) as $line) {
        if (str_contains($line,':')) { [$name,$value]=explode(':',$line,2); $headers[strtolower(trim($name))]=trim($value); }
    }
    curl_setopt($curl,CURLOPT_COOKIELIST,'FLUSH'); curl_close($curl);
    return ['status'=>$status,'body'=>substr($response,$headerSize),'headers'=>$headers];
}
function album_csrf(array $response): string {
    preg_match('/name="_csrf"\s+value="([a-f0-9]{64})"/',$response['body'],$match);
    album_check(isset($match[1]),'Form did not expose its CSRF token.');
    return $match[1];
}
function album_login(string $base,string $jar,string $email,string $password,bool $admin=false): string {
    $page=$admin?'/login.php':'/login-cliente.php';
    $token=album_csrf(album_http($base,$page,$jar));
    $result=album_http($base,$admin?'/actions/login.php':'/actions/login-cliente.php',$jar,'POST',['_csrf'=>$token,'email'=>$email,'senha'=>$password]);
    album_check($result['status']===302,'Fixture authentication failed.');
    $panel=album_http($base,$admin?'/album-admin.php':'/cliente.php',$jar);
    album_check($panel['status']===200,'Authenticated fixture panel failed.');
    return album_csrf($panel);
}
function album_action(string $base,string $jar,string $token,array $fields): array {
    $result=album_http($base,'/album-admin.php?format=json',$jar,'POST',['_csrf'=>$token]+$fields);
    return $result+['json'=>json_decode($result['body'],true)];
}
function album_create(string $base,string $jar,string $token,int $client,string $title): string {
    $result=album_action($base,$jar,$token,['action'=>'create','client_id'=>$client,'title'=>$title,'description'=>'Synthetic acceptance fixture']);
    album_check($result['status']===200 && !empty($result['json']['ok']),'Create album failed.');
    parse_str(parse_url($result['json']['url'],PHP_URL_QUERY),$query);
    album_check((bool)preg_match('/\A[a-f0-9]{32}\z/D',$query['id']??''),'Album identifier malformed.');
    return $query['id'];
}
function album_row(PDO $pdo,string $id): array { $query=$pdo->prepare('SELECT * FROM photo_albums WHERE id=?'); $query->execute([$id]); return $query->fetch(); }
function album_photos(PDO $pdo,string $id): array { $query=$pdo->prepare('SELECT * FROM album_photos WHERE album_id=? ORDER BY id'); $query->execute([$id]); return $query->fetchAll(); }
function album_upload(string $base,string $jar,string $token,string $id,string $file,string $mime,string $name): array {
    return album_http($base,'/album-admin.php?format=json',$jar,'POST',['_csrf'=>$token,'action'=>'upload','album_id'=>$id,'photos[0]'=>new CURLFile($file,$mime,$name)],true);
}
function album_tree_remove(string $directory): void {
    if (!is_dir($directory)) { return; }
    foreach (new FilesystemIterator($directory,FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir() && !$file->isLink()) { album_tree_remove($file->getPathname()); } else { unlink($file->getPathname()); }
    }
    rmdir($directory);
}
$host=getenv('ALBUM_TEST_DB_HOST')?:'127.0.0.1'; $port=getenv('ALBUM_TEST_DB_PORT')?:'33306';
$user=getenv('ALBUM_TEST_DB_USER')?:'root'; $password=getenv('ALBUM_TEST_DB_PASSWORD')?:'';
$database='lume_test_albums';
$pdo=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("DROP DATABASE IF EXISTS `$database`"); $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $pdo->exec("USE `$database`");
$temp=sys_get_temp_dir().'/lume-albums-http-'.bin2hex(random_bytes(8)); mkdir($temp,0700,true); mkdir($temp.'/sessions',0700);
$server=null;
$failed=false;
try {
    $environment=array_merge(getenv(),['DB_HOST'=>$host,'DB_PORT'=>$port,'DB_DATABASE'=>$database,'DB_USERNAME'=>$user,'DB_PASSWORD'=>$password,
        'APP_ENV'=>'local','APP_TIMEZONE'=>'UTC','ALBUM_STORAGE_PATH'=>$temp.'/photos']);
    $installer=proc_open([PHP_BINARY,$app.'/scripts/install.php'],[0=>['pipe','r'],1=>['file',$temp.'/install.log','a'],2=>['file',$temp.'/install.log','a']],$installPipes,$app,$environment);
    if (is_resource($installer)) { fclose($installPipes[0]); }
    album_check(is_resource($installer) && proc_close($installer)===0,'Isolated database installation failed; inspect installer behavior.');
    $fixturePassword=bin2hex(random_bytes(24)); $hash=password_hash($fixturePassword,PASSWORD_DEFAULT);
    $accounts=[];
    foreach (['admin'=>'ADMIN','alice'=>'CLIENTE','bob'=>'CLIENTE'] as $name=>$type) {
        $pdo->prepare("INSERT INTO admins (nome,email,telefone,senha_hash,senha_provisoria,tipo,status_admin) VALUES (?,?,?,?,0,?,'APROVADO')")
            ->execute(['Synthetic '.$name,$name.'@example.test','(11) 99999-0000',$hash,$type]);
        $accounts[$name]=(int)$pdo->lastInsertId();
    }
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); if (!$socket) { throw new RuntimeException('Cannot reserve HTTP test port.'); }
    $address=stream_socket_get_name($socket,false); fclose($socket); $base='http://'.$address;
    $environment['APP_URL']=$base;
    $server=proc_open([PHP_BINARY,'-d','session.save_path='.$temp.'/sessions','-d','upload_max_filesize=25M','-d','post_max_size=128M','-S',$address,'-t',$app],
        [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$serverPipes,$app,$environment);
    album_check(is_resource($server),'Cannot start acceptance HTTP server.'); fclose($serverPipes[0]);
    $jars=[]; foreach (['admin','alice','bob','visitor'] as $name) { $jars[$name]=$temp.'/'.$name.'.cookies'; }
    for ($try=0;$try<40;$try++) { if (album_http($base,'/login.php',$jars['visitor'])['status']===200) { break; } usleep(50000); }
    $pdo->prepare("UPDATE admins SET status_admin='PENDENTE' WHERE id=?")->execute([$accounts['admin']]);
    $pendingToken=album_csrf(album_http($base,'/login.php',$jars['admin']));
    $pendingLogin=album_http($base,'/actions/login.php',$jars['admin'],'POST',['_csrf'=>$pendingToken,'email'=>'admin@example.test','senha'=>$fixturePassword]);
    album_check(str_contains($pendingLogin['headers']['location']??'','erro=pendente'),'Pending admin login rejected incorrectly: status='.$pendingLogin['status'].' location='.($pendingLogin['headers']['location']??'(none)'));
    album_check(album_http($base,'/album-admin.php',$jars['admin'])['status']===302,'Pending administrator accessed album management.');
    $pdo->prepare("UPDATE admins SET status_admin='APROVADO' WHERE id=?")->execute([$accounts['admin']]);
    $tokens=[];
    foreach (['admin','alice','bob'] as $name) { $tokens[$name]=album_login($base,$jars[$name],$name.'@example.test',$fixturePassword,$name==='admin'); }
    $albums=[]; $photos=[];
    $image=imagecreatetruecolor(32,24); imagefill($image,0,0,imagecolorallocate($image,38,84,122)); imagejpeg($image,$temp.'/fixture.jpg',90); imagedestroy($image);
    file_put_contents($temp.'/fake.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    foreach (['alice','bob'] as $name) {
        $id=album_create($base,$jars['admin'],$tokens['admin'],$accounts[$name],'Synthetic album '.$name); $albums[$name]=$id;
        $draft=album_row($pdo,$id); album_check($draft['status']==='DRAFT' && $draft['expires_at']===null,'Draft started its deadline too early.');
        $before=album_photos($pdo,$id);
        $bad=album_upload($base,$jars['admin'],$tokens['admin'],$id,$temp.'/fake.svg','image/jpeg','fake.jpg');
        album_check(count(album_photos($pdo,$id))===count($before),'Disguised SVG was stored.');
        album_check(!empty(json_decode($bad['body'],true)['message']) && str_contains($bad['body'],'Falhas'),'Rejected upload did not report its failure.');
        $uploaded=album_upload($base,$jars['admin'],$tokens['admin'],$id,$temp.'/fixture.jpg','image/jpeg','ensaio "teste".jpg');
        album_check($uploaded['status']===200 && !empty(json_decode($uploaded['body'],true)['ok']),'Real HTTP JPEG upload failed.');
        $stored=album_photos($pdo,$id); album_check(count($stored)===1,'JPEG upload did not create exactly one photo.'); $photos[$name]=$stored[0];
        album_check($stored[0]['mime']==='image/jpeg' && (int)$stored[0]['width']===32 && (int)$stored[0]['height']===24,'Uploaded photo metadata incorrect.');
        album_check(!empty($stored[0]['thumbnail_key']),'Thumbnail was not created.');
        foreach (['/album-photo.php?id='.$stored[0]['id'],'/album-photo.php?id='.$stored[0]['id'].'&thumbnail=1','/album-download.php?album='.$id] as $route) {
            album_check(album_http($base,$route,$jars[$name])['status']===404,'Client accessed a draft asset.');
        }
        $noCsrf=album_http($base,'/album-admin.php?format=json',$jars['admin'],'POST',['action'=>'publish','album_id'=>$id,'confirm_publish'=>'1']);
        album_check($noCsrf['status']===403 && album_row($pdo,$id)['status']==='DRAFT','Publication accepted without CSRF.');
        $notAdmin=album_action($base,$jars[$name],$tokens[$name],['action'=>'publish','album_id'=>$id,'confirm_publish'=>'1']);
        album_check($notAdmin['status']===302 && album_row($pdo,$id)['status']==='DRAFT','Client published an album.');
        $noUploadCsrf=album_http($base,'/album-admin.php?format=json',$jars['admin'],'POST',['action'=>'upload','album_id'=>$id,'photos[0]'=>new CURLFile($temp.'/fixture.jpg','image/jpeg','fixture.jpg')],true);
        album_check($noUploadCsrf['status']===403 && count(album_photos($pdo,$id))===1,'Upload accepted without CSRF.');
        $clientUpload=album_upload($base,$jars[$name],$tokens[$name],$id,$temp.'/fixture.jpg','image/jpeg','fixture.jpg');
        album_check($clientUpload['status']===302 && count(album_photos($pdo,$id))===1,'Client uploaded a photo.');
        $published=album_action($base,$jars['admin'],$tokens['admin'],['action'=>'publish','album_id'=>$id,'confirm_publish'=>'1']);
        album_check($published['status']===200 && !empty($published['json']['ok']),'Publish album failed.');
        $row=album_row($pdo,$id);
        album_check(strtotime($row['expires_at'].' UTC')-strtotime($row['published_at'].' UTC')===14*86400,'Publication deadline is not 14 days.');
        album_check(strtotime($row['purge_at'].' UTC')-strtotime($row['expires_at'].' UTC')===7*86400,'Recovery deadline is not seven days.');
    }
    foreach (['alice'=>'bob','bob'=>'alice'] as $owner=>$other) {
        $photo=$photos[$owner]; $id=$albums[$owner];
        $routes=['/album-photo.php?id='.$photo['id'],'/album-photo.php?id='.$photo['id'].'&thumbnail=1','/album-download.php?album='.$id];
        foreach ($routes as $route) {
            album_check(album_http($base,$route,$jars[$owner])['status']===200,'Owner could not read published asset.');
            album_check(album_http($base,$route,$jars[$other])['status']===404,'Cross-account album asset exposure.');
            album_check(album_http($base,$route,$jars['visitor'])['status']===404,'Visitor read private asset.');
        }
        $original=album_http($base,$routes[0].'&download=1',$jars[$owner]);
        album_check(($original['headers']['content-type']??'')==='image/jpeg' && ($original['headers']['x-content-type-options']??'')==='nosniff','Original MIME hardening incorrect.');
        album_check(@getimagesizefromstring($original['body'])!==false && hash('sha256',$original['body'])===hash_file('sha256',$temp.'/fixture.jpg'),'Original download bytes changed or invalid.');
        album_check((bool)preg_match('/\Aattachment; filename="lume-[a-f0-9]{32}\.jpg"\z/D',$original['headers']['content-disposition']??''),'Download filename not generated safely.');
        $thumbnail=album_http($base,$routes[1],$jars[$owner]);
        album_check(($thumbnail['headers']['content-type']??'')==='image/webp' && @getimagesizefromstring($thumbnail['body'])!==false,'Thumbnail MIME/bytes invalid.');
        $archive=album_http($base,$routes[2],$jars[$owner]); file_put_contents($temp.'/fixture.zip',$archive['body']);
        $zip=new ZipArchive(); album_check(($archive['headers']['content-type']??'')==='application/zip' && $zip->open($temp.'/fixture.zip')===true,'ZIP payload invalid.');
        album_check($zip->numFiles===1 && preg_match('/\A001-lume-[a-f0-9]{32}\.jpg\z/D',$zip->getNameIndex(0))===1,'ZIP includes unsafe or unexpected entries.');
        album_check(hash('sha256',$zip->getFromIndex(0))===hash_file('sha256',$temp.'/fixture.jpg'),'ZIP original bytes differ.'); $zip->close();
    }
    // HTTP verifies persisted expiry; a fixed clock additionally checks equality without timing flakiness.
    $id=$albums['alice']; $photo=$photos['alice'];
    $pdo->prepare("UPDATE photo_albums SET expires_at=UTC_TIMESTAMP(),purge_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY) WHERE id=?")->execute([$id]);
    foreach (['/album-photo.php?id='.$photo['id'],'/album-photo.php?id='.$photo['id'].'&thumbnail=1','/album-download.php?album='.$id] as $route) {
        album_check(album_http($base,$route,$jars['alice'])['status']===404,'Expired owner asset remained accessible.');
    }
    album_check(album_http($base,'/album-photo.php?id='.$photo['id'],$jars['admin'])['status']===200,'Admin lost grace-period recovery access.');
    $priorVersion=(int)album_row($pdo,$id)['version'];
    $extended=album_action($base,$jars['admin'],$tokens['admin'],['action'=>'extend','album_id'=>$id,'days'=>'7']);
    album_check($extended['status']===200 && !empty($extended['json']['ok']),'Grace-period extension failed.');
    $after=album_row($pdo,$id); $remaining=strtotime($after['expires_at'].' UTC')-time();
    album_check($remaining>=7*86400-5 && $remaining<=7*86400+1 && (int)$after['version']===$priorVersion+1,'Extension did not restart expired deadline from now.');
    album_check(album_http($base,'/album-photo.php?id='.$photo['id'],$jars['alice'])['status']===200,'Extension did not reopen client access.');
    $pdo->prepare("UPDATE photo_albums SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY),purge_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?")->execute([$id]);
    foreach (['admin','alice'] as $actor) {
        foreach (['/album-photo.php?id='.$photo['id'],'/album-photo.php?id='.$photo['id'].'&thumbnail=1'] as $route) {
            album_check(album_http($base,$route,$jars[$actor])['status']===404,'Asset remained available after recovery deadline.');
        }
    }
    $past=album_action($base,$jars['admin'],$tokens['admin'],['action'=>'extend','album_id'=>$id,'days'=>'7']);
    album_check($past['status']===422 && empty($past['json']['ok']),'Post-grace album extension was allowed.');
    require_once $app.'/vendor/autoload.php';
    $clock=new class implements Danny\Albums\Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2030-01-01 12:00:00',new DateTimeZone('UTC')); } };
    $policy=new Danny\Albums\AlbumPolicy($clock);
    album_check(!$policy->readable(['client_id'=>$accounts['alice'],'status'=>'PUBLISHED','expires_at'=>'2030-01-01 12:00:00'],$accounts['alice']),'Exact expiry boundary remained readable.');
    album_check(!$policy->recoverable(['status'=>'PUBLISHED','purge_at'=>'2030-01-01 12:00:00']),'Exact recovery boundary remained recoverable.');
    $storage=new Danny\Albums\LocalPhotoStorage($temp.'/photos');
    $actualClock=new Danny\Albums\SystemClock();
    $service=new Danny\Albums\AlbumService(new Danny\Albums\PdoAlbumRepository($pdo),$storage,$actualClock,new Danny\Albums\AlbumPolicy($actualClock));
    album_check($service->purgeExpired(false)===1 && is_file($storage->path($photo['storage_key'])),'Purge dry-run changed files or count.');
    album_check($service->purgeExpired(true)===1,'Expired album was not purged.');
    album_check(album_row($pdo,$id)['status']==='PURGED' && count(album_photos($pdo,$id))===0,'Purge did not clear photo metadata.');
    album_check(!is_file($storage->path($photo['storage_key'])) && !is_file($storage->path($photo['thumbnail_key'])),'Purge left expired files behind.');
    album_check(album_http($base,'/album-photo.php?id='.$photos['bob']['id'],$jars['bob'])['status']===200,'Purge affected an active album.');
    echo "Album HTTP/database acceptance checks passed: {$checks}\n";
} catch (Throwable $error) {
    $failed=true;
    fwrite(STDERR,'Album acceptance failed after '.$checks.' checks: '.$error->getMessage()."\n");
    if (getenv('ALBUM_TEST_KEEP_LOGS')==='1') { fwrite(STDERR,'Diagnostic files retained at '.$temp."\n"); }
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec("DROP DATABASE IF EXISTS `$database`");
    if (getenv('ALBUM_TEST_KEEP_LOGS')!=='1') { album_tree_remove($temp); }
}
if ($failed) { exit(1); }
