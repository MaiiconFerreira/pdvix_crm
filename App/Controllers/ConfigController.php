<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * ConfigController
 *
 * Gerencia configurações do sistema (tabela `config`) e maquininhas Stone POS
 * (tabela `maquininhas`). Acesso exclusivo ao perfil administrador.
 *
 * Rotas:
 *   GET    /api/config              → getAll()        Retorna todas as configs
 *   POST   /api/config              → salvar()        Salva uma ou várias chaves
 *   GET    /api/config/maquininhas  → listMaquininhas()
 *   POST   /api/config/maquininhas  → createMaquininha()
 *   PUT    /api/config/maquininhas  → updateMaquininha()
 *   DELETE /api/config/maquininhas  → deleteMaquininha()
 */
class ConfigController extends Controller
{
    private \PDO $pdo;

    /** Chaves que podem ser lidas/escritas via painel */
    private array $chavesPermitidas = [
        'nome_loja',
        'cnpj_loja',
        'endereco_loja',
        'telefone_loja',
        'email_loja',
        'logo_url',
        'pagarme_api_key',
        'pagarme_webhook_secret',
        'pagarme_customer_id',
        'pix_expiracao_segundos',
        'versao',
        'api_token',
    ];

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // DISPATCHER
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador']);

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->getAll(),
            'POST'   => $this->salvar(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    public function maquininhas(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador']);

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->listMaquininhas(),
            'POST'   => $this->createMaquininha(),
            'PUT'    => $this->updateMaquininha(),
            'DELETE' => $this->deleteMaquininha(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // GET /api/config
    // =========================================================================

    private function getAll(): void
    {
        $stmt = $this->pdo->query("SELECT chave, valor FROM config ORDER BY chave");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Oculta valores sensíveis — retorna apenas flag booleano
        $sensiveis = ['pagarme_api_key', 'pagarme_webhook_secret', 'api_token'];
        $resultado = [];
        foreach ($rows as $row) {
            if (in_array($row['chave'], $sensiveis, true) && !empty($row['valor'])) {
                $resultado[$row['chave']] = ['valor' => '***configurado***', 'sensivel' => true];
            } else {
                $resultado[$row['chave']] = ['valor' => $row['valor'], 'sensivel' => false];
            }
        }

        $this->responseJson('success', $resultado, 'Configurações carregadas.');
    }

    // =========================================================================
    // POST /api/config
    // Aceita { chave, valor } para uma chave, ou { configs: [{chave,valor},...] }
    // =========================================================================

    private function salvar(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Lote ou chave única
        $pares = !empty($data['configs']) ? $data['configs'] : [['chave' => $data['chave'] ?? '', 'valor' => $data['valor'] ?? '']];

        $stmt = $this->pdo->prepare(
            "INSERT INTO config (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );

        $erros = [];
        foreach ($pares as $par) {
            $chave = trim($par['chave'] ?? '');
            $valor = $par['valor'] ?? '';

            if (empty($chave)) {
                $erros[] = 'Chave vazia ignorada.';
                continue;
            }

            // Bloqueia chaves não permitidas (exceto se já existem — permite atualizar qualquer chave)
            // Para criar novas chaves restringe à whitelist; para editar existentes é livre.
            $existe = $this->pdo->prepare("SELECT 1 FROM config WHERE chave = ?");
            $existe->execute([$chave]);
            if (!$existe->fetchColumn() && !in_array($chave, $this->chavesPermitidas, true)) {
                $erros[] = "Chave '{$chave}' não está na lista de chaves permitidas.";
                continue;
            }

            $stmt->execute([':chave' => $chave, ':valor' => $valor]);
        }

        if (!empty($erros)) {
            $this->responseJson('error', ['erros' => $erros], implode(' | ', $erros));
        }

        $this->responseJson('success', [], 'Configurações salvas com sucesso.');
    }

    // =========================================================================
    // GET /api/config/maquininhas
    // =========================================================================

    private function listMaquininhas(): void
    {
        $lojaId = $this->getLojaIdSessao();

        $sql    = "SELECT m.*, l.nome AS loja_nome FROM maquininhas m LEFT JOIN lojas l ON l.id = m.loja_id";
        $params = [];

        if ($lojaId !== null) {
            $sql .= " WHERE m.loja_id = :loja_id";
            $params[':loja_id'] = $lojaId;
        }

        $sql .= " ORDER BY m.nome";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC), 'Maquininhas carregadas.');
    }

    // =========================================================================
    // POST /api/config/maquininhas
    // =========================================================================

    private function createMaquininha(): void
    {
        $data = $this->getRequestData(
            ['nome', 'device_serial_number', 'loja_id'],
            ['descricao', 'tipo_padrao', 'status']
        );

        $serial = trim($data['device_serial_number']);
        if (empty($serial)) {
            $this->responseJson('error', [], 'device_serial_number é obrigatório.', 422);
        }

        // Verifica unicidade do serial
        $chk = $this->pdo->prepare("SELECT id FROM maquininhas WHERE device_serial_number = ? LIMIT 1");
        $chk->execute([$serial]);
        if ($chk->fetchColumn()) {
            $this->responseJson('error', [], 'Serial já cadastrado para outra maquininha.', 409);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO maquininhas (nome, device_serial_number, loja_id, descricao, tipo_padrao, status, created_at)
            VALUES (:nome, :serial, :loja_id, :descricao, :tipo_padrao, :status, NOW())
        ");
        $ok = $stmt->execute([
            ':nome'        => mb_substr($data['nome'], 0, 100),
            ':serial'      => $serial,
            ':loja_id'     => (int) $data['loja_id'],
            ':descricao'   => $data['descricao']   ?? null,
            ':tipo_padrao' => $data['tipo_padrao']  ?? 'debit',
            ':status'      => $data['status']       ?? 'ativa',
        ]);

        $this->responseJson(
            $ok ? 'success' : 'error',
            $ok ? ['id' => (int) $this->pdo->lastInsertId()] : [],
            $ok ? 'Maquininha cadastrada com sucesso.' : 'Erro ao cadastrar maquininha.'
        );
    }

    // =========================================================================
    // PUT /api/config/maquininhas
    // =========================================================================

    private function updateMaquininha(): void
    {
        $data = $this->getRequestData(['id'], ['nome', 'device_serial_number', 'loja_id', 'descricao', 'tipo_padrao', 'status']);
        $id   = (int) $data['id'];

        $chk = $this->pdo->prepare("SELECT id FROM maquininhas WHERE id = ? LIMIT 1");
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            $this->responseJson('error', [], 'Maquininha não encontrada.', 404);
        }

        // Verifica unicidade do serial se foi alterado
        if (!empty($data['device_serial_number'])) {
            $chk2 = $this->pdo->prepare("SELECT id FROM maquininhas WHERE device_serial_number = ? AND id != ? LIMIT 1");
            $chk2->execute([trim($data['device_serial_number']), $id]);
            if ($chk2->fetchColumn()) {
                $this->responseJson('error', [], 'Serial já cadastrado para outra maquininha.', 409);
            }
        }

        $allowed = ['nome', 'device_serial_number', 'loja_id', 'descricao', 'tipo_padrao', 'status'];
        $sets    = [];
        $params  = [':id' => $id];
        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $sets[]        = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) {
            $this->responseJson('error', [], 'Nenhum campo para atualizar.', 422);
        }

        $stmt = $this->pdo->prepare("UPDATE maquininhas SET " . implode(', ', $sets) . " WHERE id = :id");
        $ok   = $stmt->execute($params);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Maquininha atualizada com sucesso.' : 'Erro ao atualizar maquininha.'
        );
    }

    // =========================================================================
    // DELETE /api/config/maquininhas
    // =========================================================================

    private function deleteMaquininha(): void
    {
        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        $stmt = $this->pdo->prepare("DELETE FROM maquininhas WHERE id = ?");
        $ok   = $stmt->execute([$id]) && $stmt->rowCount() > 0;

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Maquininha removida com sucesso.' : 'Maquininha não encontrada.'
        );
    }
}