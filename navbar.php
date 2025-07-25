<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_admin = $_SESSION['user_role'] ?? '';
$username = $_SESSION['user_name'] ?? 'User';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="fa fa-mobile-alt mr-2" style="font-size: 1.5em; color: #2874f0;"></i>
            <span class="font-weight-bold" style="font-size: 1.17em;">DigiCashBook</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarDigiCashBook" aria-controls="navbarDigiCashBook" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarDigiCashBook">
            <ul class="navbar-nav mr-auto">
                <!-- Reports -->
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Home</a>
                </li>
                <!-- Books Group -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="booksDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        My Books
                    </a>
                    <div class="dropdown-menu" aria-labelledby="booksDropdown">
                        <a class="dropdown-item" href="books.php">My Transactions</a>
                        <a class="dropdown-item" href="transactions_bulk.php">Import Transactions</a>
                       
                    </div>
                </li>
                <!-- Reports -->
                <li class="nav-item">
                    <a class="nav-link" href="transactions_export_filter.php">Reports</a>
                </li>
                <!-- Admin group -->
                <?php if($is_admin == "admin"): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Administrator
                    </a>
                    <div class="dropdown-menu" aria-labelledby="adminDropdown">
                        <a class="dropdown-item" href="users.php">Users</a>
                        <a class="dropdown-item" href="categories.php">Categories</a>
                        <a class="dropdown-item" href="modes.php">Payment Mode</a>
                        <a class="dropdown-item" href="admin_logs.php">Logs</a>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
            <!-- Right side: User profile or logout -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="profile.php" style="opacity:.8;">
                        <i class="fa fa-user-circle">&nbsp;</i><?=htmlspecialchars($username)?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div aria-live="polite" aria-atomic="true" style="position: fixed; top: 5rem; right: 1rem; z-index: 1080;">
    <div id="toastContainer"></div>
</div>

<!-- Sticky Fixed Footer (always at window bottom) -->
<footer class="footer bg-white border-top shadow-sm fixed-bottom">
    <div class="container text-center" style="font-size: 1.01em; color: #999;">
        DigiCashBook &copy; 2025 - Magis Technologies
    </div>
</footer>

<!-- Required JS (Bootstrap 4 + jQuery + Popper) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
