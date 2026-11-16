<?php $homePrefix = !empty($publicHome) ? '' : 'index.php'; ?>
<header class="site-header">
  <nav class="nav" id="siteNav" aria-label="Navegação principal">
    <a class="nav__logo" href="index.php" aria-label="Lume Estúdio — página inicial"><img class="brand-mark" src="assets/brand/lume-logo.png" alt="" width="44" height="44" /><span class="brand-wordmark">LUME<span>ESTÚDIO</span></span></a>
    <button class="nav__toggle" type="button" aria-label="Abrir menu" aria-controls="siteNavLinks" aria-expanded="false" hidden><span aria-hidden="true">☰</span><span>Menu</span></button>
    <ul class="nav__links" id="siteNavLinks">
      <li><a href="<?= $homePrefix ?>#sobre">O estúdio</a></li>
      <li><a href="<?= $homePrefix ?>#ensaios">Ensaios</a></li>
      <li><a href="<?= $homePrefix ?>#portfolio">Portfólio</a></li>
      <li><a href="albums.php">Meus álbuns</a></li>
      <li><a class="nav__cta" href="login-cliente.php">Área do cliente <span aria-hidden="true">↗</span></a></li>
    </ul>
  </nav>
</header>
<script>
(() => {
  const nav = document.getElementById('siteNav');
  const toggle = nav?.querySelector('.nav__toggle');
  const links = document.getElementById('siteNavLinks');
  if (!nav || !toggle || !links) return;
  nav.classList.add('nav--enhanced');
  toggle.hidden = false;
  function closeMenu(restoreFocus = false) {
    nav.classList.remove('nav--open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir menu');
    if (restoreFocus) toggle.focus();
  }
  toggle.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('nav--open');
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
  });
  nav.addEventListener('keydown', event => {
    if (event.key === 'Escape' && nav.classList.contains('nav--open')) closeMenu(true);
  });
  links.addEventListener('click', event => {
    if (event.target.closest('a')) closeMenu();
  });
  document.addEventListener('click', event => {
    if (!nav.contains(event.target)) closeMenu();
  });
  window.matchMedia('(min-width: 961px)').addEventListener('change', () => closeMenu());
})();
</script>
