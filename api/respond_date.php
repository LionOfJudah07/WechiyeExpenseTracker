<?php
// api/respond_date.php
require_once '../inc/config.php';
require_once '../inc/auth.php';

session_start();
require_login(false);
$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_id'], $_POST['rsvp_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF');
    }
    $date_id = (int)$_POST['date_id'];
    $status = $_POST['rsvp_status'];
    $stmt = $pdo->prepare("UPDATE couple_dates SET rsvp_status = ? WHERE id = ? AND invitee_id = ?");
    $stmt->execute([$status, $date_id, $user_id]);
    if (isset($_POST['alert_id'])) {
        $stmtA = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
        $stmtA->execute([$_POST['alert_id']]);
    }
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard.php'));
exit;