<?php
// dashboard/calendario/events.php
// Gera eventos “ocupados” (agendamentos) para o FullCalendar.
// OBS: Recebe start/end (YYYY-MM-DD) e estagiario_id opcional.
//      Monta blocos com 1h de duração (conforto de clique) e envia extendedProps.

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { echo json_encode([]); exit; }

// OBS: Validação básica dos parâmetros de data (YYYY-MM-DD).
$start = isset($_GET['start']) ? $_GET['start'] : '';
$end   = isset($_GET['end'])   ? $_GET['end']   : '';
$est   = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
  echo json_encode([]); exit;
}

// OBS: WHERE com parâmetros preparados evita SQL injection.
$where = " WHERE a.data BETWEEN ? AND ? ";
$params = [$start, $end];
$types  = 'ss';

if ($est > 0) {
  $where .= " AND a.estagiario_id = ? ";
  $params[] = $est;
  $types   .= 'i';
}

$sql = "
  SELECT a.id, a.data, a.hora, a.sala, a.tipo_servico,
         e.nome AS estagiario_nome,
         p.nome AS paciente_nome
  FROM agendamentos a
  JOIN estagiarios e ON e.id = a.estagiario_id
  LEFT JOIN pacientes p ON p.id = a.paciente_id
  $where
  ORDER BY a.data ASC, a.hora ASC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rs = $stmt->get_result();

$out = [];
while ($r = $rs->fetch_assoc()) {
  $date = $r['data'];
  $time = substr($r['hora'], 0, 5);

  // OBS: Duração padrão de 1h para facilitar interação visual no calendário.
  $startIso = "{$date}T{$time}:00";
  $endIso   = date('Y-m-d\TH:i:s', strtotime($startIso . ' +1 hour'));

  // OBS: Título resumido para leitura rápida; detalhes completos estão em extendedProps.
  $title = ($r['paciente_nome'] ?: '—') . ' · ' . $r['estagiario_nome'] . " · Sala " . $r['sala'];

  $out[] = [
    'id'    => (string)$r['id'], // OBS: FullCalendar espera string; guardamos também em extendedProps.
    'title' => $title,
    'start' => $startIso,
    'end'   => $endIso,
    'extendedProps' => [
      'db_id'         => (int)$r['id'],
      'paciente_nome' => $r['paciente_nome'],
      'estagiario'    => $r['estagiario_nome'],
      'sala'          => (int)$r['sala'],
      'tipo_servico'  => $r['tipo_servico'],
      'data'          => $date,
      'hora'          => $time,
    ]
  ];
}
$stmt->close();

// OBS: JSON sem escapar unicode para manter acentuação correta.
echo json_encode($out, JSON_UNESCAPED_UNICODE);