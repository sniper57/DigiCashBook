<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT name, email, default_book_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// Get books (owned/shared)
$stmt = $conn->prepare("SELECT b.id, b.name FROM books b
                        JOIN book_users bu ON b.id = bu.book_id
                        WHERE bu.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>My Profile</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h4>👤 My Profile</h4>

  <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
    <div class="alert alert-success">✅ Profile updated successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['password_changed']) && $_GET['password_changed'] == 1): ?>
    <div class="alert alert-success">✅ Your password has been updated successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error']): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <form action="profile_update.php" method="POST" class="mt-3">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control" required>
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
    </div>

    <div class="form-group">
      <label>Default Book for Dashboard</label>
      <select name="default_book_id" class="form-control">
        <option value="">-- None Selected --</option>
        <?php foreach ($books as $book): ?>
          <option value="<?= $book['id'] ?>" <?= ($user['default_book_id'] == $book['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($book['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn btn-primary">Update Profile</button>
    <button type="button" class="btn btn-link" data-toggle="modal" data-target="#changePassModal">Change Password</button>
    <button type="button" class="btn btn-danger float-right" data-toggle="modal" data-target="#deleteAccountModal">
      Delete My Account
    </button>
  </form>
</div>

<!-- Password Change Modal -->
<div class="modal fade" id="changePassModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="POST" action="password_change.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="POST" action="account_delete.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Account Deletion</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-danger">⚠ This action is irreversible. All your books and transactions will be lost.</p>
        <div class="form-group">
          <label>Confirm your password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-danger">Yes, Delete My Account</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
