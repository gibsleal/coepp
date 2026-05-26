<?php // includes/info_modal.php
// OBS: Drawer reutilizável para exibir detalhes de pacientes/estagiários via AJAX.
// OBS: Incluído no footer para estar disponível ao final do DOM.
?>
<style>
  .info-drawer-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.28);display:none;z-index:5000}
  .info-drawer{position:fixed;top:0;right:0;height:100vh;width:min(520px,100%);background:#fff;
    border-left:1px solid #e5e7eb;box-shadow:-8px 0 24px rgba(0,0,0,.12);transform:translateX(100%);
    transition:transform .18s ease;z-index:5001;display:flex;flex-direction:column}
  .info-drawer.open{transform:translateX(0)}
  .info-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef2f7}
  .info-title{margin:0;font-size:18px;font-weight:800;color:#0d3b7a}
  .info-close{border:none;background:#f3f4f6;border-radius:10px;padding:8px 10px;cursor:pointer}
  .info-body{padding:16px;overflow:auto}
  .kv{display:grid;grid-template-columns: 140px 1fr;gap:8px 12px;margin-bottom:10px}
  .kv .k{color:#6b7280;font-weight:700}
  .badge{display:inline-block;padding:.2rem .5rem;border-radius:999px;background:#ecfdf5;color:#065f46;font-weight:700;font-size:12px}
  .muted{color:#6b7280}
</style>

<div id="infoBackdrop" class="info-drawer-backdrop"></div>
<aside id="infoDrawer" class="info-drawer" role="dialog" aria-modal="true" aria-labelledby="infoTitle">
  <div class="info-head">
    <h3 id="infoTitle" class="info-title">Detalhes</h3>
    <button class="info-close" type="button" id="infoClose">✕</button>
  </div>
  <div id="infoBody" class="info-body">
    <div class="muted">Carregando…</div>
  </div>
</aside>

<script>
  (function(){
    // OBS: Exponibiliza window.openInfo(tipo, id) para abrir e preencher o drawer.
    const drawer  = document.getElementById('infoDrawer');
    const body    = document.getElementById('infoBody');
    const titleEl = document.getElementById('infoTitle');
    const back    = document.getElementById('infoBackdrop');
    const closeBtn= document.getElementById('infoClose');

    function openDrawer(){ back.style.display='block'; requestAnimationFrame(()=>drawer.classList.add('open')); }
    function closeDrawer(){ drawer.classList.remove('open'); back.style.display='none'; }
    closeBtn.addEventListener('click', closeDrawer);
    back.addEventListener('click', closeDrawer);
    window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeDrawer(); });

    window.openInfo = function(tipo, id){
      // OBS: Define título e endpoint conforme o tipo
      titleEl.textContent = tipo==='paciente' ? 'Ficha do Paciente' : 'Ficha do Estagiário';
      body.innerHTML = '<div class="muted">Carregando…</div>';
      openDrawer();

      const url = tipo==='paciente'
        ? '<?= url('dashboard/pacientes/api_show.php') ?>?id='+encodeURIComponent(id)
        : '<?= url('dashboard/estagiarios/api_show.php') ?>?id='+encodeURIComponent(id);

      fetch(url, {credentials:'same-origin'})
        .then(r=>r.json())
        .then(j=>{
          if(!j || j.error){ body.innerHTML = '<div class="muted">Não foi possível carregar.</div>'; return; }
          // OBS: renderKV monta os pares chave-valor do painel; ajuste conforme payload retornado.
          body.innerHTML = renderKV(tipo, j);
        })
        .catch(()=> body.innerHTML = '<div class="muted">Falha ao carregar.</div>');
    };

    function e(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }

    // OBS: Estruturas específicas por tipo; mantenha campos alinhados ao retorno da API
    function renderKV(tipo, d){
      if (tipo==='paciente'){
        return `
          <div class="kv"><div class="k">Nome</div><div>${e(d.nome)}</div></div>
          <div class="kv"><div class="k">Nº Prontuário</div><div>${e(d.numero_prontuario||'—')}</div></div>
          <div class="kv"><div class="k">CPF</div><div>${e(d.cpf||'—')}</div></div>
          <div class="kv"><div class="k">Nascimento</div><div>${e(d.data_nascimento||'—')}</div></div>
          <div class="kv"><div class="k">Telefone</div><div>${e(d.telefone||'—')}</div></div>
          <div class="kv"><div class="k">E-mail</div><div>${e(d.email||'—')}</div></div>
          <div class="kv"><div class="k">Estagiário</div><div>${e(d.estagiario||'—')}</div></div>
          <div class="kv"><div class="k">Status</div><div><span class="badge">${e(d.status||'Ativo')}</span></div></div>
        `;
      } else {
        const disp = d.disponibilidade_humana || '—';
        return `
          <div class="kv"><div class="k">Nome</div><div>${e(d.nome)}</div></div>
          <div class="kv"><div class="k">Matrícula</div><div>${e(d.matricula||'—')}</div></div>
          <div class="kv"><div class="k">Semestre</div><div>${e(d.semestre? d.semestre+'º':'—')}</div></div>
          <div class="kv"><div class="k">Supervisor</div><div>${e(d.supervisor||'—')}</div></div>
          <div class="kv"><div class="k">Tipo de Serviço</div><div>${e(d.tipo_servico||'—')}</div></div>
          <div class="kv"><div class="k">Telefone</div><div>${e(d.telefone||'—')}</div></div>
          <div class="kv"><div class="k">E-mail</div><div>${e(d.email||'—')}</div></div>
          <div class="kv"><div class="k">Disponibilidade</div><div>${e(disp)}</div></div>
        `;
      }
    }
  })();
</script>