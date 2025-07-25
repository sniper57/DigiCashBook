<?php
// register.php
session_start();
require_once 'db_connect.php'; // assumes a db connection file

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$name || !$email || !$password) {
        $msg = "All fields are required.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $role = 'user'; // default role

            $insert = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $name, $email, $password_hash, $role);

            if ($insert->execute()) {
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;

                //<---- Audit Logging
                $_Audit_Action = 'SAVE'; //-- SAVE/UPDATE/DELETE
                $_Audit_ModuleName = 'USERS'; //-- Transaction/User
                $_Audit_PrimaryKey = $_SESSION['user_id'];//-- PrimaryKey ID of the Data
                $_Audit_Comment = 'SAVE ['. $name .']';// -- SAVE/UPDATE/DELETE - Description of Data
                log_admin_action($_SESSION['user_id'], $_Audit_Action . ' ' .  $_Audit_ModuleName, $_Audit_PrimaryKey, $_Audit_Comment);
                //---->

                header("Location: dashboard.php");
                exit;
            } else {
                $msg = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register | DigiCashBook</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet" />
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <style>
        body { background: #fafbfc; min-height: 100vh; font-family: 'Open Sans', Arial, sans-serif;}
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; box-shadow: 0 3px 22px #0002; border-radius: 18px; padding: 0; max-width: 940px; width: 100%; display: flex; overflow: hidden; }
        .login-form-side { flex: 1 1 54%; padding: 52px 48px 48px 48px; display: flex; flex-direction: column; justify-content: center; }
        .login-illustration-side { flex: 1 1 56%; background: #fafbfc; display: flex; align-items: center; justify-content: center; padding: 32px 30px 32px 8px; }
        .login-title { font-family: 'Montserrat', Arial, sans-serif; font-weight: 700; font-size: 2.1rem; color: #222; letter-spacing: -1px; }
        .login-title b { color: #202134; }
        .login-desc { font-size: 1.04rem; color: #888; margin-bottom: 24px;}
        .form-control { border: none; border-bottom: 1px solid #ccc; border-radius: 0; background: transparent; box-shadow: none !important; padding-left: 0; }
        .form-control:focus { border-bottom: 2px solid #34db96; background: transparent; }
        .form-group label { font-size: 0.98rem; color: #888; font-weight: 500; margin-bottom: 5px; }
        .login-btn { background: #34db96; color: #fff; font-size: 1.19rem; border-radius: 7px; border: none; width: 100%; padding: 13px 0; font-weight: 600; margin-top: 10px; margin-bottom: 3px; transition: background 0.15s;}
        .login-btn:hover, .login-btn:focus { background: #1ed88c;}
        .login-links { margin-top: 18px; font-size: 0.96rem;}
        .login-links a { color: #6e6e6e; text-decoration: underline; }
        .illustration-img { width: 94%; max-width: 400px; height: auto; display: block; margin: auto; }
        @media (max-width: 991px) { .login-card { flex-direction: column-reverse; min-height: unset; }
            .login-illustration-side, .login-form-side { flex: unset; width: 100%; padding: 30px 25px;}
        }
        @media (max-width: 600px) {
            .login-form-side, .login-illustration-side { padding: 18px 6vw; }
            .login-title { font-size: 1.39rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Left: Registration Form -->
            <div class="login-form-side">
                <div class="mb-2">
                    <div class="login-title">
                        Create your <b>DigiCashBook</b> account
                    </div>
                    <div class="login-desc">Track your money smarter. Register for free!</div>
                </div>
                <?php if($msg): ?>
                <div class="alert alert-danger" style="font-size:1rem;">
                    <?=$msg?>
                </div>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="form-group mb-3">
                        <strong>Name</strong>
                        <input type="text" name="name" class="form-control" placeholder="Enter your Full Name" required autofocus />
                    </div>
                    <div class="form-group mb-3">
                        <strong>Email</strong>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" required />
                    </div>
                    <div class="form-group mb-3">
                        <strong>Password</strong>
                        <input type="password" name="password" class="form-control" placeholder="********" required />
                    </div>
                    <button class="login-btn" type="submit">Register</button>
                </form>
                <div class="login-links mt-2">
                    Already have an account? <a href="index.php">Log in</a>
                </div>
            </div>
            <!-- Right: Illustration -->
            <div class="login-illustration-side d-none d-md-flex">
                <img class="illustration-img" src="images/digicashbook_signin.png" alt="DigiCashBook SignIn" />
            </div>
        </div>
    </div>
    <div class="d-md-none text-center" style="margin-top:18px;margin-bottom:16px;">
        <img class="illustration-img" src="images/digicashbook_signin.png" alt="DigiCashBook SignIn" />
    </div>
</body>
</html>
