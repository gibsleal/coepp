<?php
// =======================================================================
// dashboard/agendamentos/atendidos.php
// [CONTEXTO]
//   - Lista agendamentos já realizados (passados) com filtros e paginação.
//   - Exporta CSV conforme os mesmos filtros.
// [NOTA]
//   - Usa detecção de colunas opcionais (tipo/obs) para adaptar ao schema.
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

// [AÇÃO] Conexão
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

// [UTIL] Escape HTML
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * [AÇÃO] Retorna a primeira coluna existente entre candidatas numa tabela.
 * [USO] Permite schema flexível (ex.: tipo_servico vs tipo).
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

// [AÇÃO] Resolve nomes de colunas opcionais conforme existir no DB
$col_tipo = first_existing_col($mysqli, 'agendamentos', ['tipo_servico','tipo','servico']);
$col_obs  = first_existing_col($mysqli, 'agendamentos', ['obs','observacoes','observacao']);

/* -------------------- Filtros -------------------- */
$today = date('Y-m-d');
$default_ini = date('Y-m-d', strtotime('-30 days'));

$data_ini      = isset($_GET['data_ini']) ? trim($_GET['data_ini']) : $default_ini;
$data_fim      = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : $today;
$estagiario_id = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;
$busca         = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$export        = isset($_GET['export']) ? trim($_GET['export']) : '';

$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

/* [REGRA] Considera “realizados” como a.data <= hoje */
$where  = ["a.data <= CURDATE()"];
$params = [];
$types  = '';

if ($data_ini !== '') { $where[] = "a.data >= ?"; $params[] = $data_ini; $types .= 's'; }
if ($data_fim !== '') { $where[] = "a.data <= ?"; $params[] = $data_fim; $types .= 's'; }
if ($estagiario_id > 0) { $where[] = "a.estagiario_id = ?"; $params[] = $estagiario_id; $types .= 'i'; }
if ($busca !== '') {
  $where[] = "(COALESCE(p.nome,'') LIKE ? OR COALESCE(e.nome,'') LIKE ?)";
  $like = "%{$busca}%";
  $params[] = $like; $types .= 's';
  $params[] = $like; $types .= 's';
}
$W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* [AÇÃO] Monta SELECT apenas com colunas existentes */
$select_cols = "a.id, a.data, a.hora, a.sala, e.nome AS estagiario_nome, p.nome AS paciente_nome";
if ($col_tipo) $select_cols .= ", a.`$col_tipo` AS tipo_servico";
if ($col_obs)  $select_cols .= ", a.`$col_obs`  AS obs";

/* -------------------- Exportação CSV -------------------- */
// [REGRA] Se ?export=csv, retorna arquivo e encerra (sem HTML/layout)
if ($export === 'csv') {
  $sqlCSV = "
    SELECT $select_cols
      FROM agendamentos a
      JOIN estagiarios e ON e.id = a.estagiario_id
      LEFT JOIN pacientes p ON p.id = a.paciente_id
      $W
     ORDER BY a.data DESC, a.hora DESC
  ";
  $stcsv = $mysqli->prepare($sqlCSV);
  if ($types) $stcsv->bind_param($types, ...$params);
  $stcsv->execute();
  $res = $stcsv->get_result();

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="agendamentos_realizados.csv"');

  $out = fopen('php://output', 'w');
  // [AÇÃO] BOM para Excel identificar UTF-8
  fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

  // [AÇÃO] Cabeçalho do CSV
  $headers = ['Data','Hora','Estagiário','Paciente','Sala'];
  if ($col_tipo) $headers[] = 'Tipo de Serviço';
  if ($col_obs)  $headers[] = 'Observações';
  fputcsv($out, $headers, ';');

  // [AÇÃO] Linhas do CSV
  while ($r = $res->fetch_assoc()) {
    $data = $r['data'] ? DateTime::createFromFormat('Y-m-d', $r['data'])->format('d/m/Y') : '';
    $hora = $r['hora'] ? substr($r['hora'],0,5) : '';
    $row  = [
      $data,
      $hora,
      (string)$r['estagiario_nome'],
      (string)($r['paciente_nome'] ?: '—'),
      (string)$r['sala'],
    ];
    if ($col_tipo) $row[] = (string)($r['tipo_servico'] ?? '');
    if ($col_obs)  $row[] = (string)($r['obs'] ?? '');
    fputcsv($out, $row, ';');
  }

  fclose($out);
  $stcsv->close();
  exit;
}

/* -------------------- Página HTML normal -------------------- */
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

// [AÇÃO] Combo de estagiários
$ests = $mysqli->query("SELECT id, nome FROM estagiarios ORDER BY nome ASC");

// [AÇÃO] Total para paginação
$sqlCount = "
  SELECT COUNT(*) AS total
    FROM agendamentos a
    JOIN estagiarios e ON e.id = a.estagiario_id
    LEFT JOIN pacientes p ON p.id = a.paciente_id
    $W
";
$stc = $mysqli->prepare($sqlCount);
if ($types) $stc->bind_param($types, ...$params);
$stc->execute();
$total = (int)($stc->get_result()->fetch_assoc()['total'] ?? 0);
$stc->close();

// [AÇÃO] Lista paginada
$sqlList = "
  SELECT $select_cols
    FROM agendamentos a
    JOIN estagiarios e ON e.id = a.estagiario_id
    LEFT JOIN pacientes p ON p.id = a.paciente_id
    $W
   ORDER BY a.data DESC, a.hora DESC
   LIMIT ? OFFSET ?
";
$typesList  = $types . 'ii';
$paramsList = array_merge($params, [$perPage, $offset]);

$st = $mysqli->prepare($sqlList);
$st->bind_param($typesList, ...$paramsList);
$st->execute();
$rows = $st->get_result();
?>
<style>
  /* [UI] Estilos locais da página (layout e tabela) */
  .page-head{display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:10px;}
  .page-title{font-size:42px; font-weight:800; letter-spacing:.4px; margin:0;}
  .page-actions a{
    display:inline-flex; align-items:center; gap:8px;
    height:44px; padding:0 14px; border-radius:12px; border:1px solid var(--border,#e5e7eb);
    background:#fff; text-decoration:none; font-weight:700; color:#0a4ea1;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
  }
  .page-actions a:hover{ background:#f0f6ff; }

  .search-row{display:grid; grid-template-columns: 1.4fr 1fr 1fr 1fr auto; gap:10px; margin:10px 0 18px;}
  .search-row .input, .search-row select{
    height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:0 12px;
  }
  .search-row .btn{height:44px; padding:0 14px; border:none; border-radius:10px; background:#0a4ea1; color:#fff; font-weight:700;}
  .card{background:#fff; border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.06); overflow:hidden;}
  .table{width:100%; border-collapse:separate; border-spacing:0; min-width:1100px;}
  .table thead th{
    position:sticky; top:0; background:#f8fafc; border-bottom:1px solid var(--border,#e5e7eb);
    text-align:left; font-size:12px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; padding:14px;
  }
  .table tbody td{padding:16px 14px; border-bottom:1px solid var(--border,#e5e7eb); vertical-align:middle;}
  .table tbody tr:nth-child(odd){background:#fafafa;}
  .table tbody tr:hover{background:#f3f4f6;}
  .muted{ color:#6b7280; }
</style>

<div class="page-wrapper">
  <div class="page-head">
    <h1 class="page-title">Agendamentos Realizados</h1>
    <?php
      $qs = $_GET; $qs['export'] = 'csv';
      $exportUrl = '?' . http_build_query($qs);
    ?>
    <div class="page-actions">
      <a href="<?= e($exportUrl) ?>">⬇️ Exportar CSV</a>
    </div>
  </div>

  <!-- [UI] Filtros -->
  <form class="search-row" method="get">
    <input class="input" type="text" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por paciente ou estagiário">
    <select name="estagiario_id" class="input">
      <option value="0">Todos os estagiários</option>
      <?php if ($ests): while($r = $ests->fetch_assoc()): ?>
        <option value="<?= (int)$r['id'] ?>" <?= $estagiario_id===(int)$r['id']?'selected':'' ?>>
          <?= e($r['nome']) ?>
        </option>
      <?php endwhile; endif; ?>
    </select>
    <input class="input" type="date" name="data_ini" value="<?= e($data_ini) ?>">
    <input class="input" type="date" name="data_fim" value="<?= e($data_fim) ?>">
    <button class="btn" type="submit">🔍 Buscar</button>
  </form>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="table">
        <thead>
          <tr>
            <th style="width:110px;">Data</th>
            <th style="width:90px;">Hora</th>
            <th>Estagiário</th>
            <th>Paciente</th>
            <th style="width:70px;">Sala</th>
            <?php if ($col_tipo): ?><th>Tipo de Serviço</th><?php endif; ?>
            <?php if ($col_obs):  ?><th>Observações</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows && $rows->num_rows): ?>
            <?php while ($r = $rows->fetch_assoc()):
              $data = $r['data'] ? DateTime::createFromFormat('Y-m-d', $r['data'])->format('d/m/Y') : '';
              $hora = $r['hora'] ? substr($r['hora'],0,5) : '';
            ?>
              <tr>
                <td><?= e($data) ?></td>
                <td><?= e($hora) ?></td>
                <td><?= e($r['estagiario_nome']) ?></td>
                <td><?= e($r['paciente_nome'] ?: '—') ?></td>
                <td><?= e((string)$r['sala']) ?></td>
                <?php if ($col_tipo): ?><td><?= e($r['tipo_servico'] ?? '—') ?></td><?php endif; ?>
                <?php if ($col_obs):  ?><td><?= e($r['obs'] ?? '—') ?></td><?php endif; ?>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= 5 + ($col_tipo?1:0) + ($col_obs?1:0) ?>" style="text-align:center; color:#6b7280; padding:22px;">
                Nenhum agendamento realizado no filtro informado.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
    // [UI] Paginação
    $pages = (int)ceil(max(1,$total)/$perPage);
    if ($pages > 1):
      $qs = $_GET; unset($qs['p']);
  ?>
    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
      <?php for($i=1;$i<=$pages;$i++): $qs['p']=$i; $url='?'.http_build_query($qs); ?>
        <a href="<?= e($url) ?>"
           style="display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:36px;
                  border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:0 10px; text-decoration:none;<?= $i===$page?' background:var(--brand,#0a4ea1); color:#fff;':'' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php
$st->close();
include ROOT . '/includes/footer.php';