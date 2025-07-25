<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
include 'navbar.php';
require_once __DIR__.'/vendor/autoload.php'; // COMPOSER AUTOLOADER

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$user_id = $_SESSION['user_id'];
$books = [];
$stmt = $conn->prepare("SELECT id, name FROM books WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $books[] = $row;
$stmt->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Transactions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
</head>
<body>
<div class="container py-4">
    <h3>Import Transactions</h3>
    <form method="get" class="mb-4">
        <div class="form-group">
            <label for="book_id"><strong>Select Book:</strong></label>
            <select name="book_id" id="book_id" class="form-control" required>
                <option value="">-- Select Book --</option>
                <?php foreach($books as $book): ?>
                    <option value="<?= $book['id'] ?>" <?= (isset($_GET['book_id']) && $_GET['book_id']==$book['id']?'selected':'') ?>><?= htmlspecialchars($book['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Select</button>
    </form>

    <?php if (isset($_GET['book_id']) && $_GET['book_id']): ?>
    <div class="mb-3">
        <a href="transactions_bulk.php?book_id=<?=intval($_GET['book_id'])?>&download_template=1" class="btn btn-success">
            <i class="fa fa-download"></i> Download Excel Upload Template
        </a>
    </div>

    <form method="post" action="transactions_bulk_process.php" enctype="multipart/form-data">
        <input type="hidden" name="book_id" value="<?=intval($_GET['book_id'])?>">
        <div class="form-group">
            <label for="file"><strong>Upload Filled Excel File:</strong></label>
            <input type="file" name="file" id="file" accept=".xlsx,.xls" class="form-control-file" required>
        </div>
        <button type="submit" name="preview" class="btn btn-warning">Preview & Validate</button>
    </form>
    <?php endif; ?>

    <?php
    // Download template
    if (isset($_GET['download_template']) && $_GET['book_id']) {
        // Get modes for dropdown
        $modes = [];
        $stmt = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $mode_res = $stmt->get_result();
        while ($row = $mode_res->fetch_assoc()) $modes[] = $row;
        $stmt->close();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'TYPE (cashin or cashout)');
        $sheet->setCellValue('B1', 'MODE_ID (select from dropdown)');
        $sheet->setCellValue('C1', 'AMOUNT');
        $sheet->setCellValue('D1', 'DESCRIPTION');
        $sheet->setCellValue('E1', 'DATE (YYYY-MM-DD HH:MM:SS)');

        // Data validation for TYPE
        for($row=2;$row<=101;$row++) {
            $validation = $sheet->getCell("A$row")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1('"cashin,cashout"');
        }
        // Data validation for MODE_ID (show id + name)
        $hiddenSheet = $spreadsheet->createSheet();
        $hiddenSheet->setTitle('modes');
        $i=1;
        foreach ($modes as $m) {
            $hiddenSheet->setCellValue("A$i", $m['id']." - ".$m['name']);
            $i++;
        }
        // Data validation for MODE_ID
        for($row=2;$row<=101;$row++) {
            $dv = $sheet->getCell("B$row")->getDataValidation();
            $dv->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(false)
                ->setShowDropDown(true)
                ->setFormula1('=modes!$A$1:$A$'.($i-1));
        }
        $spreadsheet->setActiveSheetIndex(0);

        // CLEAN output buffer just before outputting file
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Transactions_Upload_Template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    ?>
</div>
</body>
</html>
