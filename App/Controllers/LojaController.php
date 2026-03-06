<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * LojaController
 *
 * CRUD de lojas + gestão de PDVs cadastrados.
 * Apenas administradores.
 *
 * Rotas:
 *   GET    /api/lojas              → index()       listar lojas
 *   POST   /api/lojas              → index()       criar loja
 *   PUT    /api/lojas              → index()       atualizar loja
 *   DELETE /api/lojas              → index()       excluir loja
 *   GET    /api/lojas/pdvs         → pdvs()        listar PDVs de todas as lojas
 *   POST   /api/lojas/pdvs         → pdvs()        cadastrar PDV
 *   PUT    /api/lojas/pdvs         → pdvs()        atualizar PDV
 */
class LojaController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // DISPATCHER /api/lojas
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador']);

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'    => $this->_listarLojas(),
            'POST'   => $this->_criarLoja(),
            'PUT'    => $this->_atualizarLoja(),
            'DELETE' => $this->_excluirLoja(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // DISPATCHER /api/lojas/pdvs
    // =========================================================================

    public function pdvs(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->requirePerfil(['administrador', 'gerente']);

        match (strtoupper($_SERVER['REQUEST_METHOD'])) {
            'GET'  => $this->_listarPdvs(),
            'POST' => $this->_criarPdv(),
            'PUT'  => $this->_atualizarPdv(),
            default => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // LOJAS
    // =========================================================================

    private function _listarLojas(): void
    {
        $lojas = $this->pdo->query("
            SELECT l.*, COUNT(p.id) AS total_pdvs
            FROM lojas l
            LEFT JOIN pdvs p ON p.loja_id = l.id
            GROUP BY l.id
            ORDER BY l.nome
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->responseJson('success', $lojas);
    }

    private function _criarLoja(): void
    {
        $data = $this->getRequestData(
            ['nome', 'cnpj'],
            ['endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep', 'telefone']
        );

        // Verifica CNPJ duplicado
        $stmt = $this->pdo->prepare("SELECT id FROM lojas WHERE cnpj = ? LIMIT 1");
        $stmt->execute([$data['cnpj']]);
        if ($stmt->fetch()) {
            $this->responseJson('error', [], 'CNPJ já cadastrado.', 409);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO lojas (nome, cnpj, endereco, numero, bairro, cidade, estado, cep, telefone, status)
            VALUES (:nome, :cnpj, :endereco, :numero, :bairro, :cidade, :estado, :cep, :telefone, 'ativa')
        ");
        $stmt->execute([
            ':nome'     => $data['nome'],
            ':cnpj'     => $data['cnpj'],
            ':endereco' => $data['endereco'] ?? null,
            ':numero'   => $data['numero']   ?? null,
            ':bairro'   => $data['bairro']   ?? null,
            ':cidade'   => $data['cidade']   ?? null,
            ':estado'   => $data['estado']   ?? null,
            ':cep'      => $data['cep']      ?? null,
            ':telefone' => $data['telefone'] ?? null,
        ]);

        $this->responseJson('success', ['id' => (int) $this->pdo->lastInsertId()], 'Loja criada com sucesso!');
    }

    private function _atualizarLoja(): void
    {
        $data = $this->getRequestData(
            ['id'],
            ['nome', 'cnpj', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep', 'telefone', 'status']
        );

        $id = (int) $data['id'];
        if ($id <= 0) $this->responseJson('error', [], 'ID inválido.', 400);

        $campos = ['nome', 'cnpj', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep', 'telefone', 'status'];
        $sets   = [];
        $params = [':id' => $id];

        foreach ($campos as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== null) {
                $sets[]           = "`{$campo}` = :{$campo}";
                $params[":{$campo}"] = $data[$campo];
            }
        }

        if (empty($sets)) $this->responseJson('error', [], 'Nenhum dado para atualizar.', 400);

        $this->pdo->prepare("UPDATE lojas SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        $this->responseJson('success', [], 'Loja atualizada com sucesso!');
    }

    private function _excluirLoja(): void
    {
        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM vendas WHERE loja_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->responseJson('error', [], 'Não é possível excluir: loja possui vendas vinculadas.', 409);
        }

        $this->pdo->prepare("DELETE FROM lojas WHERE id = ?")->execute([$id]);
        $this->responseJson('success', [], 'Loja excluída com sucesso!');
    }

    // =========================================================================
    // PDVs
    // =========================================================================

    private function _listarPdvs(): void
    {
        $lojaId = $this->getLojaIdSessao();
        $where  = '';
        $params = [];

        if ($lojaId !== null) {
            $where              = 'WHERE p.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.*, l.nome AS loja_nome
            FROM pdvs p
            LEFT JOIN lojas l ON l.id = p.loja_id
            {$where}
            ORDER BY l.nome, p.numero_pdv
        ");
        $stmt->execute($params);
        $this->responseJson('success', $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function _criarPdv(): void
    {
        $data = $this->getRequestData(
            ['loja_id', 'numero_pdv'],
            ['descricao', 'url_local']
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO pdvs (loja_id, numero_pdv, descricao, url_local, status)
            VALUES (:loja_id, :numero_pdv, :descricao, :url_local, 'ativo')
        ");
        try {
            $stmt->execute([
                ':loja_id'    => (int) $data['loja_id'],
                ':numero_pdv' => $data['numero_pdv'],
                ':descricao'  => $data['descricao']  ?? null,
                ':url_local'  => $data['url_local']  ?? null,
            ]);
            $this->responseJson('success', ['id' => (int) $this->pdo->lastInsertId()], 'PDV cadastrado com sucesso!');
        } catch (\PDOException $e) {
            $this->responseJson('error', [], 'PDV já cadastrado nesta loja.', 409);
        }
    }

    private function _atualizarPdv(): void
    {
        $data = $this->getRequestData(
            ['id'],
            ['descricao', 'url_local', 'status']
        );

        $id     = (int) $data['id'];
        $params = [':id' => $id];
        $sets   = [];

        foreach (['descricao', 'url_local', 'status'] as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== null) {
                $sets[]           = "`{$campo}` = :{$campo}";
                $params[":{$campo}"] = $data[$campo];
            }
        }

        if (empty($sets)) $this->responseJson('error', [], 'Nenhum dado para atualizar.', 400);

        $this->pdo->prepare("UPDATE pdvs SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        $this->responseJson('success', [], 'PDV atualizado com sucesso!');
    }

    }

