<?php
// includes/config.php

// ── App root: works whether the app lives at web-root OR in a sub-folder ──────
// e.g.  http://localhost/             → APP_ROOT = ''
//       http://localhost/techparts/   → APP_ROOT = '/techparts'
// Every header('Location:') and every href= in PHP must prefix with APP_ROOT.
// Static assets referenced from HTML (CSS/JS) use ROOT_URL for the same reason.
if (!defined('APP_ROOT')) {
    // __DIR__ is  …/includes   so we go one level up to get the project root
    $scriptDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    $docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
    $projectDir = str_replace('\\', '/', dirname(__DIR__)); // one up from includes/

    // Derive the URL prefix from filesystem paths
    $webRoot = '/' . ltrim(str_replace($docRoot, '', $projectDir), '/');
    $webRoot = rtrim($webRoot, '/');               // never a trailing slash
    define('APP_ROOT', $webRoot);                  // e.g. '' or '/techparts'
}

// ── Database credentials ───────────────────────────────────────────────────────
// database.sql creates a DB called TechParts; change here if your DB is different
define('DB_HOST', 'localhost');
define('DB_NAME', 'TechParts');   // was 'TechParts2' — must match database.sql
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('APP_NAME',    'TechParts POS');
define('APP_VERSION', '2.0.0');

// ── Database class ─────────────────────────────────────────────────────────────
class Database {
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    public  ?PDO   $conn = null;

    public function __construct() {
        $this->host     = DB_HOST;
        $this->db_name  = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) return $this->conn;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please contact the administrator.');
        }
        return $this->conn;
    }
}

// ── Singleton PDO wrapper ──────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
}

// ── Session / auth helpers ─────────────────────────────────────────────────────
function requireLogin(array $allowedRoles = []): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_ROOT . '/login.php');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        header('Location: ' . APP_ROOT . '/unauthorized.php');
        exit;
    }
}

function currentUser(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'Guest',  // login.php must set 'user_name'
        'role' => $_SESSION['role']      ?? 'Viewer',
    ];
}
