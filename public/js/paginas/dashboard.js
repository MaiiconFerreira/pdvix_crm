'use strict';
// dashboard.js

window.PageFunctions['dashboard'] = function () {

  document.title = 'Dashboard | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ─────────────────────────────────────────────────────────────────
  const brl = v => parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const hoje = () => new Date().toISOString().slice(0, 10);

  // ── Chart.js cores ───────────────────────────────────────────────────────────
  const PGTO_CORES = {
    dinheiro:    '#22c55e',
    pix:         '#3b82f6',
    pos_debito:  '#8b5cf6',
    pos_credito: '#ec4899',
    pos_pix:     '#06b6d4',
    convenio:    '#f59e0b',
    outros:      '#94a3b8',
  };
  const PGTO_LABELS = {
    dinheiro: 'Dinheiro', pix: 'PIX', pos_debito: 'Débito POS',
    pos_credito: 'Crédito POS', pos_pix: 'PIX POS', convenio: 'Convênio', outros: 'Outros',
  };

  let chartHora    = null;
  let chartPagto   = null;
  let timerAuto    = null;

  // ── Inicializa inputs de data ────────────────────────────────────────────────
  $('#dash-data-inicio').val(hoje());
  $('#dash-data-fim').val(hoje());

  // ── Carrega tudo ─────────────────────────────────────────────────────────────
  function carregarTudo() {
    const di = $('#dash-data-inicio').val();
    const df = $('#dash-data-fim').val();
    carregarResumo(di, df);
    carregarVendasHora(di, df);
    carregarFormasPagamento(di, df);
    carregarTopProdutos(di, df);
    carregarPdvs();
  }

  // ── KPIs ─────────────────────────────────────────────────────────────────────
  function carregarResumo(di, df) {
    const params = new URLSearchParams();
    if (di) params.set('data_inicio', di);
    if (df) params.set('data_fim', df);

    fetch(`/api/dashboard/resumo?${params}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const d = res.data;
        const fat      = parseFloat(d.faturamento   || 0);
        const vendas   = parseInt(d.total_finalizadas || 0);
        const cancels  = parseInt(d.total_canceladas  || 0);
        const ticket   = parseFloat(d.ticket_medio    || 0);
        const total    = parseInt(d.total_vendas       || 0);
        const cancelPct = total > 0 ? ((cancels / total) * 100).toFixed(1) : 0;

        $('#kpi-faturamento').text(brl(fat));
        $('#kpi-ticket').html(`<span style="color:var(--c-text-3)">Ticket médio: ${brl(ticket)}</span>`);
        $('#kpi-vendas').text(vendas);
        $('#kpi-ticket-medio').html(`<span style="color:var(--c-text-3)">${total} total no período</span>`);
        $('#kpi-canceladas').text(cancels);
        $('#kpi-cancel-pct').html(
          cancels > 0
            ? `<span class="dash-kpi-delta--down">${cancelPct}% do total</span>`
            : `<span style="color:var(--c-text-3)">0% do total</span>`
        );
      })
      .catch(() => {});
  }

  // ── Gráfico: Vendas por Hora ─────────────────────────────────────────────────
  function carregarVendasHora(di, df) {
    const params = new URLSearchParams();
    if (di) params.set('data_inicio', di);
    if (df) params.set('data_fim', df);

    fetch(`/api/dashboard/vendas-hora?${params}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const rows  = res.data || [];
        const total = rows.reduce((s, r) => s + parseFloat(r.faturamento || 0), 0);
        $('#dash-vendas-hora-total').text('Total: ' + brl(total));

        const labels = Array.from({ length: 24 }, (_, i) => i + 'h');
        const fat    = Array(24).fill(0);
        const qtd    = Array(24).fill(0);
        rows.forEach(r => {
          const h = parseInt(r.hora);
          fat[h] = parseFloat(r.faturamento || 0);
          qtd[h] = parseInt(r.total_vendas  || 0);
        });

        const ctx = document.getElementById('chart-vendas-hora').getContext('2d');
        if (chartHora) chartHora.destroy();
        chartHora = new Chart(ctx, {
          type: 'bar',
          data: {
            labels,
            datasets: [
              {
                label: 'Faturamento (R$)',
                data: fat,
                backgroundColor: 'rgba(37, 99, 235, 0.18)',
                borderColor: 'rgba(37, 99, 235, 0.8)',
                borderWidth: 1.5,
                borderRadius: 4,
                yAxisID: 'y',
              },
              {
                label: 'Qtd. Vendas',
                data: qtd,
                type: 'line',
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#22c55e',
                tension: 0.4,
                fill: false,
                yAxisID: 'y1',
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
              tooltip: {
                callbacks: {
                  label: function (ctx) {
                    return ctx.datasetIndex === 0
                      ? ' ' + brl(ctx.raw)
                      : ' ' + ctx.raw + ' vendas';
                  },
                },
              },
            },
            scales: {
              y:  { position: 'left',  ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR'), font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
              y1: { position: 'right', ticks: { font: { size: 10 } }, grid: { display: false } },
              x:  { ticks: { font: { size: 10 } }, grid: { display: false } },
            },
          },
        });
      })
      .catch(() => {});
  }

  // ── Gráfico: Formas de Pagamento ─────────────────────────────────────────────
  function carregarFormasPagamento(di, df) {
    const params = new URLSearchParams();
    if (di) params.set('data_inicio', di);
    if (df) params.set('data_fim', df);

    fetch(`/api/dashboard/formas-pagamento?${params}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const rows   = res.data || [];
        if (!rows.length) return;

        const total  = rows.reduce((s, r) => s + parseFloat(r.valor_total || 0), 0);
        const labels = rows.map(r => PGTO_LABELS[r.tipo_pagamento] || r.tipo_pagamento);
        const data   = rows.map(r => parseFloat(r.valor_total || 0));
        const cores  = rows.map(r => PGTO_CORES[r.tipo_pagamento] || '#94a3b8');

        const ctx = document.getElementById('chart-pagamentos').getContext('2d');
        if (chartPagto) chartPagto.destroy();
        chartPagto = new Chart(ctx, {
          type: 'doughnut',
          data: { labels, datasets: [{ data, backgroundColor: cores, borderWidth: 2, borderColor: '#fff' }] },
          options: {
            responsive: true,
            cutout: '68%',
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: ctx => ` ${brl(ctx.raw)} (${((ctx.raw / total) * 100).toFixed(1)}%)`,
                },
              },
            },
          },
        });

        // Legenda manual
        const html = rows.map(r => {
          const pct = total > 0 ? ((parseFloat(r.valor_total) / total) * 100).toFixed(1) : 0;
          return `<div class="dash-pgto-item">
            <div class="dash-pgto-dot" style="background:${PGTO_CORES[r.tipo_pagamento] || '#94a3b8'}"></div>
            <span class="dash-pgto-label">${PGTO_LABELS[r.tipo_pagamento] || r.tipo_pagamento}</span>
            <span class="dash-pgto-value">${brl(r.valor_total)}</span>
            <span class="dash-pgto-pct">${pct}%</span>
          </div>`;
        }).join('');
        $('#dash-pagamentos-legenda').replaceWith(
          `<div id="dash-pagamentos-legenda" style="margin-top:8px;">${html}</div>`
        );
      })
      .catch(() => {});
  }

  // ── Top Produtos ─────────────────────────────────────────────────────────────
  function carregarTopProdutos(di, df) {
    const params = new URLSearchParams();
    if (di) params.set('data_inicio', di);
    if (df) params.set('data_fim', df);

    fetch(`/api/dashboard/top-produtos?${params}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const rows = res.data || [];

        if (!rows.length) {
          $('#dash-top-produtos').html(
            '<p style="text-align:center;color:var(--c-text-3);padding:24px;font-size:13px;">Nenhuma venda no período.</p>'
          );
          return;
        }

        const maxQtd = Math.max(...rows.map(r => parseFloat(r.qtd_vendida || 0)));
        const rankClass = (i) => {
          if (i === 0) return 'dash-top-rank--gold';
          if (i === 1) return 'dash-top-rank--silver';
          if (i === 2) return 'dash-top-rank--bronze';
          return '';
        };

        const html = rows.map((r, i) => {
          const pct = maxQtd > 0 ? (parseFloat(r.qtd_vendida) / maxQtd * 100).toFixed(0) : 0;
          return `<div class="dash-top-item">
            <div class="dash-top-rank ${rankClass(i)}">${i + 1}</div>
            <div style="flex:1;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="dash-top-name">${window.escHtml ? window.escHtml(r.produto_nome) : r.produto_nome}</span>
                <span class="dash-top-value">${brl(r.faturamento)}</span>
              </div>
              <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                <div class="dash-top-bar-wrap" style="flex:1;">
                  <div class="dash-top-bar" style="width:${pct}%;"></div>
                </div>
                <span style="font-size:0.72rem; color:var(--c-text-3); min-width:55px; text-align:right;">
                  ${parseFloat(r.qtd_vendida).toLocaleString('pt-BR')} un.
                </span>
              </div>
            </div>
          </div>`;
        }).join('');

        $('#dash-top-produtos').html(html);
      })
      .catch(() => {});
  }

  // ── Status PDVs ───────────────────────────────────────────────────────────────
  function carregarPdvs() {
    fetch('/api/dashboard/pdvs-status')
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') return;
        const pdvs   = res.data || [];
        const online = pdvs.filter(p => p.online == 1).length;
        $('#kpi-pdvs-online').text(online);
        $('#kpi-pdvs-total').html(`<span style="color:var(--c-text-3)">${pdvs.length} PDV${pdvs.length !== 1 ? 's' : ''} cadastrados</span>`);

        if (!pdvs.length) {
          $('#dash-pdvs-lista').html(
            '<p style="text-align:center;color:var(--c-text-3);padding:24px;font-size:13px;">Nenhum PDV cadastrado.</p>'
          );
          return;
        }

        function fmtPing(ts) {
          if (!ts) return 'Nunca';
          const d    = new Date(ts.replace(' ', 'T'));
          const diff = Math.floor((Date.now() - d.getTime()) / 1000);
          if (diff < 60)   return 'Agora';
          if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
          return Math.floor(diff / 3600) + 'h atrás';
        }

        const html = pdvs.map(p => `
          <div class="dash-pdv-item">
            <div class="dash-pdv-dot ${p.online == 1 ? 'dash-pdv-dot--online' : 'dash-pdv-dot--offline'}"></div>
            <div style="flex:1;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="dash-pdv-name">PDV ${p.numero_pdv}</span>
                <span class="${p.online == 1 ? 'erp-badge badge-active' : 'erp-badge badge-inactive'}" style="font-size:0.68rem;">
                  ${p.online == 1 ? 'Online' : 'Offline'}
                </span>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
                <span class="dash-pdv-loja"><i class="bi bi-shop me-1"></i>${p.loja_nome || '—'}</span>
                <span class="dash-pdv-ping"><i class="bi bi-clock me-1"></i>${fmtPing(p.ultimo_ping)}</span>
              </div>
              ${p.versao_app ? `<span style="font-size:0.67rem; color:var(--c-text-3);">v${p.versao_app}</span>` : ''}
            </div>
          </div>`).join('');

        $('#dash-pdvs-lista').html(html);
      })
      .catch(() => {});
  }

  // ── Eventos ───────────────────────────────────────────────────────────────────
  $('#btn-dash-filtrar, #btn-dash-reload').on('click', carregarTudo);
  $('#btn-reload-pdvs').on('click', carregarPdvs);

  // ── Auto-refresh a cada 30s ───────────────────────────────────────────────────
  carregarTudo();
  timerAuto = setInterval(carregarTudo, 30000);

  // ── WebSocket — atualização em tempo real ─────────────────────────────────────
  // Escuta eventos do pdv_server.php (Workerman) para atualizar KPIs
  // imediatamente quando uma venda é finalizada ou cancelada.
  try {
    /*const wsUrl = (window.location.protocol === 'https:' ? 'wss://' : 'ws://')
                + window.location.host
                + '/ws';*/
          
      const wsUrl = "wss://pdvix.vps-kinghost.net:8443";

    const wsDash = new WebSocket(wsUrl);

    wsDash.onopen = () => {
      // Registra como painel (não como PDV)
      wsDash.send(JSON.stringify({ event: 'painel:auth', payload: {} }));
    };

    wsDash.onmessage = (ev) => {
      try {
        const msg = JSON.parse(ev.data);
        const evento = msg.event || '';

        // Venda finalizada ou cancelada → atualiza KPIs e gráfico de horas
        if (['pdv:venda_finalizada', 'pdv:venda_cancelada', 'pdv:caixa_fechado'].includes(evento)) {
          const di = $('#dash-data-inicio').val();
          const df = $('#dash-data-fim').val();
          const hoje = new Date().toISOString().slice(0, 10);

          // Só re-carrega KPIs se o filtro inclui hoje
          if (!df || df >= hoje) {
            carregarResumo(di, df);
            carregarVendasHora(di, df);
            carregarFormasPagamento(di, df);
            carregarPdvs();

            // Feedback visual rápido
            const kpi = document.getElementById('kpi-faturamento');
            if (kpi) {
              kpi.style.transition = 'color 0.3s';
              kpi.style.color      = 'var(--c-success)';
              setTimeout(() => { kpi.style.color = ''; }, 1200);
            }
          }
        }

        // PDV online/offline → atualiza lista de PDVs imediatamente
        if (['pdv:heartbeat', 'pdv:caixa_aberto', 'pdv:caixa_fechado', 'ws:auth_ok'].includes(evento)) {
          carregarPdvs();
        }

      } catch { /* ignora mensagens inválidas */ }
    };

    wsDash.onclose = () => {
      // Reconecta em 10s silenciosamente
      setTimeout(() => {
        try {
          const newWs = new WebSocket(wsUrl);
          newWs.onmessage = wsDash.onmessage;
          newWs.onopen    = wsDash.onopen;
          newWs.onclose   = wsDash.onclose;
        } catch { /* ignora */ }
      }, 10_000);
    };

    // Limpa WebSocket ao sair do módulo (SPA cleanup)
    const _origCleanup = window._dashboardCleanup;
    window._dashboardCleanup = function () {
      clearInterval(timerAuto);
      try { wsDash.close(); } catch { /* ignora */ }
      if (_origCleanup) _origCleanup();
    };

  } catch { /* WebSocket não disponível */ }

  // Limpa timer ao sair do módulo (SPA cleanup)
  if (!window._dashboardCleanup) {
    window._dashboardCleanup = function () { clearInterval(timerAuto); };
  }
};
