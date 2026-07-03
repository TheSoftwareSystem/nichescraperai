<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireAuth();

$db = getDB();
$user = getCurrentUser();
$flash = getFlash();

// Ensure contacts table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        company VARCHAR(150),
        notes TEXT,
        tags VARCHAR(500),
        status ENUM('lead','active','inactive','customer') NOT NULL DEFAULT 'lead',
        source_url VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_GET['action'] ?? 'list';
$contactId = (int)($_GET['id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'edit') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');
        $tags    = trim($_POST['tags'] ?? '');
        $status  = $_POST['status'] ?? 'lead';
        $validStatuses = ['lead','active','inactive','customer'];
        if (!in_array($status, $validStatuses)) $status = 'lead';

        if (empty($name)) {
            setFlash('error', 'Contact name is required.');
        } else {
            if ($postAction === 'create') {
                $stmt = $db->prepare('INSERT INTO contacts (user_id, name, email, phone, company, notes, tags, status) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$user['id'], $name, $email, $phone, $company, $notes, $tags, $status]);
                setFlash('success', 'Contact added successfully.');
            } else {
                $editId = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('UPDATE contacts SET name=?, email=?, phone=?, company=?, notes=?, tags=?, status=?, updated_at=NOW() WHERE id=? AND user_id=?');
                $stmt->execute([$name, $email, $phone, $company, $notes, $tags, $status, $editId, $user['id']]);
                setFlash('success', 'Contact updated successfully.');
            }
        }
        header('Location: ' . url('contacts.php')); exit;
    }

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM contacts WHERE id=? AND user_id=?');
        $stmt->execute([$delId, $user['id']]);
        setFlash('success', 'Contact deleted.');
        header('Location: ' . url('contacts.php')); exit;
    }

    if ($postAction === 'csv_import') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmpFile, 'r');
            $headers = fgetcsv($handle);
            $headers = array_map('strtolower', array_map('trim', $headers));
            $inserted = 0;
            $skipped = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine(count($headers) === count($row) ? $headers : array_slice($headers, 0, count($row)), $row);
                $cName  = trim($data['name'] ?? $data['full name'] ?? '');
                $cEmail = trim($data['email'] ?? $data['email address'] ?? '');
                $cPhone = trim($data['phone'] ?? $data['phone number'] ?? '');
                $cComp  = trim($data['company'] ?? $data['organization'] ?? '');
                $cNotes = trim($data['notes'] ?? '');
                $cTags  = trim($data['tags'] ?? '');
                $cStatus = in_array($data['status'] ?? '', ['lead','active','inactive','customer']) ? $data['status'] : 'lead';
                if (empty($cName) && empty($cEmail)) { $skipped++; continue; }
                if (empty($cName)) $cName = $cEmail;
                $stmt = $db->prepare('INSERT INTO contacts (user_id, name, email, phone, company, notes, tags, status) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$user['id'], $cName, $cEmail, $cPhone, $cComp, $cNotes, $cTags, $cStatus]);
                $inserted++;
            }
            fclose($handle);
            setFlash('success', "CSV import complete: {$inserted} contacts added, {$skipped} rows skipped.");
        } else {
            setFlash('error', 'Failed to upload CSV file.');
        }
        header('Location: ' . url('contacts.php')); exit;
    }
}

// Fetch contact for editing
$editContact = null;
if ($action === 'edit' && $contactId) {
    $stmt = $db->prepare('SELECT * FROM contacts WHERE id=? AND user_id=?');
    $stmt->execute([$contactId, $user['id']]);
    $editContact = $stmt->fetch(PDO::FETCH_ASSOC);
}

// List with search/filter/pagination
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * (int)ITEMS_PER_PAGE;

$where = 'WHERE c.user_id = ?';
$params = [$user['id']];
if ($search !== '') {
    $where .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.tags LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($filterStatus !== '') {
    $where .= ' AND c.status = ?';
    $params[] = $filterStatus;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM contacts c {$where}");
$countStmt->execute($params);
$totalContacts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalContacts / (int)ITEMS_PER_PAGE));

$listStmt = $db->prepare("SELECT * FROM contacts c {$where} ORDER BY c.created_at DESC LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset);
$listStmt->execute($params);
$contacts = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'lead'     => 'bg-blue-100 text-blue-700',
    'active'   => 'bg-green-100 text-green-700',
    'inactive' => 'bg-slate-800/40 text-slate-300',
    'customer' => 'bg-purple-100 text-purple-700',
];

require_once 'includes/header.php';
?>

<div class="space-y-6">
  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-white">Contacts</h1>
      <p class="text-sm text-slate-400 mt-1">Manage extracted leads and contacts from your scrape jobs.</p>
    </div>
    <div class="flex items-center gap-2">
      <button onclick="document.getElementById('csvModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-600/50 bg-slate-800/50 text-sm font-medium text-slate-200 hover:bg-slate-800/30 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import CSV
      </button>
      <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Contact
      </button>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <!-- Search & Filter -->
  <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 shadow-sm p-4">
    <form method="GET" action="<?= url('contacts.php') ?>" class="flex flex-col sm:flex-row gap-3">
      <div class="flex-1 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search contacts by name, email, company..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <select name="status" class="px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
        <option value="">All Statuses</option>
        <?php foreach (['lead','active','inactive','customer'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Filter</button>
      <?php if ($search || $filterStatus): ?>
        <a href="<?= url('contacts.php') ?>" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors text-center">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Contacts Table -->
  <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <span class="text-sm text-slate-400"><?= number_format($totalContacts) ?> contact<?= $totalContacts !== 1 ? 's' : '' ?></span>
    </div>
    <?php if (empty($contacts)): ?>
      <div class="flex flex-col items-center justify-center py-16 text-center">
        <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <p class="text-slate-400 font-medium">No contacts found</p>
        <p class="text-gray-400 text-sm mt-1">Add your first contact or import a CSV to get started.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-slate-800/30">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Company</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tags</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Added</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($contacts as $c): ?>
          <tr class="hover:bg-slate-800/30 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold flex-shrink-0">
                  <?= strtoupper(substr($c['name'], 0, 1)) ?>
                </div>
                <div>
                  <p class="text-sm font-medium text-white"><?= e($c['name']) ?></p>
                  <?php if ($c['phone']): ?><p class="text-xs text-gray-400"><?= e($c['phone']) ?></p><?php endif; ?>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-300"><?= e($c['email']) ?></td>
            <td class="px-4 py-3 text-sm text-slate-300"><?= e($c['company']) ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$c['status']] ?? 'bg-slate-800/40 text-slate-300' ?>">
                <?= ucfirst(e($c['status'])) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <?php if ($c['tags']): ?>
                <?php foreach (array_filter(array_map('trim', explode(',', $c['tags']))) as $tag): ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 mr-1"><?= e($tag) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button onclick="openEdit(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium px-2 py-1 rounded hover:bg-indigo-50 transition-colors">Edit</button>
                <form method="POST" action="<?= url('contacts.php') ?>" onsubmit="return confirm('Delete this contact?')" class="inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="<?= url('contacts.php') ?>?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>" class="px-3 py-1 text-sm rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800/40' ?> transition-colors"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create Contact Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-white">Add New Contact</h2>
      <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400 hover:text-slate-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="<?= url('contacts.php') ?>" class="p-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Email</label>
          <input type="email" name="email" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Phone</label>
          <input type="text" name="phone" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Company</label>
          <input type="text" name="company" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Status</label>
          <select name="status" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
            <option value="lead">Lead</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="customer">Customer</option>
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Tags <span class="text-gray-400 text-xs">(comma-separated)</span></label>
          <input type="text" name="tags" placeholder="e.g. forum-lead, niche-seo, high-intent" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Notes</label>
          <textarea name="notes" rows="3" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Add Contact</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Contact Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-white">Edit Contact</h2>
      <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-slate-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="<?= url('contacts.php') ?>" class="p-6 space-y-4" id="editForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" id="editName" required class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Email</label>
          <input type="email" name="email" id="editEmail" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Phone</label>
          <input type="text" name="phone" id="editPhone" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Company</label>
          <input type="text" name="company" id="editCompany" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Status</label>
          <select name="status" id="editStatus" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
            <option value="lead">Lead</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="customer">Customer</option>
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Tags</label>
          <input type="text" name="tags" id="editTags" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-200 mb-1">Notes</label>
          <textarea name="notes" id="editNotes" rows="3" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- CSV Import Modal -->
<div id="csvModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-slate-800/50 rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-white">Import CSV</h2>
      <button onclick="document.getElementById('csvModal').classList.add('hidden')" class="text-gray-400 hover:text-slate-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="<?= url('contacts.php') ?>" enctype="multipart/form-data" class="p-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="csv_import">
      <div class="bg-indigo-50 rounded-lg p-4 text-sm text-indigo-700">
        <p class="font-medium mb-1">Expected CSV columns:</p>
        <p class="text-indigo-600 text-xs font-mono">name, email, phone, company, notes, tags, status</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-200 mb-2">Upload CSV File</label>
        <div class="border-2 border-dashed border-slate-700/50 rounded-xl p-6 text-center cursor-pointer hover:border-indigo-400 transition-colors" onclick="document.getElementById('csvInput').click()">
          <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          <p class="text-sm text-slate-400">Click to select CSV file</p>
          <p class="text-xs text-gray-400 mt-1" id="csvFileName">No file selected</p>
        </div>
        <input type="file" name="csv_file" id="csvInput" accept=".csv" class="hidden" onchange="document.getElementById('csvFileName').textContent = this.files[0]?.name || 'No file selected'">
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="document.getElementById('csvModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/30 transition-colors">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">Import Contacts</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(contact) {
  document.getElementById('editId').value = contact.id;
  document.getElementById('editName').value = contact.name || '';
  document.getElementById('editEmail').value = contact.email || '';
  document.getElementById('editPhone').value = contact.phone || '';
  document.getElementById('editCompany').value = contact.company || '';
  document.getElementById('editStatus').value = contact.status || 'lead';
  document.getElementById('editTags').value = contact.tags || '';
  document.getElementById('editNotes').value = contact.notes || '';
  document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>