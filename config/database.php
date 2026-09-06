<?php
/**
 * Database Configuration — PDO Connection
 * Uses constants for credentials. In production use .env
 */

// ── Database credentials ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'myfinance');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = null;
        $socketPaths = [
            '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
            '/tmp/mysql.sock',
            '/var/mysql/mysql.sock',
            '/var/run/mysqld/mysqld.sock'
        ];
        foreach ($socketPaths as $sp) {
            if (file_exists($sp)) {
                $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $sp, DB_NAME, DB_CHARSET);
                break;
            }
        }
        if (!$dsn) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        }
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection error. Please check configuration.');
        }
    }
    
    return $pdo;
}