<?php
// dashboard/pacientes/cadastrar_paciente.php
// Formulário de novo paciente.
// OBS: O número de prontuário é informado manualmente pelo administrador.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';
?>
<div class="page-wrapper">
  <h2 class="page-title">Cadastro de Paciente</h2>

  <style>
    .form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .form-grid .full{ grid-column: 1 / -1; }
    .form-group label{ display:block; font-size:14px; color: var(--muted,#6b7280); margin-bottom:6px; }
    .form-group .input, .form-group select, .form-group input[type="date"], .form-group textarea{
      width:100%; height:44px; border:1px solid var(--border,#e5e7eb); border-radius:10px;
      padding:0 12px; font-size:15px; background:#fff; color:var(--ink,#1f2937);
      outline:none; transition:border-color .12s ease, box-shadow .12s ease;
    }
    .form-group textarea{ height:90px; padding-top:10px; }
    .form-group .input[readonly]{ background:#f9fafb; }
    .form-group .input:focus, .form-group select:focus, .form-group input[type="date"]:focus, .form-group textarea:focus{
      border-color:#b4d0ff; box-shadow:0 0 0 3px #ecf3ff;
    }
    .hint{ font-size:12px; color:#6b7280; margin-top:6px; }
    .row-inline{ display:flex; gap:10px; align-items:center; }
    .switch { display:inline-flex; align-items:center; gap:8px; }
    .switch input{ transform:scale(1.2); }
    .form-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
    .btn{ height:46px; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; padding:0 18px; }
    .btn-primary{ background: var(--brand,#0a4ea1); color:#fff; }
    .btn-secondary{ background:#eef2f7; color:#1f2937; border:1px solid var(--border,#e5e7eb); }
    @media (max-width:900px){ .form-grid{ grid-template-columns: 1fr; } }
  </style>

  <form method="POST" action="<?= url('dashboard/pacientes/processa_paciente.php') ?>" autocomplete="off" id="form-paciente">
    <div class="form-grid">

      <!-- Nº prontuário (MANUAL) -->
      <div class="form-group">
        <label for="numero_prontuario">Nº do Prontuário*</label>
        <input class="input" type="number" id="numero_prontuario" name="numero_prontuario"
               required min="1" placeholder="Informe o número do prontuário">
        <div class="hint">Número informado manualmente pelo administrador.</div>
      </div>

      <div class="form-group full">
        <label for="nome">Nome Completo*</label>
        <input class="input" type="text" id="nome" name="nome" required>
      </div>

      <div class="form-group">
        <label for="telefone">Telefone*</label>
        <input class="input" type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" required>
      </div>

      <div class="form-group">
        <label for="cpf">CPF*</label>
        <input class="input" type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required>
      </div>

      <div class="form-group full">
        <label for="endereco">Endereço Completo*</label>
        <input class="input" type="text" id="endereco" name="endereco" required>
      </div>

      <div class="form-group">
        <label for="email">E-mail*</label>
        <input class="input" type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="data_nascimento">Data de Nascimento*</label>
        <input class="input" type="date" id="data_nascimento" name="data_nascimento" required>
      </div>

      <!-- Preferencial (checkbox + obs) -->
      <div class="form-group">
        <label>Prioridade</label>
        <div class="switch">
          <input type="checkbox" id="preferencial" name="preferencial" value="1">
          <label for="preferencial" style="user-select:none;">Caso preferencial</label>
        </div>
        <div class="hint">Marque se o paciente é preferencial. Você pode descrever o motivo no campo ao lado.</div>
      </div>

      <div class="form-group">
        <label for="preferencial_obs">Observação (prioridade)</label>
        <input class="input" type="text" id="preferencial_obs" name="preferencial_obs" placeholder="Ex.: prioridade por idade, saúde, etc. (opcional)">
      </div>

      <div class="form-group">
        <label for="estuda_fsa">Estuda na FSA?*</label>
        <select id="estuda_fsa" name="estuda_fsa" required onchange="toggleRA(this.value)">
          <option value="" disabled selected>Selecione</option>
          <option value="1">Sim</option>
          <option value="0">Não</option>
        </select>
      </div>

      <div class="form-group" id="grupo_ra" style="display:none;">
        <label for="ra">RA*</label>
        <input class="input" type="text" id="ra" name="ra" placeholder="RA do aluno (6 dígitos)" maxlength="6" pattern="\d{6}">
        <div class="hint">Digite exatamente 6 números.</div>
      </div>

      <div class="form-group">
        <label for="tipo_servico">Tipo de Serviço*</label>
        <select id="tipo_servico" name="tipo_servico" class="input" required>
          <option value="" disabled selected>Selecione</option>
          <option value="Triagem">Triagem</option>
          <option value="Acompanhamento">Acompanhamento</option>
          <option value="Terapia">Terapia</option>
        </select>
      </div>

      <div class="form-group full">
        <label for="encaminhamento">Encaminhamento</label>
        <input class="input" type="text" id="encaminhamento" name="encaminhamento" placeholder="Ex.: Escola, UBS, Demanda espontânea">
      </div>
    </div>

    <div class="form-actions">
      <a href="<?= url('dashboard/pacientes/pacientes.php') ?>" class="btn btn-secondary"
         style="display:inline-flex; align-items:center; justify-content:center; gap:8px; height:46px; padding:0 18px;">
         Cancelar
      </a>
      <button type="submit" class="btn btn-primary">Cadastrar</button>
    </div>
  </form>
</div>

<script>
  function toggleRA(val){
    const box = document.getElementById('grupo_ra');
    box.style.display = (String(val) === '1') ? 'block' : 'none';
    if (String(val) !== '1') document.getElementById('ra').value = '';
  }

  function onlyDigits(str){ return (str || '').replace(/\D+/g,''); }

  function maskCPF(v){
    v = onlyDigits(v).slice(0,11);
    if (v.length > 9)  return v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/,'$1.$2.$3-$4');
    if (v.length > 6)  return v.replace(/(\d{3})(\d{3})(\d{0,3})/,'$1.$2.$3');
    if (v.length > 3)  return v.replace(/(\d{3})(\d{0,3})/,'$1.$2');
    return v;
  }

  function maskPhone(v){
    v = onlyDigits(v).slice(0,11);
    if (v.length <= 10){
      if (v.length > 6)  return v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');
      if (v.length > 2)  return v.replace(/(\d{2})(\d{0,4})/,'($1) $2');
      return v;
    } else {
      return v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');
    }
  }

  function maskRA(v){ return onlyDigits(v).slice(0,6); }

  const cpf = document.getElementById('cpf');
  const tel = document.getElementById('telefone');
  const ra  = document.getElementById('ra');

  cpf.addEventListener('input', e => e.target.value = maskCPF(e.target.value));
  tel.addEventListener('input', e => e.target.value = maskPhone(e.target.value));
  if (ra) ra.addEventListener('input', e => e.target.value = maskRA(e.target.value));

  document.getElementById('form-paciente').addEventListener('submit', function(ev){
    const cpfDigits = onlyDigits(cpf.value);
    const telDigits = onlyDigits(tel.value);
    const raDigits  = onlyDigits(ra.value);

    if (!(cpfDigits.length === 11)){
      alert('CPF inválido. Verifique.');
      ev.preventDefault();
    } else if (!(telDigits.length === 10 || telDigits.length === 11)){
      alert('Telefone inválido. Verifique.');
      ev.preventDefault();
    } else if (document.getElementById('estuda_fsa').value === '1' && raDigits.length !== 6){
      alert('O RA deve conter exatamente 6 números.');
      ev.preventDefault();
    }
  });
</script>

<?php include ROOT . '/includes/footer.php'; ?>