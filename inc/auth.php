<?php
// inc/auth.php
require_once 'config.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login($check_onboard = true) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    
    if ($check_onboard) {
        global $pdo;
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT is_onboarded FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && !$user['is_onboarded'] && basename($_SERVER['PHP_SELF']) !== 'onboarding.php') {
            header('Location: onboarding.php');
            exit;
        }
    }
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_linked_user_id($pdo, $user_id) {
    // If personal account, return just an array with self
    // If couple, return an array with both user_ids
    $stmt = $pdo->prepare("SELECT account_type FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) return [$user_id];
    
    if ($user['account_type'] == 'couple') {
        $stmt = $pdo->prepare("
            SELECT user1_id, user2_id 
            FROM couple_relationships 
            WHERE user1_id = ? OR user2_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $rel = $stmt->fetch();
        if ($rel) {
            return [$rel['user1_id'], $rel['user2_id']];
        }
    }
    
    return [$user_id];
}
?>
