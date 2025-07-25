<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
include 'navbar.php';

// Restrict to admin only!
if ($_SESSION['user_role'] !== 'admin') {
    die('<div class="alert alert-danger">Access denied.</div>');
}

// Fetch all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .table th, .table td { vertical-align: middle; }
        .modal .form-label { font-weight: 500; }
        .user-role-badge.admin { background: #1867c0; color: #fff; }
        .user-role-badge.manager { background: #e67e22; color: #fff; }
        .user-role-badge.user { background: #52c41a; color: #fff; }
        @media (max-width: 600px) { .table-responsive { font-size: 15px; } }
    </style>
</head>
<body>
<div class="container pt-4 mb-5">
    <h3 class="mb-3">User Management</h3>
    <button class="btn btn-success mb-3" data-toggle="modal" data-target="#addUserModal"><i class="fa fa-plus"></i> Add User</button>

    <div class="table-responsive">
    <table class="table table-bordered bg-white">
        <thead class="thead-light">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created At</th>
                <th style="width:120px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge user-role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                </td>
                <td><?= htmlspecialchars(date("M d, Y", strtotime($u['created_at']))) ?></td>
                <td>
                    <button class="btn btn-warning btn-sm btn-edit" data-id="<?= $u['id'] ?>"><i class="fa fa-pen"></i></button>
                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $u['id'] ?>"><i class="fa fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="users_ajax.php" autocomplete="off">
      <input type="hidden" name="action" value="add">
      <div class="modal-header"><h5>Add User</h5></div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Name*</label>
          <input type="text" class="form-control" name="name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email*</label>
          <input type="email" class="form-control" name="email" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password*</label>
          <input type="password" class="form-control" name="password" required minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Role*</label>
          <select class="form-control" name="role" required>
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-success">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="users_ajax.php" autocomplete="off">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header"><h5>Edit User</h5></div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Name*</label>
          <input type="text" class="form-control" name="name" id="edit_name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email*</label>
          <input type="email" class="form-control" name="email" id="edit_email" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
          <input type="password" class="form-control" name="password" id="edit_password" minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Role*</label>
          <select class="form-control" name="role" id="edit_role" required>
            <option value="user">User</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
$(function(){
    // Populate Edit Modal
    $('.btn-edit').click(function(){
        var id = $(this).data('id');
        $.get('users_ajax.php', { action:'get', id:id }, function(res){
            if(res.success){
                $('#edit_id').val(res.data.id);
                $('#edit_name').val(res.data.name);
                $('#edit_email').val(res.data.email);
                $('#edit_role').val(res.data.role);
                $('#edit_password').val('');
                $('#editUserModal').modal('show');
            } else {
                alert(res.message || 'User not found.');
            }
        }, 'json');
    });

    // Delete User
    $('.btn-delete').click(function(){
        if(!confirm('Delete this user? This cannot be undone.')) return;
        var id = $(this).data('id');
        $.post('users_ajax.php', { action:'delete', id:id }, function(res){
            if (res.success) window.location.href = 'users.php?action=deleted';
            else alert(res.message || 'Failed to delete user.');
        }, 'json');
    });
	
	// Show toast for ?action
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    if (action === 'added') showToast('Record added successfully!');
    else if (action === 'updated') showToast('Record updated successfully!');
    else if (action === 'deleted') showToast('Record deleted successfully!');
});

// Toast Notification
function showToast(message, type = 'success') {
    const id = 'toast-' + Math.random().toString(36).substring(7);
    const bg = type === 'error' ? 'bg-danger' : 'bg-success';
    const toast = `
    <div id="${id}" class="toast ${bg} text-white" role="alert" data-delay="5000" style="min-width: 200px;">
        <div class="toast-body">${message}</div>
    </div>`;
    $('#toastContainer').append(toast);
    const $toast = $('#' + id);
    $toast.toast('show');
    $toast.on('hidden.bs.toast', () => $toast.remove());
}

</script>
</body>
</html>
