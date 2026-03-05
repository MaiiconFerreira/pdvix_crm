// paginas/produtos.js
// Orquestração da tela de Produtos — REST /api/produtos

window.PageFunctions['produtos'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '&quot;');
    };
  }

  document.title = 'Produtos | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ────────────────────────────────────────────────────────────────
  function badgeBloqueado(bloqueado) {
    return bloqueado == 1
      ? '<span class="badge badge-bloqueado">Bloqueado</span>'
      : '<span class="badge badge-ativo">Ativo</span>';
  }

  function badgeMovimento(tipo) {
    const map = {
      ENTRADA: '<span class="badge badge-mov-ENTRADA">ENTRADA</span>',
      SAIDA:   '<span class="badge badge-mov-SAIDA">SAÍDA</span>',
      AJUSTE:  '<span class="badge badge-mov-AJUSTE">AJUSTE</span>',
    };
    return map[tipo] || `<span class="badge bg-secondary">${tipo}</span>`;
  }

  function badgeUnidade(unidade_base) {
    return unidade_base === 'G'
      ? '<span class="badge bg-warning text-dark" title="Produto vendido por peso">⚖ Peso</span>'
      : '<span class="badge bg-light text-dark border">UN</span>';
  }

  function formatMoeda(valor) {
    return parseFloat(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  /**
   * Formata quantidade de estoque considerando unidade_base.
   * Produtos 'G': mostra em kg se >= 1000g, caso contrário em g.
   */
  function formatQtdEstoque(qtd, unidade_base) {
    if (unidade_base === 'G') {
      const g = parseInt(qtd) || 0;
      if (g >= 1000) {
        const kg = (g / 1000).toFixed(3).replace(/\.?0+$/, '');
        return `<span class="${g <= 0 ? 'text-danger fw-bold' : ''}">${kg} kg</span>`;
      }
      return `<span class="${g <= 0 ? 'text-danger fw-bold' : ''}">${g} g</span>`;
    }
    const n = parseInt(qtd) || 0;
    return `<span class="${n <= 0 ? 'text-danger fw-bold' : ''}">${n} UN</span>`;
  }

  // ── Select2 nos filtros ─────────────────────────────────────────────────────
  $('#filtro_status_produto').select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  $('#filtro_unidade_base').select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  $('#filtro_fornecedor').select2({
    placeholder: 'Todos os fornecedores',
    allowClear: true,
    width: '100%',
  });

  // Carrega fornecedores no filtro
  fetch('/api/fornecedores?simples=1')
    .then(r => r.json())
    .then(res => {
      const data = res.data || [];
      data.forEach(f => {
        $('#filtro_fornecedor').append(new Option(f.razao_social, f.id));
      });
      $('#filtro_fornecedor').trigger('change');
    })
    .catch(() => { /* fornecedores não implementados — filtro fica vazio */ });

  // ── Colunas da tabela ───────────────────────────────────────────────────────
  const colunas = [
    { data: 'nome' },
    {
      data: 'codigo_interno_alternativo',
      render: d => d || '<span class="text-muted">—</span>',
    },
    { data: 'preco_venda',  render: d => formatMoeda(d) },
    { data: 'custo_item',   render: d => formatMoeda(d) },
    {
      data: 'fator_embalagem',
      render: (d, type, row) => {
        if (row.unidade_base === 'G') {
          return `<span class="badge bg-secondary">${d} g/fração</span>`;
        }
        return `<span class="badge bg-secondary">${d} UN/CX</span>`;
      },
    },
    {
      data: 'unidade_base',
      render: d => badgeUnidade(d),
    },
    {
      data: 'fornecedor_nome',
      render: d => d || '<span class="text-muted">—</span>',
    },
    { data: 'bloqueado',       render: d => badgeBloqueado(d) },
    {
      data: 'ultima_alteracao',
      render: d => window.formatDateTime ? window.formatDateTime(d) : (d || '—'),
    },
    {
      data: 'quantidade_atual',
      render: (d, type, row) => formatQtdEstoque(d, row.unidade_base),
    },
    {
      data: 'id',
      orderable: false,
      searchable: false,
      render: function (data, type, row) {
        const isBloqueado = row.bloqueado == 1;
        const btnStatus = isBloqueado
          ? `<button class="btn btn-sm btn-success me-1"
               title="Desbloquear"
               onclick="toggleStatusProduto(${row.id}, '${window.escHtml(row.nome)}')">
               <i class="bi bi-unlock-fill"></i>
             </button>`
          : `<button class="btn btn-sm btn-warning me-1"
               title="Bloquear"
               onclick="toggleStatusProduto(${row.id}, '${window.escHtml(row.nome)}')">
               <i class="bi bi-lock-fill"></i>
             </button>`;

        return `
          <button class="btn btn-sm btn-primary me-1" title="Editar"
            onclick="abrirModalEdicaoProduto(
              ${row.id}, '${window.escHtml(row.nome)}',
              '${window.escHtml(String(row.codigo_interno_alternativo || ''))}',
              '${row.preco_venda}', '${row.custo_item}',
              '${row.fator_embalagem}', '${row.fornecedor_id}',
              '${row.unidade_base || 'UN'}')">
            <i class="bi bi-pencil-square"></i>
          </button>
          <button class="btn btn-sm btn-secondary me-1" title="Histórico de movimentações"
            onclick="abrirHistorico(${row.id}, '${window.escHtml(row.nome)}')">
            <i class="bi bi-clock-history"></i>
          </button>
          ${btnStatus}
          <button class="btn btn-sm btn-danger ms-1" title="Excluir"
            onclick="confirmarExclusaoProduto(${row.id}, '${window.escHtml(row.nome)}')">
            <i class="bi bi-trash-fill"></i>
          </button>`;
      },
    },
  ];

  // ── Instancia tabela ────────────────────────────────────────────────────────
  new Table(
    '#tabela-produtos',
    colunas,
    '/api/produtos',
    {
      titleFile: 'Produtos',
      filename: 'produtos',
      mergeButtons: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      ajax: {
        url: '/api/produtos',
        type: 'GET',
        data: function (d) {
          const status      = $('#filtro_status_produto').val();
          const fornecedor  = $('#filtro_fornecedor').val();
          const unidadeBase = $('#filtro_unidade_base').val();
          if (status)      d.bloqueado    = status;
          if (fornecedor)  d.fornecedor_id = fornecedor;
          if (unidadeBase) d.unidade_base  = unidadeBase;
        },
        dataSrc: function (json) {
          json.recordsTotal    = json.data.recordsTotal;
          json.recordsFiltered = json.data.recordsFiltered;
          json.draw            = json.data.draw;
          return json.data.data;
        },
      },
    }
  );

  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela-produtos').DataTable().ajax.reload();
  });

  $('#filtro_status_produto, #filtro_fornecedor, #filtro_unidade_base').on('change', () => {
    $('#tabela-produtos').DataTable().ajax.reload();
  });

  // ── Botão Criar ─────────────────────────────────────────────────────────────
  document.querySelector('[data-create]').addEventListener('click', () => {
    MODAL.openFormModal({
      title: 'Criar produto',
      method: 'POST',
      action: '/api/produtos',
      inputs: [
        { label: 'Nome do produto',            name: 'nome',                       type: 'text' },
        { label: 'Código interno alternativo', name: 'codigo_interno_alternativo', type: 'text' },
        { label: 'Preço de venda (R$)',         name: 'preco_venda',                type: 'text' },
        { label: 'Custo do item (R$)',          name: 'custo_item',                 type: 'text' },
        {
          label: 'Unidade base',
          name: 'unidade_base',
          type: 'select',
          values: {
            UN: 'UN — Unidades (contagem)',
            G:  'G  — Peso em gramas (balança)',
          },
          selectedId: 'UN',
        },
        {
          label: 'Fator de embalagem',
          name: 'fator_embalagem',
          type: 'text',
          attributesRender: { type: 'number', min: '1', value: '1' },
          // Para UN: qtd de UN por CX  |  Para G: fração mínima em gramas
        },
        { label: 'ID do Fornecedor',   name: 'fornecedor_id',    type: 'text' },
        { label: 'Cód. barras UN',     name: 'codigo_barras_un', type: 'text' },
        { label: 'Cód. barras CX',     name: 'codigo_barras_cx', type: 'text' },
        { label: 'Cód. barras KG',     name: 'codigo_barras_kg', type: 'text' },
        { label: 'Cód. barras G',      name: 'codigo_barras_g',  type: 'text' },
      ],
      submitText: 'Criar',
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
            $('#tabela-produtos').DataTable().ajax.reload();
          }
        });
      },
    });
  });

  // ── Modal de edição ─────────────────────────────────────────────────────────
  window.abrirModalEdicaoProduto = function (
    id, nome, codigoInt, preco, custo, fator, fornecedorId, unidadeBase
  ) {
    const ub = unidadeBase || 'UN';

    MODAL.openTabbedModal({
      title: `Editar produto: ${nome}`,
      size: 'lg',
      tabs: [
        {
          label: 'Dados do produto',
          method: 'PUT',
          action: '/api/produtos',
          inputs: [
            { type: 'hidden', name: 'id',                         label: '',                    value: id },
            { type: 'text',   name: 'nome',                       label: 'Nome do produto',     value: nome },
            { type: 'text',   name: 'codigo_interno_alternativo', label: 'Cód. interno',        value: codigoInt },
            { type: 'text',   name: 'preco_venda',                label: 'Preço de venda (R$)', value: preco },
            { type: 'text',   name: 'custo_item',                 label: 'Custo do item (R$)',  value: custo },
            {
              type: 'select',
              name: 'unidade_base',
              label: 'Unidade base',
              values: { UN: 'UN — Unidades', G: 'G — Peso em gramas' },
              selectedId: ub,
            },
            {
              type: 'text',
              name: 'fator_embalagem',
              label: ub === 'G' ? 'Fração mínima (g)' : 'Fator embalagem (UN/CX)',
              value: fator,
              attributesRender: { type: 'number', min: '1' },
            },
            { type: 'text',   name: 'fornecedor_id',              label: 'ID do Fornecedor',   value: fornecedorId },
            { type: 'submit', name: 'submit',                     value: 'Salvar', className: 'btn btn-primary' },
          ],
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
                $('#tabela-produtos').DataTable().ajax.reload();
              }
            });
          },
        },
        {
          label: 'Códigos de barras',
          method: 'PUT',
          action: '/api/produtos',
          inputs: [
            { type: 'hidden', name: 'id',               label: '',              value: id },
            { type: 'text',   name: 'codigo_barras_un', label: 'Cód. UN',       value: '' },
            { type: 'text',   name: 'codigo_barras_cx', label: 'Cód. CX',       value: '' },
            { type: 'text',   name: 'codigo_barras_kg', label: 'Cód. KG (balança quilos)', value: '' },
            { type: 'text',   name: 'codigo_barras_g',  label: 'Cód. G (balança gramas)',  value: '' },
            { type: 'submit', name: 'submit',            value: 'Salvar códigos', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            fetch(form.action, {
              headers: { 'Content-type': 'application/json' },
              method: 'PUT',
              body: FormData.toJSON(formData),
            })
            .then(r => r.json())
            .then(data => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') modal.hide();
            });
          },
        },
      ],
    });
  };

  // ── Toggle status ───────────────────────────────────────────────────────────
  window.toggleStatusProduto = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Alterar status do produto',
      message: `Deseja alterar o status de <strong>${nome}</strong>?`,
      bodyHTML: true,
      confirmText: 'Confirmar',
      onConfirm: (modal) => {
        fetch('/api/produtos/status', {
          headers: { 'Content-type': 'application/json' },
          method: 'PATCH',
          body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          $('#tabela-produtos').DataTable().ajax.reload();
        });
      },
    });
  };

  // ── Histórico ───────────────────────────────────────────────────────────────
  window.abrirHistorico = function (id, nome) {
    fetch(`/api/produtos/historico?id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') { new Alerta('error', '', res.message); return; }

        const historico = res.data || [];
        let linhas = '';

        if (historico.length === 0) {
          linhas = '<tr><td colspan="7" class="text-center text-muted">Nenhuma movimentação registrada.</td></tr>';
        } else {
          historico.forEach(mov => {
            const dt = window.formatDateTime ? window.formatDateTime(mov.data_movimento) : mov.data_movimento;
            // Formata quantidade com unidade
            const qtdLabel = `${mov.quantidade} ${mov.unidade_origem}`;
            linhas += `<tr>
              <td>${dt}</td>
              <td>${badgeMovimento(mov.tipo_movimento)}</td>
              <td>${qtdLabel}</td>
              <td>${mov.codigo_barras_usado || '—'}</td>
              <td>${mov.motivo || '—'}</td>
              <td><span class="badge bg-secondary">${mov.origem}</span></td>
              <td>${window.escHtml(mov.operador || '—')}</td>
            </tr>`;
          });
        }

        const html = `
          <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
            <table class="table table-sm table-striped" style="font-size:13px;">
              <thead class="table-light sticky-top">
                <tr>
                  <th>Data/Hora</th><th>Tipo</th><th>Qtd</th>
                  <th>Cód. Barras</th><th>Motivo</th><th>Origem</th><th>Operador</th>
                </tr>
              </thead>
              <tbody>${linhas}</tbody>
            </table>
          </div>`;

        MODAL.openAlertModal({
          type: 'info',
          title: `Histórico: ${nome}`,
          subtitle: html,
          confirmText: 'Fechar',
          onConfirm: () => MODAL.hideModal(),
        });
      });
  };

  // ── Excluir ─────────────────────────────────────────────────────────────────
  window.confirmarExclusaoProduto = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Excluir produto',
      message: `Deseja realmente excluir <strong>${nome}</strong>? Esta ação é irreversível.`,
      bodyHTML: true,
      confirmText: 'Sim, excluir',
      onConfirm: (modal) => {
        fetch('/api/produtos', {
          headers: { 'Content-type': 'application/json' },
          method: 'DELETE',
          body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          if (data.status === 'success') {
            $('#tabela-produtos').DataTable().ajax.reload();
          }
        });
      },
    });
  };

};