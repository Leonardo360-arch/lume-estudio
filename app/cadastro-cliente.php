<?php
require_once __DIR__ . '/src/bootstrap.php';
$oldRegistration=$_SESSION['registration_old']??[]; unset($_SESSION['registration_old']);

use Danny\Database;

function e(mixed $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$erroBanco = null;
$nichos = [];
$tiposServico = [];

try {
  $pdo = Database::connection();

  $nichos = $pdo->query("
    SELECT id, nome
    FROM nichos
    ORDER BY nome
  ")->fetchAll();

  $tiposServico = $pdo->query("
    SELECT id, nome, nicho_id, duracao_minutos, exige_cenario
    FROM tipos_servico
    ORDER BY nome
  ")->fetchAll();
} catch (PDOException $e) {
  error_log('Database: '.$e->getMessage());
  $erroBanco = 'Não foi possível carregar os dados. Tente novamente em instantes.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cadastro de Cliente — Lume Estúdio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="auth-page">
  <main class="auth">
    <section class="auth__panel">
      <a class="auth__brand" href="index.php">Lume Estúdio</a>
      <span class="label">Cadastro de cliente</span>
      <h1 class="auth__title">Comece seu atendimento.</h1>
      <p class="auth__text">Conte um pouco sobre você e seu interesse. Depois, o administrador revisa o pedido e confirma a data do ensaio.</p>

      <?php if ($erroBanco): ?>
        <p style="color: red; font-weight: bold;">Erro ao carregar opções do banco.</p>
      <?php endif; ?>

      <?php if (isset($_GET['erro'])): ?>
        <p style="color: red; font-weight: bold;">
          <?php
            echo match ($_GET['erro']) {
              'campos' => 'Preencha todos os campos obrigatórios.',
              'email' => 'Informe um e-mail válido.',
              'senha_curta' => 'Use uma senha com pelo menos 12 caracteres.',
              'senha' => 'As senhas não conferem.',
              'existe' => 'Já existe um cadastro com esse e-mail.',
              'banco' => 'Não foi possível criar o cadastro. Tente novamente.',
              default => 'Ocorreu um erro. Verifique os dados informados.',
            };
          ?>
        </p>
      <?php endif; ?>

      <form class="auth-form" action="actions/cadastrar-cliente.php" method="post">
        <?= csrf_field() ?>
        <label class="auth-form__group">
          <span>Nome completo</span>
          <input class="form-field" type="text" name="nome" value="<?= htmlspecialchars((string)($oldRegistration['nome']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="Seu nome" autocomplete="name" required />
        </label>

        <label class="auth-form__group">
          <span>E-mail</span>
          <input class="form-field" type="email" name="email" value="<?= htmlspecialchars((string)($oldRegistration['email']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="seu@email.com" autocomplete="email" required />
        </label>

        <label class="auth-form__group">
          <span>Telefone / WhatsApp</span>
          <input class="form-field" type="text" name="telefone" value="<?= htmlspecialchars((string)($oldRegistration['telefone']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="(19) 99999-9999" autocomplete="tel" required />
        </label>

        <label class="auth-form__group">
          <span>O que você quer registrar?</span>
          <select class="form-field" name="nicho_id" id="nicho_id" required>
            <option value="">Escolha um nicho</option>
            <?php foreach ($nichos as $nicho): ?>
              <option value="<?= e($nicho['id']) ?>" <?= (int)($oldRegistration['nicho_id']??0)===(int)$nicho['id']?'selected':'' ?>><?= e($nicho['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="auth-form__group">
          <span>Primeiro serviço desejado</span>
          <select class="form-field" name="tipo_servico_id" id="tipo_servico_id">
            <option value="">Escolha um serviço</option>
            <?php foreach ($tiposServico as $servico): ?>
              <option value="<?= e($servico['id']) ?>" <?= (int)($oldRegistration['tipo_servico_id']??0)===(int)$servico['id']?'selected':'' ?> data-nicho="<?= e($servico['nicho_id']) ?>">
                <?= e($servico['nome']) ?> — <?= e($servico['duracao_minutos']) ?> min
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="auth-form__group">
          <span>Senha</span>
          <input class="form-field" type="password" name="senha" placeholder="Crie uma senha" autocomplete="new-password" minlength="12" required />
        </label>

        <label class="auth-form__group">
          <span>Confirmar senha</span>
          <input class="form-field" type="password" name="confirmar_senha" placeholder="Repita a senha" autocomplete="new-password" minlength="12" required />
        </label>

        <button class="btn btn--submit" type="submit">Criar cadastro</button>
      </form>

      <p class="auth__switch">Já tem cadastro? <a href="login-cliente.php">Entrar como cliente</a></p>
      <p class="auth__switch"><a href="login.php">Entrar como administrador</a></p>
    </section>
  </main>

  <script>
    const nichoSelect = document.getElementById('nicho_id');
    const servicoSelect = document.getElementById('tipo_servico_id');
    const servicoOptions = Array.from(servicoSelect.options);

    function filtrarServicos() {
      const nichoId = nichoSelect.value;

      servicoOptions.forEach((option) => {
        if (!option.value) {
          option.hidden = false;
          return;
        }

        option.hidden = nichoId && option.dataset.nicho !== nichoId;
      });

      const selected = servicoSelect.options[servicoSelect.selectedIndex];
      if (selected && selected.hidden) {
        servicoSelect.value = '';
      }
    }

    nichoSelect.addEventListener('change', filtrarServicos);
    filtrarServicos();
  </script>
</body>
</html>
