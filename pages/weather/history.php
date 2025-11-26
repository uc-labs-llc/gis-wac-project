<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Location.php';
require_once '../../models/WeatherData.php';
require_once '../../services/WeatherService.php';


$database = new Database();
$db = $database->connect();
$weatherService = new WeatherService($db, 'your_api_key');

// Get filter parameters
$location_id = $_GET['location_id'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$limit = $_GET['limit'] ?? 100;

// Get all locations for filter dropdown
$locationModel = new Location($db);
$locations = $locationModel->read();

// Get filtered weather data
$weatherData = new WeatherData($db);
if ($location_id) {
    $weather_history = $weatherData->get_by_date_range($location_id, $start_date, $end_date);
} else {
    $weather_history = $weatherData->get_by_location($location_id ?: null, $limit);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weather History - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>📊 Weather History</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Weather Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="control-panel">
            <h2>Filter Weather Data</h2>
            <form method="get" class="schedule-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="location_id">Location</label>
                        <select id="location_id" name="location_id">
                            <option value="">All Locations</option>
                            <?php while ($location = $locations->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $location['location_id']; ?>" 
                                    <?php echo $location_id == $location['location_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['location_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="limit">Records Limit</label>
                        <select id="limit" name="limit">
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250</option>
                            <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="control-btn">Apply Filters</button>
                <a href="history.php" class="control-btn" style="background: #718096;">Clear Filters</a>
            </form>
        </div>

        <div class="weather-container">
            <!-- Weather Data Table -->
            <div class="weather-data" style="grid-column: 1 / -1;">
                <h2>Historical Weather Data 
                    <?php if ($location_id): ?>
                        for Selected Location
                    <?php else: ?>
                        (Latest <?php echo $limit; ?> records)
                    <?php endif; ?>
                </h2>
                
                <?php if ($weather_history->rowCount() > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f7fafc;">
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Location</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Time</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Temperature</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Humidity</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Pressure</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Wind</th>
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($weather = $weather_history->fetch(PDO::FETCH_ASSOC)): 
                                    $locationModel = new Location($db);
                                    $locationModel->location_id = $weather['location_id'];
                                    $location_stmt = $locationModel->read_single();
                                    $location = $location_stmt->fetch();
                                    
                                    $metrics = $weatherService->extract_metrics($weather['raw_data']);
                                ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($location['location_name']); ?></td>
                                        <td style="padding: 12px;"><?php echo date('M j, H:i', strtotime($weather['recorded_at'])); ?></td>
                                        <td style="padding: 12px; color: #e53e3e; font-weight: bold;">
                                            <?php echo $metrics ? round($metrics['temperature']) . '°C' : 'N/A'; ?>
                                        </td>
                                        <td style="padding: 12px; color: #4299e1;">
                                            <?php echo $metrics ? $metrics['humidity'] . '%' : 'N/A'; ?>
                                        </td>
                                        <td style="padding: 12px; color: #38a169;">
                                            <?php echo $metrics ? $metrics['pressure'] . ' hPa' : 'N/A'; ?>
                                        </td>
                                        <td style="padding: 12px; color: #ed8936;">
                                            <?php echo $metrics ? round($metrics['wind_speed'], 1) . ' m/s' : 'N/A'; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php echo $metrics ? ucfirst($metrics['weather_description']) : 'N/A'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No Weather Data Found</h3>
                        <p>Try adjusting your filters or fetch weather data first.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
