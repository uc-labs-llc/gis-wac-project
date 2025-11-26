<?php
class User {
    private $conn;
    private $table = 'users';

    // User properties
    public $user_id;
    public $username;
    public $email;
    public $password_hash;
    public $phone_country_code;
    public $phone_number;
    public $phone_verified;
    public $email_verified;
    public $first_name;
    public $middle_name;
    public $last_name;
    public $suffix;
    public $title;
    public $address_line_1;
    public $address_line_2;
    public $building_number;
    public $building_name;
    public $city;
    public $state_province;
    public $zip_postal_code;
    public $country;
    public $prefers_email_alerts;
    public $prefers_sms_alerts;
    public $prefers_push_alerts;
    public $alert_timezone;
    public $daily_alert_digest;
    public $digest_time;
    public $mfa_enabled;
    public $mfa_secret;
    public $last_login_at;
    public $failed_login_attempts;
    public $account_locked_until;
    public $is_active;
    public $is_staff;
    public $is_superuser;
    public $created_at;
    public $updated_at;
    public $date_joined;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all users
    public function get_all() {
        $query = 'SELECT * FROM ' . $this->table . ' ORDER BY is_staff DESC, first_name, last_name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get user by ID
    public function get_by_id($user_id) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user by username
    public function get_by_username($username) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE username = :username';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user by email
    public function get_by_email($email) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE email = :email';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Read all users
    public function read_all() {
        $query = 'SELECT user_id, username, first_name, last_name, email, is_staff 
                  FROM ' . $this->table . ' 
                  WHERE is_active = true 
                  ORDER BY last_name, first_name';

        $stmt = $this->conn->prepare($query);
        
        try {
            if ($stmt->execute()) {
                return $stmt;
            }
        } catch (PDOException $e) {
            error_log("read_all error: " . $e->getMessage());
            return null; 
        }
        return null;
    }

    // Create new user
    public function create() {
        $query = 'INSERT INTO ' . $this->table . ' 
                 (username, email, password_hash, phone_country_code, phone_number, 
                  first_name, middle_name, last_name, suffix, title, 
                  address_line_1, address_line_2, building_number, building_name, 
                  city, state_province, zip_postal_code, country, 
                  prefers_email_alerts, prefers_sms_alerts, prefers_push_alerts, 
                  alert_timezone, daily_alert_digest, digest_time, 
                  is_active, is_staff, is_superuser) 
                 VALUES 
                 (:username, :email, :password_hash, :phone_country_code, :phone_number,
                  :first_name, :middle_name, :last_name, :suffix, :title,
                  :address_line_1, :address_line_2, :building_number, :building_name,
                  :city, :state_province, :zip_postal_code, :country,
                  :prefers_email_alerts, :prefers_sms_alerts, :prefers_push_alerts,
                  :alert_timezone, :daily_alert_digest, :digest_time,
                  :is_active, :is_staff, :is_superuser)';
        
        $stmt = $this->conn->prepare($query);
        
        // Bind all parameters
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':phone_country_code', $this->phone_country_code);
        $stmt->bindParam(':phone_number', $this->phone_number);
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':middle_name', $this->middle_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':suffix', $this->suffix);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':address_line_1', $this->address_line_1);
        $stmt->bindParam(':address_line_2', $this->address_line_2);
        $stmt->bindParam(':building_number', $this->building_number);
        $stmt->bindParam(':building_name', $this->building_name);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state_province', $this->state_province);
        $stmt->bindParam(':zip_postal_code', $this->zip_postal_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':prefers_email_alerts', $this->prefers_email_alerts);
        $stmt->bindParam(':prefers_sms_alerts', $this->prefers_sms_alerts);
        $stmt->bindParam(':prefers_push_alerts', $this->prefers_push_alerts);
        $stmt->bindParam(':alert_timezone', $this->alert_timezone);
        $stmt->bindParam(':daily_alert_digest', $this->daily_alert_digest);
        $stmt->bindParam(':digest_time', $this->digest_time);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':is_staff', $this->is_staff);
        $stmt->bindParam(':is_superuser', $this->is_superuser);
        
        if ($stmt->execute()) {
            return true;
        }
        
        error_log("User create error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Create basic user
    public function create_basic($username, $email, $password_hash, $first_name, $last_name, $is_staff = false) {
        // Set properties for basic user creation
        $this->username = $username;
        $this->email = $email;
        $this->password_hash = $password_hash;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->is_staff = $is_staff;
        
        // Set defaults for other required fields
        $this->phone_country_code = '+1';
        $this->phone_number = '';
        $this->middle_name = null;
        $this->suffix = null;
        $this->title = null;
        $this->address_line_1 = '';
        $this->address_line_2 = '';
        $this->building_number = null;
        $this->building_name = null;
        $this->city = '';
        $this->state_province = '';
        $this->zip_postal_code = '';
        $this->country = 'United States';
        $this->prefers_email_alerts = 1;
        $this->prefers_sms_alerts = 0;
        $this->prefers_push_alerts = 0;
        $this->alert_timezone = 'America/New_York';
        $this->daily_alert_digest = 0;
        $this->digest_time = '07:00';
        $this->is_active = 1;
        $this->is_superuser = 0;
        
        return $this->create();
    }

    // Update user
    public function update() {
        $query = 'UPDATE ' . $this->table . ' 
                 SET username = :username,
                     email = :email,
                     phone_country_code = :phone_country_code,
                     phone_number = :phone_number,
                     first_name = :first_name,
                     middle_name = :middle_name,
                     last_name = :last_name,
                     suffix = :suffix,
                     title = :title,
                     address_line_1 = :address_line_1,
                     address_line_2 = :address_line_2,
                     building_number = :building_number,
                     building_name = :building_name,
                     city = :city,
                     state_province = :state_province,
                     zip_postal_code = :zip_postal_code,
                     country = :country,
                     prefers_email_alerts = :prefers_email_alerts,
                     prefers_sms_alerts = :prefers_sms_alerts,
                     prefers_push_alerts = :prefers_push_alerts,
                     alert_timezone = :alert_timezone,
                     daily_alert_digest = :daily_alert_digest,
                     digest_time = :digest_time,
                     is_active = :is_active,
                     is_staff = :is_staff,
                     is_superuser = :is_superuser,
                     updated_at = NOW()
                 WHERE user_id = :user_id';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone_country_code', $this->phone_country_code);
        $stmt->bindParam(':phone_number', $this->phone_number);
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':middle_name', $this->middle_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':suffix', $this->suffix);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':address_line_1', $this->address_line_1);
        $stmt->bindParam(':address_line_2', $this->address_line_2);
        $stmt->bindParam(':building_number', $this->building_number);
        $stmt->bindParam(':building_name', $this->building_name);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state_province', $this->state_province);
        $stmt->bindParam(':zip_postal_code', $this->zip_postal_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':prefers_email_alerts', $this->prefers_email_alerts);
        $stmt->bindParam(':prefers_sms_alerts', $this->prefers_sms_alerts);
        $stmt->bindParam(':prefers_push_alerts', $this->prefers_push_alerts);
        $stmt->bindParam(':alert_timezone', $this->alert_timezone);
        $stmt->bindParam(':daily_alert_digest', $this->daily_alert_digest);
        $stmt->bindParam(':digest_time', $this->digest_time);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':is_staff', $this->is_staff);
        $stmt->bindParam(':is_superuser', $this->is_superuser);
        
        if ($stmt->execute()) {
            return true;
        }
        
        error_log("User update error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Update user active status
    public function update_status($user_id, $is_active) {
        $query = 'UPDATE ' . $this->table . ' 
                 SET is_active = :is_active,
                     updated_at = NOW()
                 WHERE user_id = :user_id';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        
        if ($stmt->execute()) {
            return true;
        }
        
        error_log("User status update error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Delete user
    public function delete($user_id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    // Get alert recipients only (now with search and sort support)
    public function get_alert_recipients($search = '', $sort_by = 'name', $sort_dir = 'ASC') {
        // Define allowed columns for secure sorting (maps frontend param to database column)
        $allowed_sorts = [
            'name' => 'last_name',
            'city' => 'city',
            'state' => 'state_province',
            'zip' => 'zip_postal_code',
            'email' => 'email',
            'phone' => 'phone_number',
            'status' => 'is_active',
        ];

        // Validate and set order/direction
        $order_by = $allowed_sorts[$sort_by] ?? 'last_name';
        $direction = (strtoupper($sort_dir) === 'DESC') ? 'DESC' : 'ASC';
        
        $query = 'SELECT * FROM ' . $this->table . ' WHERE is_staff = false';

        // Add search filtering
        if (!empty($search)) {
            // Use ILIKE for case-insensitive searching in PostgreSQL
            $query .= ' AND (first_name ILIKE :search_term OR last_name ILIKE :search_term OR email ILIKE :search_term OR city ILIKE :search_term OR phone_number ILIKE :search_term)';
        }

        // Append sorting, using last_name as a secondary sort for stability
        $query .= " ORDER BY {$order_by} {$direction}, last_name ASC";

        $stmt = $this->conn->prepare($query);

        // Bind search parameter
        if (!empty($search)) {
            $search_param = '%' . $search . '%';
            $stmt->bindParam(':search_term', $search_param);
        }

        $stmt->execute();
        return $stmt;
    }
}
?>
