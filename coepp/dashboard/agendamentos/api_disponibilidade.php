<?php
// =======================================================================
// dashboard/agendamentos/api_disponibilidade.php
// [CONTEXTO]
//   - Retorna as horas disponíveis para um estagiário em uma data,
//     já considerando ocupação de salas e evitando duplicidade do estagiário.
// [REGRA]
//   - Trabalha com modelo simples: data (DATE), hora (TIME), sala (INT).
// =======================================================================

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'];

// -------------------- Entrada --------------------
$estagiario_id = (int)($_GET['estagiario_id'] ?? 0);
$data          = trim($_GET['data'] ?? '');
$exclude_id    = (int)($_GET['exclude_id'] ?? 0); // [REGRA] ao editar, desconsidera o próprio agendamento

// [VALIDAÇÃO] Parâmetros essenciais
if ($estagiario_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  echo json_encode(['erro' => 'Parâmetros inválidos']); exit;
}

// -------------------- Total de salas --------------------
// [AÇÃO] Conta quantas salas existem (limite superior de atendimentos por hora)
$total_salas = 0;
$rs = $mysqli->query("SELECT COUNT(*) AS n FROM salas");
if ($rs && ($r=$rs->fetch_assoc())) $total_salas = (int)$r['n'];

// -------------------- Disponibilidade do estagiário --------------------
// [AÇÃO] Busca JSON de disponibilidade do estagiário e extrai o dia da semana
$st = $mysqli->prepare("SELECT disponibilidade FROM estagiarios WHERE id = ? LIMIT 1");
$st->bind_param('i', $estagiario_id);
$st->execute();
$dispRow = $st->get_result()->fetch_assoc();
$st->close();

$horasDia = [];
if ($dispRow && $dispRow['disponibilidade']) {
  $json = json_decode($dispRow['disponibilidade'], true);
  // [MAP] 0..6 → chaves do JSON
  $map = ['0'=>'domingo','1'=>'segunda','2'=>'terca','3'=>'quarta','4'=>'quinta','5'=>'sexta','6'=>'sabado'];
  $wd  = $map[date('w', strtotime($data))] ?? null;
  if ($wd && isset($json[$wd]) && is_array($json[$wd])) {
    $horasDia = array_values($json[$wd]); // ["08:00","09:00",...]
  }
}

// [REGRA] Se não houver disponibilidade declarada, retorna vazio
if (!$horasDia) {
  echo json_encode(['horas'=>[], 'salas_por_hora'=>[], 'total_salas'=>$total_salas]); exit;
}

// -------------------- Ocupação geral por hora --------------------
// [AÇÃO] Conta quantos agendamentos existem por hora na data (todas as salas)
//        (exclui o próprio id ao editar)
$ocup = [];
if ($exclude_id>0) {
  $q1 = $mysqli->prepare("SELECT hora, COUNT(*) AS qtd FROM agendamentos WHERE data = ? AND id <> ? GROUP BY hora");
  $q1->bind_param('si', $data, $exclude_id);
} else {
  $q1 = $mysqli->prepare("SELECT hora, COUNT(*) AS qtd FROM agendamentos WHERE data = ? GROUP BY hora");
  $q1->bind_param('s', $data);
}
$q1->execute();
$res1 = $q1->get_result();
while ($r = $res1->fetch_assoc()) {
  // [AÇÃO] Normaliza HH:MM para chave do array
  $ocup[substr($r['hora'],0,5)] = (int)$r['qtd'];
}
$q1->close();

// -------------------- Horários do estagiário nesse dia --------------------
// [REGRA] Impede duplicidade do mesmo estagiário na mesma hora/data
if ($exclude_id>0) {
  $q2 = $mysqli->prepare("SELECT hora FROM agendamentos WHERE data = ? AND estagiario_id = ? AND id <> ?");
  $q2->bind_param('sii', $data, $estagiario_id, $exclude_id);
} else {
  $q2 = $mysqli->prepare("SELECT hora FROM agendamentos WHERE data = ? AND estagiario_id = ?");
  $q2->bind_param('si', $data, $estagiario_id);
}
$q2->execute();
$res2 = $q2->get_result();
$indis = [];
while ($r = $res2->fetch_assoc()) {
  $indis[substr($r['hora'],0,5)] = true;
}
$q2->close();

// -------------------- Montagem das respostas --------------------
$horasValidas = [];   // [SAÍDA] lista de horas disponíveis (HH:MM)
$salasPorHora = [];   // [SAÍDA] mapa "HH:MM" => [salas livres]

foreach ($horasDia as $h) {
  // [REGRA] Pula se o estagiário já tem agendamento nessa hora
  if (!empty($indis[$h])) continue;

  // [REGRA] Se ocupação >= total de salas, não há vaga nessa hora
  $uso = $ocup[$h] ?? 0;
  if ($uso >= $total_salas) continue;

  // [AÇÃO] Coleta salas ocupadas nesse horário (excluindo o próprio id quando edição)
  if ($exclude_id>0) {
    $q3 = $mysqli->prepare("SELECT sala FROM agendamentos WHERE data = ? AND hora = ? AND id <> ?");
    $q3->bind_param('ssi', $data, $h, $exclude_id);
  } else {
    $q3 = $mysqli->prepare("SELECT sala FROM agendamentos WHERE data = ? AND hora = ?");
    $q3->bind_param('ss', $data, $h);
  }
  $q3->execute();
  $res3 = $q3->get_result();
  $ocupadas = [];
  while ($r=$res3->fetch_assoc()) $ocupadas[(int)$r['sala']] = true;
  $q3->close();

  // [AÇÃO] Salas livres = 1..N menos as ocupadas
  $livres = [];
  for ($s=1; $s <= $total_salas; $s++) {
    if (empty($ocupadas[$s])) $livres[] = $s;
  }

  // [REGRA] Só adiciona hora se existir ao menos uma sala livre
  if ($livres) {
    $horasValidas[]   = $h;
    $salasPorHora[$h] = $livres;
  }
}

// -------------------- Saída --------------------
echo json_encode([
  'horas'          => $horasValidas,
  'salas_por_hora' => $salasPorHora,
  'total_salas'    => $total_salas,
]);