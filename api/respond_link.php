<?php
// api/respond_link.php
require_once '../inc/config.php';
require_once '../inc/auth.php';

session_start();
require_login(false);
$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['response'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF');
    }
    $request_id = (int)$_POST['request_id'];
    $response = $_POST['response'];
    
    $stmtReq = $pdo->prepare("SELECT * FROM couple_link_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'");
    $stmtReq->execute([$request_id, $user_id]);
    if ($req = $stmtReq->fetch()) {
        $stmtUpd = $pdo->prepare("UPDATE couple_link_requests SET status = ? WHERE id = ?");
        $stmtUpd->execute([$response, $request_id]);
        if ($response === 'accepted') {
            $stmtL = $pdo->prepare("INSERT INTO couple_relationships (user1_id, user2_id) VALUES (?, ?)");
            $stmtL->execute([$req['sender_id'], $req['receiver_id']]);
            $stmtUpdType = $pdo->prepare("UPDATE users SET account_type = 'couple' WHERE id IN (?, ?)");
            $stmtUpdType->execute([$req['sender_id'], $req['receiver_id']]);
            $receiver_name = $_SESSION['user_full_name'] ?? 'Partner';
            $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'general')");
            $stmtA->execute([$req['sender_id'], "$receiver_name accepted your couples link request!"]);
        } else {
            $receiver_name = $_SESSION['user_full_name'] ?? 'Partner';
            $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'general')");
            $stmtA->execute([$req['sender_id'], "$receiver_name declined your couples link request."]);
        }
        if (isset($_POST['alert_id'])) {
            $stmtAlert = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
            $stmtAlert->execute([$_POST['alert_id']]);
        }
    }
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../settings.php'));
exit;