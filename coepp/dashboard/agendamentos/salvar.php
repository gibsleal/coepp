<?php
// =======================================================================
// salvar.php
// [FUNÇÃO] Valida e insere novo agendamento, checando disponibilidade,
//          conflitos de sala/estagiário e capacidade total de salas.
// [DINÂMICO] Insere `obs` se a coluna existir no banco.
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
  $_SESSION['flash_err'] = 'Conexão não disponível.';
  header('Location: ' . url('dashboard/agendamentos/novo.php'));
  exit;
}

// [DEV] Útil para ver erros de mysqli durante desenvolvimento
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Redireciona de volta ao form com mensagem e prefill
function back_with(string $msg, array $prefill = []): void {
  $_SESSION['flash_err'] = $msg;
  $qs = [];
  foreach ($prefill as $k => $v) {
    if ($v === null || $v === '') continue;
    $qs[] = urlencode($k) . '=' . urlencode((string)$v);
  }
  $redir = 'dashboard/agendamentos/novo.php' . ($qs ? ('?' . implode('&', $qs)) : '');
  header('Location: ' . url($redir));
  exit;
}

// Utilitários (iguais aos usados em editar.php)
function col_exists(mysqli $db, string $table, string $col): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $col))   return false;
  $colEsc = $db->real_escape_string($col);
  $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$colEsc}'";
  try { $res = $db->query($sql); return ($res && $res->num_rows > 0); }
  catch (Throwable $e) { return false; }
}
function parse_disponibilidade($json_raw): array {
  if ($json_raw === null || $json_raw === '') return [];
  $decoded = json_decode($json_raw, true);
  if (json_last_error() !== JSON_ERROR_NONE) $decoded = json_decode((string)$json_raw, true);
  if (!is_array($decoded)) return [];
  $mapKeys = [
    'segunda'=>'segunda','terça'=>'terca','terca'=>'terca','quarta'=>'quarta','quinta'=>'quinta',
    'sexta'=>'sexta','sábado'=>'sabado','sabado'=>'sabado','domingo'=>'domingo'
  ];
  $out = [];
  foreach ($decoded as $k => $v) {
    $lk = mb_strtolower((string)$k, 'UTF-8');
    $norm = $mapKeys[$lk] ?? $lk;
    if (is_array($v)) {
      $horas=[];
      foreach ($v as $h) {
        $h = trim((string)$h);
        if ($h === '') continue;
        if (preg_match('/^\d{2}:\d{2}/', $h)) $horas[] = substr($h, 0, 5);
      }
      $out[$norm] = array_values(array_unique($horas));
    }
  }
  return $out;
}
function weekday_key_for_date(string $ymd): string {
  $ts = strtotime($ymd);
  if (!$ts) return 'segunda';
  $w = (int)date('w', $ts); // 0=Dom..6=Sab
  $map=[0=>'domingo',1=>'segunda',2=>'terca',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sabado'];
  return $map[$w] ?? 'segunda';
}
function hhmm(string $h): string {
  $h = trim($h);
  if (preg_match('/^\d{2}:\d{2}$/', $h)) return $h;
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return substr($h,0,5);
  return '';
}
function total_salas(mysqli $db): int {
  try { $r = $db->query("SELECT COUNT(*) FROM salas"); if ($r) return (int)$r->fetch_row()[0]; }
  catch (Throwable $e) {}
  return 0;
}
function sala_existe(mysqli $db, int $id): bool {
  $st = $db->prepare("SELECT 1 FROM salas WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  $ex = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ex;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url('dashboard/agendamentos/novo.php'));
  exit;
}

// ------ lê campos ------
$estagiario_id = (int)($_POST['estagiario_id'] ?? 0);
$paciente_id   = (int)($_POST['paciente_id']   ?? 0);
$data_in       = trim($_POST['data'] ?? '');
$hora_in       = trim($_POST['hora'] ?? '');
$sala          = (int)($_POST['sala'] ?? 0);
$tipo_servico  = trim($_POST['tipo_servico'] ?? '');
$obs_in        = trim($_POST['obs'] ?? '');

// para manter prefill quando der erro
$prefill = [
  'estagiario_id' => $estagiario_id,
  'paciente_id'   => $paciente_id,
  'data'          => $data_in,
  'hora'          => $hora_in,
];

// normaliza data dd/mm/yyyy -> yyyy-mm-dd, se vier assim
$data = $data_in;
if ($data && preg_match('#^\d{2}/\d{2}/\d{4}$#', $data)) {
  [$d,$m,$y] = explode('/', $data);
  $data = "$y-$m-$d";
}

// normaliza hora
$hora = hhmm($hora_in);
$horaFull = $hora ? ($hora . ':00') : '';

// validações básicas
if ($estagiario_id<=0 || $paciente_id<=0 || !$data || !$hora || $sala<=0 || $tipo_servico==='') {
  back_with('Preencha todos os campos obrigatórios.', $prefill);
}

$permitidos = ['Triagem','Acompanhamento','Terapia'];
if (!in_array($tipo_servico, $permitidos, true)) {
  back_with('Tipo de serviço inválido.', $prefill);
}

if (!sala_existe($mysqli, $sala)) {
  back_with('A sala informada não existe.', $prefill);
}

// disponibilidade do estagiário
$qdisp = $mysqli->prepare("SELECT disponibilidade FROM estagiarios WHERE id=?");
$qdisp->bind_param('i', $estagiario_id);
$qdisp->execute();
$disp_raw = $qdisp->get_result()->fetch_assoc()['disponibilidade'] ?? null;
$qdisp->close();

$disp = parse_disponibilidade($disp_raw);
$diaKey = weekday_key_for_date($data);
$horas_dia = $disp[$diaKey] ?? [];
if (!in_array($hora, $horas_dia, true)) {
  back_with("Este estagiário não possui disponibilidade em " . ucfirst($diaKey) . " às {$hora}.", $prefill);
}

// conflitos: sala
$q1 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=? AND sala=?");
$q1->bind_param('ssi', $data, $horaFull, $sala);
$q1->execute();
$c1 = (int)$q1->get_result()->fetch_assoc()['c'];
$q1->close();
if ($c1>0) back_with("Já existe agendamento na sala {$sala} para este horário.", $prefill);

// conflitos: estagiário
$q2 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=? AND estagiario_id=?");
$q2->bind_param('ssi', $data, $horaFull, $estagiario_id);
$q2->execute();
$c2 = (int)$q2->get_result()->fetch_assoc()['c'];
$q2->close();
if ($c2>0) back_with("Este estagiário já possui agendamento neste horário.", $prefill);

// limite total de salas (capacidade por horário)
$cap = max(0, total_salas($mysqli));
if ($cap > 0) {
  $q3 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=?");
  $q3->bind_param('ss', $data, $horaFull);
  $q3->execute();
  $c3 = (int)$q3->get_result()->fetch_assoc()['c'];
  $q3->close();
  if ($c3 >= $cap) back_with("Limite de {$cap} salas por horário atingido.", $prefill);
}

// verifica se a tabela tem coluna obs
$has_obs = col_exists($mysqli, 'agendamentos', 'obs');

try {
  if ($has_obs) {
    // INSERT com obs
    $sql = "INSERT INTO agendamentos
              (paciente_id, estagiario_id, data, hora, sala, tipo_servico, obs, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,NOW(),NOW())";
    $st = $mysqli->prepare($sql);
    // tipos: i i s s i s s
    $st->bind_param('iississ',
      $paciente_id, $estagiario_id, $data, $horaFull, $sala, $tipo_servico, $obs_in
    );
  } else {
    // INSERT sem obs
    $sql = "INSERT INTO agendamentos
              (paciente_id, estagiario_id, data, hora, sala, tipo_servico, created_at, updated_at)
            VALUES (?,?,?,?,?,?,NOW(),NOW())";
    $st = $mysqli->prepare($sql);
    // tipos: i i s s i s
    $st->bind_param('iissis',
      $paciente_id, $estagiario_id, $data, $horaFull, $sala, $tipo_servico
    );
  }

  $st->execute();
  $st->close();

  header('Location: ' . url('dashboard/agendamentos/agendamentos.php?ok=1'));
  exit;

} catch (Throwable $e) {
  back_with('Erro ao salvar o agendamento: ' . $e->getMessage(), $prefill);
}