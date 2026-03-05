// pagamentos.js — ajustes de UI para o novo design system
// Apenas as funções de renderização visual foram atualizadas.
// A lógica de negócio e estrutura permanecem idênticas.

window.PageFunctions['pagamentos'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    };
  }

  document.title = 'Pagamentos | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ─────────────────────────────────────────────────────────────────
  function fmtMoeda(v) {
    return parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
  function fmtData(d) {
    return window.formatDateTime ? window.formatDateTime(d) : (d || '—');
  }

  const TIPO_LABEL = {
    pix: 'PIX', convenio: 'Convênio', pos_debito: 'POS Débito',
    pos_credito: 'POS Crédito', pos_pix: 'POS PIX', dinheiro: 'Dinheiro', outros: 'Outros',
  };

  // ✅ Atualizado: usa classes do design system
  const TIPO_BADGE_CLASS = {
    pix:        'badge-tipo-pix',
    convenio:   'badge-tipo-convenio',
    pos_debito: 'badge-tipo-debito',
    pos_credito:'badge-tipo-credito',
    pos_pix:    'badge-tipo-pix',
    dinheiro:   'badge-tipo-dinheiro',
    outros:     'badge-tipo-outros',
  };

  function badgeTipo(t) {
    const cls = TIPO_BADGE_CLASS[t] || 'badge-neutral';
    return `<span class="erp-badge ${cls}">${TIPO_LABEL[t] || t}</span>`;
  }

  function badgeStatus(s) {
    const map = {
      pendente:   '<span class="erp-badge badge-pgt-pendente">Pendente</span>',
      confirmado: '<span class="erp-badge badge-pgt-confirmado">Confirmado</span>',
      cancelado:  '<span class="erp-badge badge-pgt-cancelado">Cancelado</span>',
    };
    return map[s] || `<span class="erp-badge badge-neutral">${s}</span>`;
  }

  // ── Select2 filtros ─────────────────────────────────────────────────────────
  $('#filtro_pgt_tipo, #filtro_pgt_status').each(function() {
    $(this).select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  });

  // ── Colunas ─────────────────────────────────────────────────────────────────
  const colunas = [
    { data: 'numero_venda' },
    { data: 'tipo_pagamento', render: d => badgeTipo(d) },
    { data: 'valor',          render: d => `<strong>${fmtMoeda(d)}</strong>` },
    { data: 'referencia_externa', render: d => d || '<span class="text-muted-erp">—</span>' },
    { data: 'descricao',          render: d => d || '<span class="text-muted-erp">—</span>' },
    { data: 'status',             render: d => badgeStatus(d) },
    { data: 'created_at',         render: d => fmtData(d) },
    {
      data: 'id',
      orderable: false, searchable: false,
      render: (data, type, row) => {
        const isPendente = row.status === 'pendente';
        return `<div class="act-group">
          <button class="btn-act btn-act-edit" title="Editar"
            onclick="editarPagamento(${row.id}, ${row.venda_id}, '${row.tipo_pagamento}',
              '${row.valor}', '${window.escHtml(row.referencia_externa||'')}',
              '${window.escHtml(row.descricao||'')}')">
            <i class="bi bi-pencil-square"></i>
          </button>
          <button class="btn-act btn-act-warn" title="Alterar status"
            onclick="alterarStatusPagamento(${row.id}, '${row.status}')">
            <i class="bi bi-arrow-repeat"></i>
          </button>
          ${isPendente ? `
          <button class="btn-act btn-act-danger btn-act-danger-hover" title="Excluir"
            onclick="excluirPagamento(${row.id})">
            <i class="bi bi-trash-fill"></i>
          </button>` : ''}
        </div>`;
      },
    },
  ];

  // ── Tabela ──────────────────────────────────────────────────────────────────
  new Table('#tabela-pagamentos', colunas, '/api/pagamentos', {
    titleFile: 'Pagamentos', filename: 'pagamentos',
    mergeButtons: false, autoWidth: false,
    processing: true, serverSide: true,
    order: [[6, 'desc']],
    ajax: {
      url: '/api/pagamentos', type: 'GET',
      data: function (d) {
        const tipo   = $('#filtro_pgt_tipo').val();
        const status = $('#filtro_pgt_status').val();
        const di     = $('#filtro_pgt_di').val();
        const df     = $('#filtro_pgt_df').val();
        if (tipo)   d.tipo_pagamento = tipo;
        if (status) d.status         = status;
        if (di)     d.data_inicio    = di;
        if (df)     d.data_fim       = df;
      },
      dataSrc: function (json) {
        json.recordsTotal    = json.data.recordsTotal;
        json.recordsFiltered = json.data.recordsFiltered;
        json.draw            = json.data.draw;
        return json.data.data;
      },
    },
  });

  // ── Recarregar / filtros ────────────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela-pagamentos').DataTable().ajax.reload();
  });
  $('#filtro_pgt_tipo, #filtro_pgt_status').on('change', () => {
    $('#tabela-pagamentos').DataTable().ajax.reload();
  });
  $('#filtro_pgt_di, #filtro_pgt_df').on('change', () => {
    $('#tabela-pagamentos').DataTable().ajax.reload();
  });

  // ── Inputs comuns ────────────────────────────────────────────────────────────
  const TIPO_VALUES = {
    pix: 'PIX', convenio: 'Convênio', pos_debito: 'POS Débito',
    pos_credito: 'POS Crédito', pos_pix: 'POS PIX', dinheiro: 'Dinheiro', outros: 'Outros',
  };

  function inputsPagamento(overrides = {}) {
    return [
      {
        label: 'Venda',
        name: 'venda_id',
        type: 'select2',
        fetchURI: '/api/vendas?status=aberta',
        placeholder: 'Buscar venda...',
        processItem: item => ({ id: item.id, text: `${item.numero_venda} — ${fmtMoeda(item.total)}` }),
        selectedId: overrides.venda_id || undefined,
      },
      {
        label: 'Tipo de pagamento',
        name: 'tipo_pagamento',
        type: 'select',
        values: TIPO_VALUES,
        selectedId: overrides.tipo_pagamento || undefined,
      },
      { label: 'Valor (R$)',           name: 'valor',               type: 'text',
        attributesRender: { value: overrides.valor || '' } },
      { label: 'Referência externa',   name: 'referencia_externa',  type: 'text',
        attributesRender: { value: overrides.referencia_externa || '' } },
      { label: 'Descrição (opcional)', name: 'descricao',            type: 'text',
        attributesRender: { value: overrides.descricao || '' } },
    ];
  }

  // ── Criar ────────────────────────────────────────────────────────────────────
  document.querySelector('[data-create]').addEventListener('click', () => {
    MODAL.openFormModal({
      title: 'Cadastrar Pagamento',
      method: 'POST',
      action: '/api/pagamentos',
      inputs: inputsPagamento(),
      submitText: 'Cadastrar',
      onSubmit: (formData, form, modal) => {
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'POST',
          body: FormData.toJSON(formData),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') {
            modal.hide();
            $('#tabela-pagamentos').DataTable().ajax.reload();
          }
        });
      },
    });
  });

  // ── Editar ───────────────────────────────────────────────────────────────────
  window.editarPagamento = function (id, vendaId, tipoPgt, valor, ref, desc) {
    MODAL.openFormModal({
      title: 'Editar Pagamento',
      method: 'PUT',
      action: '/api/pagamentos',
      inputs: [
        { type: 'hidden', name: 'id', label: '', value: id },
        ...inputsPagamento({ venda_id: vendaId, tipo_pagamento: tipoPgt, valor, referencia_externa: ref, descricao: desc }),
      ],
      submitText: 'Salvar',
      onSubmit: (formData, form, modal) => {
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'PUT',
          body: FormData.toJSON(formData),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') {
            modal.hide();
            $('#tabela-pagamentos').DataTable().ajax.reload();
          }
        });
      },
    });
  };

  // ── Alterar status ───────────────────────────────────────────────────────────
  window.alterarStatusPagamento = function (id, statusAtual) {
    MODAL.openFormModal({
      title: 'Alterar status do pagamento',
      method: 'PATCH',
      action: '/api/pagamentos/status',
      inputs: [
        { type: 'hidden', name: 'id', label: '', value: id },
        {
          label: 'Novo status',
          name: 'status',
          type: 'select',
          values: { pendente: 'Pendente', confirmado: 'Confirmado', cancelado: 'Cancelado' },
          selectedId: statusAtual,
        },
      ],
      submitText: 'Alterar',
      onSubmit: (formData, form, modal) => {
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'PATCH',
          body: FormData.toJSON(formData),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') {
            modal.hide();
            $('#tabela-pagamentos').DataTable().ajax.reload();
          }
        });
      },
    });
  };

  // ── Excluir ──────────────────────────────────────────────────────────────────
  window.excluirPagamento = function (id) {
    MODAL.openConfirmationModal({
      title: 'Excluir pagamento',
      message: 'Deseja realmente excluir este pagamento? Esta ação é irreversível.',
      confirmText: 'Sim, excluir',
      onConfirm: (modal) => {
        fetch('/api/pagamentos', {
          headers: { 'Content-type': 'application/json' },
          method: 'DELETE',
          body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          if (data.status === 'success') $('#tabela-pagamentos').DataTable().ajax.reload();
        });
      },
    });
  };

};
