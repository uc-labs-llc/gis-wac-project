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
    error_log("[AlertEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}
if ($location_path === false || !file_exists($location_path)) {
    $error_msg = "FATAL PATH ERROR: Location model not found at: '{$relative_path_location}'";
    error_log("[AlertEmailSender] " . $error_msg);
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
    error_log("[AlertEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}

// =========================================================================
// DEBUGGING FUNCTION (Retained for troubleshooting)
// =========================================================================
function log_debug($message) {
    error_log("[AlertEmailSender] " . $message);
    if (ini_get('display_errors') == 1 && PHP_SAPI !== 'cli') {
        echo "<pre>[DEBUG] " . htmlspecialchars($message) . "</pre>";
    }
}

// =========================================================================
// CLASS DEFINITION (AlertEmailSender)
// =========================================================================
class AlertEmailSender {
    private $conn;
    private $SENDER_EMAIL = 'no-reply@gis-wac.com'; 
    
    public function __construct($db) {
        $this->conn = $db; 
    }

    /**
     * MODIFIED: Get all *available* alert rules for the manual sender form, 
     * excluding any rule that currently has an unresolved alert instance.
     */
    public function getAlertRules() {
        // Query only returns rules where COUNT(unresolved alerts) = 0
        $query = "SELECT
                    ar.rule_id,
                    ar.rule_name,
                    ar.severity_level
                  FROM
                    alert_rules ar
                  LEFT JOIN
                    -- Join only considers active/unresolved alerts
                    alert_queue aq ON ar.rule_id = aq.rule_id AND aq.status NOT IN ('RESOLVED', 'CLEARED', 'CANCELLED', 'EXPIRED')
                  WHERE
                    ar.is_active = TRUE
                  GROUP BY
                    ar.rule_id, ar.rule_name, ar.severity_level
                  HAVING
                    -- Only include rules where the count of unresolved alerts is zero
                    COUNT(aq.queue_id) = 0
                  ORDER BY
                    ar.rule_name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function sendLocationAlert($alertRuleId, $locationId, $currentData = []) {
        // ... (Existing code for sending alert remains here) ...
        log_debug("Starting sendLocationAlert for Rule ID: {$alertRuleId}, Location ID: {$locationId}");
        try {
            $users = $this->getUsersByLocation($locationId);
            if (empty($users)) { 
                log_debug("No active users found for Location ID: {$locationId}");
                return ['status' => 'error', 'message' => 'No users found for this location']; 
            }
            $alertRule = $this->getAlertRule($alertRuleId);
            if (!$alertRule) { 
                log_debug("Alert rule not found for ID: {$alertRuleId}");
                return ['status' => 'error', 'message' => 'Alert rule not found']; 
            }
            if (empty($currentData) && $locationId) {
                $currentData = $this->getLatestMetricData($locationId);
            }
            $emailData = $this->prepareAlertEmail($alertRule, $currentData, $locationId);
            $results = []; 
            log_debug("Sending alert to " . count($users) . " users.");
            foreach ($users as $user) { 
                $result = $this->sendSingleEmail($user['email'], $emailData['subject'], $emailData['message']); 
                $this->logToAlertQueue($alertRuleId, $locationId, $user['email'], $emailData, $result);
                $results[] = [ 
                    'email' => $user['email'],
                    'status' => $result['status'],
                    'message' => $result['message']
                ]; 
            }
            return [
                'status' => 'success', 
                'message' => 'Alert sent to ' . count($users) . ' users', 
                'details' => $results
            ];
        } catch (Exception $e) { 
            error_log("Alert Email Error: " . $e->getMessage()); 
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
    
    public function getLatestMetricData($locationId) {
        $CURRENT_TABLE = 'weather_history'; 
        $query = "SELECT wh.temp_celsius AS temperature, wh.wind_speed_mph AS wind_speed, wh.rainfall_mm_1h AS rain_accumulation, 
                         wh.humidity_percent AS humidity, wh.pressure_hpa AS pressure_mb
                  FROM {$CURRENT_TABLE} wh
                  WHERE wh.location_id = :location_id 
                  ORDER BY wh.recorded_at_utc DESC LIMIT 1";
        $stmt = $this->conn->prepare($query); 
        $stmt->bindParam(':location_id', $locationId);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) { return []; }
        
        return [
            'temperature' => $data['temperature'] ?? null, 'wind_speed' => $data['wind_speed'] ?? null, 
            'rain_accumulation' => $data['rain_accumulation'] ?? null, 'humidity' => $data['humidity'] ?? null, 
            'pressure_mb' => $data['pressure_mb'] ?? null, 
        ];
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
    
    private function prepareAlertEmail($alertRule, $currentData, $locationId) {
        $locationName = $alertRule['location_name'] ?: 'All Locations'; 
        $city = $alertRule['city'] ?: '';
        $subject = $alertRule['custom_subject'] ?: "🚨 Weather Alert: {$alertRule['rule_name']} - {$locationName}"; 
        $message = $this->buildAlertMessage($alertRule, $currentData, $locationName, $city); 
        return ['subject' => $subject, 'message' => $message]; 
    }
    
    private function buildAlertMessage($alertRule, $currentData, $locationName, $city) {
        $currentValue = $currentData[$alertRule['target_metric']] ?? 'N/A'; 
        $timestamp = date('Y-m-d H:i:s T');
        $message = $alertRule['message_template']; 
        $variables = [
            '{{LOCATION_NAME}}' => $locationName, '{{CITY}}' => $city, '{{TARGET_METRIC}}' => $alertRule['target_metric'],
            '{{CURRENT_VALUE}}' => $currentValue, '{{OPERATOR}}' => $alertRule['operator'], 
            '{{THRESHOLD_VALUE}}' => $alertRule['threshold_value'], '{{THRESHOLD_VALUE_2}}' => $alertRule['threshold_value_2'] ?? '', 
            '{{SEVERITY}}' => $alertRule['severity_level'], '{{TIMESTAMP}}' => $timestamp, '{{RULE_NAME}}' => $alertRule['rule_name']
        ];
        foreach ($variables as $key => $value) { 
            $message = str_replace($key, $value, $message); 
        }
        return $this->wrapInEmailTemplate($message, $alertRule['severity_level']); 
    }
    
    private function wrapInEmailTemplate($content, $severity) {
        $severityColors = ['LOW' => '#e6fffa', 'MEDIUM' => '#fefcbf', 'HIGH' => '#fed7d7', 'CRITICAL' => '#fed7d7']; 
        $backgroundColor = $severityColors[$severity] ?? '#f0f4f8'; 
        return "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'><style>body { font-family: Arial, sans-serif; line-height: 1.6; background-color: {$backgroundColor}; padding: 20px; } .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); } .header { color: #005a9c; border-bottom: 2px solid #005a9c; padding-bottom: 15px; margin-bottom: 20px; } .alert-content { background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #005a9c; } .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #888; }</style></head>
        <body><div class='container'><div class='header'><h2>⚠️ Weather Alert Notification</h2></div><div class='alert-content'>{$content}</div>
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
    
    private function logToAlertQueue($alertRuleId, $locationId, $recipientEmail, $emailData, $sendResult) {
        $status = $sendResult['status'] === 'success' ? 'SENT' : 'FAILED'; 
        $sentAt = $sendResult['status'] === 'success' ? date('Y-m-d H:i:s') : null; 
        $query = "INSERT INTO alert_queue (rule_id, location_id, status, triggered_at, sent_at, alert_message, alert_subject, calculated_value, delivery_attempts) 
                  VALUES (:rule_id, :location_id, :status, NOW(), :sent_at, :alert_message, :alert_subject, :calculated_value, 1)"; 
        $calculatedValue = json_encode(['recipient_email' => $recipientEmail, 'send_status' => $sendResult['status'], 'send_message' => $sendResult['message']]);
        $stmt = $this->conn->prepare($query); 
        $stmt->bindParam(':rule_id', $alertRuleId);
        $stmt->bindParam(':location_id', $locationId);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':sent_at', $sentAt);
        $stmt->bindParam(':alert_message', $emailData['message']);
        $stmt->bindParam(':alert_subject', $emailData['subject']);
        $stmt->bindParam(':calculated_value', $calculatedValue);
        return $stmt->execute(); 
    }
    
    /**
     * ADDED: Fetches a list of recent alert history for alert_emails.php.
     * EXCLUDES the 'RESOLVED' status to hide the original parent alert once clearance occurs.
     */
    public function getEmailHistory($limit = 10) {
        $query = "SELECT 
                    aq.status, 
                    aq.triggered_at, 
                    aq.sent_at, 
                    ar.rule_name, 
                    l.location_name
                  FROM 
                    alert_queue aq
                  LEFT JOIN 
                    alert_rules ar ON aq.rule_id = ar.rule_id
                  LEFT JOIN 
                    locations l ON aq.location_id = l.location_id
                  WHERE
                    aq.status NOT IN ('RESOLVED', 'CANCELLED', 'EXPIRED') -- Exclude resolved parent alerts
                  ORDER BY 
                    aq.triggered_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =========================================================================
// EXECUTION BLOCK: Handles both form display (GET) and form submission (POST)
// =========================================================================

// Initialize components for the form display and processing
if (!class_exists('Location')) {
    $error_msg = "FATAL CLASS ERROR: Class 'Location' not found. Check Location.php file content.";
    error_log("[AlertEmailSender] " . $error_msg);
    echo "<h1>{$error_msg}</h1>";
    exit;
}

$location_model = new Location($db);
$emailSender = new AlertEmailSender($db);

$all_locations = $location_model->read(); 
$all_alert_rules = $emailSender->getAlertRules(); // Fetch rules here (NOW FILTERED)

$alertMessage = ''; // Variable to hold success/error messages

// --- 1. HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_alert') {
    log_debug("Script execution started via POST (Manual Alert).");

    $locationId = $_POST['location_id'] ?? null;
    $alertRuleId = $_POST['alert_rule_id'] ?? null;

    if (!$locationId || !$alertRuleId) {
        // Handle error and fall through to display form
        $alertMessage = '<div class="message-box" style="color:red;border:1px solid red;background-color:#ffebeb;">ERROR: Please select both a Location AND an Alert Rule.</div>';
    } else {
        try {
            $result = $emailSender->sendLocationAlert($alertRuleId, $locationId);

            if ($result['status'] === 'success') {
                // *** FIX 1: POST/REDIRECT/GET (PRG) PATTERN ***
                // Redirect on success to prevent accidental resend on refresh (F5)
                $redirect_url = "send_alert_email.php?alert_status=success";
                header("Location: {$redirect_url}");
                exit; // Stop execution after sending header

            } else {
                // Display error message and fall through to display form
                $alertMessage = '<div class="message-box" style="color:red;border:1px solid red;background-color:#ffebeb;">ERROR: Failed to send alert. Message: ' . htmlspecialchars($result['message']) . '</div>';
            }

        } catch (Exception $e) {
            $alertMessage = '<div class="message-box" style="color:red;border:1px solid red;background-color:#ffebeb;">FATAL APPLICATION ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
            error_log("[AlertEmailSender] FATAL ERROR: " . $e->getMessage());
        }
    }
} 
// --- 2. HANDLE GET REQUEST ---
else if (isset($_GET['alert_status']) && $_GET['alert_status'] === 'success') {
    // Message displayed after successful redirect
    $alertMessage = '<div class="message-box" style="color:green;border:1px solid green;background-color:#ebfff1;">SUCCESS: Alert sent successfully.</div>';
}
// For all other GET requests, the HTML form is displayed below.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manual Alert Dispatch</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f7f6; }
        .container { max-width: 600px; margin: 50px auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #005a9c; border-bottom: 2px solid #005a9c; padding-bottom: 10px; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        select, input[type="number"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #005a9c; color: white; padding: 12px 20px; margin-top: 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background-color: #004a80; }
        .message-box { margin-bottom: 20px; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manual Alert Dispatch</h1>
        
        <?php echo $alertMessage; // Display success/error messages ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="send_alert">

            <label for="location_id">Select Location to Alert:</label>
            <select id="location_id" name="location_id" required>
                <option value="">-- Choose a Location --</option>
                <?php foreach ($all_locations as $location): ?>
                    <option value="<?php echo htmlspecialchars($location['location_id']); ?>">
                        <?php echo htmlspecialchars($location['location_name'] . ' (' . $location['city'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="alert_rule_id">Select Alert Rule (Uses Rule Name):</label>
            <select id="alert_rule_id" name="alert_rule_id" required>
                <option value="">-- Choose a Rule --</option>
                <?php foreach ($all_alert_rules as $rule): ?>
                    <option value="<?php echo htmlspecialchars($rule['rule_id']); ?>">
                        <?php echo htmlspecialchars($rule['rule_name'] . ' (Severity: ' . $rule['severity_level'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size:12px;color:#888;">The **Rule ID (UUID)** for the selected rule will be used to generate the alert content.</p>
            <p style="font-size:12px;color:red;">Rules with an existing, unresolved alert instance are hidden from this list.</p>

            <button type="submit">Send Weather Alert</button>
        </form>
    </div>
</body>
</html>
