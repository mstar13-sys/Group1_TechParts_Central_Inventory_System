<?php
class Database {
    private $host     = "localhost";
    private $db_name  = "TechParts";
    private $username = "root";        
    private $password = "root"; 
    
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,               PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,               PDO::ATTR_EMULATE_PREPARES   => false,  
                    PDO::ATTR_PERSISTENT         => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact administrator.");
        }

        return $this->conn;
    }
}

?>