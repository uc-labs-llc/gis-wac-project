<?php
// REMOVED: session_start();
require_once 'config/database.php';
// REMOVED: User model requirement and user check

$database = new Database();
$db = $database->connect();

// Get some basic stats
$locations_count = 0;
$active_alerts = 0; // Note: This variable is defined but not used in the stats-grid of the HTML below
$pending_alerts = 0;
$total_rules = 0;

try {
    // Count locations
    $stmt = $db->query("SELECT COUNT(*) as count FROM locations WHERE status = 'ACTIVE'");
    $result = $stmt->fetch();
    $locations_count = $result['count'];
    
    // Count alert rules
    $stmt = $db->query("SELECT COUNT(*) as count FROM alert_rules WHERE is_active = true");
    $result = $stmt->fetch();
    $total_rules = $result['count'];
    
    // Count pending alerts
    $stmt = $db->query("SELECT COUNT(*) as count FROM alert_queue WHERE status = 'QUEUED'");
    $result = $stmt->fetch();
    $pending_alerts = $result['count'];
    
} catch (Exception $e) {
    // Silently fail for now - tables might not have data yet
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weather Alerts Management Platorm</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="container">
        <div class="welcome-message">
            <h2>Weather Email Alerts Management Platform</h2>
            <p>Geo-Contextual Weather Alert System</p>
        </div>
        

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number locations"><?php echo $locations_count; ?></span>
                <span class="stat-label">Active Locations</span>
            </div>
            <div class="stat-card">
                <span class="stat-number rules"><?php echo $total_rules; ?></span>
                <span class="stat-label">Alert Rules</span>
            </div>
            <div class="stat-card">
                <span class="stat-number alerts">0</span>
                <span class="stat-label">Alerts Today</span>
            </div>
            <div class="stat-card">
                <span class="stat-number pending"><?php echo $pending_alerts; ?></span>
                <span class="stat-label">Pending Alerts</span>
            </div>
        </div>

        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <a href="pages/locations/index.php" class="action-btn manage">📍 Manage Locations for Alerts</a>
                <a href="pages/weather/index.php" class="action-btn weather">☁️  Retrieve Weather Data</a>
                <a href="pages/users/index.php" class="action-btn alerts">⚠️  Manaage Alert User Location</a>
                <a href="pages/users/user_locations.php" class="action-btn queue">📋 Assign Alert Users to Locations</a>
                <a href="pages/alerts/index.php" class="action-btn users">👥 Create Alert Rules </a>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
<footer style="text-align: center; padding-top: 1.5rem; margin-top: 2rem; border-top: 1px solid #2d2d4d; color: #a0a0c0; background-color: #0f0f23;">
            <div class="copyright">
                <p>&copy; 2025 Cliff Larson. UC-LABS.LLC</p>
                <p>Email: <a style="color: #667eea;" href="mailto:uclabs.llc@gmail.com">uclabs.llc@gmail.com</a></p>
                <p><em>- Help Save Lives - </em></p>
            </div>
        </footer>

</body>
</html>
