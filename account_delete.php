<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$password = $_POST['confirm_password'] ?? '';

// Fetch password hash
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: profile.php?error=User not found.");
    exit;
}

$row = $res->fetch_assoc();
if (!password_verify($password, $row['password_hash'])) {
    header("Location: profile.php?error=Incorrect password.");
    exit;
}

// Find books owned by this user
$stmt = $conn->prepare("SELECT id FROM books WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owned_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($owned_books as $book) {
    $book_id = $book['id'];

    // Delete transactions
    $conn->prepare("DELETE FROM transactions WHERE book_id = ?")->bind_param("i", $book_id)->execute();

    // Delete shared users of the book
    $conn->prepare("DELETE FROM book_users WHERE book_id = ?")->bind_param("i", $book_id)->execute();

    // Delete book
    $conn->prepare("DELETE FROM books WHERE id = ?")->bind_param("i", $book_id)->execute();
}

// Delete all book_user links
$conn->prepare("DELETE FROM book_users WHERE user_id = ?")->bind_param("i", $user_id)->execute();

// Finally delete user
$conn->prepare("DELETE FROM users WHERE id = ?")->bind_param("i", $user_id)->execute();

// Logout and redirect
session_destroy();
header("Location: login.php");
exit;
