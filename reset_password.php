<?php
// reset_password.php
require_once 'inc/config.php';
require_once 'inc/functions.php';

$lang = get_language_strings();
$error = '';
$success = '';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("No token provided.");
}

// Verify token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    die("Invalid or expired reset link.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token";
    } else {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmtUpd->execute([$hash, $reset['email']]);
            $stmtUse = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmtUse->execute([$reset['id']]);
            $success = "Password changed successfully. <a href='login.php'>Login now</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --primary: #0d631b; --primary-fixed: #2e7d32; --surface-container-low: #f0f0f0; }
        .card { background: white; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); padding: 2rem; }
        .input-field { background: var(--surface-container-low); border-radius: 0.75rem; padding: 0.75rem 1rem; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-fixed)); color: white; padding: 0.75rem; border-radius: 0.75rem; font-weight: 600; width: 100%; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full card">
        <h1 class="text-2xl font-bold text-center mb-6">Reset Your Password</h1>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-4"><input type="password" name="password" class="input-field" placeholder="New password" required></div>
                <div class="mb-4"><input type="password" name="confirm_password" class="input-field" placeholder="Confirm new password" required></div>
                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>