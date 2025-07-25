<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

$user_id = $_SESSION['user_id'];
$book_ids = $_GET['book_ids'] ?? [];
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));

if (!is_array($book_ids) || empty($book_ids)) {
    die('No books selected');
}

// Verify ownership
$placeholders = implode(',', array_fill(0, count($book_ids), '?'));
$types = str_repeat('i', count($book_ids) + 1);
$params = [...$book_ids, $user_id];

$query = "SELECT b.id, b.name FROM books b 
          JOIN book_users bu ON b.id = bu.book_id 
          WHERE b.id IN ($placeholders) AND bu.user_id = ? AND bu.role_level = 'owner'";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$book_names = [];
$valid_ids = [];
while ($row = $res->fetch_assoc()) {
    $valid_ids[] = $row['id'];
    $book_names[] = $row['name'];
}

if (empty($valid_ids)) {
    die('Access denied');
}

$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));
$safe_ids = implode(",", array_map('intval', $valid_ids));

$sql = "SELECT date, type, amount, description, category 
        FROM transactions 
        WHERE book_id IN ($safe_ids) AND date BETWEEN ? AND ? 
        ORDER BY date ASC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

// TCPDF setup
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Cashbook App');
$pdf->SetTitle('Monthly Transaction Report');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

// Header
$pdf->Write(0, "Monthly Report - " . implode(", ", $book_names), '', 0, 'L', true, 0, false, false, 0);
$pdf->Write(0, "Month: " . date('F Y', strtotime($start_date)), '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(4);

// Table
$html = '<table border="1" cellpadding="4">
<tr style="background-color:#f0f0f0;">
  <th><b>Date</b></th>
  <th><b>Type</b></th>
  <th><b>Amount</b></th>
  <th><b>Category</b></th>
  <th><b>Description</b></th>
</tr>';

$total_in = 0;
$total_out = 0;

while ($row = $res->fetch_assoc()) {
    $amount = number_format($row['amount'], 2);
    $date = $row['date'];
    $type = $row['type'];
    $cat = $row['category'] ?: 'Uncategorized';
    $desc = htmlspecialchars($row['description']);
    $color = ($type === 'cashin') ? '#dff0d8' : '#f2dede';

    if ($type === 'cashin') $total_in += $row['amount'];
    else if ($type === 'cashout') $total_out += $row['amount'];

    $html .= "<tr style='background-color:$color'>
                <td>$date</td>
                <td>$type</td>
                <td align='right'>₱$amount</td>
                <td>$cat</td>
                <td>$desc</td>
              </tr>";
}
$html .= "</table><br>";

$net = $total_in - $total_out;
$html .= "<h4>Total Cash In: ₱" . number_format($total_in, 2) . "</h4>";
$html .= "<h4>Total Cash Out: ₱" . number_format($total_out, 2) . "</h4>";
$html .= "<h4><b>Net Balance: ₱" . number_format($net, 2) . "</b></h4>";

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('report_' . $year . '_' . $month . '.pdf', 'I');
