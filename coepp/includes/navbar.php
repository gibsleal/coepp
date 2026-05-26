<?php
// includes/navbar.php
// OBS: Navbar/Sidebar alternativa (caso não use o header.php completo).
// OBS: Requer init.php antes (ROOT, APP_URL, url()) e utiliza SVG inline para ícones.

$req = $_SERVER['REQUEST_URI'] ?? '/';

function nav_active($patterns, $req) {
  foreach ((array)$patterns as $p) {
    if (strpos($req, $p) !== false) return true; // OBS: match simples por substring
  }
  return false;
}

function ico($name){
  // OBS: Ícones inline para evitar dependências externas/cdn durante testes
  $svg = [
    'chevrons' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m7 8 5 4-5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'home' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'calendar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 2v4M16 2v4M3 10h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    'schedule' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'patient' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    'grad' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="m12 3 9 5-9 5-9-5 9-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M21 8v5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M3 13v3a9 9 0 0 0 18 0v-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    'help' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 2-3 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>',
    'logout' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M16 17l5-5-5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    'star' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="m12 3 3.09 6.26L22 10.27l-5 4.88L18.18 22 12 18.56 5.82 22 7 15.15l-5-4.88 6.91-1.01L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
    'report' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 7h8M8 11h8M8 15h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  ];
  return $svg[$name] ?? '';
}

function item($href, $label, $icon, $match, $req){
  // OBS: Componente de link com estado ativo
  $active = nav_active($match, $req) ? ' is-active' : '';
  $title  = htmlspecialchars($label);
  $href   = htmlspecialchars($href);
  echo <<<HTML
    <a class="sidebar__link{$active}" href="{$href}" title="{$title}">
      <span class="sidebar__indicator"></span>
      <span class="sidebar__icon">{$icon}</span>
      <span class="sidebar__text">{$title}</span>
    </a>
  HTML;
}
?>
<aside class="sidebar">
  <div style="display:flex; align-items:center; justify-content:flex-end; padding:4px 6px 10px;">
    <button id="sidebarToggle" type="button"
            style="display:inline-flex; align-items:center; gap:6px; height:34px; padding:0 10px; border:1px solid var(--border,#e5e7eb); background:#fff; border-radius:8px; cursor:pointer;">
      <?= ico('chevrons') ?>
      <span class="sidebar__text" style="font-size:14px;">Compactar</span>
    </button>
  </div>

  <nav class="sidebar__nav">
    <?php
      // ✅ Corrigido: não usar '/dashboard/' genérico
      // OBS: Os padrões de match devem ser específicos para evitar múltiplos itens ativos.
      item(url('dashboard/index.php'),                         'Página Inicial', ico('home'),     ['/dashboard/index.php','/dashboard/index'], $req);
      item(url('dashboard/agendamentos/agendamentos.php'),     'Agendamentos',   ico('schedule'), ['/dashboard/agendamentos/agendamentos.php'], $req);
      item(url('dashboard/calendario/calendario.php'),         'Calendário',     ico('calendar'), ['/dashboard/calendario'], $req);
    ?>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">CADASTROS</div>

  <nav class="sidebar__nav">
    <?php
      item(url('dashboard/pacientes/pacientes.php'),           'Pacientes',            ico('patient'), ['/dashboard/pacientes/pacientes.php'], $req);
      item(url('dashboard/pacientes/lista_prioridade.php'),    'Lista de Prioridade',  ico('star'),    ['/dashboard/pacientes/lista_prioridade.php'], $req);
      item(url('dashboard/estagiarios/estagiarios.php'),       'Estagiários',          ico('grad'),    ['/dashboard/estagiarios/estagiarios.php'], $req);
    ?>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">RELATÓRIOS</div>

  <nav class="sidebar__nav">
    <?php
      item(url('dashboard/agendamentos/atendidos.php'), 'Atendimentos (realizados)', ico('report'), ['/dashboard/agendamentos/atendidos.php'], $req);
    ?>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">OUTROS</div>

  <nav class="sidebar__nav">
    <?php
      item(url('dashboard/duvidas/duvidas.php'), 'Dúvidas', ico('help'),   ['/dashboard/duvidas/duvidas.php'], $req);
      item(url('auth/logout.php'),               'Sair',    ico('logout'), ['/auth/logout.php'],               $req);
    ?>
  </nav>
</aside>

<script>
  (function () {
    // OBS: Estado "compacto" persistido no localStorage (UX: lembra preferência do usuário)
    const key = 'sidebar_compact';
    const body = document.body;
    const btn  = document.getElementById('sidebarToggle');

    if (localStorage.getItem(key) === '1') {
      body.classList.add('sidebar-compact');
      if (btn) btn.querySelector('.sidebar__text')?.replaceChildren(document.createTextNode('Expandir'));
    }

    btn?.addEventListener('click', () => {
      const compact = body.classList.toggle('sidebar-compact');
      localStorage.setItem(key, compact ? '1' : '0');
      btn.querySelector('.sidebar__text')?.replaceChildren(document.createTextNode(compact ? 'Expandir' : 'Compactar'));
    });
  })();
</script>