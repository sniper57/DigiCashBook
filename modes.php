<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['add_name'])) {
    $stmt = $conn->prepare("INSERT INTO transaction_modes (user_id, name) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['user_id'], $_POST['add_name']);
    $stmt->execute();
  }

  if (isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("UPDATE transaction_modes SET name=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sii", $_POST['edit_name'], $_POST['edit_id'], $_SESSION['user_id']);
    $stmt->execute();
  }

  if (isset($_POST['delete_id'])) {
    // Prevent delete if mode is in use
    $check = $conn->prepare("SELECT id FROM transactions WHERE mode_id = ?");
    $check->bind_param("i", $_POST['delete_id']);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
      $stmt = $conn->prepare("DELETE FROM transaction_modes WHERE id = ? AND user_id = ?");
      $stmt->bind_param("ii", $_POST['delete_id'], $_SESSION['user_id']);
      $stmt->execute();
    }
  }

  header("Location: modes.php");
  exit;
}

// Fetch all modes
$stmt = $conn->prepare("SELECT * FROM transaction_modes WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$modes = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Transaction Modes</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h4>Transaction Modes</h4>

  <form method="POST" class="form-inline mb-3">
    <input type="text" name="add_name" class="form-control mr-2" placeholder="New mode" required>
    <button class="btn btn-primary">Add</button>
  </form>

  <table class="table table-bordered">
    <thead><tr><th>Mode</th><th>Actions</th></tr></thead>
    <tbody>
      <?php while ($m = $modes->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($m['name']) ?></td>
          <td>
            <form method="POST" class="form-inline">
              <input type="hidden" name="edit_id" value="<?= $m['id'] ?>">
              <input name="edit_name" value="<?= htmlspecialchars($m['name']) ?>" class="form-control mr-2">
              <button class="btn btn-success btn-sm mr-1">Save</button>
              <button name="delete_id" value="<?= $m['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this mode?')" <?= (in_array($m['id'], [1,2])) ? 'disabled' : '' ?>>Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
