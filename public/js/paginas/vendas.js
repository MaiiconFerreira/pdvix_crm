'use strict';
// vendas.js — refatorado v3 (design system btn-act + cancelamento via CancelamentoController)

window.PageFunctions['vendas'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  document.title = 'Vendas | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const fmtMoeda = v => parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const fmtData  = d => window.formatDateTime ? window.formatDateTime(d) : (d || '—');

  function badgeStatus(s) {
    const map = {
      aberta:     '<span class="erp-badge badge-pending">Aberta</span>',
      finalizada: '<span class="erp-badge badge-active">Finalizada</span>',
      cancelada:  '<span class="erp-badge badge-inactive">Cancelada</span>',
    };
    return map[s] || `<span class="erp-badge badge-neutral">${s}</span>`;
  }

  // ── Select2 filtros ──────────────────────────────────────────────────────────
  $('#filtro_venda_status, #filtro_venda_total_op').each(function () {
    $(this).select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  });

  // ── Colunas ──────────────────────────────────────────────────────────────────
  const colunas = [
    { data: 'numero_venda',
      render: d => `<code style="font-size:11px; background:var(--c-bg); padding:2px 6px; border-radius:4px;">${window.escHtml(d || '—')}</code>` },
    { data: 'data_venda',  render: d => fmtData(d) },
    { data: 'usuario_nome', render: d => d || '<span class="text-muted-erp">—</span>' },
    { data: 'numero_pdv',
      render: d => d
        ? `<span class="erp-badge badge-info">PDV ${window.escHtml(d)}</span>`
        : '<span class="text-muted-erp">—</span>' },
    { data: 'subtotal', render: d => fmtMoeda(d) },
    { data: 'desconto',
      render: d => parseFloat(d) > 0
        ? `<span style="color:var(--c-danger)">-${fmtMoeda(d)}</span>`
        : '<span class="text-muted-erp">—</span>' },
    { data: 'total', render: d => `<strong>${fmtMoeda(d)}</strong>` },
    { data: 'status', render: d => badgeStatus(d) },
    {
      data: null, orderable: false, width: '100px',
      render: function (d, t, row) {
        const isAberta    = row.status === 'aberta';
        const isFinalizada = row.status === 'finalizada';
        const total = row.total;
        const desconto = row.desconto;
        const acrescimo = row.acrescimo;

        return `<div class="act-group">
          <button class="btn-act btn-act-edit" title="Ver detalhes"
            onclick="verDetalhesVenda(${row.id}, '${window.escHtml(row.numero_venda)}', '${row.status}', '${total}', '${desconto}', '${acrescimo}')">
            <i class="bi bi-eye"></i>
          </button>
          ${isAberta ? `
          <button class="btn-act btn-act-success" title="Finalizar venda"
            onclick="finalizarVenda(${row.id}, '${window.escHtml(row.numero_venda)}')">
            <i class="bi bi-check-circle"></i>
          </button>` : ''}
          ${(isAberta || isFinalizada) ? `
          <button class="btn-act btn-act-warn" title="Cancelar venda"
            onclick="abrirCancelarVenda(${row.id}, '${window.escHtml(row.numero_venda)}')">
            <i class="bi bi-x-circle"></i>
          </button>` : ''}
        </div>`;
      },
    },
  ];

  // ── Tabela ───────────────────────────────────────────────────────────────────
  new Table('#tabela-vendas', colunas, '/api/vendas', {
    titleFile: 'Vendas', filename: 'vendas',
    mergeButtons: false, autoWidth: false,
    processing: true, serverSide: true,
    order: [[1, 'desc']],
    ajax: {
      url: '/api/vendas', type: 'GET',
      data: function (d) {
        const s      = $('#filtro_venda_status').val();
        const pdv    = $('#filtro_venda_numero_pdv').val().trim();
        const di     = $('#filtro_venda_di').val();
        const df     = $('#filtro_venda_df').val();
        const op     = $('#filtro_venda_total_op').val();
        const vl     = $('#filtro_venda_total_val').val();
        if (s)          d.status      = s;
        if (pdv)        d.numero_pdv  = pdv;
        if (di)         d.data_inicio = di;
        if (df)         d.data_fim    = df;
        if (op && vl !== '') { d.total_op = op; d.total_val = vl; }
      },
      dataSrc: function (json) {
        json.recordsTotal    = json.data.recordsTotal;
        json.recordsFiltered = json.data.recordsFiltered;
        json.draw            = json.data.draw;
        return json.data.data;
      },
    },
  });

  // ── Eventos filtro ────────────────────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela-vendas').DataTable().ajax.reload();
  });
  $('#filtro_venda_status, #filtro_venda_total_op').on('change', () => {
    $('#tabela-vendas').DataTable().ajax.reload();
  });
  $('#filtro_venda_di, #filtro_venda_df, #filtro_venda_total_val, #filtro_venda_numero_pdv')
    .on('change input', () => {
      $('#tabela-vendas').DataTable().ajax.reload();
    });

  // ── Nova Venda ────────────────────────────────────────────────────────────────
  let itensCarrinho = [];

  document.querySelector('[data-create]').addEventListener('click', () => {
    itensCarrinho = [];
    abrirModalNovaVenda();
  });

  function abrirModalNovaVenda() {
    MODAL.openFormModal({
      title: 'Nova Venda',
      method: 'POST',
      action: '/api/vendas',
      size: 'xl',
      inputs: [
        { label: 'Observação',      name: 'observacao', type: 'text' },
        { label: 'Desconto (R$)',   name: 'desconto',   type: 'text', attributesRender: { value: '0' } },
        { label: 'Acréscimo (R$)', name: 'acrescimo',  type: 'text', attributesRender: { value: '0' } },
      ],
      submitText: 'Criar Venda',
      onOpen: (modalEl) => {
        const form = modalEl.querySelector('form');
        form.insertAdjacentHTML('beforeend', `
          <hr style="border-color:var(--c-border); margin:var(--space-md) 0;">
          <div style="font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--c-text-3); margin-bottom:12px;">
            <i class="bi bi-box-seam me-1" style="color:var(--c-primary);"></i> Itens da venda
          </div>
          <div style="display:grid; grid-template-columns:1fr 80px 100px auto; gap:8px; margin-bottom:12px; align-items:end;">
            <div>
              <label class="erp-label">Produto</label>
              <select id="venda_select_produto" style="width:100%;"></select>
            </div>
            <div>
              <label class="erp-label">Qtd</label>
              <input type="number" id="venda_qtd" class="erp-input" value="1" min="0.001" step="0.001">
            </div>
            <div>
              <label class="erp-label">Preço (R$)</label>
              <input type="number" id="venda_preco" class="erp-input" step="0.01" min="0">
            </div>
            <div>
              <button type="button" id="btn_add_item" class="btn-erp btn-erp-primary" style="height:36px;">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          </div>
          <div class="erp-table-wrapper" style="max-height:240px; overflow-y:auto;">
            <table class="erp-table">
              <thead>
                <tr>
                  <th>Produto</th>
                  <th style="text-align:center;">Qtd</th>
                  <th style="text-align:right;">Unitário</th>
                  <th style="text-align:right;">Subtotal</th>
                  <th style="text-align:center;">—</th>
                </tr>
              </thead>
              <tbody id="tbody-itens"></tbody>
              <tfoot>
                <tr style="background:var(--c-bg);">
                  <td colspan="3" style="text-align:right; font-weight:700; padding:8px 14px;">Total:</td>
                  <td id="venda-total-itens" style="text-align:right; font-weight:800; font-size:1rem; color:var(--c-navy); padding:8px 14px;">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        `);

        $('#venda_select_produto').select2({
          placeholder: 'Buscar produto pelo nome...',
          allowClear: true, width: '100%',
          dropdownParent: $(modalEl),
          ajax: {
            url: '/api/produtos?simples=1', dataType: 'json', delay: 250,
            processResults: res => ({
              results: (res.data?.data || res.data || []).map(p => ({ id: p.id, text: p.nome, preco: p.preco_venda })),
            }),
          },
        }).on('select2:select', e => { $('#venda_preco').val(parseFloat(e.params.data.preco || 0).toFixed(2)); });

        document.getElementById('btn_add_item').addEventListener('click', () => {
          const prodId = $('#venda_select_produto').val();
          const nome   = $('#venda_select_produto option:selected').text();
          const qtd    = parseFloat($('#venda_qtd').val()) || 0;
          const preco  = parseFloat($('#venda_preco').val()) || 0;

          if (!prodId || qtd <= 0 || preco <= 0) { new Alerta('warning', '', 'Preencha produto, quantidade e preço.'); return; }

          itensCarrinho.push({ produto_id: prodId, produto_nome: nome, quantidade: qtd, valor_unitario: preco });
          renderItens();
        });

        function renderItens() {
          const tbody = document.getElementById('tbody-itens');
          let total   = 0;
          tbody.innerHTML = itensCarrinho.map((item, i) => {
            const sub = item.quantidade * item.valor_unitario;
            total += sub;
            return `<tr>
              <td>${window.escHtml(item.produto_nome)}</td>
              <td style="text-align:center;">${item.quantidade}</td>
              <td style="text-align:right;">${fmtMoeda(item.valor_unitario)}</td>
              <td style="text-align:right; font-weight:700;">${fmtMoeda(sub)}</td>
              <td style="text-align:center;">
                <button type="button" class="btn-act btn-act-danger btn-act-danger-hover"
                  style="width:24px; height:24px; padding:0;"
                  onclick="window._removeItemVenda(${i})">
                  <i class="bi bi-trash" style="font-size:10px;"></i>
                </button>
              </td>
            </tr>`;
          }).join('');
          document.getElementById('venda-total-itens').textContent = fmtMoeda(total);
        }

        window._removeItemVenda = function (i) { itensCarrinho.splice(i, 1); renderItens(); };
        renderItens();
      },
      onSubmit: (formData, form, modal) => {
        if (itensCarrinho.length === 0) { new Alerta('warning', '', 'Adicione ao menos um item.'); return; }
        const body = {
          observacao: formData.get('observacao'),
          desconto:   formData.get('desconto')  || 0,
          acrescimo:  formData.get('acrescimo') || 0,
          itens:      itensCarrinho,
        };
        fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'POST', body: JSON.stringify(body) })
          .then(r => r.json())
          .then(data => {
            new Alerta(data.status, '', data.message);
            if (data.status === 'success') { modal.hide(); $('#tabela-vendas').DataTable().ajax.reload(); }
          });
      },
    });
  }

  // ── Ver Detalhes ─────────────────────────────────────────────────────────────
  window.verDetalhesVenda = function (id, numero, statusStr, total, desconto, acrescimo) {
    fetch(`/api/vendas/itens?id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') { new Alerta('error', '', res.message); return; }
        const itens  = res.data.itens     || [];
        const pagtos = res.data.pagamentos || [];

        const rowsItens = itens.length
          ? itens.map(i => `<tr>
              <td>${window.escHtml(i.produto_nome)}</td>
              <td style="text-align:center;">${i.quantidade}</td>
              <td style="text-align:right;">${fmtMoeda(i.valor_unitario)}</td>
              <td style="text-align:right; font-weight:700;">${fmtMoeda(i.subtotal)}</td>
            </tr>`).join('')
          : '<tr><td colspan="4" style="text-align:center; color:var(--c-text-3);">Sem itens.</td></tr>';

        const rowsPagtos = pagtos.length
          ? pagtos.map(p => `<tr>
              <td>${p.tipo_pagamento}</td>
              <td style="text-align:right; font-weight:700;">${fmtMoeda(p.valor)}</td>
              <td style="text-align:center;">${p.status === 'confirmado'
                ? '<span class="erp-badge badge-active">Confirmado</span>'
                : p.status === 'cancelado'
                  ? '<span class="erp-badge badge-inactive">Cancelado</span>'
                  : '<span class="erp-badge badge-pending">Pendente</span>'}</td>
              <td>${p.referencia_externa || '<span style="color:var(--c-text-3)">—</span>'}</td>
            </tr>`).join('')
          : '<tr><td colspan="4" style="text-align:center; color:var(--c-text-3); padding:16px;">Nenhum pagamento.</td></tr>';

        const html = `
          <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-md); margin-bottom:var(--space-md);">
            <div class="erp-card" style="text-align:center; padding:var(--space-md);">
              <div style="font-size:0.72rem; color:var(--c-text-3); font-weight:600; text-transform:uppercase; margin-bottom:6px;">Status</div>
              ${badgeStatus(statusStr)}
            </div>
            <div class="erp-card" style="text-align:center; padding:var(--space-md);">
              <div style="font-size:0.72rem; color:var(--c-text-3); font-weight:600; text-transform:uppercase; margin-bottom:6px;">Total</div>
              <div style="font-size:1.2rem; font-weight:800; color:var(--c-navy);">${fmtMoeda(total)}</div>
            </div>
            <div class="erp-card" style="text-align:center; padding:var(--space-md);">
              <div style="font-size:0.72rem; color:var(--c-text-3); font-weight:600; text-transform:uppercase; margin-bottom:6px;">Desconto / Acrésc.</div>
              <div style="font-size:13px; color:var(--c-danger);">↓ ${fmtMoeda(desconto)}</div>
              <div style="font-size:13px; color:var(--c-success);">↑ ${fmtMoeda(acrescimo)}</div>
            </div>
          </div>
          <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--c-text-3); margin-bottom:10px;">
            <i class="bi bi-box-seam me-1" style="color:var(--c-primary);"></i> Produtos
          </div>
          <div class="erp-table-wrapper" style="margin-bottom:var(--space-md);">
            <table class="erp-table">
              <thead><tr><th>Produto</th><th style="text-align:center;">Qtd</th><th style="text-align:right;">Unitário</th><th style="text-align:right;">Subtotal</th></tr></thead>
              <tbody>${rowsItens}</tbody>
            </table>
          </div>
          <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--c-text-3); margin-bottom:10px;">
            <i class="bi bi-wallet2 me-1" style="color:var(--c-success);"></i> Pagamentos
          </div>
          <div class="erp-table-wrapper">
            <table class="erp-table">
              <thead><tr><th>Tipo</th><th style="text-align:right;">Valor</th><th style="text-align:center;">Status</th><th>Referência</th></tr></thead>
              <tbody>${rowsPagtos}</tbody>
            </table>
          </div>`;

        MODAL.openContentModal({
          title: `<i class="bi bi-receipt me-2" style="color:var(--c-primary);"></i>Venda <span style="color:var(--c-primary)">${numero}</span>`,
          size: 'lg', message: html, buttonText: 'Fechar',
        });
      });
  };

  // ── Finalizar Venda ──────────────────────────────────────────────────────────
  window.finalizarVenda = function (id, numero) {
    MODAL.openConfirmationModal({
      title: 'Finalizar Venda',
      message: `Deseja finalizar a venda <strong>${numero}</strong>?`,
      bodyHTML: true, confirmText: 'Finalizar',
      onConfirm: modal => {
        fetch('/api/vendas/finalizar', {
          headers: { 'Content-type': 'application/json' },
          method: 'POST',
          body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          if (data.status === 'success') $('#tabela-vendas').DataTable().ajax.reload();
        });
      },
    });
  };

  // ── Cancelar Venda (via CancelamentoController) ───────────────────────────────
  window.abrirCancelarVenda = function (id, numero) {
    MODAL.openFormModal({
      title: `Cancelar Venda ${numero}`,
      method: 'POST', action: '/api/cancelamentos/venda',
      inputs: [
        { type: 'hidden', name: 'venda_id', value: id },
        {
          label: 'Motivo do cancelamento',
          name: 'motivo',
          type: 'text',
          attributesRender: { placeholder: 'Descreva brevemente o motivo...' },
        },
      ],
      submitText: 'Confirmar cancelamento',
      onSubmit: (formData, form, modal) => {
        const body = { venda_id: id, motivo: formData.get('motivo') };
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'POST',
          body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') { modal.hide(); $('#tabela-vendas').DataTable().ajax.reload(); }
        });
      },
    });
  };
};
