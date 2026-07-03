<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireAuth();

$db = getDB();
$currentUser = getCurrentUser();
$flash = getFlash();

// Only admins should access this full management page
// Non-admins see only their own tokens
$isAdmin = isset($currentUser['role']) && $currentUser['role'] === 'admin';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $targetUserId = $isAdmin ? (int)($_POST['user_id'] ?? $currentUser['id']) : (int)$currentUser['id'];
        $expiresInDays = max(1, min(365, (int)($_POST['expires_days'] ?? 30)));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
        // Generate a secure random token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $db->prepare('INSERT INTO user_refresh_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)');
        $stmt->execute([$targetUserId, $tokenHash, $expiresAt]);
        $newId = $db->lastInsertId();
        // Show raw token once via flash (base64 for display)
        setFlash('token_created', json_encode(['id' => $newId, 'raw' => $rawToken]));
        header('Location: ' . url('user-refresh-tokens.php')); exit;
    }

    if ($postAction === 'revoke') {
        $tokenId = (int)($_POST['id'] ?? 0);
        $stmt = $isAdmin
            ? $db->prepare('UPDATE user_refresh_tokens SET revoked_at = NOW(), updated_at = NOW() WHERE id = ?')
            : $db->prepare('UPDATE user_refresh_tokens SET revoked_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?');
        $params = $isAdmin ? [$tokenId] : [$tokenId, $currentUser['id']];
        $stmt->execute($params);
        setFlash('success', 'Token revoked successfully.');
        header('Location: ' . url('user-refresh-tokens.php')); exit;
    }

    if ($postAction === 'delete') {
        $tokenId = (int)($_POST['id'] ?? 0);
        $stmt = $isAdmin
            ? $db->prepare('DELETE FROM user_refresh_tokens WHERE id = ?')
            : $db->prepare('DELETE FROM user_refresh_tokens WHERE id = ? AND user_id = ?');
        $params = $isAdmin ? [$tokenId] : [$tokenId, $currentUser['id']];
        $stmt->execute($params);
        setFlash('success', 'Token deleted.');
        header('Location: ' . url('user-refresh-tokens.php')); exit;
    }

    if ($postAction === 'revoke_expired') {
        $count = $db->exec("UPDATE user_refresh_tokens SET revoked_at = NOW() WHERE expires_at < NOW() AND revoked_at IS NULL");
        setFlash('success', "Revoked {$count} expired token(s).");
        header('Location: ' . url('user-refresh-tokens.php')); exit;
    }

    if ($postAction === 'purge_revoked') {
        $count = $db->exec('DELETE FROM user_refresh_tokens WHERE revoked_at IS NOT NULL');
        setFlash('success', "Purged {$count} revoked token(s).");
        header('Location: ' . url('user-refresh-tokens.php')); exit;
    }
}

// Check for newly created token flash
$newTokenData = null;
$rawFlash = getFlash();
if ($rawFlash && $rawFlash['type'] === 'token_created') {
    $newTokenData = json_decode($rawFlash['message'], true);
}
// Re-get normal flash after processing
$flash = getFlash();

// Filters
$search        = trim($_GET['search'] ?? '');
$filterStatus  = $_GET['status'] ?? ''; // 'active','revoked','expired'
$filterUser    = $isAdmin ? (int)($_GET['user_id'] ?? 0) : (int)$currentUser['id'];
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * (int)ITEMS_PER_PAGE;

$where  = $isAdmin ? 'WHERE 1=1' : 'WHERE t.user_id = ' . (int)$currentUser['id'];
$params = [];

if (!$isAdmin) {
    // already filtered by user_id in WHERE
} elseif ($filterUser > 0) {
    $where .= ' AND t.user_id = ?';
    $params[] = $filterUser;
}

if ($filterStatus === 'revoked') {
    $where .= ' AND t.revoked_at IS NOT NULL';
} elseif ($filterStatus === 'expired') {
    $where .= ' AND t.revoked_at IS NULL AND t.expires_at < NOW()';
} elseif ($filterStatus === 'active') {
    $where .= ' AND t.revoked_at IS NULL AND t.expires_at >= NOW()';
}

if ($search !== '') {
    $where .= ' AND (t.token_hash LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

$countSql = "SELECT COUNT(*) FROM user_refresh_tokens t LEFT JOIN users u ON u.id = t.user_id {$where}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalTokens = (int)$countStmt->fetchColumn();
$totalPages  = max(1, ceil($totalTokens / (int)ITEMS_PER_PAGE));

$listSql = "SELECT t.*, u.email AS user_email, u.name AS user_name FROM user_refresh_tokens t LEFT JOIN users u ON u.id = t.user_id {$where} ORDER BY t.created_at DESC LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;
$listStmt = $db->prepare($listSql);
$listStmt->execute($params);
$tokens = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Load users for admin filter dropdown
$allUsers = [];
if ($isAdmin) {
    $allUsers = $db->query('SELECT id, email, name FROM users ORDER BY email ASC')->fetchAll(PDO::FETCH_ASSOC);
}

// Stats
$statsStmt = $db->prepare(
    $isAdmin
        ? 'SELECT COUNT(*) as total, SUM(CASE WHEN revoked_at IS NULL AND expires_at >= NOW() THEN 1 ELSE 0 END) as active, SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked, SUM(CASE WHEN revoked_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired FROM user_refresh_tokens'
        : 'SELECT COUNT(*) as total, SUM(CASE WHEN revoked_at IS NULL AND expires_at >= NOW() THEN 1 ELSE 0 END) as active, SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked, SUM(CASE WHEN revoked_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired FROM user_refresh_tokens WHERE user_id = ?'
);
if ($isAdmin) $statsStmt->execute(); else $statsStmt->execute([$currentUser['id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="space-y-6">
  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-white">Refresh Tokens</h1>
      <p class="text-sm text-slate-400 mt-1">Manage JWT refresh tokens for session rotation and invalidation<?= $isAdmin ? ' across all users' : '' ?>.</p>
    </div>
    <div class="flex items-center gap-2">
      <?php if ($isAdmin): ?>
      <form method="POST" action="<?= url('user-refresh-tokens.php') ?>" onsubmit="return confirm('Revoke all expired tokens?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="revoke_expired">
        <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 text-sm font-medium hover:bg-amber-100 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Revoke Expired
        </button>
      </form>
      <form method="POST" action="<?= url('user-refresh-tokens.php') ?>" onsubmit="return confirm('Permanently purge all revoked tokens?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="purge_revoked">
        <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Purge Revoked
        </button>
      </form>
      <?php endif; ?>
      <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Issue Token
      </button>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if ($flash): ?>
  <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <!-- New Token Display Modal -->
  <?php if ($newTokenData): ?>
  <div id="newTokenModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-md">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-lg font-semibold text-white">Token Created — Copy Now</h2>
      </div>
      <div class="p-6 space-y-4">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
          <p class="text-xs font-semibold text-amber-800 uppercase tracking-wider mb-1">⚠ This token will not be shown again</p>
          <p class="text-xs text-amber-700">Copy it now and store it securely. The database only stores the hashed version.</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wider">Raw Refresh Token</label>
          <div class="flex items-center gap-2">
            <div class="flex-1 bg-gray-900 rounded-lg px-3 py-2 font-mono text-xs text-green-400 break-all overflow-hidden"><?= e($newTokenData['raw']) ?></div>
            <button onclick="navigator.clipboard.writeText('<?= addslashes($newTokenData['raw']) ?>').then(()=>this.textContent='Copied!')" class="flex-shrink-0 px-3 py-2 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 transition-colors font-medium">
              Copy
            </button>
          </div>
        </div>
        <div class="flex justify-end">
          <button onclick="document.getElementById('newTokenModal').remove()" class="px-5 py-2 rounded-lg bg-slate-800/40 text-slate-200 text-sm font-medium hover:bg-slate-700/50 transition-colors">I've copied it, close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <?php
    $statsCards = [
      ['label'=>'Total Tokens','value'=>$stats['total']??0,'color'=>'text-slate-200','bg'=>'bg-slate-800/30','border'=>'border-slate-700/50'],
      ['label'=>'Active','value'=>$stats['active']??0,'color'=>'text-green-700','bg'=>'bg-green-50','border'=>'border-green-200'],
      ['label'=>'Revoked','value'=>$stats['revoked']??0,'color'=>'text-red-700','bg'=>'bg-red-50','border'=>'border-red-200'],
      ['label'=>'Expired','value'=>$stats['expired']??0,'color'=>'text-amber-700','bg'=>'bg-amber-50','border'=>'border-amber-200'],
    ];
    foreach ($statsCards as $card): ?>
    <div class="<?= $card['bg'] ?> border <?= $card['border'] ?> rounded-xl p-4">
      <p class="text-xs font-medium text-slate-400 uppercase tracking-wider"><?= $card['label'] ?></p>
      <p class="text-2xl font-bold <?= $card['color'] ?> mt-1"><?= number_format((int)$card['value']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Search & Filter -->
  <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 shadow-sm p-4">
    <form method="GET" action="<?= url('user-refresh-tokens.php') ?>" class="flex flex-col sm:flex-row gap-3">
      <div class="flex-1 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by token hash or user email..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <select name="status" class="px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
        <option value="">All Statuses</option>
        <option value="active" <?= $filterStatus==='active' ? 'selected':'' ?>>Active</option>
        <option value="revoked" <?= $filterStatus==='revoked' ? 'selected':'' ?>>Revoked</option>
        <option value="expired" <?= $filterStatus==='expired' ? 'selected':'' ?>>Expired</option>
      </select>
      <?php if ($isAdmin): ?>
      <select name="user_id" class="px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
        <option value="0">All Users</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected':'' ?>><?= e($u['email']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Filter</button>
      <?php if ($search || $filterStatus || ($isAdmin && $filterUser)): ?>
        <a href="<?= url('user-refresh-tokens.php') ?>" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors text-center">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Tokens Table -->
  <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <span class="text-sm text-slate-400"><?= number_format($totalTokens) ?> token<?= $totalTokens !== 1 ? 's' : '' ?></span>
      <span class="text-xs text-gray-400">Tokens store SHA-256 hashed values — raw tokens are never stored.</span>
    </div>
    <?php if (empty($tokens)): ?>
      <div class="flex flex-col items-center justify-center py-16 text-center">
        <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        <p class="text-slate-400 font-medium">No tokens found</p>
        <p class="text-gray-400 text-sm mt-1">Issue a new refresh token to get started.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-slate-800/30">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">ID</th>
            <?php if ($isAdmin): ?>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">User</th>
            <?php endif; ?>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Token Hash (partial)</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Expires</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Revoked At</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Created</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($tokens as $t):
            $isExpired = strtotime($t['expires_at']) < time();
            $isRevoked = !empty($t['revoked_at']);
            if ($isRevoked) { $statusLabel = 'Revoked'; $statusClass = 'bg-red-100 text-red-700'; }
            elseif ($isExpired) { $statusLabel = 'Expired'; $statusClass = 'bg-amber-100 text-amber-700'; }
            else { $statusLabel = 'Active'; $statusClass = 'bg-green-100 text-green-700'; }
            $hashPartial = substr($t['token_hash'], 0, 8) . '...' . substr($t['token_hash'], -8);
          ?>
          <tr class="hover:bg-slate-800/30 transition-colors">
            <td class="px-4 py-3 text-sm font-mono text-gray-400">#<?= $t['id'] ?></td>
            <?php if ($isAdmin): ?>
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-medium text-white"><?= e($t['user_name'] ?? '-') ?></p>
                <p class="text-xs text-gray-400"><?= e($t['user_email'] ?? '') ?></p>
              </div>
            </td>
            <?php endif; ?>
            <td class="px-4 py-3">
              <span class="font-mono text-xs text-slate-400 bg-slate-800/40 px-2 py-1 rounded" title="<?= e($t['token_hash']) ?>"><?= e($hashPartial) ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>"><?= $statusLabel ?></span>
            </td>
            <td class="px-4 py-3">
              <div class="<?= $isExpired ? 'text-red-500' : 'text-slate-300' ?>">
                <p class="text-xs font-medium"><?= date('M j, Y', strtotime($t['expires_at'])) ?></p>
                <p class="text-xs text-gray-400"><?= date('g:i A', strtotime($t['expires_at'])) ?></p>
              </div>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400">
              <?= $t['revoked_at'] ? date('M j, Y g:i A', strtotime($t['revoked_at'])) : '—' ?>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <?php if (!$isRevoked): ?>
                <form method="POST" action="<?= url('user-refresh-tokens.php') ?>" onsubmit="return confirm('Revoke this token? The user will need to log in again.')" class="inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="revoke">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" class="text-amber-600 hover:text-amber-800 text-xs font-medium px-2 py-1 rounded hover:bg-amber-50 transition-colors">Revoke</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= url('user-refresh-tokens.php') ?>" onsubmit="return confirm('Permanently delete this token record?')" class="inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1 rounded hover:bg-red-50 transition-colors">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
      <p class="text-sm text-slate-400">Page <?= $page ?> of <?= $totalPages ?></p>
      <div class="flex items-center gap-1">
        <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
          <a href="<?= url('user-refresh-tokens.php') ?>?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&user_id=<?= $filterUser ?>" class="px-3 py-1 text-sm rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800/40' ?> transition-colors"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create Token Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-white">Issue New Refresh Token</h2>
      <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400 hover:text-slate-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="<?= url('user-refresh-tokens.php') ?>" class="p-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-sm text-blue-700">
        <p class="font-medium">Secure token generation</p>
        <p class="text-xs text-blue-600 mt-0.5">A cryptographically random 256-bit token will be generated. Only the SHA-256 hash is stored in the database.</p>
      </div>
      <?php if ($isAdmin): ?>
      <div>
        <label class="block text-sm font-medium text-slate-200 mb-1">User <span class="text-red-500">*</span></label>
        <select name="user_id" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
          <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $u['id'] == $currentUser['id'] ? 'selected' : '' ?>><?= e($u['email']) ?> (<?= e($u['name'] ?? '') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="user_id" value="<?= $currentUser['id'] ?>">
      <?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-slate-200 mb-1">Token Validity (days)</label>
        <input type="number" name="expires_days" value="30" min="1" max="365" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p class="text-xs text-gray-400 mt-1">Standard is 30 days. Maximum is 365 days.</p>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Generate Token</button>
      </div>
    </form>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>