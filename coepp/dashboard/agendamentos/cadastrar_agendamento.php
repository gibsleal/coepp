<?php
// =======================================================================
// dashboard/agendamentos/cadastrar_agendamento.php
// [CONTEXTO]
//   - Cria um novo agendamento a partir de POST.
// [REGRA]
//   - Valida dados, disponibilidade do estagiário e choque de sala.
// [WARN]
//   - Este arquivo usa colunas sala_id / hora_inicio / hora_fim / status 'pendente'
//     que podem não existir no schema atual. Mantido conforme seu código.
// =======================================================================

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'];

// [REGRA] Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect('dashboard/agendamentos/agendamentos.php?erro=Acesso inválido');
}

// -------------------- Coleta --------------------
$paciente_id   = (int)($_POST['paciente_id'] ?? 0);
$estagiario_id = (int)($_POST['estagiario_id'] ?? 0);
$sala_id       = (int)($_POST['sala_id'] ?? 0);
$dataISO       = trim($_POST['data'] ?? '');         // yyyy-mm-dd
$hora_inicio   = trim($_POST['hora_inicio'] ?? '');  // HH:MM
$duracao       = (int)($_POST['duracao'] ?? 60);
$observacoes   = trim($_POST['observacoes'] ?? '');

// [VALIDAÇÃO] Formatos e obrigatórios
if ($paciente_id<=0 || $estagiario_id<=0 || $sala_id<=0
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataISO)
    || !preg_match('/^\d{2}:\d{2}$/',$hora_inicio) || $duracao<=0) {
  redirect('dashboard/agendamentos/agendamentos.php?erro=Dados inválidos');
}

// [AÇÃO] Calcula hora fim a partir da duração
$hora_fim = date('H:i', strtotime("$dataISO $hora_inicio:00") + $duracao*60);

// -------------------- Regras --------------------
/**
 * [AÇÃO] Checa se a hora está na disponibilidade declarada do estagiário.
 */
function estagiarioTemHorario(mysqli $db, int $id, string $dataISO, string $h): bool {
  $dow = (int)date('w', strtotime($dataISO));
  $map = [0=>'domingo',1=>'segunda',2=>'terca',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sabado'];

  $st = $db->prepare("SELECT disponibilidade FROM estagiarios WHERE id=? LIMIT 1");
  $st->bind_param('i',$id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();

  $arr = json_decode($row['disponibilidade'] ?? '{}', true);
  if (!is_array($arr)) return false;
  $key = $map[$dow] ?? '';
  $list = $arr[$key] ?? [];
  return in_array($h, (array)$list, true);
}

/**
 * [AÇÃO] Checa sobreposição na sala para [ini,fim).
 * [WARN] Usa sala_id/hora_inicio/hora_fim/status 'pendente/confirmado' conforme seu código atual.
 */
function existeChoque(mysqli $db, int $salaId, string $dataISO, string $ini, string $fim): bool {
  $sql = "SELECT 1 FROM agendamentos 
          WHERE sala_id=? AND data=? AND status IN ('pendente','confirmado')
            AND (hora_inicio < ? AND hora_fim > ?)
          LIMIT 1";
  $st = $db->prepare($sql);
  $st->bind_param('isss', $salaId, $dataISO, $fim, $ini);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

// [REGRA] Disponibilidade do estagiário
if (!estagiarioTemHorario($mysqli, $estagiario_id, $dataISO, $hora_inicio)) {
  redirect('dashboard/agendamentos/agendamentos.php?erro=Horário fora da disponibilidade do estagiário');
}

// [REGRA] Choque na sala
if (existeChoque($mysqli, $sala_id, $dataISO, $hora_inicio, $hora_fim)) {
  redirect('dashboard/agendamentos/agendamentos.php?erro=Já existe consulta nessa sala/horário');
}

// -------------------- Persistência --------------------
$st = $mysqli->prepare("INSERT INTO agendamentos
  (paciente_id, estagiario_id, sala_id, data, hora_inicio, hora_fim, status, observacoes)
  VALUES (?,?,?,?,?,?, 'pendente', ?)");
$st->bind_param('iiissss', $paciente_id, $estagiario_id, $sala_id, $dataISO, $hora_inicio, $hora_fim, $observacoes);

if ($st->execute()) {
  $st->close();
  redirect('dashboard/agendamentos/agendamentos.php?ok=1');
}
$st->close();
redirect('dashboard/agendamentos/agendamentos.php?erro=Falha ao salvar');