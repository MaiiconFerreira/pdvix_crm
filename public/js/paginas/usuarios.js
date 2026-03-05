// paginas/usuarios.js
// Orquestração da tela de Usuários — REST /api/users

window.PageFunctions['usuarios'] = function () {

  // ── Título da página ────────────────────────────────────────────────────────
  document.title = 'Usuários | PDV + CRM';
  const titlePage = document.querySelector('[app-title-page]');
  if (titlePage) titlePage.innerText = '';

  // ── Helpers de badge ────────────────────────────────────────────────────────
  function badgePerfil(perfil) {
    const map = {
      operador:      '<span class="badge badge-perfil-operador">Operador</span>',
      gerente:       '<span class="badge badge-perfil-gerente">Gerente</span>',
      administrador: '<span class="badge badge-perfil-administrador">Administrador</span>',
    };
    return map[perfil] || `<span class="badge bg-secondary">${perfil}</span>`;
  }

  function badgeStatus(status) {
    return status === 'ativado'
      ? '<span class="badge bg-success">Ativado</span>'
      : '<span class="badge bg-danger">Desativado</span>';
  }

  // ── Colunas do DataTable ────────────────────────────────────────────────────
  const colunas = [
    { data: 'nome' },
    { data: 'login' },
    { data: 'cpf' },
    {
      data: 'email',
      render: (data) => data
        ? `<a href="mailto:${data}">${data}</a>`
        : '<span class="text-muted">—</span>',
    },
    {
      data: 'telefone',
      render: (data) => data
        ? `<a href="https://wa.me/${data}" target="_blank">${data}</a>`
        : '<span class="text-muted">—</span>',
    },
    {
      data: 'perfil',
      render: (data) => badgePerfil(data),
    },
    {
      data: 'status',
      render: (data) => badgeStatus(data),
    },
    {
      data: 'data_criacao',
      render: (data) => window.formatDateTime ? window.formatDateTime(data) : (data || '—'),
    },
    {
      data: 'ultimo_login',
      render: (data) => window.formatDateTime ? window.formatDateTime(data) : (data || '—'),
    },
    {
      data: 'id',
      orderable: false,
      searchable: false,
      width: '150px',
      render: function (data, type, row) {
        const isAtivo     = row.status === 'ativado';
        const btnStatus   = isAtivo
          ? `<button class="btn btn-sm btn-warning"
               data-toggle="tooltip" title="Desativar"
               onclick="toggleStatus(${row.id}, '${row.nome}')">
               <i class="bi bi-hand-thumbs-down-fill"></i>
             </button>`
          : `<button class="btn btn-sm btn-success"
               data-toggle="tooltip" title="Ativar"
               onclick="toggleStatus(${row.id}, '${row.nome}')">
               <i class="bi bi-hand-thumbs-up-fill"></i>
             </button>`;

        return `
          <button class="btn btn-sm btn-primary me-1"
            data-toggle="tooltip" title="Editar"
            onclick="abrirModalEdicao(${row.id}, '${escHtml(row.nome)}', '${escHtml(row.login)}',
              '${escHtml(row.cpf)}', '${escHtml(row.email || '')}',
              '${escHtml(row.telefone || '')}', '${row.perfil}')">
            <i class="bi bi-pencil-square"></i>
          </button>
          ${btnStatus}
          <button class="btn btn-sm btn-danger ms-1"
            data-toggle="tooltip" title="Excluir"
            onclick="confirmarExclusao(${row.id}, '${escHtml(row.nome)}')">
            <i class="bi bi-trash-fill"></i>
          </button>`;
      },
    },
  ];

  // ── Instancia tabela ────────────────────────────────────────────────────────
  const tabela = new Table(
    '#tabela',
    colunas,
    '/api/users',
    {
      mergeButtons: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      ajax: {
        url: '/api/users',
        type: 'GET',
        data: function (d) {
          const perfil = $('#filtro_perfil').val();
          const status = $('#filtro_status').val();
          if (perfil) d.perfil = perfil;
          if (status) d.status = status;
        },
        dataSrc: function (json) {
          // Nosso responseJson encapsula dentro de json.data
          json.recordsTotal    = json.data.recordsTotal;
          json.recordsFiltered = json.data.recordsFiltered;
          json.draw            = json.data.draw;
          return json.data.data;
        },
      },
    }
  );

  tabela.updateTableFromServer();

  // ── Evento do botão Recarregar ──────────────────────────────────────────────
  document.querySelector('[data-submit]').addEventListener('click', () => {
    $('#tabela').DataTable().ajax.reload();
  });

  // ── Filtros em tempo real ───────────────────────────────────────────────────
  $('#filtro_perfil, #filtro_status').on('change', function () {
    $('#tabela').DataTable().ajax.reload();
  });

  // ── Helper: escapa HTML para usar em atributos onclick ──────────────────────
  window.escHtml = function (str) {
    if (!str) return '';
    return String(str)
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'")
      .replace(/"/g, '&quot;');
  };

  // ── Modal de edição ─────────────────────────────────────────────────────────
  window.abrirModalEdicao = function (id, nome, login, cpf, email, telefone, perfil) {
    MODAL.openTabbedModal({
      title: `Editar usuário: ${nome}`,
      size: 'lg',
      tabs: [
        {
          label: 'Identificação',
          method: 'PUT',
          action: '/api/users',
          inputs: [
            { type: 'hidden', name: 'id',       label: '', value: id },
            { type: 'text',   name: 'nome',      label: 'Nome completo',  value: nome },
            { type: 'text',   name: 'login',     label: 'Login',          value: login },
            {
              type: 'text', name: 'cpf', label: 'CPF (somente números)', value: cpf,
              attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` },
            },
            { type: 'email',  name: 'email',     label: 'E-mail',         value: email },
            {
              type: 'text', name: 'telefone', label: 'Telefone (somente números)', value: telefone,
              attributesRender: { onblur: `this.value = this.value.replace(/[^0-9]/g, '');` },
            },
            {
              type: 'select', name: 'perfil', label: 'Perfil', value: perfil,
              values: {
                'operador':      'Operador',
                'gerente':       'Gerente',
                'administrador': 'Administrador',
              },
            },
            { type: 'submit', name: 'submit', value: 'Salvar', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            fetch(form.action, {
              headers: { 'Content-type': 'application/json' },
              method: 'PUT',
              body: FormData.toJSON(formData),
            })
            .then(res => res.json())
            .then((data) => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') {
                modal.hide();
                $('#tabela').DataTable().ajax.reload();
              }
            });
          },
        },
        {
          label: 'Senha',
          method: 'PUT',
          action: '/api/users',
          inputs: [
            { type: 'hidden',   name: 'id',    label: '', value: id },
            { type: 'password', name: 'senha', label: 'Nova senha', value: '' },
            { type: 'submit',   name: 'submit', value: 'Alterar senha', className: 'btn btn-primary' },
          ],
          onSubmit: (formData, form, modal) => {
            fetch(form.action, {
              headers: { 'Content-type': 'application/json' },
              method: 'PUT',
              body: FormData.toJSON(formData),
            })
            .then(res => res.json())
            .then((data) => {
              new Alerta(data.status, '', data.message);
              if (data.status === 'success') modal.hide();
            });
          },
        },
      ],
    });
  };

  // ── Toggle status (PATCH /api/users/status) ─────────────────────────────────
  window.toggleStatus = function (id, nome) {
    MODAL.openConfirmationModal({
      title: 'Alterar status',
      message: `Deseja alterar o status de <strong>${nome}</strong>?`,
      bodyHTML: true,
      confirmText: 'Confirmar',
      onConfirm: (modal) => {
        fetch('/api/users/status', {
          headers: { 'Content-type': 'application/json' },
          method: 'PATCH',
          body: JSON.stringify({ id }),
        })
        .then(res => res.json())
        .then((data) => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          $('#tabela').DataTable().ajax.reload();
        });
      },
    });
  };

  // ── Confirmar exclusão (DELETE /api/users) ───────────────────────────────────
  window.confirmarExclusao = function (id, nome) {
    MODAL.openConfirmationModal({
      title: `Excluir usuário`,
      message: `Deseja realmente excluir <strong>${nome}</strong>? Esta ação é irreversível.`,
      bodyHTML: true,
      confirmText: 'Sim, excluir',
      onConfirm: (modal) => {
        fetch('/api/users', {
          headers: { 'Content-type': 'application/json' },
          method: 'DELETE',
          body: JSON.stringify({ id }),
        })
        .then(res => res.json())
        .then((data) => {
          new Alerta(data.status, '', data.message);
          modal.hide();
          $('#tabela').DataTable().ajax.reload();
        });
      },
    });
  };

};