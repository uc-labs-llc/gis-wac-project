<?php
class AlertRule {
    private $conn;
    private $table = 'alert_rules';

    // Rule properties matching database schema
    public $rule_id;
    public $location_id;
    public $rule_name;
    public $rule_description;
    public $rule_category = 'WEATHER';
    public $target_metric;
    public $operator;
    public $threshold_value;
    public $threshold_value_2;
    public $severity_level = 'MEDIUM';
    public $condition_type = 'INSTANT';
    public $duration_minutes;
    public $trend_period_minutes;
    public $message_template;
    public $custom_subject;
    public $is_template_rich_text = false;
    public $cooldown_period_minutes = 60;
    public $max_alerts_per_hour = 10;
    public $throttle_enabled = true;
    public $is_active = true;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new Alert Rule
    public function create() {
        // PostgreSQL-compatible INSERT query
        $query = 'INSERT INTO ' . $this->table . ' 
                  (location_id, rule_name, rule_description, rule_category, 
                   target_metric, operator, threshold_value, threshold_value_2, 
                   severity_level, condition_type, duration_minutes, 
                   message_template, custom_subject, cooldown_period_minutes, 
                   max_alerts_per_hour, is_active) 
                  VALUES 
                  (:location_id, :rule_name, :rule_description, :rule_category, 
                   :target_metric, :operator, :threshold_value, :threshold_value_2, 
                   :severity_level, :condition_type, :duration_minutes, 
                   :message_template, :custom_subject, :cooldown_period_minutes, 
                   :max_alerts_per_hour, :is_active)';

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->rule_name = htmlspecialchars(strip_tags($this->rule_name));
        $this->rule_description = $this->rule_description ? htmlspecialchars(strip_tags($this->rule_description)) : null;
        $this->message_template = htmlspecialchars(strip_tags($this->message_template));
        $this->custom_subject = $this->custom_subject ? htmlspecialchars(strip_tags($this->custom_subject)) : null;
        
        // Convert numeric values
        $threshold_value = is_numeric($this->threshold_value) ? (float)$this->threshold_value : $this->threshold_value;
        $threshold_value_2 = $this->threshold_value_2 && is_numeric($this->threshold_value_2) ? (float)$this->threshold_value_2 : $this->threshold_value_2;
        $duration_minutes = $this->duration_minutes ? (int)$this->duration_minutes : null;
        
        // PostgreSQL boolean conversion
        $is_active = $this->is_active ? true : false;

        // Bind data with proper PostgreSQL parameter types
        $stmt->bindParam(':location_id', $this->location_id, $this->location_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':rule_name', $this->rule_name);
        $stmt->bindParam(':rule_description', $this->rule_description, $this->rule_description ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':rule_category', $this->rule_category);
        $stmt->bindParam(':target_metric', $this->target_metric);
        $stmt->bindParam(':operator', $this->operator);
        $stmt->bindParam(':threshold_value', $threshold_value);
        $stmt->bindParam(':threshold_value_2', $threshold_value_2, $threshold_value_2 ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':severity_level', $this->severity_level);
        $stmt->bindParam(':condition_type', $this->condition_type);
        $stmt->bindParam(':duration_minutes', $duration_minutes, $duration_minutes ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':message_template', $this->message_template);
        $stmt->bindParam(':custom_subject', $this->custom_subject, $this->custom_subject ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':cooldown_period_minutes', $this->cooldown_period_minutes, PDO::PARAM_INT);
        $stmt->bindParam(':max_alerts_per_hour', $this->max_alerts_per_hour, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);

        // Execute query
        if($stmt->execute()) {
            return true;
        }

        // Print error if something goes wrong
        error_log("PostgreSQL Error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Read all alert rules
    public function read() {
        $query = 'SELECT a.*, l.location_name, l.city 
                  FROM ' . $this->table . ' a
                  LEFT JOIN locations l ON a.location_id = l.location_id
                  ORDER BY a.rule_name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get alert rule by ID
    public function readOne() {
        $query = 'SELECT a.*, l.location_name, l.city 
                  FROM ' . $this->table . ' a
                  LEFT JOIN locations l ON a.location_id = l.location_id
                  WHERE a.rule_id = :rule_id';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':rule_id', $this->rule_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->location_id = $row['location_id'];
            $this->rule_name = $row['rule_name'];
            $this->rule_description = $row['rule_description'];
            $this->rule_category = $row['rule_category'];
            $this->target_metric = $row['target_metric'];
            $this->operator = $row['operator'];
            $this->threshold_value = $row['threshold_value'];
            $this->threshold_value_2 = $row['threshold_value_2'];
            $this->severity_level = $row['severity_level'];
            $this->condition_type = $row['condition_type'];
            $this->duration_minutes = $row['duration_minutes'];
            $this->message_template = $row['message_template'];
            $this->custom_subject = $row['custom_subject'];
            $this->cooldown_period_minutes = $row['cooldown_period_minutes'];
            $this->max_alerts_per_hour = $row['max_alerts_per_hour'];
            $this->is_active = $row['is_active'];
        }
        
        return $row ? true : false;
    }

    // Update alert rule
    public function update() {
        $query = 'UPDATE ' . $this->table . ' 
                  SET location_id = :location_id,
                      rule_name = :rule_name,
                      rule_description = :rule_description,
                      rule_category = :rule_category,
                      target_metric = :target_metric,
                      operator = :operator,
                      threshold_value = :threshold_value,
                      threshold_value_2 = :threshold_value_2,
                      severity_level = :severity_level,
                      condition_type = :condition_type,
                      duration_minutes = :duration_minutes,
                      message_template = :message_template,
                      custom_subject = :custom_subject,
                      cooldown_period_minutes = :cooldown_period_minutes,
                      max_alerts_per_hour = :max_alerts_per_hour,
                      is_active = :is_active,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE rule_id = :rule_id';

        $stmt = $this->conn->prepare($query);

        // Sanitize data
        $this->rule_name = htmlspecialchars(strip_tags($this->rule_name));
        $this->rule_description = $this->rule_description ? htmlspecialchars(strip_tags($this->rule_description)) : null;
        $this->message_template = htmlspecialchars(strip_tags($this->message_template));
        $this->custom_subject = $this->custom_subject ? htmlspecialchars(strip_tags($this->custom_subject)) : null;
        
        // Convert numeric values
        $threshold_value = is_numeric($this->threshold_value) ? (float)$this->threshold_value : $this->threshold_value;
        $threshold_value_2 = $this->threshold_value_2 && is_numeric($this->threshold_value_2) ? (float)$this->threshold_value_2 : $this->threshold_value_2;
        $duration_minutes = $this->duration_minutes ? (int)$this->duration_minutes : null;
        
        // PostgreSQL boolean conversion
        $is_active = $this->is_active ? true : false;

        // Bind data
        $stmt->bindParam(':location_id', $this->location_id, $this->location_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':rule_name', $this->rule_name);
        $stmt->bindParam(':rule_description', $this->rule_description, $this->rule_description ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':rule_category', $this->rule_category);
        $stmt->bindParam(':target_metric', $this->target_metric);
        $stmt->bindParam(':operator', $this->operator);
        $stmt->bindParam(':threshold_value', $threshold_value);
        $stmt->bindParam(':threshold_value_2', $threshold_value_2, $threshold_value_2 ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':severity_level', $this->severity_level);
        $stmt->bindParam(':condition_type', $this->condition_type);
        $stmt->bindParam(':duration_minutes', $duration_minutes, $duration_minutes ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':message_template', $this->message_template);
        $stmt->bindParam(':custom_subject', $this->custom_subject, $this->custom_subject ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':cooldown_period_minutes', $this->cooldown_period_minutes, PDO::PARAM_INT);
        $stmt->bindParam(':max_alerts_per_hour', $this->max_alerts_per_hour, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->bindParam(':rule_id', $this->rule_id, PDO::PARAM_INT);

        // Execute query
        if($stmt->execute()) {
            return true;
        }

        error_log("PostgreSQL Update Error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Delete alert rule
    public function delete() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE rule_id = :rule_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':rule_id', $this->rule_id, PDO::PARAM_INT);
        
        if($stmt->execute()) {
            return true;
        }
        
        error_log("PostgreSQL Delete Error: " . print_r($stmt->errorInfo(), true));
        return false;
    }
}
?>
