<?php
namespace App\Services;

class MysqlErrorCatalog {
    public static array $errors = [
        1062 => "Entrada duplicada (violação de chave única)",
        1451 => "Restrição de chave estrangeira: não é possível excluir porque há registros filhos",
        1452 => "Chave estrangeira inválida: registro pai não existe",
        1049 => "Banco de dados não encontrado",
        1146 => "Tabela não existe",
        1054 => "Coluna desconhecida",
        1064 => "Erro de sintaxe na instrução SQL",
        1364 => "Campo obrigatório sem valor",
        93 => "Número de argumentos passados não condizem com os esperados",
    ];

    public static function getDescription(int $code): string {
        return self::$errors[$code] ?? "Erro MySQL não catalogado";
    }
}

class Log {
  private $PATH;

  public function __construct($path = PATH_LOGS) {
    $this->PATH = rtrim($path, '/') . '/';

    if (!is_dir($this->PATH)) {
      mkdir($this->PATH, 0777, true);
    }
  }

  public function arquive($data, array $detail_role = [], $filename = 'error_pdo.log') {
      $date = date('Y-m-d H:i:s');
      $today = date('Y-m-d');
      $filename = pathinfo($filename, PATHINFO_FILENAME) . "_{$today}.log";

      $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
      $caller = $trace[1] ?? null;

      $function = $caller['function'] ?? 'unknown';
      $class = $caller['class'] ?? '';
      $fullCaller = $class ? "{$class}::{$function}" : $function;

      $log = "[$date] Function: {$fullCaller}\n";

      if ($data instanceof \PDOException) {
          $errorInfo = $data->errorInfo ?? null;
          $code = $errorInfo[1] ?? $data->getCode();

          $log .= "PDOException: " . get_class($data) . "\n" .
                  "Message: " . $data->getMessage() . "\n" .
                  "MySQL Code: {$code} (" . MysqlErrorCatalog::getDescription((int)$code) . ")\n" .
                  "File: " . $data->getFile() . "\n" .
                  "Line: " . $data->getLine() . "\n" .
                  "Trace: " . $data->getTraceAsString() . "\n" .
                  "JSON System: " . json_encode($detail_role, JSON_PRETTY_PRINT) . "\n\n\n";
      } elseif ($data instanceof \Throwable) {
          $log .= "Exception: " . get_class($data) . "\n" .
                  "Message: " . $data->getMessage() . "\n" .
                  "File: " . $data->getFile() . "\n" .
                  "Line: " . $data->getLine() . "\n" .
                  "Trace: " . $data->getTraceAsString() . "\n" .
                  "JSON System: " . json_encode($detail_role, JSON_PRETTY_PRINT) . "\n\n\n";
      } else {
          $log .= print_r($data, true) . "\n\n";
      }

      file_put_contents($this->PATH . $filename, $log, FILE_APPEND);
  }
}
