'use strict';
// pdv-gestao.js

window.PageFunctions['pdv-gestao'] = function () {

  document.title = 'Gestão de PDVs | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  let timerAuto = null;
  const logEntries = [];

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function fmtPing(ts) {
    if (!ts) return 'Nunca';
    const diff = Math.floor((Date.now() - new Date(ts.replace(' ', 'T')).getTime()) / 1000);
    if (diff < 60)   return diff + 's atrás';
    if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
    return Math.floor(diff / 3600) + 'h atrás';
  }

  function addLog(numeroPdv, lojaId, mensagem) {
    const now = new Date().toLocaleTimeString('pt-BR');
    logEntries.unshift({ time: now, pdv: `${lojaId}:${numeroPdv}`, msg: mensagem });
    if (logEntries.length > 50) logEntries.pop();
    renderLog();
  }

  function renderLog() {
    if (!logEntries.length) return;
    const html = logEntries.map(e =>
      `<div class="pdvg-log-item">
        <span class="pdvg-log-time">${e.time}</span>
        <span class="pdvg-log-pdv">PDV ${e.pdv}</span>
        <span class="pdvg-log-msg">${e.msg}</span>
      </div>`
    ).join('');
    $('#pdvg-log-list').html(html);
  }

  // ── Carregar lista de lojas para filtro ──────────────────────────────────────
  fetch('/api/lojas')
    .then(r => r.json())
    .then(res => {
      (res.data || []).forEach(l => {
        $('#pdvg-filtro-loja').append(new Option(l.nome, l.id));
      });
    })
    .catch(() => {});

  // ── Carregar PDVs ────────────────────────────────────────────────────────────
  function carregarPdvs() {
    const params = new URLSearchParams();
    const loja = $('#pdvg-filtro-loja').val();
    if (loja) params.set('loja_id', loja);

    fetch(`/api/pdv/status?${params}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const pdvs = res.data || [];
        renderPdvs(pdvs);
      })
      .catch(() => {});
  }

  function renderPdvs(pdvs) {
    if (!pdvs.length) {
      $('#pdvg-grid').html(`
        <div style="grid-column:1/-1; text-align:center; padding:48px; color:var(--c-text-3);">
          <i class="bi bi-display" style="font-size:2rem; opacity:0.25;"></i>
          <p style="margin-top:12px; font-size:13px;">Nenhum PDV encontrado.</p>
        </div>`);
      return;
    }

    const html = pdvs.map(p => {
      const isOnline = p.online == 1;
      const statusClass = isOnline ? 'pdvg-card--online' : 'pdvg-card--offline';
      const dotClass    = isOnline ? 'pdvg-status-dot--online' : 'pdvg-status-dot--offline';

      return `
        <div class="pdvg-card ${statusClass}">
          <div class="pdvg-card-header">
            <div class="pdvg-status-dot ${dotClass}"></div>
            <div>
              <div class="pdvg-pdv-num"><i class="bi bi-display me-1" style="font-size:13px;"></i>PDV ${window.escHtml(p.numero_pdv)}</div>
              <div class="pdvg-loja-name"><i class="bi bi-shop me-1"></i>${window.escHtml(p.loja_nome || '—')}</div>
            </div>
            <div style="margin-left:auto;">
              ${isOnline
                ? '<span class="erp-badge badge-active">Online</span>'
                : '<span class="erp-badge badge-inactive">Offline</span>'}
            </div>
          </div>
          <div class="pdvg-card-body">
            ${p.descricao ? `<div class="pdvg-info-row"><span class="pdvg-info-label">Descrição</span><span class="pdvg-info-value">${window.escHtml(p.descricao)}</span></div>` : ''}
            <div class="pdvg-info-row">
              <span class="pdvg-info-label">Último ping</span>
              <span class="pdvg-info-value">${fmtPing(p.ultimo_ping)}</span>
            </div>
            ${p.versao_app ? `<div class="pdvg-info-row"><span class="pdvg-info-label">Versão</span><span class="pdvg-info-value" style="font-family:monospace; font-size:11px;">v${window.escHtml(p.versao_app)}</span></div>` : ''}
            ${p.url_local  ? `<div class="pdvg-info-row"><span class="pdvg-info-label">URL local</span><code style="font-size:10px; background:var(--c-bg); padding:1px 5px; border-radius:3px;">${window.escHtml(p.url_local)}</code></div>` : ''}
          </div>
          <div class="pdvg-card-footer">
            <button class="pdvg-cmd-btn pdvg-cmd-btn--green" title="Enviar carga de dados"
              onclick="enviarCarga(${p.loja_id}, '${window.escHtml(p.numero_pdv)}')">
              <i class="bi bi-cloud-download"></i> Carga
            </button>
            <button class="pdvg-cmd-btn pdvg-cmd-btn--blue" title="Enviar comanda"
              onclick="abrirEnviarComanda(${p.loja_id}, '${window.escHtml(p.numero_pdv)}')">
              <i class="bi bi-receipt"></i> Comanda
            </button>
            <button class="pdvg-cmd-btn pdvg-cmd-btn--yellow" title="Fechar caixa remoto"
              onclick="enviarComando(${p.loja_id}, '${window.escHtml(p.numero_pdv)}', 'fechar_caixa')">
              <i class="bi bi-box-arrow-right"></i> Fechar Cx
            </button>
            <button class="pdvg-cmd-btn pdvg-cmd-btn--red" title="Reiniciar PDV"
              onclick="confirmarReiniciar(${p.loja_id}, '${window.escHtml(p.numero_pdv)}')">
              <i class="bi bi-arrow-repeat"></i> Reiniciar
            </button>
          </div>
        </div>`;
    }).join('');

    $('#pdvg-grid').html(html);
  }

  // ── Enviar Carga ─────────────────────────────────────────────────────────────
  window.enviarCarga = function (lojaId, numeroPdv) {
    fetch('/api/pdv/carga', {
      headers: { 'Content-type': 'application/json' },
      method: 'POST',
      body: JSON.stringify({ loja_id: lojaId, numero_pdv: numeroPdv }),
    })
    .then(r => r.json())
    .then(data => {
      new Alerta(data.status, '', data.message);
      addLog(numeroPdv, lojaId, `Sinal de carga enviado → ${data.status}`);
    });
  };

  // ── Enviar Carga para Todos ───────────────────────────────────────────────────
  $('#btn-pdvg-carga-todos').on('click', () => {
    MODAL.openConfirmationModal({
      title: 'Enviar carga para todos os PDVs',
      message: 'Isso vai sinalizar que há uma nova carga disponível para todos os PDVs ativos. Deseja continuar?',
      confirmText: 'Enviar para todos',
      onConfirm: modal => {
        fetch('/api/pdv/status')
          .then(r => r.json())
          .then(res => {
            const pdvs = (res.data || []).filter(p => p.online == 1);
            let count  = 0;
            const reqs = pdvs.map(p =>
              fetch('/api/pdv/carga', {
                headers: { 'Content-type': 'application/json' },
                method: 'POST',
                body: JSON.stringify({ loja_id: p.loja_id, numero_pdv: p.numero_pdv }),
              }).then(() => count++)
            );
            Promise.allSettled(reqs).then(() => {
              new Alerta('success', '', `Carga enviada para ${count} PDV(s).`);
              addLog('*', '*', `Carga enviada em massa para ${count} PDV(s)`);
              modal.hide();
            });
          });
      },
    });
  });

  // ── Enviar Comando genérico ──────────────────────────────────────────────────
  window.enviarComando = function (lojaId, numeroPdv, tipo, payload = null) {
    fetch('/api/pdv/comando', {
      headers: { 'Content-type': 'application/json' },
      method: 'POST',
      body: JSON.stringify({ loja_id: lojaId, numero_pdv: numeroPdv, tipo, payload }),
    })
    .then(r => r.json())
    .then(data => {
      new Alerta(data.status, '', data.message);
      addLog(numeroPdv, lojaId, `Comando [${tipo}] → ${data.status}`);
    });
  };

  // ── Confirmar Reiniciar ──────────────────────────────────────────────────────
  window.confirmarReiniciar = function (lojaId, numeroPdv) {
    MODAL.openConfirmationModal({
      title: `Reiniciar PDV ${numeroPdv}`,
      message: `Isso vai enviar o comando de reinicialização para o PDV <strong>${numeroPdv}</strong>. O operador será notificado. Confirma?`,
      bodyHTML: true, confirmText: 'Sim, reiniciar',
      onConfirm: modal => {
        enviarComando(lojaId, numeroPdv, 'reiniciar');
        modal.hide();
      },
    });
  };

  // ── Enviar comanda para PDV específico ───────────────────────────────────────
  window.abrirEnviarComanda = function (lojaId, numeroPdv) {
    fetch('/api/comandas?status=aberta')
      .then(r => r.json())
      .then(res => {
        const comandas = res.data || [];
        if (!comandas.length) {
          new Alerta('warning', '', 'Nenhuma comanda aberta disponível para envio.');
          return;
        }
        const vals = {};
        comandas.forEach(c => { vals[c.id] = `${c.numero} — ${c.cliente_nome || 'Consumidor'}`; });

        MODAL.openFormModal({
          title: `Enviar Comanda → PDV ${numeroPdv}`,
          method: 'POST', action: '/api/comandas/enviar',
          inputs: [
            { type: 'hidden', name: 'loja_id',    value: lojaId  },
            { type: 'hidden', name: 'numero_pdv', value: numeroPdv },
            { label: 'Comanda', name: 'comanda_id', type: 'select', values: vals },
          ],
          submitText: 'Enviar',
          onSubmit: (formData, form, modal) => {
            const body = {};
            formData.forEach((v, k) => { body[k] = v; });
            fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'POST', body: JSON.stringify(body) })
              .then(r => r.json())
              .then(data => {
                new Alerta(data.status, '', data.message);
                addLog(numeroPdv, lojaId, `Comanda enviada: ${data.status}`);
                if (data.status === 'success') modal.hide();
              });
          },
        });
      });
  };

  // ── Filtro + Reload ───────────────────────────────────────────────────────────
  $('#pdvg-filtro-loja').on('change', carregarPdvs);
  $('#btn-reload-pdvg').on('click', carregarPdvs);

  // ── Auto-refresh 15s ──────────────────────────────────────────────────────────
  carregarPdvs();
  timerAuto = setInterval(carregarPdvs, 15000);
  window._pdvgestaoCleanup = () => clearInterval(timerAuto);
};
