<?php
// setup_db.php — Run this to create/update tables safely.
// Uses IF NOT EXISTS and ALTER TABLE for migrations.
require_once 'inc/config.php';

echo "<pre style='font-family:monospace;padding:20px;'>\n";
echo "=== Wechiye Database Setup & Migration ===\n\n";

$success = 0;
$errors = 0;

function run_sql($pdo, $label, $sql) {
    global $success, $errors;
    try {
        $pdo->exec($sql);
        echo "✓ $label — OK\n";
        $success++;
    } catch (PDOException $e) {
        // Ignore duplicate column errors (already migrated)
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "⊘ $label — Already exists, skipped\n";
            $success++;
        } else {
            echo "✗ $label — ERROR: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

// Create tables
$tables = [
    'couple_link_requests' => "CREATE TABLE IF NOT EXISTS couple_link_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT UNSIGNED NOT NULL,
        receiver_id INT UNSIGNED NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY (sender_id, receiver_id)
    )",
    'couple_relationships' => "CREATE TABLE IF NOT EXISTS couple_relationships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user1_id INT UNSIGNED NOT NULL,
        user2_id INT UNSIGNED NOT NULL,
        linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    'couple_dates' => "CREATE TABLE IF NOT EXISTS couple_dates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inviter_id INT UNSIGNED NOT NULL,
        invitee_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        scheduled_date DATETIME NOT NULL,
        estimated_cost DECIMAL(12,2) NOT NULL,
        rsvp_status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    'alerts' => "CREATE TABLE IF NOT EXISTS alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        alert_type ENUM('general', 'link_request', 'date_rsvp') DEFAULT 'general',
        alert_meta JSON NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $name => $sql) {
    run_sql($pdo, "Table '$name'", $sql);
}

// Fix alerts.user_id column type to match users.id, then add foreign key
echo "\n--- Fixing alerts table foreign key ---\n";
try {
    // First, ensure user_id column matches users.id exactly (may need to modify)
    $pdo->exec("ALTER TABLE alerts MODIFY COLUMN user_id INT UNSIGNED NOT NULL");
    echo "  → Modified alerts.user_id to UNSIGNED INT\n";
    
    // Add the foreign key
    $pdo->exec("ALTER TABLE alerts ADD CONSTRAINT alerts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "  → Added foreign key constraint\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "  → Foreign key already exists, skipped\n";
    } else {
        echo "  ⚠ Could not add FK (non-critical): " . $e->getMessage() . "\n";
    }
}

// Migrations — add new columns to existing tables
echo "\n--- Running Migrations ---\n\n";

// Add username column
run_sql($pdo, "Add 'username' to users", "ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE AFTER full_name");

// Add alert_type column  
run_sql($pdo, "Add 'alert_type' to alerts", "ALTER TABLE alerts ADD COLUMN alert_type ENUM('general', 'link_request', 'date_rsvp') DEFAULT 'general' AFTER message");

// Add alert_meta column
run_sql($pdo, "Add 'alert_meta' to alerts", "ALTER TABLE alerts ADD COLUMN alert_meta JSON NULL AFTER alert_type");

// Fix account_type ENUM - first convert to VARCHAR to fix invalid values, then set proper ENUM
echo "\n--- Fixing account_type column ---\n";
try {
    // Step 1: Convert to VARCHAR to allow any value
    $pdo->exec("ALTER TABLE users MODIFY COLUMN account_type VARCHAR(20) NOT NULL DEFAULT 'personal'");
    echo "  → Converted account_type to VARCHAR\n";
    
    // Step 2: Fix invalid values
    $stmt = $pdo->query("UPDATE users SET account_type = 'personal' WHERE account_type NOT IN ('personal', 'couple') OR account_type IS NULL OR account_type = ''");
    $affected = $stmt->rowCount();
    echo "  → Fixed $affected users with invalid account_type\n";
    
    // Step 3: Set proper ENUM
    $pdo->exec("ALTER TABLE users MODIFY COLUMN account_type ENUM('personal','couple') NOT NULL DEFAULT 'personal'");
    echo "  → Set account_type to proper ENUM('personal','couple')\n";
} catch (PDOException $e) {
    echo "  ✗ Error fixing account_type: " . $e->getMessage() . "\n";
}

// Add avatar_type column
run_sql($pdo, "Add 'avatar_type' to users", "ALTER TABLE users ADD COLUMN avatar_type ENUM('upload','avatar') DEFAULT 'avatar' AFTER account_type");

// Add avatar_url column
run_sql($pdo, "Add 'avatar_url' to users", "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT 'default_1.svg' AFTER avatar_type");

// Add gender column
run_sql($pdo, "Add 'gender' to users", "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other') NULL AFTER avatar_url");

// Add occupation column
run_sql($pdo, "Add 'occupation' to users", "ALTER TABLE users ADD COLUMN occupation VARCHAR(255) NULL AFTER gender");

// Add education_level column
run_sql($pdo, "Add 'education_level' to users", "ALTER TABLE users ADD COLUMN education_level VARCHAR(255) NULL AFTER occupation");

// Add has_kids column
run_sql($pdo, "Add 'has_kids' to users", "ALTER TABLE users ADD COLUMN has_kids BOOLEAN DEFAULT FALSE AFTER education_level");

// Add kids_allowance_amount column
run_sql($pdo, "Add 'kids_allowance_amount' to users", "ALTER TABLE users ADD COLUMN kids_allowance_amount DECIMAL(12,2) DEFAULT 0.00 AFTER has_kids");

// Add kids_allowance_interval column
run_sql($pdo, "Add 'kids_allowance_interval' to users", "ALTER TABLE users ADD COLUMN kids_allowance_interval ENUM('weekly', 'monthly', 'none') DEFAULT 'none' AFTER kids_allowance_amount");

// Add is_onboarded column
run_sql($pdo, "Add 'is_onboarded' to users", "ALTER TABLE users ADD COLUMN is_onboarded BOOLEAN DEFAULT FALSE AFTER kids_allowance_interval");

// Fix couple table column types for foreign key compatibility
echo "\n--- Fixing couple table column types ---\n";
try {
    $pdo->exec("ALTER TABLE couple_link_requests MODIFY COLUMN sender_id INT UNSIGNED NOT NULL, MODIFY COLUMN receiver_id INT UNSIGNED NOT NULL");
    echo "  → Fixed couple_link_requests column types\n";
} catch (PDOException $e) {
    echo "  ⚠ couple_link_requests: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE couple_relationships MODIFY COLUMN user1_id INT UNSIGNED NOT NULL, MODIFY COLUMN user2_id INT UNSIGNED NOT NULL");
    echo "  → Fixed couple_relationships column types\n";
} catch (PDOException $e) {
    echo "  ⚠ couple_relationships: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE couple_dates MODIFY COLUMN inviter_id INT UNSIGNED NOT NULL, MODIFY COLUMN invitee_id INT UNSIGNED NOT NULL");
    echo "  → Fixed couple_dates column types\n";
} catch (PDOException $e) {
    echo "  ⚠ couple_dates: " . $e->getMessage() . "\n";
}

// Backfill any users without usernames
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE username IS NULL OR username = ''");
$users_without_username = $stmt->fetchAll();
if (!empty($users_without_username)) {
    echo "\n--- Backfilling usernames ---\n";
    foreach ($users_without_username as $u) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u['full_name']));
        if (empty($base)) $base = 'user';
        $username = $base . $u['id'];
        try {
            $stmtU = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmtU->execute([$username, $u['id']]);
            echo "  → User #{$u['id']} → @{$username}\n";
        } catch (PDOException $e) {
            echo "  ✗ User #{$u['id']} — ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n--- Done: $success OK, $errors errors ---\n";

if ($errors === 0) {
    echo "\n✅ All tables are ready. You can now use the application.\n";
} else {
    echo "\n⚠ Some operations had errors. Check above for details.\n";
}

echo "</pre>";
?>
