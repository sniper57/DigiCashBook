<?php
require_once 'db_connect.php';
session_start();

$action = $_REQUEST['action'] ?? '';
$book_id = intval($_REQUEST['book_id'] ?? 0);

// Only allow book owner to modify shares
if ($book_id) {
    $check = $conn->prepare("SELECT * FROM book_users WHERE book_id=? AND user_id=? AND role_level='owner'");
    $check->bind_param("ii", $book_id, $_SESSION['user_id']);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        echo json_encode(['success'=>false,'message'=>'Access denied']); exit;
    }
}

if ($action=='list') {
    $rows = [];
    $stmt = $conn->prepare("SELECT bu.*, u.name, u.email FROM book_users bu LEFT JOIN users u ON bu.user_id=u.id WHERE bu.book_id=? ORDER BY bu.role_level='owner' DESC, bu.id ASC");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['data'=>$rows]); exit;
}

if ($action=='invite') {
    $email = trim($_POST['email']);
    $role = $_POST['role_level'];
    if(!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['editor','viewer'])){
        echo json_encode(['success'=>false,'message'=>'Invalid input']); exit;
    }
    // check if user exists
    $ust = $conn->prepare("SELECT id FROM users WHERE email=?");
    $ust->bind_param("s", $email); $ust->execute();
    $user = $ust->get_result()->fetch_assoc();

    if ($user) {
        // Add if not already
        $ins = $conn->prepare("INSERT IGNORE INTO book_users (book_id, user_id, role_level, status) VALUES (?, ?, ?, 'active')");
        $ins->bind_param("iis", $book_id, $user['id'], $role); $ins->execute();
        echo json_encode(['success'=>true,'message'=>'User added as '.$role]); exit;
    } else {
        // Add pending and send invite
        $ins = $conn->prepare("INSERT IGNORE INTO book_users (book_id, invited_email, role_level, status) VALUES (?, ?, ?, 'pending')");
        $ins->bind_param("iss", $book_id, $email, $role); $ins->execute();
        // Send invite email
        require_once __DIR__.'/vendor/autoload.php';
        $bookNameQ = $conn->prepare("SELECT name FROM books WHERE id=?"); $bookNameQ->bind_param("i",$book_id); $bookNameQ->execute();
        $bookName = $bookNameQ->get_result()->fetch_assoc()['name'] ?? 'DigiCashBook';
        $inviteLink = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/register.php?invite=".$email;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'sg2plzcpnl506710.prod.sin2.secureserver.net';    // Must be GoDaddy host name
			$mail->SMTPAuth = true;
			$mail->Username = "notifications@theweddingdaystory.com"; /*Substitute with your real email*/
			$mail->Password = "xxxxxxxx"; /*Substitute with your real password*/
			$mail->SMTPSecure = 'tls';   // ssl will no longer work on GoDaddy CPanel SMTP
			$mail->Port = 587;    // Must use port 587 with TLS

            $mail->setFrom('no-reply@digicashbook.com', 'DigiCashBook');
            $mail->addAddress($email);
            $mail->Subject = "You've been invited to a DigiCashBook";
            $mail->Body = "You have been invited to join the book \"$bookName\" on DigiCashBook.\n\n"
                        . "Click here to join and register: $inviteLink";
            $mail->send();
        } catch (Exception $e) {
            // For local dev, you may want to log this error
        }
        echo json_encode(['success'=>true,'message'=>'Invitation sent to '.$email]); exit;
    }
}

if ($action=='changerole') {
    $id = intval($_POST['id']);
    $role = $_POST['role_level'];
    if (!in_array($role,['viewer','editor'])) {
        echo json_encode(['success'=>false]); exit;
    }
    $upd = $conn->prepare("UPDATE book_users SET role_level=? WHERE id=? AND role_level!='owner'");
    $upd->bind_param("si", $role, $id); $upd->execute();
    echo json_encode(['success'=>true]); exit;
}

if ($action=='revoke') {
    $id = intval($_POST['id']);
    //$upd = $conn->prepare("UPDATE book_users SET status='revoked' WHERE id=? AND role_level!='owner'");
    $upd = $conn->prepare("DELETE FROM book_users WHERE id=? AND role_level!='owner'");
    $upd->bind_param("i", $id); $upd->execute();
    echo json_encode(['success'=>true]); exit;
}

echo json_encode(['success'=>false,'message'=>'Invalid action']);
