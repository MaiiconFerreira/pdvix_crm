<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\EstoqueModel;
use App\Models\PagamentoModel;

/**
 * PdvSyncController
 *
 * Sincroniza vendas offline do PDV Electron para o servidor.
 * Autenticação: token estático via validarTokenPdv() — sem sessão PHP.
 *
 * Rota:
 *   POST /api/pdv/sync-venda?token=XXX  → sync()
 *
 * Payload esperado (JSON):
 * {
 *   "numero_venda":  "PDV01-1234567890",
 *   "loja_id":       1,                       // opcional, default 1
 *   "numero_pdv":    "01",
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
 *     {
 *       "produto_id":          1,
 *       "produto_nome":        "Produto X",
 *       "quantidade":          2,
 *       "valor_unitario":      5.00,
 *       "desconto_item":       0,
 *       "subtotal":            10.00,
 *       "codigo_barras_usado": "7891234567890",
 *       "unidade_origem":      "UN"
 *     }
 *   ],
 *   "pagamentos": [
 *     { "tipo_pagamento": "dinheiro", "valor": 10.00, "referencia_externa": null }
 *   ]
 * }
 *
 * Resposta sucesso: { "status": "success", "data": { "id_servidor": 42 }, "message": "..." }
 */
class PdvSyncController extends Controller
{
    private \PDO           $pdo;
    private EstoqueModel   $estoqueModel;
    private PagamentoModel $pagamentoModel;

    /** Tipos de pagamento aceitos pelo servidor */
    private const TIPOS_VALIDOS = [
        'pix', 'convenio', 'pos_debito', 'pos_credito',
        'pos_pix', 'dinheiro', 'outros',
    ];

    /** Mapa de compatibilidade: nomes legados → padrão */
    private const TIPO_MAP = [
        'Dinheiro' => 'dinheiro',
        'Pix'      => 'pix',
        'Débito'   => 'pos_debito',
        'Debito'   => 'pos_debito',
        'Crédito'  => 'pos_credito',
        'Credito'  => 'pos_credito',
        'Convênio' => 'convenio',
        'Convenio' => 'convenio',
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
        $this->validarTokenPdv();

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

        // ── Idempotência — venda já sincronizada? ─────────────────────────────
        $stmt = $this->pdo->prepare("SELECT id FROM vendas WHERE numero_venda = ? LIMIT 1");
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
            $lojaId    = !empty($body['loja_id']) ? (int) $body['loja_id'] : 1;
            $numeroPdv = trim($body['numero_pdv'] ?? '01');
            $usuarioId = (int) $body['usuario_id'];
            $subtotal  = (float) ($body['subtotal']  ?? 0);
            $desconto  = (float) ($body['desconto']  ?? 0);
            $acrescimo = (float) ($body['acrescimo'] ?? 0);
            $total     = (float)  $body['total'];

            // 1. Insere venda
            $vendaId = $this->_inserirVenda([
                'numero_venda' => $body['numero_venda'],
                'loja_id'      => $lojaId,
                'numero_pdv'   => $numeroPdv,
                'data_venda'   => $body['data_venda']  ?? null,
                'cliente_id'   => !empty($body['cliente_id']) ? (int) $body['cliente_id'] : null,
                'cliente_cpf'  => $body['cliente_cpf']  ?? null,
                'cliente_nome' => $body['cliente_nome'] ?? null,
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
                    (venda_id, produto_id, produto_nome, quantidade, valor_unitario,
                     desconto_item, subtotal, codigo_barras_usado, unidade_origem, created_at)
                VALUES
                    (:venda_id, :produto_id, :produto_nome, :quantidade, :valor_unitario,
                     :desconto_item, :subtotal, :codigo_barras_usado, :unidade_origem, NOW())
            ");

            foreach ($body['itens'] as $idx => $item) {
                $produtoId     = (int)   ($item['produto_id']     ?? 0);
                $quantidade    = (float) ($item['quantidade']     ?? 0);
                $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
                $descontoItem  = (float) ($item['desconto_item']  ?? 0);
                $unidade       = $item['unidade_origem']      ?? 'UN';
                $codBarras     = $item['codigo_barras_usado'] ?? null;
                $produtoNome   = $item['produto_nome']        ?? null;

                if ($produtoId <= 0 || $quantidade <= 0) {
                    throw new \InvalidArgumentException(
                        "Item #{$idx}: produto_id ou quantidade inválidos."
                    );
                }

                $subtotalItem = ($quantidade * $valorUnitario) - $descontoItem;

                $stmtItem->execute([
                    ':venda_id'            => $vendaId,
                    ':produto_id'          => $produtoId,
                    ':produto_nome'        => $produtoNome,
                    ':quantidade'          => $quantidade,
                    ':valor_unitario'      => $valorUnitario,
                    ':desconto_item'       => $descontoItem,
                    ':subtotal'            => $subtotalItem,
                    ':codigo_barras_usado' => $codBarras,
                    ':unidade_origem'      => $unidade,
                ]);

                // Registra movimentação e atualiza saldo de estoque
                $qtdBase = $this->_calcularQtdBase($produtoId, $quantidade, $unidade);

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

                $this->estoqueModel->atualizarQuantidade($produtoId, 'SAIDA', $qtdBase);
            }

            // 3. Pagamentos
            foreach ($body['pagamentos'] as $pagto) {
                $tipo  = $this->_normalizarTipoPagamento($pagto['tipo_pagamento'] ?? 'dinheiro');
                $valor = (float) ($pagto['valor'] ?? 0);

                if ($valor <= 0) continue;

                $this->pagamentoModel->createPagamento([
                    'venda_id'           => $vendaId,
                    'tipo_pagamento'     => $tipo,
                    'valor'              => $valor,
                    'referencia_externa' => $pagto['referencia_externa'] ?? null,
                    'descricao'          => 'Sync PDV — ' . $numeroPdv,
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
     * Insere a venda preservando a data original do PDV.
     * Colunas loja_id, numero_pdv, cliente_cpf, cliente_nome são inseridas
     * somente se existirem (compatibilidade pré/pós migration_v3).
     */
    private function _inserirVenda(array $data): int|false
    {
        try {
            $dt = null;
            if (!empty($data['data_venda'])) {
                $dtObj = \DateTime::createFromFormat('Y-m-d H:i:s', $data['data_venda']);
                if ($dtObj) $dt = $dtObj->format('Y-m-d H:i:s');
            }

            // Detecta colunas extras (pós-migration v3)
            $temLojaId      = $this->_colunaExiste('vendas', 'loja_id');
            $temNumeroPdv   = $this->_colunaExiste('vendas', 'numero_pdv');
            $temClienteCpf  = $this->_colunaExiste('vendas', 'cliente_cpf');
            $temClienteNome = $this->_colunaExiste('vendas', 'cliente_nome');

            $cols   = '(numero_venda, data_venda, cliente_id, usuario_id,
                        subtotal, desconto, acrescimo, total, status, observacao,
                        created_at, updated_at';
            $vals   = '(:numero_venda, :data_venda, :cliente_id, :usuario_id,
                        :subtotal, :desconto, :acrescimo, :total, :status, :observacao,
                        NOW(), NOW()';
            $params = [
                ':numero_venda' => $data['numero_venda'],
                ':data_venda'   => $dt ?? date('Y-m-d H:i:s'),
                ':cliente_id'   => $data['cliente_id'],
                ':usuario_id'   => $data['usuario_id'],
                ':subtotal'     => $data['subtotal'],
                ':desconto'     => $data['desconto'],
                ':acrescimo'    => $data['acrescimo'],
                ':total'        => $data['total'],
                ':status'       => $data['status'],
                ':observacao'   => $data['observacao'],
            ];

            if ($temLojaId) {
                $cols .= ', loja_id'; $vals .= ', :loja_id';
                $params[':loja_id'] = $data['loja_id'];
            }
            if ($temNumeroPdv) {
                $cols .= ', numero_pdv'; $vals .= ', :numero_pdv';
                $params[':numero_pdv'] = $data['numero_pdv'];
            }
            if ($temClienteCpf) {
                $cols .= ', cliente_cpf'; $vals .= ', :cliente_cpf';
                $params[':cliente_cpf'] = $data['cliente_cpf'];
            }
            if ($temClienteNome) {
                $cols .= ', cliente_nome'; $vals .= ', :cliente_nome';
                $params[':cliente_nome'] = $data['cliente_nome'];
            }

            $cols .= ')'; $vals .= ')';

            $stmt = $this->pdo->prepare("INSERT INTO vendas {$cols} VALUES {$vals}");
            $stmt->execute($params);
            return (int) $this->pdo->lastInsertId();

        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Converte quantidade para unidade base do produto (para atualizar saldo de estoque).
     */
    private function _calcularQtdBase(int $produtoId, float $quantidade, string $unidade): int
    {
        $info  = $this->estoqueModel->getInfoProduto($produtoId);
        $fator = $info['fator'];

        return match ($unidade) {
            'CX' => (int) round($quantidade * $fator),
            'KG' => (int) round($quantidade * 1000),
            'G'  => (int) round($quantidade),
            default => (int) round($quantidade),
        };
    }

    /**
     * Normaliza tipo de pagamento para o padrão do servidor.
     */
    private function _normalizarTipoPagamento(string $tipo): string
    {
        if (in_array($tipo, self::TIPOS_VALIDOS, true)) return $tipo;
        if (isset(self::TIPO_MAP[$tipo])) return self::TIPO_MAP[$tipo];

        $lower = strtolower($tipo);
        if (in_array($lower, self::TIPOS_VALIDOS, true)) return $lower;

        return 'outros';
    }

    /**
     * Verifica se uma coluna existe (compatibilidade pré/pós migration).
     */
    private function _colunaExiste(string $tabela, string $coluna): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$tabela, $coluna]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
