<?php
// config/init.php — bootstrap central

// ===== DEBUG TEMPORÁRIO (tira depois que estabilizar) =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Caminho absoluto do projeto (ex.: C:\xampp\htdocs\coepp)
if (!defined('ROOT')) {
    define('ROOT', realpath(__DIR__ . '/..'));
}

// URL base do projeto (assumindo http://localhost/coepp)
if (!defined('APP_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = '/' . basename(ROOT);          // "/coepp"
    define('APP_URL', $scheme . '://' . $host . $base);
}

// Conexão com o banco
require_once ROOT . '/config/db.php'; // precisa definir $mysqli (ou $conn)

// Normaliza para os dois nomes
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $GLOBALS['mysqli'] = $mysqli;
    if (!isset($conn)) $conn = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $GLOBALS['mysqli'] = $conn;
}

// Helpers
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return APP_URL . '/' . ltrim($path, '/');
    }
}
if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header('Location: ' . url($path));
        exit;
    }
}

// Modo diagnóstico simples: /qualquer/pagina.php?debug=1
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