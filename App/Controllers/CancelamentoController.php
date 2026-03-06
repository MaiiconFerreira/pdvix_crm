<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * CancelamentoController
 *
 * Registra e lista cancelamentos de vendas e itens.
 * PDV sincroniza cancelamentos offline via POST /api/pdv/sync-cancelamento.
 *
 * Rotas:
 *   GET  /api/cancelamentos              → listar()           DataTables
 *   POST /api/cancelamentos/venda        → cancelarVenda()    cancela venda inteira
 *   POST /api/cancelamentos/item         → cancelarItem()     cancela item específico
 *   POST /api/pdv/sync-cancelamento      → syncCancelamento() PDV sincroniza offline
 */
class CancelamentoController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // GET /api/cancelamentos
    // =========================================================================

    public function listar(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        $request    = $_GET;
        $whereParts = [];
        $params     = [];

        $lojaId = $this->getLojaIdSessao();
        if ($lojaId !== null) {
            $whereParts[]       = 'c.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        if (!empty($request['search']['value'])) {
            $s                  = '%' . $request['search']['value'] . '%';
            $whereParts[]       = '(v.numero_venda LIKE :s OR u.nome LIKE :s)';
            $params[':s']       = $s;
        }

        if (!empty($request['tipo']) && in_array($request['tipo'], ['venda', 'item'], true)) {
            $whereParts[]       = 'c.tipo = :tipo';
            $params[':tipo']    = $request['tipo'];
        }

        if (!empty($request['data_inicio'])) {
            $whereParts[]               = 'DATE(c.cancelado_em) >= :data_inicio';
            $params[':data_inicio']     = $request['data_inicio'];
        }
        if (!empty($request['data_fim'])) {
            $whereParts[]           = 'DATE(c.cancelado_em) <= :data_fim';
            $params[':data_fim']    = $request['data_fim'];
        }

        $baseSql = "FROM cancelamentos c
                    LEFT JOIN vendas v    ON v.id  = c.venda_id
                    LEFT JOIN usuarios u  ON u.id  = c.usuario_id";

        $where           = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $stmtTotal       = $this->pdo->query("SELECT COUNT(*) {$baseSql}");
        $recordsTotal    = (int) $stmtTotal->fetchColumn();
        $stmtFiltered    = $this->pdo->prepare("SELECT COUNT(*) {$baseSql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        $start  = (int) ($request['start']  ?? 0);
        $length = (int) ($request['length'] ?? 25);
        $orderDir = strtoupper($request['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $stmt = $this->pdo->prepare("
            SELECT c.*, v.numero_venda, u.nome AS operador_nome
            {$baseSql}
            {$where}
            ORDER BY c.cancelado_em {$orderDir}
            LIMIT :start, :length
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, \PDO::PARAM_STR);
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
    // POST /api/cancelamentos/venda
    // =========================================================================

    public function cancelarVenda(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente']);

        $data    = $this->getRequestData(['venda_id', 'motivo']);
        $vendaId = (int) $data['venda_id'];
        $lojaId  = $this->getLojaIdSessao() ?? 1;

        $stmt = $this->pdo->prepare("SELECT id, status, total, numero_pdv FROM vendas WHERE id = ? LIMIT 1");
        $stmt->execute([$vendaId]);
        $venda = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$venda) $this->responseJson('error', [], 'Venda não encontrada.', 404);
        if ($venda->status === 'cancelada') $this->responseJson('error', [], 'Venda já cancelada.', 409);

        $this->pdo->beginTransaction();
        try {
            // Cancela a venda
            $this->pdo->prepare(
                "UPDATE vendas SET status = 'cancelada', updated_at = NOW() WHERE id = ?"
            )->execute([$vendaId]);

            // Registra cancelamento
            $this->pdo->prepare("
                INSERT INTO cancelamentos (tipo, venda_id, loja_id, numero_pdv, usuario_id, motivo, valor_cancelado, cancelado_em, origem)
                VALUES ('venda', :venda_id, :loja_id, :numero_pdv, :usuario_id, :motivo, :valor, NOW(), 'painel')
            ")->execute([
                ':venda_id'  => $vendaId,
                ':loja_id'   => $lojaId,
                ':numero_pdv'=> $venda->numero_pdv ?? '01',
                ':usuario_id'=> (int) $_SESSION['logado']->id,
                ':motivo'    => $data['motivo'],
                ':valor'     => $venda->total,
            ]);

            $this->pdo->commit();
            $this->responseJson('success', [], 'Venda cancelada com sucesso.');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], 'Erro ao cancelar venda: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // POST /api/cancelamentos/item
    // =========================================================================

    public function cancelarItem(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente']);

        $data       = $this->getRequestData(['venda_item_id', 'motivo']);
        $itemId     = (int) $data['venda_item_id'];
        $lojaId     = $this->getLojaIdSessao() ?? 1;

        $stmt = $this->pdo->prepare("
            SELECT vi.id, vi.venda_id, vi.subtotal, v.numero_pdv
            FROM venda_itens vi
            JOIN vendas v ON v.id = vi.venda_id
            WHERE vi.id = ? LIMIT 1
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$item) $this->responseJson('error', [], 'Item não encontrado.', 404);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                INSERT INTO cancelamentos (tipo, venda_id, venda_item_id, loja_id, numero_pdv, usuario_id, motivo, valor_cancelado, cancelado_em, origem)
                VALUES ('item', :venda_id, :item_id, :loja_id, :numero_pdv, :usuario_id, :motivo, :valor, NOW(), 'painel')
            ")->execute([
                ':venda_id'  => $item->venda_id,
                ':item_id'   => $itemId,
                ':loja_id'   => $lojaId,
                ':numero_pdv'=> $item->numero_pdv ?? '01',
                ':usuario_id'=> (int) $_SESSION['logado']->id,
                ':motivo'    => $data['motivo'],
                ':valor'     => $item->subtotal,
            ]);

            $this->pdo->commit();
            $this->responseJson('success', [], 'Item cancelado com sucesso.');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->responseJson('error', [], 'Erro ao cancelar item: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // POST /api/pdv/sync-cancelamento
    // =========================================================================

    public function syncCancelamento(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['cancelamentos']) || !is_array($body['cancelamentos'])) {
            $this->responseJson('error', [], 'Campo cancelamentos é obrigatório.', 400);
        }

        // Pré-verifica quais venda_ids existem no servidor para evitar FK errors.
        // Cancelamentos cujas vendas ainda não foram sincronizadas retornam ao PDV
        // como "pendentes" para serem tentados na próxima rodada.
        $vendaIds = array_unique(array_map(fn($c) => (int)($c['venda_id'] ?? 0), $body['cancelamentos']));
        $vendaIds = array_filter($vendaIds); // remove zeros
        $existentes = [];
        if (!empty($vendaIds)) {
            $placeholders = implode(',', array_fill(0, count($vendaIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id FROM vendas WHERE id IN ({$placeholders})");
            $stmt->execute(array_values($vendaIds));
            $existentes = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id', 'id');
        }

        $inseridos      = 0;
        $aceitos        = []; // local IDs confirmados ao PDV
        $naoSincronizados = []; // venda não existe no servidor ainda

        $stmtInsert = $this->pdo->prepare("
            INSERT IGNORE INTO cancelamentos
                (tipo, venda_id, venda_item_id, loja_id, numero_pdv, usuario_id, supervisor_id, motivo, valor_cancelado, cancelado_em, origem)
            VALUES
                (:tipo, :venda_id, :item_id, :loja_id, :numero_pdv, :usuario_id, :supervisor_id, :motivo, :valor, :cancelado_em, 'pdv')
        ");

        foreach ($body['cancelamentos'] as $c) {
            $vendaId  = (int) ($c['venda_id'] ?? 0);
            $localId  = $c['local_id'] ?? null; // ID no SQLite — PDV envia para rastrear confirmação

            // Se a venda não está no servidor, devolve como pendente
            if (!isset($existentes[$vendaId])) {
                $naoSincronizados[] = $localId;
                continue;
            }

            try {
                $stmtInsert->execute([
                    ':tipo'         => $c['tipo'] === 'item' ? 'item' : 'venda',
                    ':venda_id'     => $vendaId,
                    ':item_id'      => !empty($c['venda_item_id']) ? (int) $c['venda_item_id'] : null,
                    ':loja_id'      => (int) ($c['loja_id']     ?? 1),
                    ':numero_pdv'   => $c['numero_pdv']   ?? '01',
                    ':usuario_id'   => (int) ($c['usuario_id']  ?? 1),
                    ':supervisor_id'=> !empty($c['supervisor_id']) ? (int) $c['supervisor_id'] : null,
                    ':motivo'       => $c['motivo']       ?? null,
                    ':valor'        => (float) ($c['valor']     ?? 0),
                    ':cancelado_em' => $c['cancelado_em'] ?? date('Y-m-d H:i:s'),
                ]);
                // INSERT IGNORE retorna 0 afetadas em duplicata (sync_key) — ambos os casos são OK
                $aceitos[] = $localId;
                $inseridos++;
            } catch (\Throwable $e) {
                // Erro inesperado: não marca como sincronizado
                $naoSincronizados[] = $localId;
            }
        }

        $this->responseJson('success', [
            'inseridos'         => $inseridos,
            'aceitos'           => $aceitos,           // IDs locais OK → PDV marca sincronizado=1
            'nao_sincronizados' => $naoSincronizados,  // IDs pendentes → PDV mantém sincronizado=0
        ], 'Cancelamentos sincronizados.');
    }
}
