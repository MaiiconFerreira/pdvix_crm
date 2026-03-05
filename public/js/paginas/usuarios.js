// usuarios.js — atualizado: botão Criar movido do HTML para cá (boas práticas)
// Badges e botões de ação atualizados para o design system.

window.PageFunctions['usuarios'] = function () {

  document.title = 'Usuários | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helper ──────────────────────────────────────────────────────────────────
  window.escHtml = function (str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
  };

  // ── Badges ──────────────────────────────────────────────────────────────────
  function badgePerfil(perfil) {
    const map = {
      operador:      '<span class="erp-badge badge-perfil-operador">Operador</span>',
      gerente:       '<span class="erp-badge badge-perfil-gerente">Gerente</span>',
      administrador: '<span class="erp-badge badge-perfil-administrador">Administrador</span>',
    };
    return map[perfil] || `<span class="erp-badge badge-neutral">${perfil}</span>`;
  }

  function badgeStatus(status) {
    return status === 'ativado'
      ? '<span class="erp-badge badge-active">Ativado</span>'
      : '<span class="erp-badge badge-inactive">Desativado</span>';
  }

  // ── Colunas ─────────────────────────────────────────────────────────────────
  const colunas = [
    { data: 'nome' },
    { data: 'login' },
    { data: 'cpf' },
    {
      data: 'email',
      render: d => d
        ? `<a href="mailto:${d}" style="color:var(--c-primary)">${d}</a>`
        : '<span class="text-muted-erp">—</span>',
    },
    {
      data: 'telefone',
      render: d => d
        ? `<a href="https://wa.me/${d}" target="_blank" style="color:var(--c-success)">${d}</a>`
        : '<span class="text-muted-erp">—</span>',
    },
    { data: 'perfil',      render: d => badgePerfil(d) },
    { data: 'status',      render: d => badgeStatus(d) },
    { data: 'data_criacao', render: d => window.formatDateTime ? window.formatDateTime(d) : (d || '—') },
    { data: 'ultimo_login', render: d => window.formatDateTime ? window.formatDateTime(d) : (d || '—') },
    {
      data: 'id',
      orderable: false, searchable: false, width: '110px',
      render: function (data, type, row) {
        const isAtivo = row.status === 'ativado';
        const btnStatus = isAtivo
          ? `<button class="btn-act btn-act-warn" title="Desativar"
               onclick="toggleStatus(${row.id}, '${escHtml(row.nome)}')">
               <i class="bi bi-pause-fill"></i>
             </button>`
          : `<button class="btn-act btn-act-success" title="Ativar"
               onclick="toggleStatus(${row.id}, '${escHtml(row.nome)}')">
               <i class="bi bi-play-fill"></i>
             </button>`;

        return `<div class="act-group">
          <button class="btn-act btn-act-edit" title="Editar"
            onclick="abrirModalEdicao(${row.id}, '${escHtml(row.nome)}', '${escHtml(row.login)}',
              '${escHtml(row.cpf)}', '${escHtml(row.email || '')}',
              '${escHtml(row.telefone || '')}', '${row.perfil}')">
            <i class="bi bi-pencil-square"></i>
          </button>
          ${btnStatus}
          <button class="btn-act btn-act-danger btn-act-danger-hover" title="Excluir"
            onclick="confirmarExclusao(${row.id}, '${escHtml(row.nome)}')">
            <i class="bi bi-trash-fill"></i>
          </button>
        </div>`;
      },
    },
  ];

  // ── Tabela ───────────────────────────────────────────────────────────────────
  const tabela = new Table('#tabela', colunas, '/api/users', {
    mergeButtons: false, autoWidth: false, processing: true, serverSide: true,
    ajax: {
      url: '/api/users', type: 'GET',
      data: function (d) {
        const perfil = $('#filtro_perfil').val();
        const status = $('#filtro_status').val();
        if (perfil) d.perfil = perfil;
        if (status) d.status = status;
      },
      dataSrc: function (json) {
        json.recordsTotal    = json.data.recordsTotal;
        json.recordsFiltered = json.data.recordsFiltered;
        json.draw            = json.data.draw;
        return json.data.data;
      },
    },
  });

  tabela.updateTableFromServer();

  // ── Eventos ──────────────────────────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela').DataTable().ajax.reload();
  });

  $('#filtro_perfil, #filtro_status').on('change', () => {
    $('#tabela').DataTable().ajax.reload();
  });

  // ── Criar usuário (movido do HTML) ───────────────────────────────────────────
  document.querySelector('[data-create]').addEventListener('click', () => {
    MODAL.openFormModal({
      title: 'Criar usuário',
      method: 'POST',
      action: '/api/users',
      inputs: [
        { label: 'Nome completo', name: 'nome',     type: 'text' },
        { label: 'Login',        name: 'login',    type: 'text' },
        { label: 'CPF (somente números)', name: 'cpf', type: 'text',
          attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` } },
        { label: 'E-mail',   name: 'email',    type: 'email' },
        { label: 'Telefone (somente números)', name: 'telefone', type: 'text',
          attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` } },
        { label: 'Senha', name: 'senha', type: 'password' },
        { label: 'Perfil', name: 'perfil', type: 'select',
          values: { operador: 'Operador', gerente: 'Gerente', administrador: 'Administrador' } },
      ],
      submitText: 'Criar',
      onSubmit: (formData, form, modal) => {
        fetch(form.action, {
          headers: { 'Content-type': 'application/json' },
          method: 'POST',
          body: FormData.toJSON(formData),
        })
        .then(res => res.json())
        .then(data => {
          new Alerta(data.status, '', data.message);
          if (data.status === 'success') {
            modal.hide();
            $('#tabela').DataTable().ajax.reload();
          }
        });
      },
    });
  });

  // ── Editar ───────────────────────────────────────────────────────────────────
  window.abrirModalEdicao = function (id, nome, login, cpf, email, telefone, perfil) {
    MODAL.openTabbedModal({
      title: `Editar usuário: ${nome}`, size: 'lg',
      tabs: [
        {
          label: 'Identificação', method: 'PUT', action: '/api/users',
          inputs: [
            { type: 'hidden', name: 'id', label: '', value: id },
            { type: 'text', name: 'nome', label: 'Nome completo', value: nome },
            { type: 'text', name: 'login', label: 'Login', value: login },
            { type: 'text', name: 'cpf', label: 'CPF (somente números)', value: cpf,
              attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` } },
            { type: 'email', name: 'email', label: 'E-mail', value: email },
            { type: 'text', name: 'telefone', label: 'Telefone (somente números)', value: telefone,
              attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` } },
            { type: 'select', name: 'perfil', label: 'Perfil', value: perfil,
              values: { operador: 'Operador', gerente: 'Gerente', administrador: 'Administrador' } },
            { type: 'submit', name: 'submit', value: 'Salvar', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'PUT', body: FormData.toJSON(formData) })
            .then(res => res.json()).then(data => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') { modal.hide(); $('#tabela').DataTable().ajax.reload(); }
            });
          },
        },
        {
          label: 'Senha', method: 'PUT', action: '/api/users',
          inputs: [
            { type: 'hidden', name: 'id', label: '', value: id },
            { type: 'password', name: 'senha', label: 'Nova senha', value: '' },
            { type: 'submit', name: 'submit', value: 'Alterar senha', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            fetch(form.action, { headers: { 'Content-type': 'application/json' }, method: 'PUT', body: FormData.toJSON(formData) })
            .then(res => res.json()).then(data => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') modal.hide();
            });
          },
        },
      ],
    });
  };

  // ── Toggle status ─────────────────────────────────────────────────────────────
  window.toggleStatus = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Alterar status',
      message: `Deseja alterar o status de <strong>${nome}</strong>?`,
      bodyHTML: true, confirmText: 'Confirmar',
      onConfirm: (modal) => {
        fetch('/api/users/status', { headers: { 'Content-type': 'application/json' }, method: 'PATCH', body: JSON.stringify({ id }) })
        .then(res => res.json()).then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          $('#tabela').DataTable().ajax.reload();
        });
      },
    });
  };

  // ── Excluir ───────────────────────────────────────────────────────────────────
  window.confirmarExclusao = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Excluir usuário',
      message: `Deseja realmente excluir <strong>${nome}</strong>? Esta ação é irreversível.`,
      bodyHTML: true, confirmText: 'Sim, excluir',
      onConfirm: (modal) => {
        fetch('/api/users', { headers: { 'Content-type': 'application/json' }, method: 'DELETE', body: JSON.stringify({ id }) })
        .then(res => res.json()).then(data => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          $('#tabela').DataTable().ajax.reload();
        });
      },
    });
  };

};
