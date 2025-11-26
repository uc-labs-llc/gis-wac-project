<?php
// REMOVED: session_start();
require_once '../../config/database.php';
// REMOVED: require_once '../../models/User.php';
require_once '../../models/Location.php';


$database = new Database();
$db = $database->connect();
$location = new Location($db);

$error = '';
$success = '';

// Handle form submission
if ($_POST) {
    try {
        // Set location properties from form data
        $location->location_name = $_POST['location_name'] ?? '';
        $location->location_code = $_POST['location_code'] ?? '';
        $location->location_type = $_POST['location_type'] ?? 'FACILITY';
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
        $location->country = $_POST['country'] ?? 'United States';
        $location->latitude = $_POST['latitude'] ?? null;
        $location->longitude = $_POST['longitude'] ?? null;
        $location->elevation_meters = $_POST['elevation_meters'] ?? null;
        $location->polling_frequency = $_POST['polling_frequency'] ?? '5 minutes';
        $location->timezone = $_POST['timezone'] ?? 'America/New_York';
        $location->operational_radius_km = $_POST['operational_radius_km'] ?? 1.0;
        $location->status = $_POST['status'] ?? 'ACTIVE';
        $location->priority_level = $_POST['priority_level'] ?? 'MEDIUM';
        $location->area_sq_meters = $_POST['area_sq_meters'] ?? null;
        $location->surrounding_terrain = $_POST['surrounding_terrain'] ?? 'URBAN';
        
        // FIX: Properly handle checkbox - if not set, default to false
        $location->has_weather_station = isset($_POST['has_weather_station']) ? true : false;
        
        $location->station_model = $_POST['station_model'] ?? '';
        // REMOVED: $location->created_by = $_SESSION['user_id'];

        // Create the location
        if ($location->create()) {
            $success = 'Location created successfully!';
            // Clear form or redirect
            $_POST = []; // Clear form data
        } else {
            $error = 'Failed to create location. Please try again.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Location - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/locations.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>📍 Add New Location</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Locations</a>
                </div>
            </div>
            </div>

        <?php if ($error): ?>
            <div style="background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #c6f6d5; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success); ?>
                <a href="index.php" style="margin-left: 10px;">View All Locations</a>
            </div>
        <?php endif; ?>

        <div class="locations-container">
            <div class="locations-list">
                <form method="post" id="locationForm">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <h3>Basic Information</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="location_name">Location Name *</label>
                            <input type="text" id="location_name" name="location_name" 
                                   value="<?php echo htmlspecialchars($_POST['location_name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="location_code">Location Code</label>
                            <input type="text" id="location_code" name="location_code" 
                                   value="<?php echo htmlspecialchars($_POST['location_code'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="location_type">Location Type *</label>
                            <select id="location_type" name="location_type" required>
                                <option value="FACILITY" <?php echo ($_POST['location_type'] ?? '') == 'FACILITY' ? 'selected' : ''; ?>>Facility</option>
                                <option value="BUILDING" <?php echo ($_POST['location_type'] ?? '') == 'BUILDING' ? 'selected' : ''; ?>>Building</option>
                                <option value="CAMPUS" <?php echo ($_POST['location_type'] ?? '') == 'CAMPUS' ? 'selected' : ''; ?>>Campus</option>
                                <option value="CONSTRUCTION_SITE" <?php echo ($_POST['location_type'] ?? '') == 'CONSTRUCTION_SITE' ? 'selected' : ''; ?>>Construction Site</option>
                                <option value="FARM" <?php echo ($_POST['location_type'] ?? '') == 'FARM' ? 'selected' : ''; ?>>Farm</option>
                                <option value="WAREHOUSE" <?php echo ($_POST['location_type'] ?? '') == 'WAREHOUSE' ? 'selected' : ''; ?>>Warehouse</option>
                                <option value="OFFICE" <?php echo ($_POST['location_type'] ?? '') == 'OFFICE' ? 'selected' : ''; ?>>Office</option>
                                <option value="RETAIL" <?php echo ($_POST['location_type'] ?? '') == 'RETAIL' ? 'selected' : ''; ?>>Retail</option>
                                <option value="RESIDENTIAL" <?php echo ($_POST['location_type'] ?? '') == 'RESIDENTIAL' ? 'selected' : ''; ?>>Residential</option>
                                <option value="OTHER" <?php echo ($_POST['location_type'] ?? '') == 'OTHER' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="ACTIVE" <?php echo ($_POST['status'] ?? 'ACTIVE') == 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                                <option value="INACTIVE" <?php echo ($_POST['status'] ?? '') == 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="MAINTENANCE" <?php echo ($_POST['status'] ?? '') == 'MAINTENANCE' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="CONSTRUCTION" <?php echo ($_POST['status'] ?? '') == 'CONSTRUCTION' ? 'selected' : ''; ?>>Construction</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority_level">Priority Level</label>
                            <select id="priority_level" name="priority_level">
                                <option value="LOW" <?php echo ($_POST['priority_level'] ?? 'MEDIUM') == 'LOW' ? 'selected' : ''; ?>>Low</option>
                                <option value="MEDIUM" <?php echo ($_POST['priority_level'] ?? 'MEDIUM') == 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                                <option value="HIGH" <?php echo ($_POST['priority_level'] ?? '') == 'HIGH' ? 'selected' : ''; ?>>High</option>
                                <option value="CRITICAL" <?php echo ($_POST['priority_level'] ?? '') == 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <h3>Address Information</h3>
                        </div>

                        <div class="form-group">
                            <label for="address_line_1">Address Line 1 *</label>
                            <input type="text" id="address_line_1" name="address_line_1" 
                                   value="<?php echo htmlspecialchars($_POST['address_line_1'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="address_line_2">Address Line 2</label>
                            <input type="text" id="address_line_2" name="address_line_2" 
                                   value="<?php echo htmlspecialchars($_POST['address_line_2'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="building_number">Building Number</label>
                            <input type="text" id="building_number" name="building_number" 
                                   value="<?php echo htmlspecialchars($_POST['building_number'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="building_name">Building Name</label>
                            <input type="text" id="building_name" name="building_name" 
                                   value="<?php echo htmlspecialchars($_POST['building_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="state_province">State/Province *</label>
                            <input type="text" id="state_province" name="state_province" 
                                   value="<?php echo htmlspecialchars($_POST['state_province'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="zip_postal_code">ZIP/Postal Code *</label>
                            <input type="text" id="zip_postal_code" name="zip_postal_code" 
                                   value="<?php echo htmlspecialchars($_POST['zip_postal_code'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" 
                                   value="<?php echo htmlspecialchars($_POST['country'] ?? 'United States'); ?>">
                        </div>

                        <div class="form-group full-width">
                            <h3>Geographic Coordinates</h3>
                            <p style="color: #718096; font-size: 0.9em;">Click on the map to set coordinates automatically</p>
                        </div>

                        <div class="form-group">
                            <label for="latitude">Latitude *</label>
                            <input type="number" id="latitude" name="latitude" step="any" 
                                   value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="longitude">Longitude *</label>
                            <input type="number" id="longitude" name="longitude" step="any" 
                                   value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group full-width">
                            <h3>Additional Settings</h3>
                        </div>

                        <div class="form-group">
                            <label for="polling_frequency">Polling Frequency</label>
                            <select id="polling_frequency" name="polling_frequency">
                                <option value="1 minute" <?php echo ($_POST['polling_frequency'] ?? '5 minutes') == '1 minute' ? 'selected' : ''; ?>>1 minute</option>
                                <option value="5 minutes" <?php echo ($_POST['polling_frequency'] ?? '5 minutes') == '5 minutes' ? 'selected' : ''; ?>>5 minutes</option>
                                <option value="15 minutes" <?php echo ($_POST['polling_frequency'] ?? '') == '15 minutes' ? 'selected' : ''; ?>>15 minutes</option>
                                <option value="30 minutes" <?php echo ($_POST['polling_frequency'] ?? '') == '30 minutes' ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="1 hour" <?php echo ($_POST['polling_frequency'] ?? '') == '1 hour' ? 'selected' : ''; ?>>1 hour</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone">
                                <option value="America/New_York" <?php echo ($_POST['timezone'] ?? 'America/New_York') == 'America/New_York' ? 'selected' : ''; ?>>Eastern (EST)</option>
                                <option value="America/Chicago" <?php echo ($_POST['timezone'] ?? '') == 'America/Chicago' ? 'selected' : ''; ?>>Central (CST)</option>
                                <option value="America/Denver" <?php echo ($_POST['timezone'] ?? '') == 'America/Denver' ? 'selected' : ''; ?>>Mountain (MST)</option>
                                <option value="America/Los_Angeles" <?php echo ($_POST['timezone'] ?? '') == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific (PST)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="has_weather_station" value="1" 
                                       <?php echo isset($_POST['has_weather_station']) ? 'checked' : ''; ?>>
                                Has Weather Station
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Create Location</button>
                </form>
            </div>

            <div class="locations-map">
                <h2>Set Coordinates</h2>
                <p style="color: #718096; margin-bottom: 15px;">Click on the map to automatically set latitude and longitude</p>
                <div id="map"></div>
                <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                    <strong>Selected Coordinates:</strong>
                    <div id="selectedCoords">None selected</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([39.8283, -98.5795], 4);
        var marker = null;

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

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
                .bindPopup('Selected Location<br>Lat: ' + lat.toFixed(6) + '<br>Lng: ' + lng.toFixed(6))
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

        // Auto-fill coordinates from form if they exist
        var existingLat = document.getElementById('latitude').value;
        var existingLng = document.getElementById('longitude').value;
        
        if (existingLat && existingLng) {
            var lat = parseFloat(existingLat);
            var lng = parseFloat(existingLng);
            
            map.setView([lat, lng], 13);
            marker = L.marker([lat, lng]).addTo(map);
            
            document.getElementById('selectedCoords').textContent = 
                'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
        }
    </script>
</body>
</html>
