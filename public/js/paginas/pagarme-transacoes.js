'use strict';
// pagarme-transacoes.js

window.PageFunctions['pagarme-transacoes'] = function () {

  document.title = 'Transações Pagar.me | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  const fmtDt = d => window.formatDateTime ? window.formatDateTime(d) : (d ? new Date(d).toLocaleString('pt-BR') : '—');
  const hoje  = () => new Date().toISOString().slice(0, 10);

  // ── Badges Visuais ───────────────────────────────────────────────────────────
  function badgeTipo(t) {
    if (!t) return '<span style="color:var(--c-text-3)">—</span>';
    if (t.includes('pos_')) {
      return `<span class="erp-badge badge-pgm-tipo-pos"><i class="bi bi-calculator me-1"></i>POS / Maquininha</span>`;
    }
    return `<span class="erp-badge badge-pgm-tipo-pix"><i class="bi bi-qr-code-scan me-1"></i>PIX API</span>`;
  }

  function badgeStatus(s) {
    const map = {
      'paid':            { class: 'badge-pgm-status-paid',     icon: 'bi-check-circle',  label: 'Pago' },
      'pending':         { class: 'badge-pgm-status-pending',  icon: 'bi-clock',         label: 'Pendente' },
      'pending_capture': { class: 'badge-pgm-status-pending',  icon: 'bi-clock-history', label: 'Pend. Captura' },
      'canceled':        { class: 'badge-pgm-status-canceled', icon: 'bi-x-circle',      label: 'Cancelado' },
      'failed':          { class: 'badge-pgm-status-canceled', icon: 'bi-exclamation-triangle', label: 'Falhou' }
    };
    const b = map[s] || { class: '', icon: 'bi-info-circle', label: s };
    return `<span class="erp-badge ${b.class}"><i class="bi ${b.icon} me-1"></i>${b.label}</span>`;
  }

  // ── Inicia data de hoje (opcional, igual cancelamentos) ──────────────────────
  $('#filtro-pgm-di').val(hoje());
  $('#filtro-pgm-df').val(hoje());

  // ── DataTable ────────────────────────────────────────────────────────────────
  const tabela = $('#tabela-pagarme').DataTable({
    processing: true, 
    serverSide: true,
    pageLength: 25, 
    order: [[0, 'desc']],
    language: { url: '/template/datatables_pt-BR.json' },
    ajax: {
      url: '/api/pagarme/transacoes', 
      type: 'GET',
      data: function (d) {
        d.status      = $('#filtro-pgm-status').val();
        d.tipo        = $('#filtro-pgm-tipo').val();
        d.data_inicio = $('#filtro-pgm-di').val();
        d.data_fim    = $('#filtro-pgm-df').val();
      },
    },
    columns: [
      { data: 'created_at',   render: d => fmtDt(d) },
      { data: 'numero_venda', render: d => d
          ? `<code style="font-size:11px; background:var(--c-bg); padding:2px 6px; border-radius:4px;">${window.escHtml(d)}</code>`
          : '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'order_id',     render: d => `<strong>${window.escHtml(d)}</strong>` },
      { data: 'tipo',         render: d => badgeTipo(d) },
      { data: 'status',       render: d => badgeStatus(d) },
      {
        data: null,
        orderable: false,
        className: 'text-center',
        render: function (data, type, row) {
          let botoes = '';
          
          // Botão Status (Sempre visível)
          botoes += `<button class="btn btn-sm btn-outline-info btn-status mx-1" data-order="${row.order_id}" title="Verificar Status da API Pagar.me">
                        <i class="bi bi-arrow-repeat"></i>
                     </button>`;

          // Botão Cancelar (Apenas se pendente)
          if (row.status === 'pending') {
            botoes += `<button class="btn btn-sm btn-outline-danger btn-cancelar mx-1" data-order="${row.order_id}" title="Cancelar Cobrança na Pagar.me">
                          <i class="bi bi-x-lg"></i>
                       </button>`;
          }

          return botoes;
        }
      }
    ],
    drawCallback: function () {
      atualizarCards(this.api().ajax.json());
    },
  });

  // ── Cards resumo ──────────────────────────────────────────────────────────────
  function atualizarCards(json) {
    if (!json || !json.data) return;
    const rows = json.data;
    
    const pagos      = rows.filter(r => r.status === 'paid').length;
    const pendentes  = rows.filter(r => r.status === 'pending' || r.status === 'pending_capture').length;
    const falhas     = rows.filter(r => r.status === 'canceled' || r.status === 'failed').length;

    $('#card-pgm-total').text(rows.length);
    $('#card-pgm-pagos').text(pagos);
    $('#card-pgm-pendentes').text(pendentes);
    $('#card-pgm-cancelados').text(falhas);
  }

  // ── Filtros ───────────────────────────────────────────────────────────────────
  $('#btn-pgm-filtrar').on('click', () => tabela.ajax.reload());
  
  // Atualização automática ao mudar status ou tipo
  $('#filtro-pgm-status, #filtro-pgm-tipo').on('change', () => tabela.ajax.reload());

  $('#btn-pgm-limpar').on('click', () => {
    $('#filtro-pgm-status').val('');
    $('#filtro-pgm-tipo').val('');
    $('#filtro-pgm-di').val(hoje());
    $('#filtro-pgm-df').val(hoje());
    tabela.search('').ajax.reload();
  });

  document.querySelector('[data-submit]').addEventListener('click', () => {
    tabela.ajax.reload();
  });

  // ── AÇÕES DA TABELA (Usando o modal global do AdminLTE) ───────────────────────

  // 1. Cancelar Cobrança
  $('#tabela-pagarme tbody').on('click', '.btn-cancelar', function () {
    const orderId = $(this).data('order');

    window.MODAL.openConfirmationModal({
        title: 'Cancelar Cobrança',
        message: `Deseja realmente cancelar a cobrança <b>${orderId}</b>?<br>Essa ação não pode ser desfeita na Pagar.me.`,
        confirmText: 'Sim, cancelar',
        onConfirm: (modalObj) => {
            modalObj.hide();
            
            // Loading
            const loading = window.MODAL.openAlertModal({ type: 'loading', title: 'Cancelando...' });

            fetch('/api/pagamentos/pix/cancelar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(r => r.json())
            .then(res => {
                window.MODAL.hideModal(); // Fecha o loading
                if (res.status === 'success') {
                    window.MODAL.openAlertModal({
                        type: 'success', 
                        title: 'Cancelado!', 
                        subtitle: 'A cobrança foi cancelada com sucesso.'
                    });
                    tabela.ajax.reload(null, false);
                } else {
                    window.MODAL.openAlertModal({
                        type: 'error', 
                        title: 'Erro', 
                        subtitle: res.message || 'Erro ao cancelar a cobrança.'
                    });
                }
            })
            .catch(e => {
                window.MODAL.hideModal();
                window.MODAL.openAlertModal({
                    type: 'error', 
                    title: 'Erro de Comunicação', 
                    subtitle: 'Falha ao se comunicar com o servidor.'
                });
            });
        }
    });
  });

  // 2. Consultar Status Atual na Pagar.me
  $('#tabela-pagarme tbody').on('click', '.btn-status', function () {
    const orderId = $(this).data('order');
    
    const loading = window.MODAL.openAlertModal({ type: 'loading', title: 'Consultando...' });

    fetch(`/api/pagamentos/pix/status?order_id=${orderId}`)
    .then(r => r.json())
    .then(res => {
        window.MODAL.hideModal();
        if (res.status === 'success') {
            const statusAtual = res.data && res.data.status ? res.data.status : 'Desconhecido';
            window.MODAL.openAlertModal({
                type: 'info', 
                title: 'Status Atual', 
                subtitle: `O status local da transação é: <b>${statusAtual}</b>`
            });
            // Recarrega de forma silenciosa para o caso do status ter mudado via webhook em background
            tabela.ajax.reload(null, false);
        } else {
            window.MODAL.openAlertModal({
                type: 'error', 
                title: 'Erro', 
                subtitle: res.message || 'Erro ao consultar status.'
            });
        }
    })
    .catch(e => {
        window.MODAL.hideModal();
        window.MODAL.openAlertModal({
            type: 'error', 
            title: 'Erro', 
            subtitle: 'Falha de comunicação com o servidor.'
        });
    });
  });

};