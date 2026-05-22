<?php
// transactions.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login();
$user_id = get_current_user_id();
$user_ids = get_linked_user_id($pdo, $user_id);
$in_clause = str_repeat('?,', count($user_ids) - 1) . '?';

$success_msg = '';
$error_msg = '';

// Handle add / edit / delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token. Please refresh the page and try again.";
    } else {
        if ($_POST['action'] === 'add') {
            $amount = (float)$_POST['amount'];
            $category_id = (int)$_POST['category_id'];
            $bank_account_id = (int)$_POST['bank_account_id'];
            $type = $_POST['type'];
            $date = $_POST['transaction_date'];
            $note = trim($_POST['note'] ?? '');

            if ($amount <= 0) {
                $error_msg = "Amount must be greater than 0.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, bank_account_id, amount, category_id, type, transaction_date, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $bank_account_id, $amount, $category_id, $type, $date, $note])) {
                    $success_msg = "Transaction added successfully.";
                } else {
                    $error_msg = "Database error: could not add transaction.";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $tx_id = (int)$_POST['tx_id'];
            $amount = (float)$_POST['amount'];
            $category_id = (int)$_POST['category_id'];
            $bank_account_id = (int)$_POST['bank_account_id'];
            $type = $_POST['type'];
            $date = $_POST['transaction_date'];
            $note = trim($_POST['note'] ?? '');

            $stmt = $pdo->prepare("UPDATE transactions SET amount=?, category_id=?, bank_account_id=?, type=?, transaction_date=?, note=? WHERE id=? AND user_id IN ($in_clause)");
            $params = array_merge([$amount, $category_id, $bank_account_id, $type, $date, $note, $tx_id], $user_ids);
            if ($stmt->execute($params)) {
                $success_msg = "Transaction updated.";
            } else {
                $error_msg = "Update failed.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $tx_id = (int)$_POST['tx_id'];
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id IN ($in_clause)");
            $params = array_merge([$tx_id], $user_ids);
            if ($stmt->execute($params)) {
                $success_msg = "Transaction deleted.";
            } else {
                $error_msg = "Delete failed.";
            }
        }
    }
    // After processing, redirect to refresh the page (clears POST data)
    header("Location: transactions.php?" . ($success_msg ? "success=" . urlencode($success_msg) : "error=" . urlencode($error_msg)));
    exit;
}

// Show messages from redirect
if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
} elseif (isset($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error']);
}

// Fetch all transactions (with linked users)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id IN ($in_clause) 
    ORDER BY t.transaction_date DESC, t.created_at DESC
");
$stmt->execute($user_ids);
$transactions = $stmt->fetchAll();

// Fetch categories for form
$stmtCat = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY type, name");
$stmtCat->execute([$user_id]);
$categories = $stmtCat->fetchAll();

// Fetch bank accounts
$stmtBanks = $pdo->prepare("SELECT id, name FROM bank_accounts WHERE user_id IN ($in_clause)");
$stmtBanks->execute($user_ids);
$banks = $stmtBanks->fetchAll();

include 'inc/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Add Transaction Form -->
    <div class="lg:col-span-1">
        <div class="card bg-gradient-to-br from-[var(--surface-container-lowest)] to-[var(--surface-container-low)]">
            <h3 class="text-xl font-bold mb-6 font-display"><?php echo $lang['add_transaction']; ?></h3>

            <?php if ($success_msg): ?>
                <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-700 text-sm"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 text-sm"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST" action="transactions.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">

                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="expense" checked class="accent-[var(--tertiary)]">
                        <span class="text-sm"><?php echo $lang['expenses']; ?></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="income" class="accent-[var(--primary)]">
                        <span class="text-sm"><?php echo $lang['income']; ?></span>
                    </label>
                </div>

                <div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span class="text-gray-500">$</span></div>
                        <input type="number" step="0.01" name="amount" required class="input-field pl-8" placeholder="0.00">
                    </div>
                </div>

                <div>
                    <select name="category_id" id="category_select" required class="input-field">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-type="<?php echo $c['type']; ?>">
                                <?php echo $lang[strtolower($c['name'])] ?? htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <select name="bank_account_id" required class="input-field">
                        <?php foreach ($banks as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <input type="date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>" class="input-field">
                </div>

                <div>
                    <textarea name="note" rows="2" class="input-field" placeholder="<?php echo $lang['note']; ?>"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-primary w-full"><?php echo $lang['save']; ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction List -->
    <div class="lg:col-span-2">
        <div class="flex justify-between items-end mb-6">
            <h2 class="text-3xl font-bold font-display"><?php echo $lang['transactions']; ?></h2>
        </div>

        <div class="card p-4 sm:p-6 shadow-sm border-0">
            <div class="space-y-3">
                <?php if (empty($transactions)): ?>
                    <p class="text-gray-500 text-center py-8">No transactions found.</p>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-[var(--surface-container-low)] hover:bg-[var(--surface-container-highest)] transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="tx-icon w-10 h-10 rounded-full bg-[var(--surface-container-highest)] flex items-center justify-center">
                                    <span class="text-xl font-bold"><?php echo substr($tx['category_name'], 0, 1); ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-[var(--on-surface)]"><?php echo $lang[strtolower($tx['category_name'])] ?? htmlspecialchars($tx['category_name']); ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <?php echo date('M d, Y', strtotime($tx['transaction_date'])); ?>
                                        <?php if ($tx['note']) echo ' • <span class="italic truncate w-32 inline-block align-bottom">' . htmlspecialchars($tx['note']) . '</span>'; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-lg font-bold <?php echo $tx['type'] == 'expense' ? 'text-[var(--tertiary)]' : 'text-[var(--primary)]'; ?>">
                                    <?php echo $tx['type'] == 'expense' ? '-' : '+'; ?><?php echo format_currency($tx['amount']); ?>
                                </p>
                                <div class="flex gap-2">
                                    <a href="edit_transaction.php?id=<?php echo $tx['id']; ?>" class="text-gray-400 hover:text-[var(--primary)] transition-colors" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form method="POST" action="transactions.php" class="inline" onsubmit="return confirm('Delete this transaction?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tx_id" value="<?php echo $tx['id']; ?>">
                                        <button type="submit" class="text-gray-400 hover:text-[var(--tertiary)] transition-colors" title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple category filter for add form
    function filterCategories() {
        const type = document.querySelector('input[name="type"]:checked').value;
        const select = document.getElementById('category_select');
        for (let opt of select.options) {
            opt.style.display = opt.dataset.type === type ? '' : 'none';
        }
        select.selectedIndex = Array.from(select.options).findIndex(opt => opt.style.display !== 'none');
    }
    document.querySelectorAll('input[name="type"]').forEach(radio => radio.addEventListener('change', filterCategories));
    filterCategories();
</script>

<?php include 'inc/footer.php'; ?>