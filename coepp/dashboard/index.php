<?php
// dashboard/index.php
// Painel principal (KPIs + gráfico por mês + atalhos).
// OBS: KPIs usam detecção dinâmica de colunas (p.ex. preferencial/caso_preferencial; status em agendamentos).
// OBS: Os filtros de mês/ano controlam a série do gráfico (agendamentos do mês selecionado).
// OBS: Em ambientes com alto volume, considere índices em (agendamentos.data), (pacientes.preferencial) etc.

require_once __DIR__ . '/../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

/**
 * first_existing_col
 * Retorna o primeiro nome de coluna encontrado dentre candidatos em uma tabela.
 * OBS: Usa INFORMATION_SCHEMA.COLUMNS com bind seguro e LIMIT 1.
 */
function first_existing_col(mysqli $db, string $table, array $candidates): ?string {
  $placeholders = implode(',', array_fill(0, count($candidates), '?'));
  $types = 's' . str_repeat('s', count($candidates));
  $sql = "
    SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ?
       AND COLUMN_NAME IN ($placeholders)
     LIMIT 1
  ";
  $st = $db->prepare($sql);
  $params = array_merge([$table], $candidates);
  $st->bind_param($types, ...$params);
  $st->execute();
  $res = $st->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $st->close();
  return $row ? $row['COLUMN_NAME'] : null;
}

$consultasHoje        = 0;
$consultasDesmarcadas = 0;
$cadastros            = 0;
$prioridadeCount      = 0;

/* ---------------- Filtros de mês/ano ----------------
   OBS: Mantém padrão simples com defaults (mês/ano atuais) e sanitização básica.
*/
$reqMes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$reqAno = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$reqMes = ($reqMes >= 1 && $reqMes <= 12) ? $reqMes : (int)date('n');
$reqAno = ($reqAno >= 2000 && $reqAno <= 2100) ? $reqAno : (int)date('Y');

$firstDay = sprintf('%04d-%02d-01', $reqAno, $reqMes);
$lastDay  = date('Y-m-t', strtotime($firstDay));
$today    = date('Y-m-d');

/* ---------------- KPIs ----------------
   OBS: KPIs são resilientes à ausência de tabelas/colunas (checagens SHOW TABLES / INFORMATION_SCHEMA).
*/
try {
  // total de pacientes
  $rs = $mysqli->query("SHOW TABLES LIKE 'pacientes'");
  if ($rs && $rs->num_rows) {
    $q = $mysqli->query("SELECT COUNT(*) AS c FROM pacientes");
    if ($q && $row = $q->fetch_assoc()) $cadastros = (int)$row['c'];

    // prioridade: tenta 'preferencial' (0/1), senão 'caso_preferencial' ('Sim')
    // OBS: Mantemos compatibilidade com bancos legados.
    $colPrefer = first_existing_col($mysqli, 'pacientes', ['preferencial', 'caso_preferencial']);
    if ($colPrefer) {
      if ($colPrefer === 'preferencial') {
        $q2 = $mysqli->query("SELECT COUNT(*) AS c FROM pacientes WHERE preferencial = 1");
      } else {
        $q2 = $mysqli->query("SELECT COUNT(*) AS c FROM pacientes WHERE caso_preferencial = 'Sim'");
      }
      if ($q2 && $row = $q2->fetch_assoc()) $prioridadeCount = (int)$row['c'];
    }
  }
  if ($rs) $rs->close();

  // agendamentos
  $rs = $mysqli->query("SHOW TABLES LIKE 'agendamentos'");
  if ($rs && $rs->num_rows) {
    // OBS: COUNT de hoje (data = hoje). Certifique-se do timezone do servidor/banco.
    $st = $mysqli->prepare("SELECT COUNT(*) FROM agendamentos WHERE DATE(data) = ?");
    $st->bind_param('s', $today);
    $st->execute(); $st->bind_result($consultasHoje); $st->fetch(); $st->close();

    // OBS: cancelados só são contados se existir coluna de status.
    $colStatus = first_existing_col($mysqli, 'agendamentos', ['status']);
    if ($colStatus) {
      $sql = "SELECT COUNT(*) FROM agendamentos WHERE `$colStatus` = 'cancelado'";
      $q3 = $mysqli->query($sql);
      if ($q3 && $row = $q3->fetch_row()) $consultasDesmarcadas = (int)$row[0];
    }
  }
  if ($rs) $rs->close();
} catch (Throwable $e) {
  // silencioso
}

// novo KPI: não prioritários
// OBS: Simples diferença entre total de cadastros e prioritários (proteção com max).
$semPrioridade = max(0, $cadastros - $prioridadeCount);

/* ---------------- Série do gráfico (atendimentos no mês) ----------------
   OBS: Gera labels 01..t(dias do mês) e preenche com os COUNTs retornados do agrupamento.
   OBS: Se mês atual estiver selecionado, limita série até a data de hoje para coerência visual.
*/
$labelsJS = [];
$dataJS   = [];

$temAg = $mysqli->query("SHOW TABLES LIKE 'agendamentos'");
if ($temAg && $temAg->num_rows) {
  $diasMes = (int)date('t', strtotime($firstDay));
  for ($d = 1; $d <= $diasMes; $d++) {
    $labelsJS[] = sprintf('%02d/%02d', $d, $reqMes);
    $dataJS[]   = 0;
  }

  $ate = ($lastDay > $today && $reqMes == (int)date('n') && $reqAno == (int)date('Y')) ? $today : $lastDay;

  $st = $mysqli->prepare("
    SELECT data, COUNT(*) AS c
      FROM agendamentos
     WHERE data BETWEEN ? AND ?
     GROUP BY data
     ORDER BY data
  ");
  $st->bind_param('ss', $firstDay, $ate);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    $d   = (int)date('j', strtotime($row['data']));
    $idx = $d - 1;
    if (isset($dataJS[$idx])) $dataJS[$idx] = (int)$row['c'];
  }
  $st->close();
}
if ($temAg) $temAg->close();

$labelsJSON = json_encode($labelsJS, JSON_UNESCAPED_UNICODE);
$dataJSON   = json_encode($dataJS,   JSON_UNESCAPED_UNICODE);
?>
<style>
  .cards{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap:18px; margin-bottom:20px;
  }
  .card{background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:18px; box-shadow:0 8px 24px rgba(0,0,0,.06);}
  .card h3{margin:0 0 8px; font-size:16px; color:#334155; font-weight:800;}
  .kpi{font-size:36px; font-weight:800; color:#0a4ea1;}
  .filters{display:flex; gap:10px; align-items:center; margin:8px 0 16px;}
  .filters select{height:40px; border:1px solid #e5e7eb; border-radius:10px; padding:0 10px; background:#fff;}
  .chart-card{background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:18px; box-shadow:0 8px 24px rgba(0,0,0,.06);}
</style>

<div class="page-wrapper">
  <h1 class="page-title" style="padding-top:10px;">Bem-vindo ao Painel COEPP</h1>

  <div class="cards">
    <div class="card">
      <h3>Consultas de Hoje</h3>
      <div class="kpi"><?= (int)$consultasHoje ?></div>
    </div>
    <div class="card">
      <h3>Pacientes na Lista de Prioridade</h3>
      <div class="kpi"><?= (int)$prioridadeCount ?></div>
    </div>
    <div class="card">
      <h3>Pacientes sem Prioridade</h3>
      <div class="kpi"><?= (int)$semPrioridade ?></div>
    </div>
    <div class="card">
      <h3>Quantidade de Cadastros</h3>
      <div class="kpi"><?= (int)$cadastros ?></div>
    </div>
  </div>

  <div class="chart-card">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
      <h2 style="margin:0; font-size:20px; font-weight:800;">Atendimentos realizados no mês</h2>
      <form class="filters" method="get">
        <?php $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez']; ?>
        <select name="mes">
          <?php foreach ($meses as $m => $label): ?>
            <option value="<?= $m ?>" <?= $m===$reqMes?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <select name="ano">
          <?php for ($a = date('Y')-3; $a <= date('Y')+1; $a++): ?>
            <option value="<?= $a ?>" <?= (int)$a===$reqAno?'selected':'' ?>><?= $a ?></option>
          <?php endfor; ?>
        </select>
        <button class="btn" type="submit" style="height:40px; border:none; border-radius:10px; background:#0a4ea1; color:#fff; font-weight:700; padding:0 14px;">Aplicar</button>
      </form>
    </div>

    <div style="margin-top:10px;">
      <canvas id="chartMes" height="110"></canvas>
    </div>
  </div>

  <div class="page-wrapper" style="padding:0; margin-top:20px;">
    <h2 style="margin:18px 0 10px;">Acessos Rápidos</h2>
    <ul style="line-height:1.9;">
      <li><a href="<?= url('dashboard/calendario/calendario.php') ?>">Calendário de Agendamentos</a></li>
      <li><a href="<?= url('dashboard/pacientes/pacientes.php') ?>">Pacientes</a></li>
      <li><a href="<?= url('dashboard/estagiarios/estagiarios.php') ?>">Estagiários</a></li>
      <li><a href="<?= url('dashboard/pacientes/lista_prioridade.php') ?>">Lista de Prioridade</a></li>
      <li><a href="<?= url('dashboard/agendamentos/atendidos.php') ?>">Relatório de Atendidos</a></li>
    </ul>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function () {
    // OBS: Labels/dados preenchidos em PHP; se vazio, não renderizamos o gráfico para evitar erro.
    const labels = <?= $labelsJSON ?: '[]' ?>;
    const data   = <?= $dataJSON   ?: '[]' ?>;

    const ctx = document.getElementById('chartMes');
    if (!ctx || !labels.length) return;

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Atendimentos',
          data: data,
          borderWidth: 1
        }]
      },
      options: {
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }
      }
    });
  })();
</script>

<?php include ROOT . '/includes/footer.php'; ?>