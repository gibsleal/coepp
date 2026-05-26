<?php
// dashboard/calendario/availability.php
// Gera eventos de disponibilidade (background) no FullCalendar, para um estagiário específico.
// OBS: Este endpoint é chamado pelo calendário apenas quando um estagiário é filtrado.
//      Ele pega a disponibilidade salva em JSON, agrupa horas contíguas em blocos e
//      retorna como "background events" estilizados com a classe `disp-slot`.

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
  // OBS: Se a conexão global não estiver disponível, retornamos 500 e JSON com erro.
  http_response_code(500);
  echo json_encode(['error' => 'Conexão indisponível.']);
  exit;
}

// OBS: FullCalendar envia 'start' e 'end' no formato ISO. Aqui truncamos para YYYY-MM-DD.
//      ATENÇÃO: o 'end' do FullCalendar é EXCLUSIVO (padrão da lib).
$start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : null;
$end   = isset($_GET['end'])   ? substr($_GET['end'],   0, 10) : null;
$estId = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;

// OBS: Fallback — se o calendário não mandar intervalo, considera 30 dias a partir de hoje.
if (!$start || !$end) {
  $today = (new DateTimeImmutable('today'));
  $start = $today->format('Y-m-d');
  $end   = $today->modify('+30 days')->format('Y-m-d');
}

// OBS: Sem estagiário selecionado, não pintamos disponibilidade.
if ($estId <= 0) {
  echo json_encode([]);
  exit;
}

// OBS: Busca nome e disponibilidade do estagiário. LIMIT 1 por segurança.
$sql = "SELECT nome, disponibilidade FROM estagiarios WHERE id = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $estId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  echo json_encode([]);
  exit;
}

// OBS: Disponibilidade é um JSON com chaves de dias ("segunda", "terca"/"terça", ...).
//      Aceitamos variações com acento.
$disp = json_decode($row['disponibilidade'] ?? '{}', true);
if (!is_array($disp)) $disp = [];

// OBS: Mapeia ISO dia da semana (1=seg .. 7=dom) para possíveis chaves do JSON.
$dayKey = [
  1 => ['segunda'],
  2 => ['terca','terça'],
  3 => ['quarta'],
  4 => ['quinta'],
  5 => ['sexta'],
  6 => ['sabado','sábado'],
  7 => ['domingo'],
];

/**
 * buildBlocks
 * OBS: Recebe horas soltas (ex.: ["08:00","09:00","11:00"]) e agrupa em blocos contíguos
 *      de 60 em 60 minutos. Ex.: vira ["08:00"–"10:00"] e ["11:00"–"12:00"].
 *      Isso deixa a visualização no calendário mais limpa.
 */
function buildBlocks(array $horas): array {
  $toMin = function(string $hhmm): int {
    $h = (int)substr($hhmm,0,2);
    $m = (int)substr($hhmm,3,2);
    return $h*60 + $m;
  };

  $mins = array_map($toMin, $horas);
  sort($mins);

  $blocks = [];
  $i = 0;
  $n = count($mins);

  while ($i < $n) {
    $s = $mins[$i];       // início do bloco
    $e = $s + 60;         // próximo esperado
    $j = $i + 1;

    while ($j < $n && $mins[$j] === $e) {
      $e += 60;
      $j++;
    }

    $toHHMM = function(int $m): string {
      return sprintf('%02d:%02d', intdiv($m,60), $m%60);
    };

    $blocks[] = [$toHHMM($s), $toHHMM($e)];
    $i = $j;
  }
  return $blocks;
}

try {
  $events = [];

  // OBS: Loop diário entre $start e $end (end exclusivo).
  $cursor = new DateTimeImmutable($start);
  $limit  = new DateTimeImmutable($end);

  while ($cursor < $limit) {
    $dow  = (int)$cursor->format('N');
    $keys = $dayKey[$dow] ?? [];

    $horas = [];
    foreach ($keys as $k) {
      if (!empty($disp[$k]) && is_array($disp[$k])) {
        foreach ($disp[$k] as $h) {
          $h = substr((string)$h, 0, 5);
          if (preg_match('/^\d{2}:\d{2}$/', $h)) {
            $horas[] = $h;
          }
        }
      }
    }

    if ($horas) {
      $blocks = buildBlocks(array_unique($horas));
      foreach ($blocks as [$hStart, $hEnd]) {
        $events[] = [
          'title'        => 'Disponível',
          'start'        => $cursor->format('Y-m-d')."T{$hStart}:00",
          'end'          => $cursor->format('Y-m-d')."T{$hEnd}:00",
          'display'      => 'background',
          'overlap'      => false,
          'classNames'   => ['disp-slot'], // <- CSS
          'extendedProps'=> [
            'tipo'          => 'disponibilidade',
            'estagiario_id' => $estId,
            'estagiario'    => $row['nome'] ?? '',
          ],
        ];
      }
    }

    $cursor = $cursor->modify('+1 day');
  }

  echo json_encode($events, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // OBS: Em caso de erro inesperado, retorna 500 com detalhe (útil no console do navegador).
  http_response_code(500);
  echo json_encode([
    'error'  => 'Erro ao montar disponibilidade',
    'detail' => $e->getMessage()
  ]);
}