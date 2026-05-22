<?php
// budgets.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login();
$user_id = get_current_user_id();
$user_ids = get_linked_user_id($pdo, $user_id);
$in_clause = str_repeat('?,', count($user_ids) - 1) . '?';
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

function _t($key, $fallback = '')
{
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : ($fallback ?: $key);
}

// Helper: render a single budget row HTML
function renderBudgetRow($budget, $spent, $amount_limit, $category_name, $category_id, $lang)
{
    $percent = min(100, max(0, ($spent / $amount_limit) * 100));
    $is_over = $percent >= 100;
    $is_warning = $percent >= 80 && $percent < 100;
    $bar_color = $is_over ? 'bg-[var(--tertiary)]' : 'bg-[var(--primary)]';

    $status_badge = '';
    if ($is_over) {
        $status_badge = '<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[var(--tertiary)] bg-opacity-10 text-[var(--tertiary)]">' . _t('exceeded', 'Exceeded') . '</span>';
    } elseif ($is_warning) {
        $status_badge = '<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[var(--secondary)] bg-opacity-10 text-[var(--secondary)]">' . _t('near_limit', 'Near Limit') . '</span>';
    }

    $spent_formatted = format_currency($spent);
    $limit_formatted = format_currency($amount_limit);
    $diff_formatted = format_currency($is_over ? $spent - $amount_limit : $amount_limit - $spent);
    $diff_text = $is_over ? _t('over_budget', 'over budget') : _t('remaining', 'remaining');

    $spent_color = $is_over ? 'text-[var(--tertiary)]' : 'text-[var(--on-surface)]';

    return <<<HTML
    <div class="budget-item group p-4 rounded-xl bg-[var(--surface-container-low)] hover:bg-[var(--surface-container-highest)] transition-all duration-200" data-category-id="{$category_id}" data-limit="{$amount_limit}" data-spent="{$spent}">
        <div class="flex justify-between items-start mb-2">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-lg text-[var(--on-surface)]">{$category_name}</span>
                {$status_badge}
            </div>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="edit-budget-btn p-1 text-gray-400 hover:text-[var(--primary)] transition-colors" data-category-id="{$category_id}" data-limit="{$amount_limit}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button class="delete-budget-btn p-1 text-gray-400 hover:text-[var(--tertiary)] transition-colors" data-category-id="{$category_id}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        <div class="flex justify-between items-baseline mb-1 text-sm">
            <span class="text-gray-500">Spent</span>
            <div>
                <span class="font-bold {$spent_color}">{$spent_formatted}</span>
                <span class="text-gray-500 mx-1">/</span>
                <span class="text-gray-500">{$limit_formatted}</span>
            </div>
        </div>
        <div class="budget-bar-container h-2 rounded-full bg-[var(--surface-container-highest)] overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 ease-out {$bar_color}" style="width: {$percent}%"></div>
        </div>
        <p class="text-xs text-gray-500 mt-2 text-right">{$diff_formatted} {$diff_text}</p>
    </div>
HTML;
}

// Fetch expense categories
$stmtCat = $pdo->prepare("SELECT id, name FROM categories WHERE (user_id IS NULL OR user_id = ?) AND type = 'expense' ORDER BY name");
$stmtCat->execute([$user_id]);
$expense_categories = $stmtCat->fetchAll();

// Fetch existing budgets
$stmt = $pdo->prepare("
    SELECT b.id as budget_id, b.amount_limit, b.category_id, c.name,
           COALESCE(SUM(t.amount), 0) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id IN ($in_clause) 
        AND t.transaction_date BETWEEN ? AND ?
    WHERE b.user_id IN ($in_clause) AND b.month_year = ?
    GROUP BY b.id
");
$params = array_merge($user_ids, [$month_start, $month_end], $user_ids, [$month_start]);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

// -------------------------------------------------------------------
// AJAX handlers
// -------------------------------------------------------------------
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');

    // GET: fetch budgets list or chart data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['fetch_budgets'])) {
            $stmt = $pdo->prepare("
                SELECT b.id as budget_id, b.amount_limit, b.category_id, c.name,
                       COALESCE(SUM(t.amount), 0) as spent
                FROM budgets b
                JOIN categories c ON b.category_id = c.id
                LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id IN ($in_clause) 
                    AND t.transaction_date BETWEEN ? AND ?
                WHERE b.user_id IN ($in_clause) AND b.month_year = ?
                GROUP BY b.id
            ");
            $params = array_merge($user_ids, [$month_start, $month_end], $user_ids, [$month_start]);
            $stmt->execute($params);
            $budgets = $stmt->fetchAll();
            $html = '';
            foreach ($budgets as $b) {
                $catLabel = _t(strtolower($b['name']), htmlspecialchars($b['name']));
                $html .= renderBudgetRow($b, $b['spent'], $b['amount_limit'], $catLabel, $b['category_id'], $lang);
            }
            if (empty($html)) $html = '<p class="text-gray-500 text-center py-8">' . _t('no_active_budgets', 'No active budgets for this month.') . '</p>';
            echo json_encode(['success' => true, 'html' => $html]);
            exit;
        }

        if (isset($_GET['get_chart'])) {
            $stmt = $pdo->prepare("
                SELECT c.name, b.amount_limit as budget, COALESCE(SUM(t.amount), 0) as spent
                FROM budgets b
                JOIN categories c ON b.category_id = c.id
                LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id IN ($in_clause) 
                    AND t.transaction_date BETWEEN ? AND ?
                WHERE b.user_id IN ($in_clause) AND b.month_year = ?
                GROUP BY b.id
                ORDER BY c.name
            ");
            $params = array_merge($user_ids, [$month_start, $month_end], $user_ids, [$month_start]);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $labels = [];
            $budgetsArr = [];
            $spentArr = [];
            foreach ($rows as $r) {
                $labels[] = _t(strtolower($r['name']), $r['name']);
                $budgetsArr[] = (float)$r['budget'];
                $spentArr[] = (float)$r['spent'];
            }
            echo json_encode(['success' => true, 'chartData' => ['labels' => $labels, 'budgets' => $budgetsArr, 'spent' => $spentArr]]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // POST: set, edit, delete budget
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        if ($_POST['action'] === 'set_budget') {
            $category_id = (int)$_POST['category_id'];
            $amount_limit = (float)$_POST['amount_limit'];

            if ($amount_limit <= 0) {
                echo json_encode(['success' => false, 'message' => _t('invalid_amount', 'Amount must be greater than 0')]);
                exit;
            }

            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND month_year = ?");
            $stmt->execute([$user_id, $category_id, $month_start]);
            if ($row = $stmt->fetch()) {
                $stmtU = $pdo->prepare("UPDATE budgets SET amount_limit = ? WHERE id = ?");
                $stmtU->execute([$amount_limit, $row['id']]);
            } else {
                $stmtI = $pdo->prepare("INSERT INTO budgets (user_id, category_id, month_year, amount_limit) VALUES (?, ?, ?, ?)");
                $stmtI->execute([$user_id, $category_id, $month_start, $amount_limit]);
            }

            // Get spent amount and category name
            $stmtSpent = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as spent, c.name
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE t.user_id IN ($in_clause) AND t.category_id = ? AND t.transaction_date BETWEEN ? AND ?
            ");
            $stmtSpent->execute(array_merge($user_ids, [$category_id, $month_start, $month_end]));
            $spentData = $stmtSpent->fetch();
            $spent = $spentData['spent'] ?? 0;
            $catName = $spentData['name'] ?? '';
            if (!$catName) {
                $stmtCatName = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                $stmtCatName->execute([$category_id]);
                $catName = $stmtCatName->fetchColumn();
            }
            $catLabel = _t(strtolower($catName), $catName);
            $rowHtml = renderBudgetRow((object)[], $spent, $amount_limit, $catLabel, $category_id, $lang);

            echo json_encode([
                'success' => true,
                'message' => _t('budget_saved', 'Budget saved'),
                'row_html' => $rowHtml,
                'category_id' => $category_id
            ]);
            exit;
        }

        if ($_POST['action'] === 'delete_budget') {
            $category_id = (int)$_POST['category_id'];
            $stmt = $pdo->prepare("DELETE FROM budgets WHERE user_id = ? AND category_id = ? AND month_year = ?");
            $stmt->execute([$user_id, $category_id, $month_start]);
            echo json_encode([
                'success' => true,
                'message' => _t('budget_deleted', 'Budget deleted'),
                'category_id' => $category_id
            ]);
            exit;
        }

        if ($_POST['action'] === 'schedule_date' && count($user_ids) > 1) {
            $partner_id = ($user_ids[0] == $user_id) ? $user_ids[1] : $user_ids[0];
            $title = trim($_POST['date_title']);
            $date_time = $_POST['date_time'];
            $cost = (float)$_POST['estimated_cost'];
            if (empty($title) || empty($date_time)) {
                echo json_encode(['success' => false, 'message' => _t('invalid_date', 'Please fill all fields')]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO couple_dates (inviter_id, invitee_id, title, scheduled_date, estimated_cost) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $partner_id, $title, $date_time, $cost]);
            $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type, alert_meta) VALUES (?, ?, 'date_rsvp', ?)");
            $stmtA->execute([$partner_id, _t('date_invite', 'New Surprise Date Invite: {title}', ['title' => $title]), json_encode(['title' => $title, 'date' => $date_time, 'cost' => $cost])]);
            echo json_encode(['success' => true, 'message' => _t('date_sent', 'Invitation sent!')]);
            exit;
        }
    }
    exit;
}

include 'inc/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Set Budget Form -->
    <div class="lg:col-span-1">
        <div class="card bg-gradient-to-br from-[var(--surface-container-lowest)] to-[var(--surface-container-low)]">
            <h3 class="text-xl font-bold mb-6 font-display"><?php echo _t('set_monthly_budget', 'Set Monthly Budget'); ?></h3>
            <form id="setBudgetForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="set_budget">
                <div>
                    <select name="category_id" id="budget_category" required class="input-field">
                        <option value="" disabled selected><?php echo _t('select_category', 'Select Category'); ?></option>
                        <?php foreach ($expense_categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo _t(strtolower($c['name']), htmlspecialchars($c['name'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span class="text-gray-500 sm:text-sm">$</span></div>
                        <input type="number" step="0.01" name="amount_limit" id="budget_limit" required class="input-field pl-8" placeholder="<?php echo _t('budget_limit', 'Budget Limit'); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full"><?php echo _t('save', 'Save'); ?></button>
            </form>
        </div>

        <?php if (count($user_ids) > 1): ?>
            <div class="card mt-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-6 h-6 text-[var(--tertiary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <h3 class="text-xl font-bold font-display"><?php echo _t('surprise_date', 'Surprise Date'); ?></h3>
                </div>
                <form id="scheduleDateForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="schedule_date">
                    <input type="text" name="date_title" required placeholder="<?php echo _t('date_title', 'Date Title / Idea'); ?>" class="input-field">
                    <input type="datetime-local" name="date_time" required class="input-field">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span class="text-gray-500 sm:text-sm">$</span></div>
                        <input type="number" step="0.01" name="estimated_cost" required class="input-field pl-8" placeholder="<?php echo _t('estimated_cost', 'Estimated Cost'); ?>">
                    </div>
                    <button type="submit" class="btn-primary w-full"><?php echo _t('send_invite', 'Send Invite'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Budgets List -->
    <div class="lg:col-span-2">
        <div class="flex justify-between items-end mb-6 flex-wrap gap-2">
            <h2 class="text-3xl font-bold font-display"><?php echo _t('budgets', 'Budgets'); ?> – <?php echo date('F Y'); ?></h2>
            <button id="refreshBudgetsBtn" class="btn-secondary text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <?php echo _t('refresh', 'Refresh'); ?>
            </button>
        </div>

        <!-- Chart -->
        <div class="card p-4 mb-6">
            <h4 class="font-semibold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <?php echo _t('spending_vs_budget', 'Spending vs Budget'); ?>
            </h4>
            <div id="budgetChartContainer" class="h-64"><canvas id="budgetChart"></canvas></div>
        </div>

        <!-- Budgets List -->
        <div class="card p-4 sm:p-6 shadow-sm border-0">
            <div id="budgetsList" class="space-y-3">
                <?php if (empty($budgets)): ?>
                    <p class="text-gray-500 text-center py-8"><?php echo _t('no_active_budgets', 'No active budgets for this month.'); ?></p>
                <?php else: ?>
                    <?php foreach ($budgets as $b):
                        $catLabel = _t(strtolower($b['name']), htmlspecialchars($b['name']));
                        echo renderBudgetRow($b, $b['spent'], $b['amount_limit'], $catLabel, $b['category_id'], $lang);
                    endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Budget Modal -->
<div id="editBudgetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity">
    <div class="card max-w-md w-full mx-4 p-6 transform transition-all scale-95 opacity-0" id="editBudgetModalContent">
        <h3 class="text-xl font-bold mb-4"><?php echo _t('edit_budget', 'Edit Budget'); ?></h3>
        <form id="editBudgetForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="set_budget">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div>
                <label class="block text-sm font-semibold mb-1"><?php echo _t('amount_limit', 'Amount Limit'); ?></label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span class="text-gray-500 sm:text-sm">$</span></div>
                    <input type="number" step="0.01" name="amount_limit" id="edit_amount_limit" required class="input-field pl-8">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary flex-1"><?php echo _t('save', 'Save'); ?></button>
                <button type="button" id="closeEditModalBtn" class="btn-secondary flex-1"><?php echo _t('cancel', 'Cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white text-sm ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} transform translate-x-full transition-all duration-300`;
        toast.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}"/></svg><span>${message}</span>`;
        document.getElementById('toastContainer').appendChild(toast);
        setTimeout(() => toast.classList.remove('translate-x-full'), 10);
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    let budgetChart = null;

    function updateChart(chartData) {
        const ctx = document.getElementById('budgetChart').getContext('2d');
        if (budgetChart) budgetChart.destroy();
        if (!chartData || chartData.labels.length === 0) {
            ctx.canvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><?php echo _t("no_budget_data", "No budget data for this month"); ?></div>';
            return;
        }
        budgetChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                        label: '<?php echo _t("budget", "Budget"); ?>',
                        data: chartData.budgets,
                        backgroundColor: 'rgba(13, 99, 27, 0.7)',
                        borderRadius: 8
                    },
                    {
                        label: '<?php echo _t("spent", "Spent"); ?>',
                        data: chartData.spent,
                        backgroundColor: 'rgba(171, 17, 24, 0.7)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: $${ctx.raw.toFixed(2)}`
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (val) => '$' + val
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    async function loadChartData() {
        try {
            const res = await fetch('budgets.php?get_chart=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.success) updateChart(data.chartData);
        } catch (err) {
            console.error('Chart load error:', err);
            showToast('Failed to load chart', 'error');
        }
    }

    // Set budget form
    document.getElementById('setBudgetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            const res = await fetch('budgets.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                const existingRow = document.querySelector(`.budget-item[data-category-id="${data.category_id}"]`);
                if (existingRow) existingRow.outerHTML = data.row_html;
                else {
                    const container = document.getElementById('budgetsList');
                    if (container.children.length === 1 && container.children[0].tagName === 'P') container.innerHTML = '';
                    container.insertAdjacentHTML('beforeend', data.row_html);
                }
                await loadChartData();
                e.target.reset();
            } else showToast(data.message, 'error');
        } catch (err) {
            console.error(err);
            showToast('Network error. Check console.', 'error');
        }
    });

    // Edit & Delete handlers
    const editModal = document.getElementById('editBudgetModal');
    const editModalContent = document.getElementById('editBudgetModalContent');
    document.getElementById('budgetsList').addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-budget-btn');
        const deleteBtn = e.target.closest('.delete-budget-btn');
        if (editBtn) {
            document.getElementById('edit_category_id').value = editBtn.dataset.categoryId;
            document.getElementById('edit_amount_limit').value = editBtn.dataset.limit;
            editModal.classList.remove('hidden');
            setTimeout(() => editModalContent.classList.remove('scale-95', 'opacity-0'), 10);
        }
        if (deleteBtn && confirm('<?php echo addslashes(_t("confirm_delete_budget", "Delete this budget?")); ?>')) {
            const categoryId = deleteBtn.dataset.categoryId;
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'delete_budget');
            formData.append('category_id', categoryId);
            try {
                const res = await fetch('budgets.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    const row = document.querySelector(`.budget-item[data-category-id="${categoryId}"]`);
                    if (row) row.remove();
                    if (document.getElementById('budgetsList').children.length === 0) document.getElementById('budgetsList').innerHTML = '<p class="text-gray-500 text-center py-8"><?php echo _t("no_active_budgets", "No active budgets for this month."); ?></p>';
                    loadChartData();
                } else showToast(data.message, 'error');
            } catch (err) {
                showToast('Network error', 'error');
            }
        }
    });

    document.getElementById('editBudgetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            const res = await fetch('budgets.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                const row = document.querySelector(`.budget-item[data-category-id="${data.category_id}"]`);
                if (row) row.outerHTML = data.row_html;
                await loadChartData();
                document.getElementById('closeEditModalBtn').click();
            } else showToast(data.message, 'error');
        } catch (err) {
            showToast('Network error', 'error');
        }
    });

    document.getElementById('closeEditModalBtn').addEventListener('click', () => {
        editModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => editModal.classList.add('hidden'), 300);
    });
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => editModal.classList.add('hidden'), 300);
        }
    });

    // Surprise date form
    const dateForm = document.getElementById('scheduleDateForm');
    if (dateForm) {
        dateForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch('budgets.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    dateForm.reset();
                } else showToast(data.message, 'error');
            } catch (err) {
                showToast('Network error', 'error');
            }
        });
    }

    // Refresh button
    document.getElementById('refreshBudgetsBtn').addEventListener('click', async () => {
        await loadChartData();
        try {
            const res = await fetch('budgets.php?fetch_budgets=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.success) document.getElementById('budgetsList').innerHTML = data.html;
        } catch (err) {
            showToast('Refresh failed', 'error');
        }
    });

    loadChartData();
</script>

<?php include 'inc/footer.php'; ?>