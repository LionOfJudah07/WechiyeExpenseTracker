<?php
// api/get_chart_data.php
require_once '../inc/config.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = get_current_user_id();
$user_ids = get_linked_user_id($pdo, $user_id);
$in_clause = str_repeat('?,', count($user_ids) - 1) . '?';

// Get spending data for last 7 days
$data = [];
$dates = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = $date;
    $data[$date] = 0;
}

$start_date = $dates[0];
$end_date = $dates[count($dates) - 1];

$stmt = $pdo->prepare("
    SELECT transaction_date, SUM(amount) as total 
    FROM transactions 
    WHERE user_id IN ($in_clause) AND type = 'expense' 
    AND transaction_date >= ? AND transaction_date <= ?
    GROUP BY transaction_date
");

$params = array_merge($user_ids, [$start_date, $end_date]);
$stmt->execute($params);

while ($row = $stmt->fetch()) {
    $data[$row['transaction_date']] = (float)$row['total'];
}

echo json_encode([
    'labels' => array_keys($data),
    'data' => array_values($data)
]);
?>
