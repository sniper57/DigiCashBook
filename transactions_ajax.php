<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$upload_dir = "uploads/";
$allowed_ext = ['jpg','jpeg','png','gif','pdf'];

function save_attachments($transaction_id) {
    global $conn, $upload_dir, $allowed_ext;
    require_once __DIR__ . '/vendor/autoload.php';
    $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);

    foreach ($_FILES['attachments']['name'] as $i => $name) {
        $tmp = $_FILES['attachments']['tmp_name'][$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;
        $new = uniqid() . "." . $ext;
        $type = ($ext === 'pdf') ? 'pdf' : 'image';
        $dest_path = $upload_dir . $new;

        if ($type === 'image') {
            $max_size_mb = 1.5;
            $final_size_mb = 1.0;
            $size_mb = filesize($tmp) / (1024 * 1024);

            if ($size_mb > $max_size_mb) {
                try {
                    $image = $manager->make($tmp);
                    $quality = 90;
                    $width = $image->width();
                    $height = $image->height();

                    do {
                        ob_start();
                        if ($width > 1280) {
                            $width = max(800, (int)($width * 0.85));
                            $height = intval($width * $image->height() / $image->width());
                            $image->resize($width, $height, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                        }
                        $image->encode($ext == 'png' ? 'png' : 'jpg', $quality);
                        $img_data = ob_get_clean() ?: $image->getEncoded();
                        $try_size_mb = strlen($img_data) / (1024 * 1024);
                        $quality -= 5;
                    } while ($try_size_mb > $final_size_mb && $quality >= 55);

                    file_put_contents($dest_path, $img_data);
                }
                catch (Exception $e) {
                    move_uploaded_file($tmp, $dest_path);
                }
            } else {
                move_uploaded_file($tmp, $dest_path);
            }
        } else {
            // PDF: save as is
            move_uploaded_file($tmp, $dest_path);
        }

        // DB Save
        $stmt = $conn->prepare("INSERT INTO transaction_attachments (transaction_id, file_name, file_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $transaction_id, $new, $type);
        $stmt->execute();

        // <---- Audit Logging
        $_Audit_Action = 'SAVE';
        $_Audit_ModuleName = 'TRANSACTION_ATTACHMENTS';
        $_Audit_PrimaryKey = $stmt->insert_id;
        $_Audit_Comment = 'SAVE [' . $name . ']';
        log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' . $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
        // ---->
    }
}


if ($_POST['action'] == 'add') {
  $stmt = $conn->prepare("INSERT INTO transactions (book_id, type, amount, category_id, mode_id, date, description, created_by)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
	$stmt->bind_param("isdisssi", $_POST['book_id'], $_POST['type'], $_POST['amount'], $_POST['category_id'], $_POST['mode_id'], $_POST['date'], $_POST['description'], $_SESSION['user_id']);

  $stmt->execute();
  $transaction_id = $stmt->insert_id;
  if (!empty($_FILES['attachments']['name'][0])) save_attachments($transaction_id);

  //<---- Audit Logging
  $_Audit_Action = 'SAVE'; //-- SAVE/UPDATE/DELETE
  $_Audit_ModuleName = 'TRANSACTIONS'; //-- Transaction/User
  $_Audit_PrimaryKey = $transaction_id;//-- PrimaryKey ID of the Data
  $_Audit_Comment = 'SAVE ['. $_POST['type'] .' - ' . $_POST['amount'] . ' - ' . $_POST['description'] .']';// -- SAVE/UPDATE/DELETE - Description of Data
  log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
  //---->

  header("Location: transactions.php?book_id=" . $_POST['book_id'] . "&action=added");
  exit;
}

if ($_POST['action'] == 'edit') {
  $id = $_POST['transaction_id'];
  $stmt = $conn->prepare("UPDATE transactions SET type=?, amount=?, category_id=?, mode_id=?, date=?, description=? WHERE id=?");
  $stmt->bind_param("sdisssi", $_POST['type'], $_POST['amount'], $_POST['category_id'], $_POST['mode_id'], $_POST['date'], $_POST['description'], $id);
  $stmt->execute();

  //<---- Audit Logging
  $_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
  $_Audit_ModuleName = 'TRANSACTIONS'; //-- Transaction/User
  $_Audit_PrimaryKey = $id;//-- PrimaryKey ID of the Data
  $_Audit_Comment = 'UPDATE ['. $_POST['type'] .' - ' . $_POST['amount'] . ' - ' . $_POST['description'] .']';// -- SAVE/UPDATE/DELETE - Description of Data
  log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
  //---->

  // Handle attachment deletions
  if (!empty($_POST['delete_attachments'])) {
      $ids = explode(',', $_POST['delete_attachments']);
      foreach ($ids as $delId) {
          $delId = intval($delId);
          $stmt = $conn->prepare("SELECT file_name FROM transaction_attachments WHERE id=?");
          $stmt->bind_param("i", $delId);
          $stmt->execute();
          $res = $stmt->get_result();
          if ($row = $res->fetch_assoc()) {
              @unlink(__DIR__ . "/uploads/" . $row['file_name']);

              //<---- Audit Logging
              $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
              $_Audit_ModuleName = 'TRANSACTION_ATTACHMENTS'; //-- Transaction/User
              $_Audit_PrimaryKey = $delId;//-- PrimaryKey ID of the Data
              $_Audit_Comment = 'DELETE ['. $row['file_name'] .']';// -- SAVE/UPDATE/DELETE - Description of Data
              log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
              //---->
          }

          $delStmt = $conn->prepare("DELETE FROM transaction_attachments WHERE id=?");
          $delStmt->bind_param("i", $delId);
          $delStmt->execute();
      }
  }

  if (!empty($_FILES['attachments']['name'][0])) save_attachments($id);
  header("Location: transactions.php?book_id=" . $_POST['book_id'] . "&action=updated");
  exit;
}

if ($_POST['action'] == 'delete') {
  $id = intval($_POST['transaction_id']);

  // Confirm ownership or access
  $check = $conn->prepare("SELECT t.book_id, b.user_id FROM transactions t JOIN books b ON t.book_id = b.id WHERE t.id=?");
  $check->bind_param("i", $id);
  $check->execute();
  $result = $check->get_result();
  if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
  }
  $row = $result->fetch_assoc();
  $book_id = $row['book_id'];

  // Owner or editor check
  $allowed = $row['user_id'] == $_SESSION['user_id'];
  if (!$allowed) {
    $shared = $conn->prepare("SELECT role_level FROM book_users WHERE book_id = ? AND user_id = ?");
    $shared->bind_param("ii", $book_id, $_SESSION['user_id']);
    $shared->execute();
    $r = $shared->get_result()->fetch_assoc();
    $allowed = $r && in_array($r['role_level'], ['owner', 'editor']);
  }

  if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
  }

  // Delete attachments
  $att = $conn->prepare("SELECT file_name FROM transaction_attachments WHERE transaction_id=?");
  $att->bind_param("i", $id);
  $att->execute();
  $files = $att->get_result();
  while ($f = $files->fetch_assoc()) {
    @unlink("uploads/" . $f['file_name']);

    //<---- Audit Logging
    $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
    $_Audit_ModuleName = 'TRANSACTION_ATTACHMENTS'; //-- Transaction/User
    $_Audit_PrimaryKey = $id;//-- PrimaryKey ID of the Data
    $_Audit_Comment = 'DELETE ['. $f['file_name'] .']';// -- SAVE/UPDATE/DELETE - Description of Data
    log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
    //---->

  }
  $conn->query("DELETE FROM transaction_attachments WHERE transaction_id=$id");

  // Delete transaction
  $conn->query("DELETE FROM transactions WHERE id=$id");

  //<---- Audit Logging
  $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
  $_Audit_ModuleName = 'TRANSACTIONS'; //-- Transaction/User
  $_Audit_PrimaryKey = $id;//-- PrimaryKey ID of the Data
  $_Audit_Comment = 'DELETE [TRANSACTIONS ID: '. $id .']';// -- SAVE/UPDATE/DELETE - Description of Data
  log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
  //---->

  echo json_encode(['success' => true]);
  exit;
}
