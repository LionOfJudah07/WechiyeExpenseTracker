<?php
// onboarding.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login(false);

$user_id = get_current_user_id();

$stmt = $pdo->prepare("SELECT is_onboarded FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if ($user && $user['is_onboarded']) {
    header('Location: dashboard.php');
    exit;
}

function _t($key, $fallback = '') {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : ($fallback ?: $key);
}

$lang = get_language_strings();
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1) $step = 1;
if ($step > 4) $step = 4;

$avatarPresets = [
    ['file' => 'av_1.svg', 'gradient' => 'from-green-500 to-emerald-700', 'initial' => 'A'],
    ['file' => 'av_2.svg', 'gradient' => 'from-blue-500 to-indigo-700', 'initial' => 'B'],
    ['file' => 'av_3.svg', 'gradient' => 'from-purple-500 to-pink-600', 'initial' => 'C'],
    ['file' => 'av_4.svg', 'gradient' => 'from-amber-500 to-orange-700', 'initial' => 'D'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $avatar_type = $_POST['avatar_type'] ?? 'avatar';
        $avatar_val = $_POST['selected_avatar'] ?? 'av_1.svg';
        $bank_name = trim($_POST['bank_name'] ?? '');
        $initial_balance = (float)($_POST['initial_balance'] ?? 0);
        $gender = $_POST['gender'] ?? 'other';
        $education = $_POST['education_level'] ?? 'high_school';
        $occupation = $_POST['occupation'] ?? 'unemployed';
        $account_type = $_POST['account_type'] ?? 'personal';
        $has_kids = (isset($_POST['has_kids']) && $_POST['has_kids'] === '1') ? 1 : 0;
        $kids_allowance_amount = $has_kids ? (float)($_POST['kids_allowance_amount'] ?? 0) : 0;
        $kids_allowance_interval = $has_kids ? ($_POST['kids_allowance_interval'] ?? 'none') : 'none';

        if (empty($bank_name)) {
            $error = _t('bank_name_required', 'You must provide an initial bank account name.');
        } else {
            if ($avatar_type === 'upload' && isset($_FILES['custom_avatar']) && $_FILES['custom_avatar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['custom_avatar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $filename = uniqid('avatar_') . '.' . $ext;
                    $uploadDir = 'assets/images/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    if (move_uploaded_file($_FILES['custom_avatar']['tmp_name'], $uploadDir . $filename)) {
                        $avatar_val = $filename;
                    } else {
                        $error = _t('upload_failed', 'Failed to upload avatar.');
                    }
                } else {
                    $error = _t('invalid_image', 'Invalid image format.');
                }
            } else if ($avatar_type === 'upload') {
                $avatar_type = 'avatar';
            }

            if (empty($error)) {
                $pdo->beginTransaction();
                try {
                    $stmtU = $pdo->prepare("UPDATE users SET avatar_type = ?, avatar_url = ?, gender = ?, occupation = ?, education_level = ?, has_kids = ?, kids_allowance_amount = ?, kids_allowance_interval = ?, account_type = ?, is_onboarded = 1 WHERE id = ?");
                    $stmtU->execute([$avatar_type, $avatar_val, $gender, $occupation, $education, $has_kids, $kids_allowance_amount, $kids_allowance_interval, $account_type, $user_id]);
                    $stmtB = $pdo->prepare("INSERT INTO bank_accounts (user_id, name, initial_balance) VALUES (?, ?, ?)");
                    $stmtB->execute([$user_id, $bank_name, $initial_balance]);
                    $pdo->commit();
                    header('Location: dashboard.php');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = _t('db_error', 'Database Error: ') . $e->getMessage();
                }
            }
        }
    } else {
        $error = _t('csrf_error', 'Invalid CSRF Token');
    }
}

include 'inc/header.php';
?>

<div class="max-w-2xl mx-auto my-8">
    <!-- Progress steps -->
    <div class="mb-8 flex justify-between items-center">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="flex-1 flex items-center">
                <div class="relative flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg <?php echo $step >= $i ? 'bg-[var(--primary)] text-white' : 'bg-[var(--surface-container-highest)] text-gray-500'; ?>"><?php echo $i; ?></div>
                    <span class="text-xs mt-1 font-medium <?php echo $step >= $i ? 'text-[var(--primary)]' : 'text-gray-400'; ?>">
                        <?php if ($i == 1) echo _t('step_avatar', 'Avatar');
                        elseif ($i == 2) echo _t('step_details', 'Details');
                        elseif ($i == 3) echo _t('step_account', 'Account Type');
                        else echo _t('step_bank', 'Bank'); ?>
                    </span>
                </div>
                <?php if ($i < 4): ?>
                    <div class="flex-1 h-0.5 mx-2 <?php echo $step > $i ? 'bg-[var(--primary)]' : 'bg-[var(--surface-container-highest)]'; ?>"></div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div class="card p-8">
        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-600 border border-red-200"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="onboarding.php" enctype="multipart/form-data" class="space-y-8" id="onboardingForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="step" id="currentStep" value="<?php echo $step; ?>">

            <!-- STEP 1: Avatar -->
            <div id="step1" class="<?php echo $step != 1 ? 'hidden' : ''; ?>">
                <h3 class="text-2xl font-bold mb-6"><?php echo _t('choose_avatar', 'Choose Your Avatar'); ?></h3>
                <div class="flex justify-center mb-8">
                    <div id="avatarPreview" class="w-32 h-32 rounded-full shadow-lg overflow-hidden ring-4 ring-[var(--surface-container-highest)]">
                        <div class="w-full h-full bg-gradient-to-br from-green-500 to-emerald-700 flex items-center justify-center text-white text-4xl font-bold">A</div>
                    </div>
                </div>
                <div class="flex gap-4 mb-6">
                    <label class="flex items-center gap-2"><input type="radio" name="avatar_type" value="avatar" checked class="accent-[var(--primary)]"> <?php echo _t('choose_preset', 'Choose Preset'); ?></label>
                    <label class="flex items-center gap-2"><input type="radio" name="avatar_type" value="upload" class="accent-[var(--primary)]"> <?php echo _t('upload_custom', 'Upload Custom'); ?></label>
                </div>
                <div id="presetContainer" class="grid grid-cols-4 gap-4 mb-6">
                    <?php foreach($avatarPresets as $index => $preset): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="selected_avatar" value="<?php echo $preset['file']; ?>" class="peer sr-only avatar-preset" <?php echo $index === 0 ? 'checked' : ''; ?> data-gradient="<?php echo $preset['gradient']; ?>" data-initial="<?php echo $preset['initial']; ?>">
                            <div class="aspect-square rounded-full bg-gradient-to-br <?php echo $preset['gradient']; ?> flex items-center justify-center text-white text-2xl font-bold peer-checked:ring-4 ring-[var(--primary)] transition-all"><?php echo $preset['initial']; ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div id="uploadContainer" class="hidden">
                    <div id="dropZone" class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer hover:border-[var(--primary)] transition">
                        <input type="file" name="custom_avatar" accept="image/*" class="hidden" id="avatarFileInput">
                        <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-500"><?php echo _t('drag_drop', 'Drag & drop or click to upload'); ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?php echo _t('image_formats', 'JPG, PNG, WEBP up to 2MB'); ?></p>
                    </div>
                </div>
                <div class="flex justify-end mt-8"><button type="button" class="btn-primary next-step"><?php echo _t('next', 'Next'); ?></button></div>
            </div>

            <!-- STEP 2: Personal Details -->
            <div id="step2" class="<?php echo $step != 2 ? 'hidden' : ''; ?>">
                <h3 class="text-2xl font-bold mb-6"><?php echo _t('personal_details', 'Personal Details'); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div><label class="block text-sm font-semibold mb-1"><?php echo _t('gender', 'Gender'); ?></label><select name="gender" class="input-field" required><option value="male"><?php echo _t('male', 'Male'); ?></option><option value="female"><?php echo _t('female', 'Female'); ?></option><option value="other"><?php echo _t('other', 'Other'); ?></option></select></div>
                    <div><label class="block text-sm font-semibold mb-1"><?php echo _t('education_level', 'Education Level'); ?></label><select name="education_level" class="input-field" required><option value="high_school"><?php echo _t('high_school', 'High School'); ?></option><option value="bachelors"><?php echo _t('bachelors', "Bachelor's Degree"); ?></option><option value="masters"><?php echo _t('masters', "Master's Degree"); ?></option><option value="phd"><?php echo _t('phd', 'PhD'); ?></option><option value="tvet"><?php echo _t('tvet', 'TVET / Vocational'); ?></option></select></div>
                    <div><label class="block text-sm font-semibold mb-1"><?php echo _t('occupation', 'Occupation'); ?></label><select name="occupation" class="input-field" required><option value="employed"><?php echo _t('employed', 'Employed'); ?></option><option value="self_employed"><?php echo _t('self_employed', 'Self-Employed'); ?></option><option value="unemployed"><?php echo _t('unemployed', 'Unemployed'); ?></option><option value="student"><?php echo _t('student', 'Student'); ?></option></select></div>
                </div>
                <div class="mt-6 p-4 rounded-xl border border-[var(--outline-variant)] bg-[var(--surface-container-lowest)]">
                    <p class="font-bold mb-3"><?php echo _t('has_kids', 'Do you have kids?'); ?></p>
                    <div class="flex gap-4 mb-4"><label class="flex items-center gap-2"><input type="radio" name="has_kids" value="0" checked class="accent-[var(--primary)]"> <span>No</span></label><label class="flex items-center gap-2"><input type="radio" name="has_kids" value="1" class="accent-[var(--primary)]"> <span>Yes</span></label></div>
                    <div id="kidsOptions" class="hidden space-y-4 pt-2 border-t border-[var(--outline-variant)]">
                        <p class="font-bold text-sm text-[var(--secondary)]"><?php echo _t('kids_allowance', 'Kids Allowance'); ?></p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1"><label class="block text-xs text-gray-500 mb-1"><?php echo _t('allowance_amount', 'Amount'); ?></label><div class="relative"><div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span>$</span></div><input type="number" step="0.01" name="kids_allowance_amount" class="input-field pl-7" placeholder="0.00"></div></div>
                            <div class="flex-1"><label class="block text-xs text-gray-500 mb-1"><?php echo _t('allowance_interval', 'Interval'); ?></label><select name="kids_allowance_interval" class="input-field"><option value="weekly"><?php echo _t('weekly', 'Weekly'); ?></option><option value="monthly"><?php echo _t('monthly', 'Monthly'); ?></option></select></div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between mt-8"><button type="button" class="btn-secondary prev-step"><?php echo _t('previous', 'Previous'); ?></button><button type="button" class="btn-primary next-step"><?php echo _t('next', 'Next'); ?></button></div>
            </div>

            <!-- STEP 3: Account Type -->
            <div id="step3" class="<?php echo $step != 3 ? 'hidden' : ''; ?>">
                <h3 class="text-2xl font-bold mb-6"><?php echo _t('account_type_choice', 'Account Type'); ?></h3>
                <div class="space-y-4">
                    <div class="p-4 rounded-xl border-2 border-gray-200 hover:border-[var(--primary)] transition-colors">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="account_type" value="personal" class="mt-1 accent-[var(--primary)]" checked>
                            <div><p class="font-bold"><?php echo _t('personal_account', 'Personal Account'); ?></p><p class="text-sm text-gray-500"><?php echo _t('personal_desc', 'Manage your own finances only. No linking with a partner.'); ?></p></div>
                        </label>
                    </div>
                    <div class="p-4 rounded-xl border-2 border-gray-200 hover:border-[var(--primary)] transition-colors">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="account_type" value="couple" class="mt-1 accent-[var(--primary)]">
                            <div><p class="font-bold"><?php echo _t('couple_account', 'Couple Account'); ?></p><p class="text-sm text-gray-500"><?php echo _t('couple_desc', 'Link with your partner to share transactions, budgets, and kids allowance.'); ?></p></div>
                        </label>
                    </div>
                </div>
                <div class="flex justify-between mt-8"><button type="button" class="btn-secondary prev-step"><?php echo _t('previous', 'Previous'); ?></button><button type="button" class="btn-primary next-step"><?php echo _t('next', 'Next'); ?></button></div>
            </div>

            <!-- STEP 4: Bank Account -->
            <div id="step4" class="<?php echo $step != 4 ? 'hidden' : ''; ?>">
                <h3 class="text-2xl font-bold mb-6"><?php echo _t('add_bank_account', 'Add Your Bank Account'); ?></h3>
                <div class="space-y-5">
                    <div><label class="block text-sm font-semibold mb-1"><?php echo _t('account_name', 'Account Name'); ?></label><input type="text" name="bank_name" required placeholder="<?php echo _t('account_name_eg', 'e.g., CBE, Wallet'); ?>" class="input-field"></div>
                    <div><label class="block text-sm font-semibold mb-1"><?php echo _t('initial_balance', 'Initial Balance'); ?></label><div class="relative"><div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span>$</span></div><input type="number" step="0.01" name="initial_balance" required class="input-field pl-8" placeholder="0.00"></div></div>
                </div>
                <div class="flex justify-between mt-8"><button type="button" class="btn-secondary prev-step"><?php echo _t('previous', 'Previous'); ?></button><button type="submit" class="btn-primary"><?php echo _t('finish_setup', 'Finish Setup'); ?></button></div>
            </div>
        </form>
    </div>
</div>

<script>
// Step navigation
const form = document.getElementById('onboardingForm');
const currentStepInput = document.getElementById('currentStep');
const stepDivs = {1: document.getElementById('step1'),2: document.getElementById('step2'),3: document.getElementById('step3'),4: document.getElementById('step4')};

function goToStep(step) {
    for (let i=1;i<=4;i++) stepDivs[i].classList.add('hidden');
    stepDivs[step].classList.remove('hidden');
    currentStepInput.value = step;
    // Update progress indicators
    const steps = document.querySelectorAll('.flex-1 .relative .w-10');
    steps.forEach((circle, idx) => {
        let stepNum = idx+1;
        if (stepNum <= step) {
            circle.classList.add('bg-[var(--primary)]','text-white');
            circle.classList.remove('bg-[var(--surface-container-highest)]','text-gray-500');
        } else {
            circle.classList.remove('bg-[var(--primary)]','text-white');
            circle.classList.add('bg-[var(--surface-container-highest)]','text-gray-500');
        }
    });
    const lines = document.querySelectorAll('.flex-1 .flex-1.h-0.5');
    lines.forEach((line, idx) => {
        let stepNum = idx+1;
        if (step > stepNum) line.classList.add('bg-[var(--primary)]');
        else line.classList.remove('bg-[var(--primary)]');
    });
    const url = new URL(window.location.href);
    url.searchParams.set('step', step);
    window.history.pushState({}, '', url);
}

document.querySelectorAll('.next-step').forEach(btn => btn.addEventListener('click', () => { let next = parseInt(currentStepInput.value)+1; if(next<=4) goToStep(next); }));
document.querySelectorAll('.prev-step').forEach(btn => btn.addEventListener('click', () => { let prev = parseInt(currentStepInput.value)-1; if(prev>=1) goToStep(prev); }));

// Avatar preview
const avatarPreview = document.getElementById('avatarPreview');
const presetRadios = document.querySelectorAll('.avatar-preset');
const presetContainer = document.getElementById('presetContainer');
const uploadContainer = document.getElementById('uploadContainer');
const presetRadio = document.querySelector('input[name="avatar_type"][value="avatar"]');
const uploadRadio = document.querySelector('input[name="avatar_type"][value="upload"]');
const fileInput = document.getElementById('avatarFileInput');
const dropZone = document.getElementById('dropZone');

function updateAvatarPreview(html) { avatarPreview.innerHTML = html; }
presetRadios.forEach(radio => radio.addEventListener('change', () => { if(radio.checked){ updateAvatarPreview(`<div class="w-full h-full bg-gradient-to-br ${radio.dataset.gradient} flex items-center justify-center text-white text-4xl font-bold">${radio.dataset.initial}</div>`); } }));
presetRadio.addEventListener('change', () => { presetContainer.classList.remove('hidden'); uploadContainer.classList.add('hidden'); const checked = document.querySelector('.avatar-preset:checked'); if(checked) updateAvatarPreview(`<div class="w-full h-full bg-gradient-to-br ${checked.dataset.gradient} flex items-center justify-center text-white text-4xl font-bold">${checked.dataset.initial}</div>`); });
uploadRadio.addEventListener('change', () => { presetContainer.classList.add('hidden'); uploadContainer.classList.remove('hidden'); if(fileInput.files.length){ const reader = new FileReader(); reader.onload = (e) => updateAvatarPreview(`<img src="${e.target.result}" class="w-full h-full object-cover">`); reader.readAsDataURL(fileInput.files[0]); } else { updateAvatarPreview(`<div class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-500 text-4xl">?</div>`); } });
dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-[var(--primary)]','bg-[var(--surface-container-low)]'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-[var(--primary)]','bg-[var(--surface-container-low)]'));
dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('border-[var(--primary)]','bg-[var(--surface-container-low)]'); const file = e.dataTransfer.files[0]; if(file && file.type.startsWith('image/')){ const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files; const reader = new FileReader(); reader.onload = (ev) => updateAvatarPreview(`<img src="${ev.target.result}" class="w-full h-full object-cover">`); reader.readAsDataURL(file); uploadRadio.checked = true; uploadRadio.dispatchEvent(new Event('change')); } else { alert('Please drop an image file'); } });
fileInput.addEventListener('change', () => { if(fileInput.files.length){ const reader = new FileReader(); reader.onload = (e) => updateAvatarPreview(`<img src="${e.target.result}" class="w-full h-full object-cover">`); reader.readAsDataURL(fileInput.files[0]); uploadRadio.checked = true; uploadRadio.dispatchEvent(new Event('change')); } });

// Kids toggle
const kidsRadios = document.querySelectorAll('input[name="has_kids"]');
const kidsOptionsDiv = document.getElementById('kidsOptions');
kidsRadios.forEach(radio => radio.addEventListener('change', () => { if(radio.value === '1') kidsOptionsDiv.classList.remove('hidden'); else kidsOptionsDiv.classList.add('hidden'); }));

// Validate step2
const nextToStep3 = document.querySelector('#step2 .next-step');
if(nextToStep3){
    nextToStep3.addEventListener('click', (e) => {
        const gender = document.querySelector('select[name="gender"]').value;
        const edu = document.querySelector('select[name="education_level"]').value;
        const occ = document.querySelector('select[name="occupation"]').value;
        if(!gender || !edu || !occ){ alert('<?php echo addslashes(_t('fill_all_fields', 'Please fill all required fields')); ?>'); e.stopPropagation(); }
        else { goToStep(3); }
    });
}
</script>

<?php include 'inc/footer.php'; ?>