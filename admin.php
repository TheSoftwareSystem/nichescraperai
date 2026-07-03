<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Only allow admins
$user = getCurrentUser();
if (!$user || (isset($user['role']) && $user['role'] !== 'admin')) {
    // Check if user is logged in at all
    if (!isLoggedIn()) {
        header('Location: ' . url('login.php'));
        exit;
    }
    // For this app, treat plan=agency as admin, or check a role column if it exists
    $db = getDB();
    $cols = array_column($db->query('DESCRIBE users')->fetchAll(), 'Field');
    $hasRole = in_array('role', $cols);
    if ($hasRole) {
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row || $row['role'] !== 'admin') {
            setFlash('error', 'Access denied. Admin only.');
            header('Location: ' . url('dashboard.php'));
            exit;
        }
    } else {
        // Fall back: only agency plan users can access admin
        if (!isset($user['plan']) || $user['plan'] !== 'agency') {
            setFlash('error', 'Access denied. Admin only.');
            header('Location: ' . url('dashboard.php'));
            exit;
        }
    }
}

$db = getDB();
$cols = array_column($db->query('DESCRIBE users')->fetchAll(), 'Field');
$nameCol = in_array('first_name', $cols) ? 'first_name' : (in_array('name', $cols) ? 'name' : null);
$pwCol = in_array('password_hash', $cols) ? 'password_hash' : 'password';
$hasRole = in_array('role', $cols);
$hasPlan = in_array('plan', $cols);
$hasStatus = in_array('status', $cols);
$hasEmailVerified = in_array('email_verified_at', $cols);

$flash = getFlash();

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // ── Delete user ──
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$user['id']) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            setFlash('success', 'User deleted successfully.');
        } else {
            setFlash('error', 'Cannot delete yourself or invalid user.');
        }
        header('Location: ' . url('admin.php') . '?tab=users');
        exit;
    }

    // ── Toggle user status ──
    if ($action === 'toggle_status' && $hasStatus) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $current = $_POST['current_status'] ?? 'active';
        $new = $current === 'active' ? 'inactive' : 'active';
        if ($uid) {
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $uid]);
            setFlash('success', 'User status updated.');
        }
        header('Location: ' . url('admin.php') . '?tab=users');
        exit;
    }

    // ── Update user role/plan ──
    if ($action === 'update_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newPlan = $_POST['plan'] ?? 'free';
        if (!in_array($newPlan, ['free','pro','agency'])) $newPlan = 'free';
        if ($uid && $hasPlan) {
            $db->prepare('UPDATE users SET plan = ? WHERE id = ?')->execute([$newPlan, $uid]);
        }
        if ($uid && $hasRole && isset($_POST['role'])) {
            $newRole = in_array($_POST['role'], ['admin','user']) ? $_POST['role'] : 'user';
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $uid]);
        }
        setFlash('success', 'User updated successfully.');
        header('Location: ' . url('admin.php') . '?tab=users');
        exit;
    }

    // ── Save settings ──
    if ($action === 'save_settings') {
        $keys = $_POST['setting_key'] ?? [];
        $vals = $_POST['setting_value'] ?? [];
        foreach ($keys as $i => $k) {
            $k = trim($k);
            $v = trim($vals[$i] ?? '');
            if ($k === '') continue;
            $db->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute([$k, $v]);
        }
        // New setting
        $newKey = trim($_POST['new_key'] ?? '');
        $newVal = trim($_POST['new_value'] ?? '');
        if ($newKey !== '') {
            $db->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute([$newKey, $newVal]);
        }
        setFlash('success', 'Settings saved.');
        header('Location: ' . url('admin.php') . '?tab=settings');
        exit;
    }

    // ── Add to domain blocklist ──
    if ($action === 'add_blocklist') {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $reason = trim($_POST['reason'] ?? '');
        if ($domain) {
            try {
                // Check if domain_blocklist table exists
                $tables = array_column($db->query("SHOW TABLES LIKE 'domain_blocklist'")->fetchAll(), 0);
                if (!empty($tables)) {
                    $db->prepare('INSERT IGNORE INTO domain_blocklist (domain, reason) VALUES (?, ?)')->execute([$domain, $reason]);
                    setFlash('success', 'Domain added to blocklist.');
                } else {
                    setFlash('error', 'domain_blocklist table not found.');
                }
            } catch (Exception $e) {
                setFlash('error', $e->getMessage());
            }
        }
        header('Location: ' . url('admin.php') . '?tab=blocklist');
        exit;
    }

    // ── Remove from blocklist ──
    if ($action === 'remove_blocklist') {
        $bid = (int)($_POST['blocklist_id'] ?? 0);
        if ($bid) {
            $db->prepare('DELETE FROM domain_blocklist WHERE id = ?')->execute([$bid]);
            setFlash('success', 'Domain removed from blocklist.');
        }
        header('Location: ' . url('admin.php') . '?tab=blocklist');
        exit;
    }
}

// ── Active tab ────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';

// ── Stats ─────────────────────────────────────────────────────────────────
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalJobs = 0;
$totalEmails = 0;
$totalLists = 0;
$jobTables = array_column($db->query("SHOW TABLES LIKE 'scrape_jobs'")->fetchAll(), 0);
if (!empty($jobTables)) {
    $totalJobs = $db->query('SELECT COUNT(*) FROM scrape_jobs')->fetchColumn();
}
$emailTables = array_column($db->query("SHOW TABLES LIKE 'scraped_emails'")->fetchAll(), 0);
if (!empty($emailTables)) {
    $totalEmails = $db->query('SELECT COUNT(*) FROM scraped_emails')->fetchColumn();
}
$listTables = array_column($db->query("SHOW TABLES LIKE 'lead_lists'")->fetchAll(), 0);
if (!empty($listTables)) {
    $totalLists = $db->query('SELECT COUNT(*) FROM lead_lists')->fetchColumn();
}

// ── Plan distribution ────────────────────────────────────────────────────
$planDist = [];
if ($hasPlan) {
    $rows = $db->query("SELECT plan, COUNT(*) as cnt FROM users GROUP BY plan")->fetchAll();
    foreach ($rows as $r) $planDist[$r['plan']] = $r['cnt'];
}

// ── User registrations last 7 days ───────────────────────────────────────
$regDays = [];
$regCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $regDays[] = date('M j', strtotime($date));
    $cnt = $db->prepare('SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?');
    $cnt->execute([$date]);
    $regCounts[] = (int)$cnt->fetchColumn();
}

// ── Job statuses (if table exists) ───────────────────────────────────────
$jobStatuses = [];
if (!empty($jobTables)) {
    $statusCols = array_column($db->query('DESCRIBE scrape_jobs')->fetchAll(), 'Field');
    if (in_array('status', $statusCols)) {
        $rows = $db->query("SELECT status, COUNT(*) as cnt FROM scrape_jobs GROUP BY status")->fetchAll();
        foreach ($rows as $r) $jobStatuses[$r['status']] = $r['cnt'];
    }
}

// ── Recent audit logs ────────────────────────────────────────────────────
$recentActivity = [];
$auditTables = array_column($db->query("SHOW TABLES LIKE 'audit_logs'")->fetchAll(), 0);
if (!empty($auditTables)) {
    $recentActivity = $db->query(
        'SELECT al.*, u.email as user_email FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC LIMIT 20'
    )->fetchAll();
} else {
    // Fallback: recent users
    $nameSelect = $nameCol ? ", $nameCol as display_name" : '';
    $recentActivity = $db->query(
        "SELECT id, email{$nameSelect}, created_at, plan FROM users ORDER BY created_at DESC LIMIT 20"
    )->fetchAll();
}

// ── User listing with search/pagination ──────────────────────────────────
$search = trim($_GET['q'] ?? '');
$planFilter = $_GET['plan_filter'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * (int)ITEMS_PER_PAGE;

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (email LIKE ? ' . ($nameCol ? "OR {$nameCol} LIKE ?" : '') . ')';
    $params[] = "%{$search}%";
    if ($nameCol) $params[] = "%{$search}%";
}
if ($planFilter && $hasPlan && in_array($planFilter, ['free','pro','agency'])) {
    $where .= ' AND plan = ?';
    $params[] = $planFilter;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
$countStmt->execute($params);
$totalUserCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalUserCount / (int)ITEMS_PER_PAGE));

$nameSelect = $nameCol ? ", {$nameCol} as display_name" : '';
$statusSelect = $hasStatus ? ', status' : '';
$planSelect = $hasPlan ? ', plan' : '';
$roleSelect = $hasRole ? ', role' : '';

$userStmt = $db->prepare(
    "SELECT id, email{$nameSelect}{$statusSelect}{$planSelect}{$roleSelect}, created_at, credits_used_this_month
     FROM users WHERE {$where}
     ORDER BY created_at DESC
     LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset
);
$userStmt->execute($params);
$users = $userStmt->fetchAll();

// ── Settings ─────────────────────────────────────────────────────────────
$settings = [];
try {
    $settings = $db->query('SELECT * FROM settings ORDER BY setting_key ASC')->fetchAll();
} catch (Exception $e) {}

// ── Domain blocklist ─────────────────────────────────────────────────────
$blocklist = [];
if (!empty($tables ?? [])) {
    $blTables = array_column($db->query("SHOW TABLES LIKE 'domain_blocklist'")->fetchAll(), 0);
    if (!empty($blTables)) {
        $blocklist = $db->query('SELECT * FROM domain_blocklist ORDER BY created_at DESC LIMIT 100')->fetchAll();
    }
}

require_once 'includes/header.php';
?>

<div class="space-y-6">
  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-3xl font-extrabold text-white tracking-tight">Admin Panel</h1>
      <p class="text-slate-400 mt-1 text-sm">Manage users, monitor scrape jobs, configure system settings, and maintain blocklists.</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-700 text-xs font-semibold border border-red-200">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
        Admin Access
      </span>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="rounded-xl px-5 py-4 text-sm font-medium border
      <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Tab Navigation -->
  <div class="border-b border-slate-700/50">
    <nav class="-mb-px flex gap-1 overflow-x-auto">
      <?php
      $tabs = [
        'overview'  => ['label' => 'Overview',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
        'users'     => ['label' => 'Users',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
        'settings'  => ['label' => 'Settings',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        'blocklist' => ['label' => 'Blocklist',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>'],
        'activity'  => ['label' => 'Activity Log','icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'],
      ];
      foreach ($tabs as $key => $t): ?>
        <a href="<?= url('admin.php') ?>?tab=<?= $key ?>"
           class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors
             <?= $tab === $key
               ? 'border-indigo-600 text-indigo-700'
               : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-600/50' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $t['icon'] ?></svg>
          <?= $t['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- ═══════════════════════════════════════════════════════════ OVERVIEW -->
  <?php if ($tab === 'overview'): ?>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $stats = [
      ['label'=>'Total Users',       'value'=>number_format($totalUsers),  'color'=>'indigo',  'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
      ['label'=>'Scrape Jobs',       'value'=>number_format($totalJobs),   'color'=>'blue',    'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>'],
      ['label'=>'Emails Scraped',    'value'=>number_format($totalEmails), 'color'=>'emerald', 'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'],
      ['label'=>'Lead Lists',        'value'=>number_format($totalLists),  'color'=>'violet',  'icon'=>'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
    ];
    $colorMap = ['indigo'=>'bg-indigo-50 text-indigo-600 border-indigo-100','blue'=>'bg-blue-50 text-blue-600 border-blue-100','emerald'=>'bg-emerald-50 text-emerald-600 border-emerald-100','violet'=>'bg-violet-50 text-violet-600 border-violet-100'];
    foreach ($stats as $s): ?>
    <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 p-5 shadow-sm hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= $s['label'] ?></p>
          <p class="text-3xl font-extrabold text-white mt-1" id="stat-<?= $s['color'] ?>"><?= $s['value'] ?></p>
        </div>
        <div class="p-2.5 rounded-xl border <?= $colorMap[$s['color']] ?>">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $s['icon'] ?></svg>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- User Registrations Chart -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-base font-bold text-white">User Registrations</h3>
          <p class="text-xs text-slate-400 mt-0.5">Last 7 days</p>
        </div>
        <span class="text-xs font-semibold px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg">Daily</span>
      </div>
      <canvas id="regChart" height="180"></canvas>
    </div>

    <!-- Plan Distribution Chart -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-base font-bold text-white">Plan Distribution</h3>
          <p class="text-xs text-slate-400 mt-0.5">Current active users by plan</p>
        </div>
      </div>
      <div class="flex items-center justify-center">
        <canvas id="planChart" width="220" height="220"></canvas>
      </div>
      <div class="flex justify-center gap-6 mt-4">
        <?php
        $planColors = ['free'=>['bg'=>'bg-gray-400','label'=>'Free'],'pro'=>['bg'=>'bg-indigo-500','label'=>'Pro'],'agency'=>['bg'=>'bg-violet-500','label'=>'Agency']];
        foreach ($planColors as $pk => $pc): ?>
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full <?= $pc['bg'] ?>"></div>
          <span class="text-xs text-slate-300 font-medium"><?= $pc['label'] ?> (<?= number_format($planDist[$pk] ?? 0) ?>)</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Job Status Overview -->
  <?php if (!empty($jobStatuses)): ?>
  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 p-6 shadow-sm">
    <h3 class="text-base font-bold text-white mb-4">Scrape Job Status Overview</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <?php
      $statusStyle = [
        'queued'    => 'bg-slate-800/40 text-slate-200 border-slate-700/50',
        'running'   => 'bg-blue-100 text-blue-700 border-blue-200',
        'completed' => 'bg-green-100 text-green-700 border-green-200',
        'failed'    => 'bg-red-100 text-red-700 border-red-200',
        'cancelled' => 'bg-orange-100 text-orange-700 border-orange-200',
      ];
      foreach ($jobStatuses as $status => $count): ?>
      <div class="rounded-xl border p-4 <?= $statusStyle[$status] ?? 'bg-slate-800/40 text-slate-200 border-slate-700/50' ?>">
        <p class="text-xs font-semibold uppercase tracking-wide"><?= ucfirst(e($status)) ?></p>
        <p class="text-2xl font-extrabold mt-1"><?= number_format($count) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
  (function() {
    // Registration chart
    const regCtx = document.getElementById('regChart').getContext('2d');
    new Chart(regCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($regDays) ?>,
        datasets: [{
          label: 'New Users',
          data: <?= json_encode($regCounts) ?>,
          backgroundColor: 'rgba(99,102,241,0.15)',
          borderColor: 'rgba(99,102,241,0.8)',
          borderWidth: 2,
          borderRadius: 6,
          hoverBackgroundColor: 'rgba(99,102,241,0.3)',
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 } } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { precision: 0, font: { size: 11 } } }
        }
      }
    });

    // Plan distribution donut
    const planCtx = document.getElementById('planChart').getContext('2d');
    const planData = {
      free:   <?= (int)($planDist['free']   ?? 0) ?>,
      pro:    <?= (int)($planDist['pro']    ?? 0) ?>,
      agency: <?= (int)($planDist['agency'] ?? 0) ?>,
    };
    const total = planData.free + planData.pro + planData.agency;
    new Chart(planCtx, {
      type: 'doughnut',
      data: {
        labels: ['Free', 'Pro', 'Agency'],
        datasets: [{
          data: [planData.free, planData.pro, planData.agency],
          backgroundColor: ['#9ca3af','#6366f1','#8b5cf6'],
          borderColor: ['#fff','#fff','#fff'],
          borderWidth: 3,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                const pct = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                return ` ${ctx.label}: ${ctx.raw} (${pct}%)`;
              }
            }
          }
        }
      }
    });
  })();
  </script>

  <!-- ════════════════════════════════════════════════════════════ USERS TAB -->
  <?php elseif ($tab === 'users'): ?>

  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <!-- Table header / search -->
    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
      <div class="flex-1">
        <h3 class="text-base font-bold text-white">All Users</h3>
        <p class="text-xs text-slate-400 mt-0.5"><?= number_format($totalUserCount) ?> total users</p>
      </div>
      <form method="GET" action="<?= url('admin.php') ?>" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="users">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search users..."
            class="pl-9 pr-3 py-2 text-sm border border-slate-600/50 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none w-48">
        </div>
        <?php if ($hasPlan): ?>
        <select name="plan_filter" class="text-sm border border-slate-600/50 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
          <option value="">All plans</option>
          <option value="free" <?= $planFilter==='free'?'selected':'' ?>>Free</option>
          <option value="pro" <?= $planFilter==='pro'?'selected':'' ?>>Pro</option>
          <option value="agency" <?= $planFilter==='agency'?'selected':'' ?>>Agency</option>
        </select>
        <?php endif; ?>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
          Search
        </button>
        <?php if ($search || $planFilter): ?>
        <a href="<?= url('admin.php') ?>?tab=users" class="px-4 py-2 bg-slate-800/40 text-slate-300 text-sm font-semibold rounded-lg hover:bg-slate-700/50 transition">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Users Table -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-800/30 border-b border-gray-100">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">User</th>
            <?php if ($hasPlan): ?><th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Plan</th><?php endif; ?>
            <?php if ($hasRole): ?><th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Role</th><?php endif; ?>
            <?php if ($hasStatus): ?><th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th><?php endif; ?>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Credits Used</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Joined</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($users)): ?>
          <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400 text-sm">No users found.</td></tr>
          <?php else: foreach ($users as $u): ?>
          <tr class="hover:bg-slate-800/30 transition-colors" id="user-row-<?= $u['id'] ?>">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                  <span class="text-xs font-bold text-indigo-700"><?= strtoupper(substr($u['email'], 0, 1)) ?></span>
                </div>
                <div>
                  <?php if ($nameCol && !empty($u['display_name'])): ?>
                  <p class="font-semibold text-white text-sm"><?= e($u['display_name']) ?></p>
                  <?php endif; ?>
                  <p class="text-slate-400 text-xs"><?= e($u['email']) ?></p>
                </div>
              </div>
            </td>
            <?php if ($hasPlan): ?>
            <td class="px-4 py-3">
              <?php
              $planBadge = [
                'free'   => 'bg-slate-800/40 text-slate-300',
                'pro'    => 'bg-indigo-100 text-indigo-700',
                'agency' => 'bg-violet-100 text-violet-700',
              ];
              $plan = $u['plan'] ?? 'free';
              ?>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $planBadge[$plan] ?? 'bg-slate-800/40 text-slate-300' ?>"><?= ucfirst(e($plan)) ?></span>
            </td>
            <?php endif; ?>
            <?php if ($hasRole): ?>
            <td class="px-4 py-3">
              <span class="text-xs font-medium text-slate-200"><?= ucfirst(e($u['role'] ?? 'user')) ?></span>
            </td>
            <?php endif; ?>
            <?php if ($hasStatus): ?>
            <td class="px-4 py-3">
              <?php $st = $u['status'] ?? 'active'; ?>
              <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full
                <?= $st==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $st==='active' ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                <?= ucfirst(e($st)) ?>
              </span>
            </td>
            <?php endif; ?>
            <td class="px-4 py-3 text-slate-200 font-medium"><?= number_format((int)($u['credits_used_this_month'] ?? 0)) ?></td>
            <td class="px-4 py-3 text-slate-400 text-xs"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-1">
                <!-- Edit button triggers inline modal -->
                <button onclick="openEditModal(<?= htmlspecialchars(json_encode(['id'=>$u['id'],'plan'=>$u['plan']??'free','role'=>$u['role']??'user','email'=>$u['email'],'status'=>$u['status']??'active']), ENT_QUOTES) ?>)"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <?php if ($hasStatus): ?>
                <form method="POST" action="<?= url('admin.php') ?>?tab=users" class="inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="current_status" value="<?= e($u['status'] ?? 'active') ?>">
                  <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-orange-600 hover:bg-orange-50 transition" title="Toggle status">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                <form method="POST" action="<?= url('admin.php') ?>?tab=users" class="inline"
                      onsubmit="return confirm('Permanently delete this user and all their data?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
      <p class="text-xs text-slate-400">
        Showing <?= (($page-1) * (int)ITEMS_PER_PAGE) + 1 ?>–<?= min($page * (int)ITEMS_PER_PAGE, $totalUserCount) ?> of <?= number_format($totalUserCount) ?>
      </p>
      <div class="flex gap-1">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p > 3 && $p < $totalPages - 2 && abs($p - $page) > 2): ?>
            <?php if ($p === 4 || $p === $totalPages - 3): ?><span class="px-2 py-1 text-gray-400">…</span><?php endif; ?>
          <?php else: ?>
          <a href="<?= url('admin.php') ?>?tab=users&page=<?= $p ?>&q=<?= urlencode($search) ?>&plan_filter=<?= urlencode($planFilter) ?>"
             class="px-3 py-1.5 text-xs font-medium rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800/40' ?>">
            <?= $p ?>
          </a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Edit User Modal -->
  <div id="editModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEditModal()"></div>
    <div class="relative bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-md p-6">
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-bold text-white">Edit User</h3>
        <button onclick="closeEditModal()" class="p-1.5 rounded-lg text-gray-400 hover:bg-slate-800/40">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="POST" action="<?= url('admin.php') ?>?tab=users">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email</label>
            <input type="text" id="edit_user_email" readonly
              class="w-full px-3 py-2 text-sm bg-slate-800/30 border border-slate-700/50 rounded-lg text-slate-400 cursor-not-allowed">
          </div>
          <?php if ($hasPlan): ?>
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Plan</label>
            <select name="plan" id="edit_user_plan"
              class="w-full px-3 py-2 text-sm border border-slate-600/50 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="free">Free</option>
              <option value="pro">Pro</option>
              <option value="agency">Agency</option>
            </select>
          </div>
          <?php endif; ?>
          <?php if ($hasRole): ?>
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Role</label>
            <select name="role" id="edit_user_role"
              class="w-full px-3 py-2 text-sm border border-slate-600/50 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <?php endif; ?>
        </div>
        <div class="flex gap-3 mt-6">
          <button type="button" onclick="closeEditModal()"
            class="flex-1 px-4 py-2.5 text-sm font-semibold text-slate-200 bg-slate-800/40 rounded-xl hover:bg-slate-700/50 transition">
            Cancel
          </button>
          <button type="submit"
            class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-sm">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
  <script>
  function openEditModal(data) {
    document.getElementById('edit_user_id').value = data.id;
    document.getElementById('edit_user_email').value = data.email;
    const planEl = document.getElementById('edit_user_plan');
    if (planEl) planEl.value = data.plan || 'free';
    const roleEl = document.getElementById('edit_user_role');
    if (roleEl) roleEl.value = data.role || 'user';
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
  }
  function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
  }
  </script>

  <!-- ═══════════════════════════════════════════════════════ SETTINGS TAB -->
  <?php elseif ($tab === 'settings'): ?>

  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-bold text-white">System Settings</h3>
      <p class="text-xs text-slate-400 mt-0.5">Edit key-value configuration pairs stored in the database.</p>
    </div>
    <form method="POST" action="<?= url('admin.php') ?>?tab=settings">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_settings">
      <div class="divide-y divide-gray-50">
        <?php if (empty($settings)): ?>
        <div class="px-6 py-8 text-center text-gray-400 text-sm">No settings found. Add one below.</div>
        <?php else: foreach ($settings as $i => $s): ?>
        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
          <div class="sm:w-1/3">
            <input type="text" name="setting_key[]" value="<?= e($s['setting_key']) ?>"
              class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg bg-slate-800/30 focus:ring-2 focus:ring-indigo-500 outline-none font-mono text-slate-200"
              placeholder="setting_key">
          </div>
          <div class="sm:flex-1">
            <input type="text" name="setting_value[]" value="<?= e($s['setting_value'] ?? '') ?>"
              class="w-full px-3 py-2 text-sm border border-slate-600/50 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              placeholder="value">
          </div>
          <div class="text-xs text-gray-400 hidden sm:block whitespace-nowrap">
            Updated <?= $s['updated_at'] ? date('M j', strtotime($s['updated_at'])) : 'never' ?>
          </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- Add new setting row -->
        <div class="px-6 py-4 bg-indigo-50/50">
          <p class="text-xs font-semibold text-indigo-700 mb-3 uppercase tracking-wide">Add New Setting</p>
          <div class="flex flex-col sm:flex-row gap-3">
            <div class="sm:w-1/3">
              <input type="text" name="new_key" placeholder="new_setting_key"
                class="w-full px-3 py-2 text-sm border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-mono bg-slate-800/50">
            </div>
            <div class="sm:flex-1">
              <input type="text" name="new_value" placeholder="value"
                class="w-full px-3 py-2 text-sm border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-800/50">
            </div>
          </div>
        </div>
      </div>
      <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
        <button type="submit"
          class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition shadow-sm">
          Save All Settings
        </button>
      </div>
    </form>
  </div>

  <!-- ══════════════════════════════════════════════════════ BLOCKLIST TAB -->
  <?php elseif ($tab === 'blocklist'): ?>

  <!-- Add domain form -->
  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm p-6">
    <h3 class="text-base font-bold text-white mb-4">Add Domain to Blocklist</h3>
    <form method="POST" action="<?= url('admin.php') ?>?tab=blocklist" class="flex flex-col sm:flex-row gap-3">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_blocklist">
      <input type="text" name="domain" placeholder="example.com" required
        class="flex-1 px-3 py-2.5 text-sm border border-slate-600/50 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
      <input type="text" name="reason" placeholder="Reason (optional)"
        class="flex-1 px-3 py-2.5 text-sm border border-slate-600/50 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
      <button type="submit"
        class="px-5 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 transition flex-shrink-0">
        Block Domain
      </button>
    </form>
  </div>

  <!-- Blocklist table -->
  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-bold text-white">Blocked Domains</h3>
      <p class="text-xs text-slate-400 mt-0.5">Domains in this list are blocked at the scrape engine level.</p>
    </div>
    <?php if (empty($blocklist)): ?>
    <div class="px-6 py-12 text-center">
      <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
      <p class="text-sm text-gray-400">No domains blocked yet.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-800/30 border-b border-gray-100">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Domain</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Reason</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase">Added</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-400 uppercase">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($blocklist as $bl): ?>
          <tr class="hover:bg-slate-800/30 transition">
            <td class="px-4 py-3 font-mono text-sm text-slate-100"><?= e($bl['domain'] ?? '') ?></td>
            <td class="px-4 py-3 text-slate-400 text-sm"><?= e($bl['reason'] ?? '—') ?></td>
            <td class="px-4 py-3 text-gray-400 text-xs">
              <?= isset($bl['created_at']) ? date('M j, Y', strtotime($bl['created_at'])) : '—' ?>
            </td>
            <td class="px-4 py-3 text-right">
              <form method="POST" action="<?= url('admin.php') ?>?tab=blocklist" class="inline"
                    onsubmit="return confirm('Remove this domain from the blocklist?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="remove_blocklist">
                <input type="hidden" name="blocklist_id" value="<?= (int)$bl['id'] ?>">
                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline">
                  Remove
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═════════════════════════════════════════════════ ACTIVITY LOG TAB -->
  <?php elseif ($tab === 'activity'): ?>

  <div class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-bold text-white">Recent Activity</h3>
      <p class="text-xs text-slate-400 mt-0.5"><?= !empty($auditTables) ? 'Latest audit log entries' : 'Most recently registered users' ?></p>
    </div>
    <?php if (empty($recentActivity)): ?>
    <div class="px-6 py-12 text-center">
      <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      <p class="text-sm text-gray-400">No activity recorded yet.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
      <?php foreach ($recentActivity as $entry): ?>
      <div class="px-6 py-4 flex items-start gap-4 hover:bg-slate-800/30 transition">
        <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if (!empty($auditTables)): ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            <?php else: ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            <?php endif; ?>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <?php if (!empty($auditTables) && isset($entry['action'])): ?>
            <p class="text-sm font-semibold text-white"><?= e($entry['action'] ?? '') ?></p>
            <p class="text-xs text-slate-400 mt-0.5">
              <?= e($entry['user_email'] ?? 'System') ?>
              <?php if (!empty($entry['details'])): ?> — <?= e(substr($entry['details'], 0, 100)) ?><?php endif; ?>
            </p>
          <?php else: ?>
            <p class="text-sm font-semibold text-white">
              New user registered: <?= e($entry['email'] ?? '') ?>
            </p>
            <p class="text-xs text-slate-400 mt-0.5">
              <?php if ($nameCol && !empty($entry['display_name'])): ?><?= e($entry['display_name']) ?> · <?php endif; ?>
              <?php if (!empty($entry['plan'])): ?>
                <span class="font-medium"><?= ucfirst(e($entry['plan'])) ?></span> plan
              <?php endif; ?>
            </p>
          <?php endif; ?>
        </div>
        <div class="text-xs text-gray-400 flex-shrink-0">
          <?php
          $ts = strtotime($entry['created_at'] ?? 'now');
          $diff = time() - $ts;
          if ($diff < 60) echo $diff . 's ago';
          elseif ($diff < 3600) echo floor($diff/60) . 'm ago';
          elseif ($diff < 86400) echo floor($diff/3600) . 'h ago';
          else echo date('M j, Y', $ts);
          ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
