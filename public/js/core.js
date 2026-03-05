// js/core.js — versão corrigida para SPA + AdminLTE (mobile fix)

// ====================================================================
// 🔒 FIX GLOBAL — trava overlay e evita menu abrindo sozinho
// ====================================================================

// Intercepta TODOS os cliques ANTES do AdminLTE
document.addEventListener('click', function (e) {
    const overlay = document.querySelector('.sidebar-overlay');

    // Se o AdminLTE recriou o overlay → removemos imediatamente
    if (overlay) {
        overlay.remove();
        document.body.classList.remove('sidebar-open', 'sidebar-collapse');
    }
}, true);

// Impede o AdminLTE de tratar toque em componentes internos como clique de menu
document.addEventListener('touchstart', function (e) {
    if (
        e.target.closest('.select2') ||
        e.target.closest('.dataTables_wrapper') ||
        e.target.closest('.modal') ||
        e.target.closest('.dropdown-menu') ||
        e.target.closest('.dt-button') ||
        e.target.closest('.form-control')
    ) {
        e.stopPropagation(); // previne comportamento de "abrir menu"
    }
}, true);

// ====================================================================
// 🔧 Carregamento de libs externas (já existia)
// ====================================================================

function carregarLib(url, chave) {
    if (!window.libsCarregadas.includes(chave)) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = () => {
                window.libsCarregadas.push(chave);
                resolve();
            };
            script.onerror = () => reject(`Erro ao carregar: ${url}`);
            document.head.appendChild(script);
        });
    }
    return Promise.resolve();
}

// ====================================================================
// 🚀 Função principal de navegação SPA
// ====================================================================

window.carregarPagina = async function carregarPagina(pagina) {
    try {
        let param = undefined;

        const hash = window.location.hash; // Ex: "#agendar/123" ou "#diarias?loja_id=5"
        
        // Formato clássico: #pagina/param
        const match = hash.match(/^#([\w-]+)\/([\w-]+)$/);

        if (match) {
            pagina = match[1];
            param = match[2];
        } else {
            // Formato com query string: #pagina?chave=valor&...
            // Garante que o nome da página esteja sempre limpo, mesmo que
            // carregarPagina seja chamada diretamente com a string completa.
            pagina = pagina.split('?')[0];
        }

        const html = await fetch(`views/${pagina}.html?nocache=` + new Date().getTime())
            .then(res => {
                if (!res.ok) throw new Error(`#PAGE_LOAD:: ${res.status} ${res.statusText}`);
                return res.text();
            });

        document.getElementById('app').innerHTML = html;
        document.querySelector("[app-subtitle-page='']").innerHTML = ``;

        // ====================================================================
        // 📌 FECHA MENU SEMPRE QUE TROCAR DE TELA (Mobile Fix)
        // ====================================================================
        if (window.innerWidth < 768) {

            // Remove classes
            document.body.classList.remove('sidebar-open', 'sidebar-collapse');

            // Remove overlay se existir
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.remove();

            // Fecha via botão do AdminLTE (garante 100%)
            const toggleBtn = document.querySelector("[data-lte-toggle='sidebar']");
            if (toggleBtn) toggleBtn.classList.add("sidebar-collapsed-force");
            if (toggleBtn) toggleBtn.click();
        }

        // Importa JS da página
        try {
            await import(`./paginas/${pagina}.js?nocache=` + new Date().getTime());
        } catch (erroImportacao) {
            console.error('Erro ao importar script da página:', erroImportacao);
            mostrarErroDePagina('#MOD_IMP_500:: ' + erroImportacao.message);
            return;
        }

        // Execução da função da página
        if (typeof window.PageFunctions[pagina] === 'function') {
            const biaFilters = window.$biaPageFilters;
            let finalParam = param;

            if (biaFilters && Object.keys(biaFilters).length > 0) {
                finalParam = biaFilters;
                window.$biaPageFilters = {}; // limpa estado
            }

            window.PageFunctions[pagina](finalParam);
        }

    } catch (e) {
        console.error('Erro geral ao carregar página:', e);
        mostrarErroDePagina(e.message || String(e));
    }
};

// ====================================================================
// 🔀 Roteamento por hash
// ====================================================================

window.addEventListener('hashchange', () => {
    // Remove '#' e descarta tudo após '?' (query string do e-mail de alertas)
    // Ex.: "#diarias?loja_id=5&data_inicio=2025-01-01" → "diarias"
    const rawHash = location.hash.replace('#', '') || $paginaInicial;
    const pagina  = rawHash.split('?')[0];          // ignora query params aqui
    carregarPagina(pagina);
});

// ====================================================================
// ⚙ Configura eventos ao carregar DOM
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
    window.MODAL = new ModalManager();

    // Fecha menu quando clicar em item do menu no mobile
    if (window.innerWidth < 768) {
        document.querySelectorAll('[app-screen=""]').forEach(function (el) {

            el.addEventListener("click", () => {
                if (document.body.classList.contains('sidebar-open')) {
                    const toggleBtn = document.querySelector("[data-lte-toggle='sidebar']");
                    if (toggleBtn) toggleBtn.click();
                }
            });

        });
    }
});

// Primeira carga
window.dispatchEvent(new Event('hashchange'));