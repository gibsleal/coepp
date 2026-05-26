<?php
// =======================================================================
// dashboard/agendamentos/atualizar.php
// [CONTEXTO]
//   - Recebe POST de edição de agendamento e aplica validações de conflito.
// [REGRA]
//   - Checa: campos obrigatórios, formato data/hora, existência de sala,
//     conflitos de estagiário/sala/paciente e limite de salas.
// =======================================================================

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
  die('Conexão não disponível.');
}

// [UTIL] Redireciona de volta para o form de edição com mensagem
function back($id, $msg){
  $_SESSION['flash_err'] = $msg;
  header('Location: ' . url('dashboard/agendamentos/editar.php?id=' . (int)$id));
  exit;
}

// [UTIL] Normaliza HH:MM(:SS) para HH:MM
function normalize_hora($h){
  $h = trim((string)$h);
  if ($h === '') return '';
  if (preg_match('/^\d{2}:\d{2}$/', $h))      return $h;          // HH:MM
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return substr($h,0,5); // HH:MM:SS -> HH:MM
  return '';
}

// -------------------- Coleta POST --------------------
$id            = (int)($_POST['id'] ?? 0);
$paciente_id   = (int)($_POST['paciente_id'] ?? 0);
$estagiario_id = (int)($_POST['estagiario_id'] ?? 0);
$data          = trim($_POST['data'] ?? '');
$hora_in       = trim($_POST['hora'] ?? '');
$sala          = (int)($_POST['sala'] ?? 0);
$tipo_servico  = trim($_POST['tipo_servico'] ?? '');
$hora          = normalize_hora($hora_in);

// [REGRA] ID válido?
if ($id <= 0) {
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php'));
  exit;
}

// [REGRA] Agendamento existe?
$chk = $mysqli->prepare("SELECT id FROM agendamentos WHERE id=? LIMIT 1");
$chk->bind_param('i', $id);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
  $chk->close();
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?erro=Agendamento não encontrado.'));
  exit;
}
$chk->close();

// -------------------- Validações básicas --------------------
if ($paciente_id<=0 || $estagiario_id<=0 || !$data || !$hora || $sala<=0 || $tipo_servico===''){
  back($id, 'Preencha todos os campos obrigatórios.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) back($id, 'Data inválida.');
if (!preg_match('/^\d{2}:\d{2}$/', $hora))      back($id, 'Hora inválida.');

// [AÇÃO] Total de salas (defensivo)
$total_salas = 0;
$rs = $mysqli->query("SELECT COUNT(*) AS n FROM salas");
if ($rs && ($r=$rs->fetch_assoc())) $total_salas = (int)$r['n'];
if ($sala > $total_salas) back($id, 'Sala inexistente.');

// -------------------- Regras de conflito --------------------
// [REGRA] Estagiário já ocupado nesse horário? (exclui o próprio ID)
$st = $mysqli->prepare("SELECT id FROM agendamentos WHERE estagiario_id=? AND data=? AND hora=? AND id<>? LIMIT 1");
$st->bind_param('issi', $estagiario_id, $data, $hora, $id);
$st->execute();
if ($st->get_result()->fetch_assoc()) { $st->close(); back($id, 'Estagiário já possui agendamento nesse horário.'); }
$st->close();

// [REGRA] Sala já ocupada nesse horário? (exclui o próprio ID)
$st = $mysqli->prepare("SELECT id FROM agendamentos WHERE data=? AND hora=? AND sala=? AND id<>? LIMIT 1");
$st->bind_param('ssii', $data, $hora, $sala, $id);
$st->execute();
if ($st->get_result()->fetch_assoc()) { $st->close(); back($id, 'Sala já ocupada nesse horário.'); }
$st->close();

// [REGRA] Paciente já ocupado nesse horário? (exclui o próprio ID)
$st = $mysqli->prepare("SELECT id FROM agendamentos WHERE data=? AND hora=? AND paciente_id=? AND id<>? LIMIT 1");
$st->bind_param('ssii', $data, $hora, $paciente_id, $id);
$st->execute();
if ($st->get_result()->fetch_assoc()) { $st->close(); back($id, 'Paciente já possui agendamento nesse horário.'); }
$st->close();

// [REGRA] Limite de salas no horário (defensivo)
$st = $mysqli->prepare("SELECT COUNT(*) AS qtd FROM agendamentos WHERE data=? AND hora=? AND id<>?");
$st->bind_param('ssi', $data, $hora, $id);
$st->execute();
$r = $st->get_result()->fetch_assoc();
$st->close();
if ((int)$r['qtd'] >= $total_salas) back($id, 'Não há salas disponíveis nesse horário.');

// -------------------- Disponibilidade do estagiário --------------------
$st = $mysqli->prepare("SELECT disponibilidade FROM estagiarios WHERE id=? LIMIT 1");
$st->bind_param('i', $estagiario_id);
$st->execute();
$dispRow = $st->get_result()->fetch_assoc();
$st->close();

$ok = false;
if ($dispRow && $dispRow['disponibilidade']) {
  $json = json_decode($dispRow['disponibilidade'], true);
  $map = ['0'=>'domingo','1'=>'segunda','2'=>'terca','3'=>'quarta','4'=>'quinta','5'=>'sexta','6'=>'sabado'];
  $wd  = $map[date('w', strtotime($data))] ?? null;
  if ($wd && !empty($json[$wd]) && in_array($hora, $json[$wd], true)) $ok = true;
}
if (!$ok) back($id, 'Horário fora da disponibilidade do estagiário.');

// -------------------- UPDATE --------------------
$now = date('Y-m-d H:i:s');
$up = $mysqli->prepare("
  UPDATE agendamentos
     SET paciente_id   = ?,
         estagiario_id = ?,
         data          = ?,
         hora          = ?,
         sala          = ?,
         tipo_servico  = ?,
         updated_at    = ?
   WHERE id = ?
");
$up->bind_param(
  'iississi',
  $paciente_id,
  $estagiario_id,
  $data,
  $hora,
  $sala,
  $tipo_servico,
  $now,
  $id
);
$up->execute();
$up->close();

// [AÇÃO] Redireciona com sucesso
header('Location: ' . url('dashboard/agendamentos/agendamentos.php?ok=1'));
exit;