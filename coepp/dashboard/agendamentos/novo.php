<?php
// =======================================================================
// novo.php
// [FUNÇÃO] Formulário para criar novo agendamento (com slots dinâmicos).
// [DETALHE] Usa timeslots.php para carregar horários disponíveis.
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Prefill vindos do calendário ou lista de prioridade
$pref_est  = isset($_GET['estagiario_id']) ? (int)$_GET['estagiario_id'] : 0;
$pref_data = isset($_GET['data']) ? trim($_GET['data']) : '';
$pref_hora = isset($_GET['hora']) ? substr(trim($_GET['hora']),0,5) : '';
$pref_pac  = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;

// Combos
$estagiarios = $mysqli->query("SELECT id, nome FROM estagiarios ORDER BY nome ASC");
$pacientes   = $mysqli->query("SELECT id, nome FROM pacientes ORDER BY nome ASC");
$salas       = $mysqli->query("SELECT id, nome FROM salas ORDER BY id ASC");

// Lê flash da sessão (erros do salvar.php)
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_err']);
?>
<div class="page-wrapper">
  <h2 class="page-title">Novo Agendamento</h2>

  <?php if ($flash_err !== ''): ?>
    <!-- [UI] Exibe erro vindo do salvamento -->
    <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 12px;border-radius:10px;margin:10px 0;">
      <?= e($flash_err) ?>
    </div>
  <?php elseif (!empty($_GET['erro'])): ?>
    <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 12px;border-radius:10px;margin:10px 0;">
      <?= e($_GET['erro']) ?>
    </div>
  <?php elseif (!empty($_GET['ok'])): ?>
    <div style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;padding:10px 12px;border-radius:10px;margin:10px 0;">
      Agendamento criado com sucesso.
    </div>
  <?php endif; ?>

  <style>
    /* [UI] Estilos do formulário */
    .card { background:#fff; border:1px solid var(--border,#e5e7eb); border-radius:12px; box-shadow:var(--shadow,0 8px 24px rgba(0,0,0,.06)); padding:20px; }
    .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .full { grid-column: 1 / -1; }
    .form-group label { display:block; font-size:14px; color:var(--muted,#6b7280); margin-bottom:6px; font-weight:700; }
    .input, select {
      width:100%; height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px;
      padding:0 12px; font-size:15px; background:#fff; color:var(--ink,#1f2937);
      outline:none; transition:border-color .12s ease, box-shadow .12s ease;
    }
    .input:focus, select:focus { border-color:#b4d0ff; box-shadow:0 0 0 3px #ecf3ff; }
    .hint { font-size:12px; color:#6b7280; margin-top:6px; }

    .actions { display:flex; justify-content:flex-end; gap:10px; margin-top:14px; }
    .btn { height:46px; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; padding:0 18px; }
    .btn-primary { background:var(--brand,#0a4ea1); color:#fff; }
    .btn-secondary { background:#eef2f7; color:#1f2937; border:1px solid var(--border,#e5e7eb); }
    .btn-secondary.inline-center { display:inline-flex; align-items:center; justify-content:center; gap:8px; }

    @media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } }
  </style>

  <!-- [AÇÃO] Envia para salvar.php -->
  <form class="card" method="POST" action="<?= url('dashboard/agendamentos/salvar.php') ?>" id="form-agendamento" autocomplete="off">
    <div class="form-grid">
      <!-- Estagiário -->
      <div class="form-group">
        <label for="estagiario_id">Estagiário*</label>
        <select id="estagiario_id" name="estagiario_id" required>
          <option value="" disabled <?= $pref_est? '' : 'selected' ?>>Selecione…</option>
          <?php if ($estagiarios): while($e = $estagiarios->fetch_assoc()): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $pref_est===(int)$e['id']?'selected':'' ?>>
              <?= e($e['nome']) ?>
            </option>
          <?php endwhile; endif; ?>
        </select>
        <div class="hint">Apenas horários livres desse estagiário serão exibidos.</div>
      </div>

      <!-- Paciente -->
      <div class="form-group">
        <label for="paciente_id">Paciente*</label>
        <select id="paciente_id" name="paciente_id" required>
          <option value="" disabled <?= $pref_pac? '' : 'selected' ?>>Selecione…</option>
          <?php if ($pacientes): while($p = $pacientes->fetch_assoc()): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $pref_pac===(int)$p['id']?'selected':'' ?>><?= e($p['nome']) ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <!-- Data -->
      <div class="form-group">
        <label for="data">Data*</label>
        <input class="input" type="date" id="data" name="data" required value="<?= e($pref_data) ?>">
      </div>

      <!-- Hora (slots dinâmicos) -->
      <div class="form-group">
        <label for="hora">Hora (apenas disponíveis)*</label>
        <select id="hora" name="hora" required disabled>
          <option value="" disabled selected>Selecione estagiário e data</option>
        </select>
      </div>

      <!-- Sala -->
      <div class="form-group">
        <label for="sala">Sala*</label>
        <select id="sala" name="sala" required>
          <?php if ($salas && $salas->num_rows): ?>
            <?php while($s = $salas->fetch_assoc()): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['nome'] ?: ('Sala '.(int)$s['id'])) ?></option>
            <?php endwhile; ?>
          <?php else: ?>
            <!-- [FALLBACK] Caso tabela de salas esteja vazia -->
            <?php for($s=1;$s<=12;$s++): ?>
              <option value="<?= $s ?>">Sala <?= $s ?></option>
            <?php endfor; ?>
          <?php endif; ?>
        </select>
      </div>

      <!-- Tipo de Serviço -->
      <div class="form-group">
        <label for="tipo_servico">Tipo de Serviço*</label>
        <select id="tipo_servico" name="tipo_servico" class="input" required>
          <option value="" disabled selected>Selecione</option>
          <option value="Triagem">Triagem</option>
          <option value="Acompanhamento">Acompanhamento</option>
          <option value="Terapia">Terapia</option>
        </select>
      </div>

      <!-- Observações (opcional) -->
      <div class="form-group full">
        <label for="obs">Observações (opcional)</label>
        <input class="input" type="text" id="obs" name="obs" placeholder="Observações do atendimento (opcional)">
      </div>
    </div>

    <div class="actions">
      <a class="btn btn-secondary inline-center" href="<?= url('dashboard/agendamentos/agendamentos.php') ?>">Cancelar</a>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </form>
</div>

<script>
  // =====================================================================
  // Carrega horários disponíveis a partir de timeslots.php
  // =====================================================================
  (function() {
    const estSel  = document.getElementById('estagiario_id');
    const dataInp = document.getElementById('data');
    const horaSel = document.getElementById('hora');

    function clearHoras() {
      horaSel.innerHTML = '<option value="" disabled selected>Selecione estagiário e data</option>';
      horaSel.disabled = true;
    }

    function fillHoras(slots, preselect) {
      horaSel.innerHTML = '';
      if (!slots || !slots.length) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Sem horários disponíveis';
        opt.disabled = true; opt.selected = true;
        horaSel.appendChild(opt);
        horaSel.disabled = true;
        return;
      }
      const ph = document.createElement('option');
      ph.value = '';
      ph.textContent = 'Selecione…';
      ph.disabled = true;
      ph.selected = true;
      horaSel.appendChild(ph);

      slots.forEach(h => {
        const o = document.createElement('option');
        o.value = h;
        o.textContent = h;
        if (preselect && preselect === h) o.selected = true;
        horaSel.appendChild(o);
      });
      horaSel.disabled = false;
    }

    function loadSlots(preselect=null) {
      const est = (estSel.value || '').trim();
      const dt  = (dataInp.value || '').trim();
      if (!est || !dt) { clearHoras(); return; }

      const params = new URLSearchParams({ estagiario_id: est, data: dt });
      fetch('<?= url('dashboard/agendamentos/timeslots.php') ?>' + '?' + params.toString(), {
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(j => fillHoras(j.slots || [], preselect))
      .catch(() => clearHoras());
    }

    estSel.addEventListener('change', () => loadSlots());
    dataInp.addEventListener('change', () => loadSlots());

    // Prefill vindo do calendário/listas
    const prefEst  = '<?= (int)$pref_est ?>';
    const prefData = '<?= e($pref_data) ?>';
    const prefHora = '<?= e($pref_hora) ?>';
    if (prefEst && prefData) {
      loadSlots(prefHora || null);
    }
  })();
</script>

<?php include ROOT . '/includes/footer.php'; ?>