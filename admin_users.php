<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

// Only allow admin access
if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit;
}

// Fetch all users
$result = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin - User Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h4>🛠 Admin: User Management</h4>

  <table class="table table-bordered table-sm">
    <thead class="thead-light">
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Created</th>
        <th width="200">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
      <tr>
        <td><?= htmlspecialchars($user['name']) ?></td>
        <td><?= htmlspecialchars($user['email']) ?></td>
        <td>
          <form method="POST" action="admin_user_action.php" class="form-inline">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <select name="new_role" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
              <?php foreach (['admin', 'manager', 'user'] as $role): ?>
                <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                  <?= ucfirst($role) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
        <td>
          <button class="btn btn-sm btn-warning btn-reset" 
                  data-id="<?= $user['id'] ?>" 
                  data-name="<?= htmlspecialchars($user['name']) ?>">Reset Password</button>
          <button class="btn btn-sm btn-danger btn-delete" 
                  data-id="<?= $user['id'] ?>" 
                  data-name="<?= htmlspecialchars($user['name']) ?>">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog" role="document">
    <form method="POST" action="admin_user_action.php" class="modal-content">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset_user_id">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Set a new password for <strong id="reset_user_name"></strong>:</p>
        <div class="form-group">
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="New password">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success">Reset</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog" role="document">
    <form method="POST" action="admin_user_action.php" class="modal-content">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="delete_user_id">
      <div class="modal-header">
        <h5 class="modal-title">Confirm User Deletion</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-danger">⚠ Are you sure you want to permanently delete user <strong id="delete_user_name"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger">Delete User</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $('.btn-reset').click(function () {
    $('#reset_user_id').val($(this).data('id'));
    $('#reset_user_name').text($(this).data('name'));
    $('#resetModal').modal('show');
  });

  $('.btn-delete').click(function () {
    $('#delete_user_id').val($(this).data('id'));
    $('#delete_user_name').text($(this).data('name'));
    $('#deleteModal').modal('show');
  });
</script>
</body>
</html>
