<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Danny\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../esqueci-senha.php');
    exit;
}

$token = is_string($_POST['token'] ?? null) ? trim($_POST['token']) : '';
$senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
$confirmarSenha = is_string($_POST['confirmar_senha'] ?? null) ? $_POST['confirmar_senha'] : '';

if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
    header('Location: ../esqueci-senha.php?erro=token');
    exit;
}

if (mb_strlen($senha) < 12 || strlen($senha) > 72) {
    header('Location: ../redefinir-senha.php?token=' . urlencode($token) . '&erro=senha_curta');
    exit;
}

if ($senha !== $confirmarSenha) {
    header('Location: ../redefinir-senha.php?token=' . urlencode($token) . '&erro=senhas');
    exit;
}

try {
    $pdo = Database::connection();
    $tokenHash = hash('sha256', $token);
    // Hash before locking so the transaction stays short.
    $passwordHash = password_hash($senha, PASSWORD_DEFAULT);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT pr.id AS reset_id, a.id AS admin_id, a.tipo
        FROM password_resets pr
        INNER JOIN admins a ON a.id = pr.admin_id
        WHERE pr.token_hash = :token_hash
          AND pr.used_at IS NULL
          AND pr.expires_at > NOW()
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':token_hash' => $tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $pdo->rollBack();
        header('Location: ../redefinir-senha.php?erro=token');
        exit;
    }

    $pdo->prepare("
        UPDATE admins
        SET senha_hash = :senha_hash,
            senha_provisoria = 0
        WHERE id = :admin_id
    ")->execute([
        ':senha_hash' => $passwordHash,
        ':admin_id' => $reset['admin_id'],
    ]);

    $consume = $pdo->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE id = :reset_id
          AND used_at IS NULL
          AND expires_at > NOW()
    ");
    $consume->execute([':reset_id' => $reset['reset_id']]);
    if ($consume->rowCount() !== 1) {
        throw new RuntimeException('Token inválido ou já utilizado.');
    }

    $pdo->commit();

    $destino = $reset['tipo'] === 'CLIENTE' ? '../login-cliente.php' : '../login.php';
    header('Location: ' . $destino . '?senha=alterada');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../redefinir-senha.php?token=' . urlencode($token) . '&erro=banco');
    exit;
}
