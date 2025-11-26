<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Location.php';
require_once '../../models/ArchiveSettings.php';
require_once '../../services/ArchiveScheduler.php';


$database = new Database();
$db = $database->connect();
$archiveScheduler = new ArchiveScheduler($db);
$archiveModel = new ArchiveSettings($db);

$message = '';
$message_type = '';

// Get all active locations for dropdown
$locationModel = new Location($db);
$locations = $locationModel->read();

// Get selected location from GET or POST
$selected_location_id = $_GET['location_id'] ?? $_POST['location_id'] ?? '';
$current_settings = null;
$archive_stats = null;

// If location is selected, load its settings and stats
if ($selected_location_id) {
    $current_settings = $archiveModel->get_by_location($selected_location_id);
    $archive_stats = $archiveScheduler->get_archive_stats($selected_location_id);
    
    // If no settings exist, create default ones
    if (!$current_settings) {
        $defaults = ArchiveSettings::get_defaults();
        $current_settings = array_merge(['location_id' => $selected_location_id], $defaults);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $archiveSettings = new ArchiveSettings($db);
    
    $archiveSettings->location_id = $_POST['location_id'];
    $archiveSettings->archive_frequency = $_POST['archive_frequency'];
    $archiveSettings->realtime_keep_hours = $_POST['realtime_keep_hours'] ?? 24;
    $archiveSettings->hourly_keep_days = $_POST['hourly_keep_days'] ?? 30;
    $archiveSettings->daily_keep_years = $_POST['daily_keep_years'] ?? 2;
    $archiveSettings->weekly_keep_years = $_POST['weekly_keep_years'] ?? 5;
    $archiveSettings->monthly_keep_years = $_POST['monthly_keep_years'] ?? 10;
    $archiveSettings->yearly_keep_years = $_POST['yearly_keep_years'] ?? 20;
    $archiveSettings->enabled = isset($_POST['enabled']) ? true : false;
    
    if ($archiveSettings->save()) {
        $message = 'Archive settings saved successfully!';
        $message_type = 'success';
        $current_settings = $archiveModel->get_by_location($selected_location_id);
    } else {
        $message = 'Error saving archive settings.';
        $message_type = 'error';
    }
}

// Handle manual archive trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_archive'])) {
    $result = $archiveScheduler->process_archive($selected_location_id);
    $message = $result['message'];
    $message_type = 'success';
    $archive_stats = $archiveScheduler->get_archive_stats($selected_location_id);
}

// Handle global archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_global_archive'])) {
    $result = $archiveScheduler->process_archive();
    $message = $result['message'];
    $message_type = 'success';
}

// Get overall archive stats
$overall_stats = $archiveScheduler->get_archive_stats();
$storage_info = $archiveScheduler->get_storage_info();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Archive Settings - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
    <script>
        function updateSettingsVisibility() {
            const frequency = document.getElementById('archive_frequency').value;
            
            // Hide all setting sections
            document.querySelectorAll('.frequency-settings').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show relevant section
            const activeSection = document.getElementById(frequency + '-settings');
            if (activeSection) {
                activeSection.style.display = 'block';
            }
        }
        
        function onLocationChange() {
            const locationSelect = document.getElementById('location_select');
            const locationId = locationSelect.value;
            if (locationId) {
                window.location.href = 'archive_settings.php?location_id=' + locationId;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSettingsVisibility();
        });
    </script>
</head>
<body>
    <div class="container wide-container">
        <div class="header">
            <div class="user-info">
                <h1>📦 Archive Settings</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Weather Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert-banner <?php echo $message_type; ?>">
                <div class="alert-icon">
                    <?php echo $message_type === 'success' ? '✅' : '❌'; ?>
                </div>
                <div class="alert-content">
                    <strong><?php echo htmlspecialchars($message); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Overall Stats -->
        <div class="control-panel">
            <h2>Archive Overview</h2>
            <div class="stats-grid">
                <div class="stat-card gradient-blue">
                    <span class="stat-number"><?php echo $overall_stats['total_records'] ?? 0; ?></span>
                    <span class="stat-label">Total Records</span>
                </div>
                <div class="stat-card gradient-purple">
                    <span class="stat-number"><?php echo $overall_stats['locations_with_data'] ?? 0; ?></span>
                    <span class="stat-label">Locations with Data</span>
                </div>
                <div class="stat-card gradient-green">
                    <span class="stat-number">
                        <?php 
                        if ($overall_stats['oldest_record']) {
                            echo date('M Y', strtotime($overall_stats['oldest_record']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </span>
                    <span class="stat-label">Oldest Data</span>
                </div>
                <div class="stat-card gradient-orange">
                    <span class="stat-number"><?php echo $storage_info['total_size'] ?? 'N/A'; ?></span>
                    <span class="stat-label">Total Storage</span>
                </div>
            </div>
        </div>

        <!-- Location Selection -->
        <div class="control-panel">
            <h2>Location Selection</h2>
            <div class="form-group">
                <label for="location_select">Select Location:</label>
                <select id="location_select" name="location_id" onchange="onLocationChange()" class="form-control">
                    <option value="">-- Choose a Location --</option>
                    <?php while ($location = $locations->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $location['location_id']; ?>" 
                            <?php echo $selected_location_id == $location['location_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['location_name'] . ' - ' . $location['city'] . ', ' . $location['state_province']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <?php if ($selected_location_id && $current_settings): ?>
        <!-- Archive Settings Form -->
        <div class="control-panel">
            <div class="panel-header">
                <h2>Archive Settings for Selected Location</h2>
                <?php if ($archive_stats): ?>
                    <div class="location-stats">
                        <span class="badge"><?php echo $archive_stats['total_records'] ?? 0; ?> records</span>
                        <span class="badge">Since <?php echo $archive_stats['oldest_record'] ? date('M Y', strtotime($archive_stats['oldest_record'])) : 'N/A'; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" class="archive-form">
                <input type="hidden" name="location_id" value="<?php echo $selected_location_id; ?>">
                
                <div class="form-section">
                    <h3>Archive Frequency</h3>
                    <div class="form-group">
                        <label for="archive_frequency">Data Retention Strategy:</label>
                        <select id="archive_frequency" name="archive_frequency" onchange="updateSettingsVisibility()" class="form-control">
                            <option value="realtime" <?php echo $current_settings['archive_frequency'] === 'realtime' ? 'selected' : ''; ?>>Real-time (Keep all data for specified hours)</option>
                            <option value="hourly" <?php echo $current_settings['archive_frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly (Keep one record per hour)</option>
                            <option value="daily" <?php echo $current_settings['archive_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily (Keep one record per day)</option>
                            <option value="weekly" <?php echo $current_settings['archive_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly (Keep one record per week)</option>
                            <option value="monthly" <?php echo $current_settings['archive_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly (Keep one record per month)</option>
                            <option value="yearly" <?php echo $current_settings['archive_frequency'] === 'yearly' ? 'selected' : ''; ?>>Yearly (Keep one record per year)</option>
                        </select>
                    </div>
                </div>

                <!-- Real-time Settings -->
                <div id="realtime-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Real-time Settings</h3>
                    <div class="form-group">
                        <label for="realtime_keep_hours">Keep data for (hours):</label>
                        <input type="number" id="realtime_keep_hours" name="realtime_keep_hours" 
                               value="<?php echo $current_settings['realtime_keep_hours'] ?? 24; ?>" 
                               min="1" max="8760" class="form-control">
                        <small class="form-help">Data older than this will be automatically deleted</small>
                    </div>
                </div>

                <!-- Hourly Settings -->
                <div id="hourly-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Hourly Settings</h3>
                    <div class="form-group">
                        <label for="hourly_keep_days">Keep hourly data for (days):</label>
                        <input type="number" id="hourly_keep_days" name="hourly_keep_days" 
                               value="<?php echo $current_settings['hourly_keep_days'] ?? 30; ?>" 
                               min="1" max="3650" class="form-control">
                    </div>
                </div>

                <!-- Daily Settings -->
                <div id="daily-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Daily Settings</h3>
                    <div class="form-group">
                        <label for="daily_keep_years">Keep daily data for (years):</label>
                        <input type="number" id="daily_keep_years" name="daily_keep_years" 
                               value="<?php echo $current_settings['daily_keep_years'] ?? 2; ?>" 
                               min="1" max="100" class="form-control">
                    </div>
                </div>

                <!-- Weekly Settings -->
                <div id="weekly-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Weekly Settings</h3>
                    <div class="form-group">
                        <label for="weekly_keep_years">Keep weekly data for (years):</label>
                        <input type="number" id="weekly_keep_years" name="weekly_keep_years" 
                               value="<?php echo $current_settings['weekly_keep_years'] ?? 5; ?>" 
                               min="1" max="100" class="form-control">
                    </div>
                </div>

                <!-- Monthly Settings -->
                <div id="monthly-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Monthly Settings</h3>
                    <div class="form-group">
                        <label for="monthly_keep_years">Keep monthly data for (years):</label>
                        <input type="number" id="monthly_keep_years" name="monthly_keep_years" 
                               value="<?php echo $current_settings['monthly_keep_years'] ?? 10; ?>" 
                               min="1" max="100" class="form-control">
                    </div>
                </div>

                <!-- Yearly Settings -->
                <div id="yearly-settings" class="frequency-settings form-section" style="display: none;">
                    <h3>Yearly Settings</h3>
                    <div class="form-group">
                        <label for="yearly_keep_years">Keep yearly data for (years):</label>
                        <input type="number" id="yearly_keep_years" name="yearly_keep_years" 
                               value="<?php echo $current_settings['yearly_keep_years'] ?? 20; ?>" 
                               min="1" max="100" class="form-control">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled" 
                               <?php echo ($current_settings['enabled'] ?? true) ? 'checked' : ''; ?>>
                        <label for="enabled">Enable automatic archiving for this location</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="save_settings" class="control-btn primary">💾 Save Settings</button>
                    <button type="submit" name="run_archive" class="control-btn accent">🔄 Run Archive Now</button>
                    <a href="archive_settings.php" class="control-btn secondary">🔄 Reset</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions-panel">
            <h2 class="section-title">Archive Management</h2>
            <div class="quick-actions-grid">
                <div class="quick-action-card card-hover">
                    <div class="action-icon">📊</div>
                    <div class="action-content">
                        <h3>View Archive History</h3>
                        <p>Browse and analyze historical weather data across all locations.</p>
                        <a href="history.php" class="action-link">
                            View History →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">⚙️</div>
                    <div class="action-content">
                        <h3>System Settings</h3>
                        <p>Configure global archive settings and automation schedules.</p>
                        <a href="archive_settings.php" class="action-link">
                            System Settings →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">📈</div>
                    <div class="action-content">
                        <h3>Storage Analytics</h3>
                        <p>Monitor database usage and archive performance metrics.</p>
                        <a href="history.php" class="action-link">
                            View Analytics →
                        </a>
                    </div>
                </div>

                <div class="quick-action-card card-hover">
                    <div class="action-icon">🔄</div>
                    <div class="action-content">
                        <h3>Run Global Archive</h3>
                        <p>Execute archiving process for all locations immediately.</p>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="run_global_archive" value="1">
                            <button type="submit" class="action-link" style="background: none; border: none; color: #667eea; cursor: pointer; padding: 0;">
                                Run Global Archive →
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize visibility based on current frequency
        document.addEventListener('DOMContentLoaded', function() {
            updateSettingsVisibility();
        });
    </script>
</body>
</html>
