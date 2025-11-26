<?php
// alert_emails.php - Alert Email Management
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../models/AlertRule.php';
require_once '../../models/Location.php';
require_once 'send_alert_email.php'; // Include the email sender

$database = new Database();
$db = $database->connect();
$alertRule = new AlertRule($db);
$location = new Location($db);
$emailSender = new AlertEmailSender($db);

// Fetch all alert rules and locations
$rules_stmt = $alertRule->read();
$all_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);

$locations_stmt = $location->read();
$all_locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get email history
$emailHistory = $emailSender->getEmailHistory(10);

// Handle form submission
$message = '';
$message_type = '';
$detailedResults = [];

// Function to generate mock current data for demonstration
function getMockCurrentData($metric) {
    $mockData = [
        'temperature' => rand(20, 35),
        'humidity' => rand(30, 90),
        'wind_speed' => rand(0, 25),
        'wind_gust' => rand(0, 40),
        'pressure' => rand(980, 1020),
        'rain_1h' => rand(0, 10) / 10,
        'snow_1h' => rand(0, 5),
        'visibility' => rand(1000, 10000)
    ];
    
    return [$metric => $mockData[$metric] ?? 'N/A'];
}

if ($_POST && isset($_POST['send_alerts'])) {
    $selectedAlerts = $_POST['selected_alerts'] ?? [];
    
    if (empty($selectedAlerts)) {
        $message = 'Please select at least one alert to send.';
        $message_type = 'error';
    } else {
        // Process each selected alert
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($selectedAlerts as $alertRuleId) {
            // Find the alert rule
            $alertRuleData = null;
            foreach ($all_rules as $rule) {
                if ($rule['rule_id'] == $alertRuleId) {
                    $alertRuleData = $rule;
                    break;
                }
            }
            
            if ($alertRuleData) {
                // Use mock current data - in real scenario, get from weather API
                $currentData = getMockCurrentData($alertRuleData['target_metric']);
                
                // Send alert
                $result = $emailSender->sendLocationAlert(
                    $alertRuleData['rule_id'], 
                    $alertRuleData['location_id'], 
                    $currentData
                );
                
                if ($result['status'] === 'success') {
                    $successCount++;
                    $results[] = "✓ {$alertRuleData['rule_name']}: " . $result['message'];
                } else {
                    $errorCount++;
                    $results[] = "✗ {$alertRuleData['rule_name']}: " . $result['message'];
                }
            }
        }
        
        $message = "Sent {$successCount} alerts successfully" . ($errorCount > 0 ? ", {$errorCount} failed" : "");
        $message_type = $errorCount === 0 ? 'success' : ($successCount > 0 ? 'warning' : 'error');
        $detailedResults = $results;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Send Alert Emails - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse the same modern styling from index.php */
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
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

        .alert-warning {
            background: #fffaf0;
            color: #744210;
            border-left-color: #ed8936;
        }

        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .alerts-table, .history-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-header {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--dark-color);
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .severity-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-low { background: #c6f6d5; color: #276749; }
        .severity-medium { background: #fefcbf; color: #744210; }
        .severity-high { background: #fed7d7; color: #c53030; }
        .severity-critical { background: #fed7d7; color: #c53030; border: 2px solid #c53030; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-sent { background: #c6f6d5; color: #276749; }
        .status-failed { background: #fed7d7; color: #c53030; }
        .status-queued { background: #bee3f8; color: #2c5282; }

        .no-alerts {
            text-align: center;
            padding: 60px 20px;
            color: var(--light-color);
        }

        .detailed-results {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .result-item {
            padding: 5px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .result-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="user-info">
                <h1><i class="fas fa-paper-plane"></i> Send Alert Emails</h1>
                <div class="user-details">
                    <a href="index.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Back to Alert Rules
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                
                <?php if (!empty($detailedResults)): ?>
                    <div class="detailed-results">
                        <strong>Detailed Results:</strong>
                        <?php foreach ($detailedResults as $result): ?>
                            <div class="result-item"><?php echo htmlspecialchars($result); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="alertsForm">
            <div class="action-bar">
                <div>
                    <h3 style="margin: 0; color: var(--dark-color);">
                        <i class="fas fa-bell"></i> Select Alerts to Send
                    </h3>
                    <p style="margin: 5px 0 0 0; color: var(--light-color);">
                        Choose alert rules and send notifications to subscribed users
                    </p>
                </div>
                <button type="submit" name="send_alerts" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Selected Alerts
                </button>
            </div>

            <div class="alerts-table">
                <?php if (count($all_rules) > 0): ?>
                    <div class="table-header">
                        <h4><i class="fas fa-list"></i> Available Alert Rules (<?php echo count($all_rules); ?>)</h4>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th>Rule Name</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_rules as $rule): ?>
                                <?php
                                // Get real user count for this location
                                $userCount = $emailSender->getUserCountByLocation($rule['location_id']);
                                ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="selected_alerts[]" value="<?php echo $rule['rule_id']; ?>" class="alert-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rule['rule_name']); ?></strong>
                                        <?php if ($rule['rule_description']): ?>
                                            <br><small style="color: var(--light-color);"><?php echo htmlspecialchars($rule['rule_description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rule['location_name']): ?>
                                            <?php echo htmlspecialchars($rule['location_name']); ?>
                                            <br><small style="color: var(--light-color);"><?php echo htmlspecialchars($rule['city'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--light-color); font-style: italic;">All Locations</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code style="background: #f7fafc; padding: 4px 8px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($rule['target_metric']); ?> 
                                            <?php echo htmlspecialchars($rule['operator']); ?> 
                                            <?php echo htmlspecialchars($rule['threshold_value']); ?>
                                            <?php if ($rule['threshold_value_2']): ?>
                                                AND <?php echo htmlspecialchars($rule['threshold_value_2']); ?>
                                            <?php endif; ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="severity-badge severity-<?php echo strtolower($rule['severity_level']); ?>">
                                            <?php echo htmlspecialchars($rule['severity_level']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas fa-<?php echo $rule['is_active'] ? 'check' : 'times'; ?>"></i>
                                            <?php echo $rule['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-users"></i> <?php echo $userCount; ?> users
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-alerts">
                        <i class="fas fa-bell-slash" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i>
                        <h3>No Alert Rules Found</h3>
                        <p>Create some alert rules first to send notifications.</p>
                        <a href="create_rule.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Create Alert Rule
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Email History Section -->
        <div class="history-table">
            <div class="table-header">
                <h4><i class="fas fa-history"></i> Recent Email History</h4>
            </div>
            
            <?php if (count($emailHistory) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Triggered</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailHistory as $history): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($history['rule_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['location_name'] ?? 'Global'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($history['status']); ?>">
                                        <?php echo htmlspecialchars($history['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($history['triggered_at'])); ?></td>
                                <td>
                                    <?php if ($history['sent_at']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($history['sent_at'])); ?>
                                    <?php else: ?>
                                        <span style="color: var(--light-color);">Not sent</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: var(--light-color);">
                    <i class="fas fa-inbox" style="font-size: 2em; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No email history found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSelectAll(checkbox) {
            const alertCheckboxes = document.querySelectorAll('.alert-checkbox');
            alertCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        // Add confirmation before sending
        document.getElementById('alertsForm').addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.alert-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Please select at least one alert to send.');
                return;
            }
            
            if (!confirm(`Are you sure you want to send ${selected} alert(s) to all subscribed users?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
