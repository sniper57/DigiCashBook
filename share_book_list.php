<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$book_id = intval($_GET['book_id'] ?? 0);

// Validate ownership
$stmt = $conn->prepare("SELECT 1 FROM book_users WHERE book_id = ? AND user_id = ? AND role_level = 'owner'");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<tr><td colspan="3">Access denied.</td></tr>';
    exit;
}

// Get shared users
$sql = "SELECT bu.id, u.email, bu.role_level
        FROM book_users bu
        JOIN users u ON bu.user_id = u.id
        WHERE bu.book_id = ? AND bu.role_level != 'owner'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<tr><td colspan="3">No shared users found.</td></tr>';
    exit;
}

while ($row = $res->fetch_assoc()) {
    echo "<tr>
            <td>" . htmlspecialchars($row['email']) . "</td>
            <td>" . htmlspecialchars(ucfirst($row['role_level'])) . "</td>
            <td><button class='btn btn-sm btn-danger btn-remove' data-id='" . $row['id'] . "'>Remove</button></td>
          </tr>";
}
