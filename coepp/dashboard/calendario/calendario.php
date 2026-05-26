<?php
// dashboard/calendario/calendario.php
// Página do FullCalendar: mostra agendamentos (events.php) e disponibilidade (availability.php).
// OBS: Possui filtro por estagiário, criação rápida (dateClick) e menu contextual (editar/excluir).

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';
include ROOT . '/includes/header.php';
include ROOT . '/includes/navbar.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) { die('Conexão não disponível.'); }

$ests = $mysqli->query("SELECT id, nome FROM estagiarios ORDER BY nome ASC");
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="page-wrapper">
  <h2 class="page-title">Calendário</h2>

  <!-- OBS: Filtro de estagiário controla tanto os “events” (agendamentos)
            quanto a fonte “background” de disponibilidade. -->
  <form id="filtros" class="form-inline" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
    <select id="estagiario_id" name="estagiario_id" class="input" style="min-width:260px;">
      <option value="0">— Filtrar por estagiário —</option>
      <?php if ($ests): while($r = $ests->fetch_assoc()): ?>
        <option value="<?= (int)$r['id'] ?>"><?= e($r['nome']) ?></option>
      <?php endwhile; endif; ?>
    </select>
    <button type="button" id="aplicar" class="btn btn-primary" style="width:auto;">Aplicar filtro</button>
    <a href="<?= url('dashboard/agendamentos/novo.php') ?>" class="btn" style="width:auto; display:inline-flex; align-items:center; justify-content:center; gap:8px; height:40px; padding:0 16px;">Novo agendamento</a>
  </form>

  <div id="calendar"></div>
</div>

<!-- Menu flutuante Editar/Excluir -->
<div id="eventMenu" style="
  position:fixed; left:12px; top:12px; display:none; z-index:99999;
  background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12);
  min-width:200px; overflow:hidden;">
  <a id="menuEditar"  href="#" style="display:block; padding:10px 12px; text-decoration:none; color:#111827;">✏️ Editar</a>
  <a id="menuExcluir" href="#" style="display:block; padding:10px 12px; text-decoration:none; color:#b91c1c;">🗑️ Excluir</a>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
  :root{
    --disp-bg: #eaf2ff;
    --disp-stripe: #dce8ff;
    --disp-outline: #cfe0ff;
  }
  #calendar {
    background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px;
  }

  /* OBS: Disponibilidade pintada como “background event” listrado. */
  .fc .fc-bg-event.disp-slot {
    background: repeating-linear-gradient(
      45deg,
      var(--disp-bg),
      var(--disp-bg) 8px,
      var(--disp-stripe) 8px,
      var(--disp-stripe) 16px
    ) !important;
    opacity: 1 !important;
    border: 1px solid var(--disp-outline);
    border-left: 3px solid #0a4ea1;
    box-sizing: border-box;
  }
  .fc .fc-bg-event.disp-slot .fc-event-title { display:none; }

  .fc-event:not(.fc-bg-event) { cursor: pointer; }
  .fc-timegrid-slot:hover { background:#f8fafc; }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const estSel  = document.getElementById('estagiario_id');
    const aplicar = document.getElementById('aplicar');
    const calEl   = document.getElementById('calendar');

    // OBS: Menu contextual (abre ao clicar em um evento “busy”)
    const menu    = document.getElementById('eventMenu');
    const btnEdit = document.getElementById('menuEditar');
    const btnDel  = document.getElementById('menuExcluir');

    let ignoreNextDocClick = false;

    function clamp(val, min, max){ return Math.max(min, Math.min(max, val)); }
    function openMenu(pageX, pageY, id){
      btnEdit.href = '<?= url('dashboard/agendamentos/editar.php') ?>?id=' + encodeURIComponent(id);
      btnDel.href  = '<?= url('dashboard/agendamentos/excluir.php') ?>?id=' + encodeURIComponent(id);

      const vw = window.innerWidth, vh = window.innerHeight;
      const menuW = 220, menuH = 96; // aprox
      const left = clamp(pageX + 8, 8, vw - menuW - 8);
      const top  = clamp(pageY + 8, 8, vh - menuH - 8);

      menu.style.left = left + 'px';
      menu.style.top  = top + 'px';
      menu.style.display = 'block';

      // OBS: evita que o clique que abriu o menu também o feche imediatamente.
      ignoreNextDocClick = true;
      setTimeout(() => { ignoreNextDocClick = false; }, 0);
    }
    function closeMenu(){ menu.style.display = 'none'; }

    document.addEventListener('click', (e) => {
      if (ignoreNextDocClick) return;
      if (!menu.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });
    window.addEventListener('scroll', closeMenu, true);

    const calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'timeGridWeek',
      height: 'auto',
      slotMinTime: '08:00:00',
      slotMaxTime: '21:00:00',
      allDaySlot: false,
      nowIndicator: true,
      locale: 'pt-br',
      firstDay: 1,
      headerToolbar: { left:'prev,next today', center:'title', right:'timeGridWeek,timeGridDay,dayGridMonth' },

      // OBS: “events” carrega agendamentos concretos (ocupados).
      events: function(fetchInfo, success, failure) {
        const params = new URLSearchParams({
          start: fetchInfo.startStr.slice(0,10), // start inclusive
          end:   fetchInfo.endStr.slice(0,10),   // end exclusivo (padrão FC)
        });
        const est = (estSel.value || '0').trim();
        if (est !== '0') params.append('estagiario_id', est);

        fetch('<?= url('dashboard/calendario/events.php') ?>' + '?' + params.toString(), { credentials:'same-origin' })
          .then(r => r.json())
          .then(data => success(data))
          .catch(err => failure(err));
      },

      // OBS: “eventSources” extra (background) — disponibilidade do estagiário filtrado.
      eventSources: [{
        events: function(fetchInfo, success, failure) {
          const est = (estSel.value || '0').trim();
          if (est === '0') { success([]); return; }

          const params = new URLSearchParams({
            start: fetchInfo.startStr.slice(0,10),
            end:   fetchInfo.endStr.slice(0,10),
            estagiario_id: est
          });

          fetch('<?= url('dashboard/calendario/availability.php') ?>' + '?' + params.toString(), { credentials:'same-origin' })
            .then(r => r.json())
            .then(data => success(data))
            .catch(err => failure(err));
        }
      }],

      // OBS: Criação rápida — leva para “novo agendamento” com os parâmetros preenchidos.
      dateClick: function(info) {
        const est = (estSel.value || '0').trim();
        if (est === '0') { alert('Selecione um estagiário para criar agendamento.'); return; }
        const d = info.date;
        const yyyy = d.getFullYear();
        const mm   = String(d.getMonth()+1).padStart(2,'0');
        const dd   = String(d.getDate()).padStart(2,'0');
        const hh   = String(d.getHours()).padStart(2,'0');
        const mn   = String(d.getMinutes()).padStart(2,'0');

        const qs = new URLSearchParams({ estagiario_id: est, data: `${yyyy}-${mm}-${dd}`, hora: `${hh}:${mn}` });
        window.location.href = '<?= url('dashboard/agendamentos/novo.php') ?>' + '?' + qs.toString();
      },

      // OBS: Clique em evento “busy”: abre menu contextual.
      eventClick: function(info) {
        const ev = info.event;
        if (ev.display === 'background') return; // não abre menu para disponibilidade

        const id = ev.extendedProps?.db_id || ev.id;
        if (!id) return;

        if (info.jsEvent) {
          info.jsEvent.preventDefault?.();
          info.jsEvent.stopPropagation?.();
        }

        const x = (info.jsEvent?.pageX ?? (info.jsEvent?.clientX + window.scrollX) ?? window.innerWidth/2);
        const y = (info.jsEvent?.pageY ?? (info.jsEvent?.clientY + window.scrollY) ?? window.innerHeight/2);
        openMenu(x, y, id);
      },

      eventDidMount: function(arg){
        if (arg.event.display !== 'background') {
          arg.el.style.cursor = 'pointer';
          arg.el.title = (arg.event.title || '').trim();
        }
      }
    });

    calendar.render();

    // OBS: Refaz consultas ao aplicar filtro.
    aplicar.addEventListener('click', () => {
      closeMenu();
      calendar.refetchEvents();
      calendar.getEventSources().forEach(src => src.refetch());
    });
  });
</script>

<?php include ROOT . '/includes/footer.php'; ?>