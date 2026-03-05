// paginas/estoque.js
// Orquestração da tela de Estoque

window.PageFunctions['estoque'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    };
  }

  document.title = 'Estoque | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers ─────────────────────────────────────────────────────────────────
  function badgeBloqueado(v) {
    return v == 1
      ? '<span class="badge badge-bloqueado">Bloqueado</span>'
      : '<span class="badge badge-ativo">Ativo</span>';
  }

  function fmtData(d) {
    return window.formatDateTime ? window.formatDateTime(d) : (d || '—');
  }

  /**
   * Exibe quantidade no formato correto para a unidade_base do produto.
   *   unidade_base='G' e qtd >= 1000 → "1,500 kg"
   *   unidade_base='G' e qtd <  1000 → "500 g"
   *   unidade_base='UN'               → "120 UN"
   */
  function fmtQtd(qtd, unidade_base) {
    if (unidade_base === 'G') {
      const g = parseInt(qtd) || 0;
      if (g >= 1000) {
        const kg = (g / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        return `<span class="${g <= 0 ? 'estoque-zero' : 'fw-bold'}">${kg} kg</span>`;
      }
      return `<span class="${g <= 0 ? 'estoque-zero' : 'fw-bold'}">${g} g</span>`;
    }
    const n = parseInt(qtd) || 0;
    return `<span class="${n <= 0 ? 'estoque-zero' : 'fw-bold'}">${n} UN</span>`;
  }

  function fmtFator(fator, unidade_base) {
    if (unidade_base === 'G') {
      return `<span class="badge bg-secondary">${fator} g/fração</span>`;
    }
    return `<span class="badge bg-secondary">${fator} UN/CX</span>`;
  }

  function badgeUnidade(unidade_base) {
    return unidade_base === 'G'
      ? '<span class="badge bg-warning text-dark">⚖ Peso</span>'
      : '<span class="badge bg-light text-dark border">UN</span>';
  }

  // ── Select2 filtros ─────────────────────────────────────────────────────────
  $('#filtro_est_status').select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  $('#filtro_est_unidade_base').select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  $('#filtro_est_embalagem').select2({ placeholder: 'Todas', allowClear: true, width: '100%' });

  // ── Colunas ─────────────────────────────────────────────────────────────────
  const colunas = [
    {
      data: 'produto_nome',
    },
    {
      data: 'fator_embalagem',
      render: (d, type, row) => fmtFator(d, row.unidade_base),
    },
    {
      data: 'unidade_base',
      render: d => badgeUnidade(d),
    },
    {
      data: 'codigos_barras',
      render: d => d
        ? d.split(' | ').map(c => `<span class="badge bg-light text-dark border me-1">${window.escHtml(c)}</span>`).join('')
        : '<span class="text-muted">—</span>',
      orderable: false,
    },
    {
      data: 'quantidade_atual',
      render: (d, type, row) => fmtQtd(d, row.unidade_base),
    },
    {
      data: 'ultima_movimentacao',
      render: d => fmtData(d),
    },
    {
      data: 'data_atualizacao',
      render: d => fmtData(d),
    },
    {
      data: 'bloqueado',
      render: d => badgeBloqueado(d),
    },
    {
      data: 'produto_id',
      orderable: false,
      searchable: false,
      render: (data, type, row) =>
        `<button class="btn btn-sm btn-primary"
           title="Movimentar"
           onclick="abrirMovimentar(${row.produto_id}, '${window.escHtml(row.produto_nome)}', '${row.unidade_base || 'UN'}')">
           <i class="bi bi-arrow-left-right"></i>
         </button>`,
    },
  ];

  // ── Instancia tabela ────────────────────────────────────────────────────────
  new Table(
    '#tabela-estoque',
    colunas,
    '/api/estoque',
    {
      titleFile: 'Estoque',
      filename: 'estoque',
      mergeButtons: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      ajax: {
        url: '/api/estoque',
        type: 'GET',
        data: function (d) {
          const status      = $('#filtro_est_status').val();
          const unidadeBase = $('#filtro_est_unidade_base').val();
          const embalagem   = $('#filtro_est_embalagem').val();
          const qtyOp       = $('#filtro_qty_op').val();
          const qtyVal      = $('#filtro_qty_val').val();

          if (status)      d.bloqueado      = status;
          if (unidadeBase) d.unidade_base   = unidadeBase;
          if (embalagem)   d.tipo_embalagem = embalagem;
          if (qtyOp && qtyVal !== '') {
            d.qty_op  = qtyOp;
            d.qty_val = qtyVal;
          }
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

  // ── Recarregar e filtros ────────────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela-estoque').DataTable().ajax.reload();
  });

  $('#filtro_est_status, #filtro_est_unidade_base, #filtro_est_embalagem').on('change', () => {
    $('#tabela-estoque').DataTable().ajax.reload();
  });

  $('#filtro_qty_op, #filtro_qty_val').on('change input', () => {
    $('#tabela-estoque').DataTable().ajax.reload();
  });

  // ── Botão Movimentar (genérico) ─────────────────────────────────────────────
  document.querySelector('[data-movimentar]').addEventListener('click', () => {
    abrirMovimentar(null, null, null);
  });

  // ── Modal de movimentação ────────────────────────────────────────────────────
  /**
   * @param {number|null}  produtoIdPresel  - pré-seleciona o produto
   * @param {string|null}  produtoNomePresel
   * @param {string|null}  unidadeBase      - 'UN' | 'G' | null (desconhecido)
   */
  window.abrirMovimentar = function (produtoIdPresel, produtoNomePresel, unidadeBase) {
    // Define as opções de unidade disponíveis conforme o tipo do produto
    // Se unidadeBase for null (botão genérico), mostra todas para o usuário escolher
    let valoresUnidade;
    if (unidadeBase === 'G') {
      valoresUnidade = {
        KG: 'KG — Quilograma (ex: 1.5 para 1500g)',
        G:  'G  — Grama (ex: 500)',
      };
    } else if (unidadeBase === 'UN') {
      valoresUnidade = {
        UN: 'UN — Unidade',
        CX: 'CX — Caixa (multiplica pelo fator_embalagem)',
      };
    } else {
      // Produto desconhecido — exibe todas as opções
      valoresUnidade = {
        UN: 'UN — Unidade',
        CX: 'CX — Caixa',
        KG: 'KG — Quilograma',
        G:  'G  — Grama',
      };
    }

    const inputs = [
      {
        label: 'Produto',
        name: 'produto_id',
        type: 'select2',
        fetchURI: '/api/produtos?simples=1',
        placeholder: 'Buscar produto...',
        processItem: item => ({ id: item.id, text: item.nome }),
        selectedId: produtoIdPresel || undefined,
      },
      {
        label: 'Tipo de movimento',
        name: 'tipo_movimento',
        type: 'select',
        values: {
          ENTRADA: 'ENTRADA',
          SAIDA:   'SAÍDA',
          AJUSTE:  'AJUSTE (saldo exato na unidade base)',
        },
      },
      {
        label: 'Quantidade',
        name: 'quantidade',
        type: 'text',
        // Para KG permite decimais (ex.: 1.5)
        attributesRender: { type: 'number', min: '0.001', step: '0.001' },
      },
      {
        label: 'Unidade de origem',
        name: 'unidade_origem',
        type: 'select',
        values: valoresUnidade,
      },
      { label: 'Código de barras usado', name: 'codigo_barras_usado', type: 'text' },
      { label: 'Motivo (opcional)',       name: 'motivo',              type: 'text' },
      {
        label: 'Origem',
        name: 'origem',
        type: 'select',
        values: {
          COMPRA:    'COMPRA',
          AJUSTE:    'AJUSTE',
          DEVOLUCAO: 'DEVOLUÇÃO',
          ESTORNO:   'ESTORNO',
          VENDA:     'VENDA',
        },
        selectedId: 'AJUSTE',
      },
    ];

    MODAL.openFormModal({
      title: produtoNomePresel
        ? `Movimentar: ${produtoNomePresel}`
        : 'Registrar movimentação',
      method: 'POST',
      action: '/api/estoque/movimentar',
      inputs,
      submitText: 'Registrar',
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
            $('#tabela-estoque').DataTable().ajax.reload();
          }
        });
      },
    });
  };

};