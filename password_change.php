<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $err = "All password fields are required.";
    header("Location: profile.php?error=". $err);
    exit;
}
if ($new_password !== $confirm_password) {
    $err = "New password and confirmation do not match.";
    header("Location: profile.php?error=". $err);
    exit;
}
if (strlen($new_password) < 6) {
    $err = "Password must be at least 6 characters.";
    header("Location: profile.php?error=". $err);
    exit;
}

// Fetch current hashed password
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $err = "User not found.";
    header("Location: profile.php?error=". $err);
    exit;
}

$row = $res->fetch_assoc();
$hashed_password = $row['password_hash'];

// Verify current password
if (!password_verify($current_password, $hashed_password)) {
    $err = "Current password is incorrect.";
    header("Location: profile.php?error=". $err);
    exit;
}

// Update to new hashed password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->bind_param("si", $new_hash, $user_id);
$stmt->execute();

//<---- Audit Logging
$_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
$_Audit_ModuleName = 'USERS'; //-- Transaction/User
$_Audit_PrimaryKey = $user_id;//-- PrimaryKey ID of the Data
$_Audit_Comment = 'UPDATE PASSWORD ['. $user_id .']';// -- SAVE/UPDATE/DELETE - Description of Data
log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
//---->

// Redirect back to profile
header("Location: profile.php?password_changed=1");
exit;
