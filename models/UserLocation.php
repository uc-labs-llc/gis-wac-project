<?php
class UserLocation {
    private $conn;
    private $table = 'user_locations';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Assigns a user to a location with a default relationship type
    public function create($user_id, $location_id, $relationship_type = 'VIEWER') {
        // Query to insert the record (REMOVED assigned_by)
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, location_id, relationship_type)
                  VALUES (:user_id, :location_id, :relationship_type)";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':location_id', $location_id);
        $stmt->bindParam(':relationship_type', $relationship_type);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        printf("Error: %s.\n", $stmt->error);
        return false;
    }

    // Reads all locations assigned to a specific user
    public function read_for_user($user_id) {
        // We join locations to get the location_name for the display
        $query = "SELECT ul.user_location_id, l.location_name, l.location_code, ul.relationship_type
                  FROM " . $this->table . " ul
                  JOIN locations l ON ul.location_id = l.location_id
                  WHERE ul.user_id = :user_id AND ul.is_current = true
                  ORDER BY l.location_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt;
    }

    // Retrieves a list of all locations for the dropdown box
    public function get_all_locations() {
        $query = "SELECT location_id, location_name, city, state_province
                  FROM locations
                  WHERE status = 'ACTIVE'
                  ORDER BY location_name ASC";
                  
        $stmt = $this->conn->query($query);
        return $stmt;
    }

    // Deletes an assignment (by its user_location_id)
    public function delete($user_location_id) {
        $query = "DELETE FROM " . $this->table . " WHERE user_location_id = :user_location_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_location_id', $user_location_id);

        if ($stmt->execute()) {
            return true;
        }

        printf("Error: %s.\n", $stmt->error);
        return false;
    }
}
?>
