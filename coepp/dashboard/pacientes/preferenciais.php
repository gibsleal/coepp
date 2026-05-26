<?php
// dashboard/pacientes/preferenciais.php
// Lista somente pacientes marcados como preferenciais (preferencial = 1).
// OBS: A busca filtra por Nº prontuário, Nome ou CPF, mantendo a ordenação por nome.
// OBS: Esta tela é apenas de consulta — sem botões de ação por linha. Use pacientes.php para editar/excluir.
// OBS: Formatação simples de telefone e data apenas para exibição.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;

function fmt_tel($t){
  $d=preg_replace('/\D/','',(string)$t);
  if(strlen($d)===11) return preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$d);
  if(strlen($d)===10) return preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$d);
  return $t;
}
function fmt_data($dt){ $ts=strtotime($dt); return $ts?date('d/m/Y',$ts):''; }

$q = trim($_GET['busca'] ?? '');
$like = '%'.$q.'%';

// OBS: Quando há termo de busca, usamos prepared statement. Sem busca, consulta direta.
if ($mysqli){
  if ($q!==''){
    $st=$mysqli->prepare("
      SELECT id, numero_prontuario, nome, data_nascimento, telefone, cpf, email
      FROM pacientes
      WHERE preferencial=1 AND (numero_prontuario LIKE ? OR nome LIKE ? OR cpf LIKE ?)
      ORDER BY nome ASC
    ");
    $st->bind_param('sss',$like,$like,$like);
    $st->execute();
    $rows=$st->get_result();
  } else {
    $rows=$mysqli->query("
      SELECT id, numero_prontuario, nome, data_nascimento, telefone, cpf, email
      FROM pacientes
      WHERE preferencial=1
      ORDER BY nome ASC
    ");
  }
}
?>
<style>
  .page-head{display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:10px;}
  .page-title{font-size:42px; font-weight:800; letter-spacing:.4px; margin:0;}
  .search-row{display:flex; gap:8px; margin:10px 0 18px;}
  .search-row .input{height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:0 12px; width:320px;}
  .search-row .btn{height:44px; padding:0 14px; border:none; border-radius:10px; background:#0a4ea1; color:#fff; font-weight:700;}
  .card{background:#fff; border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.06); overflow:hidden;}
  .table{width:100%; border-collapse:separate; border-spacing:0; min-width:960px;}
  .table thead th{position:sticky; top:0; background:#f8fafc; border-bottom:1px solid var(--border,#e5e7eb); text-align:left; font-size:12px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; padding:14px;}
  .table tbody td{padding:16px 14px; border-bottom:1px solid var(--border,#e5e7eb); vertical-align:middle;}
  .table tbody tr:nth-child(odd){background:#fafafa;}
  .table tbody tr:hover{background:#f3f4f6;}
  .td-actions{white-space:nowrap; width:160px; text-align:right;}
  .btn-icon{display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; text-decoration:none; color:#fff; margin-left:6px; font-weight:700;}
  .btn-info{background:#64748b;}
  .btn-edit{background:#0a4ea1;}
  .btn-del{background:#dc2626;}
</style>

<div class="page-wrapper">
  <div class="page-head">
    <h1 class="page-title">Pacientes Preferenciais</h1>
  </div>

  <form class="search-row" method="get">
    <input class="input" type="text" name="busca" value="<?= htmlspecialchars($q) ?>"
           placeholder="Buscar por Nº Prontuário, Nome ou CPF">
    <button class="btn" type="submit">🔍 Buscar</button>
  </form>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="table">
        <thead>
          <tr>
            <th style="width:140px;">Nº Prontuário</th>
            <th>Nome</th>
            <th style="width:170px;">Data de Nascimento</th>
            <th style="width:180px;">Telefone</th>
            <th style="width:180px;">CPF</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
          <?php if($rows && $rows->num_rows): while($r=$rows->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['numero_prontuario']) ?></td>
              <td><?= htmlspecialchars($r['nome']) ?></td>
              <td><?= htmlspecialchars(fmt_data($r['data_nascimento'])) ?></td>
              <td><?= htmlspecialchars(fmt_tel($r['telefone'])) ?></td>
              <td><?= htmlspecialchars($r['cpf']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:24px;">Nenhum paciente preferencial encontrado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (isset($st) && $st instanceof mysqli_stmt) $st->close(); ?>
<?php include ROOT . '/includes/footer.php'; ?>