'use strict';
// lojas.js

window.PageFunctions['lojas'] = function () {

  document.title = 'Lojas & PDVs | PDVix CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  if (typeof window.escHtml !== 'function') {
    window.escHtml = str => !str ? '' : String(str)
      .replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  }

  // ── Badges ───────────────────────────────────────────────────────────────────
  function badgeStatusLoja(s) {
    return s === 'ativa'
      ? '<span class="erp-badge badge-active">Ativa</span>'
      : '<span class="erp-badge badge-inactive">Inativa</span>';
  }

  // ── Lojas (cards) ────────────────────────────────────────────────────────────
  function carregarLojas() {
    fetch('/api/lojas')
      .then(r => r.json())
      .then(res => {
        if (res.status !== 'success') {
          $('#lojas-grid-container').html('<p style="color:var(--c-danger); padding:16px;">Erro ao carregar lojas.</p>');
          return;
        }
        const lojas = res.data || [];

        if (!lojas.length) {
          $('#lojas-grid-container').html(`
            <div style="text-align:center; padding:48px; color:var(--c-text-3);">
              <i class="bi bi-shop" style="font-size:2.5rem; opacity:0.25;"></i>
              <p style="margin-top:12px; font-size:13px;">Nenhuma loja cadastrada.</p>
            </div>`);
          return;
        }

        const html = `<div class="lojas-grid">` + lojas.map(l => `
          <div class="loja-card">
            <div class="loja-card-header">
              <div>
                <div class="loja-card-name"><i class="bi bi-shop me-2" style="opacity:.7;"></i>${window.escHtml(l.nome)}</div>
                <div class="loja-card-cnpj">${l.cnpj || '—'}</div>
              </div>
              <div style="display:flex; gap:6px;">
                <button class="btn-act btn-act-edit" title="Editar loja" style="background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25);"
                  onclick="editarLoja(${l.id}, '${window.escHtml(l.nome)}', '${window.escHtml(l.cnpj||'')}',
                    '${window.escHtml(l.endereco||'')}', '${window.escHtml(l.numero||'')}',
                    '${window.escHtml(l.cidade||'')}', '${window.escHtml(l.estado||'')}',
                    '${window.escHtml(l.cep||'')}', '${window.escHtml(l.telefone||'')}', '${l.status}')">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn-act btn-act-danger btn-act-danger-hover" title="Excluir loja"
                  style="background:rgba(255,255,255,.1); color:#fca5a5; border-color:rgba(252,165,165,.3);"
                  onclick="excluirLoja(${l.id}, '${window.escHtml(l.nome)}')">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </div>
            </div>
            <div class="loja-card-body">
              ${l.endereco ? `<div class="loja-info-row"><i class="bi bi-geo-alt"></i><span>${window.escHtml(l.endereco)}${l.numero ? ', ' + l.numero : ''} — ${window.escHtml(l.cidade || '')} ${l.estado ? '/' + l.estado : ''}</span></div>` : ''}
              ${l.telefone ? `<div class="loja-info-row"><i class="bi bi-telephone"></i><span>${window.escHtml(l.telefone)}</span></div>` : ''}
              <div class="loja-info-row"><i class="bi bi-circle-fill" style="font-size:8px; margin-top:4px;"></i><span>${badgeStatusLoja(l.status)}</span></div>
            </div>
            <div class="loja-card-footer">
              <span class="loja-pdv-count"><i class="bi bi-display me-1"></i>${l.total_pdvs || 0} PDV(s) cadastrado(s)</span>
            </div>
          </div>`).join('') + `</div>`;

        $('#lojas-grid-container').html(html);
      })
      .catch(() => {
        $('#lojas-grid-container').html('<p style="color:var(--c-danger); padding:16px;">Falha na requisição.</p>');
      });
  }

  // ── Tabela PDVs ──────────────────────────────────────────────────────────────
  function initTabelaPdvs() {
    if ($.fn.DataTable.isDataTable('#tabela-pdvs')) {
      $('#tabela-pdvs').DataTable().ajax.reload();
      return;
    }

    $('#tabela-pdvs').DataTable({
      processing: true,
      ajax: {
        url: '/api/lojas/pdvs',
        type: 'GET',
        dataSrc: res => res.data || [],
      },
      order: [[1, 'asc'], [0, 'asc']],
      language: { url: '/template/datatables_pt-BR.json' },
      columns: [
        { data: 'numero_pdv', render: d => `<span class="erp-badge badge-info">PDV ${d}</span>` },
        { data: 'loja_nome',  render: d => d || '—' },
        { data: 'descricao',  render: d => d || '<span style="color:var(--c-text-3)">—</span>' },
        { data: 'url_local',  render: d => d
            ? `<code style="font-size:11px; background:var(--c-bg); padding:2px 6px; border-radius:4px;">${window.escHtml(d)}</code>`
            : '<span style="color:var(--c-text-3)">—</span>' },
        { data: 'status',     render: d => d === 'ativo'
            ? '<span class="erp-badge badge-active">Ativo</span>'
            : '<span class="erp-badge badge-inactive">Inativo</span>' },
        {
          data: null, orderable: false, width: '80px',
          render: (d, t, row) => `<div class="act-group">
            <button class="btn-act btn-act-edit" title="Editar"
              onclick="editarPdv(${row.id}, '${window.escHtml(row.descricao||'')}', '${window.escHtml(row.url_local||'')}', '${row.status}')">
              <i class="bi bi-pencil-square"></i>
            </button>
          </div>`,
        },
      ],
    });
  }

  // ── Criar Loja ───────────────────────────────────────────────────────────────
  function inputsLoja(vals = {}) {
    return [
      { label: 'Nome da Loja',    name: 'nome',     type: 'text', attributesRender: { value: vals.nome     || '' } },
      { label: 'CNPJ',           name: 'cnpj',     type: 'text', attributesRender: { value: vals.cnpj     || '', placeholder: '00.000.000/0001-00' } },
      { label: 'Endereço',       name: 'endereco', type: 'text', attributesRender: { value: vals.endereco || '' } },
      { label: 'Número',         name: 'numero',   type: 'text', attributesRender: { value: vals.numero   || '' } },
      { label: 'Cidade',         name: 'cidade',   type: 'text', attributesRender: { value: vals.cidade   || '' } },
      { label: 'Estado (UF)',    name: 'estado',   type: 'text', attributesRender: { value: vals.estado   || '', maxlength: 2 } },
      { label: 'CEP',            name: 'cep',      type: 'text', attributesRender: { value: vals.cep      || '' } },
      { label: 'Telefone',       name: 'telefone', type: 'text', attributesRender: { value: vals.telefone || '' } },
    ];
  }

  $('#btn-criar-loja').on('click', () => {
    MODAL.openFormModal({
      title: 'Nova Loja', method: 'POST', action: '/api/lojas',
      inputs: inputsLoja(), submitText: 'Criar Loja',
      onSubmit: (formData, form, modal) => {
        const body = {};
        formData.forEach((v, k) => { if (v.trim()) body[k] = v; });
        fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'POST', body: JSON.stringify(body) })
          .then(r => r.json())
          .then(data => {
            new Alerta(data.status, '', data.message);
            if (data.status === 'success') { modal.hide(); recarregar(); }
          });
      },
    });
  });

  window.editarLoja = function (id, nome, cnpj, endereco, numero, cidade, estado, cep, telefone, status) {
    MODAL.openTabbedModal({
      title: `Editar: ${nome}`, size: 'lg',
      tabs: [
        {
          label: 'Dados', method: 'PUT', action: '/api/lojas',
          inputs: [
            { type: 'hidden', name: 'id', value: id },
            ...inputsLoja({ nome, cnpj, endereco, numero, cidade, estado, cep, telefone }),
            { label: 'Status', name: 'status', type: 'select', values: { ativa: 'Ativa', inativa: 'Inativa' }, selectedId: status },
            { type: 'submit', name: 'submit', value: 'Salvar', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            const body = {};
            formData.forEach((v, k) => { body[k] = v; });
            fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'PUT', body: JSON.stringify(body) })
              .then(r => r.json())
              .then(data => { new Alerta(data.status, '', data.message); if (data.status === 'success') { modal.hide(); recarregar(); } });
          },
        },
      ],
    });
  };

  window.excluirLoja = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Excluir Loja',
      message: `Deseja realmente excluir a loja <strong>${nome}</strong>? Esta ação não poderá ser desfeita.`,
      bodyHTML: true, confirmText: 'Sim, excluir',
      onConfirm: modal => {
        fetch('/api/lojas', { headers: { 'Content-type': 'application/json' }, method: 'DELETE', body: JSON.stringify({ id }) })
          .then(r => r.json())
          .then(data => { new Alerta(data.status, '', data.message); modal.hide(); if (data.status === 'success') recarregar(); });
      },
    });
  };

  // ── Criar PDV ────────────────────────────────────────────────────────────────
  function abrirModalCriarPdv() {
    fetch('/api/lojas')
      .then(r => r.json())
      .then(res => {
        const lojas = res.data || [];
        const valoresLoja = {};
        lojas.forEach(l => { valoresLoja[l.id] = l.nome; });

        MODAL.openFormModal({
          title: 'Cadastrar PDV', method: 'POST', action: '/api/lojas/pdvs',
          inputs: [
            { label: 'Loja',        name: 'loja_id',    type: 'select', values: valoresLoja },
            { label: 'Nº PDV',      name: 'numero_pdv', type: 'text', attributesRender: { placeholder: 'Ex: 01' } },
            { label: 'Descrição',   name: 'descricao',  type: 'text', attributesRender: { placeholder: 'Ex: Caixa Principal' } },
            { label: 'URL Local',   name: 'url_local',  type: 'text', attributesRender: { placeholder: 'Ex: http://192.168.1.10:3000' } },
          ],
          submitText: 'Cadastrar',
          onSubmit: (formData, form, modal) => {
            const body = {};
            formData.forEach((v, k) => { if (v.trim()) body[k] = v; });
            fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'POST', body: JSON.stringify(body) })
              .then(r => r.json())
              .then(data => { new Alerta(data.status, '', data.message); if (data.status === 'success') { modal.hide(); recarregarPdvs(); } });
          },
        });
      });
  }

  window.editarPdv = function (id, descricao, urlLocal, status) {
    MODAL.openFormModal({
      title: 'Editar PDV', method: 'PUT', action: '/api/lojas/pdvs',
      inputs: [
        { type: 'hidden', name: 'id', value: id },
        { label: 'Descrição',  name: 'descricao', type: 'text', attributesRender: { value: descricao } },
        { label: 'URL Local',  name: 'url_local',  type: 'text', attributesRender: { value: urlLocal } },
        { label: 'Status', name: 'status', type: 'select', values: { ativo: 'Ativo', inativo: 'Inativo' }, selectedId: status },
      ],
      submitText: 'Salvar',
      onSubmit: (formData, form, modal) => {
        const body = {};
        formData.forEach((v, k) => { body[k] = v; });
        fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'PUT', body: JSON.stringify(body) })
          .then(r => r.json())
          .then(data => { new Alerta(data.status, '', data.message); if (data.status === 'success') { modal.hide(); recarregarPdvs(); } });
      },
    });
  };

  // ── Eventos ──────────────────────────────────────────────────────────────────
  $('#btn-criar-pdv').on('click', abrirModalCriarPdv);
  $('#btn-reload-lojas').on('click', recarregar);

  function recarregarPdvs() {
    if ($.fn.DataTable.isDataTable('#tabela-pdvs')) {
      $('#tabela-pdvs').DataTable().ajax.reload();
    }
  }

  function recarregar() {
    carregarLojas();
    recarregarPdvs();
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  carregarLojas();
  initTabelaPdvs();
};
