<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$book_ids = $_GET['book_ids'] ?? [];
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));

if (!is_array($book_ids) || empty($book_ids)) {
    echo json_encode(['error' => 'No books selected']);
    exit;
}

// Sanitize and verify ownership
$placeholders = implode(',', array_fill(0, count($book_ids), '?'));
$types = str_repeat('i', count($book_ids) + 1); // books + user_id
$params = [...$book_ids, $user_id];

$query = "SELECT b.id FROM books b 
          JOIN book_users bu ON b.id = bu.book_id 
          WHERE b.id IN ($placeholders) AND bu.user_id = ? AND bu.role_level = 'owner'";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$valid_ids = [];
while ($row = $res->fetch_assoc()) {
    $valid_ids[] = $row['id'];
}
if (empty($valid_ids)) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));

// Rebuild query with safe IDs
$safe_ids = implode(",", array_map('intval', $valid_ids));
$sql = "SELECT type, amount, date, category FROM transactions 
        WHERE book_id IN ($safe_ids) AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Initialize
$cashin_total = 0;
$cashout_total = 0;
$chart_labels = [];
$cashin_daily = [];
$cashout_daily = [];
$net_daily = [];
$category_map = [];

$days_in_month = date('t', strtotime($start_date));
for ($i = 1; $i <= $days_in_month; $i++) {
    $day = str_pad($i, 2, '0', STR_PAD_LEFT);
    $label = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$day";
    $chart_labels[] = $label;
    $cashin_daily[$label] = 0;
    $cashout_daily[$label] = 0;
    $net_daily[$label] = 0;
}

// Populate
while ($row = $result->fetch_assoc()) {
    $type = $row['type'];
    $amount = (float)$row['amount'];
    $date = $row['date'];
    $category = $row['category'] ?: 'Uncategorized';

    if (!isset($category_map[$category])) $category_map[$category] = 0;

    if ($type === 'cashin') {
        $cashin_total += $amount;
        $cashin_daily[$date] += $amount;
    } elseif ($type === 'cashout') {
        $cashout_total += $amount;
        $cashout_daily[$date] += $amount;
        $category_map[$category] += $amount;
    }
}

// Compute net
foreach ($chart_labels as $label) {
    $net_daily[$label] = $cashin_daily[$label] - $cashout_daily[$label];
}

// Response
$response = [
    'total_cashin' => number_format($cashin_total, 2),
    'total_cashout' => number_format($cashout_total, 2),
    'net_balance' => number_format($cashin_total - $cashout_total, 2),
    'chart' => [
        'labels' => $chart_labels,
        'cashin' => array_values($cashin_daily),
        'cashout' => array_values($cashout_daily),
        'net' => array_values($net_daily)
    ],
    'categories' => [
        'labels' => array_keys($category_map),
        'values' => array_values($category_map)
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
