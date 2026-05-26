<?php
// =======================================================================
// dashboard/agendamentos/editar.php
// [CONTEXTO]
//   - Carrega um agendamento, exibe o formulário e salva alterações.
// [FORÇA]
//   - Compatível com variações de schema (ex.: coluna opcional `obs`).
//   - Normaliza data/hora e checa conflitos (sala/estagiário/capacidade).
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** [AÇÃO] Checa se coluna existe na tabela (valida identificadores) */
function col_exists(mysqli $db, string $table, string $col): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;   // [REGRA] evita SQL injection em identificadores
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $col))   return false;
  $colEsc = $db->real_escape_string($col);
  $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$colEsc}'";
  try {
    $res = $db->query($sql);
    return ($res && $res->num_rows > 0);
  } catch (Throwable $e) {
    return false; // [DEFENSIVO] se der erro, assume que não existe
  }
}

/** [AÇÃO] Interpreta JSON de disponibilidade aceitando nomes com/sem acento */
function parse_disponibilidade($json_raw): array {
  if ($json_raw === null || $json_raw === '') return [];
  $decoded = json_decode($json_raw, true);
  if (json_last_error() !== JSON_ERROR_NONE) $decoded = json_decode((string)$json_raw, true);
  if (!is_array($decoded)) return [];

  // [REGRA] mapeia chaves diferentes para um padrão único
  $mapKeys = [
    'segunda'=>'segunda','terça'=>'terca','terca'=>'terca','quarta'=>'quarta','quinta'=>'quinta',
    'sexta'=>'sexta','sábado'=>'sabado','sabado'=>'sabado','domingo'=>'domingo'
  ];

  $out = [];
  foreach ($decoded as $k => $v) {
    $lk = mb_strtolower((string)$k, 'UTF-8');
    $norm = $mapKeys[$lk] ?? $lk;
    if (is_array($v)) {
      $horas=[];
      foreach ($v as $h) {
        $h = trim((string)$h);
        if ($h === '') continue;
        if (preg_match('/^\d{2}:\d{2}/', $h)) $horas[] = substr($h, 0, 5); // [NORMALIZA] HH:MM(:SS) → HH:MM
      }
      $out[$norm] = array_values(array_unique($horas));
    }
  }
  return $out;
}

/** [AÇÃO] Converte data → chave do dia da semana (segunda, terca, ...) */
function weekday_key_for_date(string $ymd): string {
  $w = (int)date('w', strtotime($ymd)); // 0=Dom..6=Sab
  $map=[0=>'domingo',1=>'segunda',2=>'terca',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sabado'];
  return $map[$w] ?? 'segunda';
}

/** [AÇÃO] Normaliza hora para HH:MM */
function hhmm(string $h): string {
  $h = trim($h);
  if (preg_match('/^\d{2}:\d{2}$/', $h)) return $h;
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return substr($h,0,5);
  return ''; // [DEFENSIVO] formato inesperado → vazio
}

/** [AÇÃO] Conta salas (capacidade) */
function total_salas(mysqli $db): int {
  try {
    $r = $db->query("SELECT COUNT(*) FROM salas");
    if ($r) return (int)$r->fetch_row()[0];
  } catch (Throwable $e) {}
  return 0;
}

/** [AÇÃO] Verifica se a sala existe pelo ID */
function sala_existe(mysqli $db, int $id): bool {
  $st = $db->prepare("SELECT 1 FROM salas WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  $ex = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ex;
}

// -------------------- ID do registro alvo --------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? $id); // [REGRA] POST sobrepõe se presente
}
if ($id <= 0) {
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php'));
  exit;
}

$erro = '';
$has_obs = col_exists($mysqli, 'agendamentos', 'obs');               // [DINÂMICO] campo opcional
$permitidos_tipo = ['Triagem','Acompanhamento','Terapia'];           // [REGRA] enum tolerado no form

// -------------------- POST: atualizar --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // [COLETA]
  $paciente_id   = (int)($_POST['paciente_id']   ?? 0);
  $estagiario_id = (int)($_POST['estagiario_id'] ?? 0);
  $data          = trim($_POST['data'] ?? '');
  $hora_in       = trim($_POST['hora'] ?? '');
  $sala          = (int)($_POST['sala'] ?? 0);
  $tipo_servico  = trim($_POST['tipo_servico'] ?? '');
  $obs           = $has_obs ? trim($_POST['obs'] ?? '') : null;      // [DINÂMICO] só lê se existe

  // [NORMALIZA] dd/mm/yyyy → yyyy-mm-dd
  if ($data && preg_match('#^\d{2}/\d{2}/\d{4}$#', $data)) {
    [$d,$m,$y] = explode('/', $data);
    $data = "$y-$m-$d";
  }
  $hora = hhmm($hora_in);                 // [NORMALIZA] HH:MM(:SS) → HH:MM
  $horaFull = $hora ? ($hora . ':00') : '';// [FORMATO] campo `hora` pode armazenar HH:MM:SS

  // [VALIDAÇÃO] campos obrigatórios e domínios
  if ($paciente_id<=0 || $estagiario_id<=0 || !$data || !$hora || $sala<=0) {
    $erro = 'Preencha todos os campos obrigatórios.';
  } elseif (!in_array($tipo_servico, $permitidos_tipo, true)) {
    $erro = 'Tipo de serviço inválido.';
  } elseif (!sala_existe($mysqli, $sala)) {
    $erro = 'A sala informada não existe.';
  } else {
    // [REGRA] Disponibilidade do estagiário no dia/hora informados
    $qdisp = $mysqli->prepare("SELECT disponibilidade FROM estagiarios WHERE id=?");
    $qdisp->bind_param('i', $estagiario_id);
    $qdisp->execute();
    $disp_raw = $qdisp->get_result()->fetch_assoc()['disponibilidade'] ?? null;
    $qdisp->close();

    $disp = parse_disponibilidade($disp_raw);
    $diaKey = weekday_key_for_date($data);
    $horas_dia = $disp[$diaKey] ?? [];
    if (!in_array($hora, $horas_dia, true)) {
      $erro = "Este estagiário não possui disponibilidade em " . ucfirst($diaKey) . " às {$hora}.";
    }

    // [REGRA] Conflitos: sala, estagiário e capacidade total
    if (!$erro) {
      // Sala ocupada?
      $q1 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=? AND sala=? AND id<>?");
      $q1->bind_param('ssii', $data, $horaFull, $sala, $id);
      $q1->execute();
      $c1 = (int)$q1->get_result()->fetch_assoc()['c'];
      $q1->close();
      if ($c1>0) $erro = "Já existe agendamento na sala {$sala} para este horário.";

      // Estagiário ocupado?
      if (!$erro) {
        $q2 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=? AND estagiario_id=? AND id<>?");
        $q2->bind_param('ssii', $data, $horaFull, $estagiario_id, $id);
        $q2->execute();
        $c2 = (int)$q2->get_result()->fetch_assoc()['c'];
        $q2->close();
        if ($c2>0) $erro = "Este estagiário já possui agendamento neste horário.";
      }

      // Limite de salas por horário (capacidade total)
      if (!$erro) {
        $cap = max(0, total_salas($mysqli));
        if ($cap > 0) {
          $q3 = $mysqli->prepare("SELECT COUNT(*) c FROM agendamentos WHERE data=? AND hora=? AND id<>?");
          $q3->bind_param('ssi', $data, $horaFull, $id);
          $q3->execute();
          $c3 = (int)$q3->get_result()->fetch_assoc()['c'];
          $q3->close();
          if ($c3 >= $cap) $erro = "Limite de {$cap} salas por horário atingido.";
        }
      }

      // [AÇÃO] Atualiza (com/sem `obs`, conforme schema real)
      if (!$erro) {
        if ($has_obs) {
          $up = $mysqli->prepare("
            UPDATE agendamentos
               SET paciente_id=?, estagiario_id=?, data=?, hora=?, sala=?, tipo_servico=?, obs=?, updated_at=NOW()
             WHERE id=?");
          $up->bind_param('iississi', $paciente_id, $estagiario_id, $data, $horaFull, $sala, $tipo_servico, $obs, $id);
        } else {
          $up = $mysqli->prepare("
            UPDATE agendamentos
               SET paciente_id=?, estagiario_id=?, data=?, hora=?, sala=?, tipo_servico=?, updated_at=NOW()
             WHERE id=?");
          $up->bind_param('iissisi', $paciente_id, $estagiario_id, $data, $horaFull, $sala, $tipo_servico, $id);
        }

        if ($up->execute()) {
          $up->close();
          header('Location: ' . url('dashboard/agendamentos/agendamentos.php?ok=1'));
          exit;
        }
        $erro = 'Falha ao salvar o agendamento.';
        $up->close();
      }
    }
  }
}

// -------------------- GET: carrega dados do agendamento --------------------
$st = $mysqli->prepare("SELECT * FROM agendamentos WHERE id=?");
$st->bind_param('i', $id);
$st->execute();
$ag = $st->get_result()->fetch_assoc();
$st->close();

if (!$ag) {
  header('Location: ' . url('dashboard/agendamentos/agendamentos.php'));
  exit;
}

// [COMBOS] Estagiários, Pacientes, Salas
$estagiarios = $mysqli->query("SELECT id, nome FROM estagiarios ORDER BY nome ASC");
$pacientes   = $mysqli->query("SELECT id, nome FROM pacientes ORDER BY nome ASC");
$salas       = $mysqli->query("SELECT id, nome FROM salas ORDER BY id ASC");

// [VALORES] Mantém POST em caso de erro para não perder os dados digitados
$val_paciente   = $_POST['paciente_id']   ?? $ag['paciente_id'];
$val_estagiario = $_POST['estagiario_id'] ?? $ag['estagiario_id'];
$val_data       = $_POST['data']          ?? $ag['data'];
$val_hora       = hhmm($_POST['hora'] ?? substr($ag['hora'],0,5));
$val_sala       = $_POST['sala']          ?? $ag['sala'];
$val_tipo       = $_POST['tipo_servico']  ?? ($ag['tipo_servico'] ?? '');
$val_obs        = $has_obs ? ($_POST['obs'] ?? ($ag['obs'] ?? '')) : ''; // [DINÂMICO]

include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';
?>
<div class="page-wrapper">
  <h2 class="page-title">Editar Agendamento</h2>

  <?php if ($erro): ?>
    <!-- [UI] Alerta de erro -->
    <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 12px;border-radius:10px;margin:10px 0;">
      <?= e($erro) ?>
    </div>
  <?php endif; ?>

  <style>
    /* [UI] Estilos locais do formulário */
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

  <!-- [UI] Formulário de edição -->
  <form class="card" method="POST" action="" id="form-editar" autocomplete="off">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="form-grid">
      <!-- Estagiário -->
      <div class="form-group">
        <label for="estagiario_id">Estagiário*</label>
        <select id="estagiario_id" name="estagiario_id" required>
          <?php if ($estagiarios): while($e = $estagiarios->fetch_assoc()): ?>
            <option value="<?= (int)$e['id'] ?>" <?= ((int)$val_estagiario===(int)$e['id'])?'selected':'' ?>>
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
          <?php if ($pacientes): while($p = $pacientes->fetch_assoc()): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ((int)$val_paciente===(int)$p['id'])?'selected':'' ?>>
              <?= e($p['nome']) ?>
            </option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <!-- Data -->
      <div class="form-group">
        <label for="data">Data*</label>
        <input class="input" type="date" id="data" name="data" required value="<?= e($val_data) ?>">
      </div>

      <!-- Hora (slots dinâmicos) -->
      <div class="form-group">
        <label for="hora">Hora (apenas disponíveis)*</label>
        <select id="hora" name="hora" required>
          <!-- [UX] Pré-carrega o valor atual para não "sumir" ao abrir -->
          <option value="<?= e($val_hora) ?>" selected><?= e($val_hora) ?></option>
        </select>
        <div class="hint">Selecione o estagiário e a data para carregar os horários livres.</div>
      </div>

      <!-- Sala -->
      <div class="form-group">
        <label for="sala">Sala*</label>
        <select id="sala" name="sala" required>
          <?php if ($salas && $salas->num_rows): ?>
            <?php while($s = $salas->fetch_assoc()): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ((int)$val_sala===(int)$s['id'])?'selected':'' ?>>
                <?= e($s['nome'] ?: ('Sala '.(int)$s['id'])) ?>
              </option>
            <?php endwhile; ?>
          <?php else: ?>
            <!-- [DEFENSIVO] fallback se a tabela salas estiver vazia -->
            <?php for($s=1;$s<=12;$s++): ?>
              <option value="<?= $s ?>" <?= ((int)$val_sala===$s)?'selected':'' ?>>Sala <?= $s ?></option>
            <?php endfor; ?>
          <?php endif; ?>
        </select>
      </div>

      <!-- Tipo de Serviço -->
      <div class="form-group">
        <label for="tipo_servico">Tipo de Serviço*</label>
        <select id="tipo_servico" name="tipo_servico" class="input" required>
          <option value="Triagem"        <?= ($val_tipo==='Triagem'        ? 'selected' : '') ?>>Triagem</option>
          <option value="Acompanhamento" <?= ($val_tipo==='Acompanhamento' ? 'selected' : '') ?>>Acompanhamento</option>
          <option value="Terapia"        <?= ($val_tipo==='Terapia'        ? 'selected' : '') ?>>Terapia</option>
        </select>
      </div>

      <!-- Observações (se existir coluna) -->
      <?php if ($has_obs): ?>
      <div class="form-group full">
        <label for="obs">Observações</label>
        <input class="input" type="text" id="obs" name="obs" value="<?= e($val_obs) ?>" placeholder="Observações do atendimento (opcional)">
      </div>
      <?php endif; ?>
    </div>

    <!-- Ações -->
    <div class="actions">
      <a class="btn btn-secondary inline-center" href="<?= url('dashboard/agendamentos/agendamentos.php') ?>">Cancelar</a>
      <button class="btn btn-primary" type="submit">Salvar alterações</button>
    </div>
  </form>
</div>

<script>
  // =====================================================================
  // [JS] Carregamento dinâmico de horários com base no estagiário + data
  // - Busca em dashboard/agendamentos/timeslots.php?estagiario_id=..&data=..
  // - Mantém o horário atual caso esteja ocupado (editação de registro).
  // =====================================================================
  (function() {
    const estSel  = document.getElementById('estagiario_id');
    const dataInp = document.getElementById('data');
    const horaSel = document.getElementById('hora');
    const horaAtual = '<?= e($val_hora) ?>';

    function fillHoras(slots) {
      horaSel.innerHTML = '';
      const set = new Set(slots || []);
      if (horaAtual && !set.has(horaAtual)) {
        // [UX] Mantém opção atual marcada, indicando que está ocupada
        const cur = document.createElement('option');
        cur.value = horaAtual;
        cur.textContent = horaAtual + ' (ocupado)';
        cur.selected = true;
        horaSel.appendChild(cur);
      } else {
        // Placeholder quando não há horário atual a manter
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = 'Selecione…';
        ph.disabled = true;
        ph.selected = true;
        horaSel.appendChild(ph);
      }
      // Insere slots livres
      (slots || []).forEach(h => {
        const o = document.createElement('option');
        o.value = h;
        o.textContent = h;
        if (h === horaAtual) o.selected = true;
        horaSel.appendChild(o);
      });
      horaSel.disabled = !horaSel.options.length;
    }

    function loadSlots() {
      const est = (estSel.value || '').trim();
      const dt  = (dataInp.value || '').trim();
      if (!est || !dt) { fillHoras([]); return; }
      const params = new URLSearchParams({ estagiario_id: est, data: dt });
      fetch('<?= url('dashboard/agendamentos/timeslots.php') ?>' + '?' + params.toString(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(j => fillHoras(j.slots || []))
        .catch(() => fillHoras([]));
    }

    estSel.addEventListener('change', loadSlots);
    dataInp.addEventListener('change', loadSlots);
    loadSlots(); // carrega ao abrir
  })();
</script>

<?php include ROOT . '/includes/footer.php'; ?>