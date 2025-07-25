<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$email = trim($_POST['email'] ?? '');
$role_level = $_POST['role_level'] ?? '';
$book_id = intval($_POST['book_id'] ?? 0);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role_level, ['viewer', 'editor'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Validate current user is owner of the book
$stmt = $conn->prepare("SELECT 1 FROM book_users WHERE book_id = ? AND user_id = ? AND role_level = 'owner'");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Get target user's ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User with this email does not exist.']);
    exit;
}

$target_user_id = $res->fetch_assoc()['id'];

// Prevent sharing to self
if ($target_user_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'You are already the owner.']);
    exit;
}

// Check if already shared
$stmt = $conn->prepare("SELECT 1 FROM book_users WHERE book_id = ? AND user_id = ?");
$stmt->bind_param("ii", $book_id, $target_user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'User already has access to this book.']);
    exit;
}

// Add user to book
$stmt = $conn->prepare("INSERT INTO book_users (book_id, user_id, role_level) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $book_id, $target_user_id, $role_level);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Book shared successfully.']);
