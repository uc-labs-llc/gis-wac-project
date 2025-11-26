<?php
class ArchiveSettings {
    private $conn;
    private $table = 'weather_archive_settings';

    public $setting_id;
    public $location_id;
    public $archive_frequency;
    public $realtime_keep_hours;
    public $hourly_keep_days;
    public $daily_keep_years;
    public $weekly_keep_years;
    public $monthly_keep_years;
    public $yearly_keep_years;
    public $enabled;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get settings by location ID
    public function get_by_location($location_id) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE location_id = :location_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all settings with location info
    public function get_all_with_locations() {
        $query = '
            SELECT s.*, l.location_name, l.city, l.state_province 
            FROM ' . $this->table . ' s
            JOIN locations l ON s.location_id = l.location_id
            WHERE l.status = \'ACTIVE\'
            ORDER BY l.location_name
        ';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }

    // Create or update settings
    public function save() {
        // Check if settings already exist for this location
        $existing = $this->get_by_location($this->location_id);
        
        if ($existing) {
            return $this->update();
        } else {
            return $this->create();
        }
    }

    // Create new settings
    public function create() {
        $query = 'INSERT INTO ' . $this->table . ' 
                 (location_id, archive_frequency, realtime_keep_hours, hourly_keep_days, 
                  daily_keep_years, weekly_keep_years, monthly_keep_years, yearly_keep_years, enabled) 
                 VALUES (:location_id, :archive_frequency, :realtime_keep_hours, :hourly_keep_days,
                         :daily_keep_years, :weekly_keep_years, :monthly_keep_years, :yearly_keep_years, :enabled)';
        
        $stmt = $this->conn->prepare($query);
        
        $this->bind_params($stmt);

        if ($stmt->execute()) {
            return true;
        }
        
        error_log("ArchiveSettings create error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Update existing settings
    public function update() {
        $query = 'UPDATE ' . $this->table . ' 
                 SET archive_frequency = :archive_frequency,
                     realtime_keep_hours = :realtime_keep_hours,
                     hourly_keep_days = :hourly_keep_days,
                     daily_keep_years = :daily_keep_years,
                     weekly_keep_years = :weekly_keep_years,
                     monthly_keep_years = :monthly_keep_years,
                     yearly_keep_years = :yearly_keep_years,
                     enabled = :enabled,
                     updated_at = NOW()
                 WHERE location_id = :location_id';
        
        $stmt = $this->conn->prepare($query);
        
        $this->bind_params($stmt);

        if ($stmt->execute()) {
            return true;
        }
        
        error_log("ArchiveSettings update error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Helper method to bind parameters
    private function bind_params($stmt) {
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':archive_frequency', $this->archive_frequency);
        $stmt->bindParam(':realtime_keep_hours', $this->realtime_keep_hours);
        $stmt->bindParam(':hourly_keep_days', $this->hourly_keep_days);
        $stmt->bindParam(':daily_keep_years', $this->daily_keep_years);
        $stmt->bindParam(':weekly_keep_years', $this->weekly_keep_years);
        $stmt->bindParam(':monthly_keep_years', $this->monthly_keep_years);
        $stmt->bindParam(':yearly_keep_years', $this->yearly_keep_years);
        $stmt->bindParam(':enabled', $this->enabled);
    }

    // Get default settings
    public static function get_defaults() {
        return [
            'archive_frequency' => 'realtime',
            'realtime_keep_hours' => 24,
            'hourly_keep_days' => 30,
            'daily_keep_years' => 2,
            'weekly_keep_years' => 5,
            'monthly_keep_years' => 10,
            'yearly_keep_years' => 20,
            'enabled' => true
        ];
    }

    // Delete settings for a location
    public function delete_by_location($location_id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE location_id = :location_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        return $stmt->execute();
    }
}
?>
