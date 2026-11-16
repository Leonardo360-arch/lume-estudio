<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Danny\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cadastro-cliente.php');
    exit;
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$nichoId = (int) ($_POST['nicho_id'] ?? 0) ?: null;
$tipoServicoId = (int) ($_POST['tipo_servico_id'] ?? 0) ?: null;
$senha = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

$_SESSION['registration_old'] = ['nome' => mb_substr($nome,0,120), 'email' => mb_substr($email,0,150), 'telefone' => mb_substr($telefone,0,30), 'nicho_id' => (int)($_POST['nicho_id']??0), 'tipo_servico_id' => (int)($_POST['tipo_servico_id']??0)];
if (mb_strlen($nome)>120 || mb_strlen($email)>150 || mb_strlen($telefone)>30) { header('Location: ../cadastro-cliente.php?erro=campos'); exit; }
if (!is_string($senha) || mb_strlen($senha)<12 || strlen($senha)>72) { header('Location: ../cadastro-cliente.php?erro=senha_curta'); exit; }

if ($nome === '' || $email === '' || $telefone === '' || $senha === '' || $confirmarSenha === '') {
    header('Location: ../cadastro-cliente.php?erro=campos');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../cadastro-cliente.php?erro=email');
    exit;
}

if ($senha !== $confirmarSenha) {
    header('Location: ../cadastro-cliente.php?erro=senha');
    exit;
}

try {
    $pdo = Database::connection();

    if ($nichoId !== null) {
        $valid = $pdo->prepare('SELECT id FROM nichos WHERE id=?'); $valid->execute([$nichoId]);
        if (!$valid->fetch()) { header('Location: ../cadastro-cliente.php?erro=campos'); exit; }
    }
    if ($tipoServicoId !== null) {
        $valid = $pdo->prepare('SELECT id FROM tipos_servico WHERE id=? AND nicho_id=?'); $valid->execute([$tipoServicoId,$nichoId]);
        if (!$valid->fetch()) { header('Location: ../cadastro-cliente.php?erro=campos'); exit; }
    }
    $check = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);

    if ($check->fetch()) {
        header('Location: ../cadastro-cliente.php?erro=existe');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO admins (nome, email, telefone, senha_hash, senha_provisoria, nicho_id, tipo_servico_id, tipo) VALUES (:nome, :email, :telefone, :senha_hash, 0, :nicho_id, :tipo_servico_id, 'CLIENTE')
    ");

    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone,
        ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        ':nicho_id' => $nichoId,
        ':tipo_servico_id' => $tipoServicoId,
    ]);

    unset($_SESSION['registration_old']);
    header('Location: ../login-cliente.php?cadastro=sucesso');
    exit;
} catch (PDOException $e) {
    header('Location: ../cadastro-cliente.php?erro=banco');
    exit;
}
