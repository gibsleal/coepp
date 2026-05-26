<?php
// dashboard/estagiarios/editar_estagiario.php
// Tela para edição de estagiário + UI de disponibilidade (08:00–20:00).
// OBS: Carrega dados atuais, hidrata grade de horários e envia POST para atualizar_estagiario.php.
//      Link “Cancelar” atualmente aponta para PACIENTES (talvez queira ajustar para ESTAGIÁRIOS).

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'];
$id = (int)($_GET['id'] ?? 0);
if ($id<=0){ header('Location: '.url('dashboard/estagiarios/estagiarios.php?erro=ID inválido')); exit; }

// --- Busca registro ---
$st = $mysqli->prepare("SELECT * FROM estagiarios WHERE id=?");
$st->bind_param('i',$id); $st->execute();
$est = $st->get_result()->fetch_assoc(); $st->close();
if(!$est){ header('Location: '.url('dashboard/estagiarios/estagiarios.php?erro=Estagiário não encontrado')); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
$disp_json = $est['disponibilidade'] ?: '{}';
$tipoAtual = trim((string)($est['tipo_servico'] ?? ''));
?>

<div class="page-wrapper">
  <h2 class="page-title">Editar Estagiário</h2>

  <style>
    .form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .form-grid .full{ grid-column: 1 / -1; }
    .form-group label{ display:block; font-size:14px; color: var(--muted,#6b7280); margin-bottom:6px; }
    .form-group .input, .form-group select{
      width:100%; height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px;
      padding:0 12px; font-size:15px; background:#fff; color: var(--ink,#1f2937);
      outline:none; transition:border-color .12s ease, box-shadow .12s ease;
    }
    .form-group .input:focus, .form-group select:focus{ border-color:#b4d0ff; box-shadow:0 0 0 3px #ecf3ff; }

    .availability{ border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:16px; background:#fff; display:grid; gap:12px; }
    .availability .toolbar{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
    .availability .btn-chip{ height:34px; padding:0 12px; border-radius:999px; border:1px solid var(--border,#e5e7eb); background:#f8fafc; cursor:pointer; font-size:14px; }
    .availability .btn-chip.primary{ background: var(--brand,#0a4ea1); color:#fff; border-color:transparent; }
    .availability .btn-chip:hover{ filter:brightness(1.03); }

    .day{ border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:12px; background:#fbfdff; }
    .day-head{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .day-title{ font-weight:800; }
    .presets{ display:flex; gap:8px; flex-wrap:wrap; }
    .preset, .toggle-edit{ height:30px; padding:0 10px; border-radius:8px; border:1px solid var(--border,#e5e7eb); background:#fff; cursor:pointer; font-size:13px; }
    .slots{ display:none; margin-top:10px; }
    .slots.open{ display:block; }
    .slot-grid{ display:flex; flex-wrap:wrap; gap:8px; }
    .slot{ display:inline-flex; align-items:center; gap:6px; font-size:13px; background:#f8fafc; border:1px solid var(--border,#e5e7eb); border-radius:8px; padding:6px 8px; }

    .form-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
    .btn{ height:46px; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; padding:0 18px; }
    .btn-primary{ background: var(--brand,#0a4ea1); color:#fff; }
    .btn-secondary{ background:#eef2f7; color:#1f2937; border:1px solid var(--border,#e5e7eb); }

    @media (max-width:900px){ .form-grid{ grid-template-columns: 1fr; } }
  </style>

  <!-- OBS: POST vai para atualizar_estagiario.php (sem acentos no destino). -->
  <form method="POST" action="<?= url('dashboard/estagiarios/atualizar_estagiario.php') ?>" autocomplete="off" id="form-estagiario">
    <input type="hidden" name="id" value="<?= (int)$est['id'] ?>">

    <div class="form-grid">
      <div class="form-group full">
        <label for="nome">Nome Completo*</label>
        <input class="input" type="text" id="nome" name="nome" required value="<?= e($est['nome']) ?>">
      </div>

      <div class="form-group">
        <label for="matricula">Matrícula*</label>
        <input class="input" type="text" id="matricula" name="matricula" required value="<?= e($est['matricula']) ?>">
      </div>

      <div class="form-group">
        <label for="telefone">Telefone*</label>
        <input class="input" type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" required value="<?= e($est['telefone']) ?>">
      </div>

      <div class="form-group">
        <label for="email">E-mail Institucional*</label>
        <input class="input" type="email" id="email" name="email" required value="<?= e($est['email']) ?>">
      </div>

      <div class="form-group">
        <label for="semestre">Semestre*</label>
        <select id="semestre" name="semestre" class="input" required>
          <?php for($s=4;$s<=8;$s++): ?>
            <option value="<?= $s ?>" <?= ((int)$est['semestre']===$s)?'selected':''; ?>><?= $s ?>º</option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="supervisor">Supervisor*</label>
        <input class="input" type="text" id="supervisor" name="supervisor" required value="<?= e($est['supervisor']) ?>">
      </div>

      <div class="form-group full">
        <label for="tipo_servico">Tipo de Serviço*</label>
        <select id="tipo_servico" name="tipo_servico" class="input" required>
          <option value="" disabled <?= $tipoAtual===''?'selected':''; ?>>Selecione</option>
          <option value="Triagem"        <?= ($tipoAtual === 'Triagem' ? 'selected' : '') ?>>Triagem</option>
          <option value="Acompanhamento" <?= ($tipoAtual === 'Acompanhamento' ? 'selected' : '') ?>>Acompanhamento</option>
          <option value="Terapia"        <?= ($tipoAtual === 'Terapia' ? 'selected' : '') ?>>Terapia</option>
        </select>
      </div>

      <!-- Disponibilidade -->
      <div class="form-group full">
        <label>Disponibilidade de Horários</label>
        <div class="availability">
          <div class="toolbar">
            <button type="button" class="btn-chip primary" onclick="copyFrom('segunda')">Copiar seleção da Segunda para todos os dias</button>
            <button type="button" class="btn-chip" onclick="clearAllDays()">Limpar tudo</button>
          </div>

          <?php
            // OBS: Dias úteis + sábado. Se quiser domingo, inclua nos arrays e no hydrate.
            $dias = ["segunda","terca","quarta","quinta","sexta","sabado"];
            $rotulos = ["segunda"=>"Segunda","terca"=>"Terça","quarta"=>"Quarta","quinta"=>"Quinta","sexta"=>"Sexta","sabado"=>"Sábado"];
            $horas = []; for ($h=8; $h<=20; $h++) $horas[] = sprintf('%02d:00',$h);
            foreach ($dias as $dia):
          ?>
            <div class="day" data-day="<?= $dia ?>">
              <div class="day-head">
                <div class="day-title"><?= $rotulos[$dia] ?></div>
                <div class="presets">
                  <button type="button" class="preset" onclick="applyPreset('<?= $dia ?>','none')">Nenhum</button>
                  <button type="button" class="preset" onclick="applyPreset('<?= $dia ?>','morning')">Manhã</button>
                  <button type="button" class="preset" onclick="applyPreset('<?= $dia ?>','afternoon')">Tarde</button>
                  <button type="button" class="preset" onclick="applyPreset('<?= $dia ?>','evening')">Noite</button>
                  <button type="button" class="preset" onclick="applyPreset('<?= $dia ?>','all')">Todos</button>
                  <button type="button" class="toggle-edit" onclick="toggleEditor('<?= $dia ?>')">Editar horários</button>
                </div>
              </div>

              <div class="slots" id="slots-<?= $dia ?>">
                <div class="slot-grid">
                  <?php foreach ($horas as $hora): ?>
                    <label class="slot">
                      <input type="checkbox" class="slot-checkbox"
                           data-hour="<?= intval(substr($hora,0,2)) ?>"
                           name="disponibilidade[<?= $dia ?>][]" value="<?= $hora ?>">
                      <?= $hora ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>
      <!-- /Disponibilidade -->
    </div>

    <div class="form-actions">
      <a href="<?= url('dashboard/estagiarios/estagiarios.php') ?>" class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center; gap:8px; height:46px; padding:0 18px;">Cancelar</a>
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
    </div>
  </form>
</div>

<script>
  // Máscaras básicas
  function onlyDigits(s){ return (s||'').replace(/\D+/g,''); }
  function maskPhone(v){
    v = onlyDigits(v).slice(0,11);
    if (v.length <= 10){
      if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');
      if (v.length > 2) return v.replace(/(\d{2})(\d{0,4})/,'($1) $2');
      return v;
    }
    return v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');
  }
  document.getElementById('telefone').addEventListener('input', e => e.target.value = maskPhone(e.target.value));
  document.getElementById('matricula').addEventListener('input', e => e.target.value = onlyDigits(e.target.value));

  // Presets de disponibilidade
  const RANGES = { morning:[8,12], afternoon:[13,17], evening:[18,20], all:[8,20], none:null };
  function getSlots(day){ return document.querySelectorAll('.day[data-day="'+day+'"] input.slot-checkbox'); }
  function applyPreset(day,p){ const boxes=getSlots(day); boxes.forEach(b=>b.checked=false); if(p==='none')return; const [s,e]=RANGES[p]; boxes.forEach(b=>{const h=+b.dataset.hour; if(h>=s&&h<=e) b.checked=true;}); document.getElementById('slots-'+day).classList.add('open'); }
  function toggleEditor(day){ document.getElementById('slots-'+day).classList.toggle('open'); }
  function copyFrom(fromDay){
    const sel=[]; getSlots(fromDay).forEach(b=>b.checked && sel.push(+b.dataset.hour));
    ['segunda','terca','quarta','quinta','sexta','sabado'].forEach(d=>{
      if(d===fromDay) return;
      getSlots(d).forEach(b=>b.checked = sel.includes(+b.dataset.hour));
      document.getElementById('slots-'+d).classList.add('open');
    });
  }
  function clearAllDays(){ document.querySelectorAll('.slot-checkbox').forEach(b=>b.checked=false); }

  // Hidrata disponibilidade existente (JSON vindo do servidor)
  const DISP = <?= $disp_json ?: '{}' ?>;
  (function hydrate(){
    try{
      if (!DISP) return;
      Object.keys(DISP).forEach(day=>{
        const hours = Array.isArray(DISP[day]) ? DISP[day] : [];
        const boxes = getSlots(day);
        let any = false;
        boxes.forEach(b => { if (hours.includes(b.value)) { b.checked = true; any = true; } });
        if (any) document.getElementById('slots-'+day).classList.add('open');
      });
    }catch(e){}
  })();
</script>

<?php include ROOT . '/includes/footer.php'; ?>