<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$default_book_id = $_POST['default_book_id'] ?? null;

if (empty($name)) {
    die("Name is required.");
}

// If a default book was selected, verify the user has access to it
if (!empty($default_book_id)) {
    $stmt = $conn->prepare("SELECT 1 FROM book_users WHERE book_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $default_book_id, $user_id);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows === 0) {
        die("Invalid default book selection.");
    }
} else {
    $default_book_id = null;
}

// Update user profile
$stmt = $conn->prepare("UPDATE users SET name = ?, default_book_id = ? WHERE id = ?");
$stmt->bind_param("sii", $name, $default_book_id, $user_id);
$stmt->execute();

//<---- Audit Logging
$_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
$_Audit_ModuleName = 'USERS'; //-- Transaction/User
$_Audit_PrimaryKey = $user_id;//-- PrimaryKey ID of the Data
$_Audit_Comment = 'UPDATE ['. $name .']';// -- SAVE/UPDATE/DELETE - Description of Data
log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
//---->

header("Location: profile.php?updated=1");
exit;
