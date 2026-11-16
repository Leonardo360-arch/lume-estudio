<aside class="admin-sidebar" aria-label="Menu administrativo">
  <a class="admin-sidebar__brand" href="admin.php">
    <span class="admin-sidebar__logo">Lume Estúdio</span>
    <span class="admin-sidebar__eyebrow">Admin</span>
  </a>

  <nav class="admin-sidebar__nav">
    <a class="admin-sidebar__link admin-sidebar__link--active" href="admin.php#agenda">Agenda</a>
    <a class="admin-sidebar__link" href="admin.php#novo-agendamento">Cadastrar atendimento</a>
    <a class="admin-sidebar__link" href="album-admin.php">Entregas de fotos</a>
    <a class="admin-sidebar__link" href="historico.php">Historico</a>
    <a class="admin-sidebar__link" href="servicos-cadastrados.php">Servicos cadastrados</a>
    <?php if (can_manage_admin_access()): ?>
      <a class="admin-sidebar__link" href="admin.php#acessos-admin">Acessos admin</a>
    <?php endif; ?>
  </nav>

  <div class="admin-sidebar__footer">
    <span class="admin-sidebar__user">Lume Estúdio</span>
    <form class="logout-form" action="logout.php" method="post"><?= csrf_field() ?><button type="submit" class="admin-sidebar__logout" >Sair</button></form>
  </div>
</aside>
