<?php
$host = 'localhost';
$db   = 'cashbook_db';     // Update with your actual database name
$user = 'root';            // Update with your DB username
$pass = '';                // Update with your DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $conn = new mysqli($host, $user, $pass, $db); // For legacy `mysqli` usage
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


// ====================
// GLOBAL ADMIN LOGGING
// ====================
// Usage: log_admin_action($admin_id, $action_type, $target_user_id = null, $details = null);
// Example: log_admin_action($_SESSION['user_id'], 'DELETE_USER', 12, 'Deleted user 12 for violation');
function log_admin_action($admin_id, $action_type, $target_user_id = null, $details = null) {
    $conn = $GLOBALS['conn'];
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_user_id, details) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param(
        "isis",
        $admin_id,
        $action_type,
        $target_user_id,
        $details
    );
    $stmt->execute();
    $stmt->close();
    return true;
}

?>
