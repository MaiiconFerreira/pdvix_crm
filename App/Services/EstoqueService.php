<?php
namespace App\Services;

use App\Models\EstoqueModel;
use App\Core\Database;

/**
 * EstoqueService
 *
 * Regras de negócio para movimentações de estoque.
 * Gerencia a transação PDO para garantir atomicidade entre
 * o registro da movimentação e a atualização do saldo.
 *
 * CONVERSÃO DE UNIDADES — tabela resumo:
 * ┌──────────────────┬────────────────────────────────────────────────────────┐
 * │ unidade_origem   │ Conversão para unidade base (estoque)                  │
 * ├──────────────────┼────────────────────────────────────────────────────────┤
 * │ UN               │ quantidadeBase = round(quantidade)           [inteiro]  │
 * │ CX               │ quantidadeBase = round(quantidade × fator)   [inteiro]  │
 * │ KG               │ quantidadeBase = round(quantidade × 1000)    [gramas]   │
 * │ G                │ quantidadeBase = round(quantidade)            [gramas]   │
 * └──────────────────┴────────────────────────────────────────────────────────┘
 *
 * O campo estoque_movimentacoes.quantidade armazena a qtd na unidade ORIGINAL.
 * O campo estoque.quantidade_atual armazena SEMPRE a unidade base:
 *   - Produto UN/CX  → unidades inteiras
 *   - Produto G/KG   → gramas inteiras
 */
class EstoqueService
{
    private EstoqueModel $estoqueModel;
    private \PDO         $pdo;

    public function __construct()
    {
        $this->estoqueModel = new EstoqueModel();
        $this->pdo          = Database::getConnection();
    }

    // =========================================================================
    // REGISTRAR MOVIMENTAÇÃO
    // =========================================================================

    /**
     * Valida, persiste a movimentação e atualiza o estoque em uma única transação.
     *
     * @param array $data       Dados da movimentação
     * @param int   $usuarioId  ID do usuário que executa a ação
     * @throws \InvalidArgumentException Validação de negócio
     * @throws \RuntimeException         Falha de persistência
     */
    public function registrarMovimentacao(array $data, int $usuarioId): bool
    {
        // ── Validações de entrada ──────────────────────────────────────────────
        $produtoId  = (int)   ($data['produto_id']     ?? 0);
        $quantidade = (float) ($data['quantidade']     ?? 0);  // float para KG fracionado
        $tipo       =         ($data['tipo_movimento'] ?? '');
        $unidade    =         ($data['unidade_origem'] ?? 'UN');
        $origem     =         ($data['origem']         ?? 'AJUSTE');

        if ($produtoId <= 0) {
            throw new \InvalidArgumentException('Produto inválido.');
        }

        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $tiposValidos = ['ENTRADA', 'SAIDA', 'AJUSTE'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new \InvalidArgumentException('Tipo de movimento inválido. Use: ENTRADA, SAIDA ou AJUSTE.');
        }

        $unidadesValidas = ['UN', 'CX', 'KG', 'G'];
        if (!in_array($unidade, $unidadesValidas, true)) {
            throw new \InvalidArgumentException('Unidade de origem inválida. Use: UN, CX, KG ou G.');
        }

        $origensValidas = ['VENDA', 'COMPRA', 'AJUSTE', 'DEVOLUCAO', 'ESTORNO'];
        if (!in_array($origem, $origensValidas, true)) {
            throw new \InvalidArgumentException('Origem inválida.');
        }

        // ── Conversão para unidade base ────────────────────────────────────────
        $infoProduto   = $this->estoqueModel->getInfoProduto($produtoId);
        $fator         = $infoProduto['fator'];
        $unidadeBase   = $infoProduto['unidade_base']; // 'UN' ou 'G'

        $quantidadeBase = $this->_converter($quantidade, $unidade, $fator);

        // ── Validação cruzada: unidade × unidade_base do produto ───────────────
        // Impede usar KG/G em produto UN e vice-versa
        if ($unidadeBase === 'UN' && in_array($unidade, ['KG', 'G'], true)) {
            throw new \InvalidArgumentException(
                'Este produto é vendido em unidades (UN/CX). Use unidade de origem UN ou CX.'
            );
        }
        if ($unidadeBase === 'G' && in_array($unidade, ['UN', 'CX'], true)) {
            throw new \InvalidArgumentException(
                'Este produto é vendido por peso. Use unidade de origem KG ou G.'
            );
        }

        // ── Verifica estoque para SAÍDA ────────────────────────────────────────
        if ($tipo === 'SAIDA') {
            $qtdAtual = $this->estoqueModel->getQuantidadeAtual($produtoId);
            if ($qtdAtual < $quantidadeBase) {
                $dispLabel = $this->_formatarQuantidade($qtdAtual, $unidadeBase);
                $solLabel  = $this->_formatarMovimentacao($quantidade, $unidade, $quantidadeBase, $unidadeBase);
                throw new \InvalidArgumentException(
                    "Estoque insuficiente. Disponível: {$dispLabel}, solicitado: {$solLabel}."
                );
            }
        }

        // ── Transação ──────────────────────────────────────────────────────────
        $this->pdo->beginTransaction();

        try {
            // Grava na unidade ORIGINAL (ex.: 1.5 KG, 1 CX, 500 G)
            $movData = [
                'produto_id'          => $produtoId,
                'tipo_movimento'      => $tipo,
                'quantidade'          => $quantidade,    // original — pode ser float (KG)
                'unidade_origem'      => $unidade,
                'codigo_barras_usado' => $data['codigo_barras_usado'] ?? null,
                'motivo'              => $data['motivo']              ?? null,
                'referencia_id'       => $data['referencia_id']       ?? null,
                'origem'              => $origem,
                'usuario_id'          => $usuarioId,
            ];

            $ok = $this->estoqueModel->registrarMovimentacao($movData);
            if (!$ok) {
                throw new \RuntimeException('Falha ao registrar movimentação no banco.');
            }

            // Atualiza saldo em unidade base (gramas ou unidades)
            $ok = $this->estoqueModel->atualizarQuantidade($produtoId, $tipo, $quantidadeBase);
            if (!$ok) {
                throw new \RuntimeException('Falha ao atualizar quantidade em estoque.');
            }

            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Converte a quantidade para unidade base (inteiro).
     *
     * @param float  $quantidade  Quantidade informada pelo usuário
     * @param string $unidade     UN | CX | KG | G
     * @param int    $fator       fator_embalagem do produto
     * @return int  Quantidade na unidade base (unidades ou gramas)
     */
    private function _converter(float $quantidade, string $unidade, int $fator): int
    {
        return match ($unidade) {
            'CX' => (int) round($quantidade * $fator),
            'KG' => (int) round($quantidade * 1000),
            'G'  => (int) round($quantidade),
            'UN' => (int) round($quantidade),
            default => (int) round($quantidade),
        };
    }

    /**
     * Formata quantidade para exibição amigável no erro de estoque insuficiente.
     *
     * @param int    $qtd         Quantidade em unidade base
     * @param string $unidadeBase 'UN' ou 'G'
     */
    private function _formatarQuantidade(int $qtd, string $unidadeBase): string
    {
        if ($unidadeBase === 'G') {
            return $qtd >= 1000
                ? number_format($qtd / 1000, 3, ',', '.') . ' kg'
                : "{$qtd} g";
        }
        return "{$qtd} UN";
    }

    /**
     * Formata a movimentação para exibição amigável no erro de estoque insuficiente.
     */
    private function _formatarMovimentacao(
        float $quantidade, string $unidade,
        int $quantidadeBase, string $unidadeBase
    ): string {
        $baseFormatada = $this->_formatarQuantidade($quantidadeBase, $unidadeBase);

        return match ($unidade) {
            'CX' => "{$quantidade} CX (= {$baseFormatada})",
            'KG' => "{$quantidade} kg (= {$baseFormatada})",
            default => $baseFormatada,
        };
    }
}