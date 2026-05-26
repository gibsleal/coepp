<?php
/** =======================================================================
 *  auth/logout.php — Encerrar sessão do usuário
 *  [CONTEXTO]
 *    - Limpa dados de sessão e invalida o cookie de sessão.
 *  [REGRA]
 *    - Sempre redireciona para a tela de login ao final.
 *  [TODO]
 *    - Opcional: registrar log de auditoria (user_id, timestamp, IP).
 * ======================================================================= */

require_once __DIR__ . '/../config/init.php';

// [AÇÃO] Zera array de sessão
$_SESSION = [];

// [AÇÃO] Invalida cookie de sessão respeitando parâmetros atuais
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 3600,                     // expira no passado
    $params['path']     ?? '/',
    $params['domain']   ?? '',
    $params['secure']   ?? false,      // true em produção (HTTPS)
    $params['httponly'] ?? true
);

// [AÇÃO] Destroi a sessão no servidor, se ativa
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// [AÇÃO] Redireciona para login
header('Location: ' . url('auth/login.php'));
exit;