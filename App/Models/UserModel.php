<?php
namespace App\Models;
use App\Core\Database;

class UserModel extends Database {

    private $pdo;

    /** Colunas permitidas para SELECT genérico */
    private array $colunasValidas = [
        'id',
        'login',
        'perfil',
        'nome',
        'cpf',
        'email',
        'telefone',
        'status',
        'data_criacao',
        'criado_por',
        'ultimo_login',
    ];

    /** Colunas permitidas para UPDATE */
    private array $colunasValidas_update = [
        'login',
        'password',
        'perfil',
        'nome',
        'cpf',
        'email',
        'telefone',
        'status',
        'ultimo_login',
    ];

    /** Colunas permitidas para SELECT na listagem completa */
    private array $colunasValidas_list = [
        'u.id',
        'u.login',
        'u.perfil',
        'u.nome',
        'u.cpf',
        'u.email',
        'u.telefone',
        'u.status',
        'u.data_criacao',
        'u.ultimo_login',
        'u.criado_por',
    ];

    /** Campos aceitos em existUser() */
    private array $existUser_acceptsParams = ['id', 'cpf', 'login', 'email'];

    /** Perfis válidos da tabela (enum) */
    private array $perfilValidos = ['operador', 'gerente', 'administrador'];

    public function __construct() {
        $this->pdo = Database::getConnection();
        parent::__construct();
    }

    // -------------------------------------------------------------------------
    // LEITURA
    // -------------------------------------------------------------------------

    /**
     * Retorna um usuário específico (por login) com colunas filtradas.
     */
    public function findByLogin(string $login, array $colunas = ['id', 'login', 'nome', 'perfil', 'status']): ?object {
        $colunas = array_values(array_intersect($colunas, $this->colunasValidas));
        if (empty($colunas)) {
            $colunas = ['id'];
        }

        $colunasStr = implode(', ', array_map(fn($c) => "`$c`", $colunas));
        $sql = "SELECT {$colunasStr} FROM usuarios WHERE login = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$login]);

        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Retorna um usuário específico por ID.
     */
    public function findById(int $id, array $colunas = ['id', 'login', 'nome', 'perfil', 'status']): ?object {
        $colunas = array_values(array_intersect($colunas, $this->colunasValidas));
        if (empty($colunas)) {
            $colunas = ['id'];
        }

        $colunasStr = implode(', ', array_map(fn($c) => "`$c`", $colunas));
        $sql = "SELECT {$colunasStr} FROM usuarios WHERE id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Retorna hash da senha para verificação no login.
     */
    public function getPasswordHash(string $login): ?string {
        $stmt = $this->pdo->prepare("SELECT `password` FROM usuarios WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        return $row ? $row->password : null;
    }

    /**
     * Listagem completa — suporta busca textual e paginação (DataTables server-side).
     */
    public function listUsers(array $request = []): array {
        $colunasStr = implode(', ', $this->colunasValidas_list);

        $sql = "FROM usuarios u";

        $whereParts = [];
        $params     = [];

        // Busca global (DataTables)
        if (!empty($request['search']['value'])) {
            $search = '%' . $request['search']['value'] . '%';
            $whereParts[] = "(u.nome LIKE :search OR u.login LIKE :search OR u.cpf LIKE :search OR u.email LIKE :search OR u.telefone LIKE :search)";
            $params[':search'] = $search;
        }

        // Filtro por perfil
        if (!empty($request['perfil']) && in_array($request['perfil'], $this->perfilValidos)) {
            $whereParts[] = "u.perfil = :perfil";
            $params[':perfil'] = $request['perfil'];
        }

        // Filtro por status
        if (isset($request['status']) && in_array($request['status'], ['ativado', 'desativado'])) {
            $whereParts[] = "u.status = :status";
            $params[':status'] = $request['status'];
        }

        $where = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

        // Totais para DataTables
        $stmtTotal = $this->pdo->query("SELECT COUNT(*) {$sql}");
        $recordsTotal = $stmtTotal->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(*) {$sql} {$where}");
        $stmtFiltered->execute($params);
        $recordsFiltered = $stmtFiltered->fetchColumn();

        // Ordenação e paginação
        $colunasIndex = array_values($this->colunasValidas_list);
        $orderColIndex = (int) ($request['order'][0]['column'] ?? 0);
        $orderCol      = $colunasIndex[$orderColIndex] ?? 'u.id';
        $orderDir      = strtoupper($request['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $start         = isset($request['start'])  ? (int) $request['start']  : 0;
        $length        = isset($request['length']) ? (int) $request['length'] : 25;

        $stmt = $this->pdo->prepare("
            SELECT {$colunasStr}
            {$sql}
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

        return [
            'draw'            => isset($request['draw']) ? (int) $request['draw'] : 1,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    // -------------------------------------------------------------------------
    // VERIFICAÇÕES
    // -------------------------------------------------------------------------

    /**
     * Verifica se um usuário existe pelo campo+valor informado.
     */
    public function existUser(string $field, string $value): bool {
        if (!in_array($field, $this->existUser_acceptsParams)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE `{$field}` = :value LIMIT 1");
        $stmt->bindValue(':value', $value);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    /**
     * Valida se o perfil informado é um dos valores do enum.
     */
    public function isPerfilValido(string $perfil): bool {
        return in_array($perfil, $this->perfilValidos, true);
    }

    // -------------------------------------------------------------------------
    // ESCRITA
    // -------------------------------------------------------------------------

    /**
     * Cria um novo usuário.
     */
    public function createUser(array $data): bool {
        $sql = "INSERT INTO usuarios (login, password, perfil, nome, cpf, email, telefone, criado_por)
                VALUES (:login, :password, :perfil, :nome, :cpf, :email, :telefone, :criado_por)";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':login'     => $data[':login'],
                ':password'  => $data[':password'],
                ':perfil'    => $data[':perfil'],
                ':nome'      => $data[':nome'],
                ':cpf'       => $data[':cpf'],
                ':email'     => $data[':email']     ?? '',
                ':telefone'  => $data[':telefone']  ?? '',
                ':criado_por'=> $data[':criado_por'],
            ]);
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => array_diff_key($data, [':password' => '']),
            ]);
            return false;
        }
    }

    /**
     * Atualiza campos de um usuário pelo ID.
     */
    public function updateUser(int $id, array $data): bool {
        $updateData = array_intersect_key($data, array_flip($this->colunasValidas_update));

        if (empty($updateData)) {
            return false;
        }

        $setParts = [];
        foreach ($updateData as $col => $val) {
            $setParts[] = "`{$col}` = :{$col}";
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $setParts) . " WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($updateData as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => array_diff_key($data, ['password' => '']),
            ]);
            return false;
        }
    }

    /**
     * Alterna status entre 'ativado' e 'desativado'.
     */
    public function toggleStatus(int $id): ?string {
        $user = $this->findById($id, ['status']);
        if (!$user) {
            return null;
        }

        $novoStatus = ($user->status === 'ativado') ? 'desativado' : 'ativado';
        $ok = $this->updateUser($id, ['status' => $novoStatus]);

        return $ok ? $novoStatus : null;
    }

    /**
     * Exclui um usuário permanentemente.
     */
    public function deleteUser(int $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException $e) {
            $this->log->arquive($e, [
                'userWhoSent' => $_SESSION['logado']->login ?? 'sistema',
                'input'       => ['id' => $id],
            ]);
            return false;
        }
    }

    /**
     * Atualiza o campo ultimo_login do usuário.
     */
    public function updateLastLogin(string $login): void {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE login = :login");
        $stmt->execute([':login' => $login]);
    }
}