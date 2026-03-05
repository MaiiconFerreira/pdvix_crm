/**
 * NotificationsManager — Componente de notificações reutilizável
 * Sistema MVC PHP + JS Vanilla + AdminLTE
 *
 * Recursos:
 *  - Carrega notificações via API
 *  - Renderiza dropdown no header
 *  - Toast rotativo (1 por vez, 8s) para não-lidas — independente do dropdown
 *  - Ler todas / ler individual
 *  - Parser de tags: [LINK url|texto], [BOLD texto], [DESTAQUE texto], [USUARIO nome]
 */

class NotificationsManager {

  constructor(options = {}) {
    this.options = Object.assign({
      apiList:       '/listarUltimasNotificacoes',
      apiRead:       '/lerNotificacao',
      apiReadAll:    '/lerTodasNotificacoes',
      rotateInterval: 8000,        // ms entre cada toast
      maxToast:      10,           // máx de notificações no roteador
      containerAttr: 'notificacoes_container',
      totalAttr:     'notificacoes_total',
    }, options);

    this._notifications  = [];
    this._toastTimer     = null;
    this._toastIndex     = 0;
    this._toastEl        = null;
    this._initialized    = false;
  }

  /* ─────────────────────────────────────────────
   * INICIALIZAÇÃO
   * ───────────────────────────────────────────── */

  init() {
    if (this._initialized) return;
    this._initialized = true;
    this._injectStyles();
    this._buildToastElement();
    this._bindHeaderButtons();
    this.refresh();
  }

  /* ─────────────────────────────────────────────
   * API
   * ───────────────────────────────────────────── */

  async refresh() {
    try {
      const res  = await fetch(this.options.apiList);
      const json = await res.json();
      if (json.status !== 'success') return;
      this._notifications = json.data || [];
      this._renderDropdown();
      this._startRotatingToast();
    } catch (e) {
      console.warn('[NotificationsManager] Erro ao carregar notificações:', e);
    }
  }

  async markAsRead(id, silent = false) {
    try {
      const res  = await fetch(this.options.apiRead, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id }),
      });
      const json = await res.json();
      if (!silent) new Alerta(json.status, '', json.message);
      await this.refresh();
      return json.status === 'success';
    } catch (e) {
      console.warn('[NotificationsManager] Erro ao marcar como lida:', e);
      return false;
    }
  }

  async markAllAsRead() {
    const unread = this._notifications.filter(n => n.lida === 0).map(n => n.id);
    if (!unread.length) {
      new Alerta('info', '', 'Nenhuma notificação pendente.');
      return;
    }
    try {
      const res  = await fetch(this.options.apiReadAll, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ ids: unread }),
      });
      const json = await res.json();
      new Alerta(json.status, '', json.message);
      this._stopRotatingToast();
      await this.refresh();
    } catch (e) {
      console.warn('[NotificationsManager] Erro ao ler todas:', e);
    }
  }

  /* ─────────────────────────────────────────────
   * PARSER DE TAGS DE CONTEÚDO
   * ───────────────────────────────────────────── */

  parseContent(text) {
    if (!text) return '';
    return text
      // [LINK url|Texto do link]
      .replace(/\[LINK\s+([^\|]+)\|([^\]]+)\]/gi,
        (_, url, label) => `<a href="${url.trim()}" target="_blank" rel="noopener" class="notif-link">${label.trim()}</a>`)
      // [BOLD texto]
      .replace(/\[BOLD\s+([^\]]+)\]/gi,
        (_, content) => `<strong>${content.trim()}</strong>`)
      // [DESTAQUE texto]
      .replace(/\[DESTAQUE\s+([^\]]+)\]/gi,
        (_, content) => `<span class="notif-highlight">${content.trim()}</span>`)
      // [USUARIO nome]
      .replace(/\[USUARIO\s+([^\]]+)\]/gi,
        (_, name) => `<span class="notif-mention">@${name.trim()}</span>`);
  }

  /* ─────────────────────────────────────────────
   * RENDER — DROPDOWN DO HEADER
   * ───────────────────────────────────────────── */

  _renderDropdown() {
    const container = document.querySelector(`[${this.options.containerAttr}]`);
    const totals    = document.querySelectorAll(`[${this.options.totalAttr}]`);
    if (!container) return;

    const unreadCount = this._notifications.filter(n => n.lida === 0).length;

    // Atualiza badges de total
    totals.forEach(el => {
      if (unreadCount === 0) {
        el.style.display = 'none';
      } else {
        el.style.display = '';
        el.textContent   = unreadCount > 99 ? '99+' : unreadCount;
      }
    });

    // Lista vazia
    if (!this._notifications.length) {
      container.innerHTML = `
        <div class="notif-empty">
          <i class="bi bi-bell-slash"></i>
          <span>Nenhuma notificação</span>
        </div>`;
      return;
    }

    container.innerHTML = this._notifications.map(n => `
      <a href="${n.reference_link || '#'}"
         class="notif-item ${n.lida === 0 ? 'notif-item--unread' : ''}"
         onclick="window.notificationsManager._handleItemClick(event, '${n.id}', '${n.reference_link || ''}')">
        <div class="notif-icon" style="background:var(--notif-color-${n.cor || 'primary'}, var(--notif-color-primary));">
          <i class="${n.icone || 'bi bi-bell'}"></i>
        </div>
        <div class="notif-body">
          <div class="notif-title">${n.titulo || ''}</div>
          <div class="notif-text">${this.parseContent(n.conteudo || '')}</div>
        </div>
        <div class="notif-meta">
          <span class="notif-time">${n.quando || this._timeAgo(n.time)}</span>
          ${n.lida === 0 ? '<span class="notif-dot"></span>' : ''}
        </div>
      </a>`).join('');
  }

  _handleItemClick(event, id, link) {
    event.preventDefault();
    this.markAsRead(id, true).then(ok => {
      if (ok && link && link !== '#' && link !== '') {
        // Usa o sistema SPA se disponível
        if (typeof window.navigateTo === 'function') {
          window.navigateTo(link);
        } else {
          window.location.href = link;
        }
      }
    });
  }

  /* ─────────────────────────────────────────────
   * TOAST ROTATIVO
   * ───────────────────────────────────────────── */

  _startRotatingToast() {
    this._stopRotatingToast();
    const unread = this._notifications.filter(n => n.lida === 0).slice(0, this.options.maxToast);
    if (!unread.length) {
      this._hideToast();
      return;
    }
    this._toastIndex = 0;
    this._showToastItem(unread);
    this._toastTimer = setInterval(() => {
      this._toastIndex = (this._toastIndex + 1) % unread.length;
      this._showToastItem(unread);
    }, this.options.rotateInterval);
  }

  _stopRotatingToast() {
    clearInterval(this._toastTimer);
    this._toastTimer = null;
  }

  _showToastItem(unread) {
    const n   = unread[this._toastIndex];
    const el  = this._toastEl;
    if (!el || !n) return;

    // Fade out → atualiza → fade in
    el.classList.remove('notif-toast--visible');
    setTimeout(() => {
      el.innerHTML = `
        <div class="notif-toast__inner">
          <div class="notif-toast__icon" style="background:var(--notif-color-${n.cor || 'primary'}, var(--notif-color-primary));">
            <i class="${n.icone || 'bi bi-bell'}"></i>
          </div>
          <div class="notif-toast__content">
            <div class="notif-toast__title">${n.titulo || ''}</div>
            <div class="notif-toast__text">${this.parseContent(n.conteudo || '')}</div>
          </div>
          <div class="notif-toast__actions">
            <button class="notif-toast__read" title="Marcar como lida"
              onclick="window.notificationsManager._toastRead(event, '${n.id}')">
              <i class="bi bi-check2"></i>
            </button>
            <button class="notif-toast__close" title="Fechar"
              onclick="window.notificationsManager._toastClose(event)">
              <i class="bi bi-x"></i>
            </button>
          </div>
        </div>
        <div class="notif-toast__progress">
          <div class="notif-toast__bar" style="animation-duration:${this.options.rotateInterval}ms"></div>
        </div>`;
      el.classList.add('notif-toast--visible');
    }, 220);
  }

  _hideToast() {
    if (this._toastEl) this._toastEl.classList.remove('notif-toast--visible');
  }

  async _toastRead(event, id) {
    event.stopPropagation();
    this._stopRotatingToast();
    this._hideToast();
    await this.markAsRead(id, true);
  }

  _toastClose(event) {
    event.stopPropagation();
    this._stopRotatingToast();
    this._hideToast();
  }

  /* ─────────────────────────────────────────────
   * BOTÃO "LER TODAS"
   * ───────────────────────────────────────────── */

  _bindHeaderButtons() {
    // Delegação — funciona mesmo se o header for renderizado depois
    document.addEventListener('click', e => {
      if (e.target.closest('[data-notif-readall]')) {
        e.preventDefault();
        this.markAllAsRead();
      }
    });
  }

  /* ─────────────────────────────────────────────
   * UTILITÁRIOS
   * ───────────────────────────────────────────── */

  _timeAgo(dateStr) {
    if (!dateStr) return '';
    const date  = new Date(dateStr);
    const now   = new Date();
    const diff  = Math.floor((now - date) / 1000);
    if (diff < 60)    return 'agora';
    if (diff < 3600)  return `${Math.floor(diff / 60)}min`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
  }

  /* ─────────────────────────────────────────────
   * CONSTRUÇÃO DO ELEMENTO TOAST
   * ───────────────────────────────────────────── */

  _buildToastElement() {
    const el = document.createElement('div');
    el.className = 'notif-toast';
    el.id = 'notif-rotating-toast';

    // Impede que qualquer clique dentro do toast
    // seja interpretado pelo Bootstrap como clique no dropdown do header
    el.addEventListener('click',      e => e.stopPropagation());
    el.addEventListener('mousedown',  e => e.stopPropagation());
    el.addEventListener('pointerdown',e => e.stopPropagation());

    document.body.appendChild(el);
    this._toastEl = el;
  }

  /* ─────────────────────────────────────────────
   * ESTILOS INJETADOS
   * ───────────────────────────────────────────── */

  _injectStyles() {
    if (document.getElementById('notif-styles')) return;
    const style = document.createElement('style');
    style.id = 'notif-styles';
    style.textContent = `
      /* ── Variáveis de cor ── */
      :root {
        --notif-color-primary:  #4361ee;
        --notif-color-success:  #2ec4b6;
        --notif-color-warning:  #f9a825;
        --notif-color-danger:   #e63946;
        --notif-color-info:     #4cc9f0;
        --notif-color-dark:     #343a40;
        --notif-color-secondary:#6c757d;
        --notif-radius:         12px;
        --notif-shadow:         0 8px 32px rgba(0,0,0,.12);
        --notif-transition:     .22s cubic-bezier(.4,0,.2,1);
      }

      /* ── Dropdown container ── */
      /* ── Dropdown container ── */
      .dropdown-custom22 {
        width: 360px !important;
        border-radius: var(--notif-radius) !important;
        box-shadow: var(--notif-shadow) !important;
        border: 1px solid rgba(0,0,0,.06) !important;
        overflow: hidden;
        max-height: 480px;
        /* Removido o display: flex daqui para não conflitar com o Bootstrap */
        flex-direction: column;
      }
      
      /* Só aplica o flex quando o Bootstrap abrir o menu (classe .show) */
      .dropdown-custom22.show {
        display: flex !important;
      }
      
      .dropdown-custom22 > *:not([notificacoes_container]) { flex-shrink: 0; }
      [notificacoes_container] { overflow-y: auto; flex: 1; }
      [notificacoes_container]::-webkit-scrollbar { width: 4px; }
      [notificacoes_container]::-webkit-scrollbar-track { background: transparent; }
      [notificacoes_container]::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

      /* ── Item de notificação ── */
      .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        text-decoration: none !important;
        color: inherit !important;
        border-bottom: 1px solid rgba(0,0,0,.05);
        transition: background var(--notif-transition);
        position: relative;
      }
      .notif-item:hover { background: #f8f9ff; }
      .notif-item:last-child { border-bottom: none; }
      .notif-item--unread { background: #f0f3ff; }
      .notif-item--unread:hover { background: #e8ecff; }

      .notif-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-size: 15px;
      }
      .notif-body { flex: 1; min-width: 0; }
      .notif-title {
        font-size: 13px;
        font-weight: 600;
        color: #212529;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
      }
      .notif-text {
        font-size: 12px;
        color: #6c757d;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      .notif-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
      }
      .notif-time { font-size: 11px; color: #adb5bd; white-space: nowrap; }
      .notif-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--notif-color-primary);
      }

      /* ── Estado vazio ── */
      .notif-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 36px 16px;
        color: #adb5bd;
        font-size: 13px;
      }
      .notif-empty i { font-size: 28px; }

      /* ── Tags de conteúdo ── */
      .notif-link { color: var(--notif-color-primary); font-weight: 500; }
      .notif-highlight {
        background: #fff3cd;
        color: #856404;
        padding: 0 3px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
      }
      .notif-mention { color: var(--notif-color-primary); font-weight: 600; }

      /* ── Toast rotativo ── */
      .notif-toast {
        position: fixed;
        top: 68px;
        right: 20px;
        width: 320px;
        z-index: 9999;
        background: #fff;
        border-radius: var(--notif-radius);
        box-shadow: var(--notif-shadow);
        border: 1px solid rgba(0,0,0,.07);
        overflow: hidden;
        opacity: 0;
        transform: translateY(-8px) scale(.97);
        pointer-events: none;
        transition: opacity var(--notif-transition), transform var(--notif-transition);
      }
      .notif-toast--visible {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: all;
      }
      .notif-toast__inner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 14px;
      }
      .notif-toast__icon {
        width: 34px; height: 34px;
        border-radius: 8px;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-size: 14px;
      }
      .notif-toast__content { flex: 1; min-width: 0; }
      .notif-toast__title {
        font-size: 12.5px;
        font-weight: 600;
        color: #212529;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .notif-toast__text {
        font-size: 11.5px;
        color: #6c757d;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      .notif-toast__actions {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
      }
      .notif-toast__read,
      .notif-toast__close {
        background: none;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        color: #6c757d;
        font-size: 13px;
        transition: all .15s;
      }
      .notif-toast__read:hover  { background: #d1fae5; border-color: #2ec4b6; color: #2ec4b6; }
      .notif-toast__close:hover { background: #fee2e2; border-color: #e63946; color: #e63946; }

      /* ── Barra de progresso do toast ── */
      .notif-toast__progress {
        height: 3px;
        background: #f1f3f5;
        overflow: hidden;
      }
      .notif-toast__bar {
        height: 100%;
        width: 100%;
        background: linear-gradient(90deg, var(--notif-color-primary), var(--notif-color-info));
        transform-origin: left;
        animation: notifProgress linear forwards;
      }
      @keyframes notifProgress {
        from { transform: scaleX(1); }
        to   { transform: scaleX(0); }
      }

      /* ── Ler todas btn ── */
      [data-notif-readall] { cursor: pointer; }
    `;
    document.head.appendChild(style);
  }
}

/* ─────────────────────────────────────────────
 * Bootstrap global — chamado no footer após permissão PHP
 * ───────────────────────────────────────────── */
window.notificationsManager = new NotificationsManager();

// Expõe helpers legados para compatibilidade com código existente
window.loadNotifications = () => window.notificationsManager.refresh();
window.lerNotificacao    = (event, id) => {
  event.preventDefault();
  window.notificationsManager.markAsRead(id);
};

