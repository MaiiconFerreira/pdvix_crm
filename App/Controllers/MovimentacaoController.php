<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\MovimentacaoModel;

/**
 * MovimentacaoController
 *
 * Rotas mapeadas em routes.php:
 *   GET  /api/movimentacoes  → index()  listar (DataTables server-side, somente leitura)
 */
class MovimentacaoController extends Controller
{
    private MovimentacaoModel $movimentacaoModel;

    public function __construct()
    {
        $this->movimentacaoModel = new MovimentacaoModel();
    }

    // =========================================================================
    // GET /api/movimentacoes
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $result = $this->movimentacaoModel->listMovimentacoes($_GET);
        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // HELPER PRIVADO
    // =========================================================================

    private function requirePerfil(array $perfisPermitidos): void
    {
        $perfilLogado = $_SESSION['logado']->perfil ?? '';
        if (!in_array($perfilLogado, $perfisPermitidos, true)) {
            $this->responseJson('error', [], 'Sem permissão para executar esta ação.', 403);
        }
    }
}