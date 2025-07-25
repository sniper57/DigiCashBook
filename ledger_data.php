<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$book_id = intval($_GET['book_id'] ?? 0);

// Confirm access to the book
$stmt = $conn->prepare("SELECT role_level FROM book_users WHERE book_id = ? AND user_id = ?");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$role_res = $stmt->get_result();

if ($role_res->num_rows === 0) {
    die("Access denied.");
}

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$type = $_POST['type'] ?? '';
$category = $_POST['category'] ?? '';

$sql = "SELECT * FROM transactions WHERE book_id = ?";
$params = [$book_id];
$types = "i";

if ($start_date) {
    $sql .= " AND date >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if ($end_date) {
    $sql .= " AND date <= ?";
    $params[] = $end_date;
    $types .= "s";
}
if ($type) {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= "s";
}
if ($category) {
    $sql .= " AND category LIKE ?";
    $params[] = "%$category%";
    $types .= "s";
}
$sql .= " ORDER BY date ASC, id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

echo "<table class='table table-bordered'><thead>
<tr><th>Date</th><th>Type</th><th>Amount</th><th>Category</th><th>Description</th><th>Balance</th></tr></thead><tbody>";

$running = 0;
while ($row = $res->fetch_assoc()) {
    $amt = $row['amount'];
    $running += ($row['type'] === 'cashin') ? $amt : -$amt;
    echo "<tr>
            <td>{$row['date']}</td>
            <td>{$row['type']}</td>
            <td>₱" . number_format($amt, 2) . "</td>
            <td>{$row['category']}</td>
            <td>{$row['description']}</td>
            <td>₱" . number_format($running, 2) . "</td>
          </tr>";
}
echo "</tbody></table>";
