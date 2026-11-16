<?php
/** Integration checks against an explicitly isolated, already migrated lume_test_* database.
 * TEST_DB_DSN='mysql:host=127.0.0.1;dbname=lume_test_backend' TEST_DB_USER=... TEST_DB_PASSWORD=... php tests/backend_integration.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/AgendamentoHelper.php';

use function Danny\bloquearAgenda;
use function Danny\carregarAgendamentoParaAlteracao;
use function Danny\buscarOuCriarCliente;
use function Danny\duracaoContratada;
use function Danny\exigeAntecedencia;
use function Danny\validarAgendamento;

date_default_timezone_set('America/Sao_Paulo');
$dsn = getenv('TEST_DB_DSN') ?: '';
if (!preg_match('/(?:^|;)dbname=lume_test_[a-z0-9_]+(?:;|$)/i', $dsn)) {
    fwrite(STDERR, "Use TEST_DB_DSN with an isolated lume_test_* database, migrated before running.\n");
    exit(2);
}
function connection(): PDO
{
    $pdo = new PDO(getenv('TEST_DB_DSN'), getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function fixtures(PDO $pdo): array
{
    $key = bin2hex(random_bytes(8));
    $pdo->prepare('INSERT INTO nichos (nome, slug) VALUES (?, ?)')->execute(['Teste ' . $key, 'teste-' . $key]);
    $nicho = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tipos_servico (nome, nicho_id, exige_cenario, duracao_minutos) VALUES (?, ?, 0, 60)')->execute(['Teste ' . $key, $nicho]);
    $servico = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO fotografos (nome) VALUES (?)')->execute(['Teste ' . $key]);
    $fotografo = (int) $pdo->lastInsertId();
    $cliente = buscarOuCriarCliente($pdo, 'Pessoa de teste', $servico, null, 'teste-' . $key . '@example.test');
    return compact('nicho', 'servico', 'fotografo', 'cliente');
}
function booking(PDO $pdo, array $f, string $data, string $hora = '10:00', string $status = 'PENDENTE', int $duracao = 60): int
{
    $stmt = $pdo->prepare('INSERT INTO agendamentos (admin_id, fotografo_id, tipo_servico_id, data, hora_inicio, duracao_minutos, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$f['cliente'], $f['fotografo'], $f['servico'], $data, $hora, $duracao, $status]);
    return (int) $pdo->lastInsertId();
}
function loadBooking(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    return $stmt->fetch();
}
function validate(PDO $pdo, array $f, string $data, string $hora = '10:00', ?int $id = null, int $duration = 60, bool $antecedencia = true): array
{
    return validarAgendamento($pdo, $f['fotografo'], $f['servico'], null, $data, $hora, 'CONFIRMADO', $id, $duration, $antecedencia);
}
if (($argv[1] ?? '') === '--worker') {
    $pdo = connection();
    try {
        $a = carregarAgendamentoParaAlteracao($pdo, (int) $argv[2]);
        check($a !== null, 'Agendamento de teste inexistente.');
        if (($argv[3] ?? '') === 'hold') {
            echo "LOCKED\n";
            flush();
            usleep(500000);
        }
        [$valid, $reason] = validarAgendamento($pdo, (int) $a['fotografo_id'], (int) $a['tipo_servico_id'], null, $a['data'], $a['hora_inicio'], 'CONFIRMADO', (int) $a['id'], (int) $a['duracao_minutos']);
        if ($valid) {
            $pdo->prepare("UPDATE agendamentos SET status = 'CONFIRMADO' WHERE id = ?")->execute([$a['id']]);
            $pdo->commit();
            echo "CONFIRMADO\n";
        } else {
            $pdo->rollBack();
            echo $reason . "\n";
        }
        exit(0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
$pdo = connection();
$tests = [];
$tests['homônimos e reutilização por e-mail sem sobrescrever perfil'] = function () use ($pdo): void {
    $f = fixtures($pdo);
    $a = buscarOuCriarCliente($pdo, 'Mesmo Nome', $f['servico'], '11911112222', ' a-' . $f['cliente'] . '@example.test ');
    $b = buscarOuCriarCliente($pdo, 'Mesmo Nome', $f['servico'], '11933334444', 'b-' . $f['cliente'] . '@example.test');
    check($a !== $b, 'Homônimos foram unidos.');
    $same = buscarOuCriarCliente($pdo, 'Outro Nome', $f['servico'], '11999999999', 'A-' . $f['cliente'] . '@EXAMPLE.TEST');
    check($same === $a, 'Normalização do e-mail falhou.');
    $stmt = $pdo->prepare('SELECT nome, telefone FROM admins WHERE id = ?'); $stmt->execute([$a]);
    $account = $stmt->fetch();
    check($account['nome'] === 'Mesmo Nome' && $account['telefone'] === '11911112222', 'Agendamento alterou o perfil existente.');
    check(buscarOuCriarCliente($pdo, 'Sem Email', $f['servico']) !== buscarOuCriarCliente($pdo, 'Sem Email', $f['servico']), 'Clientes sem e-mail foram unidos por nome.');
};
$tests['pendentes não reservam; confirmado impede sobreposição'] = function () use ($pdo): void {
    $f = fixtures($pdo); $date = date('Y-m-d', strtotime('+14 days'));
    $a = booking($pdo, $f, $date); booking($pdo, $f, $date);
    bloquearAgenda($pdo, [$f['fotografo']]);
    check(validate($pdo, $f, $date, '10:00', $a)[0], 'Outro pendente bloqueou a aprovação.');
    $pdo->prepare("UPDATE agendamentos SET status='CONFIRMADO' WHERE id=?")->execute([$a]);
    check(validate($pdo, $f, $date)[1] === 'conflito', 'Confirmado não bloqueou sobreposição.');
    check(validate($pdo, $f, $date, '11:00')[0], 'Reserva consecutiva sem sobreposição foi bloqueada.');
};
$tests['edição perto da data não reaplica antecedência; remarcação reaplica'] = function () use ($pdo): void {
    $f = fixtures($pdo); $date = date('Y-m-d', strtotime('+14 days'));
    $id = booking($pdo, $f, $date, '10:00', 'CONFIRMADO');
    $a = loadBooking($pdo, $id);
    check(!exigeAntecedencia($a, $f['fotografo'], $f['servico'], $date, '10:00'), 'Manutenção reaplicou antecedência.');
    check(exigeAntecedencia($a, $f['fotografo'], $f['servico'], $date, '11:00'), 'Remarcação dispensou antecedência.');
    $pdo->exec('SET timestamp = ' . strtotime('+13 days'));
    try {
        $pdo->prepare('UPDATE agendamentos SET observacao=?, valor=? WHERE id=?')->execute(['Observação corrigida', '50.00', $id]);
        $blocked = false;
        try { $pdo->prepare("UPDATE agendamentos SET hora_inicio='11:00' WHERE id=?")->execute([$id]); }
        catch (PDOException $e) { $blocked = str_contains($e->getMessage(), 'antecedência'); }
        check($blocked, 'Trigger aceitou remarcação sem antecedência.');
    } finally { $pdo->exec('SET timestamp = DEFAULT'); }
};
$tests['duração contratada estável e conflito que atravessa meia-noite'] = function () use ($pdo): void {
    $f = fixtures($pdo); $date = date('Y-m-d', strtotime('+14 days'));
    $id = booking($pdo, $f, $date, '19:00', 'CONFIRMADO', 720);
    $pdo->prepare('UPDATE tipos_servico SET duracao_minutos=120 WHERE id=?')->execute([$f['servico']]);
    $a = loadBooking($pdo, $id);
    check(duracaoContratada($pdo, $f['servico'], $a) === 720, 'Alteração no catálogo mudou a duração contratada.');
    check(duracaoContratada($pdo, $f['servico']) === 120, 'Novo agendamento ignorou duração atual do catálogo.');
    check(validate($pdo, $f, date('Y-m-d', strtotime($date . ' +1 day')), '06:00')[1] === 'conflito', 'Conflito no dia seguinte não detectado.');
};
$tests['datas inexistentes e pendentes inválidos são rejeitados'] = function () use ($pdo): void {
    $f = fixtures($pdo);
    check(validarAgendamento($pdo, $f['fotografo'], $f['servico'], null, '2030-02-31', '10:00', 'PENDENTE')[1] === 'campos', 'Data inexistente foi normalizada.');
    check(validarAgendamento($pdo, $f['fotografo'], $f['servico'], null, date('Y-m-d', strtotime('+14 days')), '25:00', 'PENDENTE')[1] === 'campos', 'Horário inválido foi aceito.');
};
foreach ($tests as $name => $test) {
    $pdo->beginTransaction();
    try { $test(); echo "PASS $name\n"; }
    finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
}
// Duas conexões/processos reais: o segundo espera o lock e enxerga a confirmação do primeiro.
$f = null;
try {
    $pdo->beginTransaction(); $f = fixtures($pdo); $date = date('Y-m-d', strtotime('+14 days'));
    $a = booking($pdo, $f, $date); $b = booking($pdo, $f, $date); $pdo->commit();
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $first = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $a, 'hold'], $spec, $pipes1);
    check(is_resource($first), 'Não foi possível iniciar primeiro worker.');
    fclose($pipes1[0]);
    check(trim(fgets($pipes1[1])) === 'LOCKED', 'Primeiro worker não adquiriu o lock.');
    $started = microtime(true);
    $second = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $b], $spec, $pipes2);
    check(is_resource($second), 'Não foi possível iniciar segundo worker.');
    fclose($pipes2[0]);
    $result1 = trim(stream_get_contents($pipes1[1])); $error1 = stream_get_contents($pipes1[2]);
    $result2 = trim(stream_get_contents($pipes2[1])); $error2 = stream_get_contents($pipes2[2]);
    foreach ([$pipes1[1], $pipes1[2], $pipes2[1], $pipes2[2]] as $pipe) fclose($pipe);
    $exit1 = proc_close($first); $exit2 = proc_close($second);
    check($exit1 === 0 && $exit2 === 0, 'Workers falharam: ' . $error1 . $error2);
    check($result1 === 'CONFIRMADO' && $result2 === 'conflito', 'Aprovações simultâneas não produziram um vencedor: ' . $result1 . '/' . $result2);
    check(microtime(true) - $started >= 0.2, 'Segundo worker não aguardou o bloqueio.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE fotografo_id=? AND status='CONFIRMADO'"); $stmt->execute([$f['fotografo']]);
    check((int) $stmt->fetchColumn() === 1, 'Mais de uma confirmação para o mesmo intervalo.');
    echo "PASS duas confirmações simultâneas: uma vence e outra recebe conflito\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($f !== null) {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM agendamentos WHERE fotografo_id=?')->execute([$f['fotografo']]);
        $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$f['cliente']]);
        $pdo->prepare('DELETE FROM tipos_servico WHERE id=?')->execute([$f['servico']]);
        $pdo->prepare('DELETE FROM fotografos WHERE id=?')->execute([$f['fotografo']]);
        $pdo->prepare('DELETE FROM nichos WHERE id=?')->execute([$f['nicho']]);
        $pdo->commit();
    }
}
echo "6 backend integration checks passed.\n";
