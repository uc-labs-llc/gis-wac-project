<?php
// index.php - Alert Rules Management
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../models/AlertRule.php';

$database = new Database();
$db = $database->connect();
$alertRule = new AlertRule($db);

// Handle bulk actions
$message = '';
$message_type = '';

// Check for alert sending results from send_alerts.php
if (isset($_GET['alert_sent'])) {
    $message_type = 'success';
    switch ($_GET['alert_sent']) {
        case 'success':
            $alert_count = $_GET['count'] ?? 0;
            $rule_name = $_GET['rule_name'] ?? 'the rule';
            $message = "✓ Alert sent successfully! Sent {$alert_count} alert(s) for '{$rule_name}'";
            break;
        case 'no_users':
            $message = "Alert triggered but no users to notify for this location.";
            $message_type = 'info';
            break;
        case 'error':
            $message = "Error sending alerts: " . ($_GET['error'] ?? 'Unknown error');
            $message_type = 'error';
            break;
    }
}

if ($_POST && isset($_POST['bulk_action'])) {
    try {
        $selected_rules = $_POST['selected_rules'] ?? [];
        
        if (empty($selected_rules)) {
            throw new Exception('Please select at least one rule to perform this action.');
        }
        
        switch ($_POST['bulk_action']) {
            case 'activate':
                $updated = 0;
                foreach ($selected_rules as $rule_id) {
                    $alertRule->rule_id = $rule_id;
                    if ($alertRule->readOne()) {
                        $alertRule->is_active = true;
                        if ($alertRule->update()) {
                            $updated++;
                        }
                    }
                }
                $message = "Successfully activated $updated rules.";
                $message_type = 'success';
                break;
                
            case 'deactivate':
                $updated = 0;
                foreach ($selected_rules as $rule_id) {
                    $alertRule->rule_id = $rule_id;
                    if ($alertRule->readOne()) {
                        $alertRule->is_active = false;
                        if ($alertRule->update()) {
                            $updated++;
                        }
                    }
                }
                $message = "Successfully deactivated $updated rules.";
                $message_type = 'success';
                break;
                
            case 'delete':
                $deleted = 0;
                foreach ($selected_rules as $rule_id) {
                    $alertRule->rule_id = $rule_id;
                    if ($alertRule->delete()) {
                        $deleted++;
                    }
                }
                $message = "Successfully deleted $deleted rules.";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Handle individual delete
if (isset($_GET['delete'])) {
    $alertRule->rule_id = $_GET['delete'];
    if ($alertRule->delete()) {
        $message = 'Alert rule deleted successfully.';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete alert rule.';
        $message_type = 'error';
    }
}

// Handle individual toggle
if (isset($_GET['toggle'])) {
    $alertRule->rule_id = $_GET['toggle'];
    if ($alertRule->readOne()) {
        $alertRule->is_active = !$alertRule->is_active;
        if ($alertRule->update()) {
            $message = 'Alert rule ' . ($alertRule->is_active ? 'activated' : 'deactivated') . ' successfully.';
            $message_type = 'success';
        }
    }
}

// Fetch alert sending history from alert_queue table
$last_sent_times = [];
$sent_counts = [];

try {
    $stmt = $db->prepare("
        SELECT 
            rule_id,
            MAX(sent_at) as last_sent,
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as successful_sends
        FROM alert_queue 
        WHERE sent_at IS NOT NULL
        GROUP BY rule_id
    ");
    $stmt->execute();
    $sent_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sent_data as $row) {
        $last_sent_times[$row['rule_id']] = $row['last_sent'];
        $sent_counts[$row['rule_id']] = [
            'total' => $row['total_sent'],
            'successful' => $row['successful_sends']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching alert queue: " . $e->getMessage());
}

// Helper function for time ago display
function time_ago($datetime) {
    if (!$datetime) return 'Never';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

// Get sorting parameters
$sort = $_GET['sort'] ?? 'rule_name';
$order = $_GET['order'] ?? 'asc';
$next_order = $order === 'asc' ? 'desc' : 'asc';

// Fetch all rules with sorting
$rules_stmt = $alertRule->read();
$all_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort rules
usort($all_rules, function($a, $b) use ($sort, $order) {
    $a_val = $a[$sort] ?? '';
    $b_val = $b[$sort] ?? '';
    
    if ($sort === 'threshold_value' || $sort === 'threshold_value_2') {
        $a_val = (float)$a_val;
        $b_val = (float)$b_val;
    }
    
    if ($order === 'asc') {
        return $a_val <=> $b_val;
    } else {
        return $b_val <=> $a_val;
    }
});

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_severity = $_GET['severity'] ?? 'all';

// Apply filters
$filtered_rules = array_filter($all_rules, function($rule) use ($filter_status, $filter_severity) {
    $status_match = $filter_status === 'all' || 
                   ($filter_status === 'active' && $rule['is_active']) ||
                   ($filter_status === 'inactive' && !$rule['is_active']);
    
    $severity_match = $filter_severity === 'all' || $rule['severity_level'] === $filter_severity;
    
    return $status_match && $severity_match;
});

$total_rules = count($filtered_rules);
$active_rules = count(array_filter($filtered_rules, fn($rule) => $rule['is_active']));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Alert Rules Management - GIS-WAC</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--primary-color);
            display: block;
        }

        .stat-label {
            color: var(--light-color);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            font-size: 0.95em;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            font-size: 0.9em;
        }

        .rules-table {
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
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .table-header h3 {
            margin: 0;
            color: var(--dark-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.3s;
            user-select: none;
        }

        th:hover {
            background: #edf2f7;
        }

        th i {
            margin-left: 5px;
            opacity: 0.6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f7fafc;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .rule-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .rule-metric {
            display: inline-block;
            padding: 4px 8px;
            background: #e9d8fd;
            color: #6b46c1;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .rule-condition {
            font-family: 'Courier New', monospace;
            background: #f7fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-size: 0.9em;
        }

        .severity-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .severity-low { background: #c6f6d5; color: #276749; }
        .severity-medium { background: #fefcbf; color: #744210; }
        .severity-high { background: #fed7d7; color: #c53030; }
        .severity-critical { background: #fed7d7; color: #c53030; border: 2px solid #c53030; }

        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .status-active {
            background: #c6f6d5;
            color: #276749;
        }

        .status-inactive {
            background: #fed7d7;
            color: #c53030;
        }

        .status-toggle:hover {
            transform: scale(1.05);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--light-color);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9em;
        }

        .action-btn:hover {
            background: #edf2f7;
            color: var(--primary-color);
        }

        .action-btn.delete:hover {
            color: var(--danger-color);
        }

        .no-rules {
            text-align: center;
            padding: 60px 20px;
            color: var(--light-color);
        }

        .no-rules i {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
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

        .alert-info {
            background: #e6f3ff;
            color: #1e5eb4;
            border-left-color: #3182ce;
        }

        .bulk-actions {
            background: #f8fafc;
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .selected-count {
            font-weight: 600;
            color: var(--dark-color);
        }

        .sent-stats {
            font-size: 0.8em;
            color: var(--light-color);
            margin-top: 2px;
        }

        .sent-success {
            color: #38a169;
        }

        .sent-total {
            color: #718096;
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-group {
                justify-content: center;
            }
            
            .filters {
                justify-content: center;
            }
            
            .rules-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="user-info">
                <h1><i class="fas fa-bell"></i> Alert Rules Management</h1>
                <div class="user-details">
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'info' ? 'info' : 'error'); ?>">
                <i class="fas fa-<?php 
                    echo $message_type === 'success' ? 'check-circle' : 
                         ($message_type === 'info' ? 'info-circle' : 'exclamation-circle'); 
                ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_rules; ?></span>
                <span class="stat-label">Total Rules</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $active_rules; ?></span>
                <span class="stat-label">Active Rules</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_rules - $active_rules; ?></span>
                <span class="stat-label">Inactive Rules</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo count(array_filter($filtered_rules, fn($r) => $r['severity_level'] === 'CRITICAL')); ?></span>
                <span class="stat-label">Critical Rules</span>
            </div>
        </div>

        <div class="action-bar">
            <div class="btn-group">
                <a href="create_rule.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Rule
                </a>
		<a href="send_alert_email.php" class="btn btn-success" title="Send Active Alert">
                    <i class="fas fa-paper-plane"></i> Send Active Alerts
                </a>

                 <a href="send_deactive_alert.php" class="btn btn-outline" title="Send Deactive Alert">
                    <i class="fas fa-paper-plane"></i> Send Deactive Alerts
                </a>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label>Status:</label>
                    <select class="filter-select" onchange="updateFilter('status', this.value)">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Severity:</label>
                    <select class="filter-select" onchange="updateFilter('severity', this.value)">
                        <option value="all" <?php echo $filter_severity === 'all' ? 'selected' : ''; ?>>All Severity</option>
                        <option value="LOW" <?php echo $filter_severity === 'LOW' ? 'selected' : ''; ?>>Low</option>
                        <option value="MEDIUM" <?php echo $filter_severity === 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                        <option value="HIGH" <?php echo $filter_severity === 'HIGH' ? 'selected' : ''; ?>>High</option>
                        <option value="CRITICAL" <?php echo $filter_severity === 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
            </div>
        </div>

        <form method="post" id="bulkForm">
            <div class="rules-table">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Alert Rules (<?php echo $total_rules; ?>)</h3>
                </div>

                <?php if ($total_rules > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th onclick="sortTable('rule_name')">
                                    Rule Name <i class="fas fa-sort"></i>
                                </th>
                                <th onclick="sortTable('location_name')">
                                    Location <i class="fas fa-sort"></i>
                                </th>
                                <th onclick="sortTable('target_metric')">
                                    Metric <i class="fas fa-sort"></i>
                                </th>
                                <th>Condition</th>
                                <th onclick="sortTable('severity_level')">
                                    Severity <i class="fas fa-sort"></i>
                                </th>
                                <th onclick="sortTable('is_active')">
                                    Status <i class="fas fa-sort"></i>
                                </th>
                                <th onclick="sortTable('last_sent')">
                                    Last Sent <i class="fas fa-sort"></i>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_rules as $rule): 
                                $lastSent = $last_sent_times[$rule['rule_id']] ?? null;
                                $sentCount = $sent_counts[$rule['rule_id']] ?? ['total' => 0, 'successful' => 0];
                            ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="selected_rules[]" value="<?php echo $rule['rule_id']; ?>" class="rule-checkbox">
                                    </td>
                                    <td>
                                        <div class="rule-name"><?php echo htmlspecialchars($rule['rule_name']); ?></div>
                                        <?php if ($rule['rule_description']): ?>
                                            <small style="color: var(--light-color);"><?php echo htmlspecialchars($rule['rule_description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rule['location_name']): ?>
                                            <?php echo htmlspecialchars($rule['location_name']); ?>
                                            <br><small style="color: var(--light-color);"><?php echo htmlspecialchars($rule['city'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--light-color); font-style: italic;">Global</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="rule-metric"><?php echo htmlspecialchars($rule['target_metric']); ?></span>
                                    </td>
                                    <td>
                                        <div class="rule-condition">
                                            <?php echo htmlspecialchars($rule['target_metric']); ?> 
                                            <?php echo htmlspecialchars($rule['operator']); ?> 
                                            <?php echo htmlspecialchars($rule['threshold_value']); ?>
                                            <?php if ($rule['threshold_value_2']): ?>
                                                AND <?php echo htmlspecialchars($rule['threshold_value_2']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="severity-badge severity-<?php echo strtolower($rule['severity_level']); ?>">
                                            <?php echo htmlspecialchars($rule['severity_level']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?toggle=<?php echo $rule['rule_id']; ?>" 
                                           class="status-toggle <?php echo $rule['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <i class="fas fa-<?php echo $rule['is_active'] ? 'check' : 'times'; ?>"></i>
                                            <?php echo $rule['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($lastSent): ?>
                                            <div style="font-size: 0.9em;">
                                                <i class="fas fa-paper-plane" style="color: #48bb78;"></i>
                                                <?php echo date('M j, g:i A', strtotime($lastSent)); ?>
                                            </div>
                                            <div style="color: var(--light-color); font-size: 0.8em;">
                                                <?php echo time_ago($lastSent); ?>
                                            </div>
                                            <?php if ($sentCount['total'] > 0): ?>
                                                <div class="sent-stats">
                                                    <span class="sent-success"><?php echo $sentCount['successful']; ?>✓</span>
                                                    <span class="sent-total">/<?php echo $sentCount['total']; ?> sent</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--light-color); font-style: italic;">
                                                <i class="fas fa-clock"></i> Never sent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_rule.php?id=<?php echo $rule['rule_id']; ?>" class="action-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="send_alerts.php?rule_id=<?php echo $rule['rule_id']; ?>&test=true" 
                                               class="action-btn" 
                                               title="Test This Rule"
                                               onclick="return confirm('Test this alert rule? This will send actual alerts to users.')">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                            <a href="?delete=<?php echo $rule['rule_id']; ?>" 
                                               class="action-btn delete" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this rule?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="bulk-actions" id="bulkActions" style="display: none;">
                        <div class="selected-count">
                            <span id="selectedCount">0</span> rules selected
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="bulk_action" value="activate" class="btn btn-success">
                                <i class="fas fa-check"></i> Activate Selected
                            </button>
                            <button type="submit" name="bulk_action" value="deactivate" class="btn btn-warning">
                                <i class="fas fa-times"></i> Deactivate Selected
                            </button>
                            <button type="submit" name="bulk_action" value="delete" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete the selected rules?')">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-rules">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Alert Rules Found</h3>
                        <p>Get started by creating your first alert rule.</p>
                        <a href="create_rule.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Create New Rule
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order');
            
            let newOrder = 'asc';
            if (currentSort === column && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', newOrder);
            window.location.search = urlParams.toString();
        }

        function updateFilter(type, value) {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (value === 'all') {
                urlParams.delete(type);
            } else {
                urlParams.set(type, value);
            }
            
            // Remove pagination when filtering
            urlParams.delete('page');
            window.location.search = urlParams.toString();
        }

        function toggleSelectAll(checkbox) {
            const ruleCheckboxes = document.querySelectorAll('.rule-checkbox');
            ruleCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.rule-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected;
            
            const bulkActions = document.getElementById('bulkActions');
            if (selected > 0) {
                bulkActions.style.display = 'flex';
            } else {
                bulkActions.style.display = 'none';
            }
        }

        function toggleBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            if (bulkActions.style.display === 'none') {
                // Select all when showing bulk actions
                document.getElementById('selectAll').checked = true;
                toggleSelectAll(document.getElementById('selectAll'));
            } else {
                bulkActions.style.display = 'none';
                // Deselect all when hiding
                document.getElementById('selectAll').checked = false;
                toggleSelectAll(document.getElementById('selectAll'));
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.rule-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            
            // Update sort icons
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order');
            
            if (currentSort) {
                const headers = document.querySelectorAll('th');
                headers.forEach(header => {
                    if (header.textContent.includes(currentSort.replace('_', ' '))) {
                        const icon = header.querySelector('i');
                        if (icon) {
                            icon.className = currentOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                        }
                    }
                });
            }
            
            // Auto-hide success messages after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s';
                    setTimeout(() => successAlert.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
