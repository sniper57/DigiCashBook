<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

$user_id = $_SESSION['user_id'];

// Owned books
$owned = $conn->prepare("SELECT b.id, b.name, b.opening_balance, b.created_at, bu.role_level FROM books b
                         JOIN book_users bu ON b.id = bu.book_id
                         WHERE bu.user_id = ? AND bu.role_level = 'owner'");
$owned->bind_param("i", $user_id);
$owned->execute();
$owned_books = $owned->get_result()->fetch_all(MYSQLI_ASSOC);

// Shared books
$shared = $conn->prepare("SELECT b.id, b.name, b.opening_balance, b.created_at, bu.role_level FROM books b
                         JOIN book_users bu ON b.id = bu.book_id
                         WHERE bu.user_id = ? AND bu.role_level in ('editor','viewer')");
$shared->bind_param("i", $user_id);
$shared->execute();
$shared_books = $shared->get_result()->fetch_all(MYSQLI_ASSOC);


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book') {
        $name = trim($_POST['name']);
        $opening = floatval($_POST['opening_balance']);

        $stmt = $conn->prepare("INSERT INTO books (user_id, name, opening_balance) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $user_id, $name, $opening);
        $stmt->execute();

        //<---- Audit Logging
        $_Audit_Action = 'SAVE'; //-- SAVE/UPDATE/DELETE
        $_Audit_ModuleName = 'BOOKS'; //-- Transaction/User
        $_Audit_PrimaryKey =  $stmt->insert_id;//-- PrimaryKey ID of the Data
        $_Audit_Comment = 'SAVE ['. $name .']';// -- SAVE/UPDATE/DELETE - Description of Data
        log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
        //---->

        // Assign as owner in book_users
        $book_id = $stmt->insert_id;
        $conn->query("INSERT INTO book_users (book_id, user_id, role_level) VALUES ($book_id, $user_id, 'owner')");

        //<---- Audit Logging
        $_Audit_Action = 'SAVE'; //-- SAVE/UPDATE/DELETE
        $_Audit_ModuleName = 'BOOK_USERS'; //-- Transaction/User
        $_Audit_PrimaryKey =  $conn->insert_id;//-- PrimaryKey ID of the Data
        $_Audit_Comment = 'SAVE ['. $_SESSION['user_name'] .']';// -- SAVE/UPDATE/DELETE - Description of Data
        log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
        //---->

    } elseif ($action === 'edit_book') {
        $book_id = intval($_POST['book_id']);
        $name = trim($_POST['name']);
        $opening = floatval($_POST['opening_balance']);

        // Only owner can edit
        $stmt = $conn->prepare("SELECT role_level FROM book_users WHERE user_id = ? AND book_id = ?");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row && $row['role_level'] === 'owner') {
            $stmt = $conn->prepare("UPDATE books SET name = ?, opening_balance = ? WHERE id = ?");
            $stmt->bind_param("sdi", $name, $opening, $book_id);
            $stmt->execute();

            //<---- Audit Logging
            $_Audit_Action = 'UPDATE'; //-- SAVE/UPDATE/DELETE
            $_Audit_ModuleName = 'BOOKS'; //-- Transaction/User
            $_Audit_PrimaryKey =  $book_id;//-- PrimaryKey ID of the Data
            $_Audit_Comment = 'UPDATE ['. $name .']';// -- SAVE/UPDATE/DELETE - Description of Data
            log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
            //---->
        }

    } elseif ($action === 'delete_book') {
        $book_id = intval($_POST['book_id']);

        // Only owner can delete
        $stmt = $conn->prepare("SELECT role_level FROM book_users WHERE user_id = ? AND book_id = ?");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row && $row['role_level'] === 'owner') {

            //<---- Audit Logging
            $_Audit_Action = 'DELETE'; //-- SAVE/UPDATE/DELETE
            $_Audit_ModuleName = 'TRANSACTIONS/BOOK_USERS/BOOKS'; //-- Transaction/User
            $_Audit_PrimaryKey =  $book_id;//-- PrimaryKey ID of the Data
            $_Audit_Comment = 'DELETE TRANSACTIONS/BOOK_USERS/BOOKS ['. $book_id .']';// -- SAVE/UPDATE/DELETE - Description of Data
            log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
            //---->

            $conn->query("DELETE FROM transactions WHERE book_id = $book_id");
            $conn->query("DELETE FROM book_users WHERE book_id = $book_id");
            $conn->query("DELETE FROM books WHERE id = $book_id");
        }
    }

    header("Location: books.php");
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
  <title>My Books</title>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">

   <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Extra Styling for Card Effect (add in your <style> or main CSS file) -->
    <style>
    .book-card {
      background: #f7f8f4 !important;
      border-radius: 2rem !important;
      box-shadow: 0 6px 24px #0001;
      border: none;
    }
    </style>

</head>


<body>
<div class="container my-4" <?= (count($owned_books) == 0? "hidden" : "") ?>>
   <h2 class="mb-4 font-weight-bold" style="font-size:2rem;">My Books</h2>

    <button class="btn btn-success mb-3 btn-block" data-toggle="modal" data-target="#addBookModal">+ Add Book</button>

    <div class="input-group mb-4">
      <input type="text" class="form-control form-control-lg rounded-pill" placeholder="Search Book..." id="searchBook" style="font-weight:700; background:#f3f3f0; border:none;">
      <div class="input-group-append">
        <span class="input-group-text bg-transparent border-0" style="margin-left:-48px;"><i class="fa fa-search"></i></span>
      </div>
    </div>

    <!-- LOOP HERE -->

     <?php foreach ($owned_books as $book):

                  $book_id = $book['id'];
                  $opening_balance = floatval($book['opening_balance']);

                  // Compute running balance: opening_balance + all cashin - all cashout
                  $bal_stmt = $conn->prepare("SELECT
                SUM(CASE WHEN type = 'cashin' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'cashout' THEN amount ELSE 0 END) as total_out
                FROM transactions WHERE book_id = ?");
                  $bal_stmt->bind_param("i", $book_id);
                  $bal_stmt->execute();
                  $bal = $bal_stmt->get_result()->fetch_assoc();

                  $total_in = floatval($bal['total_in']);
                  $total_out = floatval($bal['total_out']);

                  $current_balance = $opening_balance + $total_in - $total_out;
                  $created_at = $book['created_at'] ? date("M d, Y h:i A", strtotime($book['created_at'])) : '-';

     ?>


        <!-- Book Card Start -->
      <div class="container-fluid book-card bg-light rounded shadow-sm mb-4 p-4 align-items-md-center">
          <div class="row">
              <div class="col-6">
                  <div class="font-weight-bold" style="font-size:1.5rem; letter-spacing:-.5px;"><?= htmlspecialchars($book['name']) ?></div>
                  <div class="mt-2 text-dark" style="font-size:1rem;">(<?php echo htmlspecialchars($created_at) ?>)</div>
              </div>
              <div class="col-6 text-md-right">
                  <div style="font-size:1rem; color:#111;">(Opening Balance: ₱<?= number_format($book['opening_balance'], 2) ?>)</div>
                  <div style="font-size:1.5rem; color:#19be7a; font-weight:600;">₱<?php echo number_format($current_balance, 2) ?></div>
              </div>
          </div>
          </br>
          <div class="row">
              <div class="col-lg-12 row">
                  <div class="col-lg-2 col-sm-6 pb-1">
                      <a href="transactions.php?book_id=<?= $book['id'] ?>" class="btn btn-primary btn-xs btn-block ml-1 mr-2">📂</br>Open</a>
                  </div>
                   <div class="col-lg-2 col-sm-6 pb-1">
                      <a href="ledger.php?book_id=<?= $book['id'] ?>" class="btn btn-success btn-xs btn-block ml-1 mr-2">📒</br>Ledger</a>
                  </div>
                   <div class="col-lg-2 col-sm-6 pb-1">
                      <a href="transactions_export.php?book_id=<?=intval($book['id'])?>" class="btn btn-warning btn-xs btn-block ml-1 mr-2">⬇️</br>Export</a>
                  </div>
                   <div class="col-lg-2 col-sm-6 pb-1">
                      <button class="btn btn-info btn-xs btn-block ml-1 mr-2" data-id="<?= $book['id'] ?>"
                      data-name="<?= htmlspecialchars($book['name']) ?>" data-balance="<?= $book['opening_balance'] ?>" >✎</br>Edit</button>
                  </div>
                   <div class="col-lg-2 col-sm-6 pb-1">
                      <button class="btn btn-danger btn-xs btn-block ml-1 mr-2" data-id="<?= $book['id'] ?>">❌</br>Delete</button>
                  </div>
                   <div class="col-lg-2 col-sm-6 pb-1">
                      <button class="btn btn-secondary btn-xs btn-block ml-1 mr-2 share-btn" data-book-id="<?=$book['id']?>"
                       data-book-name="<?=htmlspecialchars($book['name'])?>">🤝</br>Share</button>
                  </div>
              </div>
          </div>
      </div>

      <!-- Book Card End -->
    <!-- END LOOP -->
    <?php endforeach; ?>



  
<!-- SHARED BOOKS -->
<div class="container my-4 mb-5" <?= (count($shared_books) == 0? "hidden" : "") ?>>
   <h2 class="mb-4 font-weight-bold" style="font-size:2rem;">🤝 Shared Books</h2>

    <div class="input-group mb-4">
      <input type="text" class="form-control form-control-lg rounded-pill" placeholder="Search Shared Book..." id="searchShareBook" style="font-weight:700; background:#f3f3f0; border:none;">
      <div class="input-group-append">
        <span class="input-group-text bg-transparent border-0" style="margin-left:-48px;"><i class="fa fa-search"></i></span>
      </div>
    </div>

     <!-- LOOP SHARED HERE -->

     <?php foreach ($shared_books as $book):

                  $book_id = $book['id'];
                  $opening_balance = floatval($book['opening_balance']);

                  // Compute running balance: opening_balance + all cashin - all cashout
                  $bal_stmt = $conn->prepare("SELECT
                SUM(CASE WHEN type = 'cashin' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'cashout' THEN amount ELSE 0 END) as total_out
                FROM transactions WHERE book_id = ?");
                  $bal_stmt->bind_param("i", $book_id);
                  $bal_stmt->execute();
                  $bal = $bal_stmt->get_result()->fetch_assoc();

                  $total_in = floatval($bal['total_in']);
                  $total_out = floatval($bal['total_out']);

                  $current_balance = $opening_balance + $total_in - $total_out;
                  $created_at = $book['created_at'] ? date("M d, Y h:i A", strtotime($book['created_at'])) : '-';

     ?>

      <!-- Book Card Start -->
      <div class="container-fluid book-card bg-light rounded shadow-sm mb-4 p-4 align-items-md-center">
          <div class="row">
               <div class="col-6">
                  <div class="font-weight-bold" style="font-size:1.5rem; letter-spacing:-.5px;"><?= htmlspecialchars($book['name']) ?></div>
                  <div class="mt-2 text-dark" style="font-size:1rem;">(<?php echo htmlspecialchars($created_at) ?>)</div>
              </div>
              <div class="col-6 text-md-right">
                  <div style="font-size:1rem; color:#111;">(Opening Balance: ₱<?= number_format($book['opening_balance'], 2) ?>)</div>
                  <div style="font-size:1.5rem; color:#19be7a; font-weight:600;">₱<?php echo number_format($current_balance, 2) ?></div>
              </div>
          </div>
          </br>
          <div class="row">
              <div class="col-lg-12 row">
                  <div class="<?= ($book['role_level'] == 'editor' ? 'col-lg-3 col-sm-6 pb-1' : 'col-4 col-sm-6 pb-1') ?>">
                      <a href="transactions.php?book_id=<?= $book['id'] ?>" class="btn btn-primary btn-xs btn-block ml-1 mr-2">📂</br>Open</a>
                  </div>
                  <div class="<?= ($book['role_level'] == 'editor' ? 'col-lg-3 col-sm-6 pb-1' : 'col-4 col-sm-6 pb-1') ?>">
                      <a href="ledger.php?book_id=<?= $book['id'] ?>" class="btn btn-success btn-xs btn-block ml-1 mr-2">📒</br>Ledger</a>
                  </div>
                  <div class="<?= ($book['role_level'] == 'editor' ? 'col-lg-3 col-sm-6 pb-1' : 'col-4 col-sm-6 pb-1') ?>">
                      <a href="transactions_export.php?book_id=<?=intval($book['id'])?>" class="btn btn-warning btn-xs btn-block ml-1 mr-2">⬇️</br>Export</a>
                  </div>
                  <div class="<?= ($book['role_level'] == 'editor' ? 'col-lg-3 col-sm-6 pb-1' : 'col-4 col-sm-6 pb-1') ?>" <?= ($book['role_level'] == 'editor' ? '' : 'hidden') ?>>
                      <button class="btn btn-info btn-xs btn-block ml-1 mr-2" data-id="<?= $book['id'] ?>"
                      data-name="<?= htmlspecialchars($book['name']) ?>" data-balance="<?= $book['opening_balance'] ?>" >✎</br>Edit</button>
                  </div>
              </div>
          </div>
      </div>

      <!-- Book Card End -->
    <!-- END SHARED LOOP -->

    <?php endforeach; ?>


</div>

<!-- SHARE BOOK MODAL -->
<div class="modal fade" id="shareBookModal" tabindex="-1" aria-labelledby="shareBookModalLabel" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Share Book: <span id="modalBookName"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="inviteForm" class="form-inline mb-3">
            <input type="hidden" name="book_id" id="modalBookId">
            <label class="sr-only" for="email">Email</label>
            <input type="email" name="email" required class="form-control mb-2 mr-sm-2" placeholder="Invite by email">
            <select name="role_level" class="form-control mb-2 mr-sm-2" required>
                <option value="viewer">Viewer</option>
                <option value="editor">Editor</option>
            </select>
            <button type="submit" class="btn btn-success mb-2">Invite</button>
        </form>
        <div id="inviteMsg"></div>
        <h6>Shared Users</h6>
        <table class="table table-sm table-hover" id="shareTable">
            <thead>
                <tr>
                    <th>Name / Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <!-- Will be filled by AJAX -->
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


<!-- Add/Edit/Delete Modals -->
<?php include 'books_modals.php'; ?>

<script>
$(document).on('click', '.btn-edit', function () {
  $('#edit_book_id').val($(this).data('id'));
  $('#edit_book_name').val($(this).data('name'));
  $('#edit_book_balance').val($(this).data('balance'));
  $('#editBookModal').modal('show');
});
$(document).on('click', '.btn-delete', function () {
  $('#delete_book_id').val($(this).data('id'));
  $('#deleteBookModal').modal('show');
});


//SHARE BOOK FUNCTIONALITY
let currentBookId = null;

$('.share-btn').click(function(){
    const bookId = $(this).data('book-id');
    const bookName = $(this).data('book-name');
    $('#modalBookId').val(bookId);
    $('#modalBookName').text(bookName);
    currentBookId = bookId;
    loadShares(bookId);
    $('#inviteMsg').html('');
    $('#shareBookModal').modal('show');
});


$('#searchBook, #searchShareBook').on('input', function() {
    var query = $(this).val().toLowerCase().trim();
    $('.book-card').each(function() {
        // Search book title and section (edit as needed for your data)
        var title = $(this).find('.font-weight-bold').first().text().toLowerCase();
        if(title.indexOf(query) !== -1) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

function loadShares(bookId) {
    $.get('share_book_ajax.php', {action:'list', book_id:bookId}, function(res){
        let html = '';
        (res.data || []).forEach(row => {
            html += `<tr>
                <td>${row.name ? row.name + " ("+row.email+")" : row.invited_email}</td>
                <td>${row.role_level}</td>
                <td>${row.status}</td>
                <td>
                  ${row.role_level!=='owner' && row.status=='active' ?
                    `<button class="btn btn-warning btn-sm mt-1" onclick="changeRole(${row.id},'${row.role_level=='editor'?'viewer':'editor'}')">Set as ${row.role_level=='editor'?'Viewer':'Editor'}</button>
                     <button class="btn btn-danger btn-sm mt-1" onclick="revokeShare(${row.id})">Revoke</button>` : ''}
                  ${row.status=='pending' ? `<button class="btn btn-danger btn-sm mt-1" onclick="revokeShare(${row.id})">Cancel</button>` : ''}
                </td>
            </tr>`;
        });
        $('#shareTable tbody').html(html || '<tr><td colspan="4" class="text-center text-muted">No shared users yet.</td></tr>');
    },'json');
}

$('#inviteForm').submit(function(e){
    e.preventDefault();
    $.post('share_book_ajax.php', $(this).serialize() + '&action=invite', function(res){
        $('#inviteMsg').html('<div class="alert alert-'+(res.success?'success':'danger')+'">'+res.message+'</div>');
        if(res.success && currentBookId) loadShares(currentBookId);
    },'json');
});
function changeRole(id, role) {
    $.post('share_book_ajax.php', {action:'changerole', id, role_level:role}, function(res){
        if(currentBookId) loadShares(currentBookId);
    },'json');
}
function revokeShare(id) {
    if(confirm('Are you sure to revoke/cancel sharing for this user?'))
        $.post('share_book_ajax.php', {action:'revoke', id}, function(res){ if(currentBookId) loadShares(currentBookId); },'json');
}



</script>
</body>
</html>
