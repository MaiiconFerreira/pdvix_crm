<?php
namespace App\Models;

use App\Core\Database;

class PagamentoModel extends Database
{
    private \PDO $pdo;

    private array $colunasValidas_update = [
        'venda_id', 'tipo_pagamento', 'valor',
        'referencia_externa', 'descricao', 'status',
    ];

    private array $colunasOrdenacao = [
        0 => 'v.numero_venda',
        1 => 'pv.tipo_pagamento',
        2 => 'pv.valor',
        3 => 'pv.referencia_externa',
        4 => 'pv.descricao',
        5 => 'pv.status',
        6 => 'pv.created_at',
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
            "SELECT id, venda_id, tipo_pagamento, valor, status FROM pagamentos_venda WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listPagamentos(array $request = []): array
    {
        $whereParts = [];
        $params     = [];

        // Busca global
        if (!empty($request['search']['value'])) {
            $search         = '%' . $request['search']['value'] . '%';
            $whereParts[]   = "(v.numero_venda LIKE :search OR pv.referencia_externa LIKE :search OR pv.descricao LIKE :search)";
            $params[':search'] = $search;
        }

        // Filtro: tipo_pagamento
        $tiposValidos = ['pix','convenio','pos_debito','pos_credito','pos_pix','dinheiro','outros'];
        if (!empty($request['tipo_pagamento']) && in_array($request['tipo_pagamento'], $tiposValidos, true)) {
            $whereParts[]                = "pv.tipo_pagamento = :tipo_pagamento";
            $params[':tipo_pagamento']   = $request['tipo_pagamento'];
        }

        // Filtro: status
        $statusValidos = ['pendente', 'confirmado', 'cancelado'];
        if (!empty($request['status']) && in_array($request['status'], $statusValidos, true)) {
            $whereParts[]      = "pv.status = :status";
            $params[':status'] = $request['status'];
        }

        // Filtro: datas
        if (!empty($request['data_inicio'])) {
            $whereParts[]             = "DATE(pv.created_at) >= :data_inicio";
            $params[':data_inicio']   = $request['data_inicio'];
        }
        if (!empty($request['data_fim'])) {
            $whereParts[]          = "DATE(pv.created_at) <= :data_fim";
            $params[':data_fim']   = $request['data_fim'];
        }

        $baseSql = "FROM pagamentos_venda pv
                    LEFT JOIN vendas v ON v.id = pv.venda_id";

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 6);
        $orderCol      = $this->colunasOrdenacao[$orderColIndex] ?? 'pv.created_at';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT
                pv.id, v.numero_venda, v.id AS venda_id,
                pv.tipo_pagamento, pv.valor,
                pv.referencia_externa, pv.descricao,
                pv.status, pv.created_at
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

    // -------------------------------------------------------------------------
    // ESCRITA
    // -------------------------------------------------------------------------

    public function createPagamento(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pagamentos_venda
                    (venda_id, tipo_pagamento, valor, referencia_externa, descricao, status, created_at)
                VALUES
                    (:venda_id, :tipo_pagamento, :valor, :referencia_externa, :descricao, :status, NOW())
            ");
            return $stmt->execute([
                ':venda_id'           => (int)   $data['venda_id'],
                ':tipo_pagamento'     =>          $data['tipo_pagamento'],
                ':valor'              => (float)  $data['valor'],
                ':referencia_externa' =>          $data['referencia_externa'] ?? null,
                ':descricao'          =>          $data['descricao']          ?? null,
                ':status'             =>          $data['status']             ?? 'pendente',
            ]);
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['input' => $data]);
            return false;
        }
    }

    public function updatePagamento(int $id, array $data): bool
    {
        $updateData = array_intersect_key($data, array_flip($this->colunasValidas_update));
        if (empty($updateData)) return false;

        $setParts = [];
        foreach ($updateData as $col => $val) {
            $setParts[] = "`{$col}` = :{$col}";
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE pagamentos_venda SET " . implode(', ', $setParts) . " WHERE id = :id"
            );
            foreach ($updateData as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['id' => $id, 'input' => $data]);
            return false;
        }
    }

    public function alterarStatus(int $id, string $status): bool
    {
        $validos = ['pendente', 'confirmado', 'cancelado'];
        if (!in_array($status, $validos, true)) return false;

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE pagamentos_venda SET status = :status WHERE id = :id"
            );
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['id' => $id, 'status' => $status]);
            return false;
        }
    }

    public function deletePagamento(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM pagamentos_venda WHERE id = :id AND status = 'pendente'"
            );
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['id' => $id]);
            return false;
        }
    }
}