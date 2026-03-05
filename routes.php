<?php

$routes = [
    '' => ['controller' => 'HomeController', 'method' => 'index'],
    'login' => ['controller' => 'AuthController', 'method' => 'index'],
    'auth' => ['controller' => 'AuthController', 'method' => 'authentication'],
    'logout' => ['controller' => 'AuthController', 'method' => 'logout'],

    /*** PDV ELECTRON — Carga inicial de dados mestres ***/
    // GET  /api/carga-inicial?token=XXX  → produtos, códigos de barras, usuários,
    //                                       cartões de supervisor e clientes
    // Autenticação por token estático (config.api_token) — sem sessão PHP.
    'api/carga-inicial'  => ['controller' => 'CargaInicialController', 'method' => 'index'],

    /*** USUÁRIOS — REST /api/users/ ***/
    // GET    /api/users          → listar todos
    // POST   /api/users          → criar
    // PUT    /api/users          → atualizar
    // DELETE /api/users          → excluir
    // PATCH  /api/users/status   → ativar / desativar (toggle)
    'api/users'         => ['controller' => 'UserController', 'method' => 'index'],
    'api/users/status'  => ['controller' => 'UserController', 'method' => 'toggleStatus'],

    /*** PRODUTOS — REST /api/produtos/ ***/
    // GET    /api/produtos             → listar todos (server-side DataTables)
    // GET    /api/produtos?simples=1   → lista simples para selects
    // POST   /api/produtos             → criar
    // PUT    /api/produtos             → atualizar
    // DELETE /api/produtos             → excluir
    // PATCH  /api/produtos/status      → bloquear / desbloquear (toggle)
    // GET    /api/produtos/historico   → histórico de movimentações do produto
    'api/produtos'           => ['controller' => 'ProdutoController', 'method' => 'index'],
    'api/produtos/status'    => ['controller' => 'ProdutoController', 'method' => 'toggleStatus'],
    'api/produtos/historico' => ['controller' => 'ProdutoController', 'method' => 'historico'],

    /*** ESTOQUE — REST /api/estoque/ ***/
    // GET   /api/estoque            → listar posição de estoque (server-side DataTables)
    // POST  /api/estoque/movimentar → registrar entrada / saída / ajuste
    'api/estoque'            => ['controller' => 'EstoqueController', 'method' => 'index'],
    'api/estoque/movimentar' => ['controller' => 'EstoqueController', 'method' => 'movimentar'],

    /*** MOVIMENTAÇÕES — somente leitura ***/
    // GET  /api/movimentacoes  → listar histórico completo (server-side DataTables)
    'api/movimentacoes'      => ['controller' => 'MovimentacaoController', 'method' => 'index'],

    /*** VENDAS — REST /api/vendas/ ***/
    // GET    /api/vendas           → listar (server-side DataTables)
    // POST   /api/vendas           → criar venda com itens (baixa estoque)
    // DELETE /api/vendas           → excluir (somente status 'aberta')
    // PATCH  /api/vendas/status    → alterar status (cancelar, etc.)
    // GET    /api/vendas/itens     → itens + pagamentos de uma venda (GET ?id=X)
    // POST   /api/vendas/finalizar → finalizar venda (verifica pagamentos)
    'api/vendas'             => ['controller' => 'VendaController', 'method' => 'index'],
    'api/vendas/status'      => ['controller' => 'VendaController', 'method' => 'alterarStatus'],
    'api/vendas/itens'       => ['controller' => 'VendaController', 'method' => 'itens'],
    'api/vendas/finalizar'   => ['controller' => 'VendaController', 'method' => 'finalizar'],

    /*** PAGAMENTOS — REST /api/pagamentos/ ***/
    // GET    /api/pagamentos         → listar (server-side DataTables)
    // POST   /api/pagamentos         → criar
    // PUT    /api/pagamentos         → atualizar
    // DELETE /api/pagamentos         → excluir (somente status 'pendente')
    // PATCH  /api/pagamentos/status  → alterar status
    'api/pagamentos'         => ['controller' => 'PagamentoController', 'method' => 'index'],
    'api/pagamentos/status'  => ['controller' => 'PagamentoController', 'method' => 'alterarStatus'],
];