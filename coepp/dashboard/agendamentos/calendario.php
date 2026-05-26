<?php
// =======================================================================
// dashboard/agendamentos/calendario.php
// [CONTEXTO]
//   - Página com FullCalendar (sem dependências adicionais aqui).
//   - Carrega eventos a partir da API informada na opção `events`.
// [TODO]
//   - Incluir assets do FullCalendar (CSS/JS) no header global, se faltar.
//   - Lidar com select/eventClick/drag-n-drop conforme desejado.
// =======================================================================

require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<h2>Calendário</h2>
<div id="calendar"></div>
<script>
// [AÇÃO] Inicializa FullCalendar com configuração básica
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'timeGridWeek',
    locale: 'pt-br',
    slotMinTime: '08:00:00',
    slotMaxTime: '21:00:00',

    // [REGRA] Fonte de eventos (mantém sua API atual)
    events: "<?= url('dashboard/agendamentos/api_agendamentos.php') ?>",

    // [UX] Selecionável/arrastável (dependendo dos handlers de evento)
    selectable: true,
    editable: true

    // [TODO] Adicionar handlers:
    // select(info) { ... },
    // eventClick(info) { ... },
    // eventDrop/info.eventResize para atualizar no backend ...
  });
  calendar.render();
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>