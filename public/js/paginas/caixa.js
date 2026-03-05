'use strict';
// caixa.js — badges e botões atualizados para o design system

// ── Helpers ───────────────────────────────────────────────────────────────────

function brl(v) {
  return parseFloat(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fmtDt(str) {
  if (!str) return '—';
  const d = new Date(str.replace(' ', 'T'));
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fmtDuracao(aberturaStr, fechamentoStr) {
  if (!aberturaStr || !fechamentoStr) return '—';
  const a = new Date(aberturaStr.replace(' ', 'T'));
  const f = new Date(fechamentoStr.replace(' ', 'T'));
  const diff = Math.floor((f - a) / 1000);
  const h = Math.floor(diff / 3600);
  const m = Math.floor((diff % 3600) / 60);
  return h + 'h ' + String(m).padStart(2, '0') + 'min';
}

function badgeStatus(status) {
  return status === 'aberto'
    ? '<span class="erp-badge badge-active">Aberto</span>'
    : '<span class="erp-badge badge-neutral">Fechado</span>';
}

function badgeDiferenca(diff) {
  if (diff === null || diff === undefined || diff === '') return '<span class="text-muted-erp">—</span>';
  const v = parseFloat(diff);
  if (Math.abs(v) < 0.01) return '<span class="erp-badge badge-active">Zerado</span>';
  if (v < 0) return `<span class="erp-badge badge-inactive">Quebra ${brl(Math.abs(v))}</span>`;
  return `<span class="erp-badge badge-pending">Sobra ${brl(v)}</span>`;
}

// ── DataTable ─────────────────────────────────────────────────────────────────

let tabela;

function initTabela() {
  tabela = $('#tabela-caixas').DataTable({
    processing: true, serverSide: true, pageLength: 25, order: [[3, 'desc']],
    language: { url: '/assets/plugins/datatables/pt-BR.json' },
    ajax: {
      url: '/api/pdv/caixas', type: 'GET',
      data: function (d) {
        d.status      = $('#filtro-status').val();
        d.numero_pdv  = $('#filtro-numero-pdv').val().trim();
        d.data_inicio = $('#filtro-data-inicio').val();
        d.data_fim    = $('#filtro-data-fim').val();
        return d;
      },
    },
    columns: [
      { data: 'id', width: '50px' },
      { data: 'numero_pdv', width: '70px',
        render: d => `<span class="erp-badge badge-info">PDV ${d}</span>` },
      { data: 'operador_nome' },
      { data: 'abertura_em',   render: d => fmtDt(d) },
      { data: 'fechamento_em', render: d => fmtDt(d) },
      { data: 'total_vendas', className: 'text-center',
        render: d => `<span class="erp-badge badge-info">${d}</span>` },
      { data: 'total_geral',   render: d => `<strong>${brl(d)}</strong>` },
      { data: 'total_sangrias', render: d => parseFloat(d) > 0 ? `<span style="color:var(--c-warning);font-weight:600">${brl(d)}</span>` : '<span class="text-muted-erp">—</span>' },
      { data: 'saldo_esperado', render: d => brl(d) },
      { data: 'diferenca',     render: d => badgeDiferenca(d) },
      { data: 'status',        render: d => badgeStatus(d) },
      {
        data: null, orderable: false, width: '60px',
        render: function (row) {
          return `<button class="btn-act btn-act-edit btn-detalhe" data-id="${row.id}" title="Ver detalhes">
                    <i class="bi bi-eye"></i>
                  </button>`;
        },
      },
    ],
    drawCallback: function () {
      atualizarCards(this.api().ajax.json());
    },
  });
}

// ── Cards ─────────────────────────────────────────────────────────────────────

function atualizarCards(json) {
  if (!json || !json.data) return;
  const rows = json.data;
  const hoje = new Date().toISOString().slice(0, 10);
  const rowsHoje = rows.filter(r => r.abertura_em && r.abertura_em.slice(0, 10) === hoje);

  $('#card-total-caixas').text(rowsHoje.length);
  $('#card-total-vendas').text(rowsHoje.reduce((s, r) => s + parseInt(r.total_vendas || 0), 0));
  $('#card-total-sangrias').text(brl(rowsHoje.reduce((s, r) => s + parseFloat(r.total_sangrias || 0), 0)));
  $('#card-caixas-abertos').text(rowsHoje.filter(r => r.status === 'aberto').length);
}

// ── Modal Detalhe ─────────────────────────────────────────────────────────────

$(document).on('click', '.btn-detalhe', function () {
  carregarDetalhe($(this).data('id'));
});

function carregarDetalhe(id) {
  $('#modal-detalhe-body').html(`
    <div style="text-align:center;padding:40px;">
      <i class="bi bi-arrow-repeat" style="font-size:2rem;color:var(--c-primary);animation:spin 1s linear infinite;"></i>
      <p style="color:var(--c-text-3);margin-top:12px;">Carregando...</p>
    </div>
    <style>@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}</style>
  `);
  $('#modal-detalhe-caixa').modal('show');
  $('#btn-imprimir-espelho').data('id', id);

  $.get('/api/pdv/caixas/detalhe', { id })
    .done(resp => {
      if (resp.status !== 'success') {
        $('#modal-detalhe-body').html('<div style="color:var(--c-danger);padding:16px;">Erro ao carregar detalhe.</div>');
        return;
      }
      renderDetalhe(resp.data.caixa, resp.data.sangrias);
    })
    .fail(() => {
      $('#modal-detalhe-body').html('<div style="color:var(--c-danger);padding:16px;">Falha na requisição.</div>');
    });
}

function renderDetalhe(c, sangrias) {
  const total_geral = [c.total_dinheiro, c.total_pix, c.total_debito, c.total_credito, c.total_convenio, c.total_outros]
    .reduce((s, v) => s + parseFloat(v || 0), 0);

  const diferencaHtml = (() => {
    if (c.diferenca === null || c.diferenca === '') return '—';
    const v = parseFloat(c.diferenca);
    if (Math.abs(v) < 0.01) return '<span style="color:var(--c-success);font-weight:700">✓ Zerado</span>';
    if (v < 0) return `<span style="color:var(--c-danger);font-weight:700">⚠ Quebra de ${brl(Math.abs(v))}</span>`;
    return `<span style="color:var(--c-warning);font-weight:700">↑ Sobra de ${brl(v)}</span>`;
  })();

  let sangriasHtml = '<p class="text-muted-erp" style="text-align:center;padding:16px;">Nenhuma sangria nesta sessão.</p>';
  if (sangrias && sangrias.length > 0) {
    sangriasHtml = `
      <div class="erp-table-wrapper">
        <table class="erp-table">
          <thead><tr><th>Data/Hora</th><th>Operador</th><th>Valor</th><th>Motivo</th></tr></thead>
          <tbody>
            ${sangrias.map(s => `<tr>
              <td>${fmtDt(s.data_hora)}</td>
              <td>${s.operador_nome || '—'}</td>
              <td><strong style="color:var(--c-warning)">${brl(s.valor)}</strong></td>
              <td>${s.motivo || '—'}</td>
            </tr>`).join('')}
          </tbody>
          <tfoot>
            <tr style="background:var(--c-bg)">
              <td colspan="2" style="text-align:right;font-weight:600;padding:10px 14px;">Total Sangrias:</td>
              <td style="font-weight:700;color:var(--c-warning);padding:10px 14px;">${brl(c.total_sangrias)}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>`;
  }

  $('#modal-detalhe-body').html(`
    <div class="caixa-detalhe-grid">
      <div>
        <dl class="caixa-dl">
          <dt>PDV</dt><dd><span class="erp-badge badge-info">PDV ${c.numero_pdv}</span></dd>
          <dt>Operador</dt><dd>${c.operador_nome || '—'}</dd>
          <dt>Abertura</dt><dd>${fmtDt(c.abertura_em)}</dd>
          <dt>Fechamento</dt><dd>${fmtDt(c.fechamento_em)}</dd>
          <dt>Duração</dt><dd>${fmtDuracao(c.abertura_em, c.fechamento_em)}</dd>
        </dl>
      </div>
      <div>
        <dl class="caixa-dl">
          <dt>Status</dt><dd>${badgeStatus(c.status)}</dd>
          <dt>Vendas realizadas</dt><dd><span class="erp-badge badge-info">${c.total_vendas}</span></dd>
          <dt>Canceladas</dt><dd><span class="erp-badge badge-neutral">${c.total_canceladas}</span></dd>
          <dt>Sync em</dt><dd>${fmtDt(c.sincronizado_em)}</dd>
        </dl>
      </div>
    </div>

    <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:var(--c-text-3);margin-bottom:12px;">
      <i class="bi bi-cash-coin me-1" style="color:var(--c-primary)"></i>Totais por Forma de Pagamento
    </div>
    <div class="caixa-pagto-grid">
      ${[
        ['💵','Dinheiro',c.total_dinheiro],['📱','PIX',c.total_pix],
        ['💳','Débito',c.total_debito],['💳','Crédito',c.total_credito],
        ['🤝','Convênio',c.total_convenio],['⚙️','Outros',c.total_outros],
      ].map(([icon, label, val]) => `
        <div class="caixa-pagto-item">
          <div class="caixa-pagto-icon">${icon}</div>
          <div>
            <div class="caixa-pagto-label">${label}</div>
            <div class="caixa-pagto-value">${brl(val)}</div>
          </div>
        </div>
      `).join('')}
    </div>

    <div class="caixa-resumo-grid">
      <div class="caixa-resumo-card caixa-resumo-card--green">
        <span class="val">${brl(total_geral)}</span><span class="lbl">Total Geral</span>
      </div>
      <div class="caixa-resumo-card caixa-resumo-card--yellow">
        <span class="val">${brl(c.total_sangrias)}</span><span class="lbl">Sangrias</span>
      </div>
      <div class="caixa-resumo-card caixa-resumo-card--blue">
        <span class="val">${brl(c.saldo_esperado)}</span><span class="lbl">Saldo Esperado</span>
      </div>
      <div class="caixa-resumo-card caixa-resumo-card--gray">
        <span class="val">${c.caixa_contado !== null ? brl(c.caixa_contado) : '—'}</span><span class="lbl">Contado</span>
      </div>
    </div>

    <div class="caixa-diff-box">
      <strong>Diferença de Caixa:</strong> ${diferencaHtml}
    </div>

    <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:var(--c-text-3);margin-bottom:12px;margin-top:20px;">
      <i class="bi bi-arrow-down-circle me-1" style="color:var(--c-warning)"></i>Sangrias
    </div>
    ${sangriasHtml}

    ${c.observacao ? `<div class="caixa-diff-box" style="margin-top:16px;"><strong>Observação:</strong> ${c.observacao}</div>` : ''}
  `);
}

// ── Espelho ───────────────────────────────────────────────────────────────────

$('#btn-imprimir-espelho').on('click', function () {
  window.open('/espelho-caixa?id=' + $(this).data('id'), '_blank');
});

// ── Filtros ───────────────────────────────────────────────────────────────────

function setFiltroHoje() {
  const hoje = new Date().toISOString().slice(0, 10);
  $('#filtro-data-inicio').val(hoje);
  $('#filtro-data-fim').val(hoje);
}

$('#btn-filtrar').on('click', () => tabela.ajax.reload());

$('#btn-limpar-filtros').on('click', () => {
  $('#filtro-status').val('');
  $('#filtro-numero-pdv').val('');
  setFiltroHoje();
  tabela.search('').ajax.reload();
});

// ── Init ──────────────────────────────────────────────────────────────────────

$(function () {
  setFiltroHoje();
  initTabela();
});
