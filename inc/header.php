<?php
// inc/header.php
require_once 'config.php';
require_once 'auth.php';
require_once 'functions.php';

$lang = get_language_strings();
$current_lang = $_SESSION['lang'] ?? 'en';

$unread_alerts = [];
if (is_logged_in()) {
    global $pdo;
    $uid = get_current_user_id();
    $stmtAl = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 20");
    $stmtAl->execute([$uid]);
    $unread_alerts = $stmtAl->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['app_name']; ?></title>
    <!-- Tailwind via CDN for no-build setup, customized for design system -->
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
            --secondary-container: #ffe082;
          }
          body {
            @apply bg-[var(--surface)] text-[var(--on-surface)] antialiased;
            font-family: 'Inter', sans-serif;
          }
          h1, h2, h3, h4, h5, h6 { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.025em; }
        }
        @layer components {
          .card {
            background-color: var(--surface-container-lowest);
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.04);
            border: 0;
          }
          .card-premium {
            @apply card relative overflow-hidden;
          }
          .card-premium::after {
            content: ""; position: absolute; top: -20%; right: -20%; width: 200px; height: 200px; opacity: 0.03;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 0 L100 50 L50 100 L0 50 Z" fill="black"/></svg>');
            background-size: 50px 50px; pointer-events: none; transform: rotate(45deg);
          }
          .input-field {
            @apply block w-full rounded-xl border-0 py-2.5 px-4 text-sm focus:ring-0 transition-colors;
            background-color: var(--surface-container-low); color: var(--on-surface);
          }
          .input-field:focus { background-color: var(--surface-container-highest); }
          .btn-primary {
            @apply inline-flex justify-center items-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-opacity shadow-sm;
            background: linear-gradient(135deg, var(--primary), var(--primary-fixed));
          }
          .btn-primary:hover { @apply opacity-90; }
          .btn-secondary {
            @apply inline-flex justify-center items-center rounded-xl px-5 py-2.5 text-sm font-semibold transition-colors;
            background-color: var(--surface-container-highest); color: var(--on-surface);
          }
          .btn-secondary:hover { background-color: var(--surface-container-low); }
          .heritage-divider {
            height: 2px; width: 100%; opacity: 0.2;
            background-image: url('data:image/svg+xml;utf8,<svg width="40" height="2" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v2H0zM15 0h10v2H15zM30 0h10v2h-10z" fill="rgb(26,28,28)"/></svg>');
            background-repeat: repeat-x; margin: 1.5rem 0;
          }
          .glass {
            background-color: rgba(249, 249, 249, 0.6);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
          }
          .budget-bar-container { background-color: var(--surface-container-highest); height: 4px; border-radius: 2px; overflow: hidden; }
          .budget-bar-fill { background: linear-gradient(90deg, var(--primary), var(--primary-fixed)); height: 100%; transition: width 1s ease-out; }
          .tx-icon { @apply w-12 h-12 rounded-md flex items-center justify-center shrink-0; background-color: var(--secondary-container); color: var(--secondary); }
        }
        html[lang="am"] body, html[lang="or"] body { line-height: 1.8; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body class="pb-24 sm:pb-8">
    <!-- Navbar / Bottom Nav depending on screen -->
    <nav class="glass fixed bottom-0 w-full sm:top-0 sm:bottom-auto z-50 border-t sm:border-t-0 sm:border-b border-[var(--outline-variant)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="hidden sm:flex sm:items-center">
                    <span class="text-xl font-bold text-[var(--primary)]"><?php echo $lang['app_name']; ?></span>
                </div>
                <div class="flex flex-1 sm:flex-none justify-around sm:justify-end sm:space-x-8 items-center text-sm font-medium">
                    <a href="dashboard.php" class="text-[var(--on-surface)] hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span class="sm:inline hidden"><?php echo $lang['dashboard']; ?></span>
                    </a>
                    <a href="transactions.php" class="text-gray-500 hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="sm:inline hidden"><?php echo $lang['transactions']; ?></span>
                    </a>
                    <a href="budgets.php" class="text-gray-500 hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span class="sm:inline hidden"><?php echo $lang['budgets']; ?></span>
                    </a>
                    <a href="profile.php" class="text-gray-500 hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="sm:inline hidden"><?php echo $lang['profile'] ?? 'Profile'; ?></span>
                    </a>
                    <a href="settings.php" class="text-gray-500 hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="sm:inline hidden"><?php echo $lang['settings']; ?></span>
                    </a>
                    
                    <!-- Notification Bell -->
                    <?php if (is_logged_in()): ?>
                    <button onclick="document.getElementById('alerts_modal').classList.toggle('hidden')" class="relative text-gray-500 hover:text-[var(--primary)] flex flex-col sm:flex-row items-center gap-1 sm:ml-2 focus:outline-none">
                        <svg class="w-6 h-6 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <?php if (count($unread_alerts) > 0): ?>
                        <span class="absolute top-0 right-0 sm:-right-1 w-2.5 h-2.5 bg-[#ab1118] rounded-full border border-white animate-pulse"></span>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php if (is_logged_in()): ?>
    <!-- Alerts Modal -->
    <div id="alerts_modal" class="hidden fixed bottom-16 sm:bottom-auto sm:top-16 right-4 sm:right-8 w-96 bg-white shadow-2xl rounded-2xl border border-gray-100 z-50 max-h-[28rem] overflow-hidden" style="box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-sm"><?php echo $lang['notifications'] ?? 'Notifications'; ?></h3>
            <?php if (count($unread_alerts) > 0): ?>
                <span class="text-xs text-gray-400"><?php echo count($unread_alerts); ?> <?php echo $lang['unread'] ?? 'unread'; ?></span>
            <?php endif; ?>
        </div>
        <div class="overflow-y-auto max-h-80 p-3">
        <?php if (empty($unread_alerts)): ?>
            <div class="text-center py-8">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <p class="text-xs text-gray-400"><?php echo $lang['no_notifications'] ?? 'No new notifications'; ?></p>
            </div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach($unread_alerts as $al): ?>
                <div class="p-3 bg-gray-50 rounded-xl text-sm border border-gray-100 transition-all hover:bg-gray-100">
                    <?php 
                    $alert_type = $al['alert_type'] ?? 'general';
                    $alert_meta = !empty($al['alert_meta']) ? json_decode($al['alert_meta'], true) : [];
                    ?>
                    
                    <?php if ($alert_type === 'link_request' && !empty($alert_meta['request_id'])): ?>
                        <!-- Link Request Notification -->
                        <div class="flex items-start gap-2 mb-2">
                            <div class="w-8 h-8 shrink-0 rounded-full bg-[var(--primary)] bg-opacity-10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs text-gray-700 leading-relaxed"><?php echo htmlspecialchars($al['message']); ?></p>
                                <p class="text-[10px] text-gray-400 mt-1"><?php echo date('M d, h:i A', strtotime($al['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <form method="POST" action="api/respond_link.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="request_id" value="<?php echo $alert_meta['request_id']; ?>">
                                <input type="hidden" name="alert_id" value="<?php echo $al['id']; ?>">
                                <input type="hidden" name="response" value="accepted">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--primary)] text-white text-xs font-bold rounded-lg hover:opacity-90 transition-opacity"><?php echo $lang['accept']; ?></button>
                            </form>
                            <form method="POST" action="api/respond_link.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="request_id" value="<?php echo $alert_meta['request_id']; ?>">
                                <input type="hidden" name="alert_id" value="<?php echo $al['id']; ?>">
                                <input type="hidden" name="response" value="rejected">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--surface-container-highest)] text-[var(--on-surface)] text-xs font-bold rounded-lg hover:bg-gray-200 transition-colors"><?php echo $lang['decline']; ?></button>
                            </form>
                        </div>
                    
                    <?php elseif ($alert_type === 'date_rsvp' && !empty($alert_meta['date_id'])): ?>
                        <!-- Date RSVP Notification -->
                        <div class="flex items-start gap-2 mb-2">
                            <div class="w-8 h-8 shrink-0 rounded-full bg-[var(--tertiary)] bg-opacity-10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-[var(--tertiary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs text-gray-700 leading-relaxed"><?php echo htmlspecialchars($al['message']); ?></p>
                                <?php if (!empty($alert_meta['date_time'])): ?>
                                    <p class="text-[10px] text-[var(--secondary)] font-semibold mt-1"><?php echo date('M d, Y h:i A', strtotime($alert_meta['date_time'])); ?> • <?php echo isset($alert_meta['cost']) ? '$'.number_format($alert_meta['cost'], 2) : ''; ?></p>
                                <?php endif; ?>
                                <p class="text-[10px] text-gray-400 mt-0.5"><?php echo date('M d, h:i A', strtotime($al['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <form method="POST" action="api/respond_date.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="date_id" value="<?php echo $alert_meta['date_id']; ?>">
                                <input type="hidden" name="alert_id" value="<?php echo $al['id']; ?>">
                                <input type="hidden" name="rsvp_status" value="accepted">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--primary)] text-white text-xs font-bold rounded-lg hover:opacity-90 transition-opacity"><?php echo $lang['accept']; ?></button>
                            </form>
                            <form method="POST" action="api/respond_date.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="date_id" value="<?php echo $alert_meta['date_id']; ?>">
                                <input type="hidden" name="alert_id" value="<?php echo $al['id']; ?>">
                                <input type="hidden" name="rsvp_status" value="declined">
                                <button type="submit" class="w-full text-center py-1.5 bg-[var(--surface-container-highest)] text-[var(--on-surface)] text-xs font-bold rounded-lg hover:bg-gray-200 transition-colors"><?php echo $lang['decline']; ?></button>
                            </form>
                        </div>
                    
                    <?php else: ?>
                        <!-- General Notification -->
                        <div class="flex items-start gap-2">
                            <div class="w-8 h-8 shrink-0 rounded-full bg-[var(--secondary-container)] flex items-center justify-center">
                                <svg class="w-4 h-4 text-[var(--secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs text-gray-700 leading-relaxed"><?php echo htmlspecialchars($al['message']); ?></p>
                                <p class="text-[10px] text-gray-400 mt-1"><?php echo date('M d, h:i A', strtotime($al['created_at'])); ?></p>
                            </div>
                            <form method="POST" action="api/mark_read.php">
                                 <input type="hidden" name="alert_id" value="<?php echo $al['id']; ?>">
                                 <button type="submit" class="text-[10px] text-[var(--primary)] font-semibold p-1.5 bg-[var(--primary)] bg-opacity-10 rounded-md hover:bg-opacity-20 transition-colors shrink-0">OK</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 sm:mt-24 mt-6">
