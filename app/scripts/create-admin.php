<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../src/bootstrap.php';
use Danny\Database;

/** Credentials are read from stdin and never accepted as command-line arguments. */
function readAdminField(string $prompt, bool $secret=false): string {
    fwrite(STDOUT,$prompt);
    $terminal=$secret && stream_isatty(STDIN);
    $state=$terminal && function_exists('shell_exec') ? trim((string)shell_exec('stty -g 2>/dev/null')) : '';
    if ($terminal && $state==='') { throw new RuntimeException('Use entrada por pipe para informar a senha com segurança neste terminal.'); }
    if ($state!=='') { shell_exec('stty -echo 2>/dev/null'); }
    try { $value=fgets(STDIN); if ($value===false) { throw new RuntimeException('Entrada incompleta.'); } return rtrim($value,"\r\n"); }
    finally { if ($state!=='') { shell_exec('stty '.escapeshellarg($state).' 2>/dev/null'); fwrite(STDOUT,"\n"); } }
}
try {
    if (count($argv)>1) { fwrite(STDOUT,"Uso: php scripts/create-admin.php\nInforme nome, e-mail, senha e confirmação pela entrada padrão.\n"); exit(in_array('--help',$argv,true)?0:2); }
    $name=trim(readAdminField('Nome do administrador: '));
    $email=mb_strtolower(trim(readAdminField('E-mail: ')));
    $password=readAdminField('Senha (mínimo 12 caracteres): ',true);
    $confirm=readAdminField('Confirme a senha: ',true);
    if ($name==='' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || mb_strlen($password)<12 || strlen($password)>72 || !hash_equals($password,$confirm)) { throw new InvalidArgumentException('Nome, e-mail ou senha inválidos, ou confirmação diferente.'); }
    $pdo=Database::connection();
    $query=$pdo->prepare("INSERT INTO admins(nome,email,telefone,senha_hash,senha_provisoria,tipo,status_admin) VALUES (?,?,?, ?,0,'ADMIN','APROVADO')");
    $query->execute([$name,$email,'Não informado',password_hash($password,PASSWORD_DEFAULT)]);
    unset($password,$confirm);
    fwrite(STDOUT,"Administrador aprovado criado. Nenhuma senha foi registrada em arquivos.\n");
} catch (Throwable $e) { fwrite(STDERR,$e instanceof InvalidArgumentException?$e->getMessage()."\n":"Não foi possível criar o administrador. Confira a instalação e se o e-mail já existe.\n"); exit(1); }
