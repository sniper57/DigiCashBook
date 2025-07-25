<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
require_once __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$user_id = $_SESSION['user_id'];
$book_id = intval($_GET['book_id'] ?? 0);

// Fetch Book Name
$book_name = '';
$stmt = $conn->prepare("SELECT name FROM books WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$stmt->bind_result($book_name);
$stmt->fetch();
$stmt->close();

$book_name_export = $book_name ? str_replace(' ', '_', $book_name) : 'Book';
$export_date = date('mdY');
$export_time = date('Hi');
$export_filename = "Transactions_Export_{$book_name_export}_{$export_date}_{$export_time}.xlsx";


// Get all transaction modes (id => name)
$modes = [];
$stmt = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mode_res = $stmt->get_result();
while ($row = $mode_res->fetch_assoc()) $modes[$row['id']] = $row['name'];
$stmt->close();

// Fetch transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE book_id = ? ORDER BY date ASC, id ASC");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$res = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header same as import template
$sheet->setCellValue('A1', 'TYPE (cashin or cashout)');
$sheet->setCellValue('B1', 'MODE_ID (select from dropdown)');
$sheet->setCellValue('C1', 'AMOUNT');
$sheet->setCellValue('D1', 'DESCRIPTION');
$sheet->setCellValue('E1', 'DATE (YYYY-MM-DD HH:MM:SS)');

// Data
$rownum = 2;
while ($row = $res->fetch_assoc()) {
    $sheet->setCellValue("A$rownum", $row['type']);
    // Export as "id - name" for dropdown compatibility
    $mode_name = isset($modes[$row['mode_id']]) ? $modes[$row['mode_id']] : '';
    $sheet->setCellValue("B$rownum", $row['mode_id'] . ($mode_name ? " - $mode_name" : ''));
    $sheet->setCellValue("C$rownum", $row['amount']);
    $sheet->setCellValue("D$rownum", $row['description']);
    $sheet->setCellValue("E$rownum", $row['date']);
    $rownum++;
}

// Add data validation for TYPE
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
for ($i = 2; $i < $rownum; $i++) {
    $validation = $sheet->getCell("A$i")->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST)
        ->setErrorStyle(DataValidation::STYLE_STOP)
        ->setAllowBlank(false)
        ->setShowInputMessage(true)
        ->setShowErrorMessage(true)
        ->setShowDropDown(true)
        ->setFormula1('"cashin,cashout"');
}

// Add hidden sheet for modes (dropdown)
$hiddenSheet = $spreadsheet->createSheet();
$hiddenSheet->setTitle('modes');
$m = 1;
foreach ($modes as $id => $name) {
    $hiddenSheet->setCellValue("A$m", $id . " - " . $name);
    $m++;
}
for ($i = 2; $i < $rownum; $i++) {
    $dv = $sheet->getCell("B$i")->getDataValidation();
    $dv->setType(DataValidation::TYPE_LIST)
        ->setAllowBlank(false)
        ->setShowDropDown(true)
        ->setFormula1('=modes!$A$1:$A$' . ($m - 1));
}
$spreadsheet->setActiveSheetIndex(0);

//<---- Audit Logging
$_Audit_Action = 'EXPORT'; //-- SAVE/UPDATE/DELETE
$_Audit_ModuleName = 'TRANSACTIONS'; //-- Transaction/User
$_Audit_PrimaryKey = $book_id;//-- PrimaryKey ID of the Data
$_Audit_Comment = 'EXPORT ['. $export_filename .']';// -- SAVE/UPDATE/DELETE - Description of Data
log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
//---->

// Output to browser
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $export_filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
