<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * CargaInicialController
 *
 * Endpoint exclusivo para o PDV Electron carregar todos os dados mestres
 * necessários para funcionar offline.
 *
 * Autenticação: token estático (config.api_token) via validarTokenPdv().
 *
 * Rota:
 *   GET /api/carga-inicial?token=XXX[&loja_id=1]  → index()
 *
 * Payload de resposta:
 * {
 *   "produtos":             [...],   id, nome, codigo_interno_alternativo,
 *                                    preco_venda, fator_embalagem, unidade_base, bloqueado
 *   "codigos_barras":       [...],   produto_id, codigo_barras, tipo_embalagem, preco_venda
 *   "usuarios":             [...],   id, login, perfil, nome, cpf, status  (SEM password)
 *   "supervisores_cartoes": [...],   id, usuario_id, codigo_cartao, permissões...
 *   "clientes":             [...]    id, nome, cpf, telefone, status
 * }
 *
 * Nota sobre loja_id:
 *   - Após migration_v3, produtos e supervisores_cartoes terão loja_id.
 *   - O parâmetro ?loja_id=X filtra os dados da loja específica.
 *   - Antes da migration, o parâmetro é ignorado e todos os dados são retornados.
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
        $this->validarTokenPdv();

        // loja_id opcional — filtra dados por loja quando a coluna existir
        $lojaId = !empty($_GET['loja_id']) && is_numeric($_GET['loja_id'])
            ? (int) $_GET['loja_id']
            : null;

        $this->responseJson('success', [
            'produtos'             => $this->getProdutos($lojaId),
            'codigos_barras'       => $this->getCodigosBarras($lojaId),
            'usuarios'             => $this->getUsuarios($lojaId),
            'supervisores_cartoes' => $this->getSupCards($lojaId),
            'clientes'             => $this->getClientes(),
        ], 'Carga inicial carregada com sucesso.');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Produtos ativos — exclui bloqueados.
     * Filtra por loja_id se a coluna existir (pós-migration v3).
     */
    private function getProdutos(?int $lojaId): array
    {
        $where  = 'WHERE p.bloqueado = 0';
        $params = [];

        if ($lojaId !== null && $this->_colunaExiste('produtos', 'loja_id')) {
            $where          .= ' AND p.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.nome,
                p.codigo_interno_alternativo,
                p.preco_venda,
                p.fator_embalagem,
                p.unidade_base,
                p.bloqueado
            FROM produtos p
            {$where}
            ORDER BY p.nome
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Códigos de barras de todos os produtos ativos da loja.
     */
    private function getCodigosBarras(?int $lojaId): array
    {
        $join   = '';
        $where  = 'WHERE p.bloqueado = 0';
        $params = [];

        if ($lojaId !== null && $this->_colunaExiste('produtos', 'loja_id')) {
            $where          .= ' AND p.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                pcb.produto_id,
                pcb.codigo_barras,
                pcb.tipo_embalagem,
                pcb.preco_venda
            FROM produtos_codigos_barras pcb
            JOIN produtos p ON p.id = pcb.produto_id
            {$where}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Usuários ativos — SEM campo password.
     * O hash bcrypt para auth offline é gerado pelo Electron no login online.
     * Filtra por loja via loja_usuarios quando multi-loja estiver ativo.
     */
    private function getUsuarios(?int $lojaId): array
    {
        $where  = "WHERE u.status = 'ativado'";
        $params = [];

        // Filtra por loja via tabela de relacionamento loja_usuarios (pós-migration v3)
        if ($lojaId !== null && $this->_tabelaExiste('loja_usuarios')) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM loja_usuarios lu
                WHERE lu.usuario_id = u.id AND lu.loja_id = :loja_id
            )';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.login, u.perfil, u.nome, u.cpf, u.status
            FROM usuarios u
            {$where}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Cartões de supervisor ativos da loja.
     */
    private function getSupCards(?int $lojaId): array
    {
        try {
            $where  = 'WHERE sc.ativo = 1';
            $params = [];

            if ($lojaId !== null && $this->_colunaExiste('supervisores_cartoes', 'loja_id')) {
                $where          .= ' AND sc.loja_id = :loja_id';
                $params[':loja_id'] = $lojaId;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    sc.id, sc.usuario_id, sc.codigo_cartao, sc.descricao,
                    sc.permite_desconto_item, sc.permite_desconto_venda,
                    sc.permite_cancelar_item, sc.permite_cancelar_venda, sc.ativo
                FROM supervisores_cartoes sc
                {$where}
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Clientes ativos — não filtrado por loja (clientes são globais).
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

    /**
     * Verifica se uma coluna existe em uma tabela.
     * Usado para compatibilidade pré/pós migration_v3.
     */
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

    /**
     * Verifica se uma tabela existe.
     */
    private function _tabelaExiste(string $tabela): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$tabela]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
