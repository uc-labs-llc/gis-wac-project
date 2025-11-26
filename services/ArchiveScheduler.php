<?php
class ArchiveScheduler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Process archiving for all locations or specific location
    public function process_archive($location_id = null) {
        $archiveModel = new ArchiveSettings($this->db);
        
        if ($location_id) {
            // Process specific location
            $settings = $archiveModel->get_by_location($location_id);
            if ($settings && $settings['enabled']) {
                $this->archive_location_data($settings);
            }
        } else {
            // Process all locations
            $settings_list = $archiveModel->get_all_with_locations();
            while ($settings = $settings_list->fetch(PDO::FETCH_ASSOC)) {
                if ($settings['enabled']) {
                    $this->archive_location_data($settings);
                }
            }
        }
        
        return ['success' => true, 'message' => 'Archive processing completed'];
    }
    
    // Archive data for a specific location based on settings
    private function archive_location_data($settings) {
        switch ($settings['archive_frequency']) {
            case 'realtime':
                $this->cleanup_realtime($settings);
                break;
            case 'hourly':
                $this->archive_to_hourly($settings);
                break;
            case 'daily':
                $this->archive_to_daily($settings);
                break;
            case 'weekly':
                $this->archive_to_weekly($settings);
                break;
            case 'monthly':
                $this->archive_to_monthly($settings);
                break;
            case 'yearly':
                $this->archive_to_yearly($settings);
                break;
        }
    }
    
    // Real-time: Keep all data for specified hours, delete older
    private function cleanup_realtime($settings) {
        $keep_hours = $settings['realtime_keep_hours'] ?? 24;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc < NOW() - INTERVAL '" . $keep_hours . ' hours' . "'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Hourly: Keep one record per hour, delete others
    private function archive_to_hourly($settings) {
        $keep_days = $settings['hourly_keep_days'] ?? 30;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_days . ' days' . "'
            AND history_id NOT IN (
                SELECT DISTINCT ON (DATE_TRUNC('hour', recorded_at_utc)) history_id
                FROM weather_history 
                WHERE location_id = :location_id 
                AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_days . ' days' . "'
                ORDER BY DATE_TRUNC('hour', recorded_at_utc), recorded_at_utc DESC
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Daily: Keep one record per day
    private function archive_to_daily($settings) {
        $keep_years = $settings['daily_keep_years'] ?? 2;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
            AND history_id NOT IN (
                SELECT DISTINCT ON (DATE(recorded_at_utc)) history_id
                FROM weather_history 
                WHERE location_id = :location_id 
                AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
                ORDER BY DATE(recorded_at_utc), recorded_at_utc DESC
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Weekly: Keep one record per week
    private function archive_to_weekly($settings) {
        $keep_years = $settings['weekly_keep_years'] ?? 5;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
            AND history_id NOT IN (
                SELECT DISTINCT ON (DATE_TRUNC('week', recorded_at_utc)) history_id
                FROM weather_history 
                WHERE location_id = :location_id 
                AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
                ORDER BY DATE_TRUNC('week', recorded_at_utc), recorded_at_utc DESC
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Monthly: Keep one record per month
    private function archive_to_monthly($settings) {
        $keep_years = $settings['monthly_keep_years'] ?? 10;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
            AND history_id NOT IN (
                SELECT DISTINCT ON (DATE_TRUNC('month', recorded_at_utc)) history_id
                FROM weather_history 
                WHERE location_id = :location_id 
                AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
                ORDER BY DATE_TRUNC('month', recorded_at_utc), recorded_at_utc DESC
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Yearly: Keep one record per year
    private function archive_to_yearly($settings) {
        $keep_years = $settings['yearly_keep_years'] ?? 20;
        
        $query = "
            DELETE FROM weather_history 
            WHERE location_id = :location_id 
            AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
            AND history_id NOT IN (
                SELECT DISTINCT ON (DATE_TRUNC('year', recorded_at_utc)) history_id
                FROM weather_history 
                WHERE location_id = :location_id 
                AND recorded_at_utc >= NOW() - INTERVAL '" . $keep_years . ' years' . "'
                ORDER BY DATE_TRUNC('year', recorded_at_utc), recorded_at_utc DESC
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $settings['location_id']);
        return $stmt->execute();
    }
    
    // Get archive statistics
    public function get_archive_stats($location_id = null) {
        $stats = [];
        
        if ($location_id) {
            // Stats for specific location
            $query = "
                SELECT 
                    COUNT(*) as total_records,
                    MIN(recorded_at_utc) as oldest_record,
                    MAX(recorded_at_utc) as newest_record
                FROM weather_history 
                WHERE location_id = :location_id
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Overall stats
            $query = "
                SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT location_id) as locations_with_data,
                    MIN(recorded_at_utc) as oldest_record,
                    MAX(recorded_at_utc) as newest_record
                FROM weather_history
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $stats;
    }
    
    // Get storage size information (PostgreSQL specific)
    public function get_storage_info() {
        $query = "
            SELECT 
                pg_size_pretty(pg_total_relation_size('weather_history')) as total_size,
                pg_size_pretty(pg_relation_size('weather_history')) as table_size,
                pg_size_pretty(pg_total_relation_size('weather_history') - pg_relation_size('weather_history')) as index_size
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
