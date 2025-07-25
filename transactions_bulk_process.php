<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
require_once __DIR__.'/vendor/autoload.php'; // COMPOSER AUTOLOADER

use PhpOffice\PhpSpreadsheet\IOFactory;

$ALLOWED_TYPES = ['xlsx','xls'];
$errors = [];
$preview_data = [];
$mode_ids = [];
$user_id = $_SESSION['user_id'];

// Build mode_id => mode_name mapping
$mode_names = [];
$stmt = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $mode_ids[] = $row['id'];
    $mode_names[$row['id']] = $row['name'];
}
$stmt->close();

// Get allowed mode IDs
$stmt = $conn->prepare("SELECT id FROM transaction_modes WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $mode_ids[] = $row['id'];
$stmt->close();

function validate_text($txt) {
    $txt = trim($txt);
    if (preg_match('/<[^>]*script|onerror|onload|javascript:|[\'"]/', strtolower($txt))) return false;
    if (strlen($txt)>1000) return false;
    return htmlspecialchars($txt, ENT_QUOTES);
}

if (isset($_POST['preview'])) {
    $book_id = intval($_POST['book_id'] ?? 0);

    if (!isset($_FILES['file']) || !$_FILES['file']['tmp_name']) $errors[] = "No file uploaded.";
    else {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_TYPES)) $errors[] = "Invalid file type.";
        else {
            try {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                for ($i=1;$i<count($rows);$i++) {
                    $row = $rows[$i];
                    if (count($row)<5) continue;
                    $row_errors = [];
                    // TYPE
                    $type = strtolower(trim($row[0]));
                    if (!in_array($type, ['cashin','cashout'])) $row_errors[] = "Invalid TYPE";
                    // MODE_ID (expecting "id - name" format)
                    $mode_val = trim($row[1]);
                    if (preg_match('/^(\d+)\s*-\s*(.+)$/', $mode_val, $matches)) {
                        $mode_id = $matches[1];
                        $mode_name = $mode_names[$mode_id] ?? '(Unknown)';
                        if (!in_array($mode_id, $mode_ids)) $row_errors[] = "Invalid MODE_ID";
                    } else {
                        $row_errors[] = "Invalid MODE_ID format";
                        $mode_id = '';
                        $mode_name = '';
                    }
                    // AMOUNT
                    $amount = trim($row[2]);
                    if (!is_numeric($amount) || $amount <= 0) $row_errors[] = "Invalid AMOUNT";
                    // DESCRIPTION
                    $desc = validate_text($row[3]);
                    if ($desc===false || !$desc) $row_errors[] = "Invalid or unsafe DESCRIPTION";
                    // DATE
                    $date = trim($row[4]);
                    if (!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',$date)) $row_errors[] = "Invalid DATE (format: YYYY-MM-DD HH:MM:SS)";
                    // Save for preview
                    $preview_data[] = [
                        'row' => $i+1,
                        'type' => $type,
                        'mode_id' => $mode_id,      // store ID for import
                        'mode_name' => $mode_name,  // store name for preview
                        'amount' => $amount,
                        'desc' => $desc,
                        'date' => $date,
                        'errors' => $row_errors
                    ];
                }
            }
            catch (Exception $e) {
                $errors[] = "Failed to read Excel: " . $e->getMessage();
            }
        }
    }
    // Show preview table
?>
<html>
<head>
    <title>Preview Bulk Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
</head><body>
    <div class="container py-4">
        <h3>Preview Import Data</h3>
        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?=implode('<br>',$errors)?>
        </div>
        <a href="transactions_bulk.php?book_id=<?=$book_id?>" class="btn btn-secondary">Back</a>
        <?php else: ?>
        <form method="post" action="transactions_bulk_process.php">
            <input type="hidden" name="book_id" value="<?=$book_id?>" />
            <input type="hidden" name="import" value="1" />
            <input type="hidden" name="data" value="<?=base64_encode(serialize($preview_data))?>" />
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>TYPE</th>
                        <th>MODE</th>
                        <th>AMOUNT</th>
                        <th>DESCRIPTION</th>
                        <th>DATE</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($preview_data as $d): ?>
                    <tr <?=count($d['errors'])?'class="table-danger"':''?>>
                        <td>
                            <?=$d['row']?>
                        </td>
                        <td>
                            <?=htmlspecialchars($d['type'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($d['mode_name'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($d['amount'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($d['desc'])?>
                        </td>
                        <td>
                            <?=htmlspecialchars($d['date'])?>
                        </td>
                        <td>
                            <?php if ($d['errors']) echo implode(', ', $d['errors']); else echo '<span class="text-success">OK</span>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count(array_filter($preview_data,fn($d)=>$d['errors']))): ?>
            <div class="alert alert-warning">Some rows have errors. Please fix and re-upload.</div>
            <a href="transactions_bulk.php?book_id=<?=$book_id?>" class="btn btn-secondary">Back</a>
            <?php else: ?>
            <button class="btn btn-success">Confirm and Import</button>
            <a href="transactions_bulk.php?book_id=<?=$book_id?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
    exit;
}

if (isset($_POST['import']) && $_POST['data']) {
    $book_id = intval($_POST['book_id']);
    $data = unserialize(base64_decode($_POST['data']));
    $success = 0;
    $fail = 0;
    foreach($data as $row) {
        if ($row['errors']) { $fail++; continue; }
        $stmt = $conn->prepare("INSERT INTO transactions (book_id, type, mode_id, amount, description, date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isidssi", $book_id, $row['type'], $row['mode_id'], $row['amount'], $row['desc'], $row['date'], $user_id);
        $stmt->execute();
        if ($stmt->affected_rows) $success++; else $fail++;
        $stmt->close();
    }

    //<---- Audit Logging
    $_Audit_Action = 'SAVE'; //-- SAVE/UPDATE/DELETE
    $_Audit_ModuleName = 'TRANSACTIONS'; //-- Transaction/User
    $_Audit_PrimaryKey = $book_id;//-- PrimaryKey ID of the Data
    $_Audit_Comment = 'UPDATE [Book_ID: '. $book_id .']';// -- SAVE/UPDATE/DELETE - Description of Data
    log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
    //---->

?>
<html>
<head>
    <title>Import Complete</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <meta http-equiv="refresh" content="2;url=transactions.php?book_id=<?=$book_id?>" />
</head><body>
    <div class="container py-4">
        <h3>Import Complete</h3>
        <div class="alert alert-success">
            Successfully imported <?=$success?> records. <?=$fail? $fail.' failed.':''?>
        </div>
        <a href="transactions.php?book_id=<?=$book_id?>" class="btn btn-primary">View Transactions</a>
    </div>
</body>
</html>
<?php
    exit;
}
?>
