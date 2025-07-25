<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$book_user_id = intval($_POST['id'] ?? 0);

// Get the book_id and user_id of the record to be removed
$stmt = $conn->prepare("SELECT book_id, user_id FROM book_users WHERE id = ?");
$stmt->bind_param("i", $book_user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Record not found.']);
    exit;
}

$row = $res->fetch_assoc();
$book_id = $row['book_id'];

// Confirm current user is the owner of the book
$stmt = $conn->prepare("SELECT 1 FROM book_users WHERE book_id = ? AND user_id = ? AND role_level = 'owner'");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$check = $stmt->get_result();

if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Proceed to delete
$stmt = $conn->prepare("DELETE FROM book_users WHERE id = ?");
$stmt->bind_param("i", $book_user_id);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Access revoked.']);
