<?php
// includes/header.php
// OBS: Define APP_URL (auto-descoberta) e helpers para navegação ativa.
// OBS: Este arquivo abre <body>, <header>, <aside> e <main>. O footer fecha.

if (!defined('APP_URL')) {
  // OBS: Descobre o esquema/host com fallback seguro
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = '/' . basename(realpath(__DIR__ . '/..'));
  define('APP_URL', $scheme . '://' . $host . $base);
}

if (!function_exists('is_active')) {
  // OBS: Marca item ativo comparando REQUEST_URI com prefixos de rota
  function is_active($paths) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    foreach ((array)$paths as $p) {
      if ($p && strpos($uri, $p) === 0) return ' is-active';
    }
    return '';
  }
}

/* URLs */
// OBS: Centraliza as URLs usadas na sidebar/topbar
$URL_DASHBOARD     = APP_URL . '/dashboard/index.php';
$URL_AGENDAMENTOS  = APP_URL . '/dashboard/agendamentos/agendamentos.php';
$URL_ATENDIDOS     = APP_URL . '/dashboard/agendamentos/atendidos.php';         // NOVO
$URL_CALENDARIO    = APP_URL . '/dashboard/calendario/calendario.php';
$URL_PACIENTES     = APP_URL . '/dashboard/pacientes/pacientes.php';
$URL_PAC_PREFS     = APP_URL . '/dashboard/pacientes/preferenciais.php';        // NOVO
$URL_ESTAGIARIOS   = APP_URL . '/dashboard/estagiarios/estagiarios.php';
$URL_DUVIDAS       = APP_URL . '/dashboard/duvidas/duvidas.php';
$URL_LOGO          = APP_URL . '/assets/img/logo-fsa.png';
$URL_CSS           = APP_URL . '/assets/css/app.css';
$URL_LOGOUT        = APP_URL . '/auth/logout.php';

/* Caminhos p/ ativo */
// OBS: Usados por is_active() para destacar o item de menu atual
$baseReq           = '/' . basename(dirname(__DIR__));
$REQ_DASHBOARD     = $baseReq . '/dashboard/index.php';
$REQ_AGENDAMENTOS  = $baseReq . '/dashboard/agendamentos/agendamentos.php';
$REQ_ATENDIDOS     = $baseReq . '/dashboard/agendamentos/atendidos.php';
$REQ_CALENDARIO    = $baseReq . '/dashboard/calendario/calendario.php';
$REQ_PACIENTES     = $baseReq . '/dashboard/pacientes/pacientes.php';
$REQ_PAC_PREFS     = $baseReq . '/dashboard/pacientes/preferenciais.php';
$REQ_ESTAGIARIOS   = $baseReq . '/dashboard/estagiarios/estagiarios.php';
$REQ_DUVIDAS       = $baseReq . '/dashboard/duvidas/duvidas.php';

/* E-mail do usuário logado (fallbacks comuns) */
// OBS: Vários formatos possíveis de sessão; unifica para exibição
@session_start();
$USER_EMAIL = $_SESSION['user_email']
  ?? ($_SESSION['user']['email'] ?? ($_SESSION['email'] ?? 'usuario@coepp'));
$USER_EMAIL = htmlspecialchars($USER_EMAIL);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>COEPP</title>

  <!-- OBS: Font Awesome e CSS do app (cache-bust com ?v=time()) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($URL_CSS) ?>?v=<?= time() ?>">

  <style>
    /* OBS: Estilos base do layout fixo (topbar + sidebar + content) */
    .topbar{position:fixed;top:0;left:0;right:0;height:64px;background:#fff;border-bottom:1px solid #e5e7eb;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:0 16px;z-index:1000}
    .topbar__left{display:flex;align-items:center;gap:10px}
    .topbar__logo{height:36px}
    .topbar__center{text-align:center}
    .topbar__title{margin:0;font-weight:800;letter-spacing:.4px;color:#0d3b7a}
    .topbar__right{display:flex;align-items:center;gap:12px;justify-content:flex-end}
    .user-menu{position:relative}
    .user-icon{font-size:24px;color:#334155;cursor:pointer}
    .user-dropdown{position:absolute;right:0;top:calc(100% + 8px);min-width:220px;background:#e9f0f7;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.08);display:none;overflow:hidden}
    .user-menu:hover .user-dropdown{display:block}
    .user-email{padding:12px 14px;color:#111827;font-size:14px;line-height:1.4;white-space:nowrap}
    .user-email i{margin-right:8px;color:#0a4ea1}

    .sidebar{position:fixed;top:64px;left:0;bottom:0;width:240px;background:#fff;border-right:1px solid #e5e7eb;padding:14px 10px;overflow:auto;z-index:900}
    .sidebar__section-title{font-size:12px;color:#6b7280;text-transform:uppercase;padding:8px 12px;margin-top:6px;letter-spacing:.6px}
    .sidebar__separator{height:1px;background:#e5e7eb;margin:8px 10px 10px}
    .sidebar__nav{display:grid;gap:6px}
    .sidebar__link{position:relative;display:grid;grid-template-columns:22px 1fr;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:#1f2937}
    .sidebar__link:hover{background:#f3f4f6}
    .sidebar__indicator{position:absolute;left:0;top:8px;bottom:8px;width:4px;border-radius:4px;background:transparent}
    .sidebar__link.is-active{background:#ecf3ff;color:#0d3b7a;box-shadow:inset 0 0 0 1px #d8e7ff}
    .sidebar__link.is-active .sidebar__indicator{background:#0a4ea1}
    .sidebar__link i{color:#0a4ea1}
    .content{margin-top:64px;margin-left:240px;padding:28px;min-height:calc(100vh - 64px);background:var(--bg,#f5f6fa)}
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar__left">
    <img class="topbar__logo" src="<?= htmlspecialchars($URL_LOGO) ?>" alt="FSA">
  </div>
  <div class="topbar__center">
    <h1 class="topbar__title">COEPP</h1>
  </div>
  <div class="topbar__right">
    <div class="user-menu" aria-label="Usuário logado">
      <i class="fa fa-user-circle user-icon" aria-hidden="true"></i>
      <div class="user-dropdown" role="tooltip" aria-live="polite">
        <div class="user-email"><i class="fa-solid fa-envelope"></i><?= $USER_EMAIL ?></div>
      </div>
    </div>
  </div>
</header>

<aside class="sidebar">
  <div class="sidebar__section-title">Navegação</div>
  <nav class="sidebar__nav">
    <a class="sidebar__link<?= is_active([$REQ_DASHBOARD]) ?>" href="<?= htmlspecialchars($URL_DASHBOARD) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-house"></i><span class="sidebar__text">Página Inicial</span>
    </a>
    <a class="sidebar__link<?= is_active([$REQ_AGENDAMENTOS]) ?>" href="<?= htmlspecialchars($URL_AGENDAMENTOS) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-calendar-check"></i><span class="sidebar__text">Agendamentos</span>
    </a>
    <a class="sidebar__link<?= is_active([$REQ_CALENDARIO]) ?>" href="<?= htmlspecialchars($URL_CALENDARIO) ?>">
      <span class="sidebar__indicator"></span><i class="fa-regular fa-calendar-days"></i><span class="sidebar__text">Calendário</span>
    </a>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">Cadastros</div>
  <nav class="sidebar__nav">
    <a class="sidebar__link<?= is_active([$REQ_PACIENTES]) ?>" href="<?= htmlspecialchars($URL_PACIENTES) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-user-plus"></i><span class="sidebar__text">Pacientes</span>
    </a>
    <a class="sidebar__link<?= is_active([$REQ_PAC_PREFS]) ?>" href="<?= htmlspecialchars($URL_PAC_PREFS) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-star"></i><span class="sidebar__text">Preferenciais</span>
    </a>
    <a class="sidebar__link<?= is_active([$REQ_ESTAGIARIOS]) ?>" href="<?= htmlspecialchars($URL_ESTAGIARIOS) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-graduation-cap"></i><span class="sidebar__text">Estagiários</span>
    </a>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">Relatórios</div>
  <nav class="sidebar__nav">
    <a class="sidebar__link<?= is_active([$REQ_ATENDIDOS]) ?>" href="<?= htmlspecialchars($URL_ATENDIDOS) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-clipboard-check"></i><span class="sidebar__text">Atendimentos (realizados)</span>
    </a>
  </nav>

  <div class="sidebar__separator"></div>
  <div class="sidebar__section-title">Outros</div>
  <nav class="sidebar__nav">
    <a class="sidebar__link<?= is_active([$REQ_DUVIDAS]) ?>" href="<?= htmlspecialchars($URL_DUVIDAS) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-circle-question"></i><span class="sidebar__text">Dúvidas</span>
    </a>
    <a class="sidebar__link" href="<?= htmlspecialchars($URL_LOGOUT) ?>">
      <span class="sidebar__indicator"></span><i class="fa-solid fa-right-from-bracket"></i><span class="sidebar__text">Sair</span>
    </a>
  </nav>
</aside>

<main class="content">
<?= "<!-- DASH: $URL_DASHBOARD -->\n" ?>
<?= "<!-- AGEN: $URL_AGENDAMENTOS -->\n" ?>
<?= "<!-- CAL:  $URL_CALENDARIO  -->\n" ?>
<?= "<!-- PACS: $URL_PACIENTES   -->\n" ?>
<?= "<!-- PREF: $URL_PAC_PREFS   -->\n" ?>
<?= "<!-- ESTG: $URL_ESTAGIARIOS -->\n" ?>
<?= "<!-- ATEN: $URL_ATENDIDOS   -->\n" ?>
<?= "<!-- DUV:  $URL_DUVIDAS     -->\n" ?>