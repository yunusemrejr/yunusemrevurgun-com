<?php
require_once __DIR__ . '/../config/setPath.php';

 
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $config = require __DIR__ . '/../config/database.php';
            
            if (!$config) {
                throw new Exception("Database configuration not found");
            }

            $driver = $config['driver'] ?? 'mysql';

            if ($driver === 'sqlite') {
                $dsn = "sqlite:" . $config['dbname'];
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                // SQLite doesn't need username/password
                $this->connection = new PDO($dsn, null, null, $options);
            } else {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    $config['host'],
                    $config['port'] ?? 3306,
                    $config['dbname']
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ];
                
                // MySQL needs username and password
                $this->connection = new PDO(
                    $dsn,
                    $config['username'] ?? '',
                    $config['password'] ?? '',
                    $options
                );
            }
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            if (getenv('MODE') === 'development') {
                header('Content-Type: text/plain');
                exit("Connection failed: " . $e->getMessage());
            } else {
                header('Content-Type: text/html; charset=utf-8');
                exit('<!DOCTYPE html><html lang="en"><head><title>Error</title></head><body><h1>Service Unavailable</h1><p>The application is temporarily unavailable. Please try again later.</p></body></html>');
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
