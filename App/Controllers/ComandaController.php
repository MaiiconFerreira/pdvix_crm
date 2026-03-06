<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\RedisService;

/**
 * ComandaController
 *
 * Cria e gerencia pré-vendas (comandas) que são enviadas ao PDV via WebSocket.
 *
 * Rotas:
 *   GET    /api/comandas         → index()   listar
 *   POST   /api/comandas         → index()   criar
 *   PUT    /api/comandas         → index()   atualizar
 *   DELETE /api/comandas         → index()   cancelar
 *   POST   /api/comandas/enviar  → enviar()  envia comanda para um PDV
 *
 * Fluxo:
 *   1. Admin cria comanda via POST /api/comandas
 *   2. POST /api/comandas/enviar → enfileira no Redis pdv:cmd:{loja}:{pdv}
 *   3. WS Gateway entrega evento enviar_comanda ao PDV
 *   4. PDV cria venda local com os itens da comanda
 *   5. Operador finaliza venda normalmente; numero da comanda fica em vendas.observacao
 */
class ComandaController extends Controller
{
    private \PDO         $pdo;
    private RedisService $redis;

    public function __construct()
    {
        $this->pdo   = Database::getConnection();
        $this->redis = new RedisService();
    }

    // =========================================================================
    // DISPATCHER /api/comandas
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->_listar(),
            'POST'   => $this->_criar(),
            'PUT'    => $this->_atualizar(),
            'DELETE' => $this->_cancelar(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // POST /api/comandas/enviar
    // =========================================================================

    public function enviar(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $data      = $this->getRequestData(['comanda_id', 'loja_id', 'numero_pdv']);
        $comandaId = (int)  $data['comanda_id'];
        $lojaId    = (int)  $data['loja_id'];
        $numeroPdv = trim($data['numero_pdv']);

        $stmt = $this->pdo->prepare("
            SELECT c.*, GROUP_CONCAT(
                JSON_OBJECT(
                    'produto_id', ci.produto_id,
                    'produto_nome', p.nome,
                    'quantidade', ci.quantidade,
                    'valor_unitario', ci.valor_unitario,
                    'subtotal', ci.subtotal,
                    'observacao', ci.observacao
                )
            ) AS itens_json
            FROM comandas c
            LEFT JOIN comanda_itens ci ON ci.comanda_id = c.id
            LEFT JOIN produtos p ON p.id = ci.produto_id
            WHERE c.id = ? AND c.status = 'aberta'
            GROUP BY c.id
            LIMIT 1
        ");
        $stmt->execute([$comandaId]);
        $comanda = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$comanda) {
            $this->responseJson('error', [], 'Comanda não encontrada ou já enviada.', 404);
        }

        $itens = json_decode('[' . $comanda['itens_json'] . ']', true) ?? [];

        // Enfileira no Redis
        $chave   = "pdv:cmd:{$lojaId}:{$numeroPdv}";
        $payload = json_encode([
            'tipo'       => 'enviar_comanda',
            'comanda_id' => $comandaId,
            'numero'     => $comanda['numero'],
            'cliente_nome' => $comanda['cliente_nome'],
            'itens'      => $itens,
        ]);

        $this->redis->rPush($chave, $payload);

        // Atualiza status da comanda
        $this->pdo->prepare(
            "UPDATE comandas SET status = 'enviada', pdv_destino = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$numeroPdv, $comandaId]);

        $this->responseJson('success', [], 'Comanda enviada ao PDV com sucesso.');
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function _listar(): void
    {
        $lojaId = $this->getLojaIdSessao();
        $where  = '';
        $params = [];

        if ($lojaId !== null) {
            $where              = 'WHERE c.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*, u.nome AS operador_nome, cl.nome AS cliente_nome_cadastro
            FROM comandas c
            LEFT JOIN usuarios u ON u.id = c.usuario_id
            LEFT JOIN clientes cl ON cl.id = c.cliente_id
            {$where}
            ORDER BY c.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function _criar(): void
    {
        $data = $this->getRequestData(
            ['itens'],
            ['loja_id', 'cliente_id', 'cliente_nome']
        );

        $lojaId      = !empty($data['loja_id']) ? (int) $data['loja_id'] : ($this->getLojaIdSessao() ?? 1);
        $clienteId   = !empty($data['cliente_id'])   ? (int)  $data['cliente_id']   : null;
        $clienteNome = $data['cliente_nome'] ?? 'CONSUMIDOR FINAL';
        $itens       = is_array($data['itens']) ? $data['itens'] : json_decode($data['itens'], true);

        if (empty($itens)) {
            $this->responseJson('error', [], 'A comanda deve ter ao menos um item.', 400);
        }

        $this->pdo->beginTransaction();
        try {
            // Número sequencial da loja no dia
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM comandas WHERE loja_id = ? AND DATE(created_at) = CURDATE()"
            );
            $stmt->execute([$lojaId]);
            $numero = 'CMD-' . date('Ymd') . '-' . str_pad((int) $stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

            $this->pdo->prepare("
                INSERT INTO comandas (loja_id, numero, cliente_id, cliente_nome, usuario_id, status, created_at, updated_at)
                VALUES (:loja_id, :numero, :cliente_id, :cliente_nome, :usuario_id, 'aberta', NOW(), NOW())
            ")->execute([
                ':loja_id'      => $lojaId,
                ':numero'       => $numero,
                ':cliente_id'   => $clienteId,
                ':cliente_nome' => $clienteNome,
                ':usuario_id'   => (int) $_SESSION['logado']->id,
            ]);

            $comandaId = (int) $this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare("
                INSERT INTO comanda_itens (comanda_id, produto_id, quantidade, valor_unitario, subtotal, observacao)
                VALUES (:comanda_id, :produto_id, :quantidade, :valor_unitario, :subtotal, :observacao)
            ");

            foreach ($itens as $item) {
                $qtd   = (float) ($item['quantidade']     ?? 0);
                $vUnit = (float) ($item['valor_unitario'] ?? 0);
                $stmtItem->execute([
                    ':comanda_id'    => $comandaId,
                    ':produto_id'    => (int) ($item['produto_id'] ?? 0),
                    ':quantidade'    => $qtd,
                    ':valor_unitario'=> $vUnit,
                    ':subtotal'      => $qtd * $vUnit,
                    ':observacao'    => $item['observacao'] ?? null,
                ]);
            }

            $this->pdo->commit();
            $this->responseJson('success', ['id' => $comandaId, 'numero' => $numero], 'Comanda criada com sucesso!');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], 'Erro ao criar comanda: ' . $e->getMessage(), 500);
        }
    }

    private function _atualizar(): void
    {
        $data      = $this->getRequestData(['id']);
        $comandaId = (int) $data['id'];

        // Apenas comandas abertas podem ser editadas
        $stmt = $this->pdo->prepare("SELECT status FROM comandas WHERE id = ? LIMIT 1");
        $stmt->execute([$comandaId]);
        $comanda = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$comanda) $this->responseJson('error', [], 'Comanda não encontrada.', 404);
        if ($comanda->status !== 'aberta') $this->responseJson('error', [], 'Apenas comandas abertas podem ser editadas.', 409);

        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $itens = $body['itens'] ?? [];

        if (!empty($itens)) {
            $this->pdo->prepare("DELETE FROM comanda_itens WHERE comanda_id = ?")->execute([$comandaId]);

            $stmtItem = $this->pdo->prepare("
                INSERT INTO comanda_itens (comanda_id, produto_id, quantidade, valor_unitario, subtotal, observacao)
                VALUES (:comanda_id, :produto_id, :quantidade, :valor_unitario, :subtotal, :observacao)
            ");
            foreach ($itens as $item) {
                $qtd   = (float) ($item['quantidade']     ?? 0);
                $vUnit = (float) ($item['valor_unitario'] ?? 0);
                $stmtItem->execute([
                    ':comanda_id'    => $comandaId,
                    ':produto_id'    => (int) ($item['produto_id'] ?? 0),
                    ':quantidade'    => $qtd,
                    ':valor_unitario'=> $vUnit,
                    ':subtotal'      => $qtd * $vUnit,
                    ':observacao'    => $item['observacao'] ?? null,
                ]);
            }
        }

        $this->responseJson('success', [], 'Comanda atualizada com sucesso!');
    }

    private function _cancelar(): void
    {
        $data      = $this->getRequestData(['id']);
        $comandaId = (int) $data['id'];

        $this->pdo->prepare(
            "UPDATE comandas SET status = 'cancelada', updated_at = NOW() WHERE id = ? AND status = 'aberta'"
        )->execute([$comandaId]);

        $this->responseJson('success', [], 'Comanda cancelada com sucesso!');
    }
}
