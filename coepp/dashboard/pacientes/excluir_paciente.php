<?php
// dashboard/pacientes/excluir_paciente.php
// Exclui paciente por ID com checagens de segurança e vínculos.
// OBS: A exclusão é BLOQUEADA se houver agendamentos vinculados (de hoje ou históricos).
//      Caso o paciente não exista, tratamos como sucesso idempotente (UX mais fluida).
//      Em erro de FK (1451), mostra mensagem amigável.

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode('Conexão indisponível.')));
  exit;
}

// ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode('ID inválido.')));
  exit;
}

// Busca o paciente (pra validar existência e montar mensagens)
$st = $mysqli->prepare("SELECT id, nome FROM pacientes WHERE id = ? LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$pac = $st->get_result()->fetch_assoc();
$st->close();

if (!$pac) {
  // OBS: se já não existe, consideramos OK (idempotente)
  header('Location: ' . url('dashboard/pacientes/pacientes.php?ok=1'));
  exit;
}

// Verifica vínculos em agendamentos
$st = $mysqli->prepare("SELECT COUNT(*) AS total FROM agendamentos WHERE paciente_id = ?");
$st->bind_param('i', $id);
$st->execute();
$totalAg = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
$st->close();

if ($totalAg > 0) {
  // OBS: impedimos excluir para não corromper histórico; oferecemos caminho de ação
  $msg = "Não é possível excluir: há {$totalAg} agendamento(s) vinculado(s) a este paciente.";
  $link = url('dashboard/agendamentos/agendamentos.php?busca=' . urlencode($pac['nome']));
  $msg .= " Acesse a página de Agendamentos para remover/ajustar primeiro.";
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode($msg) . '&redir=' . urlencode($link)));
  exit;
}

// Tenta excluir (sem vínculos)
$del = $mysqli->prepare("DELETE FROM pacientes WHERE id = ?");
$del->bind_param('i', $id);

if ($del->execute()) {
  $del->close();
  header('Location: ' . url('dashboard/pacientes/pacientes.php?ok=1'));
  exit;
} else {
  // OBS: fallback em caso de FK 1451 por alguma relação desconhecida
  $code = $mysqli->errno;
  $msg  = ($code === 1451)
    ? 'Não é possível excluir: há registros relacionados.'
    : 'Erro ao excluir paciente.';
  $del->close();
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode($msg)));
  exit;
}