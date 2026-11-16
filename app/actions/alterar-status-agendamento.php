<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/AgendamentoHelper.php';

use Danny\Database;
use function Danny\validarAgendamento;
use function Danny\exigeAntecedencia;
use function Danny\carregarAgendamentoParaAlteracao;
use function Danny\erroBancoParaCodigo;


require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit;
}

$agendamentoId = (int) ($_POST['agendamento_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($agendamentoId <= 0 || !in_array($status, ['PENDENTE', 'CONFIRMADO', 'RECUSADO', 'CANCELADO', 'CONCLUIDO'], true)) {
    header('Location: ../admin.php?erro=status#agenda');
    exit;
}

try {
    $pdo = Database::connection();
    $agendamento = carregarAgendamentoParaAlteracao($pdo, $agendamentoId);

    if (!$agendamento) {
        header('Location: ../admin.php?erro=status#agenda');
        exit;
    }

    if (in_array($status, ['PENDENTE', 'CONFIRMADO'], true)) {
        [$valido, $motivo] = validarAgendamento(
            $pdo,
            (int) $agendamento['fotografo_id'],
            (int) $agendamento['tipo_servico_id'],
            $agendamento['cenario_id'] !== null ? (int) $agendamento['cenario_id'] : null,
            $agendamento['data'],
            $agendamento['hora_inicio'],
            $status,
            $agendamentoId,
            (int) $agendamento['duracao_minutos'],
            exigeAntecedencia($agendamento, (int) $agendamento['fotografo_id'], (int) $agendamento['tipo_servico_id'], $agendamento['data'], $agendamento['hora_inicio'])
        );

        if (!$valido) {
            $pdo->rollBack();
            header('Location: ../admin.php?erro=' . $motivo . '#agenda');
            exit;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE agendamentos
        SET status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':status' => $status,
        ':id' => $agendamentoId,
    ]);

    $pdo->commit();

    $ancora = $status === 'PENDENTE' ? '#pedidos-agendamento' : '#agenda';
    header('Location: ../admin.php?status=sucesso' . $ancora);
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
    header('Location: ../admin.php?erro=' . erroBancoParaCodigo($e->getMessage()) . '#agenda');
    exit;
}
