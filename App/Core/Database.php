<?php
namespace App\Core;
use App\Services\Log;

class Database {
   private static $pdo;
   protected Log $log;

   public function __construct(){
     $this->log = new Log();
   }

   public static function getConnection() {
       if (!self::$pdo) {
            self::$pdo = new \PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
           self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
       }
       return self::$pdo;
   }
   public static function validarDataBanco($data) {
     // Verifica se está no formato YYYY-MM-DD
      if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $data)) {
            // Quebra a data em partes
            list($ano, $mes, $dia) = explode('-', $data);

            // Verifica se é uma data válida no calendário
            return checkdate((int)$mes, (int)$dia, (int)$ano);
        }
        return false;
    }

}
