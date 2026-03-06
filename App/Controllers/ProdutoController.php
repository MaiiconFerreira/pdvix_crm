<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProdutoModel;
use App\Services\ProdutoService;

/**
 * ProdutoController
 *
 * Rotas mapeadas em routes.php:
 *   GET    /api/produtos             → index()        listar (DataTables server-side)
 *   POST   /api/produtos             → index()        criar
 *   PUT    /api/produtos             → index()        atualizar
 *   DELETE /api/produtos             → index()        excluir
 *   PATCH  /api/produtos/status      → toggleStatus() bloquear / desbloquear
 *   GET    /api/produtos/historico   → historico()    histórico de movimentações
 */
class ProdutoController extends Controller
{
    private ProdutoModel   $produtoModel;
    private ProdutoService $produtoService;

    public function __construct()
    {
        $this->produtoModel   = new ProdutoModel();
        $this->produtoService = new ProdutoService();
    }

    // =========================================================================
    // DISPATCHER
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();

        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        match ($method) {
            'GET'    => $this->listProdutos(),
            'POST'   => $this->createProduto(),
            'PUT'    => $this->updateProduto(),
            'DELETE' => $this->deleteProduto(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // GET /api/produtos
    // =========================================================================

    private function listProdutos(): void
    {
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        if (isset($_GET['simples'])) {
            $data = $this->produtoModel->listProdutosSimples();
            $this->responseJson('success', $data, 'OK');
        }

        $result = $this->produtoModel->listProdutos($_GET);
        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // POST /api/produtos
    // =========================================================================

    private function createProduto(): void
    {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(
            ['nome', 'preco_venda', 'custo_item', 'fator_embalagem', 'fornecedor_id'],
            ['codigo_interno_alternativo', 'unidade_base',
             'codigo_barras_un', 'codigo_barras_cx',
             'codigo_barras_kg', 'codigo_barras_g']
        );

        try {
            $this->produtoService->validarDados($data);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $payload = [
            'nome'                       => $data['nome'],
            'codigo_interno_alternativo' => !empty($data['codigo_interno_alternativo'])
                                            ? (int) $data['codigo_interno_alternativo']
                                            : null,
            'preco_venda'                => (float) $data['preco_venda'],
            'custo_item'                 => (float) $data['custo_item'],
            'fator_embalagem'            => (int) $data['fator_embalagem'],
            'unidade_base'               => in_array($data['unidade_base'] ?? '', ['UN', 'G'], true)
                                            ? $data['unidade_base']
                                            : 'UN',
            'fornecedor_id'              => (int) $data['fornecedor_id'],
            'ultima_alteracao_por'       => (int) $_SESSION['logado']->id,
        ];

        $novoId = $this->produtoModel->createProduto($payload);

        if (!$novoId) {
            $this->responseJson('error', [], 'Erro ao criar produto.');
        }

        // Persiste todos os tipos de código de barras
        $this->_salvarCodigosBarras($novoId, $data, (float) $data['preco_venda']);

        $this->responseJson('success', ['id' => $novoId], 'Produto criado com sucesso!');
    }

    // =========================================================================
    // PUT /api/produtos
    // =========================================================================

    private function updateProduto(): void
    {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(
            ['id'],
            ['nome', 'preco_venda', 'custo_item', 'fator_embalagem', 'unidade_base',
             'fornecedor_id', 'codigo_interno_alternativo',
             'codigo_barras_un', 'codigo_barras_cx',
             'codigo_barras_kg', 'codigo_barras_g']
        );

        $id = (int) $data['id'];

        if ($id <= 0 || !$this->produtoModel->findById($id)) {
            $this->responseJson('error', [], 'Produto não encontrado.');
        }

        try {
            $this->produtoService->validarDados($data, $id);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $campos = [];
        $opcionais = ['nome', 'preco_venda', 'custo_item', 'fator_embalagem', 'unidade_base',
                      'fornecedor_id', 'codigo_interno_alternativo'];

        foreach ($opcionais as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== '') {
                $campos[$campo] = $data[$campo];
            }
        }

        $campos['ultima_alteracao_por'] = (int) $_SESSION['logado']->id;

        if (empty($campos)) {
            $this->responseJson('error', [], 'Nenhum dado para atualizar.');
        }

        $ok = $this->produtoModel->updateProduto($id, $campos);

        // Atualiza/cria todos os códigos de barras informados
        $preco = isset($campos['preco_venda']) ? (float) $campos['preco_venda'] : 0.0;
        $this->_salvarCodigosBarras($id, $data, $preco);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Produto atualizado com sucesso!' : 'Erro ao atualizar produto.'
        );
    }

    // =========================================================================
    // DELETE /api/produtos
    // =========================================================================

    private function deleteProduto(): void
    {
        $this->requirePerfil(['administrador']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if ($id <= 0 || !$this->produtoModel->findById($id)) {
            $this->responseJson('error', [], 'Produto não encontrado.');
        }

        if ($this->produtoModel->temMovimentacoes($id)) {
            $this->responseJson(
                'error', [],
                'Não é possível excluir: o produto possui movimentações de estoque vinculadas.'
            );
        }

        $ok = $this->produtoModel->deleteProduto($id);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok
                ? 'Produto excluído com sucesso!'
                : 'Erro ao excluir produto. Verifique se há registros vinculados.'
        );
    }

    // =========================================================================
    // PATCH /api/produtos/status
    // =========================================================================

    public function toggleStatus(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('PATCH');
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if ($id <= 0 || !$this->produtoModel->findById($id)) {
            $this->responseJson('error', [], 'Produto não encontrado.');
        }

        $novoBloqueado = $this->produtoModel->toggleBloqueado($id);

        if ($novoBloqueado === null) {
            $this->responseJson('error', [], 'Erro ao alterar status do produto.');
        }

        $label = $novoBloqueado === 1 ? 'bloqueado' : 'desbloqueado';
        $this->responseJson(
            'success',
            ['bloqueado' => $novoBloqueado],
            "Produto {$label} com sucesso!"
        );
    }

    // =========================================================================
    // GET /api/produtos/historico
    // =========================================================================

    public function historico(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            $this->responseJson('error', [], 'ID do produto é obrigatório.', 400);
        }

        $id = (int) $_GET['id'];

        try {
            $historico = $this->produtoService->buildHistorico($id);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage());
        }

        $this->responseJson('success', $historico, 'Histórico carregado com sucesso.');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Persiste códigos de barras para todos os tipos que vieram no request.
     */
    private function _salvarCodigosBarras(int $produtoId, array $data, float $preco): void
    {
        $mapa = [
            'codigo_barras_un' => 'UN',
            'codigo_barras_cx' => 'CX',
            'codigo_barras_kg' => 'KG',
            'codigo_barras_g'  => 'G',
        ];

        foreach ($mapa as $campo => $tipo) {
            if (!empty($data[$campo])) {
                $this->produtoModel->upsertCodigoBarras($produtoId, $tipo, $data[$campo], $preco);
            }
        }
    }
    }