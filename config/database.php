<?php
// Databázová konfigurácia pre Forpsi hosting
define('DB_HOST', 'a066um.forpsi.com');
define('DB_NAME', 'f189968');
define('DB_USER', 'f189968');
define('DB_PASS', 'sMP7keK9');
define('DB_CHARSET', 'utf8mb4');

// PDO pripojenie k databáze
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ]);
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Chyba pripojenia k databáze: " . $e->getMessage());
        }
    }
    
    // Prevent serialization of PDO connection
    public function __sleep() {
        throw new Exception('Serialization of Database object is not allowed');
    }
    
    public function __wakeup() {
        throw new Exception('Deserialization of Database object is not allowed');
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
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute($params);
            
            // Return last insert ID for INSERT queries
            if (stripos(trim($sql), 'INSERT') === 0) {
                return $this->connection->lastInsertId();
            }
            
            // Return affected rows for UPDATE/DELETE
            if (stripos(trim($sql), 'UPDATE') === 0 || stripos(trim($sql), 'DELETE') === 0) {
                return $stmt->rowCount();
            }
            
            // Return true for other successful queries
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception("Chyba databázy: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            $stmt->closeCursor();
            return $result;
        } catch (Exception $e) {
            error_log("Database fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            $stmt->closeCursor();
            return $result;
        } catch (Exception $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            return [];
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
}

// Globálna funkcis pre databázu
function db() {
    return Database::getInstance();
}
?>