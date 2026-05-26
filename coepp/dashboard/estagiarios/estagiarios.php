<?php
// dashboard/estagiarios/estagiarios.php
// Lista de estagiários com busca, tabela scrollável, modal de informações e ações (ver/editar/excluir).
// OBS: Busca por matrícula, nome ou supervisor. Disponibilidade renderizada de forma compacta.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;

// ---- helpers de apresentação ----
function fmt_disp_est($json) {
  $disp = json_decode((string)$json, true);
  if (!is_array($disp) || !$disp) return '<span style="color:#6b7280;">Não informado</span>';
  $map = [
    'segunda'=>'Segunda','terca'=>'Terça','terça'=>'Terça','quarta'=>'Quarta',
    'quinta'=>'Quinta','sexta'=>'Sexta','sabado'=>'Sábado','sábado'=>'Sábado'
  ];
  $out = [];
  foreach ($disp as $dia => $horarios) {
    if (!is_array($horarios) || !$horarios) continue;
    $label = $map[strtolower($dia)] ?? ucfirst($dia);
    $safe  = htmlspecialchars(implode(', ', $horarios), ENT_QUOTES, 'UTF-8'); // OBS: segurança XSS
    $out[] = "<div><strong>{$label}:</strong> {$safe}</div>";
  }
  return $out ? implode('', $out) : '<span style="color:#6b7280;">Não informado</span>';
}
function fmt_tel_est($t) {
  $d = preg_replace('/\D/','',(string)$t);
  if (strlen($d)===11) return preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$d);
  if (strlen($d)===10) return preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$d);
  return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}
/** view_semestre
 * OBS: Algumas bases podem armazenar ENUM com índices (1..5).
 *      Convertemos para 4..8 para exibir “Xº”.
 */
function view_semestre($val){
  $s = (string)$val;
  if (in_array($s, ['4','5','6','7','8'], true)) return $s;
  $map = [1=>'4', 2=>'5', 3=>'6', 4=>'7', 5=>'8'];
  return $map[(int)$val] ?? '';
}

// ---- busca ----
$q = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$like = '%'.$q.'%';

if ($q !== '' && $mysqli) {
  $sql = "SELECT id, nome, matricula, telefone, email,
                 CAST(semestre AS CHAR) AS semestre_txt,
                 supervisor, tipo_servico, disponibilidade
          FROM estagiarios
          WHERE matricula LIKE ? OR nome LIKE ? OR supervisor LIKE ?
          ORDER BY nome ASC";
  $st  = $mysqli->prepare($sql);
  $st->bind_param('sss', $like, $like, $like);
  $st->execute();
  $rows = $st->get_result();
} else {
  $rows = $mysqli ? $mysqli->query("
    SELECT id, nome, matricula, telefone, email,
           CAST(semestre AS CHAR) AS semestre_txt,
           supervisor, tipo_servico, disponibilidade
    FROM estagiarios
    ORDER BY nome ASC
  ") : null;
}
?>
<style>
  .page-head{display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:10px;}
  .page-title{font-size:42px; font-weight:800; letter-spacing:.4px; margin:0;}
  .page-actions a.add{
    display:inline-flex; align-items:center; gap:8px;
    height:44px; padding:0 14px; border-radius:12px; border:1px solid var(--border,#e5e7eb);
    background:#fff; text-decoration:none; font-weight:700; color:#0a4ea1;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
  }
  .page-actions a.add:hover{ background:#f0f6ff; }

  .search-row{display:flex; gap:8px; margin:10px 0 18px;}
  .search-row .input{
    height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:0 12px; width:320px;
  }
  .search-row .btn{height:44px; padding:0 14px; border:none; border-radius:10px; background:#0a4ea1; color:#fff; font-weight:700;}

  .card{background:#fff; border:1px solid var(--border,#e5e7eb); border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.06); overflow:hidden;}

  /* ===== Área com scroll (topo + base sincronizados) ===== */
  .scroll-wrap{display:flex; flex-direction:column; gap:8px;}
  .scrollbar-top{
    height:12px; overflow-x:auto; overflow-y:hidden; position:relative; background:#f3f4f6;
    border:1px solid #e5e7eb; border-radius:999px;
  }
  .scrollbar-top .phantom{ height:1px; pointer-events:none; display:block; }
  .scroll-area{overflow-x:auto;}

  .table{width:100%; border-collapse:separate; border-spacing:0; min-width:1100px;}
  .table thead th{
    position:sticky; top:0; background:#f8fafc; border-bottom:1px solid var(--border,#e5e7eb);
    text-align:left; font-size:12px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; padding:14px;
  }
  .table tbody td{padding:16px 14px; border-bottom:1px solid var(--border,#e5e7eb); vertical-align:middle;}
  .table tbody tr:nth-child(odd){background:#fafafa;}
  .table tbody tr:hover{background:#f3f4f6;}

  .td-actions{white-space:nowrap; width:160px; text-align:right;}
  .btn-icon{
    display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px;
    border-radius:10px; text-decoration:none; color:#fff; margin-left:6px; font-weight:700;
  }
  .btn-info{background:#64748b;}
  .btn-edit{background:#0a4ea1;}
  .btn-del{background:#dc2626;}

  /* ===== Modal central (padrão unificado) ===== */
  #infoModal{position:fixed; inset:0; display:none; z-index:5000; background:rgba(0,0,0,.45);}
  #infoBox{
    position:absolute; left:50%; top:50%; transform:translate(-50%, -50%);
    width:min(720px, 92vw); max-height:85vh; overflow:auto;
    background:#fff; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,.25);
  }
  #infoHead{
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; border-bottom:1px solid #e5e7eb;
  }
  #infoTitle{font-weight:800; font-size:22px; color:#0d3b7a;}
  #infoClose{border:none; background:transparent; font-size:22px; cursor:pointer; color:#64748b;}
  #infoBody{padding:20px;}
</style>

<div class="page-wrapper">
  <div class="page-head">
    <h1 class="page-title">Estagiários</h1>
    <div class="page-actions">
      <a class="add" href="<?= url('dashboard/estagiarios/cadastrar_estagiario.php') ?>">➕ Novo Cadastro</a>
    </div>
  </div>

  <form class="search-row" method="get">
    <input class="input" type="text" name="busca" value="<?= htmlspecialchars($q) ?>"
           placeholder="Buscar por Matrícula, Nome ou Supervisor">
    <button class="btn" type="submit">🔍 Buscar</button>
  </form>

  <div class="card">
    <div class="scroll-wrap">
      <!-- barra de rolagem superior sincronizada -->
      <div class="scrollbar-top" id="scrollTop">
        <div class="phantom" id="phantomW"></div>
      </div>

      <!-- área real com a tabela -->
      <div class="scroll-area" id="scrollArea">
        <table class="table">
          <thead>
            <tr>
              <th style="width:140px;">Matrícula</th>
              <th>Nome</th>
              <th style="width:110px;">Semestre</th>
              <th>Supervisor</th>
              <th style="width:160px;">Tipo de Serviço</th>
              <th style="width:170px;">Telefone</th>
              <th>Email</th>
              <th style="min-width:360px;">Disponibilidade</th>
              <th class="td-actions">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows && $rows->num_rows): ?>
            <?php while ($r = $rows->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($r['matricula']) ?></td>
                <td><?= htmlspecialchars($r['nome']) ?></td>
                <td><?= htmlspecialchars(view_semestre($r['semestre_txt'] ?? '')) ?>º</td>
                <td><?= htmlspecialchars($r['supervisor']) ?></td>
                <td><?= htmlspecialchars($r['tipo_servico']) ?></td>
                <td><?= fmt_tel_est($r['telefone']) ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td><?= fmt_disp_est($r['disponibilidade']) ?></td>
                <td class="td-actions">
                  <a class="btn-icon btn-info js-info" href="#"
                     data-id="<?= (int)$r['id'] ?>" title="Informações">ℹ️</a>
                  <a class="btn-icon btn-edit" title="Editar"
                     href="<?= url('dashboard/estagiarios/editar_estagiario.php?id='.(int)$r['id']) ?>">✏️</a>
                  <a class="btn-icon btn-del" title="Excluir"
                     onclick="return confirm('Confirma excluir este estagiário?');"
                     href="<?= url('dashboard/estagiarios/excluir_estagiario.php?id='.(int)$r['id']) ?>">🗑️</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" style="text-align:center; color:#6b7280; padding:24px;">
                Nenhum estagiário encontrado<?= $q ? ' para “'.htmlspecialchars($q).'”' : '' ?>.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal central -->
<div id="infoModal">
  <div id="infoBox">
    <div id="infoHead">
      <div id="infoTitle">Informações do Estagiário</div>
      <button id="infoClose">✕</button>
    </div>
    <div id="infoBody"></div>
  </div>
</div>

<script>
(function(){
  // ===== Scroll sincronizado (topo x base) =====
  const scrollTop = document.getElementById('scrollTop');
  const phantomW  = document.getElementById('phantomW');
  const area     = document.getElementById('scrollArea');

  function syncPhantomWidth(){
    phantomW.style.width = area.scrollWidth + 'px';
  }
  syncPhantomWidth();
  window.addEventListener('resize', syncPhantomWidth);

  let lockA = false, lockB = false;
  scrollTop.addEventListener('scroll', () => {
    if (lockA) return;
    lockB = true;
    area.scrollLeft = scrollTop.scrollLeft;
    lockB = false;
  });
  area.addEventListener('scroll', () => {
    if (lockB) return;
    lockA = true;
    scrollTop.scrollLeft = area.scrollLeft;
    lockA = false;
  });

  // ===== Modal info =====
  const modal   = document.getElementById('infoModal');
  const bodyEl  = document.getElementById('infoBody');
  const closeBt = document.getElementById('infoClose');

  function openModal(){ modal.style.display = 'block'; }
  function closeModal(){ modal.style.display = 'none'; bodyEl.innerHTML = ''; }

  closeBt.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeModal(); });

  document.querySelectorAll('.js-info').forEach(btn=>{
    btn.addEventListener('click', function(ev){
      ev.preventDefault();
      const id = this.getAttribute('data-id');
      if(!id) return;

      fetch('<?= url('dashboard/estagiarios/api_show.php') ?>?id=' + encodeURIComponent(id), { credentials:'same-origin' })
        .then(r => r.json())
        .then(j => {
          if(j && j.ok && j.html){
            bodyEl.innerHTML = j.html;
            openModal();
          }else{
            alert(j && j.error ? j.error : 'Falha ao carregar.');
          }
        })
        .catch(()=> alert('Falha ao carregar.'));
    });
  });
})();
</script>

<?php
// OBS: Se $st existir neste escopo (quando há busca), ele já foi fechado logo após o fetch.
//      Mantemos include do footer ao final para padronização visual.
include ROOT . '/includes/footer.php';
