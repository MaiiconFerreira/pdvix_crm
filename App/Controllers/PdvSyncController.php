<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\EstoqueModel;
use App\Models\PagamentoModel;

/**
 * PdvSyncController
 *
 * Endpoint exclusivo para o PDV Electron sincronizar vendas offline para o servidor.
 * Autenticação: token estático (mesma lógica do CargaInicialController).
 * Não requer sessão PHP — ideal para chamadas do Electron sem cookie.
 *
 * Rota mapeada em routes.php:
 *   POST /api/pdv/sync-venda?token=XXX → sync()
 *
 * Payload esperado (JSON):
 * {
 *   "numero_venda":  "PDV01-1234567890",
 *   "usuario_id":    1,
 *   "cliente_id":    null,
 *   "cliente_cpf":   "00000000000",
 *   "cliente_nome":  "CONSUMIDOR FINAL",
 *   "subtotal":      10.00,
 *   "desconto":      0.00,
 *   "acrescimo":     0.00,
 *   "total":         10.00,
 *   "data_venda":    "2026-03-05 00:19:42",
 *   "observacao":    null,
 *   "itens": [
 *     { "produto_id": 1, "produto_nome": "Produto X", "quantidade": 2,
 *       "valor_unitario": 5.00, "desconto_item": 0, "subtotal": 10.00,
 *       "codigo_barras_usado": "7891234567890", "unidade_origem": "UN" }
 *   ],
 *   "pagamentos": [
 *     { "tipo_pagamento": "dinheiro", "valor": 10.00, "referencia_externa": null }
 *   ]
 * }
 *
 * Resposta de sucesso:
 * { "status": "success", "data": { "id_servidor": 42 }, "message": "..." }
 */
class PdvSyncController extends Controller
{
    private \PDO           $pdo;
    private EstoqueModel   $estoqueModel;
    private PagamentoModel $pagamentoModel;

    /** Tipos de pagamento aceitos — mapeados de volta de nomes legados do PDV */
    private const TIPOS_VALIDOS = [
        'pix', 'convenio', 'pos_debito', 'pos_credito',
        'pos_pix', 'dinheiro', 'outros',
    ];

    /** Mapa de normalização: nomes antigos/alternativos → padrão do servidor */
    private const TIPO_MAP = [
        'Dinheiro'  => 'dinheiro',
        'Pix'       => 'pix',
        'Débito'    => 'pos_debito',
        'Debito'    => 'pos_debito',
        'Crédito'   => 'pos_credito',
        'Credito'   => 'pos_credito',
        'Convênio'  => 'convenio',
        'Convenio'  => 'convenio',
    ];

    public function __construct()
    {
        $this->pdo            = Database::getConnection();
        $this->estoqueModel   = new EstoqueModel();
        $this->pagamentoModel = new PagamentoModel();
    }

    // =========================================================================
    // POST /api/pdv/sync-venda
    // =========================================================================

    public function sync(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarToken();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // ── Validação de campos obrigatórios ───────────────────────────────────
        foreach (['numero_venda', 'usuario_id', 'total', 'itens', 'pagamentos'] as $campo) {
            if (empty($body[$campo]) && $body[$campo] !== 0) {
                $this->responseJson('error', [], "Campo obrigatório ausente: {$campo}.", 400);
            }
        }

        if (!is_array($body['itens']) || count($body['itens']) === 0) {
            $this->responseJson('error', [], 'A venda deve ter ao menos um item.', 400);
        }

        if (!is_array($body['pagamentos']) || count($body['pagamentos']) === 0) {
            $this->responseJson('error', [], 'A venda deve ter ao menos um pagamento.', 400);
        }

        // ── Idempotência: venda já sincronizada? ───────────────────────────────
        // Permite reenvio seguro sem duplicar registros no servidor.
        $stmt = $this->pdo->prepare(
            "SELECT id FROM vendas WHERE numero_venda = ? LIMIT 1"
        );
        $stmt->execute([$body['numero_venda']]);
        $existente = $stmt->fetch(\PDO::FETCH_OBJ);

        if ($existente) {
            $this->responseJson(
                'success',
                ['id_servidor' => $existente->id],
                'Venda já estava sincronizada — nenhuma ação necessária.'
            );
        }

        // ── Transação: venda + itens + estoque + pagamentos ────────────────────
        $this->pdo->beginTransaction();

        try {
            $usuarioId = (int) $body['usuario_id'];
            $subtotal  = (float) ($body['subtotal']  ?? 0);
            $desconto  = (float) ($body['desconto']  ?? 0);
            $acrescimo = (float) ($body['acrescimo'] ?? 0);
            $total     = (float)  $body['total'];

            // 1. Cria a venda com a data original do PDV
            $vendaId = $this->_inserirVenda([
                'numero_venda' => $body['numero_venda'],
                'data_venda'   => $body['data_venda'] ?? null,
                'cliente_id'   => !empty($body['cliente_id'])  ? (int) $body['cliente_id'] : null,
                'usuario_id'   => $usuarioId,
                'subtotal'     => $subtotal,
                'desconto'     => $desconto,
                'acrescimo'    => $acrescimo,
                'total'        => $total,
                'status'       => 'finalizada',
                'observacao'   => $body['observacao'] ?? null,
            ]);

            if (!$vendaId) {
                throw new \RuntimeException('Falha ao inserir venda no banco de dados do servidor.');
            }

            // 2. Itens + baixa de estoque
            $stmtItem = $this->pdo->prepare("
                INSERT INTO venda_itens
                    (venda_id, produto_id, quantidade, valor_unitario, subtotal, created_at)
                VALUES (:venda_id, :produto_id, :quantidade, :valor_unitario, :subtotal, NOW())
            ");

            foreach ($body['itens'] as $idx => $item) {
                $produtoId     = (int)   ($item['produto_id']     ?? 0);
                $quantidade    = (int)   ($item['quantidade']     ?? 0);
                $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
                $descontoItem  = (float) ($item['desconto_item']  ?? 0);
                $unidade       = $item['unidade_origem'] ?? 'UN';
                $codBarras     = $item['codigo_barras_usado'] ?? null;

                if ($produtoId <= 0 || $quantidade <= 0) {
                    throw new \InvalidArgumentException(
                        "Item #{$idx}: produto_id ou quantidade inválidos."
                    );
                }

                $subtotalItem = ($quantidade * $valorUnitario) - $descontoItem;

                $stmtItem->execute([
                    ':venda_id'       => $vendaId,
                    ':produto_id'     => $produtoId,
                    ':quantidade'     => $quantidade,
                    ':valor_unitario' => $valorUnitario,
                    ':subtotal'       => $subtotalItem,
                ]);

                // Registra movimentação de estoque (SAIDA / VENDA)
                $this->estoqueModel->registrarMovimentacao([
                    'produto_id'          => $produtoId,
                    'tipo_movimento'      => 'SAIDA',
                    'quantidade'          => $quantidade,
                    'unidade_origem'      => $unidade,
                    'codigo_barras_usado' => $codBarras,
                    'motivo'              => 'Sync PDV offline — ' . $body['numero_venda'],
                    'referencia_id'       => $vendaId,
                    'origem'              => 'VENDA',
                    'usuario_id'          => $usuarioId,
                ]);

                // Atualiza saldo de estoque
                $this->estoqueModel->atualizarQuantidade($produtoId, 'SAIDA', $quantidade);
            }

            // 3. Pagamentos
            foreach ($body['pagamentos'] as $pagto) {
                $tipo  = $this->_normalizarTipoPagamento($pagto['tipo_pagamento'] ?? 'dinheiro');
                $valor = (float) ($pagto['valor'] ?? 0);

                if ($valor <= 0) {
                    continue; // ignora pagamentos com valor zero
                }

                $this->pagamentoModel->createPagamento([
                    'venda_id'           => $vendaId,
                    'tipo_pagamento'     => $tipo,
                    'valor'              => $valor,
                    'referencia_externa' => $pagto['referencia_externa'] ?? null,
                    'descricao'          => 'Sync PDV',
                    'status'             => 'confirmado',
                ]);
            }

            $this->pdo->commit();

            $this->responseJson(
                'success',
                ['id_servidor' => $vendaId],
                'Venda sincronizada com sucesso.'
            );

        } catch (\InvalidArgumentException $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], $e->getMessage(), 422);

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], 'Erro interno ao sincronizar venda: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Valida o token da requisição contra o valor armazenado em config.
     * Encerra com 401 se inválido.
     */
    private function validarToken(): void
    {
        $token = trim($_GET['token'] ?? '');
        $stmt  = $this->pdo->prepare(
            "SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row || empty($row->valor) || !hash_equals($row->valor, $token)) {
            $this->responseJson('error', [], 'Token inválido ou ausente.', 401);
        }
    }

    /**
     * Insere a venda respeitando a data original do PDV.
     * Retorna o ID gerado ou false em caso de falha.
     */
    private function _inserirVenda(array $data): int|false
    {
        try {
            // Valida e sanitiza a data do PDV
            $dataVenda = null;
            if (!empty($data['data_venda'])) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['data_venda']);
                if ($dt) {
                    $dataVenda = $dt->format('Y-m-d H:i:s');
                }
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO vendas
                    (numero_venda, data_venda, cliente_id, usuario_id,
                     subtotal, desconto, acrescimo, total, status, observacao,
                     created_at, updated_at)
                VALUES
                    (:numero_venda, :data_venda, :cliente_id, :usuario_id,
                     :subtotal, :desconto, :acrescimo, :total, :status, :observacao,
                     NOW(), NOW())
            ");

            $stmt->execute([
                ':numero_venda' => $data['numero_venda'],
                ':data_venda'   => $dataVenda ?? date('Y-m-d H:i:s'),
                ':cliente_id'   => $data['cliente_id'],
                ':usuario_id'   => $data['usuario_id'],
                ':subtotal'     => $data['subtotal'],
                ':desconto'     => $data['desconto'],
                ':acrescimo'    => $data['acrescimo'],
                ':total'        => $data['total'],
                ':status'       => $data['status'],
                ':observacao'   => $data['observacao'],
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Normaliza o tipo de pagamento para o padrão aceito pelo servidor.
     * Ex.: "Dinheiro" → "dinheiro", "pos_debito" → "pos_debito", "Cartão" → "outros"
     */
    private function _normalizarTipoPagamento(string $tipo): string
    {
        // Já está no padrão correto?
        if (in_array($tipo, self::TIPOS_VALIDOS, true)) {
            return $tipo;
        }

        // Mapa de compatibilidade
        if (isset(self::TIPO_MAP[$tipo])) {
            return self::TIPO_MAP[$tipo];
        }

        // Tenta lowercase direto
        $lower = strtolower($tipo);
        if (in_array($lower, self::TIPOS_VALIDOS, true)) {
            return $lower;
        }

        return 'outros';
    }
}