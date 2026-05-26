<?php
// =======================================================================
// dashboard/agendamentos/agendamentos.php
// [CONTEXTO]
//   - Lista os agendamentos com filtros (busca, estagiário, período).
//   - Paginação server-side.
//   - Ações: ver no calendário, editar, excluir.
// [DEPENDÊNCIAS]
//   - init.php (bootstrap: sessão, DB, helpers)
//   - includes/auth_guard.php (proteção de rota)
//   - includes/header.php / navbar.php / footer.php (layout)
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

// [AÇÃO] Obtém conexão ativa (fallback de segurança)
$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

// [UTIL] Escape seguro de HTML
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -------------------- Filtros --------------------
// [REGRA] Parâmetros de filtro vindos por GET
$busca         = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$estagiario_id = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;
$data_ini      = isset($_GET['data_ini']) ? trim($_GET['data_ini']) : '';
$data_fim      = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

// [REGRA] Paginação simples (20 por página)
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// [AÇÃO] Combo de estagiários (poderia ser prepared, aqui é leitura simples)
$ests = $mysqli->query("SELECT id, nome FROM estagiarios ORDER BY nome ASC");

// [AÇÃO] Monta WHERE dinâmico e parâmetros para prepared statements
$where  = [];
$params = [];
$types  = '';

if ($busca !== '') {
  // [REGRA] Busca por nome do paciente OU do estagiário
  $where[] = "(COALESCE(p.nome,'') LIKE ? OR e.nome LIKE ?)";
  $like = "%{$busca}%";
  $params[] = $like; $types .= 's';
  $params[] = $like; $types .= 's';
}
if ($estagiario_id > 0) {
  $where[] = "a.estagiario_id = ?";
  $params[] = $estagiario_id; $types .= 'i';
}
if ($data_ini !== '') {
  // [REGRA] Filtra a partir da data inicial (coluna 'data' é DATE)
  $where[] = "a.data >= ?";
  $params[] = $data_ini; $types .= 's';
}
if ($data_fim !== '') {
  // [REGRA] Filtra até a data final (inclusive)
  $where[] = "a.data <= ?";
  $params[] = $data_fim; $types .= 's';
}
$W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// -------------------- Total de registros --------------------
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM agendamentos a
  JOIN estagiarios e ON e.id = a.estagiario_id
  LEFT JOIN pacientes p ON p.id = a.paciente_id
  $W
";
$stmt = $mysqli->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// -------------------- Página de registros --------------------
$sqlList = "
  SELECT a.id, a.data, a.hora, a.sala,
         e.id AS estagiario_id, e.nome AS estagiario_nome,
         p.nome AS paciente_nome, a.paciente_id
  FROM agendamentos a
  JOIN estagiarios e ON e.id = a.estagiario_id
  LEFT JOIN pacientes p ON p.id = a.paciente_id
  $W
  ORDER BY a.data DESC, a.hora DESC
  LIMIT ? OFFSET ?
";
$stmt2 = $mysqli->prepare($sqlList);
if ($types) {
  // [AÇÃO] Acrescenta LIMIT/OFFSET no bind
  $types2  = $types . 'ii';
  $params2 = array_merge($params, [$perPage, $offset]);
  $stmt2->bind_param($types2, ...$params2);
} else {
  $stmt2->bind_param('ii', $perPage, $offset);
}
$stmt2->execute();
$rows = $stmt2->get_result();
?>
<div class="page-wrapper">
  <h2 class="page-title">Agendamentos</h2>

  <?php if (!empty($_GET['erro'])): ?>
    <!-- [UI] Alerta de erro via querystring -->
    <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 12px;border-radius:10px;margin:10px 0;">
      <?= e($_GET['erro']) ?>
    </div>
  <?php elseif (!empty($_GET['ok'])): ?>
    <!-- [UI] Alerta de sucesso genérico -->
    <div style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;padding:10px 12px;border-radius:10px;margin:10px 0;">
      Operação realizada com sucesso.
    </div>
  <?php endif; ?>

  <div class="page-actions">
    <!-- [AÇÃO] CTA para criar novo agendamento -->
    <a href="<?= url('dashboard/agendamentos/novo.php') ?>" class="btn btn-primary" style="width:auto; display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 16px;">
      <i class="fa fa-calendar-plus"></i> Iniciar agendamento
    </a>
  </div>

  <!-- [UI] Filtros de busca -->
  <form method="GET" class="form-inline"
        style="margin-bottom:16px; display:grid; grid-template-columns: 1.6fr 1fr 1fr 1fr auto; gap:10px;">
    <input type="text" name="busca" class="input" placeholder="Buscar por paciente ou estagiário" value="<?= e($busca) ?>">
    <select name="estagiario_id" class="input">
      <option value="0">Todos os estagiários</option>
      <?php if ($ests): while($r = $ests->fetch_assoc()): ?>
        <option value="<?= (int)$r['id'] ?>" <?= $estagiario_id===(int)$r['id']?'selected':'' ?>>
          <?= e($r['nome']) ?>
        </option>
      <?php endwhile; endif; ?>
    </select>
    <input type="date" name="data_ini" class="input" value="<?= e($data_ini) ?>">
    <input type="date" name="data_fim" class="input" value="<?= e($data_fim) ?>">
    <button type="submit" class="btn btn-primary" style="width:auto; padding:0 16px;">
      <i class="fa fa-search"></i> Buscar
    </button>
  </form>

  <!-- [UI] Tabela de resultados -->
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Hora</th>
          <th>Estagiário</th>
          <th>Paciente</th>
          <th>Sala</th>
          <th class="td-actions">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows && $rows->num_rows > 0): ?>
          <?php while ($r = $rows->fetch_assoc()): ?>
            <?php
              // [AÇÃO] Formatação de data/hora (defensiva)
              $data = $r['data'] ? DateTime::createFromFormat('Y-m-d', $r['data'])->format('d/m/Y') : '';
              $hora = $r['hora'] ? substr($r['hora'], 0, 5) : '';
            ?>
            <tr>
              <td class="td-tight"><?= e($data) ?></td>
              <td class="td-tight"><?= e($hora) ?></td>
              <td><?= e($r['estagiario_nome']) ?></td>
              <td><?= e($r['paciente_nome'] ?: '—') ?></td>
              <td class="td-tight"><?= e($r['sala']) ?></td>
              <td class="td-actions">
                <!-- [AÇÃO] Ver no calendário (filtra por estagiário e data) -->
                <a class="btn-table edit" title="Ver no calendário"
                   href="<?= url('dashboard/calendario/calendario.php?estagiario_id='.(int)$r['estagiario_id'].'&date='.$r['data']) ?>">
                  <i class="fa fa-calendar"></i>
                </a>

                <!-- [AÇÃO] Editar registro -->
                <a class="btn-table edit" title="Editar"
                   href="<?= url('dashboard/agendamentos/editar.php?id='.(int)$r['id']) ?>">
                  <i class="fa fa-pen"></i>
                </a>

                <!-- [AÇÃO] Excluir (confirmação no client) -->
                <a class="btn-table delete" title="Excluir"
                   href="<?= url('dashboard/agendamentos/excluir.php?id='.(int)$r['id']) ?>"
                   onclick="return confirm('Confirma excluir este agendamento?');">
                  <i class="fa fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align:center; color:#6b7280; padding:22px;">
              Nenhum agendamento encontrado.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    // [UI] Paginação (links simples à direita)
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
// [AÇÃO] Cleanup e include de footer
$stmt2->close();
include ROOT . '/includes/footer.php';