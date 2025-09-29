<?php
require_once 'db_connect.php';
session_start();

$msg = '';
if ((isset($_POST['username'])) && (isset($_POST['password']))){
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
    
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR name=? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            // "Remember me" (optional: implement real cookie for persistent login)
            header('Location: dashboard.php');
            exit;
        } else {
            $msg = "Invalid credentials.";
        }
    }
}
else{
    $msg = "Enter Username/Password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign In | DigiCashBook</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Google Fonts (Montserrat for title, Open Sans for body) -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet" />
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" />
    <style>
        body {
            background: #fafbfc;
            min-height: 100vh;
            font-family: 'Open Sans', Arial, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            box-shadow: 0 3px 22px #0002;
            border-radius: 18px;
            padding: 0;
            max-width: 940px;
            width: 100%;
            display: flex;
            overflow: hidden;
        }
        .login-form-side {
            flex: 1 1 54%;
            padding: 52px 48px 48px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-illustration-side {
            flex: 1 1 56%;
            background: #fafbfc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 30px 32px 8px;
        }
        .login-title {
            font-family: 'Montserrat', Arial, sans-serif;
            font-weight: 700;
            font-size: 2.1rem;
            color: #222;
            letter-spacing: -1px;
        }
        .login-title b {
            color: #202134;
        }
        .login-desc {
            font-size: 1.04rem;
            color: #888;
            margin-bottom: 24px;
        }
        .form-control {
            border: none;
            border-bottom: 1px solid #ccc;
            border-radius: 0;
            background: transparent;
            box-shadow: none !important;
            padding-left: 0;
        }
        .form-control:focus {
            border-bottom: 2px solid #34db96;
            background: transparent;
        }
        .form-group label {
            font-size: 0.98rem;
            color: #888;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .custom-checkbox .custom-control-label::before {
            border-radius: 5px;
        }
        .login-btn {
            background: #34db96;
            color: #fff;
            font-size: 1.19rem;
            border-radius: 7px;
            border: none;
            width: 100%;
            padding: 13px 0;
            font-weight: 600;
            margin-top: 10px;
            margin-bottom: 3px;
            transition: background 0.15s;
        }
        .login-btn:hover, .login-btn:focus {
            background: #1ed88c;
        }
        .login-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: -7px;
            margin-bottom: 13px;
        }
        .login-links a {
            color: #6e6e6e;
            text-decoration: underline;
            font-size: 0.97rem;
        }
        .illustration-img {
            width: 94%;
            max-width: 400px;
            height: auto;
            display: block;
            margin: auto;
        }
        @media (max-width: 991px) {
            .login-card {
                flex-direction: column-reverse;
                min-height: unset;
            }
            .login-illustration-side, .login-form-side {
                flex: unset;
                width: 100%;
                padding: 30px 25px;
            }
        }
        @media (max-width: 600px) {
            .login-form-side, .login-illustration-side {
                padding: 18px 6vw;
            }
            .login-title {
                font-size: 1.39rem;
            }
        }
        /* Hide up/down arrow on input[type=number] (for password manager autofill) */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Left: Login Form -->
            <div class="login-form-side">
                <div class="mb-2">
                    <div class="login-title">
                        Sign In to <b>DigiCashBook</b>
                    </div>
                    <div class="login-desc">
                        Easily manage your finances with a simple digital cashbook.
                    </div>
                </div>
                <?php if($msg): ?>
                <div class="alert alert-danger" style="font-size:1rem;">
                    <?=$msg?>
                </div>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="form-group mb-3">
                        <strong>Username</strong>
                        <input type="text" name="username" class="form-control" placeholder="Email" required autofocus />
                    </div>
                    <div class="form-group mb-5">
                        <strong>Password</strong>
                        <input type="password" name="password" class="form-control" placeholder="*******" required autocomplete="off" />
                    </div>
                    <div class="login-links mb-2">
                        <div class="custom-control custom-checkbox" style="padding-left:1.7em;">
                            <input type="checkbox" class="custom-control-input" id="rememberme" name="remember" />
                            <label class="custom-control-label" for="rememberme" style="font-size:1.06em;">Remember me</label>
                        </div>
                    </div>
                    <button class="login-btn" type="submit">Log In</button>

                    <div class="form-group mt-2">
                        <a href="register.php">Create an account</a>
                        <br />
                        <a href="forgot_password.php">Forgot Password</a>
                    </div>
                </form>
            </div>
            <!-- Right: Illustration -->
            <div class="login-illustration-side d-none d-md-flex">
                <!-- Inline SVG: Replace this with your own SVG or PNG if you like -->
                <img class="illustration-img" src="images/digicashbook_signin.png" alt="DigiCashBook SignIn" />
            </div>
        </div>
    </div>
    <!-- For mobile, show illustration at top -->
    <div class="d-md-none text-center" style="margin-top:18px;margin-bottom:16px;">
        <img class="illustration-img" src="images/digicashbook_signin.png" alt="DigiCashBook SignIn" />
    </div>
</body>
</html>
