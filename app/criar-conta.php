<?php require_once __DIR__ . '/src/bootstrap.php';
$oldRegistration=$_SESSION['registration_old']??[]; unset($_SESSION['registration_old']); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Criar Conta - Lume Estúdio Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="auth-page">
  <main class="auth">
    <section class="auth__panel">
      <a class="auth__brand" href="index.php">Lume Estúdio</a>
      <span class="label">Acesso administrativo</span>
      <h1 class="auth__title">Solicitar acesso.</h1>
      <p class="auth__text">Preencha seus dados para solicitar acesso ao painel administrativo. A Danny precisa aprovar antes do primeiro login.</p>

      <?php if (isset($_GET['erro'])): ?>
        <p class="auth-alert auth-alert--error">
          <?php
            echo match ($_GET['erro']) {
              'campos' => 'Preencha todos os campos obrigatorios.',
              'email' => 'Informe um e-mail valido.',
              'senha_curta' => 'Use uma senha com pelo menos 12 caracteres.',
              'senha' => 'As senhas nao conferem.',
              'existe' => 'Ja existe uma conta com esse e-mail.',
              'banco' => 'Nao foi possivel criar a solicitacao agora.',
              default => 'Ocorreu um erro. Tente novamente.',
            };
          ?>
        </p>
      <?php endif; ?>

      <form class="auth-form" action="actions/criar-conta.php" method="post">
        <?= csrf_field() ?>
        <label class="auth-form__group">
          <span>Nome</span>
          <input class="form-field" type="text" name="nome" value="<?= htmlspecialchars((string)($oldRegistration['nome']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="Seu nome" autocomplete="name" required />
        </label>
        <label class="auth-form__group">
          <span>E-mail</span>
          <input class="form-field" type="email" name="email" value="<?= htmlspecialchars((string)($oldRegistration['email']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="seu@email.com" autocomplete="email" required />
        </label>

        <label class="auth-form__group">
          <span>Telefone</span>
          <input class="form-field" type="text" name="telefone" value="<?= htmlspecialchars((string)($oldRegistration['telefone']??''),ENT_QUOTES,'UTF-8') ?>" placeholder="(19) 99999-9999" autocomplete="tel" />
        </label>
        <label class="auth-form__group">
          <span>Senha</span>
          <input class="form-field" type="password" name="senha" placeholder="Crie uma senha" autocomplete="new-password" minlength="12" required />
        </label>
        <label class="auth-form__group">
          <span>Confirmar senha</span>
          <input class="form-field" type="password" name="confirmar_senha" placeholder="Repita a senha" autocomplete="new-password" minlength="12" required />
        </label>
        <button class="btn btn--submit" type="submit">Solicitar acesso</button>
      </form>

      <p class="auth__switch">Ja tem conta aprovada? <a href="login.php">Entrar</a></p>
    </section>
  </main>
</body>
</html>
