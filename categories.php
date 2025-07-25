<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name']);
    $type = $_POST['type'] ?? 'cashin';

    if ($action === 'add_category') {
        $stmt = $conn->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $name, $type);
        $stmt->execute();
    }

    if ($action === 'edit_category') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssii", $name, $type, $id, $user_id);
        $stmt->execute();
    }

    if ($action === 'delete_category') {
        $id = intval($_POST['id']);

        $check = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE category_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            $_SESSION['error'] = "Cannot delete category: It is used in {$row['count']} transaction(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $_SESSION['success'] = "Category deleted successfully.";
        }

        header("Location: categories.php");
        exit;
    }
}

// Fetch categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Categories</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container my-4">
  <h3>Manage Categories</h3>

  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>

  <div class="mb-3">
    <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">+ Add Category</button>
  </div>

  <table class="table table-bordered">
    <thead class="thead-dark">
      <tr>
        <th>Name</th>
        <th>Type</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td><?= htmlspecialchars($cat['name']) ?></td>
          <td><?= ucfirst($cat['type']) ?></td>
          <td>
            <button class="btn btn-sm btn-warning btn-edit-cat"
                    data-id="<?= $cat['id'] ?>"
                    data-name="<?= htmlspecialchars($cat['name']) ?>"
                    data-type="<?= $cat['type'] ?>">Edit</button>
            <button class="btn btn-sm btn-danger btn-delete-cat"
                    data-id="<?= $cat['id'] ?>"
                    data-name="<?= htmlspecialchars($cat['name']) ?>">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ADD Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="add_category">
      <div class="modal-header">
        <h5 class="modal-title">Add Category</h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Name</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" class="form-control">
            <option value="cashin">Cash In</option>
            <option value="cashout">Cash Out</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_category">
      <input type="hidden" name="id" id="edit_cat_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Category</h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Name</label>
          <input name="name" id="edit_cat_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" id="edit_cat_type" class="form-control">
            <option value="cashin">Cash In</option>
            <option value="cashout">Cash Out</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="delete_category">
      <input type="hidden" name="id" id="delete_cat_id">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Delete Category</h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-danger">Are you sure you want to delete <strong id="delete_cat_name"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
  $('.btn-edit-cat').click(function () {
    $('#edit_cat_id').val($(this).data('id'));
    $('#edit_cat_name').val($(this).data('name'));
    $('#edit_cat_type').val($(this).data('type'));
    $('#editCategoryModal').modal('show');
  });

  $('.btn-delete-cat').click(function () {
    $('#delete_cat_id').val($(this).data('id'));
    $('#delete_cat_name').text($(this).data('name'));
    $('#deleteCategoryModal').modal('show');
  });
</script>
</body>
</html>
