<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM transactions WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
  echo json_encode([]);
  exit;
}

// Fetch attachments
$att_stmt = $conn->prepare("SELECT id, file_name, file_type FROM transaction_attachments WHERE transaction_id = ?");
$att_stmt->bind_param("i", $id);
$att_stmt->execute();
$attachments = $att_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$row['attachments'] = $attachments;

header('Content-Type: application/json');
echo json_encode($row);
exit;
