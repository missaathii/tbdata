<?php
/**
 * Database Configuration
 * Uses PDO MySQL with utf8mb4 charset for Indian language & character support
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $configFile = __DIR__ . '/db_config.php';
            $fileConfig = file_exists($configFile) ? require($configFile) : [];

            $host = getenv('DB_HOST') ?: ($fileConfig['host'] ?? 'sql203.infinityfree.com');
            $port = getenv('DB_PORT') ?: ($fileConfig['port'] ?? '3306');
            $dbName = getenv('DB_NAME') ?: ($fileConfig['dbname'] ?? 'if0_42821989_tbdata_db');
            $user = getenv('DB_USER') ?: ($fileConfig['username'] ?? 'if0_42821989');
            $pass = (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') ? getenv('DB_PASS') : ($fileConfig['password'] ?? 'Xn0Op7V3kF1U');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If tbdata_db doesn't exist yet, return helpful JSON response if API context
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}
