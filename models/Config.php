<?php
class Config {
    private $conn;
    private $table = 'system_config';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get config value by key
    public function get($key) {
        $query = 'SELECT config_value FROM ' . $this->table . ' WHERE config_key = :key';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['config_value'] : null;
    }

    // Set config value
    public function set($key, $value, $user_id = null) {
        $query = 'INSERT INTO ' . $this->table . ' 
                 (config_key, config_value, updated_by) 
                 VALUES (:key, :value, :user_id)
                 ON CONFLICT (config_key) 
                 DO UPDATE SET config_value = EXCLUDED.config_value, 
                               updated_by = EXCLUDED.updated_by,
                               updated_at = NOW()';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }

    // Get all configuration
    public function getAll() {
        $query = 'SELECT config_key, config_value, config_type, description FROM ' . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['config_key']] = $row;
        }
        return $result;
    }
}
?>
