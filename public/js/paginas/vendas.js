// paginas/vendas.js
// Orquestração da tela de Vendas

window.PageFunctions['vendas'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    };
  }

  document.title = 'Vendas | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ─────────────────────────────────────────────────────────────────
  function fmtMoeda(v) {
    return parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
  function fmtData(d) {
    return window.formatDateTime ? window.formatDateTime(d) : (d || '—');
  }
  function badgeStatus(s) {
    const map = {
      aberta:     '<span class="badge badge-venda-aberta">Aberta</span>',
      finalizada: '<span class="badge badge-venda-finalizada">Finalizada</span>',
      cancelada:  '<span class="badge badge-venda-cancelada">Cancelada</span>',
    };
    return map[s] || `<span class="badge bg-secondary">${s}</span>`;
  }

  // ── Select2 filtros ─────────────────────────────────────────────────────────
  $('#filtro_venda_status, #filtro_venda_total_op').each(function() {
    $(this).select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  });

  // ── Colunas ─────────────────────────────────────────────────────────────────
  const colunas = [
    { data: 'numero_venda' },
    { data: 'data_venda',    render: d => fmtData(d) },
    { data: 'usuario_nome',  render: d => d || '—' },
    { data: 'subtotal',      render: d => fmtMoeda(d) },
    { data: 'desconto',      render: d => fmtMoeda(d) },
    { data: 'acrescimo',     render: d => fmtMoeda(d) },
    { data: 'total',         render: d => `<strong>${fmtMoeda(d)}</strong>` },
    { data: 'status',        render: d => badgeStatus(d) },
    {
      data: 'id',
      orderable: false,
      searchable: false,
      render: (data, type, row) => {
        const isAberta = row.status === 'aberta';
        return `
          <button class="btn btn-sm btn-info me-1" title="Ver detalhes"
            onclick="verDetalhesVenda(${row.id}, '${window.escHtml(row.numero_venda)}')">
            <i class="bi bi-eye"></i>
          </button>
          ${isAberta ? `
          <button class="btn btn-sm btn-success me-1" title="Finalizar"
            onclick="finalizarVenda(${row.id}, '${window.escHtml(row.numero_venda)}')">
            <i class="bi bi-check-circle"></i>
          </button>
          <button class="btn btn-sm btn-warning" title="Cancelar"
            onclick="cancelarVenda(${row.id}, '${window.escHtml(row.numero_venda)}')">
            <i class="bi bi-x-circle"></i>
          </button>` : ''}`;
      },
    },
  ];

  // ── Tabela ──────────────────────────────────────────────────────────────────
  new Table('#tabela-vendas', colunas, '/api/vendas', {
    titleFile: 'Vendas', filename: 'vendas',
    mergeButtons: false, autoWidth: false,
    processing: true, serverSide: true,
    order: [[1, 'desc']],
    ajax: {
      url: '/api/vendas', type: 'GET',
      data: function (d) {
        const s  = $('#filtro_venda_status').val();
        const di = $('#filtro_venda_di').val();
        const df = $('#filtro_venda_df').val();
        const op = $('#filtro_venda_total_op').val();
        const vl = $('#filtro_venda_total_val').val();
        if (s)  d.status    = s;
        if (di) d.data_inicio = di;
        if (df) d.data_fim    = df;
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

  // ── Recarregar / filtros ────────────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela-vendas').DataTable().ajax.reload();
  });
  $('#filtro_venda_status, #filtro_venda_total_op').on('change', () => {
    $('#tabela-vendas').DataTable().ajax.reload();
  });
  $('#filtro_venda_di, #filtro_venda_df, #filtro_venda_total_val').on('change input', () => {
    $('#tabela-vendas').DataTable().ajax.reload();
  });

  // ── Nova Venda ───────────────────────────────────────────────────────────────
  let itensCarrinho = [];

  document.querySelector('[data-create]').addEventListener('click', () => {
    itensCarrinho = [];
    abrirModalNovaVenda();
  });

  function abrirModalNovaVenda() {
    // Renderiza o modal de nova venda com tabela de itens inline
    MODAL.openFormModal({
      title: 'Nova Venda',
      method: 'POST',
      action: '/api/vendas',
      size: 'xl',
      inputs: [
        { label: 'Observação', name: 'observacao', type: 'text' },
        { label: 'Desconto (R$)', name: 'desconto', type: 'text', attributesRender: { value: '0' } },
        { label: 'Acréscimo (R$)', name: 'acrescimo', type: 'text', attributesRender: { value: '0' } },
      ],
      submitText: 'Criar Venda',
      onOpen: (modalEl) => {
        // Adiciona seção de itens após os campos
        const form = modalEl.querySelector('form');
        form.insertAdjacentHTML('beforeend', `
          <hr>
          <h6>Itens da venda</h6>
          <div class="row g-2 mb-2">
            <div class="col-md-5">
              <select class="form-control" id="venda_select_produto" placeholder="Buscar produto..."></select>
            </div>
            <div class="col-md-2">
              <input type="number" id="venda_qtd" class="form-control" placeholder="Qtd" min="1" value="1">
            </div>
            <div class="col-md-2">
              <input type="number" id="venda_preco" class="form-control" placeholder="Preço" step="0.01">
            </div>
            <div class="col-md-3">
              <button type="button" class="btn btn-outline-primary w-100" id="btn_add_item">
                <i class="bi bi-plus"></i> Adicionar
              </button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" id="tabela-itens-venda">
              <thead class="table-light">
                <tr><th>Produto</th><th>Qtd</th><th>Unitário</th><th>Subtotal</th><th></th></tr>
              </thead>
              <tbody id="tbody-itens"></tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-bold">Total itens:</td>
                  <td id="venda-total-itens" class="fw-bold">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        `);

        // Select2 produto
        $('#venda_select_produto').select2({
          placeholder: 'Buscar produto pelo nome...',
          allowClear: true,
          width: '100%',
          dropdownParent: $(modalEl),
          ajax: {
            url: '/api/produtos?simples=1',
            dataType: 'json',
            delay: 250,
            processResults: res => ({
              results: (res.data?.data || res.data || []).map(p => ({
                id: p.id, text: p.nome, preco: p.preco_venda,
              })),
            }),
          },
        }).on('select2:select', function (e) {
          $('#venda_preco').val(parseFloat(e.params.data.preco || 0).toFixed(2));
        });

        // Adicionar item
        document.getElementById('btn_add_item').addEventListener('click', () => {
          const selEl  = document.getElementById('venda_select_produto');
          const prodId = $('#venda_select_produto').val();
          const nome   = $('#venda_select_produto option:selected').text();
          const qtd    = parseInt(document.getElementById('venda_qtd').value);
          const preco  = parseFloat(document.getElementById('venda_preco').value);

          if (!prodId || !qtd || !preco) {
            new Alerta('warning', '', 'Preencha produto, quantidade e preço.');
            return;
          }

          itensCarrinho.push({ produto_id: prodId, produto_nome: nome, quantidade: qtd, valor_unitario: preco });
          renderizarItens();
        });

        function renderizarItens() {
          const tbody = document.getElementById('tbody-itens');
          let total = 0;
          tbody.innerHTML = itensCarrinho.map((item, i) => {
            const sub = item.quantidade * item.valor_unitario;
            total += sub;
            return `<tr>
              <td>${window.escHtml(item.produto_nome)}</td>
              <td>${item.quantidade}</td>
              <td>${parseFloat(item.valor_unitario).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</td>
              <td>${sub.toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</td>
              <td><button type="button" class="btn btn-sm btn-danger" onclick="window._removeItemVenda(${i})">
                <i class="bi bi-trash"></i></button></td>
            </tr>`;
          }).join('');
          document.getElementById('venda-total-itens').textContent =
            total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        window._removeItemVenda = function (i) {
          itensCarrinho.splice(i, 1);
          renderizarItens();
        };
      },
      onSubmit: (formData, form, modal) => {
        if (itensCarrinho.length === 0) {
          new Alerta('warning', '', 'Adicione ao menos um item à venda.');
          return;
        }
        const body = {
          observacao: formData.get('observacao'),
          desconto:   formData.get('desconto')   || 0,
          acrescimo:  formData.get('acrescimo')  || 0,
          itens:      itensCarrinho,
        };
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
            $('#tabela-vendas').DataTable().ajax.reload();
          }
        });
      },
    });
  }

  // ── Ver detalhes ─────────────────────────────────────────────────────────────
  window.verDetalhesVenda = function (id, numero) {
    fetch(`/api/vendas/itens?id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') { new Alerta('error', '', res.message); return; }
        const itens   = res.data.itens     || [];
        const pagtos  = res.data.pagamentos || [];

        const rowsItens = itens.length
          ? itens.map(i => `<tr>
              <td>${window.escHtml(i.produto_nome)}</td>
              <td>${i.quantidade}</td>
              <td>${fmtMoeda(i.valor_unitario)}</td>
              <td>${fmtMoeda(i.subtotal)}</td>
            </tr>`).join('')
          : '<tr><td colspan="4" class="text-center text-muted">Sem itens.</td></tr>';

        const rowsPagtos = pagtos.length
          ? pagtos.map(p => `<tr>
              <td>${p.tipo_pagamento}</td>
              <td>${fmtMoeda(p.valor)}</td>
              <td><span class="badge bg-${p.status==='confirmado'?'success':p.status==='cancelado'?'danger':'warning'}">${p.status}</span></td>
              <td>${p.referencia_externa || '—'}</td>
            </tr>`).join('')
          : '<tr><td colspan="4" class="text-center text-muted">Sem pagamentos.</td></tr>';

        const html = `
          <h6 class="mt-2">Itens</h6>
          <div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Produto</th><th>Qtd</th><th>Unitário</th><th>Subtotal</th></tr></thead>
            <tbody>${rowsItens}</tbody>
          </table></div>
          <h6 class="mt-3">Pagamentos</h6>
          <div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Tipo</th><th>Valor</th><th>Status</th><th>Referência</th></tr></thead>
            <tbody>${rowsPagtos}</tbody>
          </table></div>`;

        MODAL.openAlertModal({
          type: 'info',
          title: `Detalhes: ${numero}`,
          subtitle: html,
          confirmText: 'Fechar',
          onConfirm: () => MODAL.hideModal(),
        });
      });
  };

  // ── Finalizar ────────────────────────────────────────────────────────────────
  window.finalizarVenda = function (id, numero) {
    MODAL.openConfirmationModal({
      title: 'Finalizar venda',
      message: `Deseja finalizar a venda <strong>${numero}</strong>?`,
      bodyHTML: true,
      confirmText: 'Finalizar',
      onConfirm: (modal) => {
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

  // ── Cancelar ─────────────────────────────────────────────────────────────────
  window.cancelarVenda = function (id, numero) {
    MODAL.openConfirmationModal({
      title: 'Cancelar venda',
      message: `Deseja cancelar a venda <strong>${numero}</strong>? Esta ação não pode ser desfeita.`,
      bodyHTML: true,
      confirmText: 'Sim, cancelar',
      onConfirm: (modal) => {
        fetch('/api/vendas/status', {
          headers: { 'Content-type': 'application/json' },
          method: 'PATCH',
          body: JSON.stringify({ id, status: 'cancelada' }),
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

};