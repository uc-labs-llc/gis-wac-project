<?php
// =========================================================================
// CRITICAL DEBUGGING SETTINGS: Enable all errors to prevent white screens
// =========================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// CORE FILE INCLUDES & PATH CHECK
// =========================================================================
$relative_path_db = '../../config/database.php';
$relative_path_location = '../../models/Location.php';

$db_path = realpath(__DIR__ . '/' . $relative_path_db);
$location_path = realpath(__DIR__ . '/' . $relative_path_location);

// Path checks
if ($db_path === false || !file_exists($db_path)) {
    $error_msg = "FATAL PATH ERROR: Database config not found at: '{$relative_path_db}'";
    error_log("[ClearanceEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}
if ($location_path === false || !file_exists($location_path)) {
    $error_msg = "FATAL PATH ERROR: Location model not found at: '{$relative_path_location}'";
    error_log("[ClearanceEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}

// Load necessary files
require_once $db_path; 
require_once $location_path;

// Establish connection
try {
    $database = new Database(); 
    $db = $database->connect();
    if (!$db instanceof PDO) {
         throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    $error_msg = "Database Connection Error: " . $e->getMessage();
    error_log("[ClearanceEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}

// =========================================================================
// DEBUGGING FUNCTION
// =========================================================================
function log_debug($message) {
    error_log("[ClearanceEmailSender] " . $message);
    if (ini_get('display_errors') == 1 && PHP_SAPI !== 'cli') {
        echo "<pre>[DEBUG] " . htmlspecialchars($message) . "</pre>";
    }
}

// =========================================================================
// CLASS DEFINITION (AlertEmailSender) - Modified for Clearance Logic
// =========================================================================
class AlertEmailSender {
    private $conn;
    private $SENDER_EMAIL = 'no-reply@gis-wac.com'; 
    
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * FIX: Get rules that currently have an unresolved alert instance for the dropdown filter.
     */
    public function getActiveAlertRules() {
        $query = "SELECT 
                    ar.rule_id, 
                    ar.rule_name, 
                    ar.severity_level,
                    ar.location_id, 
                    l.location_name
                  FROM 
                    alert_rules ar
                  JOIN
                    -- Join only on active/unresolved alerts (FIXED FILTER)
                    alert_queue aq ON ar.rule_id = aq.rule_id AND aq.status NOT IN ('RESOLVED', 'CLEARED', 'CANCELLED', 'EXPIRED')
                  LEFT JOIN 
                    locations l ON ar.location_id = l.location_id
                  WHERE
                    ar.is_active = TRUE
                  GROUP BY 
                    ar.rule_id, ar.rule_name, ar.severity_level, ar.location_id, l.location_name
                  ORDER BY ar.rule_name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function sendClearanceAlert($alertRuleId, $locationId, $currentData = []) {
        log_debug("Starting sendClearanceAlert for Rule ID: {$alertRuleId}, Location ID: {$locationId}");
        try {
            // Find the original active alert (Parent)
            $originalAlert = $this->findActiveAlert($alertRuleId, $locationId);
            if (!$originalAlert) {
                return ['status' => 'info', 'message' => 'No active/unresolved alert found to clear for this rule and location.'];
            }
            
            $users = $this->getUsersByLocation($locationId);
            $alertRule = $this->getAlertRule($alertRuleId);
            
            // Step 1: Prepare the clearance email content
            $emailData = $this->prepareClearanceEmail($alertRule, $originalAlert);
            
            // Step 2: Insert the clearance event and resolve the original alert (TRANSACTION APPLIED HERE)
            $dbResult = $this->logClearanceToAlertQueue($alertRuleId, $locationId, $originalAlert, $emailData);
            if ($dbResult['status'] === 'error') {
                 return ['status' => 'error', 'message' => 'Database error during clearance logging: ' . $dbResult['message']];
            }
            
            // Step 3: Send emails
            $results = [];
            log_debug("Sending clearance alert to " . count($users) . " users.");
            foreach ($users as $user) { 
                $result = $this->sendSingleEmail($user['email'], $emailData['subject'], $emailData['message']);
                $results[] = [ 
                    'email' => $user['email'],
                    'status' => $result['status'],
                    'message' => $result['message']
                ];
            }
            
            return [
                'status' => 'success', 
                'message' => 'Original alert resolved. Clearance alert sent to ' . count($users) . ' users', 
                'details' => $results
            ];
        } catch (Exception $e) { 
            error_log("Clearance Email Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    // --- Helper Functions ---
    
    private function findActiveAlert($alertRuleId, $locationId) {
        $query = "SELECT queue_id, alert_message, alert_subject, calculated_value 
                  FROM alert_queue 
                  WHERE rule_id = :rule_id AND location_id = :location_id 
                  AND status NOT IN ('RESOLVED', 'CLEARED', 'CANCELLED', 'EXPIRED') 
                  ORDER BY triggered_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':rule_id', $alertRuleId);
        $stmt->bindParam(':location_id', $locationId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function prepareClearanceEmail($alertRule, $originalAlert) {
        $originalSubject = $originalAlert['alert_subject'] ?: $alertRule['custom_subject'] ?: $alertRule['rule_name'];
        $subject = "✅ CLEARED: " . str_replace(['🚨 Weather Alert: ', '⚠️ Weather Alert: '], '', $originalSubject);
        
        $messageContent = "The weather conditions that triggered the following alert have now passed or been resolved. The system is operating normally for this rule.<br><br>";
        $messageContent .= "--- **Original Alert Details** ---<br>";
        $messageContent .= $originalAlert['alert_message'];
        
        $message = $this->wrapInEmailTemplate($messageContent, $alertRule['severity_level'], true); 
        
        return ['subject' => $subject, 'message' => $message];
    }
    
    /**
     * FIX 3: Implements a PDO transaction to ensure both INSERT and UPDATE succeed atomically.
     */
    private function logClearanceToAlertQueue($alertRuleId, $locationId, $originalAlert, $emailData) {
        $originalQueueId = $originalAlert['queue_id'];
        
        try {
            // Start Transaction to ensure data integrity
            $this->conn->beginTransaction();

            // Step 1: Insert the new 'CLEARED' alert event (The CHILD record)
            $insertQuery = "INSERT INTO alert_queue (
                                rule_id, location_id, status, triggered_at, 
                                alert_message, alert_subject, calculated_value, parent_alert_id, delivery_attempts
                            ) VALUES (
                                :rule_id, :location_id, 'CLEARED', NOW(), 
                                :alert_message, :alert_subject, :calculated_value, :parent_alert_id, 0
                            ) RETURNING queue_id"; // PostgreSQL syntax
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':rule_id', $alertRuleId);
            $insertStmt->bindParam(':location_id', $locationId);
            $insertStmt->bindParam(':alert_message', $emailData['message']);
            $insertStmt->bindParam(':alert_subject', $emailData['subject']);
            $insertStmt->bindParam(':calculated_value', $originalAlert['calculated_value']);
            $insertStmt->bindParam(':parent_alert_id', $originalQueueId);
            $insertStmt->execute();
            $newQueueId = $insertStmt->fetchColumn(); 

            // Step 2: Update the original alert to 'RESOLVED' (The PARENT record)
            $updateQuery = "UPDATE alert_queue
                            SET status = 'RESOLVED', resolved_at = NOW()
                            WHERE queue_id = :original_queue_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':original_queue_id', $originalQueueId);
            $updateStmt->execute();

            // Commit transaction if both statements succeeded
            $this->conn->commit();

            return ['status' => 'success', 'queue_id' => $newQueueId];
        } catch (PDOException $e) {
            // Rollback if anything failed
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("PostgreSQL Clearance Transaction Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function getUsersByLocation($locationId) { 
        $query = "SELECT u.user_id, u.email, u.first_name, u.last_name, ul.relationship_type, ul.location_specific_email
                  FROM users u
                  INNER JOIN user_locations ul ON u.user_id = ul.user_id 
                  WHERE ul.location_id = :location_id AND ul.is_current = true AND u.is_active = true AND u.email IS NOT NULL AND u.email != ''";
        $stmt = $this->conn->prepare($query); 
        $stmt->bindParam(':location_id', $locationId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getAlertRule($ruleId) { 
        $query = "SELECT ar.*, l.location_name, l.city 
                  FROM alert_rules ar LEFT JOIN locations l ON ar.location_id = l.location_id 
                  WHERE ar.rule_id = :rule_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':rule_id', $ruleId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); 
    }
    
    private function wrapInEmailTemplate($content, $severity, $isClearance = false) {
        $severityColors = ['LOW' => '#e6fffa', 'MEDIUM' => '#fefcbf', 'HIGH' => '#fed7d7', 'CRITICAL' => '#fed7d7'];
        
        if ($isClearance) {
            $backgroundColor = '#e6fffa'; 
            $headerColor = '#276749'; 
            $headerText = '✅ Alert Cleared Notification';
            $borderColor = '#48bb78';
        } else {
            $backgroundColor = $severityColors[$severity] ?? '#f0f4f8'; 
            $headerColor = '#005a9c';
            $headerText = '⚠️ Weather Alert Notification';
            $borderColor = '#005a9c';
        }
        
        return "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'><style>body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; } .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); } .header { color: {$headerColor}; border-bottom: 2px solid {$headerColor}; padding-bottom: 15px; margin-bottom: 20px; } .alert-content { background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid {$borderColor}; } .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #888; }</style></head>
        <body><div class='container'><div class='header'><h2>{$headerText}</h2></div><div class='alert-content'>{$content}</div>
        <div class='footer'><p>This alert was generated by GIS-WAC Alert System.</p><p>You are receiving this email because you are subscribed to alerts for this location.</p></div></div></body></html>";
    }
    
    private function sendSingleEmail($to, $subject, $message) { 
        $headers = "From: GIS-WAC Alerts <{$this->SENDER_EMAIL}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        if (mail($to, $subject, $message, $headers)) {
            return ['status' => 'success', 'message' => 'Email sent successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to send email'];
        }
    }
}

// =========================================================================
// EXECUTION BLOCK: Handles both form display (GET) and form submission (POST)
// =========================================================================

if (!class_exists('Location')) {
    $error_msg = "FATAL CLASS ERROR: Class 'Location' not found. Check Location.php file content.";
    error_log("[ClearanceEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}

$location_model = new Location($db);
$emailSender = new AlertEmailSender($db);

$active_alerts_for_dropdown = $emailSender->getActiveAlertRules(); 

$alertMessage = ''; 

// --- 1. HANDLE POST REQUEST (Clearance Attempt) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_clearance') {
    log_debug("Script execution started via POST (Manual Clearance).");
    
    $locationId = $_POST['location_id'] ?? null;
    $alertRuleId = $_POST['alert_rule_id'] ?? null;
    $status_param = 'error';

    if (!$locationId || !$alertRuleId) {
        $alertMessage = '<div class="message-box error">ERROR: Please select both a Location AND an Alert Rule.</div>';
    } else {
        try {
            $result = $emailSender->sendClearanceAlert($alertRuleId, $locationId);

            if ($result['status'] === 'success') {
                $status_param = 'success';
            } elseif ($result['status'] === 'info') {
                 $status_param = 'info';
            } else {
                 $alertMessage = '<div class="message-box error">ERROR: Failed to process clearance. Message: ' . htmlspecialchars($result['message']) . '</div>';
            }

            // FIX 4: Implement PRG (Post/Redirect/Get) for stability
            if ($result['status'] === 'success' || $result['status'] === 'info') {
                 $redirect_url = "send_deactive_alert.php?clear_status={$status_param}";
                 header("Location: {$redirect_url}");
                 exit;
            }

        } catch (Exception $e) {
            $alertMessage = '<div class="message-box error">FATAL APPLICATION ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
            error_log("[ClearanceEmailSender] FATAL ERROR: " . $e->getMessage());
        }
    }
} 
// --- 2. HANDLE GET REQUEST (After Redirect) ---
else if (isset($_GET['clear_status'])) {
    switch ($_GET['clear_status']) {
        case 'success':
            $alertMessage = '<div class="message-box success">SUCCESS: Alert resolved and clearance event logged.</div>';
            break;
        case 'info':
            $alertMessage = '<div class="message-box info">INFO: No active alert found to clear for the selected rule/location.</div>';
            break;
        case 'error':
            $alertMessage = '<div class="message-box error">ERROR: Clearance failed due to an unknown error.</div>';
            break;
    }
}
// For all other GET requests, the HTML form is displayed below.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manual Alert Clearance Dispatch</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f7f6; }
        .container { max-width: 600px; margin: 50px auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #276749; border-bottom: 2px solid #48bb78; padding-bottom: 10px; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #48bb78; color: white; padding: 12px 20px; margin-top: 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background-color: #38a169; }
        .message-box { margin-bottom: 20px; padding: 15px; border-radius: 4px; }
        .success { color: green; border: 1px solid green; background-color: #ebfff1; }
        .error { color: red; border: 1px solid red; background-color: #ffebeb; }
        .info { color: #007bff; border: 1px solid #007bff; background-color: #e6f3ff; }
        .warning { color: orange; border: 1px solid orange; background-color: #fff8e6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manual Alert Clearance Dispatch</h1>
        <p>This tool resolves the most recent **active** alert for the selected Rule/Location and sends a clearance email.</p>
        
        <?php echo $alertMessage; // Display success/error messages ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="send_clearance">
            
            <?php if (empty($active_alerts_for_dropdown)): ?>
                <div class="message-box warning">
                    <p>There are currently no active/unresolved alerts that require manual clearance. The list below is empty.</p>
                </div>
                
                <label for="alert_rule_id">Select Alert Rule to Clear:</label>
                <select id="alert_rule_id" name="alert_rule_id" disabled>
                    <option value="">-- No Active Rules Found --</option>
                </select>
                <p style="font-size:12px;color:red;">Rules only appear here if they have an active alert in the system.</p>
                <button type="button" disabled>Clear/Deactivate Alert</button>
                <input type="hidden" id="location_id" name="location_id" value="">

            <?php else: ?>

                <label for="alert_rule_id">Select Alert Rule to Clear (Only Rules with Active Alerts Shown):</label>
                <select id="alert_rule_id" name="alert_rule_id" required>
                    <option value="">-- Choose an Alert Rule to Clear --</option>
                    <?php 
                    foreach ($active_alerts_for_dropdown as $rule): 
                        $rule_id_value = htmlspecialchars($rule['rule_id']);
                        $location_id_value = htmlspecialchars($rule['location_id']);
                    ?>
                        <option value="<?php echo $rule_id_value; ?>" 
                                data-location-id="<?php echo $location_id_value; ?>">
                            <?php echo htmlspecialchars($rule['rule_name'] . ' - ' . ($rule['location_name'] ?: 'All Locations') . ' (Severity: ' . $rule['severity_level'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:12px;color:red;">Rules only appear here if they have an active alert in the system.</p>

                <input type="hidden" id="location_id" name="location_id" value="">

                <button type="submit">Resolve and Clear Alert</button>
            <?php endif; ?>
        </form>
        
        <script>
        // JavaScript to automatically set the location ID based on the selected rule
        document.addEventListener('DOMContentLoaded', function() {
            const ruleSelect = document.getElementById('alert_rule_id');
            const locationInput = document.getElementById('location_id');

            function updateLocationId() {
                if (ruleSelect.selectedIndex > 0) {
                    const selectedOption = ruleSelect.options[ruleSelect.selectedIndex];
                    const locationId = selectedOption.getAttribute('data-location-id'); 
                    locationInput.value = locationId;
                } else {
                     locationInput.value = '';
                }
            }

            ruleSelect.addEventListener('change', updateLocationId);
            updateLocationId();
        });
        </script>
    </div>
</body>
</html>
