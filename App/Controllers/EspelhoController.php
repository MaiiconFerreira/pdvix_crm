<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * EspelhoController
 *
 * Gera o espelho completo de uma sessão de caixa para impressão.
 *
 * Rotas:
 *   GET /api/espelho-pdv?caixa_sessao_id=X  → espelho()  retorna JSON completo
 *   GET /espelho-caixa?id=X                  → view()     renderiza HTML para impressão
 */
class EspelhoController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // GET /api/espelho-pdv?caixa_sessao_id=X
    // =========================================================================

    public function espelho(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $id = (int) ($_GET['caixa_sessao_id'] ?? 0);
        if ($id <= 0) $this->responseJson('error', [], 'caixa_sessao_id é obrigatório.', 400);

        // Caixa
        $stmtC = $this->pdo->prepare("
            SELECT cs.*, u.nome AS operador_nome
            FROM caixa_sessoes cs
            LEFT JOIN usuarios u ON u.id = cs.usuario_id
            WHERE cs.id = ? LIMIT 1
        ");
        $stmtC->execute([$id]);
        $caixa = $stmtC->fetch(\PDO::FETCH_ASSOC);
        if (!$caixa) $this->responseJson('error', [], 'Caixa não encontrado.', 404);

        // Vendas
        $stmtV = $this->pdo->prepare("
            SELECT v.id, v.numero_venda, v.data_venda, v.total, v.status, v.cliente_nome, v.numero_pdv
            FROM vendas v
            WHERE v.caixa_sessao_id = ?
            ORDER BY v.data_venda ASC
        ");
        $stmtV->execute([$id]);
        $vendas = $stmtV->fetchAll(\PDO::FETCH_ASSOC);

        // Sangrias
        $stmtS = $this->pdo->prepare("
            SELECT cs2.*, u2.nome AS operador_nome
            FROM caixa_sangrias cs2
            LEFT JOIN usuarios u2 ON u2.id = cs2.usuario_id
            WHERE cs2.caixa_sessao_id = ?
            ORDER BY cs2.data_hora ASC
        ");
        $stmtS->execute([$id]);
        $sangrias = $stmtS->fetchAll(\PDO::FETCH_ASSOC);

        // Cancelamentos da sessão
        $stmtCan = $this->pdo->prepare("
            SELECT c.*, v2.numero_venda
            FROM cancelamentos c
            LEFT JOIN vendas v2 ON v2.id = c.venda_id
            WHERE v2.caixa_sessao_id = ?
            ORDER BY c.cancelado_em ASC
        ");
        $stmtCan->execute([$id]);
        $cancelamentos = $stmtCan->fetchAll(\PDO::FETCH_ASSOC);

        // Resumo por forma de pagamento
        $stmtR = $this->pdo->prepare("
            SELECT pv.tipo_pagamento, SUM(pv.valor) AS total
            FROM pagamentos_venda pv
            JOIN vendas v3 ON v3.id = pv.venda_id
            WHERE v3.caixa_sessao_id = ? AND pv.status = 'confirmado'
            GROUP BY pv.tipo_pagamento
        ");
        $stmtR->execute([$id]);
        $resumo = $stmtR->fetchAll(\PDO::FETCH_ASSOC);

        $this->responseJson('success', [
            'caixa'         => $caixa,
            'vendas'        => $vendas,
            'sangrias'      => $sangrias,
            'cancelamentos' => $cancelamentos,
            'resumo_pagamentos' => $resumo,
        ]);
    }

    // =========================================================================
    // GET /espelho-caixa?id=X  (view HTML para impressão)
    // =========================================================================

    public function imprimirEspelho(): void
    {
        $this->requireLoginRedirect();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $this->view('espelho_caixa', ['caixa_sessao_id' => $id]);
    }
}
