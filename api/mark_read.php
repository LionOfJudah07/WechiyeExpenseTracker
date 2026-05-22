<?php
// api/mark_read.php
require_once '../inc/config.php';
require_once '../inc/auth.php';

session_start();
require_login(false);
$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['alert_id'])) {
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['alert_id'], $user_id]);
    } elseif (isset($_POST['all'])) {
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../settings.php'));
exit;