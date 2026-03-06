<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\VendaModel;
use App\Services\VendaService;

/**
 * VendaController
 *
 * Rotas mapeadas em routes.php:
 *   GET/POST/PUT/DELETE  /api/vendas           → index()
 *   PATCH                /api/vendas/status    → alterarStatus()
 *   GET                  /api/vendas/itens     → itens()
 *   POST                 /api/vendas/finalizar → finalizar()
 */
class VendaController extends Controller
{
    private VendaModel   $vendaModel;
    private VendaService $vendaService;

    public function __construct()
    {
        $this->vendaModel   = new VendaModel();
        $this->vendaService = new VendaService();
    }

    // =========================================================================
    // DISPATCHER
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->listVendas(),
            'POST'   => $this->createVenda(),
            'DELETE' => $this->deleteVenda(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // GET /api/vendas
    // =========================================================================

    private function listVendas(): void
    {
        $this->requirePerfil(['administrador', 'gerente', 'operador']);
        $result = $this->vendaModel->listVendas($_GET);
        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // POST /api/vendas
    // =========================================================================

    private function createVenda(): void
    {
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $data = $this->getRequestData(
            ['itens'],
            ['observacao', 'desconto', 'acrescimo', 'cliente_id']
        );

        $itens = $data['itens'];
        if (!is_array($itens)) {
            // Tenta decodificar caso venha como JSON string
            $itens = json_decode($itens, true) ?? [];
        }

        $usuarioId = (int) $_SESSION['logado']->id;

        try {
            $vendaId = $this->vendaService->criarVenda($data, $itens, $usuarioId);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->responseJson('error', [], 'Erro interno ao criar venda.');
        }

        $this->responseJson('success', ['id' => $vendaId], 'Venda criada com sucesso!');
    }

    // =========================================================================
    // DELETE /api/vendas
    // =========================================================================

    private function deleteVenda(): void
    {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if ($id <= 0 || !$this->vendaModel->findById($id)) {
            $this->responseJson('error', [], 'Venda não encontrada.');
        }

        $ok = $this->vendaModel->deleteVenda($id);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Venda excluída com sucesso!' : 'Não é possível excluir. Apenas vendas "aberta" podem ser removidas.'
        );
    }

    // =========================================================================
    // PATCH /api/vendas/status
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

        if ($id <= 0 || !$this->vendaModel->findById($id)) {
            $this->responseJson('error', [], 'Venda não encontrada.');
        }

        $ok = $this->vendaModel->alterarStatus($id, $status);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Status alterado com sucesso!' : 'Erro ao alterar status da venda.'
        );
    }

    // =========================================================================
    // GET /api/vendas/itens
    // =========================================================================

    public function itens(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            $this->responseJson('error', [], 'ID da venda é obrigatório.', 400);
        }

        $id      = (int) $_GET['id'];
        $itens   = $this->vendaModel->getItens($id);
        $pagtos  = $this->vendaModel->getPagamentos($id);

        $this->responseJson('success', ['itens' => $itens, 'pagamentos' => $pagtos], 'OK');
    }

    // =========================================================================
    // POST /api/vendas/finalizar
    // =========================================================================

    public function finalizar(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        try {
            $this->vendaService->finalizarVenda($id);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $this->responseJson('success', [], 'Venda finalizada com sucesso!');
    }

    }