<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Config.php';


$database = new Database();
$db = $database->connect();
$config = new Config($db);

$message = '';
$message_type = ''; // success, error, warning

// Handle configuration update
if ($_POST) {
    try {
        $api_key = trim($_POST['api_key'] ?? '');
        $units = $_POST['units'] ?? 'metric';
        $update_interval = $_POST['update_interval'] ?? '15 minutes';
        
        // Validate API key if provided
        if (!empty($api_key) && (strlen($api_key) < 20 || strlen($api_key) > 100)) {
            throw new Exception("API key appears invalid. OpenWeatherMap keys are typically 32 characters.");
        }
        
        // Save to database
        $config->set('openweathermap_api_key', $api_key, $_SESSION['user_id']);
        $config->set('weather_units', $units, $_SESSION['user_id']);
        $config->set('weather_update_interval', $update_interval, $_SESSION['user_id']);
        
        $message = "Configuration updated successfully!";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get current configuration
$current_api_key = $config->get('openweathermap_api_key');
$current_units = $config->get('weather_units') ?: 'metric';
$current_interval = $config->get('weather_update_interval') ?: '15 minutes';

// Get system stats
$weather_count = $db->query("SELECT COUNT(*) FROM weather_history")->fetchColumn();
$location_count = $db->query("SELECT COUNT(*) FROM locations WHERE status = 'ACTIVE'")->fetchColumn();
$oldest_record = $db->query("SELECT MIN(recorded_at_utc) FROM weather_history")->fetchColumn();

// Test API connection if requested
$api_test_result = '';
if (isset($_POST['test_api'])) {
    $api_test_result = test_api_connection($current_api_key);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weather Configuration - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>⚙️ Weather Configuration</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Weather Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="background: <?php echo $message_type == 'error' ? '#fed7d7' : '#c6f6d5'; ?>; 
                        color: <?php echo $message_type == 'error' ? '#c53030' : '#276749'; ?>; 
                        padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($api_test_result): ?>
            <div style="background: #e9d8fd; color: #553c9a; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>API Test Result:</strong> <?php echo htmlspecialchars($api_test_result); ?>
            </div>
        <?php endif; ?>

        <div class="weather-container">
            <!-- API Configuration -->
            <div class="weather-stats">
                <h2>OpenWeatherMap API</h2>
                
                <div class="weather-card">
                    <h3>🔑 API Key Configuration</h3>
                    <p>Set your OpenWeatherMap API key to enable weather data collection.</p>
                    
                    <form method="post" class="schedule-form">
                        <div class="form-group">
                            <label for="api_key">API Key</label>
                            <input type="password" id="api_key" name="api_key" 
                                   placeholder="Enter your 32-character OpenWeatherMap API key" 
                                   value="<?php echo htmlspecialchars($current_api_key ?? ''); ?>"
                                   style="font-family: monospace;">
                            <small style="color: #718096; display: block; margin-top: 5px;">
                                <?php if ($current_api_key): ?>
                                    ✅ API key is set (<?php echo strlen($current_api_key); ?> characters)
                                <?php else: ?>
                                    ❌ No API key configured
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="units">Units</label>
                            <select id="units" name="units">
                                <option value="metric" <?php echo $current_units == 'metric' ? 'selected' : ''; ?>>Metric (°C, m/s, hPa)</option>
                                <option value="imperial" <?php echo $current_units == 'imperial' ? 'selected' : ''; ?>>Imperial (°F, mph, inHg)</option>
                                <option value="standard" <?php echo $current_units == 'standard' ? 'selected' : ''; ?>>Standard (K, m/s, hPa)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="update_interval">Default Update Interval</label>
                            <select id="update_interval" name="update_interval">
                                <option value="5 minutes" <?php echo $current_interval == '5 minutes' ? 'selected' : ''; ?>>Every 5 Minutes</option>
                                <option value="15 minutes" <?php echo $current_interval == '15 minutes' ? 'selected' : ''; ?>>Every 15 Minutes</option>
                                <option value="30 minutes" <?php echo $current_interval == '30 minutes' ? 'selected' : ''; ?>>Every 30 Minutes</option>
                                <option value="1 hour" <?php echo $current_interval == '1 hour' ? 'selected' : ''; ?>>Every Hour</option>
                                <option value="2 hours" <?php echo $current_interval == '2 hours' ? 'selected' : ''; ?>>Every 2 Hours</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="control-btn">💾 Save Configuration</button>
                            <?php if ($current_api_key): ?>
                                <button type="submit" name="test_api" class="control-btn" style="background: #4299e1;">
                                    🧪 Test API Connection
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="weather-card">
                    <h3>📋 API Usage & Limits</h3>
                    <div class="weather-metrics">
                        <div class="metric">
                            <span class="metric-value" style="color: #667eea;">1,000</span>
                            <span class="metric-label">Calls/Day</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value" style="color: #48bb78;">60</span>
                            <span class="metric-label">Calls/Min</span>
                        </div>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.9em; color: #718096;">
                        <strong>Free tier limits:</strong> 1,000 calls per day, 60 calls per minute<br>
                        <strong>Current usage:</strong> Monitor at <a href="https://home.openweathermap.org/api_keys" target="_blank">OpenWeatherMap</a>
                    </p>
                </div>
            </div>

            <!-- System Information -->
            <div class="weather-data">
                <h2>System Information</h2>
                
                <div class="weather-card">
                    <h3>🖥️ Server Status</h3>
                    <div class="weather-metrics">
                        <div class="metric">
                            <span class="metric-value" style="color: #38a169;">Online</span>
                            <span class="metric-label">Status</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value" style="color: #4299e1;"><?php echo phpversion(); ?></span>
                            <span class="metric-label">PHP Version</span>
                        </div>
                    </div>
                </div>

                <div class="weather-card">
                    <h3>📊 Data Statistics</h3>
                    <div class="weather-metrics">
                        <div class="metric">
                            <span class="metric-value" style="color: #ed8936;"><?php echo $weather_count; ?></span>
                            <span class="metric-label">Weather Records</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value" style="color: #9f7aea;"><?php echo $location_count; ?></span>
                            <span class="metric-label">Active Locations</span>
                        </div>
                    </div>
                    <?php if ($oldest_record): ?>
                        <p style="margin-top: 10px; font-size: 0.9em; color: #718096;">
                            Data collection started: <?php echo date('M j, Y', strtotime($oldest_record)); ?>
                        </p>
                    <?php else: ?>
                        <p style="margin-top: 10px; font-size: 0.9em; color: #718096;">
                            No weather data collected yet
                        </p>
                    <?php endif; ?>
                </div>

                <div class="weather-card">
                    <h3>🔧 System Actions</h3>
                    <div style="display: flex; gap: 10px; flex-direction: column;">
                        <form method="post" style="display: inline;">
                            <button type="submit" name="clear_cache" class="control-btn" style="background: #ed8936; width: 100%;">
                                🧹 Clear Cache
                            </button>
                        </form>
                        <button onclick="resetConfiguration()" class="control-btn" style="background: #e53e3e; width: 100%;">
                            🔄 Reset Configuration
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="control-panel" style="margin-top: 30px;">
            <h2>❓ Getting Started</h2>
            <div class="weather-card">
                <h3>OpenWeatherMap API Key</h3>
                <p>To get an API key:</p>
                <ol style="color: #718096; margin-left: 20px;">
                    <li>Go to <a href="https://openweathermap.org/api" target="_blank">OpenWeatherMap API</a></li>
                    <li>Sign up for a free account</li>
                    <li>Navigate to API Keys section in your account</li>
                    <li>Copy your API key (typically 32 characters)</li>
                    <li>Paste it in the form above and click "Save Configuration"</li>
                </ol>
                <p style="margin-top: 10px; color: #718096;">
                    <strong>Note:</strong> The free tier includes 1,000 API calls per day, which is sufficient for monitoring 5-10 locations every 15 minutes.
                </p>
            </div>
        </div>
    </div>

    <script>
        function resetConfiguration() {
            if (confirm('Are you sure you want to reset all weather configuration? This will clear the API key and settings.')) {
                // This would typically be a form submission to a reset endpoint
                alert('Reset functionality would be implemented here. For now, manually clear the API key field and save.');
            }
        }

        // Show/hide API key
        document.addEventListener('DOMContentLoaded', function() {
            const apiKeyInput = document.getElementById('api_key');
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.textContent = '👁️';
            toggleButton.style.marginLeft = '10px';
            toggleButton.style.background = 'none';
            toggleButton.style.border = 'none';
            toggleButton.style.cursor = 'pointer';
            toggleButton.style.fontSize = '1.2em';
            
            toggleButton.addEventListener('click', function() {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                    toggleButton.textContent = '🔒';
                } else {
                    apiKeyInput.type = 'password';
                    toggleButton.textContent = '👁️';
                }
            });
            
            apiKeyInput.parentNode.appendChild(toggleButton);
        });
    </script>
</body>
</html>

<?php
function test_api_connection($api_key) {
    if (empty($api_key)) {
        return "No API key provided";
    }
    
    $test_url = "https://api.openweathermap.org/data/2.5/weather?q=London,UK&appid=" . $api_key;
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            return "✅ API connection successful! Connected to OpenWeatherMap. Location: " . ($data['name'] ?? 'Unknown');
        } else {
            $error_data = json_decode($response, true);
            $error_msg = $error_data['message'] ?? 'Unknown error';
            
            if ($http_code === 401) {
                return "❌ Invalid API key: " . $error_msg;
            } elseif ($http_code === 429) {
                return "⚠️ Rate limit exceeded: " . $error_msg;
            } else {
                return "❌ API error ($http_code): " . $error_msg;
            }
        }
    } catch (Exception $e) {
        return "❌ Connection failed: " . $e->getMessage();
    }
}
?>
