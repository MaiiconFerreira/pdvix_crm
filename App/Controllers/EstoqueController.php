<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\EstoqueModel;
use App\Services\EstoqueService;

/**
 * EstoqueController
 *
 * Rotas mapeadas em routes.php:
 *   GET   /api/estoque            → index()      listar (DataTables server-side)
 *   POST  /api/estoque/movimentar → movimentar() registrar entrada/saída/ajuste
 */
class EstoqueController extends Controller
{
    private EstoqueModel   $estoqueModel;
    private EstoqueService $estoqueService;

    public function __construct()
    {
        $this->estoqueModel   = new EstoqueModel();
        $this->estoqueService = new EstoqueService();
    }

    // =========================================================================
    // GET /api/estoque
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $result = $this->estoqueModel->listEstoque($_GET);
        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // POST /api/estoque/movimentar
    // =========================================================================

    public function movimentar(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $data = $this->getRequestData(
            ['produto_id', 'tipo_movimento', 'quantidade', 'unidade_origem', 'origem'],
            ['codigo_barras_usado', 'motivo', 'referencia_id']
        );

        $usuarioId = (int) $_SESSION['logado']->id;

        try {
            $this->estoqueService->registrarMovimentacao($data, $usuarioId);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->responseJson('error', [], 'Erro interno ao processar movimentação.');
        }

        $this->responseJson('success', [], 'Movimentação registrada com sucesso!');
    }

    }