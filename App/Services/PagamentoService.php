<?php
namespace App\Services;

use App\Models\PagamentoModel;
use App\Models\VendaModel;

class PagamentoService
{
    private PagamentoModel $pagamentoModel;
    private VendaModel     $vendaModel;

    public function __construct()
    {
        $this->pagamentoModel = new PagamentoModel();
        $this->vendaModel     = new VendaModel();
    }

    // =========================================================================
    // VALIDAR CRIAÇÃO / ATUALIZAÇÃO
    // =========================================================================

    /**
     * Valida os dados antes de persistir um pagamento.
     * Lança \InvalidArgumentException com mensagem amigável em caso de erro.
     *
     * @param array    $data   Dados vindos do request
     * @param int|null $id     ID do pagamento em edição (null = criação)
     */
    public function validar(array $data, ?int $id = null): void
    {
        // ── Campos obrigatórios ───────────────────────────────────────────────
        $obrigatorios = ['venda_id', 'tipo_pagamento', 'valor'];
        foreach ($obrigatorios as $campo) {
            if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$campo}.");
            }
        }

        // ── Venda deve existir e estar 'aberta' ───────────────────────────────
        $vendaId = (int) $data['venda_id'];
        $venda   = $this->vendaModel->findById($vendaId);

        if (!$venda) {
            throw new \InvalidArgumentException('Venda não encontrada.');
        }

        // Permite editar pagamento de venda que não seja cancelada
        if ($venda->status === 'cancelada') {
            throw new \InvalidArgumentException('Não é possível adicionar pagamento a uma venda cancelada.');
        }

        // ── Valor > 0 ─────────────────────────────────────────────────────────
        if (!is_numeric($data['valor']) || (float) $data['valor'] <= 0) {
            throw new \InvalidArgumentException('Valor do pagamento deve ser maior que zero.');
        }

        // ── Tipo de pagamento válido ──────────────────────────────────────────
        $tiposValidos = [
            'pix', 'convenio', 'pos_debito', 'pos_credito',
            'pos_pix', 'dinheiro', 'outros',
        ];
        if (!in_array($data['tipo_pagamento'], $tiposValidos, true)) {
            throw new \InvalidArgumentException('Tipo de pagamento inválido.');
        }
    }

    // =========================================================================
    // PODE EXCLUIR
    // =========================================================================

    /**
     * Verifica se o pagamento pode ser excluído (somente status 'pendente').
     */
    public function podeExcluir(int $id): bool
    {
        $pagamento = $this->pagamentoModel->findById($id);
        return $pagamento && $pagamento->status === 'pendente';
    }
}