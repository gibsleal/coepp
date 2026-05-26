<?php
// dashboard/estagiarios/excluir_estagiarios.php
// Exclui um estagiário por ID usando prepared statements.
//      Trata erro de restrição de FK (MySQL 1451) para informar quando existem vínculos.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'];

// --- valida ID ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?erro=ID+inválido'));
  exit;
}

// --- exclusão segura (prepared) ---
$sql = "DELETE FROM estagiarios WHERE id = ?";
$st = $mysqli->prepare($sql);
if (!$st) {
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?erro=Falha+ao+preparar+exclusão'));
  exit;
}
$st->bind_param('i', $id);

if ($st->execute()) {
  $st->close();
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?ok=1'));
  exit;
} else {
  // OBS: Código 1451 = restrição de chave estrangeira (há vínculos).
  $code = $mysqli->errno;
  $msg  = ($code === 1451)
    ? 'Não+é+possível+excluir:+há+registros+relacionados.'
    : 'Erro+ao+excluir.';
  $st->close();
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?erro=' . $msg));
  exit;
}