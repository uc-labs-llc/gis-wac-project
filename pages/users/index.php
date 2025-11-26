<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Location.php';

// Session check and staff check removed for public management (Security stripped)

$database = new Database();
$db = $database->connect();
$userModel = new User($db);

// --- Handle Search and Sort Parameters ---
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$sort_by = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_STRING) ?? 'name';
$sort_dir = filter_input(INPUT_GET, 'sort_dir', FILTER_SANITIZE_STRING) ?? 'ASC';

// Helper function to create the sort link URL
function get_sort_link($column, $current_sort_by, $current_sort_dir, $current_search) {
    $next_sort_dir = ($current_sort_by === $column && $current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $url = "?sort_by={$column}&sort_dir={$next_sort_dir}";
    if (!empty($current_search)) {
        $url .= "&search=" . urlencode($current_search);
    }
    return $url;
}
// ----------------------------------------


// Handle user actions
$message = '';
$message_type = '';

// Delete user
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    if ($userModel->delete($user_id)) {
        $message = 'User deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Error deleting user';
        $message_type = 'error';
    }
}

// Toggle user active status
if (isset($_GET['toggle_user'])) {
    $user_id = $_GET['toggle_user'];
    $user = $userModel->get_by_id($user_id);
    if ($user) {
        $new_status = $user['is_active'] ? 0 : 1; // Fix: Convert boolean to integer
        // NOTE: update_status must be a new method added to your User model for this to work
        if ($userModel->update_status($user_id, $new_status)) {
            $message = 'User status updated';
            $message_type = 'success';
        } else {
            $message = 'Error updating user status';
            $message_type = 'error';
        }
    }
}

// Get alert recipients (non-staff users) with current search and sort
// NOTE: This assumes you have updated User::get_alert_recipients() to accept these parameters
$users_result = $userModel->get_alert_recipients($search, $sort_by, $sort_dir); 
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Alert User Management - GIS-WAC</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/tables.css">
    <link rel="stylesheet" href="../../assets/css/weather.css">
    <style>
        /* Add basic style for the search form */
        .search-container {
            margin-bottom: 20px;
        }
        .search-container input[type="text"] {
            padding: 10px;
            width: 300px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-container button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .sort-indicator {
            font-size: 0.8em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h1>🔔 Alert User Management</h1>
                <div class="user-details">
                    <a href="../../dashboard.php">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="user-actions">
            <a href="user_create.php" class="control-btn primary">
                ➕ Create Alert Recipient
            </a>
        </div>

        <div class="search-container">
            <form method="GET" action="index.php">
                <input type="text" name="search" placeholder="Search by Name, Email, or City..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search) || !empty($sort_by) || $sort_dir !== 'ASC'): ?>
                    <a href="index.php" class="control-btn secondary">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-container">
            <?php if (!empty($users)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">
                                    <a href="<?php echo get_sort_link('name', $sort_by, $sort_dir, $search); ?>">
                                        Name
                                        <?php if ($sort_by === 'name'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 15%;">Address</th>
                                <th style="width: 10%;">
                                    <a href="<?php echo get_sort_link('city', $sort_by, $sort_dir, $search); ?>">
                                        City
                                        <?php if ($sort_by === 'city'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 5%;">
                                    <a href="<?php echo get_sort_link('state', $sort_by, $sort_dir, $search); ?>">
                                        State
                                        <?php if ($sort_by === 'state'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 5%;">
                                    <a href="<?php echo get_sort_link('zip', $sort_by, $sort_dir, $search); ?>">
                                        Zip
                                        <?php if ($sort_by === 'zip'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 15%;">
                                    <a href="<?php echo get_sort_link('email', $sort_by, $sort_dir, $search); ?>">
                                        Email
                                        <?php if ($sort_by === 'email'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 8%;">
                                    <a href="<?php echo get_sort_link('phone', $sort_by, $sort_dir, $search); ?>">
                                        Phone
                                        <?php if ($sort_by === 'phone'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 5%;">
                                    <a href="<?php echo get_sort_link('status', $sort_by, $sort_dir, $search); ?>">
                                        Status
                                        <?php if ($sort_by === 'status'): ?>
                                            <span class="sort-indicator"><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th style="width: 27%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['address_line_1'] . ($user['address_line_2'] ? ' ' . $user['address_line_2'] : '')); ?></td>
                                    <td><?php echo htmlspecialchars($user['city']); ?></td>
                                    <td><?php echo htmlspecialchars($user['state_province']); ?></td>
                                    <td><?php echo htmlspecialchars($user['zip_postal_code']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                            $phone = $user['phone_number'] ? $user['phone_country_code'] . ' ' . $user['phone_number'] : 'N/A';
                                            echo htmlspecialchars($phone); 
                                        ?>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-indicator status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="action-cell">
                                        <div class="action-buttons">
                                            <a href="?toggle_user=<?php echo htmlspecialchars($user['user_id']); ?>&search=<?php echo urlencode($search); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_dir=<?php echo urlencode($sort_dir); ?>" 
                                               class="action-btn toggle" 
                                               title="Toggle Status">
                                                <?php echo $user['is_active'] ? '🛑' : '✅'; ?>
                                            </a>
                                            <a href="user_edit.php?user_id=<?php echo htmlspecialchars($user['user_id']); ?>" 
                                               class="action-btn edit" 
                                               title="Edit User">
                                                ✏️
                                            </a>
                                            <a href="user_locations.php?user_id=<?php echo htmlspecialchars($user['user_id']); ?>" 
                                               class="action-btn assign" 
                                               title="Assign Locations">
                                                🗺️
                                            </a>
                                            
                                            <?php if ($user['user_id'] !== ($_SESSION['user_id'] ?? '')): ?>
                                                <a href="?delete_user=<?php echo htmlspecialchars($user['user_id']); ?>&search=<?php echo urlencode($search); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_dir=<?php echo urlencode($sort_dir); ?>" 
                                                   class="action-btn delete" 
                                                   title="Delete User"
                                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    🗑️
                                                </a>
                                            <?php else: ?>
                                                <span class="action-btn disabled" title="Cannot delete your own account">🚫</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data-state">
                    <div class="no-data-icon">👥</div>
                    <h3>
                        <?php echo !empty($search) ? "No results found for \"".htmlspecialchars($search)."\"" : "No Alert Recipients Found"; ?>
                    </h3>
                    <p><?php echo !empty($search) ? "Try a different search term or " : "Get started by creating your first alert recipient."; ?></p>
                    <div class="action-buttons-center">
                        <a href="user_create.php" class="control-btn primary">
                            ➕ Add First Recipient
                        </a>
                        <?php if (!empty($search)): ?>
                            <a href="index.php" class="control-btn secondary">View All Users</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
