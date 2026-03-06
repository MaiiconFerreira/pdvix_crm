<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\UserModel;

/**
 * UserController
 *
 * Rotas mapeadas em routes.php:
 *   GET    /api/users         → index()        listar (DataTables server-side)
 *   POST   /api/users         → index()        criar
 *   PUT    /api/users         → index()        atualizar
 *   DELETE /api/users         → index()        excluir
 *   PATCH  /api/users/status  → toggleStatus() ativar / desativar
 */
class UserController extends Controller {

    private UserModel $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    // =========================================================================
    // DISPATCHER — um endpoint, múltiplos verbos
    // =========================================================================

    public function index(): void {
        $this->jsonHeader();
        $this->ensureSession();

        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        match ($method) {
            'GET'    => $this->listUsers(),
            'POST'   => $this->createUser(),
            'PUT'    => $this->updateUser(),
            'DELETE' => $this->deleteUser(),
            default  => $this->responseJson('error', [], 'Método não suportado.', 405),
        };
    }

    // =========================================================================
    // GET /api/users
    // =========================================================================

    private function listUsers(): void {
        $this->requirePerfil(['administrador', 'gerente']);

        $result = $this->userModel->listUsers($_GET);

        $this->responseJson('success', $result, 'Listagem executada com sucesso.');
    }

    // =========================================================================
    // POST /api/users
    // =========================================================================

    private function createUser(): void {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['nome', 'cpf', 'login', 'senha', 'perfil']);

        // Validações
        if (!$this->userModel->isPerfilValido($data['perfil'])) {
            $this->responseJson('error', [], 'Perfil inválido. Use: operador, gerente ou administrador.');
        }

        if (!preg_match('/^\d{11}$/', $data['cpf'])) {
            $this->responseJson('error', [], 'CPF inválido. Informe 11 dígitos numéricos.');
        }

        if ($this->userModel->existUser('cpf', $data['cpf'])) {
            $this->responseJson('error', [], 'CPF já cadastrado.');
        }

        if ($this->userModel->existUser('login', $data['login'])) {
            $this->responseJson('error', [], 'Login já em uso.');
        }

        $ok = $this->userModel->createUser([
            ':login'      => trim($data['login']),
            ':password'   => password_hash($data['senha'], PASSWORD_BCRYPT),
            ':perfil'     => $data['perfil'],
            ':nome'       => trim($data['nome']),
            ':cpf'        => $data['cpf'],
            ':email'      => $data['email']    ?? '',
            ':telefone'   => $data['telefone'] ?? '',
            ':criado_por' => $_SESSION['logado']->id,
        ]);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Usuário criado com sucesso!' : 'Erro ao criar usuário.'
        );
    }

    // =========================================================================
    // PUT /api/users
    // =========================================================================

    private function updateUser(): void {
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['id']);

        $id = (int) $data['id'];

        if ($id <= 0 || !$this->userModel->findById($id)) {
            $this->responseJson('error', [], 'Usuário não encontrado.');
        }

        $campos = [];

        // Campos opcionais — só atualiza o que foi enviado
        $opcionais = ['nome', 'login', 'cpf', 'email', 'telefone', 'perfil'];
        foreach ($opcionais as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== '') {
                $campos[$campo] = trim($data[$campo]);
            }
        }

        // Valida perfil se foi enviado
        if (isset($campos['perfil']) && !$this->userModel->isPerfilValido($campos['perfil'])) {
            $this->responseJson('error', [], 'Perfil inválido. Use: operador, gerente ou administrador.');
        }

        // Troca de senha (opcional)
        if (!empty($data['senha'])) {
            $campos['password'] = password_hash($data['senha'], PASSWORD_BCRYPT);
        }

        if (empty($campos)) {
            $this->responseJson('error', [], 'Nenhum dado para atualizar.');
        }

        $ok = $this->userModel->updateUser($id, $campos);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Usuário atualizado com sucesso!' : 'Erro ao atualizar usuário.'
        );
    }

    // =========================================================================
    // DELETE /api/users
    // =========================================================================

    private function deleteUser(): void {
        $this->requirePerfil(['administrador']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if ($id <= 0 || !$this->userModel->findById($id)) {
            $this->responseJson('error', [], 'Usuário não encontrado.');
        }

        // Impede auto-exclusão
        if ($id === (int) $_SESSION['logado']->id) {
            $this->responseJson('error', [], 'Você não pode excluir sua própria conta.');
        }

        $ok = $this->userModel->deleteUser($id);

        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Usuário excluído com sucesso!' : 'Erro ao excluir usuário.'
        );
    }

    // =========================================================================
    // PATCH /api/users/status
    // =========================================================================

    public function toggleStatus(): void {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('PATCH');
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['id']);
        $id   = (int) $data['id'];

        if ($id <= 0 || !$this->userModel->findById($id)) {
            $this->responseJson('error', [], 'Usuário não encontrado.');
        }

        $novoStatus = $this->userModel->toggleStatus($id);

        if ($novoStatus === null) {
            $this->responseJson('error', [], 'Erro ao alterar status do usuário.');
        }

        $label = $novoStatus === 'ativado' ? 'ativado' : 'desativado';
        $this->responseJson('success', ['status' => $novoStatus], "Usuário {$label} com sucesso!");
    }

    }