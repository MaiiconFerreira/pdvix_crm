<?php
namespace App\Models;

use App\Core\Database;

class EstoqueModel extends Database
{
    private \PDO $pdo;

    private array $colunasOrdenacao = [
        0 => 'p.nome',
        1 => 'quantidade_atual',
        2 => 'ultima_movimentacao',
        3 => 'data_atualizacao',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->pdo = Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // LEITURA
    // -------------------------------------------------------------------------

    /**
     * Listagem de estoque — DataTables server-side.
     *
     * Retorna uma linha por produto.
     * Inclui unidade_base para que o front-end exiba g/kg/UN corretamente.
     */
    public function listEstoque(array $request = []): array
    {
        $whereParts = [];
        $params     = [];

        // Busca global
        if (!empty($request['search']['value'])) {
            $search            = '%' . $request['search']['value'] . '%';
            $whereParts[]      = "p.nome LIKE :search";
            $params[':search'] = $search;
        }

        // Filtro: status produto
        if (isset($request['bloqueado']) && in_array((string) $request['bloqueado'], ['0', '1'], true)) {
            $whereParts[]         = "p.bloqueado = :bloqueado";
            $params[':bloqueado'] = (int) $request['bloqueado'];
        }

        // Filtro: tipo de embalagem via EXISTS (não cria linhas extras)
        // Suporta UN, CX, KG, G
        $tiposEmbalagem = ['UN', 'CX', 'KG', 'G'];
        if (!empty($request['tipo_embalagem']) && in_array($request['tipo_embalagem'], $tiposEmbalagem, true)) {
            $whereParts[]              = "EXISTS (
                SELECT 1 FROM produtos_codigos_barras pcb
                WHERE pcb.produto_id = p.id AND pcb.tipo_embalagem = :tipo_embalagem
            )";
            $params[':tipo_embalagem'] = $request['tipo_embalagem'];
        }

        // Filtro: unidade_base (UN / G)
        if (!empty($request['unidade_base']) && in_array($request['unidade_base'], ['UN', 'G'], true)) {
            $whereParts[]              = "p.unidade_base = :unidade_base";
            $params[':unidade_base']   = $request['unidade_base'];
        }

        $baseSql = "FROM produtos p";
        $where   = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        // Filtro numérico de quantidade — HAVING pois usa coluna calculada
        $havingPart       = '';
        $havingParamKey   = null;
        $havingParamValue = null;
        if (!empty($request['qty_op']) && isset($request['qty_val']) && is_numeric($request['qty_val'])) {
            $opMap = ['gt' => '>', 'lt' => '<', 'eq' => '='];
            $op    = $opMap[$request['qty_op']] ?? null;
            if ($op) {
                $havingPart       = "HAVING quantidade_atual {$op} :qty_val";
                $havingParamKey   = ':qty_val';
                $havingParamValue = (int) $request['qty_val'];
            }
        }

        // Totais (sem having)
        $stmtTotal    = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        // Total filtrado (com having requer subquery)
        $countParams = $params;
        if ($havingParamKey) {
            $countParams[$havingParamKey] = $havingParamValue;
        }

        $stmtFiltered = $this->pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT p.id,
                    COALESCE(
                        (SELECT e2.quantidade_atual FROM estoque e2
                         WHERE e2.produto_id = p.id ORDER BY e2.id DESC LIMIT 1), 0
                    ) AS quantidade_atual
                {$baseSql} {$where} {$havingPart}
            ) AS sub
        ");
        foreach ($countParams as $k => $v) {
            $stmtFiltered->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $stmtFiltered->execute();
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // Ordenação
        $orderColIndex = (int) ($request['order'][0]['column'] ?? 0);
        $orderCol      = $this->colunasOrdenacao[$orderColIndex] ?? 'p.nome';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        // Paginação
        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $allParams = $params;
        if ($havingParamKey) {
            $allParams[$havingParamKey] = $havingParamValue;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.id                AS produto_id,
                p.nome              AS produto_nome,
                p.fator_embalagem,
                p.unidade_base,
                p.bloqueado,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(pcb.tipo_embalagem, ': ', pcb.codigo_barras)
                        ORDER BY pcb.tipo_embalagem SEPARATOR ' | '
                    )
                    FROM produtos_codigos_barras pcb
                    WHERE pcb.produto_id = p.id
                ) AS codigos_barras,
                COALESCE(
                    (SELECT e2.quantidade_atual FROM estoque e2
                     WHERE e2.produto_id = p.id ORDER BY e2.id DESC LIMIT 1),
                    0
                ) AS quantidade_atual,
                (
                    SELECT e3.data_atualizacao FROM estoque e3
                    WHERE e3.produto_id = p.id ORDER BY e3.id DESC LIMIT 1
                ) AS data_atualizacao,
                (
                    SELECT em.data_movimento
                    FROM estoque_movimentacoes em
                    WHERE em.produto_id = p.id
                    ORDER BY em.data_movimento DESC LIMIT 1
                ) AS ultima_movimentacao
            {$baseSql}
            {$where}
            {$havingPart}
            ORDER BY {$orderCol} {$orderDir}
            LIMIT :start, :length
        ");

        foreach ($allParams as $k => $v) {
            $stmt->bindValue($k, $v, \PDO::PARAM_STR);
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
     * Retorna a quantidade atual do produto (unidade base: UN ou gramas).
     */
    public function getQuantidadeAtual(int $produtoId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(quantidade_atual, 0) FROM estoque
             WHERE produto_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$produtoId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna fator_embalagem e unidade_base do produto para conversão de unidades.
     *
     * @return array{ fator: int, unidade_base: string }
     */
    public function getInfoProduto(int $produtoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fator_embalagem, unidade_base FROM produtos WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$produtoId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['fator' => 1, 'unidade_base' => 'UN'];
        }

        return [
            'fator'        => max(1, (int) $row['fator_embalagem']),
            'unidade_base' => $row['unidade_base'] ?? 'UN',
        ];
    }

    /**
     * @deprecated Use getInfoProduto() para obter também unidade_base.
     */
    public function getFatorEmbalagem(int $produtoId): int
    {
        return $this->getInfoProduto($produtoId)['fator'];
    }

    // -------------------------------------------------------------------------
    // ESCRITA
    // -------------------------------------------------------------------------

    /**
     * Insere registro em estoque_movimentacoes.
     *
     * O campo `quantidade` armazena a qtd na unidade original (ex: 1.5 KG, 1 CX).
     * A coluna foi alterada para DECIMAL(10,3) na migration para suportar frações de KG.
     */
    public function registrarMovimentacao(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO estoque_movimentacoes
                    (produto_id, tipo_movimento, quantidade, unidade_origem,
                     codigo_barras_usado, motivo, referencia_id, origem, usuario_id, data_movimento)
                VALUES
                    (:produto_id, :tipo_movimento, :quantidade, :unidade_origem,
                     :codigo_barras_usado, :motivo, :referencia_id, :origem, :usuario_id, NOW())
            ");

            return $stmt->execute([
                ':produto_id'          => (int)   $data['produto_id'],
                ':tipo_movimento'      =>          $data['tipo_movimento'],
                ':quantidade'          => (float)  $data['quantidade'],   // DECIMAL — pode ser 1.5 (KG)
                ':unidade_origem'      =>          $data['unidade_origem'],
                ':codigo_barras_usado' =>          $data['codigo_barras_usado'] ?? null,
                ':motivo'              =>          $data['motivo']              ?? null,
                ':referencia_id'       =>          $data['referencia_id']       ?? null,
                ':origem'              =>          $data['origem'],
                ':usuario_id'          => (int)   $data['usuario_id'],
            ]);
        } catch (\PDOException $e) {
            $this->log->arquive($e, ['input' => $data]);
            return false;
        }
    }

    /**
     * Atualiza (ou cria) o saldo de estoque do produto.
     *
     * O parâmetro $quantidadeBase é SEMPRE na unidade base do produto:
     *   - Produtos UN/CX → inteiro em unidades
     *   - Produtos G     → inteiro em gramas (ex: 1.5 kg = 1500)
     *
     * A conversão é responsabilidade do EstoqueService, não deste método.
     *
     * @param int    $produtoId     ID do produto
     * @param string $tipo          ENTRADA | SAIDA | AJUSTE
     * @param int    $quantidadeBase Quantidade já convertida para unidade base
     */
    public function atualizarQuantidade(int $produtoId, string $tipo, int $quantidadeBase): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, quantidade_atual FROM estoque
                 WHERE produto_id = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$produtoId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $qtdAtual = (int) $row['quantidade_atual'];

                $novaQtd = match ($tipo) {
                    'ENTRADA' => $qtdAtual + $quantidadeBase,
                    'SAIDA'   => max(0, $qtdAtual - $quantidadeBase),
                    'AJUSTE'  => $quantidadeBase,
                    default   => $qtdAtual,
                };

                $stmtUp = $this->pdo->prepare(
                    "UPDATE estoque
                     SET quantidade_atual = :qtd, data_atualizacao = NOW()
                     WHERE produto_id = :pid"
                );
                return $stmtUp->execute([':qtd' => $novaQtd, ':pid' => $produtoId]);

            } else {
                $qtdInicial = in_array($tipo, ['ENTRADA', 'AJUSTE'], true) ? $quantidadeBase : 0;

                $stmtIns = $this->pdo->prepare(
                    "INSERT INTO estoque (produto_id, quantidade_atual, data_atualizacao)
                     VALUES (:pid, :qtd, NOW())"
                );
                return $stmtIns->execute([':pid' => $produtoId, ':qtd' => $qtdInicial]);
            }

        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'produto_id'       => $produtoId,
                'tipo'             => $tipo,
                'quantidade_base'  => $quantidadeBase,
            ]);
            return false;
        }
    }
}