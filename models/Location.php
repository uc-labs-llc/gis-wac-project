<?php
class Location {
    private $conn;
    private $table = 'locations';

    // Properties matching database columns
    public $location_id;
    public $location_name;
    public $location_code;
    public $location_type;
    public $description;
    public $address_line_1;
    public $address_line_2;
    public $building_number;
    public $building_name;
    public $floor_number;
    public $room_suite;
    public $complex_name;
    public $landmark;
    public $neighborhood;
    public $city;
    public $county;
    public $state_province;
    public $zip_postal_code;
    public $country;
    public $latitude;
    public $longitude;
    public $elevation_meters;
    public $geohash;
    public $polling_frequency;
    public $timezone;
    public $operational_radius_km;
    public $status;
    public $priority_level;
    public $area_sq_meters;
    public $surrounding_terrain;
    public $has_weather_station;
    public $station_model;
    public $created_at;
    public $updated_at;
    public $activated_at;
    public $deactivated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all locations
    public function read() {
        $query = 'SELECT * FROM ' . $this->table . ' ORDER BY location_name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get single location
    public function read_single() {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE location_id = :location_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->execute();
        return $stmt;
    }

    // Create location
    public function create() {
        $query = 'INSERT INTO ' . $this->table . ' 
            (location_name, location_code, location_type, description, 
             address_line_1, address_line_2, building_number, building_name, 
             floor_number, room_suite, complex_name, landmark, neighborhood,
             city, county, state_province, zip_postal_code, country,
             latitude, longitude, elevation_meters, polling_frequency, 
             timezone, operational_radius_km, status, priority_level,
             area_sq_meters, surrounding_terrain, has_weather_station, station_model)
        VALUES 
            (:location_name, :location_code, :location_type, :description,
             :address_line_1, :address_line_2, :building_number, :building_name,
             :floor_number, :room_suite, :complex_name, :landmark, :neighborhood,
             :city, :county, :state_province, :zip_postal_code, :country,
             :latitude, :longitude, :elevation_meters, :polling_frequency,
             :timezone, :operational_radius_km, :status, :priority_level,
             :area_sq_meters, :surrounding_terrain, :has_weather_station, :station_model)';

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        $stmt->bindParam(':location_name', $this->location_name);
        $stmt->bindParam(':location_code', $this->location_code);
        $stmt->bindParam(':location_type', $this->location_type);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':address_line_1', $this->address_line_1);
        $stmt->bindParam(':address_line_2', $this->address_line_2);
        $stmt->bindParam(':building_number', $this->building_number);
        $stmt->bindParam(':building_name', $this->building_name);
        $stmt->bindParam(':floor_number', $this->floor_number);
        $stmt->bindParam(':room_suite', $this->room_suite);
        $stmt->bindParam(':complex_name', $this->complex_name);
        $stmt->bindParam(':landmark', $this->landmark);
        $stmt->bindParam(':neighborhood', $this->neighborhood);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':county', $this->county);
        $stmt->bindParam(':state_province', $this->state_province);
        $stmt->bindParam(':zip_postal_code', $this->zip_postal_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':elevation_meters', $this->elevation_meters);
        $stmt->bindParam(':polling_frequency', $this->polling_frequency);
        $stmt->bindParam(':timezone', $this->timezone);
        $stmt->bindParam(':operational_radius_km', $this->operational_radius_km);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':priority_level', $this->priority_level);
        $stmt->bindParam(':area_sq_meters', $this->area_sq_meters);
        $stmt->bindParam(':surrounding_terrain', $this->surrounding_terrain);
        $stmt->bindParam(':has_weather_station', $this->has_weather_station);
        $stmt->bindParam(':station_model', $this->station_model);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update location
    public function update() {
        $query = 'UPDATE ' . $this->table . '
            SET
                location_name = :location_name,
                location_code = :location_code,
                location_type = :location_type,
                description = :description,
                address_line_1 = :address_line_1,
                address_line_2 = :address_line_2,
                building_number = :building_number,
                building_name = :building_name,
                floor_number = :floor_number,
                room_suite = :room_suite,
                complex_name = :complex_name,
                landmark = :landmark,
                neighborhood = :neighborhood,
                city = :city,
                county = :county,
                state_province = :state_province,
                zip_postal_code = :zip_postal_code,
                country = :country,
                latitude = :latitude,
                longitude = :longitude,
                elevation_meters = :elevation_meters,
                polling_frequency = :polling_frequency,
                timezone = :timezone,
                operational_radius_km = :operational_radius_km,
                status = :status,
                priority_level = :priority_level,
                area_sq_meters = :area_sq_meters,
                surrounding_terrain = :surrounding_terrain,
                has_weather_station = :has_weather_station,
                station_model = :station_model,
                updated_at = NOW()
            WHERE
                location_id = :location_id';

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        $stmt->bindParam(':location_name', $this->location_name);
        $stmt->bindParam(':location_code', $this->location_code);
        $stmt->bindParam(':location_type', $this->location_type);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':address_line_1', $this->address_line_1);
        $stmt->bindParam(':address_line_2', $this->address_line_2);
        $stmt->bindParam(':building_number', $this->building_number);
        $stmt->bindParam(':building_name', $this->building_name);
        $stmt->bindParam(':floor_number', $this->floor_number);
        $stmt->bindParam(':room_suite', $this->room_suite);
        $stmt->bindParam(':complex_name', $this->complex_name);
        $stmt->bindParam(':landmark', $this->landmark);
        $stmt->bindParam(':neighborhood', $this->neighborhood);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':county', $this->county);
        $stmt->bindParam(':state_province', $this->state_province);
        $stmt->bindParam(':zip_postal_code', $this->zip_postal_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':elevation_meters', $this->elevation_meters);
        $stmt->bindParam(':polling_frequency', $this->polling_frequency);
        $stmt->bindParam(':timezone', $this->timezone);
        $stmt->bindParam(':operational_radius_km', $this->operational_radius_km);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':priority_level', $this->priority_level);
        $stmt->bindParam(':area_sq_meters', $this->area_sq_meters);
        $stmt->bindParam(':surrounding_terrain', $this->surrounding_terrain);
        $stmt->bindParam(':has_weather_station', $this->has_weather_station);
        $stmt->bindParam(':station_model', $this->station_model);
        $stmt->bindParam(':location_id', $this->location_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete location
    public function delete() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE location_id = :location_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':location_id', $this->location_id);
        
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get active locations with coordinates for maps
    public function get_active_locations_with_coords() {
        $query = 'SELECT location_id, location_name, location_type, status, priority_level, latitude, longitude 
                  FROM ' . $this->table . ' 
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL 
                  ORDER BY location_name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
