<?php
namespace App\Models;

use App\Core\Database;

class ProdutoModel extends Database
{
    private \PDO $pdo;

    private array $colunasValidas = [
        'id', 'nome', 'codigo_interno_alternativo',
        'preco_venda', 'custo_item', 'fator_embalagem', 'unidade_base',
        'fornecedor_id', 'ultima_alteracao', 'ultima_alteracao_por', 'bloqueado',
    ];

    private array $colunasValidas_update = [
        'nome', 'codigo_interno_alternativo', 'preco_venda',
        'custo_item', 'fator_embalagem', 'unidade_base', 'fornecedor_id',
        'ultima_alteracao_por', 'bloqueado',
    ];

    private array $colunasOrdenacao = [
        0 => 'p.nome',
        1 => 'p.codigo_interno_alternativo',
        2 => 'p.preco_venda',
        3 => 'p.custo_item',
        4 => 'p.fator_embalagem',
        5 => 'f.razao_social',
        6 => 'p.bloqueado',
        7 => 'p.ultima_alteracao',
        // coluna 8 = quantidade_atual (subquery, não ordenável via alias no MariaDB 10.4)
    ];

    public function __construct()
    {
        parent::__construct();
        $this->pdo = Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // LEITURA
    // -------------------------------------------------------------------------

    public function findById(int $id, array $colunas = ['id', 'nome', 'bloqueado']): ?object
    {
        $colunasAllow = $this->colunasValidas;
        $colunasAllow[] = 'unidade_base'; // garante que está na lista
        $colunas    = array_values(array_intersect($colunas, $colunasAllow));
        if (empty($colunas)) {
            $colunas = ['id'];
        }

        $colunasStr = implode(', ', array_map(fn($c) => "`{$c}`", $colunas));
        $stmt = $this->pdo->prepare("SELECT {$colunasStr} FROM produtos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function existePorCodigo(int $codigo, ?int $excluirId = null): bool
    {
        $sql    = "SELECT id FROM produtos WHERE codigo_interno_alternativo = ?";
        $params = [$codigo];

        if ($excluirId !== null) {
            $sql    .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Listagem completa — DataTables server-side.
     * Inclui unidade_base para que o front-end exiba a unidade correta.
     */
    public function listProdutos(array $request = []): array
    {
        $whereParts = [];
        $params     = [];

        if (!empty($request['search']['value'])) {
            $search            = '%' . $request['search']['value'] . '%';
            $whereParts[]      = "(p.nome LIKE :search
                                   OR f.razao_social LIKE :search
                                   OR p.codigo_interno_alternativo LIKE :search)";
            $params[':search'] = $search;
        }

        if (isset($request['bloqueado']) && in_array((string) $request['bloqueado'], ['0', '1'], true)) {
            $whereParts[]         = "p.bloqueado = :bloqueado";
            $params[':bloqueado'] = (int) $request['bloqueado'];
        }

        if (!empty($request['fornecedor_id']) && is_numeric($request['fornecedor_id'])) {
            $whereParts[]             = "p.fornecedor_id = :fornecedor_id";
            $params[':fornecedor_id'] = (int) $request['fornecedor_id'];
        }

        // Filtro por unidade_base (UN / G)
        if (!empty($request['unidade_base']) && in_array($request['unidade_base'], ['UN', 'G'], true)) {
            $whereParts[]              = "p.unidade_base = :unidade_base";
            $params[':unidade_base']   = $request['unidade_base'];
        }

        $baseSql = "FROM produtos p
                    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id";

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 0);
        $orderCol      = $this->colunasOrdenacao[$orderColIndex] ?? 'p.nome';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.nome,
                p.codigo_interno_alternativo,
                p.preco_venda,
                p.custo_item,
                p.fator_embalagem,
                p.unidade_base,
                p.fornecedor_id,
                f.razao_social AS fornecedor_nome,
                p.bloqueado,
                p.ultima_alteracao,
                p.ultima_alteracao_por,
                COALESCE(
                    (SELECT e.quantidade_atual FROM estoque e
                     WHERE e.produto_id = p.id
                     ORDER BY e.id DESC LIMIT 1),
                    0
                ) AS quantidade_atual
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

    /**
     * Lista simples para selects (PDV, estoque).
     * Inclui unidade_base e fator_embalagem para o PDV calcular corretamente.
     */
    public function listProdutosSimples(): array
    {
        $stmt = $this->pdo->query("
            SELECT p.id, p.nome, p.preco_venda, p.fator_embalagem, p.unidade_base,
                   GROUP_CONCAT(pcb.codigo_barras ORDER BY pcb.tipo_embalagem SEPARATOR '|') AS codigos
            FROM produtos p
            LEFT JOIN produtos_codigos_barras pcb ON pcb.produto_id = p.id
            WHERE p.bloqueado = 0
            GROUP BY p.id
            ORDER BY p.nome
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCodigosBarras(int $produtoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, produto_id, codigo_barras, tipo_embalagem, preco_venda
            FROM produtos_codigos_barras
            WHERE produto_id = ?
            ORDER BY tipo_embalagem
        ");
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getHistorico(int $produtoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                em.id,
                em.tipo_movimento,
                em.quantidade,
                em.unidade_origem,
                em.codigo_barras_usado,
                em.motivo,
                em.origem,
                em.data_movimento,
                u.nome AS operador
            FROM estoque_movimentacoes em
            LEFT JOIN usuarios u ON u.id = em.usuario_id
            WHERE em.produto_id = :produto_id
            ORDER BY em.data_movimento DESC
            LIMIT 200
        ");
        $stmt->bindValue(':produto_id', $produtoId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // ESCRITA
    // -------------------------------------------------------------------------

    public function createProduto(array $data): int|false
    {
        $sql = "INSERT INTO produtos
                    (nome, codigo_interno_alternativo, preco_venda, custo_item,
                     fator_embalagem, unidade_base, fornecedor_id, ultima_alteracao_por)
                VALUES
                    (:nome, :codigo_interno_alternativo, :preco_venda, :custo_item,
                     :fator_embalagem, :unidade_base, :fornecedor_id, :ultima_alteracao_por)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'                       => $data['nome'],
                ':codigo_interno_alternativo' => $data['codigo_interno_alternativo'] ?? null,
                ':preco_venda'                => $data['preco_venda'],
                ':custo_item'                 => $data['custo_item'],
                ':fator_embalagem'            => (int) $data['fator_embalagem'],
                ':unidade_base'               => $data['unidade_base'] ?? 'UN',
                ':fornecedor_id'              => (int) $data['fornecedor_id'],
                ':ultima_alteracao_por'       => (int) $data['ultima_alteracao_por'],
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => $data,
            ]);
            return false;
        }
    }

    /**
     * Upsert código de barras.
     * tipo suportado: UN, CX, KG, G
     */
    public function upsertCodigoBarras(int $produtoId, string $tipo, string $codigo, float $preco): bool
    {
        $tiposValidos = ['UN', 'CX', 'KG', 'G'];
        if (!in_array($tipo, $tiposValidos, true)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO produtos_codigos_barras (produto_id, codigo_barras, tipo_embalagem, preco_venda)
                VALUES (:produto_id, :codigo_barras, :tipo_embalagem, :preco_venda)
                ON DUPLICATE KEY UPDATE
                    codigo_barras = VALUES(codigo_barras),
                    preco_venda   = VALUES(preco_venda)
            ");
            return $stmt->execute([
                ':produto_id'     => $produtoId,
                ':codigo_barras'  => $codigo,
                ':tipo_embalagem' => $tipo,
                ':preco_venda'    => $preco,
            ]);
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['produto_id' => $produtoId, 'tipo' => $tipo, 'codigo' => $codigo]);
            return false;
        }
    }

    public function updateProduto(int $id, array $data): bool
    {
        $updateData = array_intersect_key($data, array_flip($this->colunasValidas_update));

        if (empty($updateData)) {
            return false;
        }

        $setParts = [];
        foreach ($updateData as $col => $val) {
            $setParts[] = "`{$col}` = :{$col}";
        }
        $setParts[] = "`ultima_alteracao` = NOW()";

        $sql = "UPDATE produtos SET " . implode(', ', $setParts) . " WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($updateData as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => $data,
            ]);
            return false;
        }
    }

    public function toggleBloqueado(int $id): ?int
    {
        $produto = $this->findById($id, ['id', 'bloqueado']);
        if (!$produto) {
            return null;
        }

        $novoBloqueado = $produto->bloqueado ? 0 : 1;
        $ok            = $this->updateProduto($id, ['bloqueado' => $novoBloqueado]);

        return $ok ? $novoBloqueado : null;
    }

    public function deleteProduto(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => ['id' => $id],
            ]);
            return false;
        }
    }

    public function temMovimentacoes(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM estoque_movimentacoes WHERE produto_id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() !== false;
    }
}