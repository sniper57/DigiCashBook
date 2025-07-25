<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

require_once __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$user_id = $_SESSION['user_id'];
$book_id = intval($_GET['book_id'] ?? $_POST['book_id'] ?? 0);

// Load modes and categories for filter UI
$books = [];
$stmt = $conn->prepare("SELECT id, name FROM books WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mode_res = $stmt->get_result();
while ($row = $mode_res->fetch_assoc()) $books[$row['id']] = $row['name'];
$stmt->close();

$modes = [];
$stmt = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mode_res = $stmt->get_result();
while ($row = $mode_res->fetch_assoc()) $modes[$row['id']] = $row['name'];
$stmt->close();

$categories = [];
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cat_res = $stmt->get_result();
while ($row = $cat_res->fetch_assoc()) $categories[$row['id']] = $row['name'];
$stmt->close();

$preview_data = [];
$filter_book_id = $_POST['book_id'] ?? '';

$filter_type = $_POST['type'] ?? '';
$filter_mode_id = $_POST['mode_id'] ?? '';
$filter_category_id = $_POST['category_id'] ?? '';
$filter_from = $_POST['from'] ?? '';
$filter_to = $_POST['to'] ?? '';
$filter_desc = $_POST['desc'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    // Build filter SQL
    $filter_sql = "WHERE book_id = ?";
    $params = ($book_id == 0 ? [$filter_book_id] : [$book_id]);
    $types = "i";

    if ($filter_type && in_array($filter_type, ['cashin','cashout'])) {
        $filter_sql .= " AND type = ?";
        $params[] = $filter_type;
        $types .= "s";
    }
    if ($filter_mode_id && isset($modes[$filter_mode_id])) {
        $filter_sql .= " AND mode_id = ?";
        $params[] = $filter_mode_id;
        $types .= "i";
    }
    if ($filter_category_id && isset($categories[$filter_category_id])) {
        $filter_sql .= " AND category_id = ?";
        $params[] = $filter_category_id;
        $types .= "i";
    }
    if ($filter_from) {
        $filter_sql .= " AND DATE(date) >= ?";
        $params[] = $filter_from;
        $types .= "s";
    }
    if ($filter_to) {
        $filter_sql .= " AND DATE(date) <= ?";
        $params[] = $filter_to;
        $types .= "s";
    }

    if ($filter_desc) {
        $filter_sql .= " AND description LIKE ?";
        $params[] = '%' . $filter_desc . '%';
        $types .= "s";
    }

    //var_dump($filter_sql);

    $sql = "SELECT * FROM transactions $filter_sql ORDER BY date ASC, id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $res_total =  $res->num_rows;
    while ($row = $res->fetch_assoc()) {
        $preview_data[] = [
            'type' => $row['type'],
            'mode_id' => $row['mode_id'],
            'mode_display' => isset($modes[$row['mode_id']]) ? $row['mode_id'] . " - " . $modes[$row['mode_id']] : $row['mode_id'],
            'amount' => $row['amount'],
            'description' => $row['description'],
            'date' => $row['date']
        ];
    }
    $stmt->close();
}

// Export to Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_xlsx']) && !empty($_POST['export_data'])) {
    $export_data = json_decode($_POST['export_data'], true);

    // Get book name for filename
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

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'TYPE (cashin or cashout)');
    $sheet->setCellValue('B1', 'MODE_ID (select from dropdown)');
    $sheet->setCellValue('C1', 'AMOUNT');
    $sheet->setCellValue('D1', 'DESCRIPTION');
    $sheet->setCellValue('E1', 'DATE (YYYY-MM-DD HH:MM:SS)');

    $rownum = 2;
    foreach ($export_data as $row) {
        $sheet->setCellValue("A$rownum", $row['type']);
        $sheet->setCellValue("B$rownum", $row['mode_display']);
        $sheet->setCellValue("C$rownum", $row['amount']);
        $sheet->setCellValue("D$rownum", $row['description']);
        $sheet->setCellValue("E$rownum", $row['date']);
        $rownum++;
    }

    // Data validation for TYPE
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

    // Data validation for MODE_ID
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

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $export_filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Export Transactions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <style>
        .filter-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #0001; padding: 16px 20px; margin-bottom: 28px;}
        .form-control { border-radius: 7px; }
    </style>
</head>
<body>
<div class="container pt-5 mb-4">
    <div class="filter-card mx-auto">
		<h1>Generate Reports</h1>
        <form method="POST">
            <input type="hidden" name="book_id" value="<?=htmlspecialchars($book_id)?>">
            <div class="form-row">
                 <div class="form-group col-md-2">
                    <label>Books</label>
                    <select name="book_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach($books as $id => $name): ?>
                            <option value="<?=$id?>" <?=($filter_book_id==$id?'selected':'')?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All</option>
                        <option value="cashin" <?=($filter_type=="cashin"?'selected':'')?>>Cash In</option>
                        <option value="cashout" <?=($filter_type=="cashout"?'selected':'')?>>Cash Out</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Mode</label>
                    <select name="mode_id" class="form-control">
                        <option value="">All</option>
                        <?php foreach($modes as $id => $name): ?>
                            <option value="<?=$id?>" <?=($filter_mode_id==$id?'selected':'')?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">All</option>
                        <?php foreach($categories as $id => $name): ?>
                            <option value="<?=$id?>" <?=($filter_category_id==$id?'selected':'')?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>From</label>
                    <input type="date" name="from" class="form-control" value="<?=htmlspecialchars($filter_from)?>">
                </div>
                <div class="form-group col-md-2">
                    <label>To</label>
                    <input type="date" name="to" class="form-control" value="<?=htmlspecialchars($filter_to)?>">
                </div>
                 <div class="form-group col-md-12">
                    <label>Description</label>
                    <input type="text" name="desc" class="form-control" value="<?=htmlspecialchars($filter_desc)?>" placeholder="Description">
                </div>
            </div>
            <div class="text-center">
                <button type="submit" name="preview" class="btn btn-primary px-5">
                    <i class="fa fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['preview'])): ?>
        <div class="card mt-3 p-3">
            <h5>Result: <?php echo $res_total ?> Transaction(s)</h5>
            <?php if ($preview_data): ?>
                <form method="POST">
                    <input type="hidden" name="book_id" value="<?=htmlspecialchars($book_id)?>">
                    <input type="hidden" name="type" value="<?=htmlspecialchars($filter_type)?>">
                    <input type="hidden" name="mode_id" value="<?=htmlspecialchars($filter_mode_id)?>">
                    <input type="hidden" name="category_id" value="<?=htmlspecialchars($filter_category_id)?>">
                    <input type="hidden" name="from" value="<?=htmlspecialchars($filter_from)?>">
                    <input type="hidden" name="to" value="<?=htmlspecialchars($filter_to)?>">
                    <input type="hidden" name="desc" value="<?=htmlspecialchars($filter_desc)?>">
                    <input type="hidden" name="export_data" value="<?=htmlspecialchars(json_encode($preview_data))?>">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>TYPE</th>
                                    <th>MODE_ID</th>
                                    <th>AMOUNT</th>
                                    <th>DESCRIPTION</th>
                                    <th>DATE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($preview_data as $row): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($row['type'])?></td>
                                        <td><?=htmlspecialchars($row['mode_display'])?></td>
                                        <td><?=htmlspecialchars($row['amount'])?></td>
                                        <td><?=htmlspecialchars($row['description'])?></td>
                                        <td><?=htmlspecialchars($row['date'])?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <button type="submit" name="export_xlsx" class="btn btn-success px-5">
                            <i class="fa fa-download"></i> Export to Excel
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-0">No data found for the selected filters.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
