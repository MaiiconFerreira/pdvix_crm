</div> <!-- /div-wrapper -->

  <!--begin::Script-->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"
    integrity="sha256-dghWARbRe2eLlIJ56wNB+b760ywulqK3DzZYEpsg2fQ=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>

  <!-- DataTables -->
  <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/3.0.0/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/3.0.0/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/3.0.0/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/rowgroup/1.6.0/js/dataTables.rowGroup.min.js"></script>

  <!-- Charts / misc -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- AdminLTE -->
  <script src="../template/dist/js/adminlte.js"></script>

  <script>
    // ── OverlayScrollbars sidebar ─────────────────────────────────────────────
    const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
    document.addEventListener('DOMContentLoaded', function () {
      const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
      if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true },
        });
      }
    });

    // ── Variáveis globais ─────────────────────────────────────────────────────
    window.user            = '<?= $nome_usuario ?>';
    window.$paginaInicial  = '<?= $pagina_inicial ?? 'usuarios' ?>';

    // ── Helpers globais ───────────────────────────────────────────────────────
    window.mostrarErroDePagina = function(erro) {
      document.getElementById('app').innerHTML = `
        <div class="d-flex justify-content-center align-items-center flex-column text-center" style="height:100%;">
          <div class="card border border-danger shadow" style="max-width:500px;width:100%;">
            <div class="card-body">
              <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:2.5rem;"></i>
              <h4 class="text-danger mt-2">Erro ao carregar a página!</h4>
              <p class="text-muted mb-3">Não foi possível carregar o conteúdo solicitado.</p>
              <button class="btn btn-outline-danger" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Tentar novamente
              </button>
              <small style="display:block;font-size:11px;color:#495057;margin-top:2%">${erro}</small>
            </div>
          </div>
        </div>`;
    };

    window.formatDate = function(isoDate) {
      const match = isoDate ? isoDate.match(/^(\d{4})-(\d{2})-(\d{2})/) : null;
      if (!match) return 'Data inválida';
      const [, year, month, day] = match;
      return `${day}/${month}/${year}`;
    };

    window.formatDateTime = function(iso) {
      if (!iso) return '—';
      const regex = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/;
      const m = iso.match(regex);
      if (!m) return iso;
      const [, y, mo, d, h, mi, s] = m;
      return `${d}/${mo}/${y} ${h}:${mi}:${s}`;
    };

    window.formatCurrency = function(value) {
      if (typeof value !== 'number') value = parseFloat(value) || 0;
      return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    FormData.toJSON = function(formData) {
      let obj = {};
      formData.forEach((value, key) => {
        if (obj[key]) {
          if (!Array.isArray(obj[key])) obj[key] = [obj[key]];
          obj[key].push(value);
        } else {
          obj[key] = value;
        }
      });
      return JSON.stringify(obj);
    };

    FormData.withExtra = function(formData, extra = {}) {
      try {
        const base   = JSON.parse(FormData.toJSON(formData));
        const merged = { ...base, ...extra };
        return JSON.stringify(merged);
      } catch (e) {
        return FormData.toJSON(formData);
      }
    };

    window.isMobile = function() {
      const ua        = navigator.userAgent || navigator.vendor || window.opera;
      const uaMobile  = /android|iphone|ipad|ipod|windows phone|mobile|silk|kindle|blackberry|opera mini/i.test(ua);
      const hasTouch  = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
      const smallScreen = Math.max(window.screen.width, window.screen.height) <= 1024;
      return uaMobile || (hasTouch && smallScreen);
    };

    window.exibirCarregamento = function() {
      const el = document.getElementById('loading-overlay');
      if (!el) return;
      el.style.display = 'flex';
      setTimeout(() => el.style.opacity = '1', 50);
    };

    window.esconderCarregamento = function() {
      const el = document.getElementById('loading-overlay');
      if (!el) return;
      el.style.opacity = '0';
      setTimeout(() => el.style.display = 'none', 500);
    };

    // ── Inicialização ─────────────────────────────────────────────────────────
    window.onload = function() {
      window.overlay = document.getElementById('loading-overlay');
      if (window.overlay) {
        window.overlay.style.opacity = '0';
        setTimeout(() => window.overlay.style.display = 'none', 500);
      }
    };

    // ── Service Worker ────────────────────────────────────────────────────────
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js').then(registration => {
        registration.onupdatefound = () => {
          let refreshing = false;
          navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;
            refreshing = true;
            window.location.reload();
          });
          const newWorker = registration.installing;
          newWorker.onstatechange = () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload());
            }
          };
        };
      }).catch(e => console.error('SW error:', e));
    }
  </script>

  <!-- Libs e paginas SPA -->
  <script src="/js/alerta-component.js?v=20260107"></script>
  <script src="/js/component-table.js?v=20260107"></script>
  <script src="/js/modals_component.js?v=20260107"></script>

  <script>
    window.libsCarregadas = [];
    window.PageFunctions  = [];
  </script>

  <script src="js/core.js" type="module"></script>
  <!--end::Script-->
  </body>
  <!--end::Body-->
</html>