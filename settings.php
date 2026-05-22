<?php
// settings.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

require_login();
$user_id = get_current_user_id();

function _t($key, $fallback = '') {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : ($fallback ?: $key);
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$is_linked = false;
$partner = null;
if ($user['account_type'] == 'couple') {
    $stmtLink = $pdo->prepare("SELECT * FROM couple_relationships WHERE user1_id = ? OR user2_id = ?");
    $stmtLink->execute([$user_id, $user_id]);
    if ($link = $stmtLink->fetch()) {
        $is_linked = true;
        $p_id = ($link['user1_id'] == $user_id) ? $link['user2_id'] : $link['user1_id'];
        $stmtP = $pdo->prepare("SELECT full_name, username, avatar_url FROM users WHERE id = ?");
        $stmtP->execute([$p_id]);
        $partner = $stmtP->fetch();
    }
}

$current_lang = $_SESSION['lang'] ?? 'en';

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
        
        // Language switch
        if ($_POST['action'] === 'set_lang') {
            $_SESSION['lang'] = $_POST['lang'];
            echo json_encode(['success' => true, 'message' => _t('lang_updated', 'Language updated')]);
            exit;
        }
        
        // Cancel outgoing request
        if ($_POST['action'] === 'cancel_link_request') {
            $request_id = (int)$_POST['request_id'];
            $stmt = $pdo->prepare("SELECT id FROM couple_link_requests WHERE id = ? AND sender_id = ? AND status = 'pending'");
            $stmt->execute([$request_id, $user_id]);
            if ($stmt->fetch()) {
                $stmtDel = $pdo->prepare("DELETE FROM couple_link_requests WHERE id = ?");
                $stmtDel->execute([$request_id]);
                echo json_encode(['success' => true, 'message' => _t('request_cancelled', 'Request cancelled')]);
            } else {
                echo json_encode(['success' => false, 'message' => _t('invalid_request', 'Invalid request')]);
            }
            exit;
        }
        
        // Send link request
        if ($_POST['action'] === 'send_link_request') {
            $partner_id = (int)$_POST['partner_id'];
            if ($partner_id === $user_id) {
                echo json_encode(['success' => false, 'message' => _t('cannot_link_self', 'You cannot link with yourself')]);
                exit;
            }
            $stmtC = $pdo->prepare("SELECT * FROM couple_link_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmtC->execute([$user_id, $partner_id]);
            if ($stmtC->fetch()) {
                echo json_encode(['success' => false, 'message' => _t('request_already_sent', 'Request already sent.')]);
                exit;
            }
            $stmtC2 = $pdo->prepare("SELECT * FROM couple_link_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmtC2->execute([$partner_id, $user_id]);
            if ($stmtC2->fetch()) {
                echo json_encode(['success' => false, 'message' => _t('request_already_received', 'A pending request already exists from this user.')]);
                exit;
            }
            $stmtIns = $pdo->prepare("INSERT INTO couple_link_requests (sender_id, receiver_id) VALUES (?, ?)");
            $stmtIns->execute([$user_id, $partner_id]);
            $request_id = $pdo->lastInsertId();
            $alert_msg = _t('link_request_alert', '{name} wants to link their couples account with you.', ['name' => $user['full_name']]);
            $alert_meta = json_encode(['request_id' => $request_id, 'sender_id' => $user_id, 'sender_name' => $user['full_name']]);
            $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type, alert_meta) VALUES (?, ?, 'link_request', ?)");
            $stmtA->execute([$partner_id, $alert_msg, $alert_meta]);
            echo json_encode(['success' => true, 'message' => _t('link_request_sent', 'Link request sent!')]);
            exit;
        }
        
        // Respond to request
        if ($_POST['action'] === 'respond_link_request') {
            $request_id = (int)$_POST['request_id'];
            $response = $_POST['response'];
            $stmtReq = $pdo->prepare("SELECT * FROM couple_link_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            $stmtReq->execute([$request_id, $user_id]);
            if ($req = $stmtReq->fetch()) {
                $stmtUpd = $pdo->prepare("UPDATE couple_link_requests SET status = ? WHERE id = ?");
                $stmtUpd->execute([$response, $request_id]);
                if ($response === 'accepted') {
                    $stmtL = $pdo->prepare("INSERT INTO couple_relationships (user1_id, user2_id) VALUES (?, ?)");
                    $stmtL->execute([$req['sender_id'], $req['receiver_id']]);
                    $stmtUpdType = $pdo->prepare("UPDATE users SET account_type = 'couple' WHERE id IN (?, ?)");
                    $stmtUpdType->execute([$req['sender_id'], $req['receiver_id']]);
                    $receiver_name = $user['full_name'];
                    $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'general')");
                    $stmtA->execute([$req['sender_id'], _t('link_accepted_alert', '{name} accepted your couples link request!', ['name' => $receiver_name])]);
                    echo json_encode(['success' => true, 'message' => _t('link_accepted', 'Accounts linked!'), 'linked' => true]);
                } else {
                    $receiver_name = $user['full_name'];
                    $stmtA = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'general')");
                    $stmtA->execute([$req['sender_id'], _t('link_declined_alert', '{name} declined your couples link request.', ['name' => $receiver_name])]);
                    echo json_encode(['success' => true, 'message' => _t('link_declined', 'Request declined.')]);
                }
                exit;
            }
            echo json_encode(['success' => false, 'message' => _t('invalid_request', 'Invalid request')]);
            exit;
        }
        
        // Search partner
        if ($_POST['action'] === 'search_partner') {
            $search_query = '%' . trim($_POST['search']) . '%';
            $opp_gender = ($user['gender'] === 'male') ? 'female' : (($user['gender'] === 'female') ? 'male' : '');
            if ($opp_gender) {
                $stmtSearch = $pdo->prepare("SELECT id, full_name, username FROM users WHERE username LIKE ? AND account_type = 'couple' AND gender = ? AND id != ? AND is_onboarded = 1 LIMIT 20");
                $stmtSearch->execute([$search_query, $opp_gender, $user_id]);
            } else {
                $stmtSearch = $pdo->prepare("SELECT id, full_name, username FROM users WHERE username LIKE ? AND account_type = 'couple' AND id != ? AND is_onboarded = 1 LIMIT 20");
                $stmtSearch->execute([$search_query, $user_id]);
            }
            $results = $stmtSearch->fetchAll();
            $html = '';
            foreach ($results as $r) {
                $html .= '<div class="flex items-center justify-between p-3 bg-[var(--surface-container-lowest)] rounded-xl shadow-sm" data-user-id="' . $r['id'] . '">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[var(--surface-container-highest)] flex items-center justify-center text-sm font-bold text-[var(--primary)]">' . strtoupper(substr($r['full_name'], 0, 1)) . '</div>
                        <div><span class="font-semibold text-sm block">' . htmlspecialchars($r['full_name']) . '</span><span class="text-xs text-gray-500 font-mono">@' . htmlspecialchars($r['username'] ?? '') . '</span></div>
                    </div>
                    <button class="send-request-btn text-xs font-bold text-white bg-[var(--primary)] px-3 py-1.5 rounded-lg hover:opacity-90" data-partner-id="' . $r['id'] . '">' . _t('send_request', 'Send Request') . '</button>
                </div>';
            }
            if (empty($results)) $html = '<p class="text-sm text-gray-500 text-center py-4">' . _t('no_partner_found', 'No matching partners found.') . '</p>';
            echo json_encode(['success' => true, 'html' => $html]);
            exit;
        }
        
        // Refresh requests
        if ($_POST['action'] === 'refresh_requests') {
            $stmtInc = $pdo->prepare("SELECT r.id, u.full_name, u.username FROM couple_link_requests r JOIN users u ON r.sender_id = u.id WHERE r.receiver_id = ? AND r.status = 'pending'");
            $stmtInc->execute([$user_id]);
            $incoming = $stmtInc->fetchAll();
            $incomingHtml = '';
            foreach ($incoming as $req) {
                $incomingHtml .= '<div class="flex items-center justify-between p-3 bg-[var(--surface-container-highest)] rounded-xl" data-request-id="' . $req['id'] . '">
                    <div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-[var(--primary)] bg-opacity-10 flex items-center justify-center text-xs font-bold text-[var(--primary)]">' . strtoupper(substr($req['full_name'], 0, 1)) . '</div><div><span class="text-sm font-semibold">' . htmlspecialchars($req['full_name']) . '</span><span class="text-xs text-gray-500 font-mono ml-1">@' . htmlspecialchars($req['username'] ?? '') . '</span></div></div>
                    <div class="flex gap-2"><button class="accept-request-btn text-xs font-bold text-white bg-[var(--primary)] px-3 py-1.5 rounded-lg" data-request-id="' . $req['id'] . '">' . _t('accept', 'Accept') . '</button><button class="decline-request-btn text-xs font-bold text-gray-500 bg-white border px-3 py-1.5 rounded-lg" data-request-id="' . $req['id'] . '">' . _t('decline', 'Decline') . '</button></div>
                </div>';
            }
            if (empty($incoming)) $incomingHtml = '<p class="text-sm text-gray-500 text-center py-2">' . _t('no_incoming_requests', 'No incoming requests') . '</p>';
            
            $stmtOut = $pdo->prepare("SELECT r.id, u.full_name, u.username FROM couple_link_requests r JOIN users u ON r.receiver_id = u.id WHERE r.sender_id = ? AND r.status = 'pending'");
            $stmtOut->execute([$user_id]);
            $outgoing = $stmtOut->fetchAll();
            $outgoingHtml = '';
            foreach ($outgoing as $req) {
                $outgoingHtml .= '<div class="flex items-center justify-between p-3 bg-[var(--surface-container-low)] border border-gray-100 rounded-xl" data-request-id="' . $req['id'] . '">
                    <div><span class="text-sm text-gray-600">' . _t('to', 'To') . ': ' . htmlspecialchars($req['full_name']) . '</span><span class="text-xs font-mono text-gray-400 ml-1">@' . htmlspecialchars($req['username'] ?? '') . '</span></div>
                    <div class="flex items-center gap-2"><span class="text-xs font-bold text-[var(--secondary)]">' . _t('pending', 'Pending') . '</span><button class="cancel-request-btn text-xs text-red-600 hover:text-red-800 ml-2" data-request-id="' . $req['id'] . '">' . _t('cancel', 'Cancel') . '</button></div>
                </div>';
            }
            if (empty($outgoing)) $outgoingHtml = '<p class="text-sm text-gray-500 text-center py-2">' . _t('no_outgoing_requests', 'No outgoing requests') . '</p>';
            
            echo json_encode(['success' => true, 'incoming_html' => $incomingHtml, 'outgoing_html' => $outgoingHtml]);
            exit;
        }
    }
    exit;
}

include 'inc/header.php';
?>

<div class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-3xl font-bold font-display"><?php echo _t('settings', 'Settings'); ?></h2>
        <a href="logout.php" class="btn-secondary flex items-center gap-2">
            <svg class="w-5 h-5 text-[var(--tertiary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <?php echo _t('logout', 'Logout'); ?>
        </a>
    </div>

    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

    <div class="card space-y-6">
        <!-- Language -->
        <div>
            <h3 class="text-lg font-bold font-display mb-2"><?php echo _t('language_preferences', 'Language Preferences'); ?></h3>
            <div class="flex gap-4 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="flex-1">
                    <select id="langSelect" class="input-field">
                        <option value="en" <?php echo $current_lang == 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="am" <?php echo $current_lang == 'am' ? 'selected' : ''; ?>>አማርኛ (Amharic)</option>
                        <option value="or" <?php echo $current_lang == 'or' ? 'selected' : ''; ?>>Afaan Oromo</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="heritage-divider"></div>

        <!-- Account Info -->
        <div>
            <h3 class="text-lg font-bold font-display mb-1"><?php echo _t('your_account', 'Your Account'); ?></h3>
            <p class="text-sm text-gray-500 mb-4">
                <?php echo _t('account_type_label', 'Account Type'); ?>: 
                <strong class="text-[var(--on-surface)]"><?php echo ucfirst($user['account_type']); ?></strong>
                <?php if (!empty($user['username'])): ?>
                    &nbsp;•&nbsp; <span class="text-[var(--primary)] font-mono">@<?php echo htmlspecialchars($user['username']); ?></span>
                <?php endif; ?>
            </p>
            
            <?php if ($user['account_type'] == 'couple'): ?>
                <?php if ($is_linked): ?>
                    <div id="linkedStatus" class="p-4 rounded-xl bg-[var(--primary)] bg-opacity-10 text-[var(--primary)] font-semibold flex items-center gap-3 mb-4">
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <div>
                            <p><?php echo _t('linked_with', 'Linked with'); ?>: <strong><?php echo htmlspecialchars($partner['full_name'] ?? ''); ?></strong></p>
                            <?php if (!empty($partner['username'])): ?>
                                <p class="text-xs opacity-75 font-mono">@<?php echo htmlspecialchars($partner['username']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="partnerSearchSection" class="bg-[var(--surface-container-low)] p-6 rounded-2xl">
                        <h4 class="font-bold text-[var(--on-surface)] mb-1"><?php echo _t('search_partner', 'Search Partner'); ?></h4>
                        <p class="text-sm text-gray-500 mb-4"><?php echo _t('search_partner_desc', 'Search for your partner by their username to link your accounts. Only opposite-gender couple accounts appear.'); ?></p>
                        
                        <div class="flex gap-4 mb-6">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4"><span class="text-gray-400 text-sm">@</span></div>
                                <input type="text" id="partnerSearchInput" placeholder="<?php echo _t('search_by_username', 'Search by username...'); ?>" class="input-field pl-8 flex-1">
                            </div>
                            <button id="searchPartnerBtn" class="btn-primary"><?php echo _t('search', 'Search'); ?></button>
                        </div>
                        
                        <div id="searchResults" class="space-y-3"></div>
                    </div>
                    
                    <div id="requestsSection" class="mt-8 space-y-6">
                        <div id="incomingRequestsContainer">
                            <h4 class="font-bold text-[var(--on-surface)] mb-3"><?php echo _t('incoming_requests', 'Incoming Requests'); ?></h4>
                            <div id="incomingRequestsList" class="space-y-3"><p class="text-sm text-gray-500 text-center py-2"><?php echo _t('loading', 'Loading...'); ?></p></div>
                        </div>
                        <div id="outgoingRequestsContainer">
                            <h4 class="font-bold text-[var(--on-surface)] mb-3 text-sm"><?php echo _t('outgoing_requests', 'Outgoing Requests'); ?></h4>
                            <div id="outgoingRequestsList" class="space-y-3"><p class="text-sm text-gray-500 text-center py-2"><?php echo _t('loading', 'Loading...'); ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white text-sm ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} transform translate-x-full transition-all duration-300`;
    toast.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}"/></svg><span>${message}</span>`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => { toast.classList.add('translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
}

// Language switcher
const langSelect = document.getElementById('langSelect');
if (langSelect) {
    langSelect.addEventListener('change', async function() {
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
        formData.append('action', 'set_lang');
        formData.append('lang', this.value);
        try {
            const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
            const data = await res.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, 'error');
        } catch (err) { showToast('Network error', 'error'); }
    });
}

// Partner search
const searchBtn = document.getElementById('searchPartnerBtn');
const searchInput = document.getElementById('partnerSearchInput');
const searchResults = document.getElementById('searchResults');
if (searchBtn) {
    async function performSearch() {
        const query = searchInput.value.trim();
        if (!query) { searchResults.innerHTML = '<p class="text-sm text-gray-500 text-center py-4"><?php echo addslashes(_t('enter_username', 'Please enter a username')); ?></p>'; return; }
        searchResults.innerHTML = '<p class="text-sm text-gray-500 text-center py-4"><?php echo addslashes(_t('searching', 'Searching...')); ?></p>';
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
        formData.append('action', 'search_partner');
        formData.append('search', query);
        try {
            const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
            const data = await res.json();
            if (data.success) {
                searchResults.innerHTML = data.html;
                document.querySelectorAll('.send-request-btn').forEach(btn => btn.addEventListener('click', sendLinkRequest));
            } else showToast(data.message, 'error');
        } catch (err) { showToast('Network error', 'error'); }
    }
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') performSearch(); });
}

async function sendLinkRequest(e) {
    const btn = e.currentTarget;
    const partnerId = btn.dataset.partnerId;
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('action', 'send_link_request');
    formData.append('partner_id', partnerId);
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            const row = btn.closest('[data-user-id]');
            if (row) row.remove();
            refreshRequests();
        } else showToast(data.message, 'error');
    } catch (err) { showToast('Network error', 'error'); }
}

async function refreshRequests() {
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('action', 'refresh_requests');
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById('incomingRequestsList').innerHTML = data.incoming_html;
            document.getElementById('outgoingRequestsList').innerHTML = data.outgoing_html;
            document.querySelectorAll('.accept-request-btn').forEach(btn => btn.addEventListener('click', () => respondToRequest(btn.dataset.requestId, 'accepted')));
            document.querySelectorAll('.decline-request-btn').forEach(btn => btn.addEventListener('click', () => respondToRequest(btn.dataset.requestId, 'rejected')));
            document.querySelectorAll('.cancel-request-btn').forEach(btn => btn.addEventListener('click', cancelRequest));
        }
    } catch (err) { console.error(err); }
}

async function cancelRequest(e) {
    const btn = e.currentTarget;
    const requestId = btn.dataset.requestId;
    if (!confirm('<?php echo addslashes(_t('confirm_cancel', 'Cancel this request?')); ?>')) return;
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('action', 'cancel_link_request');
    formData.append('request_id', requestId);
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            refreshRequests();
        } else showToast(data.message, 'error');
    } catch (err) { showToast('Network error', 'error'); }
}

async function respondToRequest(requestId, response) {
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('action', 'respond_link_request');
    formData.append('request_id', requestId);
    formData.append('response', response);
    try {
        const res = await fetch('settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            if (response === 'accepted' && data.linked) setTimeout(() => location.reload(), 1500);
            else refreshRequests();
        } else showToast(data.message, 'error');
    } catch (err) { showToast('Network error', 'error'); }
}

<?php if ($user['account_type'] == 'couple' && !$is_linked): ?>
refreshRequests();
<?php endif; ?>
</script>

<?php include 'inc/footer.php'; ?>