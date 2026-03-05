<?php
namespace App\Models;
use App\Core\Database;

class LogsModel extends Database {
	private $pdo;

	public function __construct() {
			$this->pdo = Database::getConnection();
			parent::__construct(); // Garante inicialização do construtor por causa de alguns serviços da classe principal
	}

	public function newLog(array $array) {
	    // Adiciona o campo 'data' manualmente (formato compatível com DATE)
	    $array['data'] = date('Y-m-d'); // ou 'Y-m-d H:i:s' se o campo for DATETIME

	    $sql = "INSERT INTO historico
	        (usuario, tipo_atividade, log, ip, data)
	        VALUES (:usuario, :tipo_atividade, :log, :ip, :data)";

	    try {
	        $stmt = $this->pdo->prepare($sql);

	        if ($stmt->execute($array)) {
	            return true;
	        } else {
	            return false;
	        }
	    } catch (\PDOException $e) {
	        $this->log->arquive($e, [
	            "userWhoSent" => '',
	            "input" => $array
	        ]);

	        return false;
	    }
	}
}
