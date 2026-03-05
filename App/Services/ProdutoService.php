<?php
namespace App\Services;

use App\Models\ProdutoModel;

class ProdutoService
{
    private ProdutoModel $produtoModel;

    public function __construct()
    {
        $this->produtoModel = new ProdutoModel();
    }

    // =========================================================================
    // VALIDAÇÃO DE DADOS (CREATE / UPDATE)
    // =========================================================================

    /**
     * Valida os campos obrigatórios e regras de negócio antes de persistir.
     *
     * @param array    $data  Dados vindos do request (já sanitizados pelo Controller)
     * @param int|null $id    ID do produto em edição (null = criação)
     */
    public function validarDados(array $data, ?int $id = null): void
    {
        // ── Campos obrigatórios ───────────────────────────────────────────────
        $obrigatorios = ['nome', 'preco_venda', 'custo_item', 'fator_embalagem', 'fornecedor_id'];
        foreach ($obrigatorios as $campo) {
            if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$campo}.");
            }
        }

        // ── Tipos numéricos ───────────────────────────────────────────────────
        if (!is_numeric($data['preco_venda']) || (float) $data['preco_venda'] < 0) {
            throw new \InvalidArgumentException('Preço de venda inválido. Informe um valor numérico não negativo.');
        }

        if (!is_numeric($data['custo_item']) || (float) $data['custo_item'] < 0) {
            throw new \InvalidArgumentException('Custo do item inválido. Informe um valor numérico não negativo.');
        }

        if (!is_numeric($data['fator_embalagem']) || (int) $data['fator_embalagem'] < 1) {
            throw new \InvalidArgumentException('Fator de embalagem deve ser um inteiro maior ou igual a 1.');
        }

        if (!is_numeric($data['fornecedor_id']) || (int) $data['fornecedor_id'] < 1) {
            throw new \InvalidArgumentException('Fornecedor inválido.');
        }

        // ── Unidade base ──────────────────────────────────────────────────────
        // Campo opcional no update, obrigatório só no create (default = 'UN')
        if (!empty($data['unidade_base'])) {
            if (!in_array($data['unidade_base'], ['UN', 'G'], true)) {
                throw new \InvalidArgumentException("Unidade base inválida. Use 'UN' (unidades) ou 'G' (peso em gramas).");
            }
        }

        // ── Regra de negócio: produto por peso não pode ter CX sem fator ─────
        // Se unidade_base = 'G', fator_embalagem representa a fração mínima em gramas
        // (ex.: 50 = mínimo de 50g por fracionamento). Valor mínimo: 1g.
        if (!empty($data['unidade_base']) && $data['unidade_base'] === 'G') {
            if ((int) $data['fator_embalagem'] < 1) {
                throw new \InvalidArgumentException(
                    'Para produtos por peso, o fator de embalagem representa a fração mínima em gramas (mínimo: 1).'
                );
            }
        }

        // ── Unicidade: código interno alternativo (se informado) ──────────────
        if (!empty($data['codigo_interno_alternativo'])) {
            if (!is_numeric($data['codigo_interno_alternativo'])) {
                throw new \InvalidArgumentException('Código interno alternativo deve ser numérico.');
            }
            if ($this->produtoModel->existePorCodigo((int) $data['codigo_interno_alternativo'], $id)) {
                throw new \InvalidArgumentException('Código interno alternativo já em uso por outro produto.');
            }
        }
    }

    // =========================================================================
    // HISTÓRICO DE MOVIMENTAÇÕES
    // =========================================================================

    /**
     * Retorna o histórico de movimentações de estoque de um produto.
     *
     * @param int $id ID do produto
     */
    public function buildHistorico(int $id): array
    {
        if (!$this->produtoModel->findById($id)) {
            throw new \InvalidArgumentException('Produto não encontrado.');
        }

        return $this->produtoModel->getHistorico($id);
    }
}