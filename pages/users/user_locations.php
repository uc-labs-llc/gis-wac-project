<?php
// user_locations.php - User Location Assignment Management

// Load database connection
require_once '../../config/database.php';

$database = new Database();
$db = $database->connect();

// Initialize variables
$message = '';
$message_type = '';
$selected_user_id = null;
$all_users = [];
$selected_user = null;
$assigned_locations = [];
$available_locations = [];

// Handle ASSIGNMENT submission FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_location'])) {
    $user_id = $_POST['user_id'] ?? null;
    $location_id = $_POST['location_id'] ?? null;
    $relationship_type = $_POST['relationship_type'] ?? '';
    
    if ($user_id && $location_id && $relationship_type) {
        try {
            // Check if assignment already exists
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM user_locations WHERE user_id = ? AND location_id = ?");
            $check_stmt->execute([$user_id, $location_id]);
            $exists = $check_stmt->fetchColumn();
            
            if ($exists > 0) {
                $message = 'This location is already assigned to the user.';
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO user_locations (user_id, location_id, relationship_type) VALUES (?, ?, ?)");
                if ($stmt->execute([$user_id, $location_id, $relationship_type])) {
                    $message = 'Location assigned successfully!';
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Error assigning location: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
    }
    $selected_user_id = $user_id;
}

// Handle DELETION request SECOND
if (isset($_GET['delete_assignment'])) {
    $user_location_id = $_GET['delete_assignment'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    
    if ($user_location_id && $user_id) {
        try {
            $stmt = $db->prepare("DELETE FROM user_locations WHERE user_location_id = ?");
            if ($stmt->execute([$user_location_id])) {
                $message = 'Location assignment removed successfully.';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error removing assignment: ' . $e->getMessage();
            $message_type = 'error';
        }
        $selected_user_id = $user_id;
    }
}

// Fetch all users
try {
    $stmt = $db->query("SELECT user_id, username, first_name, last_name FROM users ORDER BY first_name, last_name");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading users: ' . $e->getMessage();
    $message_type = 'error';
}

// Get selected user ID from GET parameter - THIS IS THE KEY FIX
if (isset($_GET['user_id'])) {
    $selected_user_id = $_GET['user_id'];
}

// If we have a selected user (from GET, POST, or deletion), load their data
if ($selected_user_id) {
    try {
        // Get user details
        $stmt = $db->prepare("SELECT user_id, username, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$selected_user_id]);
        $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selected_user) {
            // Get assigned locations
            $stmt = $db->prepare("
                SELECT ul.user_location_id, ul.relationship_type, 
                       l.location_id, l.location_name, l.city, l.state_province 
                FROM user_locations ul 
                JOIN locations l ON ul.location_id = l.location_id 
                WHERE ul.user_id = ?
                ORDER BY l.location_name
            ");
            $stmt->execute([$selected_user_id]);
            $assigned_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all available locations (not assigned to this user)
            $stmt = $db->prepare("
                SELECT location_id, location_name, city, state_province 
                FROM locations 
                WHERE location_id NOT IN (
                    SELECT location_id FROM user_locations WHERE user_id = ?
                )
                ORDER BY location_name
            ");
            $stmt->execute([$selected_user_id]);
            $available_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $message = 'Error loading user data: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Location Assignment</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .split-view { display: flex; gap: 20px; margin-top: 20px; }
        .user-list-panel { flex: 0 0 300px; }
        .assignment-panel { flex-grow: 1; }
        .user-list { max-height: 500px; overflow-y: auto; border: 1px solid #ddd; }
        .user-list a { 
            display: block; 
            padding: 12px; 
            border-bottom: 1px solid #eee; 
            text-decoration: none; 
            color: #333; 
            background: #f8f9fa;
        }
        .user-list a:hover { background: #e9ecef; }
        .user-list a.active { 
            background: #007bff; 
            color: white; 
            font-weight: bold;
        }
        .message { padding: 12px; margin: 15px 0; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .delete-btn { 
            background: #dc3545; 
            color: white; 
            padding: 4px 8px; 
            text-decoration: none; 
            border-radius: 3px; 
            font-size: 0.85em; 
            margin-left: 10px;
        }
        .delete-btn:hover { background: #c82333; }
        form { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; }
        label { display: block; margin-bottom: 15px; }
        select, button { 
            width: 100%; 
            padding: 8px; 
            margin-top: 5px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        button { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 10px; 
            cursor: pointer; 
            font-weight: bold; 
        }
        button:hover { background: #218838; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        .assignments-list { list-style: none; padding: 0; }
        .assignments-list li { 
            padding: 10px; 
            background: white; 
            margin-bottom: 5px; 
            border-radius: 4px; 
            border-left: 4px solid #007bff; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📍 User Location Assignment</h1>
        <a href="../../dashboard.php">← Back to Dashboard</a>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="split-view">
            <div class="user-list-panel">
                <h2>Select User</h2>
                <div class="user-list">
                    <?php foreach ($all_users as $user): ?>
                        <a href="?user_id=<?php echo $user['user_id']; ?>" 
                           class="<?php echo ($user['user_id'] == $selected_user_id) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="assignment-panel">
                <?php if ($selected_user): ?>
                    <h2>Assign Locations to: <?php echo htmlspecialchars($selected_user['first_name'] . ' ' . $selected_user['last_name']); ?></h2>

                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                        
                        <label>Location:
                            <select name="location_id" required>
                                <?php if (empty($available_locations)): ?>
                                    <option value="">-- No available locations --</option>
                                <?php else: ?>
                                    <option value="">-- Select location --</option>
                                    <?php foreach ($available_locations as $loc): ?>
                                        <option value="<?php echo $loc['location_id']; ?>">
                                            <?php echo htmlspecialchars($loc['location_name'] . ' - ' . $loc['city'] . ', ' . $loc['state_province']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </label>
                        
                        <label>Relationship Type:
                            <select name="relationship_type" required>
                                <option value="">-- Select relationship --</option>
                                <option value="VIEWER">Viewer</option>
                                <option value="EMERGENCY_CONTACT">Emergency Contact</option>
                                <option value="MANAGER">Manager</option>
                            </select>
                        </label>
                        
                        <button type="submit" name="assign_location" <?php echo empty($available_locations) ? 'disabled' : ''; ?>>
                            Assign Location
                        </button>
                    </form>

                    <h3>Current Assignments</h3>
                    <?php if (empty($assigned_locations)): ?>
                        <p>No locations assigned to this user.</p>
                    <?php else: ?>
                        <ul class="assignments-list">
                            <?php foreach ($assigned_locations as $assign): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($assign['location_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($assign['city'] . ', ' . $assign['state_province']); ?></small><br>
                                    <span>Role: <?php echo htmlspecialchars($assign['relationship_type']); ?></span>
                                    <a href="?user_id=<?php echo $selected_user_id; ?>&delete_assignment=<?php echo $assign['user_location_id']; ?>" 
                                       class="delete-btn"
                                       onclick="return confirm('Are you sure you want to remove this location assignment?')">Remove</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php else: ?>
                    <p style="padding: 40px; text-align: center; color: #6c757d;">
                        Select a user from the list to manage their location assignments.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
