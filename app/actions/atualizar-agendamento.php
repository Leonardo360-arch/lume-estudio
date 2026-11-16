<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/AgendamentoHelper.php';

use Danny\Database;
use function Danny\validarAgendamento;
use function Danny\duracaoContratada;
use function Danny\exigeAntecedencia;
use function Danny\carregarAgendamentoParaAlteracao;
use function Danny\erroBancoParaCodigo;


require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit;
}

$agendamentoId = (int) ($_POST['agendamento_id'] ?? 0);
$clienteNome = trim($_POST['cliente_nome'] ?? '');
$fotografoId = (int) ($_POST['fotografo_id'] ?? 0);
$tipoServicoId = (int) ($_POST['tipo_servico_id'] ?? 0);
$cenarioId = !empty($_POST['cenario_id']) ? (int) $_POST['cenario_id'] : null;
$data = $_POST['data'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$observacao = trim($_POST['observacao'] ?? '');
$valorEntrada = trim((string) ($_POST['valor'] ?? ''));
$valor = $valorEntrada !== '' ? str_replace(',', '.', $valorEntrada) : null;
if ($valor !== null && (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $valor))) {
    header('Location: ../admin.php?erro=campos');
    exit;
}
$status = $_POST['status'] ?? 'CONFIRMADO';

if (
    $agendamentoId <= 0 ||
    $clienteNome === '' ||
    $fotografoId <= 0 ||
    $tipoServicoId <= 0 ||
    $data === '' ||
    $horaInicio === '' ||
    !in_array($status, ['PENDENTE', 'CONFIRMADO', 'RECUSADO', 'CANCELADO', 'CONCLUIDO'], true)
) {
    header('Location: ../admin.php?erro=campos#agenda');
    exit;
}

try {
    $pdo = Database::connection();
    $agendamentoAtual = carregarAgendamentoParaAlteracao($pdo, $agendamentoId, [$fotografoId]);

    if (!$agendamentoAtual) {
        header('Location: ../admin.php?erro=status#agenda');
        exit;
    }

    $duracao = duracaoContratada($pdo, $tipoServicoId, $agendamentoAtual);

    [$valido, $motivo] = validarAgendamento(
        $pdo,
        $fotografoId,
        $tipoServicoId,
        $cenarioId,
        $data,
        $horaInicio,
        $status,
        $agendamentoId,
        $duracao,
        exigeAntecedencia($agendamentoAtual, $fotografoId, $tipoServicoId, $data, $horaInicio)
    );

    if (!$valido) {
        $pdo->rollBack();
        header('Location: ../editar-agendamento.php?id=' . $agendamentoId . '&erro=' . $motivo);
        exit;
    }

    // Dados do perfil são alterados somente pela ação específica do cliente.
    $stmt = $pdo->prepare("
        UPDATE agendamentos
        SET
            fotografo_id = :fotografo_id,
            tipo_servico_id = :tipo_servico_id,
            cenario_id = :cenario_id,
            data = :data,
            hora_inicio = :hora_inicio,
            observacao = :observacao,
            valor = :valor,
            duracao_minutos = :duracao_minutos,
            status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':fotografo_id' => $fotografoId,
        ':tipo_servico_id' => $tipoServicoId,
        ':cenario_id' => $cenarioId,
        ':data' => $data,
        ':hora_inicio' => $horaInicio,
        ':observacao' => $observacao !== '' ? $observacao : null,
        ':valor' => $valor,
        ':duracao_minutos' => $duracao,
        ':status' => $status,
        ':id' => $agendamentoId,
    ]);

    $pdo->commit();

    $destino = $status === 'PENDENTE' ? '../admin.php?agendamento=atualizado#pedidos-agendamento' : '../admin.php?agendamento=atualizado#agenda';
    header('Location: ' . $destino);
    exit;
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../admin.php?erro=' . rawurlencode($e->getMessage()));
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: ../editar-agendamento.php?id=' . $agendamentoId . '&erro=' . erroBancoParaCodigo($e->getMessage()));
    exit;
}
