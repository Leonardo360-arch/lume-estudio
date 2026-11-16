<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/AgendamentoHelper.php';

use Danny\Database;
use function Danny\validarAgendamento;
use function Danny\bloquearAgenda;
use function Danny\duracaoContratada;
use function Danny\erroBancoParaCodigo;


$clienteId = require_client();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cliente.php#solicitar');
    exit;
}

$fotografoId = (int) ($_POST['fotografo_id'] ?? 0);
$tipoServicoId = (int) ($_POST['tipo_servico_id'] ?? 0);
$cenarioId = !empty($_POST['cenario_id']) ? (int) $_POST['cenario_id'] : null;
$data = $_POST['data'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$observacao = trim($_POST['observacao'] ?? '');
$status = 'PENDENTE'; // Cliente sempre envia como pendente.

if ($fotografoId <= 0 || $tipoServicoId <= 0 || $data === '' || $horaInicio === '') {
    header('Location: ../cliente.php?erro=campos#solicitar');
    exit;
}

try {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    bloquearAgenda($pdo, [$fotografoId]);
    $duracao = duracaoContratada($pdo, $tipoServicoId);

    [$valido, $motivo] = validarAgendamento($pdo, $fotografoId, $tipoServicoId, $cenarioId, $data, $horaInicio, $status, null, $duracao);

    if (!$valido) {
        $pdo->rollBack();
        header('Location: ../cliente.php?erro=' . $motivo . '#solicitar');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO agendamentos
        (admin_id, fotografo_id, tipo_servico_id, cenario_id, data, hora_inicio, observacao, duracao_minutos, status)
        VALUES
        (:admin_id, :fotografo_id, :tipo_servico_id, :cenario_id, :data, :hora_inicio, :observacao, :duracao_minutos, 'PENDENTE')
    ");

    $stmt->execute([
        ':admin_id' => $clienteId,
        ':fotografo_id' => $fotografoId,
        ':tipo_servico_id' => $tipoServicoId,
        ':cenario_id' => $cenarioId,
        ':data' => $data,
        ':hora_inicio' => $horaInicio,
        ':duracao_minutos' => $duracao,
        ':observacao' => $observacao !== '' ? $observacao : null,
    ]);

    $pdo->commit();

    header('Location: ../cliente.php?agendamento=sucesso#meus-agendamentos');
    exit;
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../cliente.php?erro=' . rawurlencode($e->getMessage()));
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../cliente.php?erro=' . erroBancoParaCodigo($e->getMessage()) . '#solicitar');
    exit;
}
