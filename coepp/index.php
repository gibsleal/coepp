<?php
// OBS: Front controller de nível raiz: redireciona usuário para login ou dashboard.
require_once __DIR__ . '/config/init.php';

if (empty($_SESSION['user_id'])) {
  // OBS: Se não autenticado, envia para /auth/login.php (helper url() cuida de base path).
  header('Location: ' . url('auth/login.php'));
  exit;
}

// OBS: Autenticado → direciona para o painel principal.
header('Location: ' . url('dashboard/index.php'));
exit;