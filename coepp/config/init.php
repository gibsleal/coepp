<?php
// =======================================================================
// config/init.php — bootstrap central do app
// [CONTEXTO]
//   - Sobe sessão, caminhos, URL base, DB e helpers.
//   - Oferece um modo diagnóstico (?debug=1).
// [TODO]
//   - IS_DEV vs IS_PROD para alternar exibição de erros.
//   - Timezone/locale padronizados.
//   - Cookies de sessão com SameSite/secure.
//   - .env para APP_URL/DB, evitando hardcode.
// =======================================================================

// ===== DEBUG TEMPORÁRIO (tire após estabilizar/produzir) =====
// [REGRA] Em produção, não exibir erros em tela.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// [AÇÃO] Sobe sessão se ainda não ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    // [TODO] Endurecer cookies de sessão (secure/httponly/samesite)
    // $params = session_get_cookie_params();
    // session_set_cookie_params([
    //   'lifetime' => 0,
    //   'path'     => $params['path'] ?? '/',
    //   'domain'   => $params['domain'] ?? '',
    //   'secure'   => !empty($_SERVER['HTTPS']),
    //   'httponly' => true,
    //   'samesite' => 'Lax',
    // ]);
    session_start();
}

// [AÇÃO] Caminho absoluto do projeto (ex.: C:\xampp\htdocs\coepp)
if (!defined('ROOT')) {
    define('ROOT', realpath(__DIR__ . '/..'));
}

// [AÇÃO] URL base do projeto (assumindo http://host/<nome-da-pasta>)
// [WARN] Atrás de proxy/reverso pode precisar de HTTP_X_FORWARDED_PROTO/HOST.
if (!defined('APP_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = '/' . basename(ROOT);          // ex.: "/coepp"
    define('APP_URL', $scheme . '://' . $host . $base);
}

// [AÇÃO] Conexão com o banco (define $mysqli / $conn)
require_once ROOT . '/config/db.php';

// [AÇÃO] Normaliza para os dois nomes ($mysqli e $conn) no escopo global
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $GLOBALS['mysqli'] = $mysqli;
    if (!isset($conn)) $conn = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $GLOBALS['mysqli'] = $conn;
}

// [HELPERS] Geração de URLs absolutas a partir de APP_URL
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return APP_URL . '/' . ltrim($path, '/');
    }
}

// [HELPERS] Caminho relativo a partir da raiz do projeto (para links locais)
if (!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        $root = '/' . basename(ROOT) . '/';
        return $root . ltrim($path, '/');
    }
}

// [HELPERS] Redirecionamento simples
if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header('Location: ' . url($path));
        exit;
    }
}

// [DIAGNÓSTICO] Exibe informações úteis quando ?debug=1
// - Útil para validar rota, APP_URL e conexão com DB.
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');

    echo "ROOT:      " . ROOT . PHP_EOL;
    echo "APP_URL:   " . APP_URL . PHP_EOL;
    echo "SCRIPT:    " . ($_SERVER['SCRIPT_FILENAME'] ?? '') . PHP_EOL;
    echo "REQUEST:   " . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL;
    echo "DB ping:   ";

    try {
        if (isset($GLOBALS['mysqli']) && @$GLOBALS['mysqli']->ping()) {
            echo "ok\n";
        } else {
            echo "falhou\n";
        }
    } catch (Throwable $e) {
        echo "erro: " . $e->getMessage() . "\n";
    }
    exit;
}