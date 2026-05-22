<?php
// profile.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login();
$user_id = get_current_user_id();

function _t($key, $fallback = '') {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : ($fallback ?: $key);
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch bank accounts
$stmtBanks = $pdo->prepare("SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY created_at ASC");
$stmtBanks->execute([$user_id]);
$banks = $stmtBanks->fetchAll();

// Avatar presets
$avatarPresets = [
    ['file' => 'av_1.svg', 'gradient' => 'from-green-500 to-emerald-700', 'initial' => 'A'],
    ['file' => 'av_2.svg', 'gradient' => 'from-blue-500 to-indigo-700', 'initial' => 'B'],
    ['file' => 'av_3.svg', 'gradient' => 'from-purple-500 to-pink-600', 'initial' => 'C'],
    ['file' => 'av_4.svg', 'gradient' => 'from-amber-500 to-orange-700', 'initial' => 'D'],
];

// Current avatar HTML
$avatarHtml = '';
if ($user['avatar_type'] === 'upload' && !empty($user['avatar_url'])) {
    $avatarHtml = '<img src="assets/images/avatars/' . htmlspecialchars($user['avatar_url']) . '" class="w-full h-full object-cover">';
} else {
    $preset = $avatarPresets[array_search($user['avatar_url'], array_column($avatarPresets, 'file'))] ?? $avatarPresets[0];
    $avatarHtml = '<div class="w-full h-full bg-gradient-to-br ' . $preset['gradient'] . ' flex items-center justify-center font-bold text-3xl text-white">' . $preset['initial'] . '</div>';
}

// -------------------------------------------------------------------
// AJAX handlers
// -------------------------------------------------------------------
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        // Update username (full name is now removed)
        if ($_POST['action'] === 'update_username') {
            $username = trim($_POST['username']);
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => _t('username_required', 'Username is required')]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => _t('username_taken', 'Username already taken')]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $user_id]);
            echo json_encode(['success' => true, 'message' => _t('username_updated', 'Username updated')]);
            exit;
        }
        
        // Update account type
        if ($_POST['action'] === 'update_account_type') {
            $account_type = $_POST['account_type'];
            if (!in_array($account_type, ['personal', 'couple'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid account type']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET account_type = ? WHERE id = ?");
            $stmt->execute([$account_type, $user_id]);
            echo json_encode(['success' => true, 'message' => _t('account_type_updated', 'Account type updated')]);
            exit;
        }
        
        // Change password – prevent same as old
        if ($_POST['action'] === 'change_password') {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userData = $stmt->fetch();
            if (!password_verify($current, $userData['password_hash'])) {
                echo json_encode(['success' => false, 'message' => _t('current_password_wrong', 'Current password is incorrect')]);
                exit;
            }
            if (strlen($new) < 6) {
                echo json_encode(['success' => false, 'message' => _t('password_too_short', 'New password must be at least 6 characters')]);
                exit;
            }
            if ($new !== $confirm) {
                echo json_encode(['success' => false, 'message' => _t('passwords_not_match', 'New passwords do not match')]);
                exit;
            }
            // Check if new password is same as old
            if (password_verify($new, $userData['password_hash'])) {
                echo json_encode(['success' => false, 'message' => _t('password_same_as_old', 'New password cannot be the same as your current password')]);
                exit;
            }
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            echo json_encode(['success' => true, 'message' => _t('password_changed', 'Password changed successfully')]);
            exit;
        }
        
        // Update avatar (fixed to handle file upload properly)
        if ($_POST['action'] === 'update_avatar') {
            $avatar_type = $_POST['avatar_type'] ?? 'avatar';
            $avatar_val = $_POST['selected_avatar'] ?? 'av_1.svg';
            
            // Handle file upload
            if ($avatar_type === 'upload' && isset($_FILES['new_avatar']) && $_FILES['new_avatar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['new_avatar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $filename = uniqid('avatar_') . '.' . $ext;
                    $uploadDir = 'assets/images/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    if (move_uploaded_file($_FILES['new_avatar']['tmp_name'], $uploadDir . $filename)) {
                        $avatar_val = $filename;
                        $avatar_type = 'upload';
                    } else {
                        echo json_encode(['success' => false, 'message' => _t('upload_failed', 'Failed to upload avatar')]);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => _t('invalid_image', 'Invalid image format. Use JPG, PNG, WEBP.')]);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE users SET avatar_type = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$avatar_type, $avatar_val, $user_id]);
            
            // Generate new avatar HTML
            $newAvatarHtml = '';
            if ($avatar_type === 'upload') {
                $newAvatarHtml = '<img src="assets/images/avatars/' . htmlspecialchars($avatar_val) . '" class="w-full h-full object-cover">';
            } else {
                $preset = $avatarPresets[array_search($avatar_val, array_column($avatarPresets, 'file'))] ?? $avatarPresets[0];
                $newAvatarHtml = '<div class="w-full h-full bg-gradient-to-br ' . $preset['gradient'] . ' flex items-center justify-center font-bold text-3xl text-white">' . $preset['initial'] . '</div>';
            }
            echo json_encode(['success' => true, 'message' => _t('avatar_updated', 'Profile picture updated'), 'avatar_html' => $newAvatarHtml]);
            exit;
        }
        
        // Update demographics (gender, education, occupation, kids)
        if ($_POST['action'] === 'update_demographics') {
            $gender = $_POST['gender'] ?? null;
            $edu = $_POST['education_level'] ?? null;
            $occ = $_POST['occupation'] ?? null;
            $has_kids = isset($_POST['has_kids']) && $_POST['has_kids'] == '1' ? 1 : 0;
            $kids_amount = $has_kids ? (float)($_POST['kids_allowance_amount'] ?? 0) : 0;
            $kids_int = $has_kids ? ($_POST['kids_allowance_interval'] ?? 'none') : 'none';
            
            $stmt = $pdo->prepare("UPDATE users SET gender = ?, education_level = ?, occupation = ?, has_kids = ?, kids_allowance_amount = ?, kids_allowance_interval = ? WHERE id = ?");
            $stmt->execute([$gender, $edu, $occ, $has_kids, $kids_amount, $kids_int, $user_id]);
            echo json_encode(['success' => true, 'message' => _t('demographics_updated', 'Personal details updated')]);
            exit;
        }
        
        // Bank account CRUD (same as before, omitted for brevity – keep your existing code)
        if ($_POST['action'] === 'add_bank') {
            $name = trim($_POST['bank_name']);
            $initial = (float)($_POST['initial_balance'] ?? 0);
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => _t('bank_name_required', 'Account name is required')]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO bank_accounts (user_id, name, initial_balance) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $name, $initial]);
            $newId = $pdo->lastInsertId();
            $bankHtml = '<div class="bank-item group flex items-center justify-between p-4 bg-[var(--surface-container-low)] rounded-xl" data-bank-id="' . $newId . '">
                <div class="flex-1 cursor-pointer edit-bank-trigger" data-bank-id="' . $newId . '" data-name="' . htmlspecialchars($name) . '" data-balance="' . $initial . '">
                    <p class="font-bold text-lg">' . htmlspecialchars($name) . '</p>
                    <p class="text-xs text-gray-500">' . _t('initial_balance', 'Initial Balance') . ': ' . format_currency($initial) . '</p>
                </div>
                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="edit-bank-btn p-1 text-gray-400 hover:text-[var(--primary)] transition-colors" data-bank-id="' . $newId . '" data-name="' . htmlspecialchars($name) . '" data-balance="' . $initial . '"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                    <button class="delete-bank-btn p-1 text-gray-400 hover:text-[var(--tertiary)] transition-colors" data-bank-id="' . $newId . '"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div>
            </div>';
            echo json_encode(['success' => true, 'message' => _t('bank_added', 'Bank account added'), 'bank_html' => $bankHtml]);
            exit;
        }
        
        if ($_POST['action'] === 'edit_bank') {
            $bank_id = (int)$_POST['bank_id'];
            $name = trim($_POST['bank_name']);
            $initial = (float)($_POST['initial_balance'] ?? 0);
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => _t('bank_name_required', 'Account name is required')]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE bank_accounts SET name = ?, initial_balance = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $initial, $bank_id, $user_id]);
            $bankHtml = '<div class="bank-item group flex items-center justify-between p-4 bg-[var(--surface-container-low)] rounded-xl" data-bank-id="' . $bank_id . '">
                <div class="flex-1 cursor-pointer edit-bank-trigger" data-bank-id="' . $bank_id . '" data-name="' . htmlspecialchars($name) . '" data-balance="' . $initial . '">
                    <p class="font-bold text-lg">' . htmlspecialchars($name) . '</p>
                    <p class="text-xs text-gray-500">' . _t('initial_balance', 'Initial Balance') . ': ' . format_currency($initial) . '</p>
                </div>
                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="edit-bank-btn p-1 text-gray-400 hover:text-[var(--primary)] transition-colors" data-bank-id="' . $bank_id . '" data-name="' . htmlspecialchars($name) . '" data-balance="' . $initial . '"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                    <button class="delete-bank-btn p-1 text-gray-400 hover:text-[var(--tertiary)] transition-colors" data-bank-id="' . $bank_id . '"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                </div>
            </div>';
            echo json_encode(['success' => true, 'message' => _t('bank_updated', 'Bank account updated'), 'bank_html' => $bankHtml, 'bank_id' => $bank_id]);
            exit;
        }
        
        if ($_POST['action'] === 'delete_bank') {
            $bank_id = (int)$_POST['bank_id'];
            $stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$bank_id, $user_id]);
            echo json_encode(['success' => true, 'message' => _t('bank_deleted', 'Bank account deleted'), 'bank_id' => $bank_id]);
            exit;
        }
    }
    exit;
}

include 'inc/header.php';
?>

<div class="max-w-6xl mx-auto space-y-8 px-4 sm:px-0">
    <h2 class="text-3xl font-bold font-display"><?php echo _t('profile', 'Profile'); ?></h2>
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- LEFT COLUMN: Avatar & Info -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Avatar Card -->
            <div class="card group relative">
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button id="changeAvatarBtn" class="text-xs bg-black/50 text-white px-2 py-1 rounded-full backdrop-blur-sm"><?php echo _t('change', 'Change'); ?></button>
                </div>
                <div class="flex flex-col items-center">
                    <div id="avatarPreview" class="w-32 h-32 rounded-full shadow-lg overflow-hidden ring-4 ring-[var(--surface-container-highest)] mb-4 transition-transform duration-300 group-hover:scale-105"><?php echo $avatarHtml; ?></div>
                    <h3 id="profileFullName" class="text-xl font-bold text-center"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p class="text-gray-500 text-sm text-center"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-gray-400 text-xs text-center mt-1 font-mono">@<?php echo htmlspecialchars($user['username'] ?? 'not set'); ?></p>
                </div>
            </div>

            <!-- Update Username Form -->
            <div class="card">
                <form id="updateUsernameForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_username">
                    <div>
                        <label class="block text-sm font-semibold mb-1"><?php echo _t('username', 'Username'); ?></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="text-gray-500">@</span></div>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required class="input-field pl-8" placeholder="username">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full"><?php echo _t('update_username', 'Update Username'); ?></button>
                </form>
            </div>

            <!-- Update Account Type -->
            <div class="card">
                <form id="updateAccountTypeForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_account_type">
                    <div>
                        <label class="block text-sm font-semibold mb-1"><?php echo _t('account_type', 'Account Type'); ?></label>
                        <select name="account_type" class="input-field">
                            <option value="personal" <?php echo $user['account_type'] == 'personal' ? 'selected' : ''; ?>><?php echo _t('personal', 'Personal'); ?></option>
                            <option value="couple" <?php echo $user['account_type'] == 'couple' ? 'selected' : ''; ?>><?php echo _t('couple', 'Couple'); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full"><?php echo _t('update_account_type', 'Update Account Type'); ?></button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="card">
                <h4 class="font-bold text-lg mb-4"><?php echo _t('change_password', 'Change Password'); ?></h4>
                <form id="changePasswordForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label class="block text-sm font-semibold mb-1"><?php echo _t('current_password', 'Current Password'); ?></label>
                        <input type="password" name="current_password" required class="input-field">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1"><?php echo _t('new_password', 'New Password'); ?></label>
                        <input type="password" name="new_password" required class="input-field">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1"><?php echo _t('confirm_new_password', 'Confirm New Password'); ?></label>
                        <input type="password" name="confirm_password" required class="input-field">
                    </div>
                    <button type="submit" class="btn-primary w-full"><?php echo _t('change_password', 'Change Password'); ?></button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: Personal Details & Bank Accounts -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Personal Details Form -->
            <div class="card">
                <h4 class="font-bold text-lg mb-4"><?php echo _t('personal_details', 'Personal Details'); ?></h4>
                <form id="updateDemographicsForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_demographics">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1"><?php echo _t('gender', 'Gender'); ?></label>
                            <select name="gender" class="input-field">
                                <option value="male" <?php echo $user['gender'] == 'male' ? 'selected' : ''; ?>><?php echo _t('male', 'Male'); ?></option>
                                <option value="female" <?php echo $user['gender'] == 'female' ? 'selected' : ''; ?>><?php echo _t('female', 'Female'); ?></option>
                                <option value="other" <?php echo $user['gender'] == 'other' ? 'selected' : ''; ?>><?php echo _t('other', 'Other'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1"><?php echo _t('education_level', 'Education Level'); ?></label>
                            <select name="education_level" class="input-field">
                                <option value="high_school" <?php echo $user['education_level'] == 'high_school' ? 'selected' : ''; ?>><?php echo _t('high_school', 'High School'); ?></option>
                                <option value="bachelors" <?php echo $user['education_level'] == 'bachelors' ? 'selected' : ''; ?>><?php echo _t('bachelors', "Bachelor's Degree"); ?></option>
                                <option value="masters" <?php echo $user['education_level'] == 'masters' ? 'selected' : ''; ?>><?php echo _t('masters', "Master's Degree"); ?></option>
                                <option value="phd" <?php echo $user['education_level'] == 'phd' ? 'selected' : ''; ?>><?php echo _t('phd', 'PhD'); ?></option>
                                <option value="tvet" <?php echo $user['education_level'] == 'tvet' ? 'selected' : ''; ?>><?php echo _t('tvet', 'TVET / Vocational'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1"><?php echo _t('occupation', 'Occupation'); ?></label>
                            <select name="occupation" class="input-field">
                                <option value="employed" <?php echo $user['occupation'] == 'employed' ? 'selected' : ''; ?>><?php echo _t('employed', 'Employed'); ?></option>
                                <option value="self_employed" <?php echo $user['occupation'] == 'self_employed' ? 'selected' : ''; ?>><?php echo _t('self_employed', 'Self-Employed'); ?></option>
                                <option value="unemployed" <?php echo $user['occupation'] == 'unemployed' ? 'selected' : ''; ?>><?php echo _t('unemployed', 'Unemployed'); ?></option>
                                <option value="student" <?php echo $user['occupation'] == 'student' ? 'selected' : ''; ?>><?php echo _t('student', 'Student'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Kids Allowance Toggle -->
                    <div class="mt-4 p-4 rounded-xl border border-[var(--outline-variant)] bg-[var(--surface-container-lowest)]">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold"><?php echo _t('has_kids', 'Do you have kids?'); ?></label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="has_kids_toggle" class="sr-only peer" <?php echo !empty($user['has_kids']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[var(--primary)] peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                            <input type="hidden" name="has_kids" id="has_kids_hidden" value="<?php echo $user['has_kids'] ?? 0; ?>">
                        </div>
                        <div id="kidsSection" class="<?php echo empty($user['has_kids']) ? 'hidden' : ''; ?> space-y-4 mt-4 p-4 bg-[var(--surface-container-low)] rounded-xl">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><?php echo _t('allowance_amount', 'Allowance Amount'); ?></label>
                                <div class="flex items-center gap-3">
                                    <input type="range" name="kids_allowance_amount_slider" min="0" max="500" step="5" value="<?php echo htmlspecialchars($user['kids_allowance_amount'] ?? 0); ?>" class="flex-1 accent-[var(--primary)]">
                                    <input type="number" step="0.01" name="kids_allowance_amount" value="<?php echo htmlspecialchars($user['kids_allowance_amount'] ?? 0); ?>" class="input-field w-28 text-center">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><?php echo _t('allowance_interval', 'Interval'); ?></label>
                                <select name="kids_allowance_interval" class="input-field">
                                    <option value="weekly" <?php echo $user['kids_allowance_interval'] == 'weekly' ? 'selected' : ''; ?>><?php echo _t('weekly', 'Weekly'); ?></option>
                                    <option value="monthly" <?php echo $user['kids_allowance_interval'] == 'monthly' ? 'selected' : ''; ?>><?php echo _t('monthly', 'Monthly'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full"><?php echo _t('save_changes', 'Save Changes'); ?></button>
                </form>
            </div>

            <!-- Bank Accounts Section -->
            <div class="card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold font-display flex items-center gap-2">
                        <svg class="w-6 h-6 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <?php echo _t('bank_accounts', 'Bank Accounts'); ?>
                    </h3>
                </div>
                <div id="banksList" class="space-y-3 mb-8">
                    <?php if (empty($banks)): ?>
                        <p class="text-sm text-gray-500 text-center py-6"><?php echo _t('no_banks', 'No bank accounts added yet.'); ?></p>
                    <?php else: ?>
                        <?php foreach ($banks as $b): ?>
                            <div class="bank-item group flex items-center justify-between p-4 bg-[var(--surface-container-low)] rounded-xl transition-all duration-200 hover:shadow-md" data-bank-id="<?php echo $b['id']; ?>">
                                <div class="flex-1 cursor-pointer edit-bank-trigger" data-bank-id="<?php echo $b['id']; ?>" data-name="<?php echo htmlspecialchars($b['name']); ?>" data-balance="<?php echo $b['initial_balance']; ?>">
                                    <p class="font-bold text-lg"><?php echo htmlspecialchars($b['name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo _t('initial_balance', 'Initial Balance'); ?>: <?php echo format_currency($b['initial_balance']); ?></p>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="edit-bank-btn p-1 text-gray-400 hover:text-[var(--primary)] transition-colors" data-bank-id="<?php echo $b['id']; ?>" data-name="<?php echo htmlspecialchars($b['name']); ?>" data-balance="<?php echo $b['initial_balance']; ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                    <button class="delete-bank-btn p-1 text-gray-400 hover:text-[var(--tertiary)] transition-colors" data-bank-id="<?php echo $b['id']; ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="heritage-divider"></div>
                <h4 class="font-bold text-md mb-4"><?php echo _t('add_new_account', 'Add New Account'); ?></h4>
                <form id="addBankForm" class="flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="add_bank">
                    <div class="flex-1"><input type="text" name="bank_name" required placeholder="<?php echo _t('account_name', 'Account Name'); ?>" class="input-field"></div>
                    <div class="flex-1"><input type="number" step="0.01" name="initial_balance" required placeholder="<?php echo _t('initial_balance', 'Initial Balance'); ?>" class="input-field"></div>
                    <button type="submit" class="btn-primary whitespace-nowrap"><?php echo _t('add', 'Add'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Modal -->
<div id="avatarModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden transition-all">
    <div class="card max-w-md w-full mx-4 p-6 transform transition-all scale-95 opacity-0" id="avatarModalContent">
        <h3 class="text-xl font-bold mb-4"><?php echo _t('change_avatar', 'Change Profile Picture'); ?></h3>
        <form id="updateAvatarForm" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_avatar">
            <div>
                <label class="block text-sm font-semibold mb-2"><?php echo _t('preset_avatars', 'Preset Avatars'); ?></label>
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <?php foreach($avatarPresets as $index => $preset): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="selected_avatar" value="<?php echo $preset['file']; ?>" class="peer sr-only" <?php echo ($user['avatar_type'] == 'avatar' && $user['avatar_url'] == $preset['file']) ? 'checked' : ''; ?>>
                            <div class="aspect-square rounded-full bg-gradient-to-br <?php echo $preset['gradient']; ?> flex items-center justify-center text-white text-xl font-bold peer-checked:ring-4 ring-[var(--primary)] transition-all duration-200 hover:scale-105">
                                <?php echo $preset['initial']; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="border-t border-[var(--outline-variant)] pt-4">
                <label class="block text-sm font-semibold mb-2"><?php echo _t('upload_photo', 'Upload Photo'); ?></label>
                <div id="dropZone" class="border-2 border-dashed border-[var(--outline-variant)] rounded-xl p-6 text-center cursor-pointer transition-all hover:border-[var(--primary)]">
                    <input type="file" name="new_avatar" accept="image/*" class="hidden" id="avatarFileInput">
                    <div id="dropZoneContent">
                        <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-500"><?php echo _t('drag_drop', 'Drag & drop or click to upload'); ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?php echo _t('image_formats', 'JPG, PNG, WEBP up to 2MB'); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <input type="radio" name="avatar_type" value="upload" id="uploadRadio" <?php echo $user['avatar_type'] == 'upload' ? 'checked' : ''; ?> class="accent-[var(--primary)]">
                    <label for="uploadRadio" class="text-sm"><?php echo _t('use_uploaded', 'Use uploaded image'); ?></label>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <input type="radio" name="avatar_type" value="avatar" id="presetRadio" <?php echo $user['avatar_type'] == 'avatar' ? 'checked' : ''; ?> class="accent-[var(--primary)]">
                    <label for="presetRadio" class="text-sm"><?php echo _t('use_preset', 'Use preset avatar'); ?></label>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="btn-primary flex-1"><?php echo _t('save', 'Save'); ?></button>
                <button type="button" id="closeAvatarModalBtn" class="btn-secondary flex-1"><?php echo _t('cancel', 'Cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Bank Panel (slide‑in) -->
<div id="editBankPanel" class="fixed inset-y-0 right-0 w-full max-w-md bg-[var(--surface-container-lowest)] shadow-2xl transform translate-x-full transition-transform duration-300 z-50">
    <div class="p-6 h-full flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold"><?php echo _t('edit_bank_account', 'Edit Bank Account'); ?></h3>
            <button id="closeBankPanelBtn" class="text-gray-400 hover:text-gray-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="editBankForm" class="flex-1 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="edit_bank">
            <input type="hidden" name="bank_id" id="edit_bank_id">
            <div><label class="block text-sm font-semibold mb-1"><?php echo _t('account_name', 'Account Name'); ?></label><input type="text" name="bank_name" id="edit_bank_name" required class="input-field"></div>
            <div><label class="block text-sm font-semibold mb-1"><?php echo _t('initial_balance', 'Initial Balance'); ?></label><input type="number" step="0.01" name="initial_balance" id="edit_bank_balance" required class="input-field"></div>
            <div class="pt-4"><button type="submit" class="btn-primary w-full"><?php echo _t('save_changes', 'Save Changes'); ?></button></div>
        </form>
    </div>
</div>
<div id="editBankOverlay" class="fixed inset-0 bg-black/50 z-40 hidden transition-opacity"></div>

<script>
// Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white text-sm ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} transform translate-x-full transition-all duration-300`;
    toast.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}"/></svg><span>${message}</span>`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => { toast.classList.add('translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
}

// Update username
document.getElementById('updateUsernameForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) showToast(data.message);
    else showToast(data.message, 'error');
});

// Update account type
document.getElementById('updateAccountTypeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) showToast(data.message);
    else showToast(data.message, 'error');
});

// Change password
document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) { showToast(data.message); e.target.reset(); }
    else showToast(data.message, 'error');
});

// Kids allowance toggle
const kidsToggle = document.querySelector('input[name="has_kids_toggle"]');
const kidsHidden = document.getElementById('has_kids_hidden');
const kidsSection = document.getElementById('kidsSection');
if (kidsToggle) {
    kidsToggle.addEventListener('change', () => {
        const val = kidsToggle.checked ? '1' : '0';
        kidsHidden.value = val;
        if (val === '1') kidsSection.classList.remove('hidden');
        else kidsSection.classList.add('hidden');
    });
}
const amountSlider = document.querySelector('input[name="kids_allowance_amount_slider"]');
const amountInput = document.querySelector('input[name="kids_allowance_amount"]');
if (amountSlider && amountInput) {
    amountSlider.addEventListener('input', () => amountInput.value = amountSlider.value);
    amountInput.addEventListener('input', () => amountSlider.value = amountInput.value);
}
document.getElementById('updateDemographicsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    kidsHidden.value = kidsToggle?.checked ? '1' : '0';
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) showToast(data.message);
    else showToast(data.message, 'error');
});

// Avatar modal
const avatarModal = document.getElementById('avatarModal');
const avatarModalContent = document.getElementById('avatarModalContent');
document.getElementById('changeAvatarBtn').addEventListener('click', () => {
    avatarModal.classList.remove('hidden');
    setTimeout(() => avatarModalContent.classList.remove('scale-95', 'opacity-0'), 10);
});
document.getElementById('closeAvatarModalBtn').addEventListener('click', () => {
    avatarModalContent.classList.add('scale-95', 'opacity-0');
    setTimeout(() => avatarModal.classList.add('hidden'), 300);
});
avatarModal.addEventListener('click', (e) => {
    if (e.target === avatarModal) {
        avatarModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => avatarModal.classList.add('hidden'), 300);
    }
});

// Drag & drop upload
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('avatarFileInput');
const uploadRadio = document.getElementById('uploadRadio');
const presetRadio = document.getElementById('presetRadio');
const avatarPreview = document.getElementById('avatarPreview');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-[var(--primary)]', 'bg-[var(--surface-container-low)]'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-[var(--primary)]', 'bg-[var(--surface-container-low)]'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-[var(--primary)]', 'bg-[var(--surface-container-low)]');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        const reader = new FileReader();
        reader.onload = (ev) => {
            avatarPreview.innerHTML = `<img src="${ev.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(file);
        uploadRadio.checked = true;
    } else {
        showToast('Please drop an image file', 'error');
    }
});
fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(fileInput.files[0]);
        uploadRadio.checked = true;
    }
});

// Update avatar form submit
document.getElementById('updateAvatarForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) {
        showToast(data.message);
        document.getElementById('avatarPreview').innerHTML = data.avatar_html;
        document.getElementById('closeAvatarModalBtn').click();
    } else {
        showToast(data.message, 'error');
    }
});

// Bank accounts add
document.getElementById('addBankForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) {
        showToast(data.message);
        const banksList = document.getElementById('banksList');
        if (banksList.children.length === 1 && banksList.children[0].tagName === 'P') banksList.innerHTML = '';
        banksList.insertAdjacentHTML('beforeend', data.bank_html);
        e.target.reset();
    } else showToast(data.message, 'error');
});

// Edit bank panel
const editPanel = document.getElementById('editBankPanel');
const editOverlay = document.getElementById('editBankOverlay');
function openEditPanel(bankId, name, balance) {
    document.getElementById('edit_bank_id').value = bankId;
    document.getElementById('edit_bank_name').value = name;
    document.getElementById('edit_bank_balance').value = balance;
    editPanel.classList.remove('translate-x-full');
    editOverlay.classList.remove('hidden');
}
function closeEditPanel() {
    editPanel.classList.add('translate-x-full');
    editOverlay.classList.add('hidden');
}
document.getElementById('closeBankPanelBtn').addEventListener('click', closeEditPanel);
editOverlay.addEventListener('click', closeEditPanel);

document.getElementById('banksList').addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.edit-bank-btn');
    const deleteBtn = e.target.closest('.delete-bank-btn');
    const trigger = e.target.closest('.edit-bank-trigger');
    if (editBtn || trigger) {
        const btn = editBtn || trigger;
        openEditPanel(btn.dataset.bankId, btn.dataset.name, btn.dataset.balance);
    }
    if (deleteBtn && confirm('<?php echo addslashes(_t('confirm_delete_bank', 'Delete this bank account? This will also delete all transactions linked to it.')); ?>')) {
        const bankId = deleteBtn.dataset.bankId;
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
        formData.append('action', 'delete_bank');
        formData.append('bank_id', bankId);
        const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            const bankItem = document.querySelector(`.bank-item[data-bank-id="${bankId}"]`);
            if (bankItem) bankItem.remove();
            if (document.getElementById('banksList').children.length === 0) {
                document.getElementById('banksList').innerHTML = '<p class="text-sm text-gray-500 text-center py-6"><?php echo _t('no_banks', 'No bank accounts added yet.'); ?></p>';
            }
        } else showToast(data.message, 'error');
    }
});

document.getElementById('editBankForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('profile.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
    const data = await res.json();
    if (data.success) {
        showToast(data.message);
        const oldBank = document.querySelector(`.bank-item[data-bank-id="${data.bank_id}"]`);
        if (oldBank) oldBank.outerHTML = data.bank_html;
        closeEditPanel();
    } else showToast(data.message, 'error');
});
</script>

<?php include 'inc/footer.php'; ?>