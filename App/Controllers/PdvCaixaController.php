<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * PdvCaixaController
 *
 * Sincroniza sessões de caixa (abertura/fechamento e sangrias) do PDV para o servidor.
 * Autenticação: token estático via validarTokenPdv() — sem sessão PHP.
 *
 * Rotas:
 *   POST /api/pdv/sync-caixa?token=XXX  → sync()    sincroniza sessão completa
 *   GET  /api/pdv/caixas                → listar()  DataTables server-side (sessão PHP)
 *   GET  /api/pdv/caixas/detalhe?id=X   → detalhe() caixa + sangrias
 *
 * Payload POST /api/pdv/sync-caixa:
 * {
 *   "loja_id":        1,            // opcional, default 1
 *   "numero_pdv":     "01",
 *   "usuario_id":     1,
 *   "abertura_em":    "2026-03-05 08:00:00",
 *   "fechamento_em":  "2026-03-05 17:30:00",  // null se ainda aberto
 *   "valor_abertura": 50.00,
 *   "total_dinheiro": 320.00,
 *   "total_pix":      150.00,
 *   "total_debito":   80.00,
 *   "total_credito":  60.00,
 *   "total_convenio": 0.00,
 *   "total_outros":   0.00,
 *   "total_vendas":   12,
 *   "total_canceladas": 1,
 *   "total_sangrias": 200.00,
 *   "saldo_esperado": 170.00,
 *   "caixa_contado":  168.00,   // null se não informado
 *   "diferenca":      -2.00,    // null se não informado
 *   "status":         "fechado",
 *   "observacao":     null,
 *   "sangrias": [
 *     { "usuario_id": 1, "valor": 200.00, "motivo": "Retirada", "data_hora": "2026-03-05 12:00:00" }
 *   ]
 * }
 */
class PdvCaixaController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // POST /api/pdv/sync-caixa
    // =========================================================================

    public function sync(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        foreach (['usuario_id', 'abertura_em', 'status'] as $campo) {
            if (empty($body[$campo]) && $body[$campo] !== 0) {
                $this->responseJson('error', [], "Campo obrigatório ausente: {$campo}.", 400);
            }
        }

        $lojaId     = !empty($body['loja_id']) ? (int) $body['loja_id'] : 1;
        $numeroPdv  = trim($body['numero_pdv'] ?? '01');
        $usuarioId  = (int) $body['usuario_id'];
        $aberturaEm = $this->_sanitizarData($body['abertura_em'] ?? null);

        if (!$aberturaEm) {
            $this->responseJson('error', [], 'abertura_em inválida.', 400);
        }

        // ── Idempotência: mesma sessão já sincronizada? ───────────────────────
        $stmt = $this->pdo->prepare("
            SELECT id FROM caixa_sessoes
            WHERE numero_pdv = ? AND usuario_id = ? AND abertura_em = ?
            LIMIT 1
        ");
        $stmt->execute([$numeroPdv, $usuarioId, $aberturaEm]);
        $existente = $stmt->fetch(\PDO::FETCH_OBJ);

        $this->pdo->beginTransaction();

        try {
            if ($existente) {
                $idServidor = $existente->id;
                $this->_atualizarCaixa($idServidor, $body);
            } else {
                $idServidor = $this->_inserirCaixa($body, $lojaId, $numeroPdv, $usuarioId, $aberturaEm);
                if (!$idServidor) {
                    throw new \RuntimeException('Falha ao inserir caixa no servidor.');
                }
            }

            if (!empty($body['sangrias']) && is_array($body['sangrias'])) {
                $this->_sincronizarSangrias($idServidor, $body['sangrias']);
            }

            $this->pdo->commit();

            $this->responseJson(
                'success',
                ['id_servidor' => $idServidor],
                $existente ? 'Caixa atualizado no servidor.' : 'Caixa sincronizado com sucesso.'
            );

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], 'Erro ao sincronizar caixa: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /api/pdv/caixas  (DataTables server-side — painel admin)
    // =========================================================================

    public function listar(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $request    = $_GET;
        $whereParts = [];
        $params     = [];

        // Filtro por loja (multi-loja)
        $lojaId = $this->getLojaIdSessao();
        if ($lojaId !== null && $this->_colunaExiste('caixa_sessoes', 'loja_id')) {
            $whereParts[]       = 'cs.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        if (!empty($request['search']['value'])) {
            $search              = '%' . $request['search']['value'] . '%';
            $whereParts[]        = '(u.nome LIKE :search OR cs.numero_pdv LIKE :search)';
            $params[':search']   = $search;
        }

        if (!empty($request['status']) && in_array($request['status'], ['aberto', 'fechado'], true)) {
            $whereParts[]      = 'cs.status = :status';
            $params[':status'] = $request['status'];
        }

        if (!empty($request['numero_pdv'])) {
            $whereParts[]          = 'cs.numero_pdv = :numero_pdv';
            $params[':numero_pdv'] = $request['numero_pdv'];
        }

        if (!empty($request['data_inicio'])) {
            $whereParts[]           = 'DATE(cs.abertura_em) >= :data_inicio';
            $params[':data_inicio'] = $request['data_inicio'];
        }
        if (!empty($request['data_fim'])) {
            $whereParts[]        = 'DATE(cs.abertura_em) <= :data_fim';
            $params[':data_fim'] = $request['data_fim'];
        }

        $colunasOrdenacao = [
            0 => 'cs.id',          1 => 'cs.numero_pdv',
            2 => 'u.nome',         3 => 'cs.abertura_em',
            4 => 'cs.fechamento_em', 5 => 'cs.total_vendas',
            6 => 'cs.total_sangrias', 7 => 'cs.saldo_esperado',
            8 => 'cs.diferenca',   9 => 'cs.status',
        ];

        $baseSql = 'FROM caixa_sessoes cs LEFT JOIN usuarios u ON u.id = cs.usuario_id';
        $where   = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 3);
        $orderCol      = $colunasOrdenacao[$orderColIndex] ?? 'cs.abertura_em';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $start         = (int) ($request['start']  ?? 0);
        $length        = (int) ($request['length'] ?? 25);

        $stmt = $this->pdo->prepare("
            SELECT
                cs.id, cs.numero_pdv,
                u.nome AS operador_nome,
                cs.abertura_em, cs.fechamento_em, cs.valor_abertura,
                cs.total_dinheiro, cs.total_pix, cs.total_debito,
                cs.total_credito, cs.total_convenio, cs.total_outros,
                cs.total_vendas, cs.total_canceladas, cs.total_sangrias,
                cs.saldo_esperado, cs.caixa_contado, cs.diferenca,
                cs.status, cs.observacao, cs.sincronizado_em,
                (cs.total_dinheiro + cs.total_pix + cs.total_debito +
                 cs.total_credito  + cs.total_convenio + cs.total_outros) AS total_geral
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

        echo json_encode([
            'draw'            => isset($request['draw']) ? (int) $request['draw'] : 1,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    // =========================================================================
    // GET /api/pdv/caixas/detalhe?id=X
    // =========================================================================

    public function detalhe(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->responseJson('error', [], 'ID inválido.', 400);
        }

        $stmtCaixa = $this->pdo->prepare("
            SELECT cs.*, u.nome AS operador_nome
            FROM caixa_sessoes cs
            LEFT JOIN usuarios u ON u.id = cs.usuario_id
            WHERE cs.id = ?
        ");
        $stmtCaixa->execute([$id]);
        $caixa = $stmtCaixa->fetch(\PDO::FETCH_ASSOC);

        if (!$caixa) {
            $this->responseJson('error', [], 'Caixa não encontrado.', 404);
        }

        $stmtSangrias = $this->pdo->prepare("
            SELECT cs2.*, u2.nome AS operador_nome
            FROM caixa_sangrias cs2
            LEFT JOIN usuarios u2 ON u2.id = cs2.usuario_id
            WHERE cs2.caixa_sessao_id = ?
            ORDER BY cs2.data_hora ASC
        ");
        $stmtSangrias->execute([$id]);

        $this->responseJson('success', [
            'caixa'    => $caixa,
            'sangrias' => $stmtSangrias->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function _inserirCaixa(array $body, int $lojaId, string $numeroPdv, int $usuarioId, string $aberturaEm): int|false
    {
        try {
            // Detecta se coluna loja_id existe (pós-migration v3)
            $temLojaId = $this->_colunaExiste('caixa_sessoes', 'loja_id');

            $colsExtra = $temLojaId ? ', loja_id' : '';
            $valsExtra = $temLojaId ? ', :loja_id' : '';

            $stmt = $this->pdo->prepare("
                INSERT INTO caixa_sessoes
                    (numero_pdv, usuario_id, abertura_em, fechamento_em,
                     valor_abertura, total_dinheiro, total_pix, total_debito,
                     total_credito, total_convenio, total_outros,
                     total_vendas, total_canceladas, total_sangrias,
                     saldo_esperado, caixa_contado, diferenca,
                     status, observacao, sincronizado_em, created_at, updated_at{$colsExtra})
                VALUES
                    (:numero_pdv, :usuario_id, :abertura_em, :fechamento_em,
                     :valor_abertura, :total_dinheiro, :total_pix, :total_debito,
                     :total_credito, :total_convenio, :total_outros,
                     :total_vendas, :total_canceladas, :total_sangrias,
                     :saldo_esperado, :caixa_contado, :diferenca,
                     :status, :observacao, NOW(), NOW(), NOW(){$valsExtra})
            ");

            $params = $this->_buildParams($body, $numeroPdv, $usuarioId, $aberturaEm);
            if ($temLojaId) $params[':loja_id'] = $lojaId;

            $stmt->execute($params);
            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            return false;
        }
    }

    private function _atualizarCaixa(int $id, array $body): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE caixa_sessoes SET
                fechamento_em    = :fechamento_em,
                total_dinheiro   = :total_dinheiro,
                total_pix        = :total_pix,
                total_debito     = :total_debito,
                total_credito    = :total_credito,
                total_convenio   = :total_convenio,
                total_outros     = :total_outros,
                total_vendas     = :total_vendas,
                total_canceladas = :total_canceladas,
                total_sangrias   = :total_sangrias,
                saldo_esperado   = :saldo_esperado,
                caixa_contado    = :caixa_contado,
                diferenca        = :diferenca,
                status           = :status,
                observacao       = :observacao,
                sincronizado_em  = NOW(),
                updated_at       = NOW()
            WHERE id = :id
        ");

        $p = $this->_buildParams($body);
        $p[':id'] = $id;
        unset($p[':numero_pdv'], $p[':usuario_id'], $p[':abertura_em']);
        $stmt->execute($p);
    }

    private function _sincronizarSangrias(int $caixaId, array $sangrias): void
    {
        $this->pdo->prepare("DELETE FROM caixa_sangrias WHERE caixa_sessao_id = ?")->execute([$caixaId]);

        $stmt = $this->pdo->prepare("
            INSERT INTO caixa_sangrias (caixa_sessao_id, usuario_id, valor, motivo, data_hora)
            VALUES (:caixa_sessao_id, :usuario_id, :valor, :motivo, :data_hora)
        ");

        foreach ($sangrias as $s) {
            if (empty($s['valor']) || $s['valor'] <= 0) continue;
            $stmt->execute([
                ':caixa_sessao_id' => $caixaId,
                ':usuario_id'      => (int) ($s['usuario_id'] ?? 0),
                ':valor'           => (float) $s['valor'],
                ':motivo'          => $s['motivo'] ?? null,
                ':data_hora'       => $this->_sanitizarData($s['data_hora'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function _buildParams(array $body, string $numeroPdv = '', int $usuarioId = 0, string $aberturaEm = ''): array
    {
        return [
            ':numero_pdv'       => $numeroPdv  ?: trim($body['numero_pdv'] ?? '01'),
            ':usuario_id'       => $usuarioId  ?: (int) $body['usuario_id'],
            ':abertura_em'      => $aberturaEm ?: $this->_sanitizarData($body['abertura_em'] ?? null),
            ':fechamento_em'    => $this->_sanitizarData($body['fechamento_em'] ?? null),
            ':valor_abertura'   => (float) ($body['valor_abertura']   ?? 0),
            ':total_dinheiro'   => (float) ($body['total_dinheiro']   ?? 0),
            ':total_pix'        => (float) ($body['total_pix']        ?? 0),
            ':total_debito'     => (float) ($body['total_debito']     ?? 0),
            ':total_credito'    => (float) ($body['total_credito']    ?? 0),
            ':total_convenio'   => (float) ($body['total_convenio']   ?? 0),
            ':total_outros'     => (float) ($body['total_outros']     ?? 0),
            ':total_vendas'     => (int)   ($body['total_vendas']     ?? 0),
            ':total_canceladas' => (int)   ($body['total_canceladas'] ?? 0),
            ':total_sangrias'   => (float) ($body['total_sangrias']   ?? 0),
            ':saldo_esperado'   => (float) ($body['saldo_esperado']   ?? 0),
            ':caixa_contado'    => isset($body['caixa_contado']) && $body['caixa_contado'] !== null
                                    ? (float) $body['caixa_contado'] : null,
            ':diferenca'        => isset($body['diferenca']) && $body['diferenca'] !== null
                                    ? (float) $body['diferenca'] : null,
            ':status'           => in_array($body['status'] ?? '', ['aberto', 'fechado'], true)
                                    ? $body['status'] : 'aberto',
            ':observacao'       => $body['observacao'] ?? null,
        ];
    }

    private function _sanitizarData(?string $data): ?string
    {
        if (empty($data)) return null;
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $data);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

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
