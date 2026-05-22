<?php
// inc/functions.php
require_once 'config.php';
require_once 'auth.php';

function get_language_strings() {
    $lang = $_SESSION['lang'] ?? 'en';
    if (!in_array($lang, ['en', 'am', 'or'])) {
        $lang = 'en';
    }
    return require __DIR__ . "/lang/{$lang}.php";
}

function format_currency($amount) {
    return '$' . number_format($amount, 2); // Simplistic currency formatting
}

function generate_otp() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function get_insights_and_recommendations($pdo, $user_ids) {
    if (empty($user_ids)) return [];
    
    $lang = get_language_strings();
    $in_clause = str_repeat('?,', count($user_ids) - 1) . '?';
    $insights = [];
    
    $month_start = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT b.amount_limit, c.name, COALESCE(SUM(t.amount), 0) as spent
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id IN ($in_clause) 
            AND t.transaction_date >= ? AND t.transaction_date <= LAST_DAY(?)
        WHERE b.user_id IN ($in_clause) AND b.month_year = ?
        GROUP BY b.id
    ");
    $params = array_merge($user_ids, [$month_start, $month_start], $user_ids, [$month_start]);
    $stmt->execute($params);
    $budgets = $stmt->fetchAll();
    
    foreach ($budgets as $b) {
        if ($b['spent'] > $b['amount_limit'] * 1.2) {
            $msg = $lang['insight_over_budget'] ?? "You spent considerably over budget on %s.";
            $insights[] = sprintf($msg, htmlspecialchars($b['name']));
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT type, SUM(amount) as total
        FROM transactions
        WHERE user_id IN ($in_clause) 
        AND transaction_date >= DATE_SUB(?, INTERVAL 3 MONTH)
        AND transaction_date < ?
        GROUP BY type
    ");
    $params2 = array_merge($user_ids, [$month_start, $month_start]);
    $stmt->execute($params2);
    $past_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $past_expenses = $past_totals['expense'] ?? 0;
    $past_income = $past_totals['income'] ?? 0;
    
    if ($past_expenses > 0) {
        $avg_exp = $past_expenses / 3;
        $msg = $lang['insight_prediction'] ?? "Predicted next month spending based on moving average: %s";
        $insights[] = sprintf($msg, format_currency($avg_exp));
    } else {
        $insights[] = $lang['not_enough_data'] ?? "Not enough data for spending prediction yet.";
    }

    if ($past_income > 0) {
        $avg_inc = $past_income / 3;
        $target_savings = $avg_inc * 0.20; // Aim to save 20%
        $msg = $lang['insight_save'] ?? "Try to save 20%% of your average income: %s";
        $insights[] = sprintf($msg, format_currency($target_savings));
    }
    
    // Career & Education Intelligence
    $stmtUser = $pdo->prepare("SELECT has_kids, education_level, occupation, kids_allowance_amount, kids_allowance_interval FROM users WHERE id IN ($in_clause) LIMIT 1");
    $stmtUser->execute($user_ids);
    $active_user = $stmtUser->fetch();
    
    if ($active_user) {
        // Career Insights based on Ethiopian Industry Context
        if ($active_user['education_level'] === 'tvet') {
            $insights[] = $lang['insight_career_tvet'] ?? "Career Insight: Your TVET background is highly valuable in Ethiopia's tech sectors.";
        } elseif ($active_user['education_level'] === 'bachelors' || $active_user['education_level'] === 'masters') {
            $insights[] = $lang['insight_career_bachelors'] ?? "Career Insight: With a degree, focus on mid-level management to boost income.";
        }
        
        if ($active_user['occupation'] === 'employed' || $active_user['occupation'] === 'self_employed') {
            $insights[] = $lang['insight_career_employed'] ?? "Career Insight: Allocate 10% of your income towards upskilling.";
        } elseif ($active_user['occupation'] === 'student' || $active_user['occupation'] === 'unemployed') {
            $insights[] = $lang['insight_career_unemployed_student'] ?? "Career Insight: Utilize free digital resources (ALX, etc) to build highly employable tech skills.";
        }
        
        // Kids Allowance Tip
        if (!empty($active_user['has_kids']) && $active_user['kids_allowance_amount'] > 0) {
            $freq = $active_user['kids_allowance_interval'] === 'weekly' ? ($lang['weekly'] ?? 'weekly') : ($lang['monthly'] ?? 'monthly');
            $msg = $lang['insight_kids_allowance_tip'] ?? "Kids Tip: Consider assigning chores to associate their %s allowance with responsibility!";
            $insights[] = sprintf($msg, $freq);
        }
    }

    if (empty($insights)) {
        $insights[] = $lang['insight_good'] ?? "Looking good! Keep tracking your expenses.";
    }
    
    return $insights;
}
?>
