// Variável global para armazenar filtros complexos
// Adicione esta declaração no escopo global (e.g., no seu arquivo de inicialização da SPA)
window.$biaPageFilters = {};

/**
 * Função de ponte para navegação, chamada pelo BiaChat.
 * @param {string} screenName O nome da tela/rota (ex: 'escalas', 'agendamentos')
 * @param {object} filters Parâmetros dinâmicos (ex: {data: '2025-11-22', loja: 'X'})
 */
window.navigateToScreen = (screenName, filters = {}) => {
    // 1. Armazena os filtros da Bia em uma variável global temporária
    // Isso é necessário pois o método carregarPagina do core.js só suporta um parâmetro simples
    window.$biaPageFilters = filters
    //console.log(filters);
    // 2. Define o novo hash da URL (e.g., #escalas)
    const newHash = `#${screenName}`;

    // 3. Mudar o hash da URL (dispara o 'hashchange' de core.js se for uma URL diferente)
    window.location.hash = newHash;

    // 4. Se o hash não mudou (porque o usuário já estava na tela 'escalas'),
    // o evento 'hashchange' não dispara. Chamamos carregarPagina() manualmente.
    if (location.hash === newHash) {
        //carregarPagina(screenName); // Chama a função principal de core.js
    }
};
