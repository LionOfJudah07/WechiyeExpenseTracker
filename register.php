<?php
// register.php
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
        $full_name = trim($_POST['full_name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $account_type = $_POST['account_type'] ?? 'personal';
        
        // Validate username
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            $error = $lang['username_invalid'] ?? "Username must be 3-30 characters, lowercase letters, numbers, underscores only.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $error = "Password must be at least 8 chars, one number, one uppercase.";
        } else {
            // Check username uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = $lang['username_taken'] ?? "That username is already taken.";
            } else {
                // Check email uniqueness
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email already registered.";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, account_type) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$full_name, $username, $email, $hash, $account_type])) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Seed categories based on predefined
                        $stmtCat = $pdo->prepare("INSERT INTO categories (user_id, name, type) SELECT ?, name, type FROM categories WHERE user_id IS NULL");
                        $stmtCat->execute([$user_id]);
                        
                        $_SESSION['user_id'] = $user_id;
                        
                        if ($account_type === 'couple') {
                            $_SESSION['flash_msg'] = $lang['couple_created_msg'] ?? "Couple account created! Go to Settings to find and link with your partner.";
                        }
                        
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = "Failed to register account.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['register']; ?> - Wechiye</title>
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
            --primary-fixed: #2e7d32;
          }
          body {
            @apply bg-[var(--surface)] text-[var(--on-surface)] antialiased;
            font-family: 'Inter', sans-serif;
          }
          h1, h2, h3 { font-family: 'Plus Jakarta Sans', sans-serif; }
        }
        @layer components {
          .card {
            background-color: var(--surface-container-lowest);
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.04);
            border: 0;
          }
          .input-field {
            @apply block w-full rounded-xl border-0 py-3 px-4 text-sm focus:ring-0 transition-colors;
            background-color: var(--surface-container-low);
            color: var(--on-surface);
          }
          .input-field:focus { background-color: var(--surface-container-highest); }
          .btn-primary {
            @apply inline-flex justify-center rounded-xl px-6 py-3 text-sm font-semibold text-white transition-opacity;
            background: linear-gradient(135deg, var(--primary), var(--primary-fixed));
          }
          .btn-primary:hover { @apply opacity-90; }
          .heritage-divider {
            height: 2px; width: 100%; opacity: 0.2;
            background-image: url('data:image/svg+xml;utf8,<svg width="40" height="2" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v2H0zM15 0h10v2H15zM30 0h10v2h-10z" fill="rgb(26,28,28)"/></svg>');
            background-repeat: repeat-x; margin: 1.5rem 0;
          }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <script>
        function validateForm() {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            if (!/^[a-z0-9_]{3,30}$/.test(username)) {
                alert("Username must be 3-30 characters, lowercase letters, numbers, underscores only."); return false;
            }
            if (pwd !== confirm) {
                alert("Passwords do not match"); return false;
            }
            if(pwd.length < 8 || !/\d/.test(pwd) || !/[A-Z]/.test(pwd)) {
                alert("Password must be at least 8 chars, one number, one uppercase."); return false;
            }
            return true;
        }
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
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md card p-8 relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-40 h-40 opacity-5 pointer-events-none rotate-45" style="background-image: url('data:image/svg+xml;utf8,<svg viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M50 0 L100 50 L50 100 L0 50 Z\' fill=\'black\'/></svg>'); background-size: 50px 50px;"></div>

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-[var(--primary)]"><?php echo $lang['app_name']; ?></h1>
            <p class="mt-2 text-sm text-gray-500 font-medium"><?php echo $lang['register']; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 border border-red-400 text-red-700 text-sm font-medium">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-4" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div>
                <label for="full_name" class="sr-only"><?php echo $lang['full_name']; ?></label>
                <input id="full_name" name="full_name" type="text" required class="input-field" placeholder="<?php echo $lang['full_name']; ?>" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div>
                <label for="username" class="sr-only"><?php echo $lang['username'] ?? 'Username'; ?></label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <span class="text-gray-400 text-sm">@</span>
                    </div>
                    <input id="username" name="username" type="text" required class="input-field pl-8" placeholder="<?php echo $lang['username'] ?? 'username'; ?>" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" pattern="[a-z0-9_]{3,30}">
                </div>
                <p class="text-xs text-gray-400 mt-1 pl-1"><?php echo $lang['username_hint'] ?? 'Lowercase letters, numbers, underscores. 3-30 chars.'; ?></p>
            </div>

            <div>
                <label for="email" class="sr-only"><?php echo $lang['email']; ?></label>
                <input id="email" name="email" type="email" required class="input-field" placeholder="<?php echo $lang['email']; ?>" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div>
                <label for="password" class="sr-only"><?php echo $lang['password']; ?></label>
                <div class="relative">
                    <input id="password" name="password" type="password" required class="input-field pr-10" placeholder="<?php echo $lang['password']; ?>">
                    <button type="button" onclick="togglePassword('password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 focus:outline-none">
                        <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1 pl-1">Min 8 chars, 1 number, 1 uppercase</p>
            </div>
            
            <div>
                <label for="confirm_password" class="sr-only">Confirm Password</label>
                <div class="relative">
                    <input id="confirm_password" name="confirm_password" type="password" required class="input-field pr-10" placeholder="Confirm Password">
                    <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 focus:outline-none">
                        <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $lang['account_type'] ?? 'Account Type'; ?></label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="account_type" value="personal" <?php echo ($_POST['account_type'] ?? 'personal') === 'personal' ? 'checked' : ''; ?> class="text-[var(--primary)] focus:ring-[var(--primary)]">
                        <span class="text-sm"><?php echo $lang['personal'] ?? 'Personal'; ?></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="account_type" value="couple" <?php echo ($_POST['account_type'] ?? '') === 'couple' ? 'checked' : ''; ?> class="text-[var(--primary)] focus:ring-[var(--primary)]">
                        <span class="text-sm"><?php echo $lang['couple'] ?? 'Couple'; ?></span>
                    </label>
                </div>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="btn-primary w-full"><?php echo $lang['register']; ?></button>
            </div>
        </form>

        <div class="heritage-divider"></div>

        <div class="text-center">
            <p class="text-sm text-gray-500">
                Already have an account? 
                <a href="login.php" class="font-semibold text-[var(--primary)] hover:underline"><?php echo $lang['login']; ?></a>
            </p>
        </div>
    </div>
</body>
</html>
