<?php

/**
 * routes.php — PDVix CRM v3.0
 *
 * ADICIONADO:
 *   api/pdv/pos/criar    → criarPosPdv()   — envia pedido para Stone POS
 *   api/pdv/pos/cancelar → cancelarPosPdv() — cancela pedido POS
 *   api/pdv/pos/status   → statusPosPdv()  — consulta status pedido POS
 */

$routes = [

    // ── Autenticação / Home ───────────────────────────────────────────────────
    ''       => ['controller' => 'HomeController',   'method' => 'index'],
    'login'  => ['controller' => 'AuthController',   'method' => 'index'],
    'auth'   => ['controller' => 'AuthController',   'method' => 'authentication'],
    'logout' => ['controller' => 'AuthController',   'method' => 'logout'],

    // ── PDV Electron — Carga inicial ──────────────────────────────────────────
    'api/carga-inicial' => ['controller' => 'CargaInicialController', 'method' => 'index'],

    // ── PDV Electron — Sincronização ──────────────────────────────────────────
    'api/pdv/sync-venda'        => ['controller' => 'PdvSyncController',  'method' => 'sync'],
    'api/pdv/sync-caixa'        => ['controller' => 'PdvCaixaController', 'method' => 'sync'],
    'api/pdv/sync-cancelamento' => ['controller' => 'CancelamentoController', 'method' => 'syncCancelamento'],

    // ── PDV — Consultas ───────────────────────────────────────────────────────
    'api/pdv/caixas/detalhe' => ['controller' => 'PdvCaixaController', 'method' => 'detalhe'],
    'api/pdv/caixas'         => ['controller' => 'PdvCaixaController', 'method' => 'listar'],

    // ── PDV — Status e Comandos remotos ──────────────────────────────────────
    'api/pdv/status'  => ['controller' => 'PdvController', 'method' => 'status'],
    'api/pdv/comando' => ['controller' => 'PdvController', 'method' => 'comando'],
    'api/pdv/carga'   => ['controller' => 'PdvController', 'method' => 'carga'],

    // ── Lojas ─────────────────────────────────────────────────────────────────
    'api/lojas/pdvs' => ['controller' => 'LojaController', 'method' => 'pdvs'],
    'api/lojas'      => ['controller' => 'LojaController', 'method' => 'index'],

    // ── Usuários ──────────────────────────────────────────────────────────────
    'api/users'        => ['controller' => 'UserController', 'method' => 'index'],
    'api/users/status' => ['controller' => 'UserController', 'method' => 'toggleStatus'],

    // ── Produtos ─────────────────────────────────────────────────────────────
    'api/produtos'           => ['controller' => 'ProdutoController', 'method' => 'index'],
    'api/produtos/status'    => ['controller' => 'ProdutoController', 'method' => 'toggleStatus'],
    'api/produtos/historico' => ['controller' => 'ProdutoController', 'method' => 'historico'],

    // ── Estoque ───────────────────────────────────────────────────────────────
    'api/estoque'            => ['controller' => 'EstoqueController', 'method' => 'index'],
    'api/estoque/movimentar' => ['controller' => 'EstoqueController', 'method' => 'movimentar'],

    // ── Movimentações ─────────────────────────────────────────────────────────
    'api/movimentacoes' => ['controller' => 'MovimentacaoController', 'method' => 'index'],

    // ── Vendas ────────────────────────────────────────────────────────────────
    'api/vendas'           => ['controller' => 'VendaController', 'method' => 'index'],
    'api/vendas/status'    => ['controller' => 'VendaController', 'method' => 'alterarStatus'],
    'api/vendas/itens'     => ['controller' => 'VendaController', 'method' => 'itens'],
    'api/vendas/finalizar' => ['controller' => 'VendaController', 'method' => 'finalizar'],

    // ── Pagamentos (painel) ───────────────────────────────────────────────────
    'api/pagamentos'        => ['controller' => 'PagamentoController', 'method' => 'index'],
    'api/pagamentos/status' => ['controller' => 'PagamentoController', 'method' => 'alterarStatus'],

    // ── PIX — Painel (sessão) ─────────────────────────────────────────────────
    'api/pagamentos/pix/criar'    => ['controller' => 'PagarmeController', 'method' => 'criarPix'],
    'api/pagamentos/pix/cancelar' => ['controller' => 'PagarmeController', 'method' => 'cancelarPix'],
    'api/pagamentos/pix/status'   => ['controller' => 'PagarmeController', 'method' => 'statusPix'],

    // ── PIX — PDV Electron (token) ────────────────────────────────────────────
    'api/pdv/pix/criar'    => ['controller' => 'PagarmeController', 'method' => 'criarPixPdv'],
    'api/pdv/pix/cancelar' => ['controller' => 'PagarmeController', 'method' => 'cancelarPixPdv'],
    'api/pdv/pix/status'   => ['controller' => 'PagarmeController', 'method' => 'statusPixPdv'],

    // ── Stone POS — PDV Electron (token) — NOVO ───────────────────────────────
    // POST /api/pdv/pos/criar?token=XXX
    //   Body: { venda_id, valor, device_serial_number, tipo?, installments?, ... }
    //   Cria pedido na Pagar.me com poi_payment_settings e envia para a maquininha.
    //   Resposta chega via webhook → pdv:pagamento_confirmado (WebSocket).
    //
    // POST /api/pdv/pos/cancelar?token=XXX
    //   Body: { order_id }
    //   Cancela o pedido POS antes de ser pago na maquininha.
    //
    // GET /api/pdv/pos/status?token=XXX&order_id=X
    //   Consulta status do pedido POS no banco local.
    'api/pdv/pos/criar'    => ['controller' => 'PagarmeController', 'method' => 'criarPosPdv'],
    'api/pdv/pos/cancelar' => ['controller' => 'PagarmeController', 'method' => 'cancelarPosPdv'],
    'api/pdv/pos/status'   => ['controller' => 'PagarmeController', 'method' => 'statusPosPdv'],

    // ── Webhook Pagar.me ──────────────────────────────────────────────────────
    'api/webhook/pagarme' => ['controller' => 'PagarmeController', 'method' => 'webhook'],

    // ── Cancelamentos ─────────────────────────────────────────────────────────
    'api/cancelamentos'       => ['controller' => 'CancelamentoController', 'method' => 'listar'],
    'api/cancelamentos/venda' => ['controller' => 'CancelamentoController', 'method' => 'cancelarVenda'],
    'api/cancelamentos/item'  => ['controller' => 'CancelamentoController', 'method' => 'cancelarItem'],

    // ── Comandas ─────────────────────────────────────────────────────────────
    'api/comandas/enviar' => ['controller' => 'ComandaController', 'method' => 'enviar'],
    'api/comandas'        => ['controller' => 'ComandaController', 'method' => 'index'],

    // ── Dashboard ─────────────────────────────────────────────────────────────
    'api/dashboard/resumo'           => ['controller' => 'DashboardController', 'method' => 'resumo'],
    'api/dashboard/vendas-hora'      => ['controller' => 'DashboardController', 'method' => 'vendasPorHora'],
    'api/dashboard/top-produtos'     => ['controller' => 'DashboardController', 'method' => 'topProdutos'],
    'api/dashboard/formas-pagamento' => ['controller' => 'DashboardController', 'method' => 'formasPagamento'],
    'api/dashboard/pdvs-status'      => ['controller' => 'DashboardController', 'method' => 'pdvsStatus'],

    // ── Espelho de Caixa ──────────────────────────────────────────────────────
    'api/espelho-pdv' => ['controller' => 'EspelhoController', 'method' => 'espelho'],
    'espelho-caixa'   => ['controller' => 'EspelhoController', 'method' => 'imprimirEspelho'],

    // ── Configurações (somente administrador) ─────────────────────────────────
    'api/config'             => ['controller' => 'ConfigController', 'method' => 'index'],
    'api/config/maquininhas' => ['controller' => 'ConfigController', 'method' => 'maquininhas'],

    // ── Pagar.me — Gerenciamento (Painel) ─────────────────────────────────────
    'api/pagarme/transacoes' => ['controller' => 'PagarmeController', 'method' => 'listarTransacoes'],

    // ── InfiniteTap — PDV Electron (token) ────────────────────────────────────────
//
// POST /api/pdv/tap/criar?token=XXX
//   Body: { venda_id, valor, payment_method, installments?, numero_venda,
//           loja_id, numero_pdv, handle?, doc_number? }
//   Gera o deeplink para o app InfinitePay e salva a order localmente.
//   Retorna: { order_id, deeplink_url, valor_centavos }
//   O PDV Electron exibe QR do deeplink_url para o operador escanear
//   com o celular que tem o InfinitePay instalado.
//
// POST /api/pdv/tap/cancelar?token=XXX
//   Body: { order_id }
//   Cancela uma order pendente (operador desistiu antes de apresentar o Tap).
//
// GET /api/pdv/tap/status?token=XXX&order_id=XXX
//   Consulta status da order no banco local (polling de fallback).
//   A confirmação principal chega via WebSocket (pdv:pagamento_confirmado).

'api/pdv/tap/criar'    => ['controller' => 'InfiniteTapController', 'method' => 'criarTapPdv'],
'api/pdv/tap/cancelar' => ['controller' => 'InfiniteTapController', 'method' => 'cancelarTapPdv'],
'api/pdv/tap/status'   => ['controller' => 'InfiniteTapController', 'method' => 'statusTapPdv'],

// ── Webhook InfinitePay (result_url callback) ──────────────────────────────────
//
// GET /api/webhook/infinitetap?order_id=XXX&nsu=XXX&aut=XXX&...
//   Chamado diretamente pelo app InfinitePay após a transação Tap to Pay.
//   Sem autenticação por token (autenticado pelo order_id interno).
//   Atualiza status da order + registra pagamento + notifica PDV via WebSocket.

'api/webhook/infinitetap' => ['controller' => 'InfiniteTapController', 'method' => 'webhookInfinitetap'],
];