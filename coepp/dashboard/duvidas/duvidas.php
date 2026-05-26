<?php
// dashboard/duvidas/duvidas.php
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';
?>

<style>
  .tutorial-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:24px;
    box-shadow:0 4px 16px rgba(0,0,0,.05);
    max-width:900px;
    margin:auto;
    font-size:15px;
    line-height:1.6;
  }
  .tutorial-box h2{
    font-size:28px;
    margin-top:0;
    font-weight:800;
    color:#0d3b7a;
  }
  .tutorial-box h3{
    font-size:18px;
    margin-top:20px;
    font-weight:700;
    color:#111827;
  }
  .tutorial-box ul{margin:8px 0 16px 20px;}
  .tutorial-box .btns{margin-top:28px; display:flex; gap:12px; flex-wrap:wrap;}
  .tutorial-box .btns a{
    display:inline-block;
    padding:12px 18px;
    border-radius:10px;
    background:#0a4ea1;
    color:#fff;
    text-decoration:none;
    font-weight:700;
  }
  .tutorial-box .btns a:hover{background:#083a85;}
</style>

<div class="page-wrapper">
  <div class="tutorial-box">
    <h2>📖 Tutorial Rápido – Sistema COEPP</h2>

    <p>Este guia resume as principais ações do sistema em <b>1 página</b>. Use como referência rápida no dia a dia.</p>

    <h3>👤 Pacientes</h3>
    <ul>
      <li><b>Cadastrar:</b> preencha todos os campos obrigatórios → CPF/E-mail únicos → Nº de Prontuário é automático.</li>
      <li><b>Editar:</b> ajuste dados quando necessário. RA só aparece se “Estuda FSA = Sim”.</li>
      <li><b>Excluir:</b> só possível se o paciente não tiver agendamentos futuros ativos.</li>
      <li><b>Lista de Prioridade:</b> mostra apenas pacientes preferenciais <u>sem agendamento futuro</u>. Use botão “Agendar”.</li>
    </ul>

    <h3>🎓 Estagiários</h3>
    <ul>
      <li><b>Cadastrar:</b> defina semestre (4º–8º), supervisor e disponibilidade de horários (mínimo 1).</li>
      <li><b>Editar/Excluir:</b> ajustes e remoções conforme necessidade (exclusão bloqueada se houver vínculos).</li>
    </ul>

    <h3>📅 Agendamentos</h3>
    <ul>
      <li><b>Novo:</b> escolha paciente, estagiário, data e horário → sistema evita conflitos.</li>
      <li><b>Editar:</b> altera data, hora ou estagiário (recalcula disponibilidade).</li>
      <li><b>Cancelar:</b> muda status → “cancelado”.</li>
      <li><b>Concluir:</b> muda status → “realizado” (entra no relatório).</li>
      <li><b>Excluir:</b> só em casos específicos; preferir cancelar.</li>
    </ul>

    <h3>📊 Relatórios e Painel</h3>
    <ul>
      <li><b>Painel:</b> mostra KPIs de cadastros, prioridades e consultas do dia + gráfico do mês.</li>
      <li><b>Relatório de Atendidos:</b> lista atendimentos concluídos → opção de exportar CSV/Excel.</li>
    </ul>

    <h3>⚠️ Avisos</h3>
    <ul>
      <li>Se não aparecer horário, pode estar ocupado ou fora da disponibilidade do estagiário.</li>
      <li>Preferencial não aparece na lista se já tiver agendamento ativo.</li>
      <li>Use sempre a opção <b>Sair</b> para encerrar a sessão.</li>
    </ul>

    <div class="btns">
      <a href="<?= url('Relatório Técnico.pdf') ?>" target="_blank">📑 Relatório Técnico</a>
      <a href="<?= url('Tutorial Completo.pdf') ?>" target="_blank">📘 Tutorial Completo</a>
    </div>
  </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
