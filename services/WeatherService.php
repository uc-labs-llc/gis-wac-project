<?php
require_once __DIR__ . '/../models/WeatherData.php';
require_once __DIR__ . '/../models/Location.php';
require_once __DIR__ . '/../models/Config.php';

class WeatherService {
    private $db;
    private $weatherData;
    private $api_key;
    private $base_url = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct($db) {
        $this->db = $db;
        $this->weatherData = new WeatherData($db);
        
        // Get API key
        $config = new Config($db);
        $this->api_key = $config->get('openweathermap_api_key');
    }

    // Fetch weather for a single location
    public function fetch_weather_for_location($location_id, $location_name, $lat, $lon) {
        if (empty($this->api_key)) {
            throw new Exception('OpenWeatherMap API key not configured');
        }

        $url = $this->base_url . "?lat={$lat}&lon={$lon}&appid={$this->api_key}&units=metric";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("API request failed with code: {$http_code}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }

        if (isset($data['cod']) && $data['cod'] != 200) {
            throw new Exception("API Error: " . ($data['message'] ?? 'Unknown error'));
        }

        // Save to database
        return $this->save_weather_data($location_id, $data);
    }

    // Save weather data to database - COMPLETELY FIXED VERSION
    private function save_weather_data($location_id, $api_data) {
        $weather = new WeatherData($this->db);
        
        // Required fields
        $weather->location_id = $location_id;
        $weather->recorded_at_utc = date('Y-m-d H:i:s');
        $weather->recorded_at_local = date('Y-m-d H:i:s');
        $weather->data_effective_time = date('Y-m-d H:i:s', $api_data['dt'] ?? time());
        $weather->api_source = 'OPENWEATHERMAP';
        $weather->raw_payload = json_encode($api_data);
        
        // Optional fields with proper null handling
        $weather->source_id = $api_data['id'] ?? null;
        $weather->data_confidence = 'HIGH';
        
        // Main weather data with type conversion
        $weather->temp_celsius = $api_data['main']['temp'] ?? null;
        $weather->feels_like_celsius = $api_data['main']['feels_like'] ?? null;
        $weather->humidity_percent = isset($api_data['main']['humidity']) ? (int)$api_data['main']['humidity'] : null;
        $weather->pressure_hpa = $api_data['main']['pressure'] ?? null;
        
        // Wind data
        $weather->wind_speed_ms = $api_data['wind']['speed'] ?? null;
        $weather->wind_direction_degrees = isset($api_data['wind']['deg']) ? (int)$api_data['wind']['deg'] : null;
        $weather->wind_gust_ms = $api_data['wind']['gust'] ?? null;
        
        // Precipitation
        $rain = $api_data['rain'] ?? [];
        $weather->rainfall_mm_1h = $rain['1h'] ?? null;
        $weather->rainfall_mm_3h = $rain['3h'] ?? null;
        
        $snow = $api_data['snow'] ?? [];
        $weather->snowfall_mm_1h = $snow['1h'] ?? null;
        
        // Other data
        $weather->visibility_meters = isset($api_data['visibility']) ? (int)$api_data['visibility'] : null;
        $weather->cloudiness_percent = isset($api_data['clouds']['all']) ? (int)$api_data['clouds']['all'] : null;
        $weather->dew_point_celsius = $api_data['dew_point'] ?? null;
        $weather->uv_index = $api_data['uvi'] ?? null;
        $weather->solar_radiation_wm2 = $api_data['solar_radiation'] ?? null;
        
        // Weather conditions
        if (!empty($api_data['weather'][0])) {
            $weather_info = $api_data['weather'][0];
            $weather->weather_main = $weather_info['main'] ?? null;
            $weather->weather_description = $weather_info['description'] ?? null;
            $weather->weather_icon = $weather_info['icon'] ?? null;
        }
        
        // CRITICAL FIX: All boolean fields must be proper booleans, not strings
        $weather->is_forecast = false; // This is a boolean field
        $weather->data_quality_score = 1.0;
        $weather->needs_verification = false; // This is a boolean field
        
        // Forecast fields
        $weather->forecast_hours_ahead = null;

        return $weather->create();
    }

    // ... rest of the methods remain the same
    public function process_all_locations() {
        $location = new Location($this->db);
        $locations = $location->read();
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        while ($row = $locations->fetch(PDO::FETCH_ASSOC)) {
            if ($row['status'] !== 'ACTIVE' || !$row['latitude'] || !$row['longitude']) {
                continue;
            }

            try {
                $success = $this->fetch_weather_for_location(
                    $row['location_id'],
                    $row['location_name'],
                    $row['latitude'],
                    $row['longitude']
                );
                
                if ($success) {
                    $results['success']++;
                    $results['details'][] = "✅ {$row['location_name']}: Success";
                } else {
                    $results['failed']++;
                    $results['details'][] = "❌ {$row['location_name']}: Failed to save to database";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = "❌ {$row['location_name']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    public function get_dashboard_stats() {
        return $this->weatherData->get_dashboard_stats();
    }

    public function get_latest_for_all_locations() {
        return $this->weatherData->get_latest_for_all_locations();
    }

    public function get_active_locations_count() {
        return $this->weatherData->get_active_locations_count();
    }

    public function extract_metrics($raw_payload) {
        if (is_string($raw_payload)) {
            $data = json_decode($raw_payload, true);
        } else {
            $data = $raw_payload;
        }
        
        if (!$data) {
            return null;
        }

        return [
            'temperature' => $data['main']['temp'] ?? null,
            'feels_like' => $data['main']['feels_like'] ?? null,
            'humidity' => $data['main']['humidity'] ?? null,
            'pressure' => $data['main']['pressure'] ?? null,
            'wind_speed' => $data['wind']['speed'] ?? null,
            'wind_direction' => $data['wind']['deg'] ?? null,
            'weather_condition' => $data['weather'][0]['main'] ?? null,
            'weather_description' => $data['weather'][0]['description'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'cloudiness' => $data['clouds']['all'] ?? null,
            'rain_1h' => $data['rain']['1h'] ?? null,
            'snow_1h' => $data['snow']['1h'] ?? null
        ];
    }
}
?>
