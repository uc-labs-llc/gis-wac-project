<?php
class WeatherData {
    private $conn;
    private $table = 'weather_history';

    // Properties matching your database columns
    public $history_id;
    public $location_id;
    public $recorded_at_utc;
    public $recorded_at_local;
    public $data_effective_time;
    public $ingestion_timestamp;
    public $api_source;
    public $source_id;
    public $data_confidence;
    public $temp_celsius;
    public $temp_fahrenheit;
    public $feels_like_celsius;
    public $humidity_percent;
    public $pressure_hpa;
    public $pressure_inhg;
    public $wind_speed_ms;
    public $wind_speed_mph;
    public $wind_direction_degrees;
    public $wind_gust_ms;
    public $rainfall_mm_1h;
    public $rainfall_mm_3h;
    public $snowfall_mm_1h;
    public $visibility_meters;
    public $cloudiness_percent;
    public $dew_point_celsius;
    public $uv_index;
    public $solar_radiation_wm2;
    public $weather_main;
    public $weather_description;
    public $weather_icon;
    public $raw_payload;
    public $is_forecast;
    public $forecast_hours_ahead;
    public $data_quality_score;
    public $needs_verification;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get latest weather for all locations
    public function get_latest_for_all_locations() {
        $query = "
            SELECT DISTINCT ON (wh.location_id) 
                wh.*,
                l.location_name,
                l.city,
                l.state_province
            FROM " . $this->table . " wh
            JOIN locations l ON wh.location_id = l.location_id
            WHERE l.status = 'ACTIVE'
            ORDER BY wh.location_id, wh.recorded_at_utc DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get weather history for a specific location
    public function get_by_location($location_id, $limit = 100) {
        $query = "
            SELECT wh.*, l.location_name, l.city, l.state_province
            FROM " . $this->table . " wh
            JOIN locations l ON wh.location_id = l.location_id
            WHERE wh.location_id = :location_id
            ORDER BY wh.recorded_at_utc DESC
            LIMIT :limit
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Create new weather record - FULLY WORKING VERSION
    public function create() {
        $query = "
            INSERT INTO " . $this->table . " 
            (location_id, recorded_at_utc, recorded_at_local, data_effective_time,
             ingestion_timestamp, api_source, source_id, data_confidence, 
             temp_celsius, feels_like_celsius, humidity_percent, pressure_hpa,
             wind_speed_ms, wind_direction_degrees, wind_gust_ms, 
             rainfall_mm_1h, rainfall_mm_3h, snowfall_mm_1h,
             visibility_meters, cloudiness_percent, dew_point_celsius, uv_index,
             solar_radiation_wm2, weather_main, weather_description, weather_icon,
             raw_payload, is_forecast, forecast_hours_ahead, data_quality_score,
             needs_verification)
            VALUES 
            (:location_id, :recorded_at_utc, :recorded_at_local, :data_effective_time,
             :ingestion_timestamp, :api_source, :source_id, :data_confidence,
             :temp_celsius, :feels_like_celsius, :humidity_percent, :pressure_hpa,
             :wind_speed_ms, :wind_direction_degrees, :wind_gust_ms,
             :rainfall_mm_1h, :rainfall_mm_3h, :snowfall_mm_1h,
             :visibility_meters, :cloudiness_percent, :dew_point_celsius, :uv_index,
             :solar_radiation_wm2, :weather_main, :weather_description, :weather_icon,
             :raw_payload, :is_forecast, :forecast_hours_ahead, :data_quality_score,
             :needs_verification)
        ";

        $stmt = $this->conn->prepare($query);

        // Convert boolean values to proper PHP booleans
        $is_forecast_bool = (bool)($this->is_forecast ?? false);
        $needs_verification_bool = (bool)($this->needs_verification ?? false);

        // Bind all parameters with proper type handling
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':recorded_at_utc', $this->recorded_at_utc);
        $stmt->bindParam(':recorded_at_local', $this->recorded_at_local);
        $stmt->bindParam(':data_effective_time', $this->data_effective_time);
        $stmt->bindParam(':ingestion_timestamp', $this->ingestion_timestamp);
        $stmt->bindParam(':api_source', $this->api_source);
        $stmt->bindParam(':source_id', $this->source_id);
        $stmt->bindParam(':data_confidence', $this->data_confidence);
        $stmt->bindParam(':temp_celsius', $this->temp_celsius);
        $stmt->bindParam(':feels_like_celsius', $this->feels_like_celsius);
        
        // Integer parameters
        $humidity_percent = $this->humidity_percent !== null ? (int)$this->humidity_percent : null;
        $stmt->bindParam(':humidity_percent', $humidity_percent, PDO::PARAM_INT);
        
        $stmt->bindParam(':pressure_hpa', $this->pressure_hpa);
        $stmt->bindParam(':wind_speed_ms', $this->wind_speed_ms);
        
        $wind_direction_degrees = $this->wind_direction_degrees !== null ? (int)$this->wind_direction_degrees : null;
        $stmt->bindParam(':wind_direction_degrees', $wind_direction_degrees, PDO::PARAM_INT);
        
        $stmt->bindParam(':wind_gust_ms', $this->wind_gust_ms);
        $stmt->bindParam(':rainfall_mm_1h', $this->rainfall_mm_1h);
        $stmt->bindParam(':rainfall_mm_3h', $this->rainfall_mm_3h);
        $stmt->bindParam(':snowfall_mm_1h', $this->snowfall_mm_1h);
        
        $visibility_meters = $this->visibility_meters !== null ? (int)$this->visibility_meters : null;
        $stmt->bindParam(':visibility_meters', $visibility_meters, PDO::PARAM_INT);
        
        $cloudiness_percent = $this->cloudiness_percent !== null ? (int)$this->cloudiness_percent : null;
        $stmt->bindParam(':cloudiness_percent', $cloudiness_percent, PDO::PARAM_INT);
        
        $stmt->bindParam(':dew_point_celsius', $this->dew_point_celsius);
        $stmt->bindParam(':uv_index', $this->uv_index);
        $stmt->bindParam(':solar_radiation_wm2', $this->solar_radiation_wm2);
        $stmt->bindParam(':weather_main', $this->weather_main);
        $stmt->bindParam(':weather_description', $this->weather_description);
        $stmt->bindParam(':weather_icon', $this->weather_icon);
        $stmt->bindParam(':raw_payload', $this->raw_payload);
        
        // Boolean parameters - CRITICAL FIX
        $stmt->bindParam(':is_forecast', $is_forecast_bool, PDO::PARAM_BOOL);
        
        $forecast_hours_ahead = $this->forecast_hours_ahead !== null ? (int)$this->forecast_hours_ahead : null;
        $stmt->bindParam(':forecast_hours_ahead', $forecast_hours_ahead, PDO::PARAM_INT);
        
        $stmt->bindParam(':data_quality_score', $this->data_quality_score);
        $stmt->bindParam(':needs_verification', $needs_verification_bool, PDO::PARAM_BOOL);

        try {
            if ($stmt->execute()) {
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("WeatherData create execution error: " . implode(", ", $errorInfo));
                return false;
            }
        } catch (PDOException $e) {
            error_log("WeatherData create PDOException: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("WeatherData create Exception: " . $e->getMessage());
            return false;
        }
    }

    // Get dashboard statistics
    public function get_dashboard_stats() {
        $query = "
            SELECT 
                COUNT(DISTINCT location_id) as locations_with_data,
                COUNT(*) as total_records,
                MAX(recorded_at_utc) as latest_update
            FROM " . $this->table . "
            WHERE recorded_at_utc >= NOW() - INTERVAL '24 hours'
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure we always return an array with expected keys
        return [
            'locations_with_data' => $result['locations_with_data'] ?? 0,
            'total_records' => $result['total_records'] ?? 0,
            'latest_update' => $result['latest_update'] ?? null
        ];
    }

    // Count active locations
    public function get_active_locations_count() {
        $query = "
            SELECT COUNT(*) as count 
            FROM locations 
            WHERE status = 'ACTIVE' 
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    // Get weather data by date range
    public function get_by_date_range($location_id, $start_date, $end_date) {
        $query = "
            SELECT wh.*, l.location_name, l.city, l.state_province
            FROM " . $this->table . " wh
            JOIN locations l ON wh.location_id = l.location_id
            WHERE wh.location_id = :location_id
            AND wh.recorded_at_utc BETWEEN :start_date AND :end_date
            ORDER BY wh.recorded_at_utc ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt;
    }

    // Get recent weather data (last N records)
    public function get_recent($location_id, $limit = 50) {
        $query = "
            SELECT wh.*, l.location_name, l.city, l.state_province
            FROM " . $this->table . " wh
            JOIN locations l ON wh.location_id = l.location_id
            WHERE wh.location_id = :location_id
            ORDER BY wh.recorded_at_utc DESC
            LIMIT :limit
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Delete old weather records (for cleanup)
    public function delete_old_records($days_to_keep = 30) {
        $query = "
            DELETE FROM " . $this->table . "
            WHERE recorded_at_utc < NOW() - INTERVAL ':days_to_keep days'
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days_to_keep', $days_to_keep, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Get weather statistics for a location
    public function get_location_stats($location_id, $days = 7) {
        $query = "
            SELECT 
                COUNT(*) as total_records,
                AVG(temp_celsius) as avg_temperature,
                MIN(temp_celsius) as min_temperature,
                MAX(temp_celsius) as max_temperature,
                AVG(humidity_percent) as avg_humidity,
                AVG(pressure_hpa) as avg_pressure,
                MAX(recorded_at_utc) as last_update
            FROM " . $this->table . "
            WHERE location_id = :location_id
            AND recorded_at_utc >= NOW() - INTERVAL ':days days'
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if location has recent data (within last hour)
    public function has_recent_data($location_id, $hours = 1) {
        $query = "
            SELECT COUNT(*) as count
            FROM " . $this->table . "
            WHERE location_id = :location_id
            AND recorded_at_utc >= NOW() - INTERVAL ':hours hours'
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] ?? 0) > 0;
    }

    // Get weather alerts data (extreme conditions)
    public function get_alert_conditions($thresholds = []) {
        $default_thresholds = [
            'high_temp' => 35,
            'low_temp' => 0,
            'high_wind' => 20,
            'high_humidity' => 90,
            'low_pressure' => 980
        ];
        
        $thresholds = array_merge($default_thresholds, $thresholds);
        
        $query = "
            SELECT DISTINCT ON (wh.location_id) 
                wh.*,
                l.location_name,
                l.city,
                l.state_province
            FROM " . $this->table . " wh
            JOIN locations l ON wh.location_id = l.location_id
            WHERE l.status = 'ACTIVE'
            AND (
                wh.temp_celsius >= :high_temp OR
                wh.temp_celsius <= :low_temp OR
                wh.wind_speed_ms >= :high_wind OR
                wh.humidity_percent >= :high_humidity OR
                wh.pressure_hpa <= :low_pressure
            )
            ORDER BY wh.location_id, wh.recorded_at_utc DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':high_temp', $thresholds['high_temp']);
        $stmt->bindParam(':low_temp', $thresholds['low_temp']);
        $stmt->bindParam(':high_wind', $thresholds['high_wind']);
        $stmt->bindParam(':high_humidity', $thresholds['high_humidity']);
        $stmt->bindParam(':low_pressure', $thresholds['low_pressure']);
        $stmt->execute();
        return $stmt;
    }
}
?>
