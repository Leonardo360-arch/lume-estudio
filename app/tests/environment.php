<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=dirname(__DIR__);
$temporary=sys_get_temp_dir().'/lume-env-'.bin2hex(random_bytes(8));
mkdir($temporary.'/src',0700,true); mkdir($temporary.'/vendor',0700);
try {
    foreach (['bootstrap.php','Security.php','helpers.php'] as $file) { copy($app.'/src/'.$file,$temporary.'/src/'.$file); }
    file_put_contents($temporary.'/vendor/autoload.php','<?php require '.var_export($app.'/vendor/autoload.php',true).';');
    file_put_contents($temporary.'/.env',"DB_HOST=dotenv.invalid\nAPP_NAME=DotenvFallback\n");
    $script='require '.var_export($temporary.'/src/bootstrap.php',true).'; echo json_encode([env("DB_HOST"),env("APP_NAME")]);';
    $environment=getenv(); $environment['DB_HOST']='process.invalid'; unset($environment['APP_NAME']);
    foreach (['GPCS','EGPCS'] as $order) {
        $process=proc_open([PHP_BINARY,'-d','variables_order='.$order,'-r',$script],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$app,$environment);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start environment regression.'); }
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process)!==0 || json_decode($output,true)!==['process.invalid','DotenvFallback']) { throw new RuntimeException('Process variables were overridden with variables_order='.$order); }
    }
    echo "Environment precedence checks passed: 2\n";
} finally {
    foreach (['src/bootstrap.php','src/Security.php','src/helpers.php','vendor/autoload.php','.env'] as $file) { if (is_file($temporary.'/'.$file)) { unlink($temporary.'/'.$file); } }
    rmdir($temporary.'/src'); rmdir($temporary.'/vendor'); rmdir($temporary);
}
