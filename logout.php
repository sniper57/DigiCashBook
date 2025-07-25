<?php
// logout.php
session_start();
session_unset();
session_destroy();

// Redirect to login
header("Location: index.php");
exit;
?>
