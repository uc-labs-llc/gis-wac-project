<?php
// Optional: Keep these debug lines at the very top until all errors are gone
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Core Dependencies
require_once '../../config/database.php';
require_once '../../models/Location.php';
require_once '../../models/AlertRule.php';

$database = new Database();
$db = $database->connect();

// Instantiate models
$location = new Location($db);
$alertRule = new AlertRule($db);

$error = '';
$success = '';

// --- Configuration Data for Dropdowns ---
$targetMetrics = [
    'temperature' => 'Air Temperature (°C)',
    'humidity' => 'Humidity (%)',
    'wind_speed' => 'Wind Speed (m/s)',
    'wind_gust' => 'Wind Gust Speed (m/s)',
    'pressure' => 'Atmospheric Pressure (hPa)',
    'rain_1h' => 'Rainfall (1hr total, mm)',
    'snow_1h' => 'Snowfall (1hr total, mm)',
    'visibility' => 'Visibility (meters)',
];

$operators = [
    '>' => 'Greater than (>)',
    '<' => 'Less than (<)',
    '=' => 'Equal to (=)',
    '>=' => 'Greater than or equal to (>=)',
    '<=' => 'Less than or equal to (<=)',
    '!=' => 'Not equal to (!=)',
    'BETWEEN' => 'Between (requires 2 values)',
];

$severityLevels = [
    'LOW' => 'Low',
    'MEDIUM' => 'Medium',
    'HIGH' => 'High',
    'CRITICAL' => 'Critical',
];

// Fetch all locations for the Location Dropdown
$locations_stmt = $location->read();
$all_locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Handle Form Submission ---
if ($_POST) {
    try {
        // Map form data to AlertRule object
        $alertRule->location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
        $alertRule->rule_name = $_POST['rule_name'] ?? '';
        $alertRule->rule_description = !empty($_POST['rule_description']) ? $_POST['rule_description'] : null;
        $alertRule->target_metric = $_POST['target_metric'] ?? '';
        $alertRule->operator = $_POST['operator'] ?? '';
        $alertRule->threshold_value = $_POST['threshold_value'] ?? '';
        $alertRule->threshold_value_2 = !empty($_POST['threshold_value_2']) ? $_POST['threshold_value_2'] : null;
        $alertRule->severity_level = $_POST['severity_level'] ?? 'MEDIUM';
        $alertRule->condition_type = $_POST['condition_type'] ?? 'INSTANT';
        $alertRule->duration_minutes = !empty($_POST['duration_minutes']) ? $_POST['duration_minutes'] : null;
        $alertRule->message_template = $_POST['message_template'] ?? '';
        $alertRule->custom_subject = !empty($_POST['custom_subject']) ? $_POST['custom_subject'] : null;
        $alertRule->cooldown_period_minutes = (int)($_POST['cooldown_period_minutes'] ?? 60);
        $alertRule->is_active = isset($_POST['is_active']);
        
        // Validation 
        if (empty($alertRule->rule_name) || empty($alertRule->target_metric) || empty($alertRule->operator) || empty($alertRule->threshold_value) || empty($alertRule->message_template)) {
            throw new Exception('Please fill in all required fields (Rule Name, Metric, Operator, Threshold, Message).');
        }

        if ($alertRule->operator === 'BETWEEN' && empty($alertRule->threshold_value_2)) {
            throw new Exception('The BETWEEN operator requires a second threshold value.');
        }
        
        // Ensure threshold values are numeric if they are meant to be so
        if (!is_numeric($alertRule->threshold_value)) {
            throw new Exception('Threshold Value 1 must be a valid number.');
        }
        if ($alertRule->operator === 'BETWEEN' && !is_numeric($alertRule->threshold_value_2)) {
            throw new Exception('Threshold Value 2 must be a valid number for BETWEEN operator.');
        }

        // Create the rule
        if ($alertRule->create()) {
            $success = 'Alert Rule "' . htmlspecialchars($alertRule->rule_name) . '" created successfully!';
            // Clear POST data for a fresh form
            $_POST = []; 
        } else {
            $error = 'Failed to create Alert Rule. Please check your data and try again.';
        }
    } catch (Exception $e) {
        // Log the error for development/debugging
        error_log("Alert Rule Creation Error: " . $e->getMessage());
        $error = 'Error: ' . $e->getMessage();
    }
}

// Re-fetch locations and re-instantiate alertRule properties for a clean display on success
if (empty($_POST)) {
    $alertRule->rule_name = '';
    $alertRule->location_id = '';
    $alertRule->rule_description = '';
    $alertRule->target_metric = '';
    $alertRule->operator = '';
    $alertRule->threshold_value = '';
    $alertRule->threshold_value_2 = '';
    $alertRule->severity_level = 'MEDIUM';
    $alertRule->cooldown_period_minutes = 60;
    $alertRule->custom_subject = '';
    $alertRule->is_active = true;
    $alertRule->message_template = 'A weather alert has been triggered for {{LOCATION_NAME}}! Current {{TARGET_METRIC}} is {{CURRENT_VALUE}} which is {{OPERATOR}} {{THRESHOLD_VALUE}}.';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Alert Rule - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 15px 15px;
        }

        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info h1 {
            font-size: 2.2em;
            font-weight: 300;
            margin: 0;
        }

        .user-details {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fff4;
            color: #276749;
            border-left-color: #48bb78;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border-left-color: #f56565;
        }

        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .form-header {
            background: #f8fafc;
            padding: 25px;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-header h2 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-content {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-section {
            grid-column: 1 / -1;
            margin: 30px 0 20px 0;
            padding: 0;
            border: none;
        }

        .form-section h3 {
            color: var(--dark-color);
            font-size: 1.3em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: var(--primary-color);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
            background: white;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 0.85em;
            color: var(--light-color);
            line-height: 1.4;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-group {
            grid-column: 1 / -1;
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1em;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: var(--light-color);
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .required::after {
            content: " *";
            color: var(--danger-color);
        }

        .metric-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #e9d8fd;
            color: #6b46c1;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 5px;
        }

        .severity-preview {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 10px;
        }

        .severity-low { background: #c6f6d5; color: #276749; }
        .severity-medium { background: #fefcbf; color: #744210; }
        .severity-high { background: #fed7d7; color: #c53030; }
        .severity-critical { background: #fed7d7; color: #c53030; border: 2px solid #c53030; }

        .condition-preview {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.95em;
        }

        .template-variables {
            background: #f0fff4;
            border: 1px solid #c6f6d5;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .template-variables h4 {
            margin: 0 0 10px 0;
            color: #276749;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .variable-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .variable-tag {
            background: white;
            border: 1px solid #c6f6d5;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 0.8em;
            font-family: 'Courier New', monospace;
            color: #276749;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-group.full-width {
                grid-column: 1;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .user-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="user-info">
                <h1><i class="fas fa-plus-circle"></i> Create New Alert Rule</h1>
                <div class="user-details">
                    <a href="index.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Back to Alert Rules
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="alertRuleForm">
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-cogs"></i> Rule Configuration</h2>
                    <p style="margin: 10px 0 0 0; color: var(--light-color);">Create a new weather alert rule by filling out the form below.</p>
                </div>

                <div class="form-content">
                    <div class="form-grid">
                        
                        <div class="form-section">
                            <h3><i class="fas fa-fingerprint"></i> Rule Identification</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="rule_name" class="required">Rule Name</label>
                            <input type="text" id="rule_name" name="rule_name" 
                                   value="<?php echo htmlspecialchars($_POST['rule_name'] ?? ''); ?>" 
                                   placeholder="e.g., High Temperature Alert"
                                   required>
                            <span class="form-hint">A descriptive name for this alert rule</span>
                        </div>

                        <div class="form-group">
                            <label for="location_id">Target Location</label>
                            <select id="location_id" name="location_id">
                                <option value="">-- All Locations (Global Rule) --</option>
                                <?php foreach ($all_locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_id']); ?>"
                                        <?php echo (($_POST['location_id'] ?? '') == $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['location_name']) . ' (' . htmlspecialchars($loc['city']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">Leave blank to apply to all locations</span>
                        </div>

                        <div class="form-group full-width">
                            <label for="rule_description">Rule Description</label>
                            <textarea id="rule_description" name="rule_description" rows="2" 
                                      placeholder="Optional description of what this alert monitors..."><?php echo htmlspecialchars($_POST['rule_description'] ?? ''); ?></textarea>
                            <span class="form-hint">Describe the purpose and context of this alert rule</span>
                        </div>

                        <div class="form-section">
                            <h3><i class="fas fa-bolt"></i> Alert Condition</h3>
                        </div>

                        <div class="form-group">
                            <label for="target_metric" class="required">Weather Element</label>
                            <select id="target_metric" name="target_metric" required>
                                <option value="">-- Select Metric --</option>
                                <?php foreach ($targetMetrics as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"
                                        <?php echo (($_POST['target_metric'] ?? '') == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">The weather parameter to monitor</span>
                        </div>

                        <div class="form-group">
                            <label for="operator" class="required">Operator</label>
                            <select id="operator" name="operator" required 
                                    onchange="toggleThreshold2(this.value)">
                                <option value="">-- Select Operator --</option>
                                <?php foreach ($operators as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"
                                        <?php echo (($_POST['operator'] ?? '') == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">Comparison operator for the condition</span>
                        </div>

                        <div class="form-group">
                            <label for="threshold_value" class="required">Threshold Value 1</label>
                            <input type="text" id="threshold_value" name="threshold_value" 
                                   value="<?php echo htmlspecialchars($_POST['threshold_value'] ?? ''); ?>" 
                                   placeholder="e.g., 30, 0.5, 1000"
                                   required>
                            <span class="form-hint">Numeric value for comparison</span>
                        </div>

                        <div class="form-group" id="threshold_2_group" 
                             style="display: <?php echo (($_POST['operator'] ?? '') == 'BETWEEN') ? 'block' : 'none'; ?>;">
                            <label for="threshold_value_2">Threshold Value 2</label>
                            <input type="text" id="threshold_value_2" name="threshold_value_2" 
                                   value="<?php echo htmlspecialchars($_POST['threshold_value_2'] ?? ''); ?>"
                                   placeholder="e.g., 40, 1.5, 2000">
                            <span class="form-hint">Second value for BETWEEN operator</span>
                        </div>

                        <div class="form-group full-width">
                            <div class="condition-preview" id="conditionPreview">
                                <strong>Condition Preview:</strong> 
                                <span id="previewText">Select metric and operator to see preview</span>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-cog"></i> Alert Settings</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="severity_level" class="required">Severity Level</label>
                            <select id="severity_level" name="severity_level" required>
                                <?php foreach ($severityLevels as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"
                                        <?php echo (($_POST['severity_level'] ?? 'MEDIUM') == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                        <span class="severity-preview severity-<?php echo strtolower($key); ?>">
                                            <?php echo $key; ?>
                                        </span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">Importance level of this alert</span>
                        </div>

                        <div class="form-group">
                            <label for="cooldown_period_minutes" class="required">Cooldown Period (Minutes)</label>
                            <input type="number" id="cooldown_period_minutes" name="cooldown_period_minutes" min="0"
                                   value="<?php echo htmlspecialchars($_POST['cooldown_period_minutes'] ?? 60); ?>" 
                                   required>
                            <span class="form-hint">Time before this alert can be re-triggered</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="custom_subject">Custom Alert Subject</label>
                            <input type="text" id="custom_subject" name="custom_subject" 
                                   value="<?php echo htmlspecialchars($_POST['custom_subject'] ?? ''); ?>"
                                   placeholder="e.g., ⚠️ High Temperature Warning">
                            <span class="form-hint">Optional custom subject line for alerts</span>
                        </div>

                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?php echo isset($_POST['is_active']) ? 'checked' : (empty($_POST) ? 'checked' : ''); ?>>
                                <label for="is_active">Rule is Active</label>
                            </div>
                            <span class="form-hint">Uncheck to create the rule in inactive state</span>
                        </div>

                        <div class="form-section">
                            <h3><i class="fas fa-envelope"></i> Alert Message</h3>
                        </div>

                        <div class="form-group full-width">
                            <label for="message_template" class="required">Message Template</label>
                            <textarea id="message_template" name="message_template" rows="4" 
                                      placeholder="Customize the alert message using available variables..."
                                      required><?php echo htmlspecialchars($_POST['message_template'] ?? 'A weather alert has been triggered for {{LOCATION_NAME}}! Current {{TARGET_METRIC}} is {{CURRENT_VALUE}} which is {{OPERATOR}} {{THRESHOLD_VALUE}}.'); ?></textarea>
                            <span class="form-hint">The message template for triggered alerts</span>
                            
                            <div class="template-variables">
                                <h4><i class="fas fa-code"></i> Available Variables</h4>
                                <div class="variable-list">
                                    <span class="variable-tag">{{LOCATION_NAME}}</span>
                                    <span class="variable-tag">{{TARGET_METRIC}}</span>
                                    <span class="variable-tag">{{CURRENT_VALUE}}</span>
                                    <span class="variable-tag">{{OPERATOR}}</span>
                                    <span class="variable-tag">{{THRESHOLD_VALUE}}</span>
                                    <span class="variable-tag">{{THRESHOLD_VALUE_2}}</span>
                                    <span class="variable-tag">{{SEVERITY}}</span>
                                    <span class="variable-tag">{{TIMESTAMP}}</span>
                                </div>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Alert Rule
                            </button>
                            <a href="index.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var operatorSelect = document.getElementById('operator');
            var threshold2Group = document.getElementById('threshold_2_group');
            var targetMetricSelect = document.getElementById('target_metric');
            var thresholdValue = document.getElementById('threshold_value');
            var thresholdValue2 = document.getElementById('threshold_value_2');
            var conditionPreview = document.getElementById('conditionPreview');
            var previewText = document.getElementById('previewText');

            // Function to show/hide threshold 2 field
            window.toggleThreshold2 = function(operator) {
                if (operator === 'BETWEEN') {
                    threshold2Group.style.display = 'block';
                    document.getElementById('threshold_value_2').setAttribute('required', 'required');
                } else {
                    threshold2Group.style.display = 'none';
                    document.getElementById('threshold_value_2').removeAttribute('required');
                }
                updateConditionPreview();
            }

            // Function to update condition preview
            function updateConditionPreview() {
                var metric = targetMetricSelect.options[targetMetricSelect.selectedIndex]?.text || 'Metric';
                var operator = operatorSelect.value;
                var value1 = thresholdValue.value;
                var value2 = thresholdValue2.value;

                if (!metric || !operator || !value1) {
                    previewText.innerHTML = '<span style="color: var(--light-color);">Select metric and operator to see preview</span>';
                    return;
                }

                var preview = `<span style="color: var(--primary-color); font-weight: 600;">${metric}</span> ${operator} <span style="color: var(--success-color); font-weight: 600;">${value1}</span>`;
                
                if (operator === 'BETWEEN' && value2) {
                    preview += ` and <span style="color: var(--success-color); font-weight: 600;">${value2}</span>`;
                }

                previewText.innerHTML = preview;
            }

            // Event listeners for preview updates
            targetMetricSelect.addEventListener('change', updateConditionPreview);
            operatorSelect.addEventListener('change', updateConditionPreview);
            thresholdValue.addEventListener('input', updateConditionPreview);
            thresholdValue2.addEventListener('input', updateConditionPreview);

            // Initial setup
            toggleThreshold2(operatorSelect.value);
            updateConditionPreview();

            // Form validation for numeric fields
            document.getElementById('alertRuleForm').addEventListener('submit', function(e) {
                var threshold1 = document.getElementById('threshold_value').value;
                var threshold2 = document.getElementById('threshold_value_2').value;
                var operator = document.getElementById('operator').value;

                if (isNaN(threshold1) || threshold1.trim() === '') {
                    alert('Threshold Value 1 must be a valid number.');
                    e.preventDefault();
                    return;
                }

                if (operator === 'BETWEEN' && (isNaN(threshold2) || threshold2.trim() === '')) {
                    alert('Threshold Value 2 must be a valid number for BETWEEN operator.');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>
