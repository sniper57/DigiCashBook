<?php
require_once 'db_connect.php';
include 'navbar.php';
session_start();
$book_id = intval($_GET['book_id'] ?? 0);

// Check permission: Only owner can manage shares
$check = $conn->prepare("SELECT * FROM book_users WHERE book_id=? AND user_id=? AND role_level='owner'");
$check->bind_param("ii", $book_id, $_SESSION['user_id']);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    die('Access denied.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Share Book | DigiCashBook</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css"/>
    <style>
        body { background: #fafbfc; }
        .container { max-width: 660px; margin-top: 48px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
<div class="container">
    <h3 class="mb-3">Share Book</h3>
    <div class="card mb-4">
        <div class="card-body">
            <form id="inviteForm" class="form-inline">
                <input type="hidden" name="book_id" value="<?=$book_id?>">
                <label class="sr-only" for="email">Email</label>
                <input type="email" name="email" required class="form-control mb-2 mr-sm-2" placeholder="Invite by email">
                <select name="role_level" class="form-control mb-2 mr-sm-2" required>
                    <option value="viewer">Viewer</option>
                    <option value="editor">Editor</option>
                </select>
                <button type="submit" class="btn btn-success mb-2">Invite</button>
            </form>
            <div id="inviteMsg" class="mt-1"></div>
        </div>
    </div>

    <h5>Shared Users</h5>
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
        <!-- Fetched by JS -->
        </tbody>
    </table>
    <a href="books.php" class="btn btn-outline-primary mt-3">Back to Books</a>
</div>

<script>
function loadShares() {
    $.get('share_book_ajax.php', {action:'list', book_id:<?=$book_id?>}, function(res){
        let html = '';
        (res.data || []).forEach(row => {
            html += `<tr>
                <td>${row.name ? row.name + " ("+row.email+")" : row.invited_email}</td>
                <td>${row.role_level}</td>
                <td>${row.status}</td>
                <td>
                  ${row.role_level!=='owner' && row.status=='active' ?
                    `<button class="btn btn-warning btn-sm" onclick="changeRole(${row.id},'${row.role_level=='editor'?'viewer':'editor'}')">Set as ${row.role_level=='editor'?'Viewer':'Editor'}</button>
                     <button class="btn btn-danger btn-sm" onclick="revokeShare(${row.id})">Revoke</button>` : ''}
                  ${row.status=='pending' ? `<button class="btn btn-danger btn-sm" onclick="revokeShare(${row.id})">Cancel</button>` : ''}
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
        if(res.success) loadShares();
    },'json');
});
function changeRole(id, role) {
    $.post('share_book_ajax.php', {action:'changerole', id, role_level:role}, function(res){
        loadShares();
    },'json');
}
function revokeShare(id) {
    if(confirm('Are you sure to revoke/cancel sharing for this user?'))
        $.post('share_book_ajax.php', {action:'revoke', id}, function(res){ loadShares(); },'json');
}
$(function(){ loadShares(); });
</script>
</body>
</html>
