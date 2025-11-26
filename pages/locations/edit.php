<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Location.php';


// Check if location ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->connect();
$location = new Location($db);

// Get location data
$location->location_id = $_GET['id'];
$stmt = $location->read_single();
$current_location = $stmt->fetch();

if (!$current_location) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_POST) {
    try {
        // Update location properties
        $location->location_name = $_POST['location_name'] ?? '';
        $location->location_code = $_POST['location_code'] ?? '';
        $location->location_type = $_POST['location_type'] ?? '';
        $location->description = $_POST['description'] ?? '';
        $location->address_line_1 = $_POST['address_line_1'] ?? '';
        $location->address_line_2 = $_POST['address_line_2'] ?? '';
        $location->building_number = $_POST['building_number'] ?? '';
        $location->building_name = $_POST['building_name'] ?? '';
        $location->floor_number = $_POST['floor_number'] ?? '';
        $location->room_suite = $_POST['room_suite'] ?? '';
        $location->complex_name = $_POST['complex_name'] ?? '';
        $location->landmark = $_POST['landmark'] ?? '';
        $location->neighborhood = $_POST['neighborhood'] ?? '';
        $location->city = $_POST['city'] ?? '';
        $location->county = $_POST['county'] ?? '';
        $location->state_province = $_POST['state_province'] ?? '';
        $location->zip_postal_code = $_POST['zip_postal_code'] ?? '';
        $location->country = $_POST['country'] ?? '';
        $location->latitude = $_POST['latitude'] ?? null;
        $location->longitude = $_POST['longitude'] ?? null;
        $location->elevation_meters = $_POST['elevation_meters'] ?? null;
        $location->polling_frequency = $_POST['polling_frequency'] ?? '';
        $location->timezone = $_POST['timezone'] ?? '';
        $location->operational_radius_km = $_POST['operational_radius_km'] ?? 1.0;
        $location->status = $_POST['status'] ?? '';
        $location->priority_level = $_POST['priority_level'] ?? '';
        $location->area_sq_meters = $_POST['area_sq_meters'] ?? null;
        $location->surrounding_terrain = $_POST['surrounding_terrain'] ?? '';
        
        // FIX: Properly handle checkbox - if not set, default to false
        $location->has_weather_station = isset($_POST['has_weather_station']) ? true : false;
        
        $location->station_model = $_POST['station_model'] ?? '';
        $location->updated_by = $_SESSION['user_id'];

        // Update the location
        if ($location->update()) {
            $success = 'Location updated successfully!';
            // Refresh location data
            $stmt = $location->read_single();
            $current_location = $stmt->fetch();
        } else {
            $error = 'Failed to update location. Please try again.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Location - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/locations.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>✏️ Edit Location</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Locations</a>
                </div>
            </div>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #c6f6d5; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="locations-container">
            <!-- Location Form -->
            <div class="locations-list">
                <form method="post" id="locationForm">
                    <div class="form-grid">
                        <!-- Basic Information -->
                        <div class="form-group full-width">
                            <h3>Basic Information</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="location_name">Location Name *</label>
                            <input type="text" id="location_name" name="location_name" 
                                   value="<?php echo htmlspecialchars($current_location['location_name']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="location_code">Location Code</label>
                            <input type="text" id="location_code" name="location_code" 
                                   value="<?php echo htmlspecialchars($current_location['location_code'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="location_type">Location Type *</label>
                            <select id="location_type" name="location_type" required>
                                <option value="FACILITY" <?php echo $current_location['location_type'] == 'FACILITY' ? 'selected' : ''; ?>>Facility</option>
                                <option value="BUILDING" <?php echo $current_location['location_type'] == 'BUILDING' ? 'selected' : ''; ?>>Building</option>
                                <option value="CAMPUS" <?php echo $current_location['location_type'] == 'CAMPUS' ? 'selected' : ''; ?>>Campus</option>
                                <option value="CONSTRUCTION_SITE" <?php echo $current_location['location_type'] == 'CONSTRUCTION_SITE' ? 'selected' : ''; ?>>Construction Site</option>
                                <option value="FARM" <?php echo $current_location['location_type'] == 'FARM' ? 'selected' : ''; ?>>Farm</option>
                                <option value="WAREHOUSE" <?php echo $current_location['location_type'] == 'WAREHOUSE' ? 'selected' : ''; ?>>Warehouse</option>
                                <option value="OFFICE" <?php echo $current_location['location_type'] == 'OFFICE' ? 'selected' : ''; ?>>Office</option>
                                <option value="RETAIL" <?php echo $current_location['location_type'] == 'RETAIL' ? 'selected' : ''; ?>>Retail</option>
                                <option value="RESIDENTIAL" <?php echo $current_location['location_type'] == 'RESIDENTIAL' ? 'selected' : ''; ?>>Residential</option>
                                <option value="OTHER" <?php echo $current_location['location_type'] == 'OTHER' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="ACTIVE" <?php echo $current_location['status'] == 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                                <option value="INACTIVE" <?php echo $current_location['status'] == 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="MAINTENANCE" <?php echo $current_location['status'] == 'MAINTENANCE' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="CONSTRUCTION" <?php echo $current_location['status'] == 'CONSTRUCTION' ? 'selected' : ''; ?>>Construction</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority_level">Priority Level</label>
                            <select id="priority_level" name="priority_level">
                                <option value="LOW" <?php echo $current_location['priority_level'] == 'LOW' ? 'selected' : ''; ?>>Low</option>
                                <option value="MEDIUM" <?php echo $current_location['priority_level'] == 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                                <option value="HIGH" <?php echo $current_location['priority_level'] == 'HIGH' ? 'selected' : ''; ?>>High</option>
                                <option value="CRITICAL" <?php echo $current_location['priority_level'] == 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($current_location['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- Address Information -->
                        <div class="form-group full-width">
                            <h3>Address Information</h3>
                        </div>

                        <div class="form-group">
                            <label for="address_line_1">Address Line 1 *</label>
                            <input type="text" id="address_line_1" name="address_line_1" 
                                   value="<?php echo htmlspecialchars($current_location['address_line_1']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="address_line_2">Address Line 2</label>
                            <input type="text" id="address_line_2" name="address_line_2" 
                                   value="<?php echo htmlspecialchars($current_location['address_line_2'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($current_location['city']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="state_province">State/Province *</label>
                            <input type="text" id="state_province" name="state_province" 
                                   value="<?php echo htmlspecialchars($current_location['state_province']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="zip_postal_code">ZIP/Postal Code *</label>
                            <input type="text" id="zip_postal_code" name="zip_postal_code" 
                                   value="<?php echo htmlspecialchars($current_location['zip_postal_code']); ?>" 
                                   required>
                        </div>

                        <!-- Coordinates -->
                        <div class="form-group full-width">
                            <h3>Geographic Coordinates</h3>
                            <p style="color: #718096; font-size: 0.9em;">Click on the map to update coordinates</p>
                        </div>

                        <div class="form-group">
                            <label for="latitude">Latitude *</label>
                            <input type="number" id="latitude" name="latitude" step="any" 
                                   value="<?php echo htmlspecialchars($current_location['latitude'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="longitude">Longitude *</label>
                            <input type="number" id="longitude" name="longitude" step="any" 
                                   value="<?php echo htmlspecialchars($current_location['longitude'] ?? ''); ?>" 
                                   required>
                        </div>

                        <!-- Additional Settings -->
                        <div class="form-group full-width">
                            <h3>Additional Settings</h3>
                        </div>

                        <div class="form-group">
                            <label for="polling_frequency">Polling Frequency</label>
                            <select id="polling_frequency" name="polling_frequency">
                                <option value="1 minute" <?php echo $current_location['polling_frequency'] == '1 minute' ? 'selected' : ''; ?>>1 minute</option>
                                <option value="5 minutes" <?php echo $current_location['polling_frequency'] == '5 minutes' ? 'selected' : ''; ?>>5 minutes</option>
                                <option value="15 minutes" <?php echo $current_location['polling_frequency'] == '15 minutes' ? 'selected' : ''; ?>>15 minutes</option>
                                <option value="30 minutes" <?php echo $current_location['polling_frequency'] == '30 minutes' ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="1 hour" <?php echo $current_location['polling_frequency'] == '1 hour' ? 'selected' : ''; ?>>1 hour</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="has_weather_station" value="1" 
                                       <?php echo $current_location['has_weather_station'] ? 'checked' : ''; ?>>
                                Has Weather Station
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Update Location</button>
                </form>
            </div>

            <!-- Map for coordinate selection -->
            <div class="locations-map">
                <h2>Update Coordinates</h2>
                <p style="color: #718096; margin-bottom: 15px;">Click on the map to update latitude and longitude</p>
                <div id="map"></div>
                <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                    <strong>Current Coordinates:</strong>
                    <div id="selectedCoords">
                        <?php if ($current_location['latitude'] && $current_location['longitude']): ?>
                            Lat: <?php echo number_format($current_location['latitude'], 6); ?>, 
                            Lng: <?php echo number_format($current_location['longitude'], 6); ?>
                        <?php else: ?>
                            None selected
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        var map = L.map('map');
        var marker = null;

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Set initial view based on existing coordinates
        var existingLat = <?php echo $current_location['latitude'] ?: '39.8283'; ?>;
        var existingLng = <?php echo $current_location['longitude'] ?: '-98.5795'; ?>;
        
        map.setView([existingLat, existingLng], 13);
        
        // Add existing marker if coordinates exist
        if (<?php echo $current_location['latitude'] ? 'true' : 'false'; ?>) {
            marker = L.marker([existingLat, existingLng]).addTo(map)
                .bindPopup('Current Location<br>Lat: ' + existingLat.toFixed(6) + '<br>Lng: ' + existingLng.toFixed(6))
                .openPopup();
        }

        // Map click handler
        map.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            
            // Update form fields
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            
            // Update display
            document.getElementById('selectedCoords').textContent = 
                'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
            
            // Update marker
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker([lat, lng]).addTo(map)
                .bindPopup('Updated Location<br>Lat: ' + lat.toFixed(6) + '<br>Lng: ' + lng.toFixed(6))
                .openPopup();
        });

        // Form validation
        document.getElementById('locationForm').addEventListener('submit', function(e) {
            var lat = document.getElementById('latitude').value;
            var lng = document.getElementById('longitude').value;
            
            if (!lat || !lng) {
                e.preventDefault();
                alert('Please set coordinates by clicking on the map.');
                return false;
            }
        });
    </script>
</body>
</html>
