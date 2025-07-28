<?php
require_once 'db_connect.php';
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 60*60); // 1 hour
        $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?");
        $stmt->bind_param("ssi", $token, $expires, $user['id']);
        $stmt->execute();

        // Reset link
        $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";

        // PHPMailer config  
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
			$mail->Host = 'sg2plzcpnl506710.prod.sin2.secureserver.net';    // Must be GoDaddy host name
			$mail->SMTPAuth = true;
			$mail->Username = "notifications@theweddingdaystory.com"; /*Substitute with your real email*/
			$mail->Password = "xxxxxxxx"; /*Substitute with your real password*/
			$mail->SMTPSecure = 'tls';   // ssl will no longer work on GoDaddy CPanel SMTP
			$mail->Port = 587;    // Must use port 587 with TLS

			$mail->setFrom('no-reply@digicashbook.com', 'DigiCashBook');
            $mail->addAddress($email, $user['name']);

            $mail->Subject = 'DigiCashBook Password Reset';
            $mail->Body = "Hi {$user['name']},\n\nClick below to reset your password:\n$link\n\nThis link expires in 1 hour.";
            $mail->send();

            $msg = "Password reset link sent to your email.";
        }
        catch (Exception $e) {
            $msg = "Failed to send email. Contact support.<br><small>" . $mail->ErrorInfo . "</small>";
        }
    } else {
        $msg = "If an account exists, a reset link will be sent. Please check your email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password | DigiCashBook</title>
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
            <!-- Left: Forgot Password Form -->
            <div class="login-form-side">
                <div class="mb-2">
                    <div class="login-title">
                        Forgot your <b>DigiCashBook</b> password?
                    </div>
                    <div class="login-desc">Enter your email and we'll send you password reset instructions.</div>
                </div>
                <?php if($msg): ?>
                <div class="alert alert-info" style="font-size:1rem;">
                    <?=$msg?>
                </div>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="form-group mb-3">
                        <strong>Email</strong>
                        <input type="email" name="email" class="form-control" placeholder="Your Email Address" required autofocus />
                    </div>
                    <button class="login-btn" type="submit">Send Reset Link</button>
                </form>
                <div class="login-links mt-2">
                    <a href="index.php">Back to Login</a>
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
