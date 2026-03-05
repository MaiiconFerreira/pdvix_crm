<?php
namespace App\Models;

use App\Core\Database;

class VendaModel extends Database
{
    private \PDO $pdo;

    private array $colunasOrdenacao = [
        0 => 'v.numero_venda',
        1 => 'v.data_venda',
        2 => 'u.nome',
        3 => 'v.subtotal',
        4 => 'v.desconto',
        5 => 'v.acrescimo',
        6 => 'v.total',
        7 => 'v.status',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->pdo = Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // LEITURA
    // -------------------------------------------------------------------------

    public function findById(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, numero_venda, status, total FROM vendas WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listVendas(array $request = []): array
    {
        $whereParts = [];
        $params     = [];

        // Busca global
        if (!empty($request['search']['value'])) {
            $search         = '%' . $request['search']['value'] . '%';
            $whereParts[]   = "(v.numero_venda LIKE :search OR u.nome LIKE :search)";
            $params[':search'] = $search;
        }

        // Filtro: status
        $statusValidos = ['aberta', 'finalizada', 'cancelada'];
        if (!empty($request['status']) && in_array($request['status'], $statusValidos, true)) {
            $whereParts[]      = "v.status = :status";
            $params[':status'] = $request['status'];
        }

        // Filtro: usuario
        if (!empty($request['usuario_id']) && is_numeric($request['usuario_id'])) {
            $whereParts[]           = "v.usuario_id = :usuario_id";
            $params[':usuario_id']  = (int) $request['usuario_id'];
        }

        // Filtro: datas
        if (!empty($request['data_inicio'])) {
            $whereParts[]             = "DATE(v.data_venda) >= :data_inicio";
            $params[':data_inicio']   = $request['data_inicio'];
        }
        if (!empty($request['data_fim'])) {
            $whereParts[]          = "DATE(v.data_venda) <= :data_fim";
            $params[':data_fim']   = $request['data_fim'];
        }

        // Filtro numérico total
        if (!empty($request['total_op']) && isset($request['total_val']) && is_numeric($request['total_val'])) {
            $opMap = ['gt' => '>', 'lt' => '<', 'eq' => '='];
            $op    = $opMap[$request['total_op']] ?? null;
            if ($op) {
                $whereParts[]          = "v.total {$op} :total_val";
                $params[':total_val']  = (float) $request['total_val'];
            }
        }

        $baseSql = "FROM vendas v
                    LEFT JOIN usuarios u ON u.id = v.usuario_id";

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 1);
        $orderCol      = $this->colunasOrdenacao[$orderColIndex] ?? 'v.data_venda';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT
                v.id, v.numero_venda, v.data_venda,
                u.nome AS usuario_nome,
                v.subtotal, v.desconto, v.acrescimo, v.total,
                v.status, v.observacao, v.created_at
            {$baseSql}
            {$where}
            ORDER BY {$orderCol} {$orderDir}
            LIMIT :start, :length
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':start',  $start,  \PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'draw'            => isset($request['draw']) ? (int) $request['draw'] : 1,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    public function getItens(int $vendaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT vi.id, vi.produto_id, p.nome AS produto_nome,
                   vi.quantidade, vi.valor_unitario, vi.subtotal
            FROM venda_itens vi
            LEFT JOIN produtos p ON p.id = vi.produto_id
            WHERE vi.venda_id = :venda_id
        ");
        $stmt->bindValue(':venda_id', $vendaId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPagamentos(int $vendaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tipo_pagamento, valor, referencia_externa, descricao, status, created_at
            FROM pagamentos_venda
            WHERE venda_id = :venda_id
        ");
        $stmt->bindValue(':venda_id', $vendaId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalPagoConfirmado(int $vendaId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(valor), 0)
            FROM pagamentos_venda
            WHERE venda_id = ? AND status = 'confirmado'
        ");
        $stmt->execute([$vendaId]);
        return (float) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // ESCRITA
    // -------------------------------------------------------------------------

    public function criarVenda(array $data): int|false
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO vendas
                    (numero_venda, data_venda, cliente_id, usuario_id,
                     subtotal, desconto, acrescimo, total, status, observacao, created_at, updated_at)
                VALUES
                    (:numero_venda, NOW(), :cliente_id, :usuario_id,
                     :subtotal, :desconto, :acrescimo, :total, :status, :observacao, NOW(), NOW())
            ");
            $stmt->execute([
                ':numero_venda' => $data['numero_venda'],
                ':cliente_id'   => $data['cliente_id']  ?? null,
                ':usuario_id'   => (int) $data['usuario_id'],
                ':subtotal'     => $data['subtotal'],
                ':desconto'     => $data['desconto'],
                ':acrescimo'    => $data['acrescimo'],
                ':total'        => $data['total'],
                ':status'       => $data['status'],
                ':observacao'   => $data['observacao']  ?? null,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['input' => $data]);
            return false;
        }
    }

    public function criarItem(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO venda_itens (venda_id, produto_id, quantidade, valor_unitario, subtotal, created_at)
                VALUES (:venda_id, :produto_id, :quantidade, :valor_unitario, :subtotal, NOW())
            ");
            return $stmt->execute([
                ':venda_id'      => (int)   $data['venda_id'],
                ':produto_id'    => (int)   $data['produto_id'],
                ':quantidade'    => (int)   $data['quantidade'],
                ':valor_unitario'=> (float) $data['valor_unitario'],
                ':subtotal'      => (float) $data['subtotal'],
            ]);
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['input' => $data]);
            return false;
        }
    }

    public function alterarStatus(int $id, string $status): bool
    {
        $statusValidos = ['aberta', 'finalizada', 'cancelada'];
        if (!in_array($status, $statusValidos, true)) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vendas SET status = :status, updated_at = NOW() WHERE id = :id"
            );
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['id' => $id, 'status' => $status]);
            return false;
        }
    }

    public function deleteVenda(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM vendas WHERE id = :id AND status = 'aberta'");
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['id' => $id]);
            return false;
        }
    }
}