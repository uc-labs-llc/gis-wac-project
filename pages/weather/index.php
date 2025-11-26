<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Location.php';
require_once __DIR__ . '/../../models/WeatherData.php';
require_once __DIR__ . '/../../models/Config.php';
require_once __DIR__ . '/../../services/WeatherService.php';

$database = new Database();
$db = $database->connect();
$weatherService = new WeatherService($db);

// Instantiate Location Model once for use in the table loop
$locationModel = new Location($db);

$fetch_message = '';
$fetch_details = [];
$has_api_key = false;

// Check if API key is configured
try {
    $config = new Config($db);
    $api_key = $config->get('openweathermap_api_key');
    $has_api_key = !empty($api_key);
} catch (Exception $e) {
    $has_api_key = false;
}

// Handle manual fetch request
if (isset($_POST['fetch_weather']) && $has_api_key) {
    try {
        $results = $weatherService->process_all_locations();
        $fetch_message = "Weather fetch completed: {$results['success']} successful, {$results['failed']} failed";
        $fetch_details = $results['details'];
    } catch (Exception $e) {
        $fetch_message = "Error: " . $e->getMessage();
        $fetch_details = [$e->getMessage()];
    }
} elseif (isset($_POST['fetch_weather']) && !$has_api_key) {
    $fetch_message = "Error: No API key configured";
    $fetch_details = ["Please set your OpenWeatherMap API key in the Configuration page first."];
}

// Get dashboard stats
$stats = $weatherService->get_dashboard_stats();

// Get active locations count
$active_locations_count = $weatherService->get_active_locations_count();
$has_active_locations = $active_locations_count > 0;

// Get latest weather data
try {
    $latest_weather = $weatherService->get_latest_for_all_locations();
} catch (Exception $e) {
    $latest_weather = null;
    error_log("Error getting latest weather: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weather Data - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container wide-container">
        <div class="header">
            <div class="user-info">
                <h1>🌤️ Weather Data Dashboard</h1>
                <div class="user-details">
                    <a href="../../dashboard.php">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <?php if (!$has_api_key): ?>
            <div class="alert-banner warning">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <strong>API Key Not Configured</strong>
                    <p>You need to set your OpenWeatherMap API key before fetching weather data.</p>
                </div>
                <a href="config.php" class="control-btn danger">
                    ⚙️ Configure API Key
                </a>
            </div>
        <?php endif; ?>

        <?php if ($fetch_message): ?>
            <div class="alert-banner <?php echo strpos($fetch_message, 'Error:') === 0 ? 'error' : 'success'; ?>">
                <div class="alert-icon">
                    <?php echo strpos($fetch_message, 'Error:') === 0 ? '❌' : '✅'; ?>
                </div>
                <div class="alert-content">
                    <strong><?php echo htmlspecialchars($fetch_message); ?></strong>
                    <?php if (!empty($fetch_details)): ?>
                        <div class="alert-details">
                            <?php foreach ($fetch_details as $detail): ?>
                                <div><?php echo htmlspecialchars($detail); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="control-panel gradient-bg">
            <h2>Weather Data Management</h2>
            <div class="control-buttons">
                <?php if ($has_api_key): ?>
                    <form method="post" style="display: inline;">
                        <button type="submit" name="fetch_weather" class="control-btn fetch" 
                                <?php echo !$has_active_locations ? 'disabled' : ''; ?>>
                            🔄 Fetch Current Weather
                        </button>
                    </form>
                <?php endif; ?>
                <a href="schedule.php" class="control-btn schedule">⏰ Schedule Updates</a>
                <a href="history.php" class="control-btn history">📊 View History</a>
                <a href="config.php" class="control-btn">⚙️ API Settings</a>
                <a href="../locations/index.php" class="control-btn">📍 Manage Locations</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card gradient-blue">
                    <span class="stat-number"><?php echo $active_locations_count; ?></span>
                    <span class="stat-label">Active Locations</span>
                </div>
                <div class="stat-card gradient-purple">
                    <span class="stat-number"><?php echo $stats['locations_with_data'] ?? 0; ?></span>
                    <span class="stat-label">Locations with Data</span>
                </div>
                <div class="stat-card gradient-green">
                    <span class="stat-number"><?php echo $stats['total_records'] ?? 0; ?></span>
                    <span class="stat-label">Records (24h)</span>
                </div>
                <div class="stat-card gradient-orange">
                    <span class="stat-number">
                        <?php 
                        if ($stats['latest_update']) {
                            echo date('H:i', strtotime($stats['latest_update']));
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </span>
                    <span class="stat-label">Last Update</span>
                </div>
            </div>
        </div>

        <div class="quick-actions-panel">
            <h2 class="section-title">Quick Actions</h2>
            <div class="quick-actions-grid">
                <div class="quick-action-card card-hover">
                    <div class="action-icon">📍</div>
                    <div class="action-content">
                        <h3>Location Management</h3>
                        <p>Manage your monitoring locations to start collecting weather data.</p>
                        <a href="../locations/index.php" class="action-link">
                            Manage Locations →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">⏰</div>
                    <div class="action-content">
                        <h3>Automated Updates</h3>
                        <p>Set up scheduled weather data collection for all active locations.</p>
                        <a href="schedule.php" class="action-link">
                            Configure Schedule →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">📊</div>
                    <div class="action-content">
                        <h3>Data Analysis</h3>
                        <p>View historical weather data and generate reports.</p>
                        <a href="history.php" class="action-link">
                            View History →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">⚙️</div>
                    <div class="action-content">
                        <h3>API Configuration</h3>
                        <p>Configure your OpenWeatherMap API key and settings.</p>
                        <a href="config.php" class="action-link">
                            API Settings →
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="weather-table-container">
            <div class="weather-table-header">
                <h2 class="section-title">Current Weather Conditions</h2>
                <div class="table-actions">
                    <span class="last-update badge">
                        <?php if ($stats['latest_update']): ?>
                            📅 Last updated: <?php echo date('M j, H:i', strtotime($stats['latest_update'])); ?>
                        <?php endif; ?>
                    </span>
                    <span class="record-count badge">
                        📍 <?php echo $latest_weather ? $latest_weather->rowCount() : 0; ?> locations
                    </span>
                </div>
            </div>
            
            <?php if ($latest_weather && $latest_weather->rowCount() > 0): ?>
                <div class="table-wrapper">
                    <table class="weather-data-table">
                        <thead>
                            <tr>
                                <th class="location-col">📍 Location</th>
                                <th class="time-col">🕐 Last Update</th>
                                <th class="temp-col">🌡️ Temp</th>
                                <th class="feels-like-col">💁 Feels Like</th>
                                <th class="humidity-col">💧 Humidity</th>
                                <th class="pressure-col">📊 Pressure</th>
                                <th class="wind-col">💨 Wind</th>
                                <th class="wind-dir-col">🧭 Direction</th>
                                <th class="condition-col">☁️ Condition</th>
                                <th class="visibility-col">👁️ Visibility</th>
                                <th class="clouds-col">☁️ Clouds</th>
                                <th class="rain-col">🌧️ Rain</th>
                                <th class="snow-col">❄️ Snow</th>
                                <th class="actions-col">⚡ Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($weather = $latest_weather->fetch(PDO::FETCH_ASSOC)): 
                                $locationModel->location_id = $weather['location_id'];
                                $location_stmt = $locationModel->read_single();
                                $location = $location_stmt->fetch();
                                
                                if (!$location) continue;
                                
                                // Use the actual database columns instead of raw_payload extraction
                                $metrics = [
                                    'temperature' => $weather['temp_celsius'],
                                    'feels_like' => $weather['feels_like_celsius'],
                                    'humidity' => $weather['humidity_percent'],
                                    'pressure' => $weather['pressure_hpa'],
                                    'wind_speed' => $weather['wind_speed_ms'],
                                    'wind_direction' => $weather['wind_direction_degrees'],
                                    'weather_condition' => $weather['weather_main'],
                                    'weather_description' => $weather['weather_description'],
                                    'visibility' => $weather['visibility_meters'],
                                    'cloudiness' => $weather['cloudiness_percent'],
                                    'rain_1h' => $weather['rainfall_mm_1h'],
                                    'snow_1h' => $weather['snowfall_mm_1h']
                                ];
                            ?>
                                <tr class="table-row-hover">
                                    <td class="location-col">
                                        <div class="location-info">
                                            <strong class="location-name"><?php echo htmlspecialchars($location['location_name']); ?></strong>
                                            <div class="location-details">
                                                <?php echo htmlspecialchars($location['city'] . ', ' . $location['state_province']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="time-col">
                                        <span class="timestamp"><?php echo date('M j, H:i', strtotime($weather['recorded_at_utc'])); ?></span>
                                    </td>
                                    <td class="temp-col">
                                        <?php if ($metrics['temperature'] !== null): ?>
                                            <span class="temp-value <?php echo $metrics['temperature'] > 25 ? 'temp-hot' : ($metrics['temperature'] < 10 ? 'temp-cold' : 'temp-mild'); ?>">
                                                <?php echo round($metrics['temperature']); ?>°C
                                            </span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="feels-like-col">
                                        <?php if ($metrics['feels_like'] !== null): ?>
                                            <span class="feels-like-value"><?php echo round($metrics['feels_like']); ?>°C</span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="humidity-col">
                                        <?php if ($metrics['humidity'] !== null): ?>
                                            <span class="humidity-value <?php echo $metrics['humidity'] > 80 ? 'humidity-high' : ($metrics['humidity'] < 30 ? 'humidity-low' : 'humidity-normal'); ?>">
                                                <?php echo $metrics['humidity']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pressure-col">
                                        <?php if ($metrics['pressure'] !== null): ?>
                                            <span class="pressure-value"><?php echo $metrics['pressure']; ?> hPa</span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="wind-col">
                                        <?php if ($metrics['wind_speed'] !== null): ?>
                                            <span class="wind-value <?php echo $metrics['wind_speed'] > 8 ? 'wind-high' : ($metrics['wind_speed'] > 4 ? 'wind-medium' : 'wind-light'); ?>">
                                                <?php echo round($metrics['wind_speed'], 1); ?> m/s
                                            </span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="wind-dir-col">
                                        <?php if ($metrics['wind_direction'] !== null): ?>
                                            <span class="wind-direction"><?php echo $metrics['wind_direction']; ?>°</span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="condition-col">
                                        <?php if ($metrics['weather_condition'] !== null): ?>
                                            <div class="condition-display">
                                                <span class="weather-icon">
                                                    <?php
                                                    $condition = strtolower($metrics['weather_condition'] ?? '');
                                                    $icon = '☀️';
                                                    if (strpos($condition, 'cloud') !== false) $icon = '☁️';
                                                    if (strpos($condition, 'rain') !== false) $icon = '🌧️';
                                                    if (strpos($condition, 'snow') !== false) $icon = '❄️';
                                                    if (strpos($condition, 'thunder') !== false) $icon = '⛈️';
                                                    if (strpos($condition, 'mist') !== false || strpos($condition, 'fog') !== false) $icon = '🌫️';
                                                    if (strpos($condition, 'drizzle') !== false) $icon = '🌦️';
                                                    echo $icon;
                                                    ?>
                                                </span>
                                                <span class="condition-text">
                                                    <?php echo ucfirst($metrics['weather_description'] ?? $metrics['weather_condition']); ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="visibility-col">
                                        <?php if ($metrics['visibility'] !== null): ?>
                                            <span class="visibility-value"><?php echo round($metrics['visibility'] / 1000, 1); ?> km</span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="clouds-col">
                                        <?php if ($metrics['cloudiness'] !== null): ?>
                                            <span class="cloudiness-value"><?php echo $metrics['cloudiness']; ?>%</span>
                                        <?php else: ?>
                                            <span class="no-data">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="rain-col">
                                        <?php if ($metrics['rain_1h'] !== null && $metrics['rain_1h'] > 0): ?>
                                            <span class="rain-value rainfall"><?php echo $metrics['rain_1h']; ?> mm</span>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="snow-col">
                                        <?php if ($metrics['snow_1h'] !== null && $metrics['snow_1h'] > 0): ?>
                                            <span class="snow-value snowfall"><?php echo $metrics['snow_1h']; ?> mm</span>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-col">
                                        <div class="action-buttons">
                                            <a href="history.php?location_id=<?php echo $weather['location_id']; ?>" 
                                               class="action-btn history" title="View History">
                                                📊
                                            </a>
                                            <a href="../locations/view.php?id=<?php echo $weather['location_id']; ?>" 
                                               class="action-btn location" title="Location Details">
                                                📍
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data-state">
                    <div class="no-data-icon">🌤️</div>
                    <h3>No Weather Data Available</h3>
                    <?php if (!$has_api_key): ?>
                        <p>❌ <strong>API key not configured.</strong> Set your OpenWeatherMap API key first.</p>
                        <div class="action-buttons-center">
                            <a href="config.php" class="control-btn">
                                ⚙️ Configure API Key
                            </a>
                        </div>
                    <?php elseif (!$has_active_locations): ?>
                        <p>❌ <strong>No active locations found.</strong> You need to add and activate locations first.</p>
                        <div class="action-buttons-center">
                            <a href="../locations/create.php" class="control-btn">
                                ➕ Add First Location
                            </a>
                            <a href="../locations/index.php" class="control-btn">
                                📍 Manage Locations
                            </a>
                        </div>
                    <?php else: ?>
                        <p>✅ You have <?php echo $active_locations_count; ?> active location(s), but no weather data has been collected yet.</p>
                        <div class="action-buttons-center">
                            <form method="post" style="display: inline;">
                                <button type="submit" name="fetch_weather" class="control-btn fetch">
                                    🔄 Fetch Weather Now
                                </button>
                            </form>
                            <a href="schedule.php" class="control-btn schedule">
                                ⏰ Setup Auto-Update
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 5 minutes if we have data
        <?php if ($latest_weather && $latest_weather->rowCount() > 0): ?>
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        <?php endif; ?>

        // Add row hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table-row-hover');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>
