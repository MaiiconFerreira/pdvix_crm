<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * PdvCaixaController
 *
 * Endpoint para o PDV Electron sincronizar sessões de caixa (abertura/fechamento)
 * e suas sangrias para o servidor.
 *
 * Autenticação: token estático (igual ao PdvSyncController — sem sessão PHP).
 *
 * Rotas em routes.php:
 *   POST /api/pdv/sync-caixa   → sync()   — sincroniza abertura + fechamento + sangrias
 *   GET  /api/pdv/caixas       → listar() — lista caixas (DataTables server-side)
 *
 * ─── Payload POST /api/pdv/sync-caixa ────────────────────────────────────────
 * {
 *   "numero_pdv":     "01",
 *   "usuario_id":     1,
 *   "abertura_em":    "2026-03-05 08:00:00",
 *   "fechamento_em":  "2026-03-05 17:30:00",   // null se ainda aberto
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
 *   "caixa_contado":  168.00,          // null se não informado
 *   "diferenca":      -2.00,           // null se não informado
 *   "status":         "fechado",
 *   "observacao":     null,
 *   "sangrias": [
 *     { "usuario_id": 1, "valor": 200.00, "motivo": "Retirada", "data_hora": "2026-03-05 12:00:00" }
 *   ]
 * }
 *
 * Resposta sucesso: { "status": "success", "data": { "id_servidor": 7 }, "message": "..." }
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
        $this->validarToken();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // ── Validação mínima ──────────────────────────────────────────────────
        foreach (['usuario_id', 'abertura_em', 'status'] as $campo) {
            if (empty($body[$campo]) && $body[$campo] !== 0) {
                $this->responseJson('error', [], "Campo obrigatório ausente: {$campo}.", 400);
            }
        }

        $numeroPdv  = trim($body['numero_pdv'] ?? '01');
        $usuarioId  = (int) $body['usuario_id'];
        $aberturaEm = $this->_sanitizarData($body['abertura_em'] ?? null);

        if (!$aberturaEm) {
            $this->responseJson('error', [], 'abertura_em inválida.', 400);
        }

        // ── Idempotência: sessão já sincronizada? ─────────────────────────────
        // Chave: mesmo PDV + mesmo operador + mesma data de abertura
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
                // Atualiza (pode ser reenvio com fechamento agora)
                $idServidor = $existente->id;
                $this->_atualizarCaixa($idServidor, $body);
            } else {
                // Insere novo
                $idServidor = $this->_inserirCaixa($body, $numeroPdv, $usuarioId, $aberturaEm);
                if (!$idServidor) {
                    throw new \RuntimeException('Falha ao inserir caixa no servidor.');
                }
            }

            // ── Sangrias — re-sincroniza sempre (DELETE + INSERT idempotente) ─
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
    // GET /api/pdv/caixas   (DataTables server-side — painel admin)
    // =========================================================================

    public function listar(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        // Esta rota usa sessão PHP normal (painel admin) — sem token

        $request = $_GET;

        $whereParts = [];
        $params     = [];

        // Busca global
        if (!empty($request['search']['value'])) {
            $search         = '%' . $request['search']['value'] . '%';
            $whereParts[]   = "(u.nome LIKE :search OR cs.numero_pdv LIKE :search)";
            $params[':search'] = $search;
        }

        // Filtro: status
        if (!empty($request['status']) && in_array($request['status'], ['aberto', 'fechado'], true)) {
            $whereParts[]      = "cs.status = :status";
            $params[':status'] = $request['status'];
        }

        // Filtro: número PDV
        if (!empty($request['numero_pdv'])) {
            $whereParts[]          = "cs.numero_pdv = :numero_pdv";
            $params[':numero_pdv'] = $request['numero_pdv'];
        }

        // Filtro: datas
        if (!empty($request['data_inicio'])) {
            $whereParts[]             = "DATE(cs.abertura_em) >= :data_inicio";
            $params[':data_inicio']   = $request['data_inicio'];
        }
        if (!empty($request['data_fim'])) {
            $whereParts[]          = "DATE(cs.abertura_em) <= :data_fim";
            $params[':data_fim']   = $request['data_fim'];
        }

        $colunasOrdenacao = [
            0 => 'cs.id',
            1 => 'cs.numero_pdv',
            2 => 'u.nome',
            3 => 'cs.abertura_em',
            4 => 'cs.fechamento_em',
            5 => 'cs.total_vendas',
            6 => 'cs.total_sangrias',
            7 => 'cs.saldo_esperado',
            8 => 'cs.diferenca',
            9 => 'cs.status',
        ];

        $baseSql = "FROM caixa_sessoes cs
                    LEFT JOIN usuarios u ON u.id = cs.usuario_id";

        $where = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();

        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $orderColIndex = (int) ($request['order'][0]['column'] ?? 3);
        $orderCol      = $colunasOrdenacao[$orderColIndex] ?? 'cs.abertura_em';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $start  = isset($request['start'])  ? (int) $request['start']  : 0;
        $length = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT
                cs.id,
                cs.numero_pdv,
                u.nome        AS operador_nome,
                cs.abertura_em,
                cs.fechamento_em,
                cs.valor_abertura,
                cs.total_dinheiro,
                cs.total_pix,
                cs.total_debito,
                cs.total_credito,
                cs.total_convenio,
                cs.total_outros,
                cs.total_vendas,
                cs.total_canceladas,
                cs.total_sangrias,
                cs.saldo_esperado,
                cs.caixa_contado,
                cs.diferenca,
                cs.status,
                cs.observacao,
                cs.sincronizado_em,
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
    // GET /api/pdv/caixas/detalhe?id=X  — sangrias de um caixa específico
    // =========================================================================

    public function detalhe(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');

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
        $sangrias = $stmtSangrias->fetchAll(\PDO::FETCH_ASSOC);

        $this->responseJson('success', [
            'caixa'    => $caixa,
            'sangrias' => $sangrias,
        ]);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function validarToken(): void
    {
        $token = trim($_GET['token'] ?? '');
        $stmt  = $this->pdo->prepare(
            "SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row || empty($row->valor) || !hash_equals($row->valor, $token)) {
            $this->responseJson('error', [], 'Token inválido ou ausente.', 401);
        }
    }

    private function _inserirCaixa(array $body, string $numeroPdv, int $usuarioId, string $aberturaEm): int|false
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO caixa_sessoes
                    (numero_pdv, usuario_id, abertura_em, fechamento_em,
                     valor_abertura, total_dinheiro, total_pix, total_debito,
                     total_credito, total_convenio, total_outros,
                     total_vendas, total_canceladas, total_sangrias,
                     saldo_esperado, caixa_contado, diferenca,
                     status, observacao, sincronizado_em, created_at, updated_at)
                VALUES
                    (:numero_pdv, :usuario_id, :abertura_em, :fechamento_em,
                     :valor_abertura, :total_dinheiro, :total_pix, :total_debito,
                     :total_credito, :total_convenio, :total_outros,
                     :total_vendas, :total_canceladas, :total_sangrias,
                     :saldo_esperado, :caixa_contado, :diferenca,
                     :status, :observacao, NOW(), NOW(), NOW())
            ");

            $stmt->execute($this->_buildParams($body, $numeroPdv, $usuarioId, $aberturaEm));
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
        // Remove sangrias antigas deste caixa e reinsere (garante idempotência)
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
            ':caixa_contado'    => isset($body['caixa_contado'])  && $body['caixa_contado']  !== null ? (float) $body['caixa_contado']  : null,
            ':diferenca'        => isset($body['diferenca'])      && $body['diferenca']      !== null ? (float) $body['diferenca']      : null,
            ':status'           => in_array($body['status'] ?? '', ['aberto', 'fechado'], true) ? $body['status'] : 'aberto',
            ':observacao'       => $body['observacao'] ?? null,
        ];
    }

    private function _sanitizarData(?string $data): ?string
    {
        if (empty($data)) return null;
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $data);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }
}