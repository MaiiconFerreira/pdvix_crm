/**
 * ============================================================
 * DashboardAlertPanel — v1.0
 * Painel de alertas em tempo real para o Dashboard Builder.
 *
 * Uso:
 *   DashboardAlertPanel.add({ id, source, titulo, message, tipo })
 *   DashboardAlertPanel.clear()
 *   DashboardAlertPanel.getAll()
 *
 * O painel é injetado automaticamente no DOM na primeira chamada.
 * Pode ser aberto/fechado pelo botão flutuante ou por
 *   DashboardAlertPanel.toggle()
 * ============================================================
 */
const DashboardAlertPanel = (() => {

    // ── ESTADO ─────────────────────────────────────────────────
    const _alerts   = [];      // { id, source, titulo, message, tipo, ts }
    let   _panelEl  = null;
    let   _badgeEl  = null;
    let   _listEl   = null;
    let   _btnEl    = null;
    let   _open     = false;

    // ── TIPOS ──────────────────────────────────────────────────
    const TYPE_META = {
        sql:    { icon: 'bi-database-exclamation', color: '#ef4444', label: 'SQL'       },
        chart:  { icon: 'bi-bar-chart',             color: '#f59e0b', label: 'Gráfico'   },
        widget: { icon: 'bi-exclamation-circle',    color: '#f97316', label: 'Widget'    },
        api:    { icon: 'bi-cloud-slash',            color: '#8b5cf6', label: 'API'       },
        js:     { icon: 'bi-code-slash',             color: '#3b82f6', label: 'JavaScript'},
    };

    // ── INJEÇÃO DO DOM ─────────────────────────────────────────
    function _inject() {
        if (document.getElementById('db-alert-panel')) return;

        document.head.insertAdjacentHTML('beforeend', `
        <style>
        /* ── Botão flutuante ── */
        #db-alert-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9998;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: none;
            background: #1e293b;
            color: #f8fafc;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,0,0,.35);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .2s, background .2s;
        }
        #db-alert-btn:hover { background: #334155; transform: scale(1.08); }
        #db-alert-btn.has-errors { background: #ef4444 !important; }
        #db-alert-btn.has-warnings { background: #f59e0b !important; }

        /* ── Badge contador ── */
        #db-alert-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            padding: 0 4px;
            display: none;
            pointer-events: none;
        }

        /* ── Painel lateral ── */
        #db-alert-panel {
            position: fixed;
            bottom: 80px;
            right: 24px;
            z-index: 9997;
            width: 380px;
            max-height: 520px;
            background: var(--db-surface, #fff);
            border: 1px solid var(--db-border, #e2e8f0);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(16px) scale(.97);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s, transform .2s;
        }
        #db-alert-panel.open {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0) scale(1);
        }

        /* ── Header ── */
        .db-ap-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--db-border, #e2e8f0);
            background: var(--db-surface-raised, #f8fafc);
            flex-shrink: 0;
        }
        .db-ap-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--db-text-primary, #0f172a);
            flex: 1;
        }
        .db-ap-subtitle {
            font-size: 10px;
            color: var(--db-text-muted, #64748b);
        }
        .db-ap-clear {
            background: none;
            border: 1px solid var(--db-border, #e2e8f0);
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 10px;
            color: var(--db-text-muted, #64748b);
            cursor: pointer;
            transition: all .15s;
        }
        .db-ap-clear:hover { background: #ef444420; border-color: #ef4444; color: #ef4444; }

        /* ── Lista de alertas ── */
        .db-ap-list {
            overflow-y: auto;
            flex: 1;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .db-ap-list::-webkit-scrollbar { width: 4px; }
        .db-ap-list::-webkit-scrollbar-thumb { background: var(--db-border, #e2e8f0); border-radius: 2px; }

        /* ── Item de alerta ── */
        .db-alert-item {
            display: flex;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            background: var(--db-surface-raised, #f8fafc);
            border: 1px solid var(--db-border, #e2e8f0);
            animation: dbAlertSlide .2s ease-out;
            position: relative;
        }
        @keyframes dbAlertSlide {
            from { opacity:0; transform: translateX(12px); }
            to   { opacity:1; transform: translateX(0);    }
        }
        .db-alert-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .db-alert-body { flex: 1; min-width: 0; }
        .db-alert-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--db-text-primary, #0f172a);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .db-alert-source {
            font-size: 10px;
            color: var(--db-text-muted, #64748b);
            margin-bottom: 3px;
        }
        .db-alert-msg {
            font-size: 11px;
            color: var(--db-text-secondary, #475569);
            word-break: break-word;
            line-height: 1.4;
        }
        .db-alert-ts {
            font-size: 9px;
            color: var(--db-text-muted, #94a3b8);
            margin-top: 4px;
        }
        .db-alert-dismiss {
            position: absolute;
            top: 6px;
            right: 6px;
            background: none;
            border: none;
            color: var(--db-text-muted, #94a3b8);
            font-size: 11px;
            cursor: pointer;
            padding: 0 2px;
            line-height: 1;
            border-radius: 3px;
        }
        .db-alert-dismiss:hover { color: #ef4444; background: #ef444415; }

        /* ── Vazio ── */
        .db-ap-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            color: var(--db-text-muted, #94a3b8);
            font-size: 12px;
            gap: 8px;
        }
        .db-ap-empty i { font-size: 28px; opacity: .5; }

        /* ── Footer stats ── */
        .db-ap-footer {
            padding: 8px 14px;
            border-top: 1px solid var(--db-border, #e2e8f0);
            display: flex;
            gap: 12px;
            flex-shrink: 0;
            background: var(--db-surface-raised, #f8fafc);
        }
        .db-ap-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: var(--db-text-muted, #64748b);
        }
        .db-ap-stat i { font-size: 11px; }

        /* Responsivo */
        @media (max-width: 480px) {
            #db-alert-panel { width: calc(100vw - 32px); right: 16px; bottom: 76px; }
            #db-alert-btn   { right: 16px; bottom: 20px; }
        }
        </style>`);

        // Botão flutuante
        const btnWrap = document.createElement('div');
        btnWrap.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9998;';
        btnWrap.innerHTML = `
            <button id="db-alert-btn" title="Painel de Alertas do Dashboard"
                    onclick="DashboardAlertPanel.toggle()">
                <i class="bi bi-bell-fill"></i>
                <span id="db-alert-badge"></span>
            </button>`;
        document.body.appendChild(btnWrap);

        // Painel
        const panel = document.createElement('div');
        panel.id = 'db-alert-panel';
        panel.innerHTML = `
            <div class="db-ap-header">
                <i class="bi bi-bell-fill" style="color:#f59e0b;font-size:15px;"></i>
                <span class="db-ap-title">Painel de Alertas</span>
                <span class="db-ap-subtitle" id="db-ap-subtitle">Nenhum problema</span>
                <button class="db-ap-clear" onclick="DashboardAlertPanel.clear()">
                    <i class="bi bi-trash3"></i> Limpar
                </button>
            </div>
            <div class="db-ap-list" id="db-ap-list">
                <div class="db-ap-empty">
                    <i class="bi bi-check-circle"></i>
                    Nenhum erro ou aviso registrado
                </div>
            </div>
            <div class="db-ap-footer" id="db-ap-footer">
                <span class="db-ap-stat" id="db-ap-stat-total">
                    <i class="bi bi-bell"></i> 0 alertas
                </span>
            </div>`;
        document.body.appendChild(panel);

        _panelEl = panel;
        _badgeEl = document.getElementById('db-alert-badge');
        _listEl  = document.getElementById('db-ap-list');
        _btnEl   = document.getElementById('db-alert-btn');
    }

    // ── RENDERIZAÇÃO ───────────────────────────────────────────
    function _render() {
        if (!_listEl) return;

        // Atualiza footer stats
        const counts = {};
        _alerts.forEach(a => { counts[a.tipo] = (counts[a.tipo] || 0) + 1; });
        const total = _alerts.length;

        const statEl = document.getElementById('db-ap-stat-total');
        if (statEl) {
            const parts = Object.entries(counts).map(([t, n]) => {
                const m = TYPE_META[t] || TYPE_META.widget;
                return `<span style="color:${m.color}">${n} ${m.label}</span>`;
            });
            statEl.innerHTML = `<i class="bi bi-bell"></i> ${total} alerta${total !== 1 ? 's' : ''}${parts.length ? ' — ' + parts.join(' · ') : ''}`;
        }

        const subtitleEl = document.getElementById('db-ap-subtitle');
        if (subtitleEl) {
            subtitleEl.textContent = total === 0 ? 'Nenhum problema' : `${total} problema${total !== 1 ? 's' : ''} detectado${total !== 1 ? 's' : ''}`;
        }

        // Badge
        if (_badgeEl) {
            _badgeEl.textContent = total > 99 ? '99+' : String(total);
            _badgeEl.style.display = total > 0 ? '' : 'none';
        }

        // Cor do botão
        if (_btnEl) {
            _btnEl.classList.remove('has-errors', 'has-warnings');
            if (total > 0) {
                const hasError = _alerts.some(a => ['sql', 'api'].includes(a.tipo));
                _btnEl.classList.add(hasError ? 'has-errors' : 'has-warnings');
            }
        }

        if (total === 0) {
            _listEl.innerHTML = `
                <div class="db-ap-empty">
                    <i class="bi bi-check-circle"></i>
                    Nenhum erro ou aviso registrado
                </div>`;
            return;
        }

        // Renderiza itens (mais recentes primeiro)
        _listEl.innerHTML = [..._alerts].reverse().map(a => {
            const m      = TYPE_META[a.tipo] || TYPE_META.widget;
            const ts     = new Date(a.ts).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dtFmt  = new Date(a.ts).toLocaleDateString('pt-BR');
            return `
            <div class="db-alert-item" id="db-alert-item-${a._idx}">
                <div class="db-alert-icon"
                     style="background:${m.color}18;color:${m.color}">
                    <i class="bi ${m.icon}"></i>
                </div>
                <div class="db-alert-body">
                    <div class="db-alert-source">${m.label} · ${escHtml(a.source)}</div>
                    <div class="db-alert-title">${escHtml(a.titulo)}</div>
                    <div class="db-alert-msg">${escHtml(a.message)}</div>
                    <div class="db-alert-ts"><i class="bi bi-clock"></i> ${dtFmt} ${ts}</div>
                </div>
                <button class="db-alert-dismiss"
                        onclick="DashboardAlertPanel.dismiss(${a._idx})"
                        title="Descartar">✕</button>
            </div>`;
        }).join('');
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── API PÚBLICA ────────────────────────────────────────────

    let _idx = 0;

    /**
     * Adiciona um alerta ao painel.
     * @param {object} opts
     * @param {string} opts.id       - ID do elemento DOM do widget
     * @param {string} opts.source   - data_source / nome do endpoint
     * @param {string} opts.titulo   - Título legível do widget
     * @param {string} opts.message  - Mensagem de erro detalhada
     * @param {string} [opts.tipo]   - 'sql' | 'chart' | 'widget' | 'api' | 'js'
     */
    function add(opts) {
        _inject();

        // Detecta tipo automático pelo conteúdo da mensagem
        let tipo = opts.tipo || 'widget';
        const msg = (opts.message || '').toLowerCase();
        if (/sqlstate|syntax error|offset|limit|table|column/.test(msg)) tipo = 'sql';
        else if (/apex|tooltip|series|chart/.test(msg))                   tipo = 'chart';
        else if (/fetch|network|404|500/.test(msg))                       tipo = 'api';
        else if (/typeerror|referenceerror|syntaxerror/.test(msg))        tipo = 'js';

        // Evita duplicar o mesmo erro no mesmo widget
        const dup = _alerts.find(a => a.id === opts.id && a.message === opts.message);
        if (dup) { dup.ts = Date.now(); _render(); return; }

        _alerts.push({ ...opts, tipo, ts: Date.now(), _idx: _idx++ });

        // Mantém no máximo 50 alertas
        if (_alerts.length > 50) _alerts.shift();

        _render();

        // Abre o painel automaticamente no primeiro erro da sessão
        if (_alerts.length === 1) {
            setTimeout(() => open(), 800);
        }
    }

    /** Remove alerta pelo índice interno. */
    function dismiss(idx) {
        const i = _alerts.findIndex(a => a._idx === idx);
        if (i !== -1) _alerts.splice(i, 1);
        _render();
    }

    /** Limpa todos os alertas. */
    function clear() {
        _alerts.length = 0;
        _render();
    }

    /** Retorna cópia dos alertas atuais. */
    function getAll() {
        return [..._alerts];
    }

    /** Abre o painel. */
    function open() {
        _inject();
        _open = true;
        document.getElementById('db-alert-panel')?.classList.add('open');
    }

    /** Fecha o painel. */
    function close() {
        _open = false;
        document.getElementById('db-alert-panel')?.classList.remove('open');
    }

    /** Alterna painel aberto/fechado. */
    function toggle() {
        _open ? close() : open();
    }

    /**
     * Atalho para registrar erros JS capturados em try/catch.
     * Ex: DashboardAlertPanel.captureError('chart_lucro_estimado', 'Lucro Diário', err)
     */
    function captureError(source, titulo, error) {
        add({
            id:      'err_' + source,
            source,
            titulo,
            message: error?.message || String(error),
            tipo:    'widget',
        });
    }

    return { add, dismiss, clear, getAll, open, close, toggle, captureError };

})();

// ── Exposição Global ──────────────────────────────────────
window.DashboardAlertPanel = DashboardAlertPanel;