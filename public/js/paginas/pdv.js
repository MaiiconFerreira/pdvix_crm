// paginas/pdv.js
// PDV — Ponto de Venda
// Reutiliza rotas: /api/produtos, /api/vendas, /api/vendas/finalizar, /api/pagamentos

window.PageFunctions['pdv'] = function () {

  if (typeof window.escHtml !== 'function') {
    window.escHtml = function (str) {
      if (!str) return '';
      return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    };
  }

  document.title = 'PDV | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Estado local ────────────────────────────────────────────────────────────
  let carrinho    = [];   // [{ produto_id, produto_nome, quantidade, valor_unitario }]
  let pagamentos  = [];   // [{ tipo_pagamento, valor }]

  // ── Helpers de formatação ───────────────────────────────────────────────────
  function fmtR$(v) {
    return parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  const TIPO_LABEL = {
    pix: 'PIX', convenio: 'Convênio', pos_debito: 'POS Débito',
    pos_credito: 'POS Crédito', pos_pix: 'POS PIX', dinheiro: 'Dinheiro', outros: 'Outros',
  };

  // ── Select2 — busca de produto ──────────────────────────────────────────────
  $('#pdv-busca-produto').select2({
    placeholder: 'Buscar produto por nome...',
    allowClear: true,
    width: '100%',
    ajax: {
      url: '/api/produtos?simples=1',
      dataType: 'json',
      delay: 200,
      data: params => ({ search: { value: params.term || '' } }),
      processResults: res => ({
        results: (res.data?.data || res.data || []).map(p => ({
          id: p.id, text: p.nome, preco: p.preco_venda,
        })),
      }),
    },
  }).on('select2:select', function (e) {
    document.getElementById('pdv-add-preco').value = parseFloat(e.params.data.preco || 0).toFixed(2);
    document.getElementById('pdv-add-qtd').focus();
  });

  // ── Adicionar produto ao carrinho ───────────────────────────────────────────
  document.getElementById('btn-add-ao-carrinho').addEventListener('click', () => {
    const prodId = $('#pdv-busca-produto').val();
    const nome   = $('#pdv-busca-produto option:selected').text();
    const qtd    = parseInt(document.getElementById('pdv-add-qtd').value);
    const preco  = parseFloat(document.getElementById('pdv-add-preco').value);

    if (!prodId) {
      new Alerta('warning', '', 'Selecione um produto.');
      return;
    }
    if (!qtd || qtd <= 0) {
      new Alerta('warning', '', 'Informe uma quantidade válida.');
      return;
    }
    if (!preco || preco < 0) {
      new Alerta('warning', '', 'Informe um preço válido.');
      return;
    }

    // Se produto já está no carrinho, incrementa quantidade
    const existe = carrinho.find(i => i.produto_id == prodId);
    if (existe) {
      existe.quantidade += qtd;
    } else {
      carrinho.push({ produto_id: prodId, produto_nome: nome, quantidade: qtd, valor_unitario: preco });
    }

    // Reseta campos
    $('#pdv-busca-produto').val(null).trigger('change');
    document.getElementById('pdv-add-qtd').value   = 1;
    document.getElementById('pdv-add-preco').value = '';

    renderCarrinho();
  });

  // ── Renderiza tabela do carrinho ────────────────────────────────────────────
  function renderCarrinho() {
    const tbody = document.getElementById('tbody-carrinho');
    const emptyRow = document.getElementById('cart-empty-row');

    if (carrinho.length === 0) {
      tbody.innerHTML = `<tr id="cart-empty-row">
        <td colspan="5" class="text-center text-muted py-4">
          <i class="bi bi-cart-x" style="font-size:2rem;"></i><br>Carrinho vazio
        </td></tr>`;
      atualizarTotais();
      return;
    }

    tbody.innerHTML = carrinho.map((item, i) => {
      const sub = item.quantidade * item.valor_unitario;
      return `<tr>
        <td>${window.escHtml(item.produto_nome)}</td>
        <td>
          <input type="number" class="form-control form-control-sm text-center"
            style="width:60px" min="1" value="${item.quantidade}"
            onchange="window._pdvUpdateQtd(${i}, this.value)">
        </td>
        <td class="text-end">${fmtR$(item.valor_unitario)}</td>
        <td class="text-end fw-bold">${fmtR$(sub)}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-danger cart-table"
            onclick="window._pdvRemoveItem(${i})">
            <i class="bi bi-x-lg"></i>
          </button>
        </td>
      </tr>`;
    }).join('');

    atualizarTotais();
  }

  window._pdvUpdateQtd = function (i, val) {
    const q = parseInt(val);
    if (q > 0) { carrinho[i].quantidade = q; renderCarrinho(); }
  };

  window._pdvRemoveItem = function (i) {
    carrinho.splice(i, 1);
    renderCarrinho();
    renderPagamentos();
  };

  // ── Totais ──────────────────────────────────────────────────────────────────
  function calcSubtotal() {
    return carrinho.reduce((acc, i) => acc + i.quantidade * i.valor_unitario, 0);
  }

  function calcTotal() {
    const desc = parseFloat(document.getElementById('pdv-desconto').value)  || 0;
    const acr  = parseFloat(document.getElementById('pdv-acrescimo').value) || 0;
    return Math.max(0, calcSubtotal() - desc + acr);
  }

  function calcTotalPago() {
    return pagamentos.reduce((acc, p) => acc + p.valor, 0);
  }

  function atualizarTotais() {
    document.getElementById('pdv-subtotal').textContent = fmtR$(calcSubtotal());
    document.getElementById('pdv-total').textContent    = fmtR$(calcTotal());
    const troco   = calcTotalPago() - calcTotal();
    const trocoEl = document.getElementById('troco-box');
    const pagoEl  = document.getElementById('pdv-total-pago');
    pagoEl.textContent  = fmtR$(calcTotalPago());
    trocoEl.textContent = fmtR$(Math.abs(troco));
    trocoEl.className   = 'fw-bold ' + (troco < 0 ? 'negativo' : 'positivo');
  }

  ['pdv-desconto', 'pdv-acrescimo'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
      atualizarTotais();
      renderPagamentos();
    });
  });

  // ── Adicionar pagamento ─────────────────────────────────────────────────────
  document.getElementById('btn-add-pagamento').addEventListener('click', () => {
    const tipo  = document.getElementById('pdv-tipo-pagamento').value;
    const valor = parseFloat(document.getElementById('pdv-valor-pgt').value);

    if (!tipo)           { new Alerta('warning', '', 'Selecione o tipo de pagamento.'); return; }
    if (!valor || valor <= 0) { new Alerta('warning', '', 'Informe um valor válido.');   return; }

    pagamentos.push({ tipo_pagamento: tipo, valor });

    document.getElementById('pdv-tipo-pagamento').value = '';
    document.getElementById('pdv-valor-pgt').value      = '';

    renderPagamentos();
    atualizarTotais();
  });

  function renderPagamentos() {
    const container = document.getElementById('lista-pagamentos-pdv');
    if (pagamentos.length === 0) {
      container.innerHTML = '<p class="text-muted small mb-0">Nenhum pagamento adicionado.</p>';
      atualizarTotais();
      return;
    }
    container.innerHTML = pagamentos.map((p, i) =>
      `<div class="payment-item">
        <span><strong>${TIPO_LABEL[p.tipo_pagamento] || p.tipo_pagamento}</strong> — ${fmtR$(p.valor)}</span>
        <button class="btn btn-sm btn-outline-danger btn-remove-pgt"
          onclick="window._pdvRemovePgt(${i})">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>`
    ).join('');
    atualizarTotais();
  }

  window._pdvRemovePgt = function (i) {
    pagamentos.splice(i, 1);
    renderPagamentos();
  };

  // ── Limpar tudo ─────────────────────────────────────────────────────────────
  document.getElementById('btn-limpar-carrinho').addEventListener('click', () => {
    MODAL.openConfirmationModal({
      title: 'Limpar carrinho',
      message: 'Deseja limpar o carrinho e os pagamentos?',
      confirmText: 'Limpar',
      onConfirm: (modal) => {
        limparPDV();
        modal.hide();
      },
    });
  });

  function limparPDV() {
    carrinho   = [];
    pagamentos = [];
    document.getElementById('pdv-desconto').value  = 0;
    document.getElementById('pdv-acrescimo').value = 0;
    renderCarrinho();
    renderPagamentos();
  }

  // ── Finalizar venda ─────────────────────────────────────────────────────────
  document.getElementById('btn-finalizar-venda').addEventListener('click', () => {
    // Validações front-end
    if (carrinho.length === 0) {
      new Alerta('warning', '', 'Adicione ao menos um produto ao carrinho.');
      return;
    }

    const total     = calcTotal();
    const totalPago = calcTotalPago();

    if (totalPago < total) {
      new Alerta('warning', '', `Total pago (${fmtR$(totalPago)}) é menor que o total da venda (${fmtR$(total)}). Adicione mais pagamentos.`);
      return;
    }

    MODAL.openConfirmationModal({
      title: 'Finalizar Venda',
      message: `Confirmar venda de <strong>${fmtR$(total)}</strong>?`,
      bodyHTML: true,
      confirmText: 'Confirmar',
      onConfirm: async (modal) => {
        modal.hide();

        try {
          // 1. Cria a venda (itens + baixa de estoque)
          const bodyVenda = {
            desconto:  parseFloat(document.getElementById('pdv-desconto').value)  || 0,
            acrescimo: parseFloat(document.getElementById('pdv-acrescimo').value) || 0,
            itens: carrinho.map(i => ({
              produto_id:     i.produto_id,
              quantidade:     i.quantidade,
              valor_unitario: i.valor_unitario,
            })),
          };

          const resVenda = await fetch('/api/vendas', {
            headers: { 'Content-type': 'application/json' },
            method: 'POST',
            body: JSON.stringify(bodyVenda),
          }).then(r => r.json());

          if (resVenda.status !== 'success') {
            new Alerta('error', '', resVenda.message || 'Erro ao criar venda.');
            return;
          }

          const vendaId = resVenda.data.id;

          // 2. Registra os pagamentos
          for (const pgt of pagamentos) {
            await fetch('/api/pagamentos', {
              headers: { 'Content-type': 'application/json' },
              method: 'POST',
              body: JSON.stringify({
                venda_id:      vendaId,
                tipo_pagamento: pgt.tipo_pagamento,
                valor:          pgt.valor,
                status:         'confirmado',
              }),
            }).then(r => r.json());
          }

          // 3. Finaliza a venda
          const resFinal = await fetch('/api/vendas/finalizar', {
            headers: { 'Content-type': 'application/json' },
            method: 'POST',
            body: JSON.stringify({ id: vendaId }),
          }).then(r => r.json());

          if (resFinal.status !== 'success') {
            new Alerta('warning', '', `Venda criada (#${vendaId}), mas não foi possível finalizar automaticamente: ${resFinal.message}`);
          } else {
            new Alerta('success', '', `Venda finalizada com sucesso! Troco: ${fmtR$(totalPago - total)}`);
          }

          limparPDV();

        } catch (err) {
          console.error('PDV error:', err);
          new Alerta('error', '', 'Erro inesperado ao processar a venda. Tente novamente.');
        }
      },
    });
  });

  // ── Inicialização ────────────────────────────────────────────────────────────
  renderCarrinho();
  renderPagamentos();
};