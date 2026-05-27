<?php
// /includes/config.php
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     'root');
define('DB_NAME',     'TechParts2');
define('APP_NAME',    'TechParts POS');
define('APP_VERSION', '2.0.0');
date_default_timezone_set('Asia/Manila');

// Database class
class Database
{
    private $host= "localhost";
    private $db_name  = "TechParts2";
    private $username = "root";
    private $password = "root";

    public $conn;

    public function getConnection()
    {
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
            http_response_code(503);
            require __DIR__ . '/../database_recovery.php';
            exit;
        }

        return $this->conn;
    }
}

// Create PDO connection (backward compatibility wrapper)
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $database = new Database();
        $pdo = $database->getConnection();
        ensureProductVisibilityColumn($pdo);
    }
    return $pdo;
}

function ensureProductVisibilityColumn(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM Product LIKE 'IsActive'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE Product ADD COLUMN IsActive TINYINT(1) DEFAULT 1 AFTER Category_ID');
    }

    $checked = true;
}

// Session helper
function requireLogin(array $allowedRoles = []): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: /pages/login.php');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        header('Location: /pages/unauthorized.php');
        exit;
    }
}

function currentUser(): array
{
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['role']      ?? 'Viewer',
    ];
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
}

function setFlash(string $type, string $message, string $title = ''): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title,
    ];
}

function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['flash_message'])) {
        return null;
    }
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $flash;
}
