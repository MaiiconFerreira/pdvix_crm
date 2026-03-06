<?php

/**
 * routes.php — PDVix CRM v3.0
 *
 * Formato: 'uri' => ['controller' => 'NomeController', 'method' => 'nomeMetodo']
 *
 * Autenticação:
 *   - Rotas /api/pdv/sync-* e /api/carga-inicial → token estático (PDV Electron)
 *   - Rotas /api/webhook/*                        → HMAC externo (sem sessão)
 *   - Todas as demais rotas /api/*                → sessão PHP (painel admin)
 */

$routes = [

    // ── Autenticação / Home ───────────────────────────────────────────────────
    ''       => ['controller' => 'HomeController',   'method' => 'index'],
    'login'  => ['controller' => 'AuthController',   'method' => 'index'],
    'auth'   => ['controller' => 'AuthController',   'method' => 'authentication'],
    'logout' => ['controller' => 'AuthController',   'method' => 'logout'],

    // ── PDV Electron — Carga inicial ──────────────────────────────────────────
    // GET /api/carga-inicial?token=XXX[&loja_id=1]
    // Retorna: produtos, codigos_barras, usuarios, supervisores_cartoes, clientes
    'api/carga-inicial' => ['controller' => 'CargaInicialController', 'method' => 'index'],

    // ── PDV Electron — Sincronização ──────────────────────────────────────────
    // POST /api/pdv/sync-venda?token=XXX     → venda completa (idempotente por numero_venda)
    // POST /api/pdv/sync-caixa?token=XXX     → sessão de caixa + sangrias (idempotente)
    // POST /api/pdv/sync-cancelamento?token=XXX → cancelamentos offline
    'api/pdv/sync-venda'        => ['controller' => 'PdvSyncController',  'method' => 'sync'],
    'api/pdv/sync-caixa'        => ['controller' => 'PdvCaixaController', 'method' => 'sync'],
    'api/pdv/sync-cancelamento' => ['controller' => 'CancelamentoController', 'method' => 'syncCancelamento'],

    // ── PDV Electron — Consultas do painel ───────────────────────────────────
    // GET  /api/pdv/caixas/detalhe?id=X
    // GET  /api/pdv/caixas
    'api/pdv/caixas/detalhe' => ['controller' => 'PdvCaixaController', 'method' => 'detalhe'],
    'api/pdv/caixas'         => ['controller' => 'PdvCaixaController', 'method' => 'listar'],

    // ── PDV — Status e Comandos remotos ──────────────────────────────────────
    // GET  /api/pdv/status            → lista todos PDVs online/offline
    // POST /api/pdv/comando           → enfileira comando remoto via Redis
    // POST /api/pdv/carga             → sinaliza carga disponível
    'api/pdv/status'  => ['controller' => 'PdvController', 'method' => 'status'],
    'api/pdv/comando' => ['controller' => 'PdvController', 'method' => 'comando'],
    'api/pdv/carga'   => ['controller' => 'PdvController', 'method' => 'carga'],

    // ── Lojas — CRUD + PDVs ───────────────────────────────────────────────────
    // GET/POST/PUT/DELETE /api/lojas
    // GET/POST/PUT        /api/lojas/pdvs
    'api/lojas/pdvs' => ['controller' => 'LojaController', 'method' => 'pdvs'],
    'api/lojas'      => ['controller' => 'LojaController', 'method' => 'index'],

    // ── Usuários ──────────────────────────────────────────────────────────────
    // GET/POST/PUT/DELETE /api/users
    // PATCH               /api/users/status
    'api/users'        => ['controller' => 'UserController', 'method' => 'index'],
    'api/users/status' => ['controller' => 'UserController', 'method' => 'toggleStatus'],

    // ── Produtos ─────────────────────────────────────────────────────────────
    // GET/POST/PUT/DELETE /api/produtos
    // PATCH  /api/produtos/status
    // GET    /api/produtos/historico?id=X
    'api/produtos'           => ['controller' => 'ProdutoController', 'method' => 'index'],
    'api/produtos/status'    => ['controller' => 'ProdutoController', 'method' => 'toggleStatus'],
    'api/produtos/historico' => ['controller' => 'ProdutoController', 'method' => 'historico'],

    // ── Estoque ───────────────────────────────────────────────────────────────
    // GET  /api/estoque
    // POST /api/estoque/movimentar
    'api/estoque'            => ['controller' => 'EstoqueController',      'method' => 'index'],
    'api/estoque/movimentar' => ['controller' => 'EstoqueController',      'method' => 'movimentar'],

    // ── Movimentações (somente leitura) ───────────────────────────────────────
    // GET /api/movimentacoes
    'api/movimentacoes' => ['controller' => 'MovimentacaoController', 'method' => 'index'],

    // ── Vendas ────────────────────────────────────────────────────────────────
    // GET/POST/DELETE /api/vendas
    // PATCH  /api/vendas/status
    // GET    /api/vendas/itens?id=X
    // POST   /api/vendas/finalizar
    'api/vendas'           => ['controller' => 'VendaController', 'method' => 'index'],
    'api/vendas/status'    => ['controller' => 'VendaController', 'method' => 'alterarStatus'],
    'api/vendas/itens'     => ['controller' => 'VendaController', 'method' => 'itens'],
    'api/vendas/finalizar' => ['controller' => 'VendaController', 'method' => 'finalizar'],

    // ── Pagamentos (painel) ───────────────────────────────────────────────────
    // GET/POST/PUT/DELETE /api/pagamentos
    // PATCH               /api/pagamentos/status
    'api/pagamentos'        => ['controller' => 'PagamentoController', 'method' => 'index'],
    'api/pagamentos/status' => ['controller' => 'PagamentoController', 'method' => 'alterarStatus'],

    // ── PIX / Stone POS — Pagar.me (sessão — painel admin) ───────────────────
    // POST /api/pagamentos/pix/criar
    // POST /api/pagamentos/pix/cancelar
    // GET  /api/pagamentos/pix/status?order_id=X
    'api/pagamentos/pix/criar'    => ['controller' => 'PagarmeController', 'method' => 'criarPix'],
    'api/pagamentos/pix/cancelar' => ['controller' => 'PagarmeController', 'method' => 'cancelarPix'],
    'api/pagamentos/pix/status'   => ['controller' => 'PagarmeController', 'method' => 'statusPix'],

    // ── PIX — PDV Electron (token — sem sessão PHP) ───────────────────────────
    // POST /api/pdv/pix/criar?token=XXX
    // POST /api/pdv/pix/cancelar?token=XXX
    // GET  /api/pdv/pix/status?token=XXX&order_id=X
    'api/pdv/pix/criar'    => ['controller' => 'PagarmeController', 'method' => 'criarPixPdv'],
    'api/pdv/pix/cancelar' => ['controller' => 'PagarmeController', 'method' => 'cancelarPixPdv'],
    'api/pdv/pix/status'   => ['controller' => 'PagarmeController', 'method' => 'statusPixPdv'],

    // ── Webhook Pagar.me (sem sessão — validação HMAC interna) ───────────────
    // POST /api/webhook/pagarme
    'api/webhook/pagarme' => ['controller' => 'PagarmeController', 'method' => 'webhook'],

    // ── Cancelamentos ─────────────────────────────────────────────────────────
    // GET  /api/cancelamentos
    // POST /api/cancelamentos/venda
    // POST /api/cancelamentos/item
    'api/cancelamentos'       => ['controller' => 'CancelamentoController', 'method' => 'listar'],
    'api/cancelamentos/venda' => ['controller' => 'CancelamentoController', 'method' => 'cancelarVenda'],
    'api/cancelamentos/item'  => ['controller' => 'CancelamentoController', 'method' => 'cancelarItem'],

    // ── Comandas (pré-venda) ──────────────────────────────────────────────────
    // GET/POST/PUT/DELETE /api/comandas
    // POST                /api/comandas/enviar
    'api/comandas/enviar' => ['controller' => 'ComandaController', 'method' => 'enviar'],
    'api/comandas'        => ['controller' => 'ComandaController', 'method' => 'index'],

    // ── Dashboard ─────────────────────────────────────────────────────────────
    // GET /api/dashboard/resumo
    // GET /api/dashboard/vendas-hora
    // GET /api/dashboard/top-produtos
    // GET /api/dashboard/formas-pagamento
    // GET /api/dashboard/pdvs-status
    'api/dashboard/resumo'           => ['controller' => 'DashboardController', 'method' => 'resumo'],
    'api/dashboard/vendas-hora'      => ['controller' => 'DashboardController', 'method' => 'vendasPorHora'],
    'api/dashboard/top-produtos'     => ['controller' => 'DashboardController', 'method' => 'topProdutos'],
    'api/dashboard/formas-pagamento' => ['controller' => 'DashboardController', 'method' => 'formasPagamento'],
    'api/dashboard/pdvs-status'      => ['controller' => 'DashboardController', 'method' => 'pdvsStatus'],

    // ── Espelho de Caixa ──────────────────────────────────────────────────────
    // GET /api/espelho-pdv?caixa_sessao_id=X   → JSON completo (caixa+vendas+sangrias+cancelamentos)
    // GET /espelho-caixa?id=X                  → view HTML para impressão
    'api/espelho-pdv' => ['controller' => 'EspelhoController', 'method' => 'espelho'],
    'espelho-caixa'   => ['controller' => 'EspelhoController', 'method' => 'imprimirEspelho'],

];
