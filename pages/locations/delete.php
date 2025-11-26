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

// Get location data for confirmation
$location->location_id = $_GET['id'];
$stmt = $location->read_single();
$current_location = $stmt->fetch();

if (!$current_location) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if ($location->delete()) {
        header('Location: index.php?message=Location deleted successfully');
        exit;
    } else {
        header('Location: index.php?error=Failed to delete location');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Location - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/locations.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>🗑️ Delete Location</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Locations</a>
                </div>
            </div>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>

        <div style="background: white; border-radius: 15px; padding: 30px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: #e53e3e; margin-bottom: 20px;">Confirm Deletion</h2>
            
            <div style="background: #fed7d7; color: #c53030; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h3>⚠️ Warning: This action cannot be undone!</h3>
                <p>You are about to delete the following location:</p>
            </div>

            <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: left;">
                <h3><?php echo htmlspecialchars($current_location['location_name']); ?></h3>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($current_location['location_type']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($current_location['address_line_1'] . ', ' . $current_location['city'] . ', ' . $current_location['state_province']); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($current_location['status']); ?></p>
                <?php if ($current_location['latitude'] && $current_location['longitude']): ?>
                    <p><strong>Coordinates:</strong> <?php echo number_format($current_location['latitude'], 6); ?>, <?php echo number_format($current_location['longitude'], 6); ?></p>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="index.php" class="btn-edit" style="padding: 12px 24px; text-decoration: none;">Cancel</a>
                <a href="delete.php?id=<?php echo $current_location['location_id']; ?>&confirm=yes" 
                   class="btn-delete" style="padding: 12px 24px; text-decoration: none;">
                   Yes, Delete Permanently
                </a>
            </div>
        </div>
    </div>
</body>
</html>
