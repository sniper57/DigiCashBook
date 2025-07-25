<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit;
}

// Fetch list of admins for filter dropdown
$adminList = $conn->query("SELECT id, name FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);

// Filters
$filter_admin = $_GET['admin_id'] ?? '';
$filter_type = $_GET['action_type'] ?? '';
$date_start = $_GET['start'] ?? '';
$date_end = $_GET['end'] ?? '';

$where = [];
$params = [];
$types = '';

if ($filter_admin) {
  $where[] = "l.admin_id = ?";
  $params[] = $filter_admin;
  $types .= 'i';
}
if ($filter_type) {
  $where[] = "l.action_type = ?";
  $params[] = $filter_type;
  $types .= 's';
}
if ($date_start && $date_end) {
  $where[] = "DATE(l.created_at) BETWEEN ? AND ?";
  $params[] = $date_start;
  $params[] = $date_end;
  $types .= 'ss';
}

$sql = "SELECT l.*, a.name as admin_name, u.name as target_name
        FROM admin_logs l
        LEFT JOIN users a ON l.admin_id = a.id
        LEFT JOIN users u ON l.target_user_id = u.id";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Logs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h4>📋 Admin Audit Trail</h4>

  <form class="form-inline mb-3" method="GET">
    <select name="admin_id" class="form-control mr-2">
      <option value="">All Admins</option>
      <?php foreach ($adminList as $admin): ?>
        <option value="<?= $admin['id'] ?>" <?= ($filter_admin == $admin['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($admin['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="action_type" class="form-control mr-2">
      <option value="">All Actions</option>
      <option value="change_role" <?= $filter_type == 'change_role' ? 'selected' : '' ?>>Role Change</option>
      <option value="reset_password" <?= $filter_type == 'reset_password' ? 'selected' : '' ?>>Password Reset</option>
      <option value="delete_user" <?= $filter_type == 'delete_user' ? 'selected' : '' ?>>User Deletion</option>
    </select>

    <input type="date" name="start" class="form-control mr-2" value="<?= htmlspecialchars($date_start) ?>">
    <input type="date" name="end" class="form-control mr-2" value="<?= htmlspecialchars($date_end) ?>">

    <button class="btn btn-secondary">Filter</button>
  </form>

  <table class="table table-bordered table-sm">
    <thead class="thead-light">
      <tr>
        <th>Date</th>
        <th>Admin</th>
        <th>Action</th>
        <th>Target</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
          <td><?= htmlspecialchars($log['admin_name']) ?></td>
          <td><?= $log['action_type'] ?></td>
          <td><?= htmlspecialchars($log['target_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($log['details']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
