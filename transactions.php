<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
include 'navbar.php';

$book_id = intval($_GET['book_id'] ?? 0);

// Fetch all transactions, oldest first to compute running balance
$stmt = $conn->prepare("
  SELECT t.*, c.name AS category_name, m.name AS mode_name, u.name as user_name
  FROM transactions t
  LEFT JOIN categories c ON t.category_id = c.id
  LEFT JOIN transaction_modes m ON t.mode_id = m.id
  LEFT JOIN users u ON t.created_by = u.id
  WHERE t.book_id = ?
  ORDER BY t.date ASC, t.id ASC
");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

// GET BOOK USER LEVEL
// Shared books
$shared = $conn->prepare("SELECT bu.role_level FROM books b
                         JOIN book_users bu ON b.id = bu.book_id
                         WHERE b.id = ? AND bu.user_id = ? LIMIT 1");
$shared->bind_param("ii", $book_id, $_SESSION['user_id']);
$shared->execute();
$shared_books = $shared->get_result()->fetch_all(MYSQLI_ASSOC);


$transactions = [];
$balance = 0;
$total_in = 0;
$total_out = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'cashin') {
        $balance += $row['amount'];
        $total_in += $row['amount'];
    } else {
        $balance -= $row['amount'];
        $total_out += $row['amount'];
    }
    $row['running_balance'] = $balance;
    $transactions[] = $row;
}
$total_entries = count($transactions);
$transactions = array_reverse($transactions);

// Group by date (Y-m-d)
$grouped = [];
foreach ($transactions as $row) {
    $dateKey = date('Y-m-d', strtotime($row['date']));
    $grouped[$dateKey][] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transactions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
    body { background: #f6f7fb; }
    .summary-card {
      background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 20px 24px; margin-bottom: 18px;
    }
    .summary-card .net { font-size: 2rem; font-weight: bold; letter-spacing: .02em;}
    .summary-card .label { color: #888; }
    .summary-card .in, .day-total-amt { color: #27ae60; font-weight: 500;}
    .summary-card .out { color: #e74c3c; font-weight: 500;}
    .summary-card .reports-link { color: #2352f6; font-weight: 600; display: inline-block; margin-top: 8px; text-decoration: none;}
    .summary-card .reports-link i { margin-left: 2px;}
    .date-header { font-weight: 600; color: #444; background: #f6f7fb; padding-top: 18px; padding-bottom: 2px;}
    .trans-list { margin-bottom: 95px; }
    .trans-card {
      background: #fff; border-radius: 10px; padding: 13px 13px 10px 13px; margin-bottom: 13px; display: flex;
      align-items: flex-start; box-shadow: 0 1px 3px #0001; position: relative;
    }
    .trans-main { flex: 1; min-width: 0; }
    .trans-mode { font-size: .93rem; background: #e7f0fd; color: #2361be; font-weight: 500; border-radius: 6px; padding: 2px 11px 2px 8px; display: inline-block; margin-bottom: 2px;}
    .trans-desc { font-size: 1.06rem; color: #222; margin-bottom: 2px; word-break: break-all;}
    .trans-meta { font-size: .92rem; color: #b87ec7; margin-bottom: 2px;}
    .trans-attach { font-size: .97rem; color: #2361be; margin-bottom: 1px;}
    .trans-attach i { margin-right: 3px; }
    .trans-attach a { color: #2361be; text-decoration: underline; font-weight: 600; cursor: pointer; }
    .trans-cat { font-size: .91rem; color: #a58f1c; margin-bottom: 2px;}
    .trans-amt-wrap { min-width: 90px; text-align: right; margin-left: 12px;}
    .trans-amt { font-weight: bold; font-size: 1.25rem; letter-spacing: .01em;}
    .trans-amt.cashin { color: #1ba552;}
    .trans-amt.cashout { color: #db2222;}
    .trans-bal { color: #777; font-size: .94rem;}
    .trans-actions { position: absolute; bottom: 9px; right: 8px; z-index: 2; }
    .trans-actions .btn { padding: 0.21rem 0.56rem; font-size: 1.0rem;}
    .showing-entries { color: #888; font-size: 1rem; margin-bottom: 5px; }
    @media (max-width: 600px) {
      .summary-card { padding: 16px 10px; }
      .trans-card { padding: 11px 7px 9px 10px; }
      .trans-amt-wrap { min-width: 80px;}
    }
    .fab-bar {
      position: fixed; left: 0; right: 0; bottom: 0; background: #fff;
      border-top: 1px solid #e3e3e3; box-shadow: 0 -1px 8px #0001;
      display: flex; z-index: 1055; padding-bottom: env(safe-area-inset-bottom, 0);
    }
    .fab-bar .btn { flex: 1 1 50%; border-radius: 0; font-size: 1.13rem; padding: 19px 0;}
    .fab-bar .mid-fab {
      width: 52px; height: 52px; border-radius: 100px; position: absolute; left: 50%; transform: translate(-50%, -45%);
      background: #2459e5; color: #fff; border: 6px solid #fff; display: flex; align-items: center; justify-content: center; font-size: 1.55rem;
      box-shadow: 0 2px 7px #0002; z-index: 2;
    }

     /* Make Bootstrap modal-body scrollable if tall */
    .modal-dialog {
      max-width: 95vw;
      margin: 1.75rem auto;
    }
    @media (max-width: 600px) {
      .modal-dialog {
        margin: 10px auto;
      }
    }
    .modal-content {
      height: 80vh;
      display: flex;
      flex-direction: column;
    }
    .modal-body {
      overflow-y: auto;
      max-height: 62vh;
    }


    /* Attachments Modal */
    .modal-body .att-thumb { margin: 0 10px 10px 0; display: inline-block; vertical-align: top; }
    .modal-body img { max-width: 33.3vw; max-height: 100%; border-radius: 7px; margin-bottom: 5px; cursor: pointer; }
    .modal-body .att-file { font-size: 1.1rem; }
    /* Image Viewer */
    .image-viewer-modal .modal-dialog { max-width: 98vw; }
    .image-viewer-img { width: 100%; max-height: 82vh; object-fit: contain; display: block; margin: auto; background: #222; }
    .image-viewer-controls {
      position: absolute; top: 48%; left: 0; right: 0; display: flex; justify-content: space-between; z-index: 12;
      pointer-events: none;
    }
    .image-viewer-controls button { background: #000c; color: #fff; border: none; font-size: 2.5rem; padding: 0 16px; pointer-events: auto; }
    .pdf-embed { width: 100%; height: 78vh; border: none; background: #eee; }
    .day-total { display:flex; justify-content:flex-end; align-items:baseline; gap:10px; padding: 0 8px 12px; margin-top:-30px; }
    .day-total-label { color:#666; font-weight:600; }
    .day-total-amt { font-weight:700; font-size:1.25rem; }
    @media (max-width:600px){ .day-total-amt{font-size:1.15rem;} }
    </style>
</head>
<body>
    <div class="container pt-2 mb-4">

        <!-- SUMMARY (same as before) -->
        <div class="summary-card mt-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="label mb-1">Net Balance</div>
                    <div class="net"><?= number_format($balance,2) ?></div>
                </div>
                <div class="text-right">
                    <div class="label">Total In (+)</div>
                    <div class="in"><?= number_format($total_in,2) ?></div>
                    <div class="label">Total Out (-)</div>
                    <div class="out"><?= number_format($total_out,2) ?></div>
                </div>
            </div>
            <a href="ledger.php?book_id=<?= $book_id ?>" class="reports-link d-block text-center">
                VIEW REPORTS <i class="fa fa-arrow-right"></i>
            </a>
        </div>
        <div class="showing-entries text-center mb-2">
            Showing <?= $total_entries ?> entries
        </div>

        <!-- TRANSACTIONS LIST -->
        <div class="trans-list">
            <?php foreach ($grouped as $date => $rows): ?>
            <?php $day_total = 0; ?>
            <div class="date-header">
                <span class="pull-left"><?= date('d F Y', strtotime($date)) ?></span>
                <span class="day-total pull-right">Total: <span class="day-total-amt"><?= number_format(array_sum(array_column($rows,'amount')),2) ?></span></span>
            </div>
            <?php foreach ($rows as $row): $day_total += (float)$row['amount']; ?>
            <?php
                      $att_stmt = $conn->prepare("SELECT * FROM transaction_attachments WHERE transaction_id = ?");
                      $att_stmt->bind_param("i", $row['id']);
                      $att_stmt->execute();
                      $attachments = $att_stmt->get_result();
                      $attachmentList = [];
                      $attachmentCount = 0;
                      foreach ($attachments as $a) {
                          $attachmentList[] = $a;
                          $attachmentCount++;
                      }
                      $att_stmt->close();
            ?>
            <div class="trans-card">
                <div class="trans-main">
                    <div class="trans-mode"><?= htmlspecialchars($row['mode_name'] ?? '-') ?></div>
                    <div class="trans-desc"><?= htmlspecialchars($row['description']) ?></div>
                    <div class="trans-cat"><?= htmlspecialchars($row['category_name'] ?? '-') ?></div>
                    <div class="trans-attach">
                        <div <?= ($attachmentCount == 0 ? 'hidden' : '') ?>>
                            <i class="fa fa-paperclip"></i>
                            <a onclick="showAttachmentsModal(<?= htmlspecialchars(json_encode($attachmentList), ENT_QUOTES, 'UTF-8') ?>)">
                                <?= $attachmentCount ?> Attachment<?= $attachmentCount == 1 ? '' : 's' ?>
                            </a>
                        </div>
                        <div <?= ($attachmentCount == 0 ? '' : 'hidden') ?>>
                            &nbsp;
                        </div>
                    </div>
                    <div class="trans-meta">
                        Entry by <?= htmlspecialchars($row['user_name'] ?? 'You') ?> at <?= date('g:i a', strtotime($row['date'])) ?>
                    </div>
                </div>
                <div class="trans-amt-wrap">
                    <div class="trans-amt <?= $row['type'] ?>">
                        <?= number_format($row['amount'], 2) ?>
                    </div>
                    <div class="trans-bal">
                        Balance: <?= number_format($row['running_balance'], 2) ?>
                    </div>
                </div>
                <div class="trans-actions">
                    <button <?= ($shared_books[0]['role_level'] == 'owner' || $shared_books[0]['role_level'] == 'editor' ? '' : 'hidden') ?> class="btn btn-warning btn-sm btn-edit" data-id="<?= $row['id'] ?>">
                        <i class="fa fa-edit"></i>
                    </button>
                    <button <?= ($shared_books[0]['role_level'] == 'owner' ? '' : 'hidden') ?> class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id'] ?>">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Attachments Modal -->
    <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Attachments</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
          </div>
          <div class="modal-body" id="attachmentsBody"></div>
        </div>
      </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade image-viewer-modal" id="imageViewerModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#000; position:relative;">
          <button type="button" class="close text-white position-absolute" style="top:8px; right:18px; z-index:22;font-size:2.3rem;" data-dismiss="modal">&times;</button>
          <div class="image-viewer-controls">
            <button id="prevImageBtn" style="display:none" onclick="showPrevImage()">&lt;</button>
            <button id="nextImageBtn" style="display:none" onclick="showNextImage()">&gt;</button>
          </div>
          <img id="imageViewerImg" class="image-viewer-img" src="" alt="Attachment" />
        </div>
      </div>
    </div>

    <!-- PDF Viewer Modal -->
    <div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <button type="button" class="close position-absolute" style="top:7px; right:15px; z-index:22;font-size:2.1rem;" data-dismiss="modal">&times;</button>
          <embed id="pdfViewerEmbed" src="" type="application/pdf" class="pdf-embed"/>
        </div>
      </div>
    </div>

    <!-- Floating Action Bar & Toasts (same as your code) -->
    <div class="fab-bar" <?= ($shared_books[0]['role_level'] == 'owner' || $shared_books[0]['role_level'] == 'editor' ? '' : 'hidden') ?> >
        <button class="btn btn-success" onclick="openAddModal('cashin')">
            <i class="fa fa-plus"></i> CASH IN
        </button>
        <span class="mid-fab">
            <a href="dashboard.php"></a><i class="fa fa-home"></i></a>
        </span>
        <button class="btn btn-danger" onclick="openAddModal('cashout')">
            <i class="fa fa-minus"></i> CASH OUT
        </button>
    </div>

   

    <?php include 'transactions_modals.php'; ?>

    <script>
let imageList = [], imageIndex = 0;

function showAttachmentsModal(list) {
  let html = '';
  imageList = [];
  imageIndex = 0;
  if (!list.length) {
    html = "<div class='text-center text-muted'>No attachments.</div>";
  } else {
    for (let i = 0; i < list.length; i++) {
      const att = list[i];
      const url = "uploads/" + att.file_name;
      if (att.file_type === 'image') {
        imageList.push(url);
        html += `<div class="att-thumb"><img src="${url}" onclick="openImageViewer(${imageList.length - 1})" title="Tap to view full" /></div>`;
      } else if (att.file_type === 'pdf') {
        html += `<div class="att-thumb att-file"><a href="javascript:void(0)" onclick="openPdfViewer('${url}')"><i class="fa fa-file-pdf fa-2x text-danger"></i> ${att.file_name}</a></div>`;
      } else {
        html += `<div class="att-thumb att-file"><a href="${url}" target="_blank"><i class="fa fa-paperclip"></i> ${att.file_name}</a></div>`;
      }
    }
  }
  $('#attachmentsBody').html(html);
  $('#attachmentsModal').modal('show');
}

// Image Viewer Logic
function openImageViewer(idx) {
  if (imageList.length === 0) return;
  imageIndex = idx;
  $('#imageViewerImg').attr('src', imageList[imageIndex]);
  $('#attachmentsModal').modal('hide');
  $('#imageViewerModal').modal('show');
  // Show/hide arrows
  $('#prevImageBtn').toggle(imageList.length > 1);
  $('#nextImageBtn').toggle(imageList.length > 1);
}
function showPrevImage() {
  if (imageList.length <= 1) return;
  imageIndex = (imageIndex - 1 + imageList.length) % imageList.length;
  $('#imageViewerImg').attr('src', imageList[imageIndex]);
}
function showNextImage() {
  if (imageList.length <= 1) return;
  imageIndex = (imageIndex + 1) % imageList.length;
  $('#imageViewerImg').attr('src', imageList[imageIndex]);
}

// PDF Viewer Logic
function openPdfViewer(url) {
  $('#attachmentsModal').modal('hide');
  $('#pdfViewerEmbed').attr('src', url);
  $('#pdfViewerModal').modal('show');
}



// Handle Add/Edit Modal
function openAddModal(type) {
    $('#addTransactionModal input[name="type"]').val(type).prop('readonly', true);
    $('#addTransactionModal input[name="type_display"]').val(type === 'cashin' ? 'Cash-In' : 'Cash-Out');

    if (type == 'cashin') {
        $('.modal-custom-header').prop('class', 'modal-header modal-custom-header bg-success text-white'); 
        $('.btn-custom-color').prop('class', 'btn btn-custom-color btn-success'); 
        //$('.modal-dialog-scrollable').prop('class', 'modal-dialog modal-dialog-scrollable border border-success');                
    }
    else if (type == 'cashout') {
        $('.modal-custom-header').prop('class', 'modal-header modal-custom-header bg-danger text-white'); 
        $('.btn-custom-color').prop('class', 'btn btn-custom-color btn-danger');
        //$('.modal-dialog-scrollable').prop('class', 'modal-dialog modal-dialog-scrollable border border-danger');   
    }

    $('#addTransactionModal').modal('show');
}


// Edit modal open
$('.trans-list').on('click', '.btn-edit', function () {
  var id = $(this).data('id');
  $.get('fetch_transaction.php', {id}, function(data) {
    $('#edit_transaction_id').val(data.id);
    $('#edit_type').val(data.type);
    $('#edit_amount').val(data.amount);
    $('#edit_category').val(data.category_id);
    $('#edit_date').val(data.date);
    $('#edit_description').val(data.description);
    $('#edit_payment_mode').val(data.mode_id || data.transaction_mode_id);

    // Load existing attachments
    loadEditAttachments(data.attachments || []);
    $('#delete_attachments').val('');
    $('#edit_preview_area').html('');
    $('#edit_attachments').val('');

    $('#editTransactionModal').modal('show');
  }, 'json');
});

// Delete
$('.trans-list').on('click', '.btn-delete', function () {
    if (!confirm('Are you sure you want to delete this transaction?')) return;
    var id = $(this).data('id');
    $.post('transactions_ajax.php', { action: 'delete', transaction_id: id }, function (res) {
    if (res.success) {
        showToast('Transaction deleted!', 'success');
        setTimeout(()=>location.reload(), 900);
    } else {
        showToast('Error: ' + res.message, 'error');
    }
    }, 'json');
});



$('#add_attachments').on('change', function() {
  const files = Array.from(this.files);
  const preview = $('#add_preview_area');
  preview.html('');
  files.forEach((file, idx) => {
    let html = '';
    if (file.type.startsWith('image/')) {
      const url = URL.createObjectURL(file);
      html = `<div class="mr-2 mb-2 position-relative">
        <img src="${url}" style="max-width:33.3vw;max-height:100%;border-radius:5px;padding-top: 5px; padding-right: 5px;"/>
        <span class="remove-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-idx="${idx}">&times;</span>
      </div>`;
    } else if (file.type === 'application/pdf') {
      html = `<div class="mr-2 mb-2 position-relative">
        <i class="fa fa-file-pdf fa-2x text-danger"></i> ${file.name}
        <span class="remove-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-idx="${idx}">&times;</span>
      </div>`;
    }
    preview.append(html);
  });

  preview.off('click', '.remove-att').on('click', '.remove-att', function(){
    const index = $(this).data('idx');
    files.splice(index, 1);
    let dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    $('#add_attachments')[0].files = dt.files;
    $('#add_attachments').trigger('change');
  });
});

$('#edit_attachments').on('change', function() {
  const files = Array.from(this.files);
  const preview = $('#edit_preview_area');
  preview.html('');
  files.forEach((file, idx) => {
    let html = '';
    if (file.type.startsWith('image/')) {
      const url = URL.createObjectURL(file);
      html = `<div class="mr-2 mb-2 position-relative">
        <img src="${url}" style="max-width:33.3vw;max-height:100%;border-radius:5px;padding-top: 5px; padding-right: 5px;"/>
        <span class="remove-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-idx="${idx}">&times;</span>
      </div>`;
    } else if (file.type === 'application/pdf') {
      html = `<div class="mr-2 mb-2 position-relative">
        <i class="fa fa-file-pdf fa-2x text-danger"></i> ${file.name}
        <span class="remove-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-idx="${idx}">&times;</span>
      </div>`;
    }
    preview.append(html);
  });

  preview.off('click', '.remove-att').on('click', '.remove-att', function(){
    const index = $(this).data('idx');
    files.splice(index, 1);
    let dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    $('#edit_attachments')[0].files = dt.files;
    $('#edit_attachments').trigger('change');
  });
});

function loadEditAttachments(attachments) {
  const list = $('#attachment_list');
  list.html('');
  let html = '';
  attachments.forEach(att => {
    let elem = '';
    if (att.file_type === 'image') {
      elem = `<div class="mr-2 mb-2 position-relative" data-id="${att.id}">
        <img src="uploads/${att.file_name}" style="max-width:33.3vw;max-height:100%;border-radius:5px;padding-top: 5px; padding-right: 5px;"/>
        <span class="remove-old-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-id="${att.id}">&times;</span>
      </div>`;
    } else if (att.file_type === "pdf") {
      elem = `<div class="mr-2 mb-2 position-relative" data-id="${att.id}">
        <i class="fa fa-file-pdf fa-2x text-danger"></i> ${att.file_name}
        <span class="remove-old-att position-absolute bg-danger text-white rounded-circle" style="width: 20px;text-align: center;top:-8px;right:-8px;cursor:pointer;" data-id="${att.id}">&times;</span>
      </div>`;
    }
    html += elem;
  });
  list.html(html);

  list.off('click', '.remove-old-att').on('click', '.remove-old-att', function(){
    const id = $(this).data('id');
    let del = $('#delete_attachments').val().split(',').filter(Boolean);
    del.push(id);
    $('#delete_attachments').val(del.join(','));
    $(this).parent().remove();
  });
}


// Toast Notification
function showToast(message, type = 'success') {
    const id = 'toast-' + Math.random().toString(36).substring(7);
    const bg = type === 'error' ? 'bg-danger' : 'bg-success';
    const toast = `
    <div id="${id}" class="toast ${bg} text-white" role="alert" data-delay="2600" style="min-width: 200px;">
        <div class="toast-body">${message}</div>
    </div>`;
    $('#toastContainer').append(toast);
    const $toast = $('#' + id);
    $toast.toast('show');
    $toast.on('hidden.bs.toast', () => $toast.remove());
}

// Show toast for ?action
$(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    if (action === 'added') showToast('Transaction added successfully!');
    else if (action === 'updated') showToast('Transaction updated successfully!');
    else if (action === 'deleted') showToast('Transaction deleted successfully!');
});


</script>
</body>
</html>
