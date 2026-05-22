<?php
// dashboard.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login();
$user_id = get_current_user_id();
$user_ids = get_linked_user_id($pdo, $user_id);
$is_coupled = count($user_ids) > 1; // True if successfully linked 

// Handle Date RSVP or Date Scheduling forms
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($_POST['action'] === 'schedule_date' && $is_coupled) {
            $partner_id = ($user_ids[0] == $user_id) ? $user_ids[1] : $user_ids[0];
            $title = trim($_POST['date_title']);
            $date_time = $_POST['date_time'];
            $cost = (float)$_POST['estimated_cost'];
            
            $stmt = $pdo->prepare("INSERT INTO couple_dates (inviter_id, invitee_id, title, scheduled_date, estimated_cost) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $partner_id, $title, $date_time, $cost]);
            
            // Send Alert!
            $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message) VALUES (?, ?)");
            $stmtA->execute([$partner_id, "New Surprise Date Invite: {$title}! Go to dashboard to RSVP."]);
            
            header("Location: dashboard.php");
            exit;
        } elseif ($_POST['action'] === 'rsvp_date') {
            $date_id = $_POST['date_id'];
            $status = $_POST['rsvp_status']; // accepted or declined
            
            // Check auth
            $stmt = $pdo->prepare("SELECT * FROM couple_dates WHERE id = ? AND invitee_id = ?");
            $stmt->execute([$date_id, $user_id]);
            if ($date_row = $stmt->fetch()) {
                $stmtU = $pdo->prepare("UPDATE couple_dates SET rsvp_status = ? WHERE id = ?");
                $stmtU->execute([$status, $date_id]);
                
                // If accepted, auto-create expense transaction for the inviter!
                if ($status === 'accepted') {
                    // Try to find a primary bank account of inviter
                    $stmtB = $pdo->prepare("SELECT id FROM bank_accounts WHERE user_id = ? LIMIT 1");
                    $stmtB->execute([$date_row['inviter_id']]);
                    if ($b_row = $stmtB->fetch()) {
                        // find/create 'Entertainment' category
                        $stmtC = $pdo->prepare("SELECT id FROM categories WHERE name = 'Entertainment' LIMIT 1");
                        $stmtC->execute();
                        $c_id = $stmtC->fetchColumn();
                        
                        $stmtT = $pdo->prepare("INSERT INTO transactions (user_id, bank_account_id, amount, category_id, type, transaction_date, note) VALUES (?, ?, ?, ?, 'expense', ?, ?)");
                        $stmtT->execute([$date_row['inviter_id'], $b_row['id'], $date_row['estimated_cost'], $c_id, date('Y-m-d', strtotime($date_row['scheduled_date'])), "Date: {$date_row['title']}"]);
                    }
                    
                    // Alert the Inviter
                    $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message) VALUES (?, ?)");
                    $stmtA->execute([$date_row['inviter_id'], "Your partner accepted the date: {$date_row['title']}! Transaction auto-logged."]);
                } else {
                    $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message) VALUES (?, ?)");
                    $stmtA->execute([$date_row['inviter_id'], "Your partner declined the date: {$date_row['title']}. Make another plan!"]);
                }
            }
            header("Location: dashboard.php");
            exit;
        }
    }
}
$user_ids = get_linked_user_id($pdo, $user_id);
$in_clause = str_repeat('?,', count($user_ids) - 1) . '?';

// Fetch Totals and Bank Accounts summaries
$stmtBanks = $pdo->prepare("SELECT id, name, initial_balance FROM bank_accounts WHERE user_id IN ($in_clause)");
$stmtBanks->execute($user_ids);
$banks = $stmtBanks->fetchAll(PDO::FETCH_ASSOC);

// Map bank info
$bank_balances = [];
$total_balance = 0;
foreach($banks as $b) {
    $bank_balances[$b['id']] = [
        'name' => $b['name'],
        'balance' => (float)$b['initial_balance']
    ];
    $total_balance += (float)$b['initial_balance'];
}

$stmt = $pdo->prepare("SELECT type, bank_account_id, SUM(amount) as total FROM transactions WHERE user_id IN ($in_clause) GROUP BY type, bank_account_id");
$stmt->execute($user_ids);
$totals = ['income' => 0, 'expense' => 0];

while($row = $stmt->fetch()) {
    $totals[$row['type']] += $row['total'];
    if (isset($bank_balances[$row['bank_account_id']])) {
        if ($row['type'] == 'income') {
            $bank_balances[$row['bank_account_id']]['balance'] += $row['total'];
        } else {
            $bank_balances[$row['bank_account_id']]['balance'] -= $row['total'];
        }
    }
}
$total_balance += $totals['income'] - $totals['expense'];

// Fetch Recent Transactions
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id IN ($in_clause) 
    ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 5
");
$stmt->execute($user_ids);
$recent_transactions = $stmt->fetchAll();

// Fetch Budget Progress (Top 3)
$month_start = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT b.amount_limit, c.name, COALESCE(SUM(t.amount), 0) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id IN ($in_clause) 
        AND t.transaction_date >= ? AND t.transaction_date <= LAST_DAY(?)
    WHERE b.user_id IN ($in_clause) AND b.month_year = ?
    GROUP BY b.id
    LIMIT 3
");
$params = array_merge($user_ids, [$month_start, $month_start], $user_ids, [$month_start]);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

// Fetch Pending Dates for logged user
$pending_dates = [];
if ($is_coupled) {
    $stmtD = $pdo->prepare("SELECT * FROM couple_dates WHERE invitee_id = ? AND rsvp_status = 'pending'");
    $stmtD->execute([$user_id]);
    $pending_dates = $stmtD->fetchAll();
}

$insights = get_insights_and_recommendations($pdo, $user_ids);

include 'inc/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Balances & Trend -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Balance Card -->
        <div class="card-premium flex flex-col justify-between" style="background: var(--surface-container-highest);">
            <div class="mb-4">
                <p class="text-sm font-semibold text-[var(--on-surface)] uppercase tracking-wider"><?php echo $lang['total_balance']; ?></p>
                <h2 class="text-5xl font-bold mt-2 display-lg" style="color: var(--on-surface);">
                    <?php echo format_currency($total_balance); ?>
                </h2>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4 border-t border-[var(--outline-variant)] pt-4">
                <?php foreach($bank_balances as $bb): ?>
                <div>
                    <p class="text-xs text-[var(--on-surface)] font-bold truncate"><?php echo htmlspecialchars($bb['name']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo format_currency($bb['balance']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-8">
                <div>
                    <p class="text-xs text-[var(--on-surface)]"><?php echo $lang['income']; ?></p>
                    <p class="text-md font-semibold text-[var(--primary)]">+<?php echo format_currency($totals['income']); ?></p>
                </div>
                <div>
                    <p class="text-xs text-[var(--on-surface)]"><?php echo $lang['expenses']; ?></p>
                    <p class="text-md font-semibold text-[var(--tertiary)]">-<?php echo format_currency($totals['expense']); ?></p>
                </div>
            </div>
        </div>

        <div class="heritage-divider"></div>

        <!-- Trend Chart -->
        <div>
            <h3 class="text-xl font-bold mb-4 font-display"><?php echo $lang['spending_trend']; ?></h3>
            <div class="card p-4 h-64 border-0">
                <!-- Using vanilla JS Canvas -->
                <canvas id="trendChart" class="w-full h-full"></canvas>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div>
            <div class="flex justify-between items-end mb-4">
                <h3 class="text-xl font-bold font-display"><?php echo $lang['recent_transactions']; ?></h3>
                <a href="transactions.php" class="text-sm font-semibold text-[var(--primary)] hover:underline"><?php echo $lang['view_all']; ?></a>
            </div>
            
            <div class="space-y-3">
                <?php if (empty($recent_transactions)): ?>
                    <p class="text-gray-500 text-sm">No recent transactions.</p>
                <?php endif; ?>
                <?php foreach ($recent_transactions as $tx): ?>
                <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-[var(--surface-container-highest)] transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="tx-icon">
                            <span class="text-xl font-bold"><?php echo substr($tx['category_name'], 0, 1); ?></span>
                        </div>
                        <div>
                            <p class="font-semibold text-[var(--on-surface)]"><?php echo $lang[strtolower($tx['category_name'])] ?? htmlspecialchars($tx['category_name']); ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <?php echo date('M d, Y', strtotime($tx['transaction_date'])); ?>
                                <?php if(!empty($tx['note'])) echo ' • <span class="italic truncate max-w-[100px] sm:max-w-xs inline-block align-bottom">'.htmlspecialchars($tx['note']).'</span>'; ?>
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="text-lg font-bold <?php echo $tx['type'] == 'expense' ? 'text-[var(--tertiary)]' : 'text-[var(--primary)]'; ?>">
                            <?php echo $tx['type'] == 'expense' ? '-' : '+'; ?><?php echo format_currency($tx['amount']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Right Column: Budgets & Insights -->
    <div class="space-y-8">
        
        <!-- Quick Action Add Button -->
        <a href="transactions.php?action=add" class="btn-primary w-full shadow-ambient py-4 rounded-2xl flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <?php echo $lang['add_transaction']; ?>
        </a>

        <!-- OTP Alert if couple -->
        <?php if(isset($_SESSION['flash_otp'])): ?>
        <div class="bg-[var(--secondary-container)] p-4 rounded-xl shadow-ambient">
            <p class="text-sm text-[var(--secondary)] font-semibold mb-2"><?php echo $lang['partner_linking_otp']; ?></p>
            <p class="text-2xl font-bold tracking-widest text-center my-2"><?php echo $_SESSION['flash_otp']; ?></p>
            <p class="text-xs text-[var(--secondary)]"><?php echo $lang['share_this_code']; ?></p>
        </div>
        <?php unset($_SESSION['flash_otp']); endif; ?>

        <!-- Budgets -->
        <div class="card p-6">
            <h3 class="text-lg font-bold mb-5 font-display"><?php echo $lang['budget_progress']; ?></h3>
            <?php if (empty($budgets)): ?>
                <p class="text-sm text-gray-500"><?php echo $lang['no_active_budgets']; ?></p>
            <?php endif; ?>
            <div class="space-y-6">
                <?php foreach($budgets as $b): 
                    $percent = min(100, max(0, ($b['spent'] / $b['amount_limit']) * 100));
                ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-semibold"><?php echo htmlspecialchars($b['name']); ?></span>
                        <span class="text-gray-500"><?php echo format_currency($b['spent']); ?> / <?php echo format_currency($b['amount_limit']); ?></span>
                    </div>
                    <div class="budget-bar-container">
                        <div class="budget-bar-fill <?php echo $percent >= 100 ? 'bg-[var(--tertiary)]' : ''; ?>" style="width: <?php echo $percent; ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="budgets.php" class="block text-center text-sm font-semibold text-[var(--primary)] mt-6 hover:underline"><?php echo $lang['manage_budgets']; ?></a>
        </div>

        <!-- AI Insights -->
        <div class="card p-6 bg-gradient-to-br from-[var(--surface-container-lowest)] to-[var(--surface-container-low)]">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-[var(--secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <h3 class="text-lg font-bold font-display"><?php echo $lang['insights']; ?></h3>
            </div>
            <ul class="space-y-3">
                <?php foreach($insights as $insight): ?>
                <li class="p-3 rounded-lg bg-[var(--surface-container-lowest)] text-sm shadow-sm border-l-2 border-[var(--primary)]">
                    <?php echo htmlspecialchars($insight); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Surprise Date Scheduling View -->
        <?php if ($is_coupled): ?>
        <div class="card p-6 bg-[var(--surface-container-low)]">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-6 h-6 text-[var(--tertiary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <h3 class="text-lg font-bold font-display"><?php echo $lang['surprise_date']; ?></h3>
            </div>
            
            <?php if (!empty($pending_dates)): ?>
                <div class="mb-4">
                    <h4 class="font-bold text-sm mb-2 text-[var(--tertiary)]"><?php echo $lang['pending_invites']; ?></h4>
                    <?php foreach ($pending_dates as $pd): ?>
                    <div class="p-3 bg-white rounded-xl shadow-sm mb-2">
                        <p class="font-bold"><?php echo htmlspecialchars($pd['title']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($pd['scheduled_date'])); ?> | <?php echo format_currency($pd['estimated_cost']); ?></p>
                        <div class="flex gap-2 mt-3">
                            <form method="POST" action="dashboard.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="rsvp_date">
                                <input type="hidden" name="date_id" value="<?php echo $pd['id']; ?>">
                                <input type="hidden" name="rsvp_status" value="accepted">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--primary)] text-white text-xs font-bold rounded-lg"><?php echo $lang['accept']; ?></button>
                            </form>
                            <form method="POST" action="dashboard.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="rsvp_date">
                                <input type="hidden" name="date_id" value="<?php echo $pd['id']; ?>">
                                <input type="hidden" name="rsvp_status" value="declined">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--surface-container-highest)] text-[var(--on-surface)] text-xs font-bold rounded-lg"><?php echo $lang['decline']; ?></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="heritage-divider"></div>
            <?php endif; ?>

            <form method="POST" action="dashboard.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="schedule_date">
                
                <div>
                    <input type="text" name="date_title" required placeholder="<?php echo $lang['date_title']; ?>" class="input-field py-2 text-sm">
                </div>
                <div>
                    <input type="datetime-local" name="date_time" required class="input-field py-2 text-sm text-gray-500">
                </div>
                <div>
                    <input type="number" step="0.01" name="estimated_cost" required placeholder="<?php echo $lang['estimated_cost']; ?>" class="input-field py-2 text-sm">
                </div>
                <button type="submit" class="btn-primary w-full text-xs py-3"><?php echo $lang['send_invite']; ?></button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'inc/footer.php'; ?>
