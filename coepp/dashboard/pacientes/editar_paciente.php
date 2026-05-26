<?php
// dashboard/pacientes/editar_paciente.php
// Formulário de edição do paciente com hidratação dos campos existentes.
// OBS: Campos booleanos 'preferencial' e 'estuda_fsa' são normalizados para int.
//      Máscaras JS para CPF/telefone e exibição condicional do RA.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0){
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=ID inválido')); exit;
}

$stmt = $mysqli->prepare("SELECT * FROM pacientes WHERE id = ? LIMIT 1");
$stmt->bind_param('i',$id);
$stmt->execute();
$pac = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pac){
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=Paciente não encontrado')); exit;
}

// normaliza ints
$pac['preferencial'] = (int)($pac['preferencial'] ?? 0);
$pac['estuda_fsa']   = (int)($pac['estuda_fsa'] ?? 0);

// normaliza tipo_servico pra comparação segura
$tipoAtual = trim((string)($pac['tipo_servico'] ?? ''));
?>
<div class="page-wrapper">
  <h2 class="page-title">Editar Paciente</h2>

  <style>
    .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-grid .full{ grid-column:1 / -1; }
    .form-group label{ display:block; font-size:14px; color:var(--muted,#6b7280); margin-bottom:6px; }
    .form-group .input, .form-group select, .form-group input[type="date"], .form-group textarea{
      width:100%; height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px;
      padding:0 12px; font-size:15px; background:#fff; color:var(--ink,#1f2937);
      outline:none; transition:border-color .12s ease, box-shadow .12s ease;
    }
    .form-group textarea{ height:90px; padding-top:10px; }
    .form-group .input:focus, .form-group select:focus, .form-group input[type="date"]:focus, .form-group textarea:focus{
      border-color:#b4d0ff; box-shadow:0 0 0 3px #ecf3ff;
    }
    .hint{ font-size:12px; color:#6b7280; margin-top:6px; }
    .form-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
    .btn{ height:46px; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; padding:0 18px; }
    .btn-primary{ background: var(--brand,#0a4ea1); color:#fff; }
    .btn-secondary{ background:#eef2f7; color:#1f2937; border:1px solid var(--border,#e5e7eb); }
    @media (max-width:900px){ .form-grid{ grid-template-columns:1fr; } }
  </style>

  <form id="form-paciente" method="POST" action="<?= url('dashboard/pacientes/atualizar_paciente.php') ?>" autocomplete="off">
    <input type="hidden" name="id" value="<?= (int)$pac['id'] ?>">

    <div class="form-grid">
      <div class="form-group">
        <label>Nº Prontuário*</label>
        <input class="input" type="text" name="numero_prontuario" required value="<?= e($pac['numero_prontuario']) ?>">
        <div class="hint">Informe o número de prontuário do paciente.</div>
      </div>

      <div class="form-group full">
        <label>Nome Completo*</label>
        <input class="input" type="text" name="nome" required value="<?= e($pac['nome']) ?>">
      </div>

      <div class="form-group">
        <label>Telefone*</label>
        <input class="input" type="text" id="telefone" name="telefone" required value="<?= e($pac['telefone']) ?>">
      </div>

      <div class="form-group">
        <label>CPF*</label>
        <input class="input" type="text" id="cpf" name="cpf" required value="<?= e($pac['cpf']) ?>">
      </div>

      <div class="form-group full">
        <label>Endereço Completo*</label>
        <input class="input" type="text" name="endereco" required value="<?= e($pac['endereco']) ?>">
      </div>

      <div class="form-group">
        <label>E-mail*</label>
        <input class="input" type="email" name="email" required value="<?= e($pac['email']) ?>">
      </div>

      <div class="form-group">
        <label>Data de Nascimento*</label>
        <input class="input" type="date" name="data_nascimento" required value="<?= e($pac['data_nascimento']) ?>">
      </div>

      <div class="form-group">
        <label>Prioridade</label>
        <div style="display:flex; gap:10px; align-items:center;">
          <input type="checkbox" id="preferencial" name="preferencial" value="1" <?= $pac['preferencial']? 'checked' : '' ?>>
          <label for="preferencial" style="user-select:none;">Caso preferencial</label>
        </div>
      </div>

      <div class="form-group">
        <label>Observação (prioridade)</label>
        <input class="input" type="text" name="preferencial_obs" value="<?= e($pac['preferencial_obs'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Estuda na FSA?*</label>
        <select class="input" name="estuda_fsa" id="estuda_fsa" required>
          <option value="0" <?= ($pac['estuda_fsa']===0?'selected':'') ?>>Não</option>
          <option value="1" <?= ($pac['estuda_fsa']===1?'selected':'') ?>>Sim</option>
        </select>
      </div>

      <div class="form-group" id="grupo_ra" style="<?= ($pac['estuda_fsa']===1?'':'display:none;') ?>">
        <label>RA*</label>
        <input class="input" type="text" id="ra" name="ra" value="<?= e($pac['ra'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Tipo de Serviço*</label>
        <select class="input" name="tipo_servico" required>
          <option value="Triagem"        <?= ($tipoAtual === 'Triagem' ? 'selected' : '') ?>>Triagem</option>
          <option value="Acompanhamento" <?= ($tipoAtual === 'Acompanhamento' ? 'selected' : '') ?>>Acompanhamento</option>
          <option value="Terapia"        <?= ($tipoAtual === 'Terapia' ? 'selected' : '') ?>>Terapia</option>
        </select>
      </div>

      <div class="form-group">
        <label>Encaminhamento</label>
        <input class="input" type="text" name="encaminhamento" value="<?= e($pac['encaminhamento']) ?>">
      </div>
    </div>

    <div class="form-actions">
      <a href="<?= url('dashboard/pacientes/pacientes.php') ?>" class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center; gap:8px; height:46px; padding:0 18px;">Cancelar</a>
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
    </div>
  </form>
</div>

<script>
  function onlyDigits(str){ return (str || '').replace(/\D+/g,''); }

  function maskCPF(v){
    v = onlyDigits(v).slice(0,11);
    if (v.length > 9)  return v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
    if (v.length > 6)  return v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
    if (v.length > 3)  return v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
    return v;
  }

  function maskPhone(v){
    v = onlyDigits(v).slice(0,11);
    if (v.length <= 10){
      if (v.length > 6)  return v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');
      if (v.length > 2)  return v.replace(/(\d{2})(\d{0,4})/,'($1) $2');
      return v;
    }
    return v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');
  }

  const cpf = document.getElementById('cpf');
  const tel = document.getElementById('telefone');
  const estuda = document.getElementById('estuda_fsa');
  const grupoRA = document.getElementById('grupo_ra');
  const ra = document.getElementById('ra');

  cpf.addEventListener('input', e => e.target.value = maskCPF(e.target.value));
  tel.addEventListener('input', e => e.target.value = maskPhone(e.target.value));
  estuda.addEventListener('change', () => {
    const show = String(estuda.value) === '1';
    grupoRA.style.display = show ? 'block' : 'none';
    if (!show && ra) ra.value = '';
  });
</script>

<?php include ROOT . '/includes/footer.php'; ?>