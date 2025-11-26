<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../services/WeatherService.php';


$database = new Database();
$db = $database->connect();
$weatherService = new WeatherService($db, 'your_api_key');

$message = '';

// Handle schedule update
if ($_POST) {
    $interval = $_POST['update_interval'] ?? '15 minutes';
    $enabled = isset($_POST['scheduler_enabled']) ? 1 : 0;
    
    // In a real implementation, you would save these settings to a database
    // For now, we'll just show a message
    $message = "Scheduler " . ($enabled ? "enabled" : "disabled") . 
               " with interval: " . $interval;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weather Schedule - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>⏰ Weather Schedule</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Weather Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="background: #c6f6d5; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="weather-container">
            <!-- Schedule Configuration -->
            <div class="weather-stats">
                <h2>Automatic Weather Updates</h2>
                
                <div class="weather-card">
                    <h3>🔄 Update Schedule</h3>
                    <p>Configure how often the system should automatically fetch weather data for all active locations.</p>
                    
                    <form method="post" class="schedule-form">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="scheduler_enabled" value="1" checked>
                                Enable Automatic Weather Updates
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="update_interval">Update Interval</label>
                            <select id="update_interval" name="update_interval">
                                <option value="5 minutes">Every 5 Minutes</option>
                                <option value="15 minutes" selected>Every 15 Minutes</option>
                                <option value="30 minutes">Every 30 Minutes</option>
                                <option value="1 hour">Every Hour</option>
                                <option value="2 hours">Every 2 Hours</option>
                                <option value="6 hours">Every 6 Hours</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="control-btn schedule">Save Schedule</button>
                    </form>
                </div>

                <div class="weather-card">
                    <h3>📋 Next Scheduled Run</h3>
                    <div class="weather-metrics">
                        <div class="metric">
                            <span class="metric-value" style="color: #667eea;">
                                <?php echo date('H:i', strtotime('+15 minutes')); ?>
                            </span>
                            <span class="metric-label">Next Update</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value" style="color: #48bb78;">Active</span>
                            <span class="metric-label">Status</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Controls -->
            <div class="weather-data">
                <h2>Manual Controls</h2>
                
                <div class="weather-card">
                    <h3>🔄 Immediate Update</h3>
                    <p>Fetch weather data for all active locations right now.</p>
                    <form method="post" action="index.php" style="margin-top: 15px;">
                        <button type="submit" name="fetch_weather" class="control-btn fetch">
                            Fetch Weather Now
                        </button>
                    </form>
                </div>

                <div class="weather-card">
                    <h3>🧹 Data Cleanup</h3>
                    <p>Remove weather data older than 30 days to save storage space.</p>
                    <form method="post" style="margin-top: 15px;">
                        <button type="submit" name="cleanup_data" class="control-btn" style="background: #e53e3e;">
                            Cleanup Old Data
                        </button>
                    </form>
                </div>

                <div class="weather-card">
                    <h3>📊 System Status</h3>
                    <div class="weather-metrics">
                        <div class="metric">
                            <span class="metric-value" style="color: #4299e1;">Online</span>
                            <span class="metric-label">API Status</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value" style="color: #38a169;">0/1000</span>
                            <span class="metric-label">API Calls</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Job Instructions -->
        <div class="control-panel" style="margin-top: 30px;">
            <h2>🛠️ Server Configuration</h2>
            <p>To enable automatic weather updates, add this cron job to your server:</p>
            
            <div class="raw-data">
                # Update weather data every 15 minutes<br>
                */15 * * * * /usr/bin/php /var/www/html/gis-wac-project/cron/weather_update.php<br>
                <br>
                # Cleanup old data daily at 2 AM<br>
                0 2 * * * /usr/bin/php /var/www/html/gis-wac-project/cron/weather_cleanup.php
            </div>
            
            <p style="margin-top: 15px; color: #718096;">
                <strong>Note:</strong> Make sure to replace the path with your actual project path.
            </p>
        </div>
    </div>
</body>
</html>
