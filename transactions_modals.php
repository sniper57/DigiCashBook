<?php
$cats = $conn->prepare("SELECT id, name FROM categories WHERE user_id=?");
$cats->bind_param("i", $_SESSION['user_id']);
$cats->execute();
$categories = $cats->get_result();

date_default_timezone_set('Asia/Manila');
$datetoday = date('Y-m-d\TH:i');
?>

<!-- ADD TRANSACTION -->
<div class="modal fade" id="addTransactionModal" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="POST" enctype="multipart/form-data" action="transactions_ajax.php">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="book_id" value="<?= $_GET['book_id'] ?>">
      <div class="modal-header modal-custom-header"><h5>Add Transaction</h5></div>
      <div class="modal-body">

        <input type="hidden" name="type" id="add_type" class="form-control mb-2">
        <input name="type_display" class="form-control mb-2" readonly>

        <p>Amount<span class="text-danger">*</span> :</p>
        <input name="amount" class="form-control mb-2" type="number" step="0.01" placeholder="Amount" required>

        <p>Payment Mode<span class="text-danger">*</span> :</p>
        <select name="mode_id" class="form-control mb-2" required>
          <option value="">-- Select Mode --</option>
          <?php
          $modes = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id = ?");
          $modes->bind_param("i", $_SESSION['user_id']);
          $modes->execute();
          $mode_res = $modes->get_result();
          while ($m = $mode_res->fetch_assoc()):
          ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endwhile; ?>
        </select>

        <p>Category:</p>
        <select name="category_id" class="form-control mb-2">
          <option value="">-- Select Category --</option>
          <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endwhile; ?>
        </select>

        <p>Transaction Date<span class="text-danger">*</span> :</p>
        <input name="date" class="form-control mb-2" type="datetime-local" value='<?= $datetoday; ?>' required>

        <p>Description<span class="text-danger">*</span> :</p>
        <textarea name="description" class="form-control mb-2" placeholder="Description" required></textarea>

        <p>Attach File:</p>
        <input type="file" name="attachments[]" class="form-control-file" id="add_attachments" multiple accept=".jpg,.jpeg,.png,.gif,.pdf">
        <div id="add_preview_area" class="mb-2 d-flex flex-wrap"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button class="btn btn-custom-color">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT TRANSACTION -->
<div class="modal fade" id="editTransactionModal" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="POST" enctype="multipart/form-data" action="transactions_ajax.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="book_id" value="<?= $_GET['book_id'] ?>">
      <input type="hidden" name="transaction_id" id="edit_transaction_id">
      <input type="hidden" name="delete_attachments" id="delete_attachments">
      <div class="modal-header modal-custom-header"><h5>Edit Transaction</h5></div>
      <div class="modal-body">
        <p>Type<span class="text-danger">*</span> :</p>
        <select name="type" class="form-control mb-2" id="edit_type" required>
          <option value="">[Select]</option>
          <option value="cashin">Cash-In</option>
          <option value="cashout">Cash-Out</option>
        </select>

        <p>Amount<span class="text-danger">*</span> :</p>
        <input name="amount" class="form-control mb-2" type="number" id="edit_amount" step="0.01" required>

        <p>Payment Mode<span class="text-danger">*</span> :</p>
        <select name="mode_id" id="edit_payment_mode" class="form-control mb-2" required>
          <option value="">-- Select Mode --</option>
          <?php
          $modes = $conn->prepare("SELECT id, name FROM transaction_modes WHERE user_id = ?");
          $modes->bind_param("i", $_SESSION['user_id']);
          $modes->execute();
          $mode_res = $modes->get_result();
          while ($m = $mode_res->fetch_assoc()):
          ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endwhile; ?>
        </select>

        <p>Category :</p>
        <select name="category_id" class="form-control mb-2" id="edit_category">
          <option value="">-- Select Category --</option>
          <?php mysqli_data_seek($categories, 0); while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endwhile; ?>
        </select>

        <p>Transaction Date<span class="text-danger">*</span> :</p>
        <input name="date" class="form-control mb-2" type="datetime-local" id="edit_date" required>

        <p>Description<span class="text-danger">*</span> :</p>
        <textarea name="description" class="form-control mb-2" id="edit_description" required></textarea>

        <p>Existing Attachments:</p>
        <div id="attachment_list" class="mb-3 d-flex flex-wrap gap-2"></div>

        <p>Add New Attachments:</p>
        <input type="file" name="attachments[]" class="form-control-file" id="edit_attachments" multiple accept=".jpg,.jpeg,.png,.gif,.pdf">
        <div id="edit_preview_area" class="mb-2 d-flex flex-wrap"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button class="btn btn-success">Update</button>
      </div>
    </form>
  </div>
</div>
