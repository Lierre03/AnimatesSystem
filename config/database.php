<?php
// Database configuration for XAMPP
class Database {
    private $host = 'localhost';
    private $db_name = '8paws_db';
    private $username = 'root';
    private $password = ''; // XAMPP default password is empty
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch(PDOException $exception) {
            throw new Exception('DB connection failed: ' . $exception->getMessage());
        }
        
        return $this->conn;
    }
}

// Helper function to get database connection
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Note: Do not send headers or output from database config
?>