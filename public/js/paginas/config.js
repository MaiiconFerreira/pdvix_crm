/**
 * config.js — PDVix CRM
 * Tela de configurações do sistema.
 * Acesso restrito a perfil 'administrador' (verificado também no back-end).
 */

'use strict';

// ─── Helpers ──────────────────────────────────────────────────────────────────

const api = {
  async get(url) {
    const r = await fetch(url, { credentials: 'same-origin' });
    return r.json();
  },
  async post(url, body) {
    const r = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return r.json();
  },
  async put(url, body) {
    const r = await fetch(url, {
      method: 'PUT', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return r.json();
  },
  async delete(url, body) {
    const r = await fetch(url, {
      method: 'DELETE', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return r.json();
  },
};

function toastOk(msg)  { toastr?.success(msg) || alert('✓ ' + msg); }
function toastErr(msg) { toastr?.error(msg)   || alert('✗ ' + msg); }

function setLoading(btn, loading) {
  btn.disabled = loading;
  btn.innerHTML = loading
    ? '<i class="fas fa-spinner fa-spin mr-1"></i>Salvando...'
    : '<i class="fas fa-save mr-1"></i>Salvar';
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  verificarPermissao();
  carregarConfigs();
  carregarMaquininhas();
  bindEventos();
});

async function verificarPermissao() {
  // Checa perfil do usuário logado via session (o back-end já bloqueia o endpoint,
  // mas também escondemos o conteúdo no front para UX adequada).
  try {
    const resp = await api.get('/api/users?length=1');
    // Se chegou aqui é porque tem sessão. O back-end retorna 403 se não for admin.
  } catch {
    // ignore
  }
}

// ─── Configurações gerais / pagamento ─────────────────────────────────────────

async function carregarConfigs() {
  const resp = await api.get('/api/config');
  if (resp.status !== 'success') {
    if (resp.status === 'error' && resp.message?.includes('perfil')) {
      document.getElementById('alert-sem-permissao').classList.remove('d-none');
      document.getElementById('conteudo-config').classList.add('d-none');
    }
    return;
  }

  const cfg = resp.data;

  // Preenche campos que NÃO são sensíveis
  document.querySelectorAll('[data-chave]').forEach(el => {
    const chave = el.dataset.chave;
    if (cfg[chave] && !cfg[chave].sensivel) {
      el.value = cfg[chave].valor ?? '';
    }
    // Campos sensíveis ficam com placeholder indicativo (não preenche o valor)
  });
}

async function salvarGrupo(inputIds) {
  const configs = [];
  for (const id of inputIds) {
    const el    = document.getElementById(id);
    const chave = el?.dataset?.chave;
    const valor = el?.value?.trim();
    if (!chave) continue;
    // Pula campos senha vazia (não sobrescreve com vazio)
    if (el.type === 'password' && !valor) continue;
    configs.push({ chave, valor });
  }

  if (!configs.length) {
    toastOk('Nenhuma alteração para salvar.');
    return;
  }

  const resp = await api.post('/api/config', { configs });
  if (resp.status === 'success') {
    toastOk('Configurações salvas com sucesso!');
    carregarConfigs();
  } else {
    toastErr(resp.message || 'Erro ao salvar.');
  }
}

// ─── Maquininhas ─────────────────────────────────────────────────────────────

let maquininhaSendoEditada = null;

async function carregarMaquininhas() {
  const resp = await api.get('/api/config/maquininhas');
  const tbody = document.getElementById('tbody-maquininhas');

  if (resp.status !== 'success') {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${resp.message || 'Erro ao carregar'}</td></tr>`;
    return;
  }

  const lista = resp.data;

  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhuma maquininha cadastrada.</td></tr>';
    return;
  }

  const tipoBadge = {
    debit:           '<span class="badge badge-info">Débito</span>',
    credit:          '<span class="badge badge-primary">Crédito</span>',
    pix:             '<span class="badge badge-success">PIX</span>',
    voucher:         '<span class="badge badge-warning">Voucher</span>',
    operador_escolhe:'<span class="badge badge-secondary">Operador escolhe</span>',
  };

  tbody.innerHTML = lista.map(m => `
    <tr>
      <td><strong>${escapeHtml(m.nome)}</strong></td>
      <td><code>${escapeHtml(m.device_serial_number)}</code></td>
      <td>${escapeHtml(m.loja_nome || m.loja_id)}</td>
      <td>${tipoBadge[m.tipo_padrao] || m.tipo_padrao}</td>
      <td>
        ${m.status === 'ativa'
          ? '<span class="badge badge-success">Ativa</span>'
          : '<span class="badge badge-danger">Inativa</span>'}
      </td>
      <td>
        <button class="btn btn-xs btn-warning mr-1 btn-editar-maq" data-id="${m.id}" title="Editar">
          <i class="fas fa-pencil-alt"></i>
        </button>
        <button class="btn btn-xs btn-danger btn-excluir-maq" data-id="${m.id}" data-nome="${escapeHtml(m.nome)}" title="Excluir">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>
  `).join('');
}

function abrirModalMaquininha(maq = null) {
  maquininhaSendoEditada = maq?.id ?? null;

  document.getElementById('modal-maquininha-titulo').textContent =
    maq ? 'Editar Maquininha' : 'Nova Maquininha';

  document.getElementById('maq-id').value          = maq?.id ?? '';
  document.getElementById('maq-nome').value         = maq?.nome ?? '';
  document.getElementById('maq-serial').value       = maq?.device_serial_number ?? '';
  document.getElementById('maq-descricao').value    = maq?.descricao ?? '';
  document.getElementById('maq-tipo-padrao').value  = maq?.tipo_padrao ?? 'operador_escolhe';
  document.getElementById('maq-status').value       = maq?.status ?? 'ativa';

  // Loja
  const lojaSelect = document.getElementById('maq-loja-id');
  if (maq?.loja_id) lojaSelect.value = maq.loja_id;

  $('#modal-maquininha').modal('show');
}

async function salvarMaquininha() {
  const payload = {
    nome:                 document.getElementById('maq-nome').value.trim(),
    device_serial_number: document.getElementById('maq-serial').value.trim(),
    loja_id:              parseInt(document.getElementById('maq-loja-id').value),
    descricao:            document.getElementById('maq-descricao').value.trim(),
    tipo_padrao:          document.getElementById('maq-tipo-padrao').value,
    status:               document.getElementById('maq-status').value,
  };

  if (!payload.nome || !payload.device_serial_number) {
    toastErr('Nome e Serial são obrigatórios.');
    return;
  }

  const btn  = document.getElementById('btn-salvar-maquininha');
  setLoading(btn, true);

  let resp;
  if (maquininhaSendoEditada) {
    payload.id = maquininhaSendoEditada;
    resp = await api.put('/api/config/maquininhas', payload);
  } else {
    resp = await api.post('/api/config/maquininhas', payload);
  }

  setLoading(btn, false);

  if (resp.status === 'success') {
    toastOk(maquininhaSendoEditada ? 'Maquininha atualizada!' : 'Maquininha cadastrada!');
    $('#modal-maquininha').modal('hide');
    carregarMaquininhas();
  } else {
    toastErr(resp.message || 'Erro ao salvar maquininha.');
  }
}

async function excluirMaquininha(id, nome) {
  if (!confirm(`Excluir a maquininha "${nome}"? Esta ação não pode ser desfeita.`)) return;

  const resp = await api.delete('/api/config/maquininhas', { id });
  if (resp.status === 'success') {
    toastOk('Maquininha removida.');
    carregarMaquininhas();
  } else {
    toastErr(resp.message || 'Erro ao excluir.');
  }
}

// ─── Bind de eventos ─────────────────────────────────────────────────────────

function bindEventos() {
  // Salvar Geral
  document.getElementById('btn-salvar-geral').addEventListener('click', async function () {
    setLoading(this, true);
    await salvarGrupo(['cfg-nome_loja','cfg-cnpj_loja','cfg-telefone_loja','cfg-endereco_loja','cfg-email_loja','cfg-logo_url']);
    setLoading(this, false);
  });

  // Salvar Pagamento
  document.getElementById('btn-salvar-pagamento').addEventListener('click', async function () {
    setLoading(this, true);
    await salvarGrupo(['cfg-pagarme_api_key','cfg-pagarme_webhook_secret','cfg-pagarme_customer_id','cfg-pix_expiracao_segundos','cfg-api_token']);
    setLoading(this, false);
  });

  // Toggle visibilidade de senhas
  document.querySelectorAll('.btn-toggle-senha').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.querySelector('i').className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
  });

  // Nova maquininha
  document.getElementById('btn-nova-maquininha').addEventListener('click', () => {
    abrirModalMaquininha(null);
  });

  // Salvar maquininha (modal)
  document.getElementById('btn-salvar-maquininha').addEventListener('click', salvarMaquininha);

  // Editar / Excluir maquininha (delegação)
  document.getElementById('tbody-maquininhas').addEventListener('click', async e => {
    const btnEditar  = e.target.closest('.btn-editar-maq');
    const btnExcluir = e.target.closest('.btn-excluir-maq');

    if (btnEditar) {
      const id   = parseInt(btnEditar.dataset.id);
      const resp = await api.get('/api/config/maquininhas');
      if (resp.status === 'success') {
        const maq = resp.data.find(m => m.id === id);
        if (maq) abrirModalMaquininha(maq);
      }
    }

    if (btnExcluir) {
      excluirMaquininha(parseInt(btnExcluir.dataset.id), btnExcluir.dataset.nome);
    }
  });

  // Recarrega maquininhas ao entrar na tab
  document.getElementById('tab-maquininhas-link').addEventListener('shown.bs.tab', () => {
    carregarMaquininhas();
  });
}

// ─── Util ─────────────────────────────────────────────────────────────────────

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}