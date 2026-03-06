<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\PagamentoModel;
use App\Services\PagamentoService;

/**
 * PagamentoController
 *
 * Rotas mapeadas em routes.php:
 *   GET/POST/PUT/DELETE  /api/pagamentos         → index()
 *   PATCH                /api/pagamentos/status  → alterarStatus()
 */
class PagamentoController extends Controller
{
    private PagamentoModel   $pagamentoModel;
    private PagamentoService $pagamentoService;

    public function __construct()
    {
        $this->pagamentoModel   = new PagamentoModel();
        $this->pagamentoService = new PagamentoService();
    }

    // =========================================================================
    // DISPATCHER
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->listPagamentos(),
            'POST'   => $this->createPagamento(),
            'PUT'    => $this->updatePagamento(),
            'DELETE' => $this->deletePagamento(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // GET /api/pagamentos
    // =========================================================================

    private function listPagamentos(): void
    {
        $this->requirePerfil(['administrador', 'gerente', 'operador']);
        $result = $this->pagamentoModel->listPagamentos($_GET);
        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // POST /api/pagamentos
    // =========================================================================

    private function createPagamento(): void
    {
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $data = $this->getRequestData(
            ['venda_id', 'tipo_pagamento', 'valor'],
            ['referencia_externa', 'descricao', 'status']
        );

        try {
            $this->pagamentoService->validar($data);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $ok = $this->pagamentoModel->createPagamento($data);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Pagamento registrado com sucesso!' : 'Erro ao registrar pagamento.'
        );
    }

    // =========================================================================
    // PUT /api/pagamentos
    // =========================================================================

    private function updatePagamento(): void
    {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(
            ['id'],
            ['venda_id', 'tipo_pagamento', 'valor', 'referencia_externa', 'descricao']
        );

        $id = (int) $data['id'];
        if ($id <= 0 || !$this->pagamentoModel->findById($id)) {
            $this->responseJson('error', [], 'Pagamento não encontrado.');
        }

        try {
            $this->pagamentoService->validar($data, $id);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $ok = $this->pagamentoModel->updatePagamento($id, $data);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Pagamento atualizado com sucesso!' : 'Erro ao atualizar pagamento.'
        );
    }

    // =========================================================================
    // DELETE /api/pagamentos
    // =========================================================================

    private function deletePagamento(): void
    {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if (!$this->pagamentoService->podeExcluir($id)) {
            $this->responseJson('error', [], 'Somente pagamentos com status "pendente" podem ser excluídos.');
        }

        $ok = $this->pagamentoModel->deletePagamento($id);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Pagamento excluído com sucesso!' : 'Erro ao excluir pagamento.'
        );
    }

    // =========================================================================
    // PATCH /api/pagamentos/status
    // =========================================================================

    public function alterarStatus(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('PATCH');
        $this->requirePerfil(['administrador', 'gerente']);

        $data   = $this->getRequestData(['id', 'status']);
        $id     = (int) $data['id'];
        $status = $data['status'];

        if ($id <= 0 || !$this->pagamentoModel->findById($id)) {
            $this->responseJson('error', [], 'Pagamento não encontrado.');
        }

        $ok = $this->pagamentoModel->alterarStatus($id, $status);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Status do pagamento alterado com sucesso!' : 'Erro ao alterar status. Verifique o valor informado.'
        );
    }

    }