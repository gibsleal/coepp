<?php
// includes/auth_guard.php
// OBS: Este guard deve ser incluído em toda rota privada do dashboard.
// OBS: Mantém compatibilidade com sessão antiga (email) e nova (user_id).

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(); // OBS: Garante sessão ativa antes de ler variáveis
}

/*
  Considera autenticado se:
  - Sessão nova (user_id definido pelo login atual), OU
  - Sessão antiga (email antigo), para compatibilidade.
*/
$logged =
    (!empty($_SESSION['user_id'])) || // OBS: Preferencial (nova autenticação)
    (!empty($_SESSION['email']));     // OBS: Legado: ainda aceita email na sessão

if (!$logged) {
    // usa o helper url() do init.php
    // OBS: Redireciona sempre para a página de login da área /auth
    header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/auth/login.php');
    exit;
}