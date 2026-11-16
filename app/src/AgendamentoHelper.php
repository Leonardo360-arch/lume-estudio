<?php

declare(strict_types=1);

namespace Danny;

use DateTime;
use PDO;

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :tabela
          AND COLUMN_NAME = :coluna
    ");

    $stmt->execute([
        ':tabela' => $tabela,
        ':coluna' => $coluna,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Resolve identidade por e-mail; clientes sem e-mail sempre recebem um cadastro próprio. */
function buscarOuCriarCliente(PDO $pdo, string $nomeCliente, int $tipoServicoId, ?string $telefoneCliente = null, ?string $emailCliente = null): int
{
    $email = strtolower(trim((string) $emailCliente));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('email');
    }
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id, tipo FROM admins WHERE email = :email LIMIT 1 FOR UPDATE');
        $stmt->execute([':email' => $email]);
        $cliente = $stmt->fetch();
        if ($cliente) {
            if ($cliente['tipo'] !== 'CLIENTE') {
                throw new \InvalidArgumentException('email');
            }
            // Agendar não autoriza reescrever o perfil da conta encontrada.
            return (int) $cliente['id'];
        }
    }

    $stmt = $pdo->prepare("INSERT INTO admins
        (nome, email, telefone, senha_hash, senha_provisoria, tipo, tipo_servico_id)
        VALUES (:nome, :email, :telefone, :senha_hash, 1, 'CLIENTE', :tipo_servico_id)");
    $stmt->execute([
        ':nome' => trim($nomeCliente),
        ':email' => $email ?: 'cliente_' . bin2hex(random_bytes(16)) . '@sememail.invalid',
        ':telefone' => trim((string) $telefoneCliente) ?: 'Não informado',
        // Conta criada pelo estúdio precisa recuperar/definir senha antes de entrar.
        ':senha_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        ':tipo_servico_id' => $tipoServicoId,
    ]);
    return (int) $pdo->lastInsertId();
}

/** A linha do fotógrafo serializa reservas, inclusive quando a agenda está vazia. */
function bloquearAgenda(PDO $pdo, array $fotografoIds): void
{
    if (!$pdo->inTransaction()) {
        throw new \LogicException('O bloqueio da agenda exige transação.');
    }
    $ids = array_values(array_unique(array_map('intval', $fotografoIds)));
    sort($ids, SORT_NUMERIC);
    $stmt = $pdo->prepare('SELECT id FROM fotografos WHERE id = :id FOR UPDATE');
    foreach ($ids as $id) {
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException('fotografo');
        }
    }
}

/** Ordem única: fotógrafo(s), depois reserva. Evita inversão de locks entre aprovações. */
function carregarAgendamentoParaAlteracao(PDO $pdo, int $id, array $fotografosAdicionais = []): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM agendamentos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $inicial = $stmt->fetch();
    if (!$inicial) {
        return null;
    }
    $pdo->beginTransaction();
    bloquearAgenda($pdo, array_merge([(int) $inicial['fotografo_id']], $fotografosAdicionais));
    $stmt = $pdo->prepare('SELECT * FROM agendamentos WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $atual = $stmt->fetch();
    if (!$atual || (int) $atual['fotografo_id'] !== (int) $inicial['fotografo_id']) {
        $pdo->rollBack();
        throw new \InvalidArgumentException('conflito');
    }
    return $atual;
}

function duracaoContratada(PDO $pdo, int $tipoServicoId, ?array $agendamentoAtual = null): int
{
    if ($agendamentoAtual !== null && (int) $agendamentoAtual['tipo_servico_id'] === $tipoServicoId) {
        return (int) $agendamentoAtual['duracao_minutos'];
    }
    $stmt = $pdo->prepare('SELECT duracao_minutos FROM tipos_servico WHERE id = :id');
    $stmt->execute([':id' => $tipoServicoId]);
    $duracao = $stmt->fetchColumn();
    if ($duracao === false || (int) $duracao <= 0) {
        throw new \InvalidArgumentException('servico');
    }
    return (int) $duracao;
}

function exigeAntecedencia(?array $atual, int $fotografoId, int $tipoServicoId, string $data, string $horaInicio): bool
{
    return $atual === null || $atual['status'] !== 'CONFIRMADO'
        || (int) $atual['fotografo_id'] !== $fotografoId
        || (int) $atual['tipo_servico_id'] !== $tipoServicoId
        || $atual['data'] !== $data
        || $atual['hora_inicio'] !== (strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio);
}

function validarAgendamento(
    PDO $pdo,
    int $fotografoId,
    int $tipoServicoId,
    ?int $cenarioId,
    string $data,
    string $horaInicio,
    string $status = 'CONFIRMADO',
    ?int $ignorarAgendamentoId = null,
    ?int $duracaoMinutos = null,
    bool $exigirAntecedencia = true
): array {
    if (!in_array($status, ['PENDENTE', 'CONFIRMADO', 'RECUSADO', 'CANCELADO', 'CONCLUIDO'], true)) {
        return [false, 'status'];
    }
    // createFromFormat + round-trip rejeita datas inexistentes e horários normalizados silenciosamente.
    $formato = strlen($horaInicio) === 5 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
    $entrada = $data . ' ' . $horaInicio;
    $dataAgendamento = DateTime::createFromFormat('!' . $formato, $entrada);
    if (!$dataAgendamento || $dataAgendamento->format($formato) !== $entrada) {
        return [false, 'campos'];
    }
    if ($status === 'PENDENTE' && $data < (new DateTime('today'))->format('Y-m-d')) {
        return [false, 'data_passada'];
    }
    if ($status === 'CONFIRMADO' && $exigirAntecedencia && $data < (new DateTime('+7 days'))->format('Y-m-d')) {
        return [false, 'antecedencia'];
    }
    if (in_array($status, ['PENDENTE', 'CONFIRMADO'], true)
        && ($dataAgendamento->format('H:i:s') < '06:00:00' || $dataAgendamento->format('H:i:s') >= '20:00:00')) {
        return [false, 'horario_limite'];
    }
    $stmt = $pdo->prepare('SELECT id FROM fotografos WHERE id = :id');
    $stmt->execute([':id' => $fotografoId]);
    if (!$stmt->fetchColumn()) {
        return [false, 'fotografo'];
    }
    $stmt = $pdo->prepare('SELECT id, nicho_id, exige_cenario, duracao_minutos FROM tipos_servico WHERE id = :id');
    $stmt->execute([':id' => $tipoServicoId]);
    $servico = $stmt->fetch();
    if (!$servico) {
        return [false, 'servico'];
    }
    if ((int) $servico['exige_cenario'] === 1 && $cenarioId === null) {
        return [false, 'cenario_obrigatorio'];
    }
    if ($cenarioId !== null) {
        $stmt = $pdo->prepare('SELECT id, nicho_id, mes, ano FROM cenarios WHERE id = :id');
        $stmt->execute([':id' => $cenarioId]);
        $cenario = $stmt->fetch();
        if (!$cenario) {
            return [false, 'cenario'];
        }
        if ((int) $cenario['nicho_id'] !== (int) $servico['nicho_id']) {
            return [false, 'cenario_nicho'];
        }
        if ($cenario['mes'] !== null && (int) $cenario['mes'] !== (int) $dataAgendamento->format('n')) {
            return [false, 'cenario_mes'];
        }
        if ($cenario['ano'] !== null && (int) $cenario['ano'] !== (int) $dataAgendamento->format('Y')) {
            return [false, 'cenario_ano'];
        }
    }
    // Pendência valida os dados, mas não reserva horário.
    if ($status !== 'CONFIRMADO') {
        return [true, null];
    }
    $duracao = $duracaoMinutos ?? (int) $servico['duracao_minutos'];
    if ($duracao <= 0) {
        return [false, 'servico'];
    }
    $fim = clone $dataAgendamento;
    $fim->modify('+' . $duracao . ' minutes');
    $sql = "SELECT a.id FROM agendamentos a
        WHERE a.fotografo_id = :fotografo_id AND a.status = 'CONFIRMADO'
          AND :inicio_novo < DATE_ADD(TIMESTAMP(a.data, a.hora_inicio), INTERVAL a.duracao_minutos MINUTE)
          AND :fim_novo > TIMESTAMP(a.data, a.hora_inicio)";
    $params = [
        ':fotografo_id' => $fotografoId,
        ':inicio_novo' => $dataAgendamento->format('Y-m-d H:i:s'),
        ':fim_novo' => $fim->format('Y-m-d H:i:s'),
    ];
    if ($ignorarAgendamentoId !== null) {
        $sql .= ' AND a.id <> :id';
        $params[':id'] = $ignorarAgendamentoId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ? [false, 'conflito'] : [true, null];
}

function mensagemErroAgendamento(string $erro): string
{
    return match ($erro) {
        'campos' => 'Informe os campos obrigatórios com data e horário válidos.',
        'email' => 'Informe um e-mail válido de cliente.',
        'fotografo' => 'Fotógrafo inválido.',
        'data_passada' => 'Escolha uma data atual ou futura.',
        'horario_limite' => 'O horário inicial deve estar entre 06:00 e 20:00.',
        'banco' => 'Erro ao executar a ação no banco.',
        'status' => 'Status inválido.',
        'antecedencia' => 'Escolha uma data com pelo menos 7 dias de antecedência.',
        'servico' => 'Tipo de serviço inválido.',
        'cenario_obrigatorio' => 'Este serviço exige cenário.',
        'cenario' => 'Cenário inválido.',
        'cenario_nicho' => 'O cenário não pertence ao mesmo nicho do serviço.',
        'cenario_mes' => 'O cenário não está disponível para o mês escolhido.',
        'cenario_ano' => 'O cenário não está disponível para o ano escolhido.',
        'disponibilidade' => 'Horário indisponível.',
        'conflito' => 'Já existe um agendamento nesse período para o fotógrafo escolhido.',
        default => 'Ocorreu um erro. Verifique os dados informados.',
    };
}


function erroBancoParaCodigo(string $mensagem): string
{
    $mensagemNormalizada = mb_strtolower($mensagem);

    if (str_contains($mensagemNormalizada, 'horário inicial') || str_contains($mensagemNormalizada, 'horario inicial') || str_contains($mensagemNormalizada, 'período permitido') || str_contains($mensagemNormalizada, 'periodo permitido')) {
        return 'horario_limite';
    }

    if (str_contains($mensagemNormalizada, 'conflito')) {
        return 'conflito';
    }

    if (str_contains($mensagemNormalizada, 'disponibilidade')) {
        return 'disponibilidade';
    }

    if (str_contains($mensagemNormalizada, 'antecedência') || str_contains($mensagemNormalizada, 'antecedencia')) {
        return 'antecedencia';
    }

    if (str_contains($mensagemNormalizada, 'exige cenário') || str_contains($mensagemNormalizada, 'exige cenario')) {
        return 'cenario_obrigatorio';
    }

    if (str_contains($mensagemNormalizada, 'cenário indisponível') || str_contains($mensagemNormalizada, 'cenario indisponivel')) {
        return 'cenario_mes';
    }

    if (str_contains($mensagemNormalizada, 'cenário não pertence') || str_contains($mensagemNormalizada, 'cenario nao pertence')) {
        return 'cenario_nicho';
    }

    return 'banco';
}
