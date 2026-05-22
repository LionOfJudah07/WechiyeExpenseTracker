<?php
// login.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

$lang = get_language_strings();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token";
    } else {
        // Forgot password request
        if (isset($_POST['action']) && $_POST['action'] === 'forgot') {
            $email = trim($_POST['email'] ?? '');
            if (empty($email)) {
                $error = "Please enter your email address.";
            } else {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    // Generate token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmtIns = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmtIns->execute([$email, $token, $expires]);
                    
                    // Send email (using mail() function - configure your server)
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/w2/reset_password.php?token=" . $token;
                    $subject = "Reset your password - " . $lang['app_name'];
                    $message = "Click the link to reset your password: " . $reset_link . "\n\nThis link expires in 1 hour.";
                    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
                    mail($email, $subject, $message, $headers);
                    
                    $success = "Password reset link sent to your email. Check your inbox.";
                } else {
                    $success = "If that email exists, a reset link has been sent."; // Security: don't reveal non-existence
                }
            }
        } else {
            // Normal login
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['lang'] = $_POST['lang'] ?? 'en';
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['login']; ?> - <?php echo $lang['app_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style type="text/tailwindcss">
        @layer base {
          :root {
            --primary: #0d631b;
            --secondary: #795900;
            --tertiary: #ab1118;
            --surface: #f9f9f9;
            --surface-container-lowest: #ffffff;
            --surface-container-low: #f0f0f0;
            --surface-container-highest: #e4e4e4;
            --on-surface: #1a1c1c;
            --outline-variant: rgba(26, 28, 28, 0.15);
            --primary-fixed: #2e7d32;
          }
          body { @apply bg-[var(--surface)] text-[var(--on-surface)] antialiased; font-family: 'Inter', sans-serif; }
          h1, h2, h3, h4, h5, h6 { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.025em; }
        }
        @layer components {
          .card { background-color: var(--surface-container-lowest); border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.04); padding: 2rem; border: 0; }
          .input-field { @apply block w-full rounded-xl border-0 py-3 px-4 text-sm focus:ring-0 transition-colors; background-color: var(--surface-container-low); color: var(--on-surface); }
          .input-field:focus { background-color: var(--surface-container-highest); }
          .btn-primary { @apply inline-flex justify-center rounded-xl px-6 py-3 text-sm font-semibold text-white transition-opacity; background: linear-gradient(135deg, var(--primary), var(--primary-fixed)); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
          .btn-primary:hover { @apply opacity-90; }
          .heritage-divider { height: 2px; width: 100%; opacity: 0.2; background-image: url('data:image/svg+xml;utf8,<svg width="40" height="2" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v2H0zM15 0h10v2H15zM30 0h10v2h-10z" fill="rgb(26,28,28)"/></svg>'); background-repeat: repeat-x; margin: 1.5rem 0; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const eyeOpen = btn.querySelector('.eye-open');
            const eyeClosed = btn.querySelector('.eye-closed');
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
        function showForgotForm() {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('forgotForm').classList.remove('hidden');
        }
        function showLoginForm() {
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('forgotForm').classList.add('hidden');
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md card relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-40 h-40 opacity-5 pointer-events-none rotate-45" style="background-image: url('data:image/svg+xml;utf8,<svg viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M50 0 L100 50 L50 100 L0 50 Z\' fill=\'black\'/></svg>'); background-size: 50px 50px;"></div>

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-[var(--primary)]"><?php echo $lang['app_name']; ?></h1>
            <p class="mt-2 text-sm text-gray-500 font-medium tracking-wide"><?php echo $lang['login']; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 border border-red-400 text-red-700 text-sm font-medium"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-100 border border-green-400 text-green-700 text-sm font-medium"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="loginForm">
            <form method="POST" action="login.php" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div><input id="email" name="email" type="email" required class="input-field" placeholder="<?php echo $lang['email']; ?>"></div>
                <div class="relative">
                    <input id="password" name="password" type="password" required class="input-field pr-10" placeholder="<?php echo $lang['password']; ?>">
                    <button type="button" onclick="togglePassword('password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                <div class="text-right">
                    <button type="button" onclick="showForgotForm()" class="text-xs text-[var(--primary)] hover:underline"><?php echo $lang['forgot_password'] ?? 'Forgot password?'; ?></button>
                </div>
                <button type="submit" class="btn-primary w-full"><?php echo $lang['login']; ?></button>
            </form>
            <div class="heritage-divider"></div>
            <div class="text-center"><p class="text-sm text-gray-500">Don't have an account? <a href="register.php" class="font-semibold text-[var(--primary)] hover:underline"><?php echo $lang['register']; ?></a></p></div>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgotForm" class="hidden">
            <form method="POST" action="login.php" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="forgot">
                <div><input type="email" name="email" required class="input-field" placeholder="<?php echo $lang['email']; ?>"></div>
                <button type="submit" class="btn-primary w-full"><?php echo $lang['send_reset_link'] ?? 'Send reset link'; ?></button>
                <button type="button" onclick="showLoginForm()" class="w-full text-center text-sm text-gray-500 hover:text-gray-700"><?php echo $lang['back_to_login'] ?? 'Back to login'; ?></button>
            </form>
        </div>
    </div>
</body>
</html>