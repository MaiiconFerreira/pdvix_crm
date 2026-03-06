'use strict';
// cancelamentos.js

window.PageFunctions['cancelamentos'] = function () {

  document.title = 'Cancelamentos | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  const brl   = v => parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const fmtDt = d => window.formatDateTime ? window.formatDateTime(d) : (d || '—');
  const hoje  = () => new Date().toISOString().slice(0, 10);

  // ── Badges ───────────────────────────────────────────────────────────────────
  function badgeTipo(t) {
    return t === 'venda'
      ? '<span class="erp-badge badge-cancelamento-venda"><i class="bi bi-bag-x me-1"></i>Venda</span>'
      : '<span class="erp-badge badge-cancelamento-item"><i class="bi bi-box-seam me-1"></i>Item</span>';
  }
  function badgeOrigem(o) {
    return o === 'pdv'
      ? '<span class="erp-badge badge-origem-pdv"><i class="bi bi-display me-1"></i>PDV</span>'
      : '<span class="erp-badge badge-origem-painel"><i class="bi bi-laptop me-1"></i>Painel</span>';
  }

  // ── Inicia data de hoje ──────────────────────────────────────────────────────
  $('#filtro-cancel-di').val(hoje());
  $('#filtro-cancel-df').val(hoje());

  // ── DataTable ────────────────────────────────────────────────────────────────
  const tabela = $('#tabela-cancelamentos').DataTable({
    processing: true, serverSide: true,
    pageLength: 25, order: [[7, 'desc']],
    language: { url: '/template/datatables_pt-BR.json' },
    ajax: {
      url: '/api/cancelamentos', type: 'GET',
      data: function (d) {
        d.tipo        = $('#filtro-cancel-tipo').val();
        d.data_inicio = $('#filtro-cancel-di').val();
        d.data_fim    = $('#filtro-cancel-df').val();
      },
    },
    columns: [
      { data: 'tipo',         render: d => badgeTipo(d) },
      { data: 'numero_venda', render: d => d
          ? `<code style="font-size:11px; background:var(--c-bg); padding:2px 6px; border-radius:4px;">${window.escHtml(d)}</code>`
          : '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'operador_nome', render: d => d || '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'numero_pdv',
        render: d => d
          ? `<span class="erp-badge badge-info">PDV ${window.escHtml(d)}</span>`
          : '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'valor_cancelado',
        render: d => `<strong style="color:var(--c-danger)">${brl(d)}</strong>` },
      { data: 'motivo', render: d => d || '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'origem', render: d => badgeOrigem(d) },
      { data: 'cancelado_em', render: d => fmtDt(d) },
    ],
    drawCallback: function () {
      atualizarCards(this.api().ajax.json());
    },
  });

  // ── Cards resumo ──────────────────────────────────────────────────────────────
  function atualizarCards(json) {
    if (!json || !json.data) return;
    const rows = json.data;
    const totalVendas = rows.filter(r => r.tipo === 'venda').length;
    const totalItens  = rows.filter(r => r.tipo === 'item').length;
    const totalValor  = rows.reduce((s, r) => s + parseFloat(r.valor_cancelado || 0), 0);

    $('#card-cancel-total').text(rows.length);
    $('#card-cancel-vendas').text(totalVendas);
    $('#card-cancel-itens').text(totalItens);
    $('#card-cancel-valor').text(brl(totalValor));
  }

  // ── Filtros ───────────────────────────────────────────────────────────────────
  $('#btn-cancel-filtrar').on('click', () => tabela.ajax.reload());
  $('#filtro-cancel-tipo').on('change', () => tabela.ajax.reload());

  $('#btn-cancel-limpar').on('click', () => {
    $('#filtro-cancel-tipo').val('');
    $('#filtro-cancel-di').val(hoje());
    $('#filtro-cancel-df').val(hoje());
    tabela.search('').ajax.reload();
  });

  document.querySelector('[data-submit]').addEventListener('click', () => {
    tabela.ajax.reload();
  });

  // ── Cancelar venda pelo painel ────────────────────────────────────────────────
  $('#btn-cancelar-venda').on('click', () => {
    MODAL.openFormModal({
      title: 'Cancelar Venda', method: 'POST', action: '/api/cancelamentos/venda',
      inputs: [
        {
          label: 'Venda',
          name: 'venda_id',
          type: 'select2',
          fetchURI: '/api/vendas?status=finalizada',
          placeholder: 'Buscar pelo nº da venda...',
          processItem: item => ({ id: item.id, text: `${item.numero_venda} — ${parseFloat(item.total||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}` }),
        },
        {
          label: 'Motivo do cancelamento',
          name: 'motivo',
          type: 'text',
          attributesRender: { placeholder: 'Descreva brevemente o motivo' },
        },
      ],
      submitText: 'Cancelar Venda',
      onSubmit: (formData, form, modal) => {
        const body = {};
        formData.forEach((v, k) => { body[k] = v; });
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'POST',
          body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') {
            modal.hide();
            tabela.ajax.reload();
          }
        });
      },
    });
  });
};
