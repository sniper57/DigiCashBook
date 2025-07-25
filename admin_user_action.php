<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

if ($_SESSION['user_role'] !== 'admin') {
  die("Access denied.");
}

$user_id = $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null;

// Ensure user ID is provided
if (!$user_id || !is_numeric($user_id)) {
  die("Invalid user ID.");
}

switch ($action) {
  case 'reset_password':
    $new_password = $_POST['new_password'] ?? '';
    if (strlen($new_password) < 6) {
      die("Password must be at least 6 characters.");
    }
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    $stmt->execute();

    //<---- Audit Logging
    $_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
    $_Audit_ModuleName = 'USERS'; //-- Transaction/User
    $_Audit_PrimaryKey = $user_id;//-- PrimaryKey ID of the Data
    $_Audit_Comment = 'CHANGE PASSWORD USERS ID: '. $user_id;// -- SAVE/UPDATE/DELETE - Description of Data
    log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
    //---->

    break;

  case 'delete_user':
    // Delete books owned by this user
    $stmt = $conn->prepare("SELECT id,name FROM books WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($books as $book) {
      $book_id = $book['id'];
      $conn->query("DELETE FROM transactions WHERE book_id = $book_id");
      $conn->query("DELETE FROM book_users WHERE book_id = $book_id");
      $conn->query("DELETE FROM books WHERE id = $book_id");

      //<---- Audit Logging
      $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
      $_Audit_ModuleName = 'TRANSACTIONS,BOOK_USER,BOOKS'; //-- Transaction/User
      $_Audit_PrimaryKey = $book_id;//-- PrimaryKey ID of the Data
      $_Audit_Comment = 'DELETE BOOK: ' . $book['name'];// -- SAVE/UPDATE/DELETE - Description of Data
      log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
      //---->
    }

    // Remove shared book access
    $stmt = $conn->prepare("DELETE FROM book_users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Finally, delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    //<---- Audit Logging
    $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
    $_Audit_ModuleName = 'USERS'; //-- Transaction/User
    $_Audit_PrimaryKey = $user_id;//-- PrimaryKey ID of the Data
    $_Audit_Comment = 'DELETE USERS ID: '. $user_id;// -- SAVE/UPDATE/DELETE - Description of Data
    log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
    //---->

    break;

  default:
    // Assume role change from dropdown
    $new_role = $_POST['new_role'] ?? null;
    if (!in_array($new_role, ['admin', 'manager', 'user'])) {
      die("Invalid role.");
    }
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();

    //<---- Audit Logging
    $_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
    $_Audit_ModuleName = 'USERS'; //-- Transaction/User
    $_Audit_PrimaryKey = $user_id;//-- PrimaryKey ID of the Data
    $_Audit_Comment = 'UPDATE USERS ROLE TO '. $new_role;// -- SAVE/UPDATE/DELETE - Description of Data
    log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
    //---->

    break;
}

header("Location: profile.php");
exit;
