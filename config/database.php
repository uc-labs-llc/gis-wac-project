<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'gis_wac_db';
    private $username = 'gis_wac_user';
    private $password = '12345';
    private $conn;

    public function connect() {
        $this->conn = null;
        
        try {
            $dsn = "pgsql:host=" . $this->host . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            // Don't expose database details to users
            throw new Exception("Database connection failed. Please try again later.");
        }
        
        return $this->conn;
    }

    public function getConnection() {
        return $this->connect();
    }
}
?>
