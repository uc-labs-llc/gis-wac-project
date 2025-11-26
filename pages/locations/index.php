<?php
// REMOVED: session_start();
require_once '../../config/database.php';
// REMOVED: require_once '../../models/User.php';
require_once '../../models/Location.php';


$database = new Database();
$db = $database->connect();

// Get locations for listing
$location = new Location($db);
$locations = $location->read(); // $locations is the PDOStatement for the table list

// FIX: Replace the undefined method call with the correct one: get_active_locations_with_coords()
// This method in the corrected Location.php returns the full array of active locations (fetchAll).
$locations_json = $location->get_active_locations_with_coords(); 

/* Original, crashing block:
$map_locations = $location->get_map_locations();
$locations_json = [];

while ($row = $map_locations->fetch(PDO::FETCH_ASSOC)) {
    $locations_json[] = $row;
}
*/
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Locations - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/locations.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>📍 Manage Locations</h1>
                <div class="user-details">
                    <a href="../../dashboard.php">← Back to Dashboard</a>
                </div>
            </div>
            </div>

        <a href="create.php" class="add-location-btn">+ Add New Location</a>

        <div class="locations-container">
            <div class="locations-list">
                <?php 
                $has_locations = false;
                while ($row = $locations->fetch(PDO::FETCH_ASSOC)): 
                    extract($row);
                    $has_locations = true;
                ?>
                    <div class="location-card" data-lat="<?php echo htmlspecialchars($latitude ?? ''); ?>" data-lng="<?php echo htmlspecialchars($longitude ?? ''); ?>">
                        <div class="location-header">
                            <h3><?php echo htmlspecialchars($location_name); ?></h3>
                            <span class="location-status status-<?php echo strtolower($status); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                        <p class="location-code">Code: <?php echo htmlspecialchars($location_code); ?></p>
                        <p class="location-address">
                            <?php echo htmlspecialchars($address_line_1); ?><br>
                            <?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($state_province); ?> <?php echo htmlspecialchars($zip_postal_code); ?>
                        </p>
                        <div class="location-actions">
                            <a href="edit.php?id=<?php echo htmlspecialchars($location_id); ?>" class="action-btn edit">✏️ Edit</a>
                            <button class="action-btn delete" onclick="confirmDelete('<?php echo htmlspecialchars($location_id); ?>', '<?php echo htmlspecialchars(addslashes($location_name)); ?>')">
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>

                <?php if (!$has_locations): ?>
                    <div class="no-locations">
                        <p>No locations found. Add one to get started.</p>
                        <a href="create.php" class="control-btn primary">➕ Add First Location</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="map-view">
                <div id="mapid" style="height: 100%;"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('mapid').setView([39.8283, -98.5795], 4); // Center of US

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var locationsData = <?php echo json_encode($locations_json); ?>;
        var markers = [];
        
        // Custom icons
        var activeIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        var inactiveIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });


        locationsData.forEach(function(location) {
            if (location.latitude && location.longitude) {
                var icon = location.status === 'ACTIVE' ? activeIcon : inactiveIcon;
                var marker = L.marker([location.latitude, location.longitude], { icon: icon })
                    .addTo(map)
                    .bindPopup(
                        '<strong>' + location.location_name + '</strong><br>' +
                        'Type: ' + location.location_type + '<br>' +
                        'Status: ' + location.status + '<br>' +
                        'Priority: ' + location.priority_level
                    );
                markers.push(marker);
            }
        });

        // Map control functions
        function fitMapToMarkers() {
            if (markers.length > 0) {
                var group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        function showAllLocations() {
            map.setView([39.8283, -98.5795], 4);
        }

        // Auto-fit to markers if we have any
        if (markers.length > 0) {
            fitMapToMarkers();
        }

        // Location card click handler
        document.querySelectorAll('.location-card').forEach(card => {
            card.addEventListener('click', function() {
                var lat = this.getAttribute('data-lat');
                var lng = this.getAttribute('data-lng');
                
                if (lat && lng) {
                    map.setView([lat, lng], 15);
                    
                    // Highlight the clicked card
                    document.querySelectorAll('.location-card').forEach(c => {
                        c.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        function confirmDelete(locationId, locationName) {
            if (confirm(`Are you sure you want to delete \"${locationName}\"?`)) {
                window.location.href = `delete.php?id=${locationId}`;
            }
        }
    </script>
</body>
</html>
