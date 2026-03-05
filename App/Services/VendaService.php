<?php
namespace App\Services;

use App\Models\VendaModel;
use App\Models\EstoqueModel;
use App\Core\Database;

/**
 * VendaService
 *
 * Regras de negócio para criação e finalização de vendas.
 * Gerencia a transação que cobre venda + itens + baixa de estoque.
 *
 * Nota: A baixa de estoque é feita diretamente via EstoqueModel (não via
 * EstoqueService) para evitar transações aninhadas, que não são suportadas
 * nativamente pelo MySQL/MariaDB.
 */
class VendaService
{
    private VendaModel   $vendaModel;
    private EstoqueModel $estoqueModel;
    private \PDO         $pdo;

    public function __construct()
    {
        $this->vendaModel   = new VendaModel();
        $this->estoqueModel = new EstoqueModel();
        $this->pdo          = Database::getConnection();
    }

    // =========================================================================
    // CRIAR VENDA
    // =========================================================================

    /**
     * Valida, persiste a venda com seus itens e baixa o estoque em uma única transação.
     *
     * @param array $dados      Campos da venda (observacao, desconto, acrescimo)
     * @param array $itens      Array de [ produto_id, quantidade, valor_unitario ]
     * @param int   $usuarioId  ID do usuário logado
     * @return int  ID da venda criada
     * @throws \InvalidArgumentException Validação de negócio
     * @throws \RuntimeException         Falha de persistência
     */
    public function criarVenda(array $dados, array $itens, int $usuarioId): int
    {
        // ── Validações básicas ─────────────────────────────────────────────────
        if (empty($itens)) {
            throw new \InvalidArgumentException('A venda deve ter ao menos um item.');
        }

        $subtotal = 0;

        foreach ($itens as $i => $item) {
            $produtoId     = (int)   ($item['produto_id']     ?? 0);
            $quantidade    = (int)   ($item['quantidade']     ?? 0);
            $valorUnitario = (float) ($item['valor_unitario'] ?? 0);

            if ($produtoId <= 0) {
                throw new \InvalidArgumentException("Item #{$i}: produto inválido.");
            }
            if ($quantidade <= 0) {
                throw new \InvalidArgumentException("Item #{$i}: quantidade deve ser maior que zero.");
            }
            if ($valorUnitario < 0) {
                throw new \InvalidArgumentException("Item #{$i}: valor unitário inválido.");
            }

            // Verifica estoque disponível
            $qtdAtual = $this->estoqueModel->getQuantidadeAtual($produtoId);
            if ($qtdAtual < $quantidade) {
                throw new \InvalidArgumentException(
                    "Estoque insuficiente para o produto ID {$produtoId}. "
                    . "Disponível: {$qtdAtual}, solicitado: {$quantidade}."
                );
            }

            $subtotal += $quantidade * $valorUnitario;
        }

        $desconto   = (float) ($dados['desconto']   ?? 0);
        $acrescimo  = (float) ($dados['acrescimo']  ?? 0);
        $total      = max(0, $subtotal - $desconto + $acrescimo);

        // ── Transação ──────────────────────────────────────────────────────────
        $this->pdo->beginTransaction();

        try {
            // Gera número de venda único (prefixo VND + timestamp)
            $numeroVenda = 'VND' . date('YmdHis') . rand(10, 99);

            $vendaData = [
                'numero_venda' => $numeroVenda,
                'cliente_id'   => $dados['cliente_id'] ?? null,
                'usuario_id'   => $usuarioId,
                'subtotal'     => $subtotal,
                'desconto'     => $desconto,
                'acrescimo'    => $acrescimo,
                'total'        => $total,
                'status'       => 'aberta',
                'observacao'   => $dados['observacao'] ?? null,
            ];

            $vendaId = $this->vendaModel->criarVenda($vendaData);
            if (!$vendaId) {
                throw new \RuntimeException('Falha ao criar venda.');
            }

            // Persiste itens e dá baixa no estoque
            foreach ($itens as $item) {
                $produtoId     = (int)   $item['produto_id'];
                $quantidade    = (int)   $item['quantidade'];
                $valorUnitario = (float) $item['valor_unitario'];

                $itemData = [
                    'venda_id'      => $vendaId,
                    'produto_id'    => $produtoId,
                    'quantidade'    => $quantidade,
                    'valor_unitario'=> $valorUnitario,
                    'subtotal'      => $quantidade * $valorUnitario,
                ];

                $ok = $this->vendaModel->criarItem($itemData);
                if (!$ok) {
                    throw new \RuntimeException("Falha ao criar item (produto_id={$produtoId}).");
                }

                // Baixa no estoque (SAIDA, origem VENDA)
                $movData = [
                    'produto_id'     => $produtoId,
                    'tipo_movimento' => 'SAIDA',
                    'quantidade'     => $quantidade,
                    'unidade_origem' => 'UN',
                    'referencia_id'  => $vendaId,
                    'origem'         => 'VENDA',
                    'usuario_id'     => $usuarioId,
                ];

                $ok = $this->estoqueModel->registrarMovimentacao($movData);
                if (!$ok) {
                    throw new \RuntimeException("Falha ao registrar movimentação (produto_id={$produtoId}).");
                }

                $ok = $this->estoqueModel->atualizarQuantidade($produtoId, 'SAIDA', $quantidade);
                if (!$ok) {
                    throw new \RuntimeException("Falha ao atualizar estoque (produto_id={$produtoId}).");
                }
            }

            $this->pdo->commit();
            return $vendaId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // FINALIZAR VENDA
    // =========================================================================

    /**
     * Finaliza uma venda: verifica se está 'aberta' e tem ao menos 1 pagamento confirmado.
     *
     * @throws \InvalidArgumentException Quando a venda não pode ser finalizada
     */
    public function finalizarVenda(int $id): bool
    {
        $venda = $this->vendaModel->findById($id);

        if (!$venda) {
            throw new \InvalidArgumentException('Venda não encontrada.');
        }

        if ($venda->status !== 'aberta') {
            throw new \InvalidArgumentException('Apenas vendas com status "aberta" podem ser finalizadas.');
        }

        $totalPago = $this->vendaModel->getTotalPagoConfirmado($id);

        if ($totalPago <= 0) {
            throw new \InvalidArgumentException(
                'Não há pagamentos confirmados para esta venda. Cadastre ao menos um pagamento antes de finalizar.'
            );
        }

        return $this->vendaModel->alterarStatus($id, 'finalizada');
    }
}