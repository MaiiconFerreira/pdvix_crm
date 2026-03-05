<?php
namespace App\Core;

use App\Models\UserModel;

class Controller {

    // ── Sessão ────────────────────────────────────────────────────────────────

    public function existsSession(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logado'], $_SESSION['fingerprint'], $_SESSION['last_activity'])) {
            return false;
        }

        // Valida fingerprint (UA + IP)
        $currentFingerprint = hash('sha256',
            $_SERVER['HTTP_USER_AGENT'] . '|' . $this->getUserIP()
        );

        if (!hash_equals($_SESSION['fingerprint'], $currentFingerprint)) {
            session_unset();
            session_destroy();
            return false;
        }

        // Timeout de 60 minutos
        if (time() - $_SESSION['last_activity'] > 3600) {
            session_unset();
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    protected function ensureSession(): void {
        if (!$this->existsSession()) {
            $this->responseJson('error', [], 'Sessão inválida ou expirada!', 403);
        }

        // Verifica se o usuário ainda está ativo no banco
        $userModel = new UserModel();
        $userData  = $userModel->findById((int) $_SESSION['logado']->id, ['id', 'status']);

        if (!$userData || $userData->status === 'desativado') {
            session_unset();
            session_destroy();
            $this->responseJson('error', [], 'Usuário desativado ou inexistente.', 403);
        }
    }

    public function requireLoginRedirect(): void {
        if (!$this->existsSession()) {
            header('Location: /login');
            exit;
        }
    }

    // ── CORS / Headers ────────────────────────────────────────────────────────

    protected function jsonHeader(): void {
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

    public function view(string $view, array $data = []): void {
        extract($data);
        require "../App/Views/{$view}.php";
    }

    // ── Entrada ───────────────────────────────────────────────────────────────

    protected function getRequestData(array $requiredFields, array $optionalFields = [], $callback = null, $data_callback = null): array {
        $data      = $this->isRequestType($_SERVER['CONTENT_TYPE'] ?? '');
        $validated = $this->validarInputs($requiredFields, $data, $callback, $data_callback);

        foreach ($optionalFields as $field) {
            $validated[$field] = $data[$field] ?? null;
        }

        return $validated;
    }

    protected function validarInputs(array $campos, $INPUT = null, $callback = null, $data_callback = null): array {
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

    protected function validarInputsGet(array $requiredFields, array $optionalFields = []): array {
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

    protected function responseJson(string $status, $data = null, string $mensagem = '', int $httpCode = 200): void {
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

    public function getUserIP(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    protected function ensureMethod(string $method): void {
        if (!$this->isRequestMethod($method)) {
            $this->responseJson('error', [], 'Método não aceito!', 405);
        }
    }

    protected function compararSenha(string $senhaExterna, string $senhaInterna): bool {
        return password_verify($senhaExterna, $senhaInterna);
    }

    protected function isRequestType(string $contentType): array {
        if (stripos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_POST;
    }

    protected function isRequestMethod(string $type): bool {
        return strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($type);
    }

    public function timeAgo(string $datetime, bool $full = false): string {
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

    public function isBase64(string $string): bool {
        if (preg_match('/^data:\w+\/[a-zA-Z+\-.]+;base64,/', $string)) {
            $string = substr($string, strpos($string, ',') + 1);
        }
        return base64_encode(base64_decode($string, true)) === $string;
    }
}