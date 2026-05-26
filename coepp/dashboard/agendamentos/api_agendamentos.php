<?php
// =======================================================================
// dashboard/agendamentos/api_disponibilidade.php
// [CONTEXTO]
//   - Gera slots possíveis (início/fim) para um estagiário em uma data,
//     consultando disponibilidade e evitando choque de salas.
// [NOTA]
//   - O cabeçalho menciona api_disponibilidade, mas o arquivo está como
//     api_agendamentos.php. Nome e comentário podem ser padronizados depois.
// =======================================================================

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'];

/**
 * [AÇÃO] Retorna um array de horários tipo ['08:00','09:00',...] que
 *        o estagiário declarou para o dia informado (via JSON).
 * [REGRA]
 *   - Disponibilidade vem de estagiarios.disponibilidade (JSON por dia da semana).
 *   - Dia da semana: 0=domingo..6=sábado mapeado para chaves: domingo,...,sabado.
 */
function horariosDoDiaParaEstagiario(mysqli $db, int $estagiarioId, string $dataISO): array {
  // 0-domingo..6-sábado
  $dow = (int)date('w', strtotime($dataISO));
  $map = [0=>'domingo',1=>'segunda',2=>'terca',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sabado'];

  // [AÇÃO] Busca JSON de disponibilidade do estagiário
  $stmt = $db->prepare("SELECT disponibilidade FROM estagiarios WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $estagiarioId);
  $stmt->execute();
  $disp = $stmt->get_result()->fetch_assoc()['disponibilidade'] ?? '{}';
  $stmt->close();

  // [AÇÃO] Decodifica e extrai a lista do dia correspondente
  $arr = json_decode($disp, true);
  if (!is_array($arr)) $arr = [];
  $key = $map[$dow] ?? null;
  if (!$key || empty($arr[$key]) || !is_array($arr[$key])) return [];

  // [AÇÃO] Normaliza cada item para HH:MM (corta segundos, espaços, etc.)
  $clean = [];
  foreach ($arr[$key] as $h) {
    $h = substr(trim($h),0,5);
    if (preg_match('/^\d{2}:\d{2}$/', $h)) $clean[] = $h;
  }
  sort($clean);
  return $clean;
}

/**
 * [AÇÃO] Checa sobreposição de horários em uma sala na data informada.
 * [REGRA]
 *   - Considera intervalo (ini,fim) e status restrito a ('pendente','confirmado').
 * [WARN]
 *   - Este SQL usa colunas sala_id, hora_inicio, hora_fim e status/valores
 *     que podem não existir no esquema atual. Mantido como está por pedido.
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

// -------------------- Entrada --------------------
// [REGRA] Parâmetros esperados: estagiario_id (int), data (Y-m-d), duracao (minutos)
$estagiarioId = (int)($_GET['estagiario_id'] ?? 0);
$dataISO      = trim($_GET['data'] ?? ''); // yyyy-mm-dd
$durMin       = (int)($_GET['duracao'] ?? 60);

// [VALIDAÇÃO] Checagem simples de formato/intervalo
if ($estagiarioId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataISO) || $durMin <= 0) {
  echo json_encode(['ok'=>false, 'msg'=>'Parâmetros inválidos.']); exit;
}

// 1) [AÇÃO] Obtém os horários possíveis do estagiário para o dia
$horas = horariosDoDiaParaEstagiario($mysqli, $estagiarioId, $dataISO);
if (!$horas) {
  echo json_encode(['ok'=>true, 'slots'=>[]]); exit;
}

// 2) [AÇÃO] Para cada horário, procura alguma sala livre e monta slots inicio/fim
$res = [];

// [AÇÃO] Carrega salas disponíveis (ordenadas por id)
$salas = [];
$rs = $mysqli->query("SELECT id, nome FROM salas ORDER BY id ASC");
while ($row = $rs->fetch_assoc()) $salas[] = $row;
$rs->close();

foreach ($horas as $ini) {
  // [AÇÃO] Calcula horário final com base na duração informada (minutos)
  $ts = strtotime("$dataISO $ini:00");
  $fim = date('H:i', $ts + $durMin*60);

  // [AÇÃO] Encontra a primeira sala que não tenha choque para o intervalo
  $salaLivre = null;
  foreach ($salas as $s) {
    if (!existeChoque($mysqli, (int)$s['id'], $dataISO, $ini, $fim)) {
      $salaLivre = $s; break;
    }
  }

  // [AÇÃO] Se encontrou sala, adiciona slot no retorno
  if ($salaLivre) {
    $res[] = [
      'inicio' => $ini,
      'fim'    => $fim,
      'sala_id'=> (int)$salaLivre['id'],
      'sala'   => $salaLivre['nome']
    ];
  }
}

// -------------------- Saída --------------------
echo json_encode(['ok'=>true, 'slots'=>$res], JSON_UNESCAPED_UNICODE);