<?php
// auth_check.php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// OPTIONAL: Restrict access to specific roles
function require_role($role_required) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role_required) {
        echo "<div style='padding:20px;color:red;'>Access Denied. You do not have the required permission.</div>";
        exit;
    }
}
?>
