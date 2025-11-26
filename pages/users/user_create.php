<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';

// Session check removed for public management (Security stripped)

$database = new Database();
$db = $database->connect();
$userModel = new User($db);

$message = '';
$message_type = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    // Basic validation
    $errors = [];
    
    // Common required fields
    if (empty($_POST['email'])) $errors[] = 'Email is required';
    if (empty($_POST['first_name'])) $errors[] = 'First name is required';
    if (empty($_POST['last_name'])) $errors[] = 'Last name is required';
    if (empty($_POST['address_line_1'])) $errors[] = 'Address Line 1 is required';
    if (empty($_POST['city'])) $errors[] = 'City is required';
    if (empty($_POST['state_province'])) $errors[] = 'State/Province is required';
    if (empty($_POST['zip_postal_code'])) $errors[] = 'ZIP/Postal Code is required';
    
    if (empty($errors)) {
        $user = new User($db);
        
        // Set all the properties from the form
        $user->username = null;
        $user->email = $_POST['email'];
        $user->password_hash = null;
        
        // Phone number fields
        $user->phone_country_code = substr($_POST['phone_country_code'] ?? '+1', 0, 5);
        $user->phone_number = $_POST['phone_number'] ?? '';
        
        $user->first_name = $_POST['first_name'];
        $user->last_name = $_POST['last_name'];
        $user->middle_name = null;
        $user->suffix = null;
        $user->title = null;
        $user->address_line_1 = $_POST['address_line_1'];
        $user->address_line_2 = $_POST['address_line_2'] ?? '';
        $user->building_number = null;
        $user->building_name = null;
        $user->city = $_POST['city'];
        $user->state_province = $_POST['state_province'];
        $user->zip_postal_code = $_POST['zip_postal_code'];
        $user->country = $_POST['country'] ?? 'United States';
        $user->prefers_email_alerts = isset($_POST['prefers_email_alerts']) ? 1 : 0;
        $user->prefers_sms_alerts = isset($_POST['prefers_sms_alerts']) ? 1 : 0;
        $user->prefers_push_alerts = 0;
        $user->alert_timezone = 'America/New_York';
        $user->daily_alert_digest = isset($_POST['daily_alert_digest']) ? 1 : 0;
        $user->digest_time = $_POST['digest_time'] ?? '07:00';
        $user->is_active = isset($_POST['is_active']) ? 1 : 1;
        $user->is_staff = 0;
        $user->is_superuser = 0;
        $user->created_by = null;
        
        // Use the full create method
        if ($user->create()) {
            $message = 'User created successfully';
            $message_type = 'success';
            // Clear form data on success
            $form_data = [];
        } else {
            $message = 'Error creating user. Please check database logs.';
            $message_type = 'error';
        }
        
    } else {
        // Collect form data for sticky form fields on error
        $form_data = $_POST;
        $message = 'Please fix the errors below';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Alert Recipient - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/forms.css">
     <link rel="stylesheet" href="../../assets/css/weather.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>➕ Create Alert Recipient</h1>
                <div class="user-details">
                    <a href="index.php">← Back to Alert User Management</a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Error:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-wrapper">
            <form action="user_create.php" method="POST">
                
                <div class="form-section">
                    <h3>Recipient Details</h3>
                    
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group phone-group">
                        <label>Phone Number</label>
                        <div class="input-group">
                            <div class="phone-field">
                                <label for="phone_country_code" class="phone-label">Area Code</label>
                                <input type="text" id="phone_country_code" name="phone_country_code"
                                       placeholder="+1" maxlength="5"
                                       value="<?php echo htmlspecialchars($form_data['phone_country_code'] ?? '+1'); ?>"
                                       class="form-control small">
                            </div>
                            <div class="phone-field">
                                <label for="phone_number" class="phone-label">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number"
                                       placeholder="555-555-5555"
                                       value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>"
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Address Details</h3>
                    <div class="form-group">
                        <label for="address_line_1">Address Line 1 *</label>
                        <input type="text" id="address_line_1" name="address_line_1" required
                               value="<?php echo htmlspecialchars($form_data['address_line_1'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="address_line_2">Address Line 2</label>
                        <input type="text" id="address_line_2" name="address_line_2"
                               value="<?php echo htmlspecialchars($form_data['address_line_2'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" required
                               value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="state_province">State/Province *</label>
                        <input type="text" id="state_province" name="state_province" required
                               value="<?php echo htmlspecialchars($form_data['state_province'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="zip_postal_code">ZIP/Postal Code *</label>
                        <input type="text" id="zip_postal_code" name="zip_postal_code" required
                               value="<?php echo htmlspecialchars($form_data['zip_postal_code'] ?? ''); ?>"
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country"
                               value="<?php echo htmlspecialchars($form_data['country'] ?? 'United States'); ?>"
                               class="form-control">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Alert Preferences</h3>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="prefers_email_alerts" name="prefers_email_alerts" value="1"
                               <?php echo isset($form_data['prefers_email_alerts']) ? 'checked' : 'checked'; ?>>
                        <label for="prefers_email_alerts">Receive alerts via Email</label>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="prefers_sms_alerts" name="prefers_sms_alerts" value="1"
                               <?php echo isset($form_data['prefers_sms_alerts']) ? 'checked' : ''; ?>>
                        <label for="prefers_sms_alerts">Receive alerts via SMS</label>
                    </div>
                    
                    <div class="form-group time-group">
                        <label for="digest_time">Daily Digest Time (Local Time)</label>
                        <div class="input-group">
                            <input type="time" id="digest_time" name="digest_time"
                                   value="<?php echo htmlspecialchars($form_data['digest_time'] ?? '07:00'); ?>"
                                   class="form-control">
                        </div>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="daily_alert_digest" name="daily_alert_digest" value="1"
                               <?php echo isset($form_data['daily_alert_digest']) ? 'checked' : ''; ?>>
                        <label for="daily_alert_digest">Receive daily alert digest (instead of immediate alerts)</label>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Account Status</h3>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               <?php echo isset($form_data['is_active']) ? 'checked' : 'checked'; ?>>
                        <label for="is_active">Active account (can receive alerts)</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="create_user" class="control-btn primary">💾 Create User</button>
                    <a href="users.php" class="control-btn secondary">❌ Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
