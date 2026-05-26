<?php
// dashboard/calendario/calendario_feed.php
// Gera eventos FullCalendar: slots LIVRES (disponibilidade) e AGENDADOS (ocupados).
// OBS: No calendário atual você usa events.php + availability.php.
//      Este arquivo pode estar “sobrando”/legado — mantido por compatibilidade.
//      Se decidir removê-lo, verifique chamadas antigas no front para evitar 404.

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=UTF-8');

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { echo json_encode([]); exit; }

// OBS: Ajuste de fuso local para consistência visual no FullCalendar.
date_default_timezone_set('America/Sao_Paulo');

/**
 * weekday_key_for_date
 * OBS: Converte uma data Y-m-d em chave de disponibilidade ('segunda', 'terca', ...).
 */
function weekday_key_for_date(string $ymd): string {
  $w = (int)date('w', strtotime($ymd)); // 0=Dom .. 6=Sab
  $map = [0=>'domingo',1=>'segunda',2=>'terca',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sabado'];
  return $map[$w] ?? 'segunda';
}

/**
 * parse_disponibilidade
 * OBS: Lê JSON de disponibilidade e normaliza chaves com/sem acento.
 *      Garante formato HH:MM nas horas.
 */
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
        if (preg_match('/^\d{2}:\d{2}/', $h)) $horas[] = substr($h,0,5);
      }
      $out[$norm] = array_values(array_unique($horas));
    }
  }
  return $out;
}

// OBS: Capacidade dinâmica pela contagem de salas; fallback=8 se consulta falhar.
$cap_res = $mysqli->query("SELECT COUNT(*) FROM salas");
$TOTAL_SALAS = $cap_res ? (int)$cap_res->fetch_row()[0] : 8; // fallback

// OBS: Intervalo vindo do FullCalendar em ISO (start inclusive, end exclusivo).
$startParam = $_GET['start'] ?? null;
$endParam   = $_GET['end']   ?? null;

try {
  // OBS: Se não vier, usamos a semana corrente como padrão.
  $start = new DateTime($startParam ?: 'monday this week');
  $end   = new DateTime($endParam   ?: 'monday next week');
} catch (Throwable $e) {
  $start = new DateTime('monday this week');
  $end   = new DateTime('monday next week');
}

// OBS: Carrega todos os estagiários e a disponibilidade de cada um.
$ests = $mysqli->query("SELECT id, nome, disponibilidade FROM estagiarios ORDER BY nome ASC");

// OBS: Pré-carrega agendamentos no intervalo para montar “ocupação por slot”.
$ag = $mysqli->prepare("
  SELECT id, paciente_id, estagiario_id, data, hora, sala
    FROM agendamentos
   WHERE data >= ? AND data < ?
");
$inicio = $start->format('Y-m-d');
$fim    = $end->format('Y-m-d');
$ag->bind_param('ss', $inicio, $fim);
$ag->execute();
$resAg = $ag->get_result();

// OBS: Hashes de ocupação por slot e por estagiário+slot.
$busyByKey = [];   // key = Y-m-d|HH:MM|estagiario_id -> agendamento
$countBySlot = []; // key = Y-m-d|HH:MM -> total salas ocupadas
while ($r = $resAg->fetch_assoc()) {
  $date = $r['data'];
  $hora = substr($r['hora'], 0, 5);
  $keySlot = $date.'|'.$hora;
  $keyEst  = $date.'|'.$hora.'|'.$r['estagiario_id'];
  $busyByKey[$keyEst] = $r;
  $countBySlot[$keySlot] = ($countBySlot[$keySlot] ?? 0) + 1;
}
$ag->close();

$events = [];

/* =========================
   1) Eventos “busy” (agendados)
   ========================= */
foreach ($busyByKey as $keyEst => $r) {
  $date = $r['data'];
  $hora = substr($r['hora'], 0, 5);
  $startIso = $date.'T'.$hora.':00';
  $endIso   = (new DateTime($startIso))->modify('+1 hour')->format('Y-m-d\TH:i:s');

  // OBS: Carrega nomes para montar o título do evento.
  $estNome = '';
  $pacNome = '';
  $qe = $mysqli->prepare("SELECT nome FROM estagiarios WHERE id=?");
  $qe->bind_param('i', $r['estagiario_id']);
  $qe->execute();
  $estNome = $qe->get_result()->fetch_column() ?: '';
  $qe->close();

  if ($r['paciente_id']) {
    $qp = $mysqli->prepare("SELECT nome FROM pacientes WHERE id=?");
    $qp->bind_param('i', $r['paciente_id']);
    $qp->execute();
    $pacNome = $qp->get_result()->fetch_column() ?: '';
    $qp->close();
  }

  $title = ($pacNome ? $pacNome.' — ' : '') . ($estNome ?: 'Estagiário') . " (Sala {$r['sala']})";

  $events[] = [
    'title' => $title,
    'start' => $startIso,
    'end'   => $endIso,
    'extendedProps' => [
      'tipo' => 'busy',
      'agendamento_id' => (int)$r['id'],
    ],
  ];
}

/* =========================
   2) Eventos “free” (janelas livres)
   =========================
   OBS: Para cada estagiário, criamos um evento por slot disponível
        (desde que não tenha batido a capacidade total de salas).
*/
if ($ests && $ests->num_rows) {
  $cursor = clone $start;
  while ($cursor < $end) {
    $ymd = $cursor->format('Y-m-d');
    $diaKey = weekday_key_for_date($ymd);

    $ests->data_seek(0);
    while ($e = $ests->fetch_assoc()) {
      $estId   = (int)$e['id'];
      $estNome = (string)$e['nome'];
      $disp    = parse_disponibilidade($e['disponibilidade']);

      $horas = $disp[$diaKey] ?? [];
      if (!$horas) continue;

      foreach ($horas as $hhmm) {
        $slotKey = $ymd.'|'.$hhmm;
        $estKey  = $ymd.'|'.$hhmm.'|'.$estId;

        // OBS: Se esse estagiário já tem agendamento nesse horário, pula.
        if (isset($busyByKey[$estKey])) continue;

        // OBS: Se a quantidade de agendamentos nesse slot >= número de salas, pula.
        $ocupadas = (int)($countBySlot[$slotKey] ?? 0);
        if ($ocupadas >= $TOTAL_SALAS) continue;

        $startIso = $ymd.'T'.$hhmm':00';
        $endIso   = (new DateTime($startIso))->modify('+1 hour')->format('Y-m-d\TH:i:s');

        $events[] = [
          'title' => "Disponível — {$estNome}",
          'start' => $startIso,
          'end'   => $endIso,
          'extendedProps' => [
            'tipo' => 'free',
            'estagiario_id' => $estId,
          ],
        ];
      }
    }

    $cursor->modify('+1 day');
  }
}

// OBS: JSON codificado sem escapar unicode para acentos corretos no front.
echo json_encode($events, JSON_UNESCAPED_UNICODE);