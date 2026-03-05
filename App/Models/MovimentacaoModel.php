<?php
namespace App\Models;

use App\Core\Database;

class MovimentacaoModel extends Database
{
    private \PDO $pdo;

    private array $colunasOrdenacao = [
        0 => 'p.nome',
        1 => 'em.tipo_movimento',
        2 => 'em.quantidade',
        3 => 'em.unidade_origem',
        4 => 'em.codigo_barras_usado',
        5 => 'em.motivo',
        6 => 'em.origem',
        7 => 'u.nome',
        8 => 'em.data_movimento',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->pdo = Database::getConnection();
    }

    /**
     * Listagem de movimentações — DataTables server-side.
     * JOIN produtos (nome) e usuarios (operador).
     * Suporta filtros: produto_id, tipo_movimento, unidade_origem (UN/CX/KG/G),
     *                  origem, data_inicio, data_fim, qty_op+qty_val, search global.
     */
    public function listMovimentacoes(array $request = []): array
    {
        $whereParts = [];
        $params     = [];

        // Busca global
        if (!empty($request['search']['value'])) {
            $search            = '%' . $request['search']['value'] . '%';
            $whereParts[]      = "(p.nome LIKE :search OR em.codigo_barras_usado LIKE :search OR em.motivo LIKE :search)";
            $params[':search'] = $search;
        }

        // Filtro: produto
        if (!empty($request['produto_id']) && is_numeric($request['produto_id'])) {
            $whereParts[]          = "em.produto_id = :produto_id";
            $params[':produto_id'] = (int) $request['produto_id'];
        }

        // Filtro: tipo_movimento
        $tiposValidos = ['ENTRADA', 'SAIDA', 'AJUSTE'];
        if (!empty($request['tipo_movimento']) && in_array($request['tipo_movimento'], $tiposValidos, true)) {
            $whereParts[]              = "em.tipo_movimento = :tipo_movimento";
            $params[':tipo_movimento'] = $request['tipo_movimento'];
        }

        // Filtro: unidade_origem — suporta UN, CX, KG, G
        $unidadesValidas = ['UN', 'CX', 'KG', 'G'];
        if (!empty($request['unidade_origem']) && in_array($request['unidade_origem'], $unidadesValidas, true)) {
            $whereParts[]              = "em.unidade_origem = :unidade_origem";
            $params[':unidade_origem'] = $request['unidade_origem'];
        }

        // Filtro: origem
        $origensValidas = ['VENDA', 'COMPRA', 'AJUSTE', 'DEVOLUCAO', 'ESTORNO'];
        if (!empty($request['origem']) && in_array($request['origem'], $origensValidas, true)) {
            $whereParts[]      = "em.origem = :origem";
            $params[':origem'] = $request['origem'];
        }

        // Filtro: data_inicio
        if (!empty($request['data_inicio'])) {
            $whereParts[]           = "DATE(em.data_movimento) >= :data_inicio";
            $params[':data_inicio'] = $request['data_inicio'];
        }

        // Filtro: data_fim
        if (!empty($request['data_fim'])) {
            $whereParts[]        = "DATE(em.data_movimento) <= :data_fim";
            $params[':data_fim'] = $request['data_fim'];
        }

        // Filtro numérico quantidade (compara com o valor original registrado)
        if (!empty($request['qty_op']) && isset($request['qty_val']) && is_numeric($request['qty_val'])) {
            $opMap = ['gt' => '>', 'lt' => '<', 'eq' => '='];
            $op    = $opMap[$request['qty_op']] ?? null;
            if ($op) {
                $whereParts[]       = "em.quantidade {$op} :qty_val";
                $params[':qty_val'] = (float) $request['qty_val'];
            }
        }

        $baseSql = "FROM estoque_movimentacoes em
                    LEFT JOIN produtos p  ON p.id  = em.produto_id
                    LEFT JOIN usuarios u  ON u.id  = em.usuario_id";

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 8);
        $orderCol      = $this->colunasOrdenacao[$orderColIndex] ?? 'em.data_movimento';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT
                em.id,
                p.nome          AS produto_nome,
                p.unidade_base,
                em.tipo_movimento,
                em.quantidade,
                em.unidade_origem,
                em.codigo_barras_usado,
                em.motivo,
                em.origem,
                u.nome          AS operador,
                em.data_movimento
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
}