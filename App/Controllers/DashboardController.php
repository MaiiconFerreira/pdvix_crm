<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * DashboardController
 *
 * Dados agregados para o painel de vendas em tempo real.
 *
 * Rotas:
 *   GET /api/dashboard/resumo           → resumo()
 *   GET /api/dashboard/vendas-hora      → vendasPorHora()
 *   GET /api/dashboard/top-produtos     → topProdutos()
 *   GET /api/dashboard/formas-pagamento → formasPagamento()
 *   GET /api/dashboard/pdvs-status      → pdvsStatus()
 *
 * Filtros comuns: ?loja_id=X  ?data_inicio=YYYY-MM-DD  ?data_fim=YYYY-MM-DD
 */
class DashboardController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // GET /api/dashboard/resumo
    // =========================================================================

    public function resumo(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        [$where, $params] = $this->_buildFiltro();

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*)                                               AS total_vendas,
                COALESCE(SUM(total), 0)                                AS faturamento,
                COALESCE(AVG(total), 0)                                AS ticket_medio,
                SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) AS total_canceladas,
                SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) AS total_finalizadas
            FROM vendas v
            {$where}
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetch(\PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // GET /api/dashboard/vendas-hora
    // =========================================================================

    public function vendasPorHora(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        [$where, $params] = $this->_buildFiltro();

        $stmt = $this->pdo->prepare("
            SELECT
                HOUR(v.data_venda)         AS hora,
                COUNT(*)                   AS total_vendas,
                COALESCE(SUM(v.total), 0)  AS faturamento
            FROM vendas v
            {$where}
            GROUP BY HOUR(v.data_venda)
            ORDER BY hora ASC
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // GET /api/dashboard/top-produtos
    // =========================================================================

    public function topProdutos(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        [$where, $params] = $this->_buildFiltro();

        $stmt = $this->pdo->prepare("
            SELECT
                p.nome                        AS produto_nome,
                SUM(vi.quantidade)            AS qtd_vendida,
                SUM(vi.subtotal)              AS faturamento
            FROM venda_itens vi
            JOIN vendas v ON v.id = vi.venda_id
            JOIN produtos p ON p.id = vi.produto_id
            {$where} AND v.status = 'finalizada'
            GROUP BY vi.produto_id
            ORDER BY qtd_vendida DESC
            LIMIT 10
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // GET /api/dashboard/formas-pagamento
    // =========================================================================

    public function formasPagamento(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        [$where, $params] = $this->_buildFiltro(true);  // true = usa pagamentos_venda

        $stmt = $this->pdo->prepare("
            SELECT
                pv.tipo_pagamento,
                COUNT(*)                  AS total_transacoes,
                COALESCE(SUM(pv.valor), 0) AS valor_total
            FROM pagamentos_venda pv
            JOIN vendas v ON v.id = pv.venda_id
            {$where} AND pv.status = 'confirmado'
            GROUP BY pv.tipo_pagamento
            ORDER BY valor_total DESC
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // GET /api/dashboard/pdvs-status
    // =========================================================================

    public function pdvsStatus(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        $lojaId = $this->getLojaIdSessao();
        $where  = '';
        $params = [];

        if ($lojaId !== null) {
            $where              = 'WHERE p.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.numero_pdv, p.online, p.ultimo_ping, p.versao_app, l.nome AS loja_nome
            FROM pdvs p
            LEFT JOIN lojas l ON l.id = p.loja_id
            {$where}
            ORDER BY l.nome, p.numero_pdv
        ");
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    /**
     * Constrói cláusula WHERE com filtros de loja e datas.
     * @param bool $usaPagamentos true = prefixo v. para tabela vendas nos JOINs
     */
    private function _buildFiltro(bool $usaPagamentos = false): array
    {
        $whereParts = [];
        $params     = [];

        $lojaId = $this->getLojaIdSessao();
        if ($lojaId !== null) {
            $whereParts[]       = 'v.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        if (!empty($_GET['data_inicio'])) {
            $whereParts[]           = 'DATE(v.data_venda) >= :data_inicio';
            $params[':data_inicio'] = $_GET['data_inicio'];
        }

        if (!empty($_GET['data_fim'])) {
            $whereParts[]        = 'DATE(v.data_venda) <= :data_fim';
            $params[':data_fim'] = $_GET['data_fim'];
        }

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        return [$where, $params];
    }
}
