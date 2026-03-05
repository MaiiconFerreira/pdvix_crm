<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\UserModel;
use App\Models\LogsModel;
use App\Services\RedisService;

class AuthController extends Controller {

    private LogsModel   $logsModel;
    private RedisService $redis;

    public function __construct() {
        $this->logsModel = new LogsModel();
        $this->redis     = new RedisService();
    }

    // ── Exibe a tela de login ─────────────────────────────────────────────────
    public function index(): void {
        if ($this->existsSession()) {
            header('Location: /');
            exit;
        }
        $this->view('login', []);
    }

    // ── Processa autenticação — POST /auth ────────────────────────────────────
    public function authentication(): void {

    
        header('Content-type: application/json');

        if ($this->existsSession()) {
            $this->responseJson('error', [], 'Usuário já está logado!');
        }

        // Campos enviados pelo login.php: login, password, uuid_v4
        $validado = $this->validarInputs(['login', 'password', 'uuid_v4']);

        $login   = $validado['login'];
        $senha   = $validado['password'];
        $userIP  = $this->getUserIP();

        // ── Rate limiting ─────────────────────────────────────────────────────
        $maxLoginAttempts = 5;
        $maxIpAttempts    = 20;
        $lockTime         = 900; // 15 min

        $loginKey = 'login_err:' . $login;
        $ipKey    = 'login_err_ip:' . $userIP;

        $ipAttempts = (int) $this->redis->get($ipKey);
        if ($ipAttempts >= $maxIpAttempts) {
            $this->responseJson('error', [], 'Muitas tentativas vindas desta rede. Tente novamente mais tarde.');
        }

        $loginAttempts = (int) $this->redis->get($loginKey);
        if ($loginAttempts >= $maxLoginAttempts) {
            $remaining = ceil($this->redis->ttl($loginKey) / 60);
            $this->responseJson('error', [], "Conta bloqueada temporariamente. Tente em {$remaining} min.");
        }

        // ── Busca usuário pelo campo 'login' ──────────────────────────────────
        $userModel    = new UserModel();
        $userSelected = $userModel->findByLogin($login, ['id', 'login', 'nome', 'perfil', 'status']);

        if (is_null($userSelected)) {
            $this->registrarFalha($loginKey, $ipKey, $lockTime);
            $this->responseJson('error', [], 'Credenciais inválidas.');
        }

        // ── Compara senha usando hash da coluna 'password' ────────────────────
        $passHash = $userModel->getPasswordHash($login);
        if (!$this->compararSenha($senha, $passHash)) {
            $this->registrarFalha($loginKey, $ipKey, $lockTime);
            $this->responseJson('error', [], 'Credenciais inválidas.');
        }

        // ── Verifica status (enum: ativado | desativado) ──────────────────────
        if ($userSelected->status === 'desativado') {
            $this->responseJson('error', [], 'Usuário desativado! Contate o administrador.');
        }

        // ── Sucesso ───────────────────────────────────────────────────────────
        $this->redis->del($loginKey);
        $this->redis->del($ipKey);

        $userModel->updateLastLogin($login);

        $this->finalizarLogin($userSelected);
    }

    // ── Logout ────────────────────────────────────────────────────────────────
    public function logout(): void {
        session_start();

        $is_api = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || (isset($_GET['origin']) && $_GET['origin'] === 'app');

        session_unset();
        session_destroy();

        if ($is_api) {
            header('Content-type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Sessão encerrada.']);
            exit;
        }

        header('Location: /');
        exit;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function registrarFalha(string $loginKey, string $ipKey, int $ttl): void {
        $c = $this->redis->incr($loginKey);
        if ($c === 1) $this->redis->expire($loginKey, $ttl);

        $i = $this->redis->incr($ipKey);
        if ($i === 1) $this->redis->expire($ipKey, $ttl);
    }

    private function finalizarLogin(object $user): void {
        $this->logsModel->newLog([
            'usuario'        => $user->login,
            'tipo_atividade' => 'login',
            'log'            => 'Efetuou login no sistema.',
            'ip'             => $this->getUserIP(),
        ]);

        session_regenerate_id(true);
        $_SESSION['logado']        = $user;
        $_SESSION['fingerprint']   = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . '|' . $this->getUserIP());
        $_SESSION['last_activity'] = time();

        $this->responseJson('success', ['user' => $user], 'Autenticação efetuada com sucesso!');
    }
}