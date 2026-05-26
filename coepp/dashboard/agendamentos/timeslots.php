<?php
// =======================================================================
// timeslots.php
// [FUNÇÃO] Devolve (JSON) a lista de horários livres para estagiário+data.
// [REGRAS]
//   - Lê disponibilidade do estagiário (JSON).
//   - Remove horários já ocupados pelo estagiário.
//   - Respeita capacidade total de salas no horário (tabela `salas`).
// =======================================================================

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
  http_response_code(500);
  echo json_encode(['error' => 'Conexão indisponível.']);
  exit;
}

$estagiario_id = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;
$data          = isset($_GET['data']) ? trim($_GET['data']) : '';

if ($estagiario_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  http_response_code(400);
  echo json_encode(['error' => 'Parâmetros inválidos.']);
  exit;
}

/** Lê capacidade total de salas (dinâmica, com fallback) */
function total_salas(mysqli $db): int {
  try {
    $rs = $db->query("SELECT COUNT(*) FROM salas");
    if ($rs) {
      $n = (int)$rs->fetch_row()[0];
      return ($n > 0) ? $n : 8; // fallback 8
    }
  } catch (Throwable $e) {}
  return 8; // fallback se tabela não existir
}

$CAP = total_salas($mysqli);

// 1) Busca disponibilidade do estagiário
$st = $mysqli->prepare("SELECT disponibilidade FROM estagiarios WHERE id = ? LIMIT 1");
$st->bind_param('i', $estagiario_id);
$st->execute();
$res = $st->get_result();
$row = $res->fetch_assoc();
$st->close();

if (!$row) {
  echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
  exit;
}

$disp = json_decode((string)$row['disponibilidade'], true);
if (!is_array($disp)) $disp = [];

// 2) Mapeia data -> chave de dia usada na disponibilidade
$dt = DateTime::createFromFormat('Y-m-d', $data);
$weekdayN = (int)$dt->format('N'); // 1=Seg ... 7=Dom
$map = [1=>'segunda', 2=>'terca', 3=>'quarta', 4=>'quinta', 5=>'sexta', 6=>'sabado', 7=>'domingo'];
$chaveDia = $map[$weekdayN] ?? '';

// 3) Slots base vindos da disponibilidade (padroniza em HH:MM)
$baseSlots = [];
if ($chaveDia && !empty($disp[$chaveDia]) && is_array($disp[$chaveDia])) {
  foreach ($disp[$chaveDia] as $h) {
    $h = substr((string)$h, 0, 5);
    if (preg_match('/^\d{2}:\d{2}$/', $h)) $baseSlots[] = $h;
  }
  $baseSlots = array_values(array_unique($baseSlots));
  sort($baseSlots);
}

// Se não há nada na disponibilidade, já retorna
if (!$baseSlots) {
  echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
  exit;
}

/*
  4) Carrega, em UMA query:
     - total de agendamentos por horário (para checar capacidade das salas)
     - se o estagiário já está ocupado nesse horário (SUM CASE)
*/
$st2 = $mysqli->prepare("
  SELECT SUBSTRING(hora,1,5) AS h,
         COUNT(*) AS qtd,
         SUM(CASE WHEN estagiario_id = ? THEN 1 ELSE 0 END) AS est
    FROM agendamentos
   WHERE data = ?
   GROUP BY h
");
$st2->bind_param('is', $estagiario_id, $data);
$st2->execute();
$rs2 = $st2->get_result();

$ocupacao = []; // h => ['qtd'=>N, 'est'=>N]
while ($r = $rs2->fetch_assoc()) {
  $h = (string)$r['h'];
  $ocupacao[$h] = [
    'qtd' => (int)$r['qtd'],
    'est' => (int)$r['est'],
  ];
}
$st2->close();

// 5) Filtra: remove horários ocupados pelo estagiário OU com salas cheias
$livres = [];
foreach ($baseSlots as $h) {
  $qtd = $ocupacao[$h]['qtd'] ?? 0;
  $est = $ocupacao[$h]['est'] ?? 0;

  if ($est > 0) continue;     // estagiário já tem agendamento neste horário
  if ($qtd >= $CAP) continue; // todas as salas já ocupadas neste horário

  $livres[] = $h;
}

echo json_encode(['slots' => $livres], JSON_UNESCAPED_UNICODE);