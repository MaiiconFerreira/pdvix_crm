<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * CargaInicialController
 *
 * Endpoint exclusivo para o PDV Electron carregar todos os dados mestres
 * necessários para funcionar offline (produtos, códigos de barras, usuários,
 * cartões de supervisor e clientes).
 *
 * Autenticação: token estático armazenado em config.chave = 'api_token'.
 * Gere o token com: UPDATE config SET valor = UUID() WHERE chave = 'api_token';
 *
 * Rota mapeada em routes.php:
 *   GET  /api/carga-inicial?token=XXX  → index()
 */
class CargaInicialController extends Controller
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // =========================================================================
    // GET /api/carga-inicial
    // =========================================================================

    public function index(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        $this->validarToken();

        $this->responseJson('success', [
            'produtos'             => $this->getProdutos(),
            'codigos_barras'       => $this->getCodigosBarras(),
            'usuarios'             => $this->getUsuarios(),
            'supervisores_cartoes' => $this->getSupCards(),
            'clientes'             => $this->getClientes(),
        ], 'Carga inicial carregada com sucesso.');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /** Valida o token da requisição contra o valor armazenado em config. */
    private function validarToken(): void
    {
        $token = trim($_GET['token'] ?? '');

        $stmt = $this->pdo->prepare(
            "SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row || empty($row->valor) || !hash_equals($row->valor, $token)) {
            $this->responseJson('error', [], 'Token inválido ou ausente.', 401);
        }
    }

    /**
     * Produtos ativos com todos os campos necessários para o PDV.
     * Exclui bloqueados.
     */
    private function getProdutos(): array
    {
        return $this->pdo->query("
            SELECT
                id,
                nome,
                codigo_interno_alternativo,
                preco_venda,
                fator_embalagem,
                unidade_base,
                bloqueado
            FROM produtos
            WHERE bloqueado = 0
            ORDER BY nome
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Todos os códigos de barras de produtos ativos.
     * Retorna array plano — o Electron faz o JOIN localmente.
     */
    private function getCodigosBarras(): array
    {
        return $this->pdo->query("
            SELECT
                pcb.produto_id,
                pcb.codigo_barras,
                pcb.tipo_embalagem,
                pcb.preco_venda
            FROM produtos_codigos_barras pcb
            JOIN produtos p ON p.id = pcb.produto_id
            WHERE p.bloqueado = 0
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Usuários ativos — SEM campo password.
     * O hash bcrypt para auth offline é gerado e salvo pelo Electron no momento
     * do login online; aqui só sincronizamos os metadados.
     */
    private function getUsuarios(): array
    {
        return $this->pdo->query("
            SELECT id, login, perfil, nome, cpf, status
            FROM usuarios
            WHERE status = 'ativado'
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Cartões de supervisor ativos.
     * Tabela criada pela migration_v2.sql.
     */
    private function getSupCards(): array
    {
        try {
            return $this->pdo->query("
                SELECT
                    id, usuario_id, codigo_cartao, descricao,
                    permite_desconto_item, permite_desconto_venda,
                    permite_cancelar_item, permite_cancelar_venda, ativo
                FROM supervisores_cartoes
                WHERE ativo = 1
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Tabela pode não existir antes da migration — retorna array vazio
            return [];
        }
    }

    /**
     * Clientes ativos para busca por CPF no PDV.
     * Tabela criada pela migration_v2.sql.
     */
    private function getClientes(): array
    {
        try {
            return $this->pdo->query("
                SELECT id, nome, cpf, telefone, status
                FROM clientes
                WHERE status = 'ativo'
                ORDER BY nome
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }
}