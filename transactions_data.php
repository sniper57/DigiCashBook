<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$book_id = intval($_GET['book_id'] ?? 0);
$columns = ['date', 'type', 'amount', 'mode_name', 'category_name', 'description'];

$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

$orderColIndex = $_POST['order'][0]['column'] ?? 0;
$orderCol = $columns[$orderColIndex] ?? 'date';
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';

$search = $_POST['search']['value'] ?? '';

// Total records
$totalQuery = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE book_id = ?");
$totalQuery->bind_param("i", $book_id);
$totalQuery->execute();
$recordsTotal = $totalQuery->get_result()->fetch_row()[0];

// Build filtered query
$sql = "
  SELECT t.*, c.name AS category_name, m.name AS mode_name
  FROM transactions t
  LEFT JOIN categories c ON t.category_id = c.id
  LEFT JOIN transaction_modes m ON t.mode_id = m.id
  WHERE t.book_id = ?
";

$params = [$book_id];
$types = "i";

if ($search) {
  $sql .= " AND (
    t.description LIKE ? OR 
    t.type LIKE ? OR 
    c.name LIKE ? OR 
    m.name LIKE ?
  )";
  $like = "%$search%";
  array_push($params, $like, $like, $like, $like);
  $types .= "ssss";
}

// Filtered total
$countQuery = $conn->prepare("SELECT COUNT(*) FROM ($sql) AS sub");
$countQuery->bind_param($types, ...$params);
$countQuery->execute();
$recordsFiltered = $countQuery->get_result()->fetch_row()[0];

// Final data query
$sql .= " ORDER BY t.$orderCol $orderDir LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  // Fetch attachments
  $a_stmt = $conn->prepare("SELECT * FROM transaction_attachments WHERE transaction_id = ?");
  $a_stmt->bind_param("i", $row['id']);
  $a_stmt->execute();
  $attachments = $a_stmt->get_result();
  $att_html = '';
  while ($a = $attachments->fetch_assoc()) {
    $file = "uploads/" . $a['file_name'];
    $att_html .= $a['file_type'] === 'image'
      ? "<a href='$file' data-lightbox='t{$row['id']}'><img src='$file' height='30'></a> "
      : "<a href='$file' target='_blank'><i class='fas fa-file-pdf text-danger'></i></a> ";
  }

  $buttons = "
    <button class='btn btn-sm btn-warning btn-edit' data-id='{$row['id']}'>Edit</button>
    <button class='btn btn-sm btn-danger btn-delete' data-id='{$row['id']}'>Delete</button>
  ";

  $data[] = [
    'date' => date('Y-m-d H:i:s', strtotime($row['date'])),
    'type' => ($row['type']=='cashin' ? '<span class="badge bg-success">Cash-In</span>' : ($row['type']=='cashout' ? '<span class="badge bg-danger">Cash-Out</span>' : '-')),
    'amount' => number_format($row['amount'], 2),
    'mode_name' => $row['mode_name'] ?? '-',
    'category_name' => $row['category_name'] ?? '-',
    'description' => htmlspecialchars($row['description']),
    'attachments' => $att_html,
    'actions' => $buttons
  ];
}

echo json_encode([
  "draw" => intval($_POST['draw'] ?? 1),
  "recordsTotal" => $recordsTotal,
  "recordsFiltered" => $recordsFiltered,
  "data" => $data
]);
