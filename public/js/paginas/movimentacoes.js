// paginas/movimentacoes.js

window.PageFunctions['movimentacoes'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    };
  }

  // Garantir a existência do Helper UI do Código de Barras
  if (typeof window.uiCodigoBarras !== 'function') {
    window.uiCodigoBarras = function(codigo) {
      if (!codigo) return '<span class="text-muted">—</span>';
      return `<span class="badge rounded-pill shadow-sm" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-family: monospace; font-size: 13px; padding: 0.35em 0.7em; font-weight: 500; letter-spacing: 0.5px;"><i class="bi bi-upc-scan me-1" style="color: #64748b;"></i>${window.escHtml(codigo)}</span>`;
    };
  }

  document.title = 'Movimentações | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  function badgeTipo(tipo) {
    const map = {
      ENTRADA: '<span class="badge badge-mov-ENTRADA"><i class="bi bi-arrow-down-left"></i> ENTRADA</span>',
      SAIDA:   '<span class="badge badge-mov-SAIDA"><i class="bi bi-arrow-up-right"></i> SAÍDA</span>',
      AJUSTE:  '<span class="badge badge-mov-AJUSTE"><i class="bi bi-arrow-repeat"></i> AJUSTE</span>',
    };
    return map[tipo] || `<span class="badge bg-secondary">${tipo}</span>`;
  }

  function fmtData(d) {
    return window.formatDateTime ? window.formatDateTime(d) : (d || '—');
  }

  function fmtQtdMov(quantidade, unidade_origem) {
    const u = (unidade_origem || 'UN').toUpperCase();
    const q = parseFloat(quantidade) || 0;

    if (u === 'KG') {
      const fmt = q % 1 === 0 ? q.toFixed(0) : q.toFixed(3).replace(/\.?0+$/, '');
      return `<span class="fw-bold">${fmt}</span> <small class="text-muted">kg</small>`;
    }
    if (u === 'G') {
      return `<span class="fw-bold">${Math.round(q)}</span> <small class="text-muted">g</small>`;
    }
    if (u === 'CX') {
      return `<span class="fw-bold">${q % 1 === 0 ? Math.round(q) : q}</span> <small class="text-muted">CX</small>`;
    }
    return `<span class="fw-bold">${Math.round(q)}</span> <small class="text-muted">UN</small>`;
  }

  ['#filtro_mov_tipo', '#filtro_mov_embalagem', '#filtro_mov_origem', '#filtro_mov_qty_op'].forEach(sel => {
    $(sel).select2({ placeholder: 'Todos', allowClear: true, width: '100%' });
  });

  $('#filtro_mov_produto').select2({
    placeholder: 'Todos os produtos', allowClear: true, width: '100%',
    ajax: {
      url: '/api/produtos', data: params => ({ search: { value: params.term || '' }, simples: 1 }),
      processResults: res => ({ results: (res.data?.data || res.data || []).map(p => ({ id: p.id, text: p.nome })) }),
    },
  });

  const colunas = [
    { data: 'produto_nome' },
    { data: 'tipo_movimento', render: d => badgeTipo(d) },
    { data: 'quantidade', render: (d, type, row) => fmtQtdMov(d, row.unidade_origem) },
    { data: 'unidade_origem', render: d => `<span class="badge bg-light text-dark border">${d}</span>` },
    { data: 'codigo_barras_usado', render: d => window.uiCodigoBarras(d) }, // Uso do novo UI de código de barras
    { data: 'motivo', render: d => d || '<span class="text-muted">—</span>' },
    { data: 'origem', render: d => `<span class="badge bg-info text-dark">${d}</span>` },
    { data: 'operador', render: d => d || '<span class="text-muted">—</span>' },
    { data: 'data_movimento', render: d => fmtData(d) },
  ];

  new Table('#tabela-movimentacoes', colunas, '/api/movimentacoes', {
      titleFile: 'Movimentações', filename: 'movimentacoes', mergeButtons: false, autoWidth: false, processing: true, serverSide: true, order: [[8, 'desc']],
      ajax: {
        url: '/api/movimentacoes', type: 'GET',
        data: function (d) {
          const produto   = $('#filtro_mov_produto').val();
          const tipo      = $('#filtro_mov_tipo').val();
          const embalagem = $('#filtro_mov_embalagem').val();
          const origem    = $('#filtro_mov_origem').val();
          const di        = $('#filtro_mov_data_inicio').val();
          const df        = $('#filtro_mov_data_fim').val();
          const qtyOp     = $('#filtro_mov_qty_op').val();
          const qtyVal    = $('#filtro_mov_qty_val').val();

          if (produto) d.produto_id = produto;
          if (tipo) d.tipo_movimento = tipo;
          if (embalagem) d.unidade_origem = embalagem;
          if (origem) d.origem = origem;
          if (di) d.data_inicio = di;
          if (df) d.data_fim = df;
          if (qtyOp && qtyVal !== '') { d.qty_op = qtyOp; d.qty_val = qtyVal; }
        },
        dataSrc: function (json) {
          json.recordsTotal = json.data.recordsTotal; json.recordsFiltered = json.data.recordsFiltered; json.draw = json.data.draw;
          return json.data.data;
        },
      },
    }
  );

  document.querySelector('[data-submit]').addEventListener('click', () => { $('#tabela-movimentacoes').DataTable().ajax.reload(); });
  $('#filtro_mov_tipo, #filtro_mov_embalagem, #filtro_mov_origem, #filtro_mov_produto, #filtro_mov_qty_op').on('change', () => { $('#tabela-movimentacoes').DataTable().ajax.reload(); });
  $('#filtro_mov_data_inicio, #filtro_mov_data_fim, #filtro_mov_qty_val').on('change input', () => { $('#tabela-movimentacoes').DataTable().ajax.reload(); });

};