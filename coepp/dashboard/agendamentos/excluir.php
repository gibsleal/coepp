<?php
// =======================================================================
// dashboard/agendamentos/excluir.php
// [CONTEXTO]
//   - Exclui um agendamento por ID (com segurança via prepared).
// [REGRA]
//   - Requer sessão e guarda de autenticação.
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
  // [FALLBACK] Sem conexão → volta com erro
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?erro=Conexao+indisponivel'));
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?erro=ID+invalido'));
  exit;
}

// [AÇÃO] Checa se o registro existe antes de excluir
$chk = $mysqli->prepare("SELECT id FROM agendamentos WHERE id = ? LIMIT 1");
$chk->bind_param('i', $id);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$exists) {
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?erro=Agendamento+nao+encontrado'));
  exit;
}

// [AÇÃO] Executa exclusão
$stmt = $mysqli->prepare("DELETE FROM agendamentos WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
  $stmt->close();
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?ok=1'));
  exit;
} else {
  $stmt->close();
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?erro=Falha+ao+excluir'));
  exit;
}