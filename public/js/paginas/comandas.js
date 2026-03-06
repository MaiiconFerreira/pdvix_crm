'use strict';
// comandas.js

window.PageFunctions['comandas'] = function () {

  document.title = 'Comandas | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  const brl   = v => parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const fmtDt = d => window.formatDateTime ? window.formatDateTime(d) : (d || '—');

  // ── Estado do carrinho da nova comanda ───────────────────────────────────────
  let itensComanda = [];

  // ── Badges ───────────────────────────────────────────────────────────────────
  function badgeComanda(s) {
    const map = {
      aberta:    '<span class="erp-badge badge-comanda-aberta">Aberta</span>',
      enviada:   '<span class="erp-badge badge-comanda-enviada">Enviada</span>',
      cancelada: '<span class="erp-badge badge-comanda-cancelada">Cancelada</span>',
    };
    return map[s] || `<span class="erp-badge badge-neutral">${s}</span>`;
  }

  // ── DataTable ────────────────────────────────────────────────────────────────
  const tabela = $('#tabela-comandas').DataTable({
    processing: true,
    ajax: {
      url: '/api/comandas', type: 'GET',
      dataSrc: function (json) { return json.data || []; },
    },
    order: [[5, 'desc']],
    language: { url: '/template/datatables_pt-BR.json' },
    columns: [
      { data: 'numero',
        render: d => `<code style="font-size:12px; background:var(--c-bg); padding:2px 8px; border-radius:4px; font-weight:700;">${window.escHtml(d)}</code>` },
      { data: 'cliente_nome', render: d => d || '<span style="color:var(--c-text-3)">Consumidor</span>' },
      { data: 'operador_nome', render: d => d || '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'pdv_destino',
        render: d => d
          ? `<span class="erp-badge badge-info">PDV ${window.escHtml(d)}</span>`
          : '<span style="color:var(--c-text-3)">—</span>' },
      { data: 'status', render: d => badgeComanda(d) },
      { data: 'created_at', render: d => fmtDt(d) },
      {
        data: null, orderable: false, width: '120px',
        render: (d, t, row) => {
          const isAberta = row.status === 'aberta';
          return `<div class="act-group">
            <button class="btn-act btn-act-edit" title="Ver itens"
              onclick="verItensComanda(${row.id}, '${window.escHtml(row.numero)}')">
              <i class="bi bi-eye"></i>
            </button>
            ${isAberta ? `
            <button class="btn-act btn-act-success" title="Enviar para PDV"
              onclick="abrirEnvioComanda(${row.id}, '${window.escHtml(row.numero)}')">
              <i class="bi bi-send"></i>
            </button>
            <button class="btn-act btn-act-danger btn-act-danger-hover" title="Cancelar comanda"
              onclick="cancelarComanda(${row.id}, '${window.escHtml(row.numero)}')">
              <i class="bi bi-x-lg"></i>
            </button>` : ''}
          </div>`;
        },
      },
    ],
  });

  // ── Filtros ───────────────────────────────────────────────────────────────────
  $('#btn-comanda-filtrar').on('click', () => tabela.ajax.reload());
  $('#filtro-comanda-status').on('change', () => tabela.ajax.reload());
  $('#filtro-comanda-busca').on('input', function () {
    tabela.search(this.value).draw();
  });
  $('#btn-comanda-limpar').on('click', () => {
    $('#filtro-comanda-status').val('aberta');
    $('#filtro-comanda-busca').val('');
    tabela.search('').ajax.reload();
  });
  document.querySelector('[data-submit]').addEventListener('click', () => tabela.ajax.reload());

  // ── Criar Comanda ────────────────────────────────────────────────────────────
  $('#btn-criar-comanda').on('click', () => {
    itensComanda = [];
    abrirModalNovaComanda();
  });

  function abrirModalNovaComanda() {
    MODAL.openFormModal({
      title: 'Nova Comanda',
      method: 'POST',
      action: '/api/comandas',
      size: 'lg',
      inputs: [
        { label: 'Nome do cliente (opcional)', name: 'cliente_nome', type: 'text',
          attributesRender: { placeholder: 'Ex: Mesa 5, João...' } },
      ],
      submitText: 'Criar Comanda',
      onOpen: (modalEl) => {
        // Injeta área de itens no modal
        const form = modalEl.querySelector('form');
        form.insertAdjacentHTML('beforeend', `
          <hr style="border-color:var(--c-border); margin: var(--space-md) 0;">
          <div style="font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--c-text-3); margin-bottom:12px;">
            <i class="bi bi-box-seam me-1" style="color:var(--c-primary);"></i> Itens da Comanda
          </div>
          <div style="display:grid; grid-template-columns: 1fr 80px 100px auto; gap:8px; margin-bottom:12px; align-items:end;">
            <div>
              <label class="erp-label">Produto</label>
              <select id="comanda-select-produto" style="width:100%;"></select>
            </div>
            <div>
              <label class="erp-label">Qtd</label>
              <input type="number" id="comanda-qtd" class="erp-input" value="1" min="0.001" step="0.001">
            </div>
            <div>
              <label class="erp-label">Preço (R$)</label>
              <input type="number" id="comanda-preco" class="erp-input" step="0.01" min="0">
            </div>
            <div>
              <button type="button" id="btn-add-item-comanda" class="btn-erp btn-erp-primary" style="height:36px; white-space:nowrap;">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          </div>
          <div class="erp-table-wrapper" style="max-height:220px; overflow-y:auto;">
            <table class="erp-table" id="tabela-itens-comanda">
              <thead>
                <tr>
                  <th>Produto</th>
                  <th style="text-align:center;">Qtd</th>
                  <th style="text-align:right;">Unitário</th>
                  <th style="text-align:right;">Subtotal</th>
                  <th style="text-align:center;">—</th>
                </tr>
              </thead>
              <tbody id="tbody-comanda"></tbody>
              <tfoot>
                <tr style="background:var(--c-bg);">
                  <td colspan="3" style="text-align:right; font-weight:700; padding:8px 14px;">Total:</td>
                  <td id="comanda-total" style="text-align:right; font-weight:800; color:var(--c-navy); padding:8px 14px;">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        `);

        // Select2 para produtos
        $('#comanda-select-produto').select2({
          placeholder: 'Buscar produto...',
          allowClear: true,
          width: '100%',
          dropdownParent: $(modalEl),
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
          $('#comanda-preco').val(parseFloat(e.params.data.preco || 0).toFixed(2));
        });

        // Adicionar item
        document.getElementById('btn-add-item-comanda').addEventListener('click', () => {
          const prodId   = $('#comanda-select-produto').val();
          const nome     = $('#comanda-select-produto option:selected').text();
          const qtd      = parseFloat($('#comanda-qtd').val()) || 0;
          const preco    = parseFloat($('#comanda-preco').val()) || 0;

          if (!prodId || qtd <= 0 || preco <= 0) {
            new Alerta('warning', '', 'Preencha produto, quantidade e preço.');
            return;
          }

          itensComanda.push({ produto_id: prodId, produto_nome: nome, quantidade: qtd, valor_unitario: preco });
          renderItensComanda();
          $('#comanda-select-produto').val(null).trigger('change');
          $('#comanda-qtd').val('1');
          $('#comanda-preco').val('');
        });

        renderItensComanda();
      },
      onSubmit: (formData, form, modal) => {
        if (itensComanda.length === 0) {
          new Alerta('warning', '', 'Adicione ao menos um item à comanda.');
          return;
        }
        const body = {
          cliente_nome: formData.get('cliente_nome') || null,
          itens: itensComanda,
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
            tabela.ajax.reload();
          }
        });
      },
    });
  }

  function renderItensComanda() {
    const tbody = document.getElementById('tbody-comanda');
    if (!tbody) return;
    let total = 0;
    tbody.innerHTML = itensComanda.map((item, i) => {
      const sub = item.quantidade * item.valor_unitario;
      total += sub;
      return `<tr>
        <td>${window.escHtml(item.produto_nome)}</td>
        <td style="text-align:center;">${item.quantidade}</td>
        <td style="text-align:right;">${brl(item.valor_unitario)}</td>
        <td style="text-align:right; font-weight:700;">${brl(sub)}</td>
        <td style="text-align:center;">
          <button type="button" class="btn-act btn-act-danger btn-act-danger-hover"
            style="width:24px; height:24px; padding:0;"
            onclick="window._removeItemComanda(${i})">
            <i class="bi bi-trash" style="font-size:10px;"></i>
          </button>
        </td>
      </tr>`;
    }).join('');
    const totalEl = document.getElementById('comanda-total');
    if (totalEl) totalEl.textContent = brl(total);
  }

  window._removeItemComanda = function (i) {
    itensComanda.splice(i, 1);
    renderItensComanda();
  };

  // ── Ver itens da comanda ─────────────────────────────────────────────────────
  window.verItensComanda = function (id, numero) {
    fetch(`/api/comandas?id=${id}`)
      .then(r => r.json())
      .then(res => {
        const comanda = (res.data || []).find(c => c.id == id);
        if (!comanda) { new Alerta('error', '', 'Comanda não encontrada.'); return; }

        // Carrega itens via GET (simples, sem endpoint de itens)
        // Mostra info que temos
        const html = `
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
            <div>
              <div style="font-size:0.72rem; color:var(--c-text-3); font-weight:600; text-transform:uppercase; margin-bottom:4px;">Cliente</div>
              <div style="font-size:14px; font-weight:600;">${window.escHtml(comanda.cliente_nome || 'Consumidor Final')}</div>
            </div>
            <div>
              <div style="font-size:0.72rem; color:var(--c-text-3); font-weight:600; text-transform:uppercase; margin-bottom:4px;">PDV Destino</div>
              <div>${comanda.pdv_destino
                ? `<span class="erp-badge badge-info">PDV ${window.escHtml(comanda.pdv_destino)}</span>`
                : '<span style="color:var(--c-text-3)">Não enviada</span>'}</div>
            </div>
          </div>
          <p style="font-size:13px; color:var(--c-text-3); text-align:center; padding:16px; background:var(--c-bg); border-radius:var(--radius-md);">
            <i class="bi bi-info-circle me-1"></i>
            Para ver os itens detalhados, implemente <code>/api/comandas/itens?id=${id}</code> no backend.
          </p>`;

        MODAL.openContentModal({
          title: `Comanda <span style="color:var(--c-primary)">${numero}</span>`,
          size: 'md', message: html, buttonText: 'Fechar',
        });
      });
  };

  // ── Enviar comanda para PDV ──────────────────────────────────────────────────
  window.abrirEnvioComanda = function (id, numero) {
    fetch('/api/lojas/pdvs')
      .then(r => r.json())
      .then(res => {
        const pdvs = (res.data || []).filter(p => p.status === 'ativo');
        if (!pdvs.length) {
          new Alerta('warning', '', 'Nenhum PDV ativo encontrado.');
          return;
        }
        const vals = {};
        pdvs.forEach(p => { vals[`${p.loja_id}:${p.numero_pdv}`] = `PDV ${p.numero_pdv} — ${p.loja_nome || p.loja_id}`; });

        MODAL.openFormModal({
          title: `Enviar Comanda ${numero} → PDV`,
          method: 'POST', action: '/api/comandas/enviar',
          inputs: [
            { type: 'hidden', name: 'comanda_id', value: id },
            {
              label: 'PDV de destino',
              name: '_pdv_key',
              type: 'select',
              values: vals,
            },
          ],
          submitText: 'Enviar',
          onSubmit: (formData, form, modal) => {
            const [lojaId, numeroPdv] = (formData.get('_pdv_key') || ':').split(':');
            const body = { comanda_id: id, loja_id: parseInt(lojaId), numero_pdv: numeroPdv };
            fetch(form.action, {
              headers: { 'Content-type': 'application/json' },
              method: 'POST',
              body: JSON.stringify(body),
            })
            .then(r => r.json())
            .then(data => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') { modal.hide(); tabela.ajax.reload(); }
            });
          },
        });
      });
  };

  // ── Cancelar comanda ─────────────────────────────────────────────────────────
  window.cancelarComanda = function (id, numero) {
    MODAL.openConfirmationModal({
      title: 'Cancelar Comanda',
      message: `Deseja realmente cancelar a comanda <strong>${numero}</strong>?`,
      bodyHTML: true, confirmText: 'Sim, cancelar',
      onConfirm: modal => {
        fetch('/api/comandas', {
          headers: { 'Content-type': 'application/json' },
          method: 'DELETE',
          body: JSON.stringify({ id }),
        })
        .then(r => r.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          if (data.status === 'success') tabela.ajax.reload();
        });
      },
    });
  };
};
