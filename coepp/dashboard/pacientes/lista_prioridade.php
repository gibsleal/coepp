<?php
// dashboard/pacientes/lista_prioridade.php
// Lista dividida em 2 colunas: “com prioridade” e “demais”.
// OBS: A origem da prioridade é flexível: pode vir de `pacientes.preferencial` (TINYINT 0/1)
//      ou de `pacientes.caso_preferencial` ('Sim'/'Não'). O script detecta automaticamente.
// OBS: Removemos da listagem quem já tem agendamento ativo a partir de hoje
//      (se existir `agendamentos.status`, ignoramos os "cancelado").
// OBS: Mantida a ordenação por nome; UI com contadores e botão “Agendar” por linha.

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Verifica se uma coluna existe numa tabela (safe p/ SHOW COLUMNS) */
function col_exists(mysqli $db, string $table, string $col): bool {
  // OBS: higienização básica para não permitir nomes maliciosos em SHOW COLUMNS
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $col))   return false;
  $colEsc = $db->real_escape_string($col);
  try {
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$colEsc}'");
    return ($res && $res->num_rows > 0);
  } catch (Throwable $e) { return false; }
}

/** Formatações rápidas */
function fmt_tel($t){
  $d = preg_replace('/\D/','',(string)$t);
  if (strlen($d)===11) return preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$d);
  if (strlen($d)===10) return preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$d);
  return $t;
}
function fmt_cpf($c){
  $d = preg_replace('/\D/','',(string)$c);
  return strlen($d)===11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/','$1.$2.$3-$4',$d) : $c;
}

/* ---------------- Colunas dinâmicas ---------------- */
// OBS: Descobrimos quais colunas de prioridade existem para adaptar as queries.
$temPreferencial = col_exists($mysqli, 'pacientes', 'preferencial');          // TINYINT(1) 0/1
$temCasoPref     = col_exists($mysqli, 'pacientes', 'caso_preferencial');     // 'Sim'/'Não'
$temStatusAg     = col_exists($mysqli, 'agendamentos', 'status');             // 'marcado','cancelado',...

if (!$temPreferencial && !$temCasoPref) {
  // Ajuda para criar a coluna preferencial se quiser usar essa estratégia
  ?>
  <div class="page-wrapper">
    <h2 class="page-title">Lista de Prioridade</h2>
    <div style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:12px;padding:16px;margin-top:10px;">
      <div style="font-weight:800;margin-bottom:6px;">Coluna de prioridade ausente.</div>
      <div>Crie ao menos uma das seguintes opções no banco:</div>
      <ol style="margin:8px 0 0 18px;">
        <li><code>ALTER TABLE pacientes ADD COLUMN preferencial TINYINT(1) NOT NULL DEFAULT 0 AFTER nome;</code></li>
        <li>ou utilize a já existente <code>caso_preferencial</code> com valores <code>'Sim'</code>/<code>'Não'</code>.</li>
      </ol>
      <div style="margin-top:8px;">Depois recarregue esta página.</div>
    </div>
  </div>
  <?php
  include ROOT . '/includes/footer.php';
  exit;
}

/* ---------------- Filtro: remover quem já tem agendamento ativo de hoje em diante ----------------
   Se existir coluna status, ignoramos os "cancelado".
   Se não existir, consideramos qualquer agendamento como ativo.
*/
// OBS: Usamos uma subquery NOT EXISTS para garantir performance razoável mesmo com listas grandes.
$agExclusion = "NOT EXISTS (
  SELECT 1 FROM agendamentos a
   WHERE a.paciente_id = p.id
     AND a.data >= CURDATE()";
if ($temStatusAg) {
  $agExclusion .= " AND a.status <> 'cancelado'";
}
$agExclusion .= ")";

/* ---------------- Queries ---------------- */
// OBS: Duas estratégias possíveis abaixo, selecionadas dinamicamente.
if ($temPreferencial) {
  // Prioridade por preferencial=1
  $sqlPrio = "
    SELECT p.id, p.numero_prontuario, p.nome, p.cpf, p.telefone,
           ".($temCasoPref ? "p.preferencial_obs" : "NULL AS preferencial_obs")."
      FROM pacientes p
     WHERE p.preferencial = 1
       AND $agExclusion
     ORDER BY p.nome ASC
  ";
  $sqlNorm = "
    SELECT p.id, p.numero_prontuario, p.nome, p.cpf, p.telefone
      FROM pacientes p
     WHERE (p.preferencial = 0 OR p.preferencial IS NULL)
       AND $agExclusion
     ORDER BY p.nome ASC
  ";
} else {
  // Prioridade por caso_preferencial='Sim'
  $sqlPrio = "
    SELECT p.id, p.numero_prontuario, p.nome, p.cpf, p.telefone,
           NULL AS preferencial_obs
      FROM pacientes p
     WHERE p.caso_preferencial = 'Sim'
       AND $agExclusion
     ORDER BY p.nome ASC
  ";
  $sqlNorm = "
    SELECT p.id, p.numero_prontuario, p.nome, p.cpf, p.telefone
      FROM pacientes p
     WHERE (p.caso_preferencial <> 'Sim' OR p.caso_preferencial IS NULL)
       AND $agExclusion
     ORDER BY p.nome ASC
  ";
}

$prio = $mysqli->query($sqlPrio);
$norm = $mysqli->query($sqlNorm);

/* ---------------- Estilo ---------------- */
?>
<style>
  .grid-2 {
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:18px;
  }
  @media (max-width: 1100px) {
    .grid-2 { grid-template-columns: 1fr; }
  }

  .card {
    background:#fff; border:1px solid var(--border,#e5e7eb); border-radius:16px;
    box-shadow:0 8px 24px rgba(0,0,0,.06); overflow:hidden;
  }
  .card-head {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:14px 16px; border-bottom:1px solid var(--border,#e5e7eb);
  }
  .title {
    margin:0; font-weight:800; letter-spacing:.3px; font-size:18px;
  }
  .pill {
    background:#eef2ff; color:#3730a3; padding:4px 10px; border-radius:999px; font-weight:800; font-size:12px;
    border:1px solid #dbeafe;
  }
  .head-prio { background:#f0f9ff; }    /* azul clarinho */
  .head-norm { background:#f8fafc; }    /* cinza clarinho */

  .table {
    width:100%; border-collapse:separate; border-spacing:0; min-width:720px;
  }
  .table thead th {
    position:sticky; top:0; background:#f8fafc; border-bottom:1px solid var(--border,#e5e7eb);
    text-align:left; font-size:12px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; padding:12px 14px;
  }
  .table tbody td { padding:14px; border-bottom:1px solid var(--border,#e5e7eb); vertical-align:middle; }
  .table tbody tr:nth-child(odd){ background:#fafafa; }
  .table tbody tr:hover{ background:#f3f4f6; }

  .td-actions { white-space:nowrap; text-align:right; }
  .btn {
    display:inline-flex; align-items:center; justify-content:center;
    height:38px; padding:0 12px; border-radius:10px; text-decoration:none; font-weight:700;
    border:1px solid #0a4ea1; background:#0a4ea1; color:#fff;
  }

  /* Page spacing para alinhar com header/navbar existentes */
  .page-wrapper { padding: 0 0 10px; }
</style>

<div class="page-wrapper">
  <h2 class="page-title">Lista de Prioridade</h2>

  <div class="grid-2">
    <!-- Prioridade -->
    <div class="card">
      <div class="card-head head-prio">
        <h3 class="title">Pacientes com Prioridade</h3>
        <span class="pill"><?= (int)($prio ? $prio->num_rows : 0) ?></span>
      </div>
      <div style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th style="width:140px;">Nº Prontuário</th>
              <th>Nome</th>
              <th style="width:160px;">CPF</th>
              <th style="width:160px;">Telefone</th>
              <th style="width:200px;">Obs Prioridade</th>
              <th class="td-actions" style="width:120px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($prio && $prio->num_rows): ?>
              <?php while($r = $prio->fetch_assoc()): ?>
                <tr>
                  <td><?= e($r['numero_prontuario']) ?></td>
                  <td><?= e($r['nome']) ?></td>
                  <td><?= e(fmt_cpf($r['cpf'])) ?></td>
                  <td><?= e(fmt_tel($r['telefone'])) ?></td>
                  <td><?= !empty($r['preferencial_obs']) ? e($r['preferencial_obs']) : '<span style="color:#6b7280;">—</span>' ?></td>
                  <td class="td-actions">
                    <!-- OBS: Ação direta para abrir o fluxo de agendamento com o paciente pré-selecionado -->
                    <a class="btn" href="<?= url('dashboard/agendamentos/novo.php?paciente_id='.(int)$r['id']) ?>">Agendar</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align:center; color:#6b7280; padding:22px;">
                  Nenhum paciente prioritário livre para agendar.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Demais pacientes -->
    <div class="card">
      <div class="card-head head-norm">
        <h3 class="title">Demais Pacientes</h3>
        <span class="pill"><?= (int)($norm ? $norm->num_rows : 0) ?></span>
      </div>
      <div style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th style="width:140px;">Nº Prontuário</th>
              <th>Nome</th>
              <th style="width:160px;">CPF</th>
              <th style="width:160px;">Telefone</th>
              <th class="td-actions" style="width:120px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($norm && $norm->num_rows): ?>
              <?php while($r = $norm->fetch_assoc()): ?>
                <tr>
                  <td><?= e($r['numero_prontuario']) ?></td>
                  <td><?= e($r['nome']) ?></td>
                  <td><?= e(fmt_cpf($r['cpf'])) ?></td>
                  <td><?= e(fmt_tel($r['telefone'])) ?></td>
                  <td class="td-actions">
                    <a class="btn" href="<?= url('dashboard/agendamentos/novo.php?paciente_id='.(int)$r['id']) ?>">Agendar</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align:center; color:#6b7280; padding:22px;">
                  Nenhum paciente livre para agendar.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>