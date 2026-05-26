<?php
// dashboard/pacientes/pacientes.php
// Listagem com busca e modal de informações.
// OBS: Ordenamos por Nº de prontuário (CAST UNSIGNED) para manter sequência natural.
// OBS: A busca cobre Nº prontuário, Nome, CPF e Data de Nascimento.
// OBS: Ações rápidas: Info (modal), Editar, Excluir com confirmação.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;

/* ================= FORMATADORES ================= */
function fmt_tel_pac($t) {
  $d = preg_replace('/\D/', '', (string)$t);
  if (strlen($d) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $d);
  if (strlen($d) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $d);
  return $t;
}
function fmt_cpf_pac($cpf) {
  $d = preg_replace('/\D/', '', (string)$cpf);
  return strlen($d)===11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d) : $cpf;
}
function fmt_data_pac($dt) {
  if (!$dt) return '';
  $ts = strtotime($dt);
  return $ts ? date('d/m/Y', $ts) : $dt;
}

/* ================= BUSCA ================= */
$busca_nome = trim($_GET['nome'] ?? '');
$busca_np   = trim($_GET['numero_prontuario'] ?? '');
$busca_cpf  = trim($_GET['cpf'] ?? '');
$busca_nasc = trim($_GET['data_nascimento'] ?? '');

$sql = "SELECT id, numero_prontuario, nome, data_nascimento, telefone, cpf, email
        FROM pacientes WHERE 1=1";
$params = [];
$types  = '';

if ($busca_np !== '') { $sql.=" AND numero_prontuario=?"; $params[]=$busca_np; $types.='i'; }
if ($busca_nome !== '') { $sql.=" AND nome LIKE ?"; $params[]='%'.$busca_nome.'%'; $types.='s'; }
if ($busca_cpf !== '') { $sql.=" AND cpf LIKE ?"; $params[]='%'.$busca_cpf.'%'; $types.='s'; }
if ($busca_nasc !== '') { $sql.=" AND data_nascimento=?"; $params[]=$busca_nasc; $types.='s'; }

$sql .= " ORDER BY CAST(numero_prontuario AS UNSIGNED) ASC";

$st = $mysqli->prepare($sql);
if ($params) $st->bind_param($types, ...$params);
$st->execute();
$rows = $st->get_result();
?>
<style>
  .td-actions{white-space:nowrap; text-align:right;}
  .btn-icon{
    display:inline-flex; align-items:center; justify-content:center;
    width:38px; height:38px; border-radius:10px;
    text-decoration:none; color:#fff; margin-left:6px; font-weight:700;
  }
  .btn-info{background:#64748b;}
  .btn-edit{background:#0a4ea1;}
  .btn-del{background:#dc2626;}

  #infoModal{position:fixed; inset:0; display:none; z-index:5000; background:rgba(0,0,0,.45);}
  #infoBox{
    position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
    width:min(720px,92vw); max-height:85vh; overflow:auto;
    background:#fff; border-radius:16px;
  }
  #infoHead{padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between;}
  #infoTitle{font-weight:800;}
  #infoBody{padding:20px;}

  /* === CORREÇÃO DO LAYOUT DA BUSCA === */
.search-row{
  display:flex;
  gap:8px;
  margin:10px 0 18px;
  flex-wrap:wrap;
  align-items:center;
}

.search-row .input{
  height:44px;
  border:1px solid var(--border,#e5e7eb);
  border-radius:10px;
  padding:0 12px;
  width:220px;
}

.search-row .btn{
  height:44px;
  padding:0 16px;
  border:none;
  border-radius:10px;
  background:#0a4ea1;
  color:#fff;
  font-weight:700;
  cursor:pointer;
}
</style>

<div class="page-wrapper">
  <h1 class="page-title">Pacientes</h1>
  <div class="page-actions">
      <a class="add" href="<?= url('dashboard/pacientes/cadastrar_paciente.php') ?>">➕ Novo Cadastro</a>
    </div>
  </div>

  <form class="search-row" method="get">
    <input class="input" type="number" name="numero_prontuario" placeholder="Nº Prontuário" value="<?= htmlspecialchars($busca_np) ?>">
    <input class="input" type="text" name="nome" placeholder="Nome" value="<?= htmlspecialchars($busca_nome) ?>">
    <input class="input" type="text" name="cpf" placeholder="CPF" value="<?= htmlspecialchars($busca_cpf) ?>">
    <input class="input" type="date" name="data_nascimento" value="<?= htmlspecialchars($busca_nasc) ?>">
    <button class="btn">🔍 Buscar</button>
  </form>

  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>Nº Prontuário</th><th>Nome</th><th>Nascimento</th><th>Telefone</th><th>CPF</th><th>Email</th><th class="td-actions">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php while($r=$rows->fetch_assoc()): ?>
        <tr>
          <td><?= $r['numero_prontuario'] ?></td>
          <td><?= htmlspecialchars($r['nome']) ?></td>
          <td><?= fmt_data_pac($r['data_nascimento']) ?></td>
          <td><?= fmt_tel_pac($r['telefone']) ?></td>
          <td><?= fmt_cpf_pac($r['cpf']) ?></td>
          <td><?= htmlspecialchars($r['email']) ?></td>
          <td class="td-actions">
            <a class="btn-icon btn-info js-info" data-id="<?= $r['id'] ?>">ℹ️</a>
            <a class="btn-icon btn-edit" href="<?= url('dashboard/pacientes/editar_paciente.php?id='.$r['id']) ?>">✏️</a>
            <a class="btn-icon btn-del" onclick="return confirm('Excluir paciente?')" href="<?= url('dashboard/pacientes/excluir_paciente.php?id='.$r['id']) ?>">🗑️</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="infoModal">
  <div id="infoBox">
    <div id="infoHead">
      <div id="infoTitle">Informações do Paciente</div>
      <button onclick="closeModal()">✕</button>
    </div>
    <div id="infoBody"></div>
  </div>
</div>

<script>
function closeModal(){ document.getElementById('infoModal').style.display='none'; }

document.querySelectorAll('.js-info').forEach(btn=>{
  btn.onclick=()=>{
    fetch('<?= url('dashboard/pacientes/api_show.php') ?>?id='+btn.dataset.id)
      .then(r=>r.json())
      .then(j=>{
        document.getElementById('infoBody').innerHTML=j.html||'Erro';
        document.getElementById('infoModal').style.display='block';
      });
  };
});
</script>

<?php include ROOT . '/includes/footer.php'; ?>