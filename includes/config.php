<?php
// /includes/config.php
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     'root');
define('DB_NAME',     'TechParts2');
define('APP_NAME',    'TechParts POS');
define('APP_VERSION', '2.0.0');

// Database class
class Database {
    private $host     = "localhost";
    private $db_name  = "TechParts2";
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
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
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

// Create PDO connection (backward compatibility wrapper)
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    return $pdo;
}

// Session helper
function requireLogin(array $allowedRoles = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        header('Location: /unauthorized.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['role']      ?? 'Viewer',
    ];
}
