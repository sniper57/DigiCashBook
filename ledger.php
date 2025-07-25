<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
include 'navbar.php';

$book_id = intval($_GET['book_id'] ?? 0);

// Filters from GET
$type_filter = $_GET['type'] ?? '';
$mode_filter = $_GET['mode'] ?? '';
$category_filter = $_GET['category'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$page_size = 20;

// Build filter WHERE
$where = ["t.book_id = ?"];
$params = [$book_id];
$types = "i";
if ($type_filter)   { $where[] = "t.type=?"; $params[] = $type_filter; $types .= "s"; }
if ($mode_filter)   { $where[] = "t.mode_id=?"; $params[] = $mode_filter; $types .= "i"; }
if ($category_filter) { $where[] = "t.category_id=?"; $params[] = $category_filter; $types .= "i"; }
if ($start_date)    { $where[] = "t.date >= ?"; $params[] = $start_date; $types .= "s"; }
if ($end_date)      { $where[] = "t.date <= ?"; $params[] = $end_date; $types .= "s"; }

$where_sql = implode(" AND ", $where);

// Total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM transactions t WHERE $where_sql");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_entries = $count_stmt->get_result()->fetch_row()[0];

// Pagination
$offset = ($page-1) * $page_size;

// Fetch for running balance (we need all up to this page)
$stmt = $conn->prepare("
    SELECT t.*, c.name AS category_name, m.name AS mode_name, u.name as user_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN transaction_modes m ON t.mode_id = m.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE $where_sql
    ORDER BY t.date ASC, t.id ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$all_result = $stmt->get_result();
$all = [];
$balance = 0;
while ($row = $all_result->fetch_assoc()) {
    if ($row['type'] === 'cashin') $balance += $row['amount'];
    else $balance -= $row['amount'];
    $row['running_balance'] = $balance;
    $all[] = $row;
}
// Show only this page (from end, newest first)
$display = array_slice(array_reverse($all), $offset, $page_size);
$grouped = [];
foreach ($display as $row) {
    $dateKey = date('Y-m-d', strtotime($row['date']));
    $grouped[$dateKey][] = $row;
}

// Mode/category lists for filters
$modes = $conn->query("SELECT id, name FROM transaction_modes ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$total_in = 0;
$total_out = 0;
foreach ($all as $row) {
    if ($row['type'] === 'cashin') $total_in += $row['amount'];
    else $total_out += $row['amount'];
}


function selected($val, $cur) { return $val == $cur ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ledger</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
    body { background: #f6f7fb; }
    .summary-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 20px 24px; margin-bottom: 18px; }
    .date-header { font-weight: 600; color: #444; background: #f6f7fb; padding-top: 18px; padding-bottom: 2px;}
    .ledger-list { margin-bottom: 95px; }
    .trans-card { background: #fff; border-radius: 10px; padding: 13px 13px 10px 13px; margin-bottom: 13px; display: flex;
      align-items: flex-start; box-shadow: 0 1px 3px #0001; position: relative; }
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
    .showing-entries { color: #888; font-size: 1rem; margin-bottom: 5px; }
    .ledger-filters { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 10px 12px 2px 12px; margin-bottom: 18px; }
    .ledger-filters label { font-weight: 500; color: #345; margin-bottom: 2px;}
    @media (max-width: 600px) {
      .summary-card, .ledger-filters { padding: 13px 6px; }
      .trans-card { padding: 11px 7px 9px 10px; }
      .trans-amt-wrap { min-width: 80px;}
    }
    /* Modal viewer styles same as transactions.php */
    .modal-content { max-height: 88vh; display: flex; flex-direction: column; }
    .modal-body { overflow-y: auto; max-height: 62vh; }
    .modal-body .att-thumb { margin: 0 10px 10px 0; display: inline-block; vertical-align: top; }
    .modal-body img { max-width: 120px; max-height: 100px; border-radius: 7px; margin-bottom: 5px; cursor:pointer; }
    .modal-body .att-file { font-size: 1.1rem; }
    .image-viewer-modal .modal-dialog { max-width: 98vw; }
    .image-viewer-img { width: 100%; max-height: 82vh; object-fit: contain; display: block; margin: auto; background: #222; }
    .image-viewer-controls { position: absolute; top: 48%; left: 0; right: 0; display: flex; justify-content: space-between; z-index: 12; pointer-events: none; }
    .image-viewer-controls button { background: #000c; color: #fff; border: none; font-size: 2.5rem; padding: 0 16px; pointer-events: auto; }
    .pdf-embed { width: 100%; height: 78vh; border: none; background: #eee; }
    </style>
</head>
<body>
<div class="container pt-2 mb-4">

        <!-- SUMMARY CARD (add this before filters) -->
    <div class="summary-card d-flex flex-column flex-md-row align-items-center justify-content-between mb-4" style="background:#fff; border-radius:16px; box-shadow:0 2px 12px #0001; padding:30px 18px 18px 30px; min-height:110px;">
      <div class="flex-fill" style="min-width:140px;">
        <div class="label mb-1" style="color:#888;">Net Balance</div>
        <div class="net" style="font-size:2.1rem; font-weight:700; letter-spacing:.02em;"><?= number_format($balance,2) ?></div>
      </div>
      <div class="text-center flex-fill" style="min-width:150px;">
        <a href="transactions.php?book_id=<?= $book_id ?>" class="reports-link d-block" style="color:#2554e4; font-weight:600; text-decoration:none; font-size:1.1rem; margin-bottom:4px;">
            <i class="fa fa-arrow-left"></i> VIEW TRANSACTIONS 
        </a>
      </div>
      <div class="text-right flex-fill" style="min-width:170px;">
        <div class="label" style="color:#888;">Total In (+)</div>
        <div class="in" style="color:#21a555; font-weight:600;"><?= number_format($total_in,2) ?></div>
        <div class="label" style="color:#888;">Total Out (-)</div>
        <div class="out" style="color:#e34747; font-weight:600;"><?= number_format($total_out,2) ?></div>
      </div>
    </div>


    <div class="ledger-filters mb-3">
      <form method="get" class="form-row align-items-end">
        <input type="hidden" name="book_id" value="<?= $book_id ?>" />
        <div class="form-group col-6 col-md-2">
          <label>Type</label>
          <select class="form-control" name="type">
            <option value="">All</option>
            <option value="cashin" <?= selected('cashin', $type_filter) ?>>Cash-In</option>
            <option value="cashout" <?= selected('cashout', $type_filter) ?>>Cash-Out</option>
          </select>
        </div>
        <div class="form-group col-6 col-md-2">
          <label>Mode</label>
          <select class="form-control" name="mode">
            <option value="">All</option>
            <?php foreach($modes as $m): ?>
            <option value="<?= $m['id'] ?>" <?= selected($m['id'], $mode_filter) ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-6 col-md-2">
          <label>Category</label>
          <select class="form-control" name="category">
            <option value="">All</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= selected($c['id'], $category_filter) ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-6 col-md-2">
          <label>From</label>
          <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="form-group col-6 col-md-2">
          <label>To</label>
          <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="form-group col-6 col-md-2">
          <button class="btn btn-primary btn-block">Filter</button>
        </div>
      </form>
    </div>

    <div class="showing-entries text-center mb-2">
        Showing <?= $total_entries ?> entries
    </div>

    <!-- LEDGER LIST -->
    <div class="ledger-list">
        <?php foreach ($grouped as $date => $rows): ?>
        <div class="date-header"><?= date('d F Y', strtotime($date)) ?></div>
        <?php foreach ($rows as $row): ?>
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
                    <i class="fa fa-paperclip"></i>
                    <a onclick="showAttachmentsModal(<?= htmlspecialchars(json_encode($attachmentList), ENT_QUOTES, 'UTF-8') ?>)">
                        <?= $attachmentCount ?> Attachment<?= $attachmentCount == 1 ? '' : 's' ?>
                    </a>
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
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center align-items-center my-4">
      <?php if($page > 1): ?>
        <a href="<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>" class="btn btn-outline-primary mr-2">&laquo; Prev</a>
      <?php endif; ?>
      <span class="mx-2">Page <?= $page ?> / <?= ceil($total_entries/$page_size) ?></span>
      <?php if($offset + $page_size < $total_entries): ?>
        <a href="<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>" class="btn btn-outline-primary ml-2">Next &raquo;</a>
      <?php endif; ?>
    </div>
</div>

<!-- Attachments Modal, Image Viewer Modal, PDF Viewer Modal (identical to transactions.php) -->
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
<div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <button type="button" class="close position-absolute" style="top:7px; right:15px; z-index:22;font-size:2.1rem;" data-dismiss="modal">&times;</button>
      <embed id="pdfViewerEmbed" src="" type="application/pdf" class="pdf-embed"/>
    </div>
  </div>
</div>

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
function openImageViewer(idx) {
  if (imageList.length === 0) return;
  imageIndex = idx;
  $('#imageViewerImg').attr('src', imageList[imageIndex]);
  $('#attachmentsModal').modal('hide');
  $('#imageViewerModal').modal('show');
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
function openPdfViewer(url) {
  $('#attachmentsModal').modal('hide');
  $('#pdfViewerEmbed').attr('src', url);
  $('#pdfViewerModal').modal('show');
}
</script>
</body>
</html>
