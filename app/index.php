<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$contactStatus = (string) ($_GET['contato'] ?? '');
$publicHome = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Conheça o Lume Estúdio: um projeto demonstrativo de fotografia afetiva, agendamento de ensaios e álbuns de clientes." />
  <meta name="theme-color" content="#f7f4ee" />
  <title>Lume Estúdio — histórias que ficam</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/public.css" />
</head>
<body class="lume-public">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <?php include __DIR__ . '/includes/site-header.php'; ?>

  <main id="conteudo">
    <section class="lume-hero page-width" id="inicio" aria-labelledby="hero-title">
      <div class="hero-copy">
        <p class="eyebrow"><span class="little-sun" aria-hidden="true">✳</span> Fotografia com presença</p>
        <h1 id="hero-title">O tempo passa.<br />O afeto <em>fica.</em></h1>
        <p class="hero-description">Um olhar sensível para as histórias que você vive. Ensaios de família, gestante e retratos, com espaço para ser quem você é.</p>
        <div class="hero-actions">
          <a class="lume-button lume-button--filled" href="login-cliente.php">Solicitar meu ensaio <span aria-hidden="true">↗</span></a>
          <a class="text-link" href="#portfolio">Conheça nosso olhar <span aria-hidden="true">↓</span></a>
        </div>
        <div class="hero-footnote"><span aria-hidden="true">✧</span><p>Menos poses. Mais histórias de verdade.</p></div>
      </div>
      <figure class="hero-photo">
        <img src="assets/demo/hero.webp" width="1536" height="1024" fetchpriority="high" alt="Cena de afeto em família, fotografada em luz natural; imagem fictícia de demonstração" />
        <figcaption><span>Luz natural. Memórias inteiras.</span><span aria-hidden="true">01 / LUME</span></figcaption>
        <span class="photo-tag" aria-hidden="true">feito de<br /><em>sentir.</em></span>
      </figure>
    </section>

    <div class="editorial-strip" aria-hidden="true"><div class="page-width"><span>O cotidiano tem poesia.</span><span>✳</span><span>A gente ajuda a guardar.</span><span>✳</span><span>Do seu jeito. No seu tempo.</span></div></div>

    <section class="lume-section page-width" id="ensaios" aria-labelledby="ensaios-title">
      <div class="section-heading"><div><p class="eyebrow">Para cada capítulo</p><h2 id="ensaios-title">Qual história vamos <em>contar?</em></h2></div><p>Escolha o momento. A proposta do ensaio começa com uma conversa e ganha forma com você.</p></div>
      <div class="service-grid">
        <article class="service-card">
          <a class="service-photo" href="login-cliente.php"><img src="assets/demo/family.webp" width="1200" height="800" loading="lazy" decoding="async" alt="Família compartilhando um momento de carinho; fotografia fictícia" /><span class="image-arrow" aria-hidden="true">↗</span><span class="sr-only">Solicitar ensaio de família</span></a>
          <div class="service-caption"><span class="service-number" aria-hidden="true">01</span><div><h3>Família & infância</h3><p>Abraços, descobertas e a beleza de crescer junto.</p></div></div>
        </article>
        <article class="service-card">
          <a class="service-photo" href="login-cliente.php"><img src="assets/demo/maternity.webp" width="960" height="1200" loading="lazy" decoding="async" alt="Retrato de gestante em um ambiente de luz suave; fotografia fictícia" /><span class="image-arrow" aria-hidden="true">↗</span><span class="sr-only">Solicitar ensaio de gestante</span></a>
          <div class="service-caption"><span class="service-number" aria-hidden="true">02</span><div><h3>À espera de um amor</h3><p>Um registro delicado de uma nova fase da vida.</p></div></div>
        </article>
        <article class="service-card">
          <a class="service-photo" href="login-cliente.php"><img src="assets/demo/studio.webp" width="1200" height="800" loading="lazy" decoding="async" alt="Interior de estúdio fotográfico com luz natural e tons claros; imagem fictícia" /><span class="image-arrow" aria-hidden="true">↗</span><span class="sr-only">Solicitar ensaio no estúdio</span></a>
          <div class="service-caption"><span class="service-number" aria-hidden="true">03</span><div><h3>Encontros no estúdio</h3><p>Um espaço tranquilo para deixar a história acontecer.</p></div></div>
        </article>
      </div>
    </section>

    <section class="about-section" id="sobre" aria-labelledby="sobre-title">
      <div class="about-layout page-width"><div class="about-symbol" aria-hidden="true">✳<span>Luz que acolhe.</span></div><div><p class="eyebrow">Nosso jeito de olhar</p><h2 id="sobre-title">Antes da fotografia,<br />vem a <em>conexão.</em></h2></div><div class="about-copy"><p>Chamamos de Lume aquilo que ilumina de dentro para fora. É essa a ideia que guia este estúdio: fotografar o encontro, os pequenos gestos e o que faz cada história ser única.</p><p>Uma experiência pensada do primeiro contato ao álbum, com cuidado, clareza e liberdade para viver o momento.</p><a class="text-link" href="#como-funciona">Veja como funciona <span aria-hidden="true">↗</span></a></div></div>
    </section>

    <section class="lume-section page-width" id="portfolio" aria-labelledby="portfolio-title">
      <div class="section-heading"><div><p class="eyebrow">Um olhar, muitas histórias</p><h2 id="portfolio-title">Pequenos instantes.<br /><em>Grandes lembranças.</em></h2></div><a class="text-link" href="albums.php">Acessar meus álbuns <span aria-hidden="true">↗</span></a></div>
      <div class="portfolio-grid">
        <figure class="portfolio-image portfolio-image--wide"><img src="assets/demo/hero.webp" width="1536" height="1024" loading="lazy" decoding="async" alt="Fotografia demonstrativa de uma família em luz natural" /><figcaption>Afeto em cada encontro</figcaption></figure>
        <figure class="portfolio-image"><img src="assets/demo/maternity.webp" width="960" height="1200" loading="lazy" decoding="async" alt="Fotografia demonstrativa de um ensaio de gestante" /><figcaption>O começo de uma história</figcaption></figure>
      </div>
      <p class="demo-caption">Estúdio e imagens fictícios para demonstração.</p>
    </section>

    <section class="process-section page-width" id="como-funciona" aria-labelledby="process-title">
      <div class="section-heading"><div><p class="eyebrow">Do primeiro olá ao álbum</p><h2 id="process-title">Leve, do início <em>ao fim.</em></h2></div><p>Você acompanha cada etapa pela sua área de cliente.</p></div>
      <ol class="process-grid">
        <li><span class="process-number" aria-hidden="true">01 /</span><h3>Vamos conversar</h3><p>Crie sua conta, escolha o tipo de ensaio e envie sua solicitação de data e horário.</p></li>
        <li><span class="process-number" aria-hidden="true">02 /</span><h3>Planejamos juntos</h3><p>Acompanhe a análise do pedido na sua área. O ensaio fica agendado após a confirmação.</p></li>
        <li><span class="process-number" aria-hidden="true">03 /</span><h3>Guarde sua história</h3><p>Depois do ensaio, acesse os álbuns disponibilizados para você na área de cliente.</p></li>
      </ol>
    </section>

    <section class="contact-section" id="contato" aria-labelledby="contact-title">
      <div class="contact-layout page-width">
        <div class="contact-copy"><p class="eyebrow">Um novo capítulo começa aqui</p><h2 id="contact-title">Sua história merece<br />um pouco de <em>lume.</em></h2><p>Conte qual momento você quer registrar. Para solicitar uma data e acompanhar seu pedido, entre na área do cliente.</p><a class="lume-button lume-button--filled" href="login-cliente.php">Solicitar um ensaio <span aria-hidden="true">↗</span></a><p class="contact-demo">Este é um ambiente de demonstração. Use dados fictícios; nenhuma contratação é realizada por aqui.</p></div>
        <form class="contact-form" action="actions/contact.php" method="post" id="form-contato" aria-labelledby="form-title">
          <h3 id="form-title">Vamos conversar?</h3>
          <?= csrf_field() ?>
          <?php if ($contactStatus === 'sucesso'): ?>
            <p class="form-feedback form-feedback--success" role="status">Mensagem registrada no ambiente de demonstração.</p>
          <?php elseif ($contactStatus === 'erro'): ?>
            <p class="form-feedback form-feedback--error" role="alert">Não foi possível enviar a mensagem. Confira os campos e tente novamente.</p>
          <?php endif; ?>
          <div class="contact-fields"><div class="field"><label for="contact-name">Seu nome</label><input id="contact-name" name="nome" type="text" autocomplete="name" maxlength="120" placeholder="Como podemos chamar você?" required /></div><div class="field"><label for="contact-email">E-mail</label><input id="contact-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="voce@exemplo.com" required /></div><div class="field field--full"><label for="contact-phone">Telefone com DDD</label><input id="contact-phone" name="telefone" type="tel" autocomplete="tel" maxlength="24" placeholder="(00) 00000-0000" required /></div><div class="field field--full"><label for="contact-message">Qual história você quer registrar?</label><textarea id="contact-message" name="mensagem" rows="4" maxlength="3000" placeholder="Conte um pouco sobre o ensaio que você imagina…" required></textarea></div></div>
          <button class="lume-button lume-button--filled contact-submit" type="submit">Enviar mensagem <span aria-hidden="true">↗</span></button>
          <p class="form-note">Mensagem de demonstração. Não informe dados pessoais reais.</p>
        </form>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
