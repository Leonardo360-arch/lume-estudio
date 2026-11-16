<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Danny\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../criar-conta.php');
    exit;
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$senha = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

$_SESSION['registration_old'] = ['nome' => mb_substr($nome,0,120), 'email' => mb_substr($email,0,150), 'telefone' => mb_substr($telefone,0,30), 'nicho_id' => (int)($_POST['nicho_id']??0), 'tipo_servico_id' => (int)($_POST['tipo_servico_id']??0)];
if (mb_strlen($nome)>120 || mb_strlen($email)>150 || mb_strlen($telefone)>30) { header('Location: ../criar-conta.php?erro=campos'); exit; }
if (!is_string($senha) || mb_strlen($senha)<12 || strlen($senha)>72) { header('Location: ../criar-conta.php?erro=senha_curta'); exit; }

if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
    header('Location: ../criar-conta.php?erro=campos');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../criar-conta.php?erro=email');
    exit;
}

if ($senha !== $confirmarSenha) {
    header('Location: ../criar-conta.php?erro=senha');
    exit;
}

try {
    $pdo = Database::connection();

    $check = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);

    if ($check->fetch()) {
        header('Location: ../criar-conta.php?erro=existe');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO admins (nome, email, telefone, senha_hash, senha_provisoria, tipo, status_admin)
        VALUES (:nome, :email, :telefone, :senha_hash, 0, 'ADMIN', 'PENDENTE')
    ");

    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone !== '' ? $telefone : 'Não informado',
        ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
    ]);

    unset($_SESSION['registration_old']);
    header('Location: ../login.php?cadastro=pendente');
    exit;
} catch (PDOException $e) {
    header('Location: ../criar-conta.php?erro=banco');
    exit;
}
