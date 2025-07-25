<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

header('Content-Type: application/json');
if ($_SESSION['user_role'] !== 'admin') exit(json_encode(['success'=>false,'message'=>'Access denied.']));

$action = $_REQUEST['action'] ?? '';

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) exit(json_encode(['success'=>false, 'message'=>'Required fields missing.']));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) exit(json_encode(['success'=>false, 'message'=>'Email already exists.']));

    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $hash, $role);
    $stmt->execute();

	header("Location: users.php?action=added");
	exit;

} elseif ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) exit(json_encode(['success'=>true, 'data'=>$res]));
    else exit(json_encode(['success'=>false, 'message'=>'Not found']));

} elseif ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';

    // Check if the new email is unique (not used by another user)
    $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $check->bind_param("si", $email, $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) exit(json_encode(['success'=>false, 'message'=>'Email already in use by another user.']));

    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password_hash=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $hash, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }
    $stmt->execute();
	header("Location: users.php?action=updated");
	exit;

} elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id == $_SESSION['user_id']) {
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    exit(json_encode(['success'=>true, 'message'=>'User Deleted.']));
}

exit(json_encode(['success'=>false, 'message'=>'Invalid action.']));
