<?php
namespace App\Core;

use App\Models\UserModel;
use App\Core\Database;

/**
 * Controller base — PDVix
 *
 * Centraliza:
 *  - Gestão de sessão PHP (painel admin)
 *  - Validação de token PDV (Electron)
 *  - requirePerfil() — elimina duplicação nos controllers filhos
 *  - Helpers de entrada/saída HTTP
 */
class Controller
{
    // ── Sessão ────────────────────────────────────────────────────────────────

    public function existsSession(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logado'], $_SESSION['fingerprint'], $_SESSION['last_activity'])) {
            return false;
        }

        $currentFingerprint = hash('sha256',
            $_SERVER['HTTP_USER_AGENT'] . '|' . $this->getUserIP()
        );

        if (!hash_equals($_SESSION['fingerprint'], $currentFingerprint)) {
            session_unset();
            session_destroy();
            return false;
        }

        if (time() - $_SESSION['last_activity'] > 3600) {
            session_unset();
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    protected function ensureSession(): void
    {
        if (!$this->existsSession()) {
            $this->responseJson('error', [], 'Sessão inválida ou expirada!', 403);
        }

        $userModel = new UserModel();
        $userData  = $userModel->findById((int) $_SESSION['logado']->id, ['id', 'status']);

        if (!$userData || $userData->status === 'desativado') {
            session_unset();
            session_destroy();
            $this->responseJson('error', [], 'Usuário desativado ou inexistente.', 403);
        }
    }

    public function requireLoginRedirect(): void
    {
        if (!$this->existsSession()) {
            header('Location: /login');
            exit;
        }
    }

    // ── Autorização por perfil (centralizado — sem duplicar nos controllers) ──

    /**
     * Encerra a requisição com 403 se o perfil do usuário logado não for permitido.
     * Deve ser chamado após ensureSession().
     *
     * @param string[] $perfisPermitidos Ex: ['administrador', 'gerente']
     */
    protected function requirePerfil(array $perfisPermitidos): void
    {
        $perfilLogado = $_SESSION['logado']->perfil ?? '';
        if (!in_array($perfilLogado, $perfisPermitidos, true)) {
            $this->responseJson('error', [], 'Sem permissão para executar esta ação.', 403);
        }
    }

    // ── Token PDV (Electron) ──────────────────────────────────────────────────

    /**
     * Valida o token estático do PDV contra config.api_token.
     * Encerra com 401 se inválido.
     * Deve ser chamado nos endpoints que usam auth por token (sem sessão PHP).
     */
    protected function validarTokenPdv(): void
    {
        $token = trim($_GET['token'] ?? '');

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row || empty($row->valor) || !hash_equals($row->valor, $token)) {
            $this->responseJson('error', [], 'Token inválido ou ausente.', 401);
        }
    }

    // ── Contexto de loja do usuário logado ────────────────────────────────────

    /**
     * Retorna o loja_id da sessão (quando multi-loja estiver ativo).
     * Administradores podem passar ?loja_id=X para filtrar uma loja específica.
     * Gerentes e operadores ficam restritos à sua própria loja.
     *
     * Retorna null enquanto a coluna loja_id ainda não existir (pré-migration).
     */
    protected function getLojaIdSessao(): ?int
    {
        if (!isset($_SESSION['logado'])) {
            return null;
        }

        $user  = $_SESSION['logado'];
        $perfil = $user->perfil ?? '';

        // Admins podem filtrar qualquer loja via query param
        if ($perfil === 'administrador' && !empty($_GET['loja_id'])) {
            return (int) $_GET['loja_id'];
        }

        // Usuário tem loja vinculada na sessão (após migration v3)
        if (!empty($user->loja_id)) {
            return (int) $user->loja_id;
        }

        return null;
    }

    // ── CORS / Headers ────────────────────────────────────────────────────────

    protected function jsonHeader(): void
    {
        header('Content-type: application/json');

        if (!isset($_SERVER['HTTP_ORIGIN'])) {
            $_SERVER['HTTP_ORIGIN'] = '';
        }

        if (in_array($_SERVER['HTTP_ORIGIN'], $GLOBALS['CORS_ALLOW_DOMAIN'] ?? [])) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    public function view(string $view, array $data = []): void
    {
        extract($data);
        require "../App/Views/{$view}.php";
    }

    // ── Entrada ───────────────────────────────────────────────────────────────

    protected function getRequestData(array $requiredFields, array $optionalFields = [], $callback = null, $data_callback = null): array
    {
        $data      = $this->isRequestType($_SERVER['CONTENT_TYPE'] ?? '');
        $validated = $this->validarInputs($requiredFields, $data, $callback, $data_callback);

        foreach ($optionalFields as $field) {
            $validated[$field] = $data[$field] ?? null;
        }

        return $validated;
    }

    protected function validarInputs(array $campos, $INPUT = null, $callback = null, $data_callback = null): array
    {
        if (is_null($INPUT)) {
            $INPUT = $_POST;
        }

        $dados = [];

        foreach ($campos as $campo) {
            if (!isset($INPUT[$campo]) || $INPUT[$campo] === '' || $INPUT[$campo] === null) {
                if (is_null($callback)) {
                    $this->responseJson('error', null, "Campo obrigatório: $campo", 400);
                } else {
                    $callback($campo, $INPUT, $data_callback);
                }
            }

            $valor = $INPUT[$campo];

            if (is_array($valor)) {
                $dados[$campo] = $valor;
            } else {
                $valor = trim($valor);
                $valor = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
                $dados[$campo] = $valor;
            }
        }

        return $dados;
    }

    protected function validarInputsGet(array $requiredFields, array $optionalFields = []): array
    {
        $dados = [];

        foreach ($requiredFields as $campo) {
            if (!isset($_GET[$campo]) || trim($_GET[$campo]) === '') {
                $this->responseJson('error', null, "Campo obrigatório: $campo", 400);
            }
            $dados[$campo] = htmlspecialchars(trim($_GET[$campo]), ENT_QUOTES, 'UTF-8');
        }

        foreach ($optionalFields as $field) {
            $dados[$field] = $_GET[$field] ?? null;
        }

        return $dados;
    }

    // ── Saída JSON ────────────────────────────────────────────────────────────

    protected function responseJson(string $status, $data = null, string $mensagem = '', int $httpCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($httpCode);
        echo json_encode([
            'status'  => $status,
            'data'    => $data,
            'message' => $mensagem,
        ]);
        exit;
    }

    // ── Utilitários ───────────────────────────────────────────────────────────

    public function getUserIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    protected function ensureMethod(string $method): void
    {
        if (!$this->isRequestMethod($method)) {
            $this->responseJson('error', [], 'Método não aceito!', 405);
        }
    }

    protected function compararSenha(string $senhaExterna, string $senhaInterna): bool
    {
        return password_verify($senhaExterna, $senhaInterna);
    }

    protected function isRequestType(string $contentType): array
    {
        if (stripos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_POST;
    }

    protected function isRequestMethod(string $type): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($type);
    }

    public function timeAgo(string $datetime, bool $full = false): string
    {
        $now  = new \DateTime();
        $ago  = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w  = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'ano', 'm' => 'mês', 'w' => 'semana',
            'd' => 'dia', 'h' => 'hora', 'i' => 'minuto', 's' => 'segundo',
        ];

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? 'há ' . implode(', ', $string) : 'agora mesmo';
    }

    public function isBase64(string $string): bool
    {
        if (preg_match('/^data:\w+\/[a-zA-Z+\-.]+;base64,/', $string)) {
            $string = substr($string, strpos($string, ',') + 1);
        }
        return base64_encode(base64_decode($string, true)) === $string;
    }
}
