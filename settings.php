<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireAuth();

$db = getDB();
$user = getCurrentUser();
$flash = getFlash();

// Detect columns
$cols = array_column($db->query('DESCRIBE users')->fetchAll(), 'Field');
$pwCol = in_array('password_hash', $cols) ? 'password_hash' : 'password';
$nameCol = in_array('first_name', $cols) ? 'first_name' : (in_array('name', $cols) ? 'name' : null);

// Reload fresh user data
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$isAdmin = isset($user['role']) && $user['role'] === 'admin';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $newName  = trim($_POST['name'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        if (empty($newName) || empty($newEmail)) {
            setFlash('error', 'Name and email are required.');
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
        } else {
            // Check duplicate email
            $check = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $check->execute([$newEmail, $user['id']]);
            if ($check->fetch()) {
                setFlash('error', 'That email is already in use by another account.');
            } else {
                $updates = ['email = ?', 'updated_at = NOW()'];
                $params  = [$newEmail];
                if ($nameCol) {
                    $updates[] = "{$nameCol} = ?";
                    $params[] = $newName;
                }
                $params[] = $user['id'];
                $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
                setFlash('success', 'Profile updated successfully.');
            }
        }
        header('Location: ' . url('settings.php') . '#profile'); exit;
    }

    if ($section === 'password') {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';
        if (!password_verify($currentPw, $user[$pwCol])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($newPw) < 8) {
            setFlash('error', 'New password must be at least 8 characters.');
        } elseif ($newPw !== $confirmPw) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE users SET {$pwCol} = ?, updated_at = NOW() WHERE id = ?")->execute([$hash, $user['id']]);
            setFlash('success', 'Password changed successfully.');
        }
        header('Location: ' . url('settings.php') . '#password'); exit;
    }

    if ($section === 'preferences') {
        $notifyEmail   = isset($_POST['notify_email']) ? 1 : 0;
        $notifyWebhook = isset($_POST['notify_webhook']) ? 1 : 0;
        $webhookUrl    = trim($_POST['webhook_url'] ?? '');
        $updates = [];
        $params  = [];
        if (in_array('notify_email', $cols)) {
            $updates[] = 'notify_email = ?'; $params[] = $notifyEmail;
        }
        if (in_array('notify_webhook', $cols)) {
            $updates[] = 'notify_webhook = ?'; $params[] = $notifyWebhook;
        }
        if (in_array('webhook_url', $cols)) {
            $updates[] = 'webhook_url = ?'; $params[] = $webhookUrl;
        }
        if (!empty($updates)) {
            $updates[] = 'updated_at = NOW()';
            $params[]  = $user['id'];
            $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
        }
        setFlash('success', 'Preferences saved.');
        header('Location: ' . url('settings.php') . '#preferences'); exit;
    }

    if ($section === 'app_settings' && $isAdmin) {
        $keys   = $_POST['setting_key'] ?? [];
        $values = $_POST['setting_value'] ?? [];
        foreach ($keys as $i => $k) {
            $k = trim($k);
            $v = trim($values[$i] ?? '');
            if ($k === '') continue;
            $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()')
               ->execute([$k, $v, $v]);
        }
        // Handle new key/value from form
        $newKey = trim($_POST['new_key'] ?? '');
        $newVal = trim($_POST['new_val'] ?? '');
        if ($newKey !== '') {
            $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()')
               ->execute([$newKey, $newVal, $newVal]);
        }
        setFlash('success', 'App settings saved.');
        header('Location: ' . url('settings.php') . '#app-settings'); exit;
    }

    if ($section === 'delete_setting' && $isAdmin) {
        $delKey = trim($_POST['delete_key'] ?? '');
        if ($delKey) {
            $db->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$delKey]);
            setFlash('success', 'Setting deleted.');
        }
        header('Location: ' . url('settings.php') . '#app-settings'); exit;
    }
}

// Load settings for admin
$appSettings = [];
if ($isAdmin) {
    $appSettings = $db->query('SELECT * FROM settings ORDER BY setting_key ASC')->fetchAll(PDO::FETCH_ASSOC);
}

$displayName = $nameCol ? ($user[$nameCol] ?? '') : '';

require_once 'includes/header.php';
?>

<div class="max-w-3xl space-y-8">
  <div>
    <h1 class="text-2xl font-bold text-white">Settings</h1>
    <p class="text-sm text-slate-400 mt-1">Manage your account profile, preferences, and app configuration.</p>
  </div>

  <?php if ($flash): ?>
  <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <!-- Profile Section -->
  <div id="profile" class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-white">Profile</h2>
        <p class="text-xs text-slate-400">Update your name and email address.</p>
      </div>
    </div>
    <form method="POST" action="<?= url('settings.php') ?>" class="p-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="section" value="profile">
      <div class="flex items-center gap-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold flex-shrink-0">
          <?= strtoupper(substr($displayName ?: $user['email'], 0, 1)) ?>
        </div>
        <div>
          <p class="text-sm font-medium text-white"><?= e($displayName) ?></p>
          <p class="text-xs text-gray-400"><?= e($user['email']) ?></p>
          <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700"><?= ucfirst(e($user['plan'] ?? 'free')) ?> Plan</span>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Full Name</label>
          <input type="text" name="name" value="<?= e($displayName) ?>" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Email Address</label>
          <input type="email" name="email" value="<?= e($user['email']) ?>" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">Save Profile</button>
      </div>
    </form>
  </div>

  <!-- Password Section -->
  <div id="password" class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-white">Change Password</h2>
        <p class="text-xs text-slate-400">Use a strong password at least 8 characters long.</p>
      </div>
    </div>
    <form method="POST" action="<?= url('settings.php') ?>" class="p-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="section" value="password">
      <div>
        <label class="block text-sm font-medium text-slate-200 mb-1">Current Password</label>
        <div class="relative">
          <input type="password" name="current_password" id="currentPw" class="w-full px-3 py-2 pr-10 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <button type="button" onclick="togglePw('currentPw',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-slate-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">New Password</label>
          <div class="relative">
            <input type="password" name="new_password" id="newPw" oninput="checkStrength(this.value)" class="w-full px-3 py-2 pr-10 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="button" onclick="togglePw('newPw',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-slate-300">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
          <div class="mt-2">
            <div class="flex gap-1">
              <div id="str1" class="h-1 flex-1 rounded-full bg-slate-700/50 transition-colors"></div>
              <div id="str2" class="h-1 flex-1 rounded-full bg-slate-700/50 transition-colors"></div>
              <div id="str3" class="h-1 flex-1 rounded-full bg-slate-700/50 transition-colors"></div>
              <div id="str4" class="h-1 flex-1 rounded-full bg-slate-700/50 transition-colors"></div>
            </div>
            <p id="strLabel" class="text-xs text-gray-400 mt-1"></p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-200 mb-1">Confirm New Password</label>
          <input type="password" name="confirm_password" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-lg bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition-colors shadow-sm">Change Password</button>
      </div>
    </form>
  </div>

  <!-- Preferences Section -->
  <div id="preferences" class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-white">Notifications & Preferences</h2>
        <p class="text-xs text-slate-400">Control how you receive job completion alerts.</p>
      </div>
    </div>
    <form method="POST" action="<?= url('settings.php') ?>" class="p-6 space-y-5">
      <?= csrfField() ?>
      <input type="hidden" name="section" value="preferences">
      <?php if (in_array('notify_email', $cols)): ?>
      <div class="flex items-center justify-between py-1">
        <div>
          <p class="text-sm font-medium text-slate-100">Email Notifications</p>
          <p class="text-xs text-gray-400">Receive an email when scrape jobs complete or fail.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="notify_email" value="1" <?= ($user['notify_email'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
          <div class="w-11 h-6 bg-slate-700/50 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-800/50 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
        </label>
      </div>
      <?php endif; ?>
      <?php if (in_array('notify_webhook', $cols)): ?>
      <div class="flex items-center justify-between py-1">
        <div>
          <p class="text-sm font-medium text-slate-100">Webhook Notifications</p>
          <p class="text-xs text-gray-400">POST job results to your webhook URL on completion.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="notify_webhook" value="1" <?= ($user['notify_webhook'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
          <div class="w-11 h-6 bg-slate-700/50 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-800/50 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
        </label>
      </div>
      <?php endif; ?>
      <?php if (in_array('webhook_url', $cols)): ?>
      <div>
        <label class="block text-sm font-medium text-slate-200 mb-1">Webhook URL</label>
        <input type="url" name="webhook_url" value="<?= e($user['webhook_url'] ?? '') ?>" placeholder="https://your-server.com/webhook" class="w-full px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p class="text-xs text-gray-400 mt-1">We'll POST job completion summaries as JSON to this URL.</p>
      </div>
      <?php endif; ?>
      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors shadow-sm">Save Preferences</button>
      </div>
    </form>
  </div>

  <?php if ($isAdmin): ?>
  <!-- App Settings Section (Admin Only) -->
  <div id="app-settings" class="bg-slate-800/50 rounded-2xl border border-slate-700/50 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center">
        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-white">App Settings <span class="ml-2 text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Admin</span></h2>
        <p class="text-xs text-slate-400">Manage global key-value configuration from the database.</p>
      </div>
    </div>
    <form method="POST" action="<?= url('settings.php') ?>" class="p-6 space-y-3">
      <?= csrfField() ?>
      <input type="hidden" name="section" value="app_settings">
      <?php if (empty($appSettings)): ?>
        <p class="text-sm text-gray-400 italic">No settings configured yet. Add one below.</p>
      <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($appSettings as $setting): ?>
          <div class="flex items-center gap-3 p-3 bg-slate-800/30 rounded-xl">
            <input type="text" name="setting_key[]" value="<?= e($setting['setting_key']) ?>" class="flex-1 px-3 py-1.5 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono bg-slate-800/50">
            <?php $boolVal = in_array(strtolower($setting['setting_value'] ?? ''), ['true','1','yes','on']); ?>
            <?php if (in_array($setting['setting_value'], ['true','false','1','0','yes','no','on','off'])): ?>
              <select name="setting_value[]" class="px-3 py-1.5 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
                <option value="true" <?= $boolVal ? 'selected' : '' ?>>true</option>
                <option value="false" <?= !$boolVal ? 'selected' : '' ?>>false</option>
              </select>
            <?php else: ?>
              <input type="text" name="setting_value[]" value="<?= e($setting['setting_value'] ?? '') ?>" class="flex-2 flex-grow px-3 py-1.5 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-800/50">
            <?php endif; ?>
            <form method="POST" action="<?= url('settings.php') ?>" class="inline" onsubmit="return confirm('Delete this setting?')">
              <?= csrfField() ?>
              <input type="hidden" name="section" value="delete_setting">
              <input type="hidden" name="delete_key" value="<?= e($setting['setting_key']) ?>">
              <button type="submit" class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-4 p-4 border border-dashed border-slate-700/50 rounded-xl">
        <p class="text-xs font-medium text-slate-400 mb-3 uppercase tracking-wider">Add New Setting</p>
        <div class="flex gap-3">
          <input type="text" name="new_key" placeholder="setting_key" class="flex-1 px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
          <input type="text" name="new_val" placeholder="value" class="flex-1 px-3 py-2 text-sm border border-slate-700/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="px-5 py-2 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700 transition-colors shadow-sm">Save App Settings</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Danger Zone -->
  <div class="bg-slate-800/50 rounded-2xl border border-red-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-red-50">
      <h2 class="text-base font-semibold text-red-700">Danger Zone</h2>
      <p class="text-xs text-slate-400 mt-0.5">Permanent actions that cannot be undone.</p>
    </div>
    <div class="p-6 flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-slate-100">Delete Account</p>
        <p class="text-xs text-gray-400">Permanently remove your account and all associated data.</p>
      </div>
      <button onclick="alert('Please contact support to delete your account.')" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 transition-colors">Delete Account</button>
    </div>
  </div>
</div>

<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}
function checkStrength(pw) {
  let score = 0;
  if (pw.length >= 8) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
  const labels = ['Weak','Fair','Good','Strong'];
  const textColors = ['text-red-500','text-orange-500','text-yellow-600','text-green-600'];
  for (let i = 1; i <= 4; i++) {
    const el = document.getElementById('str' + i);
    if (i <= score) { el.className = 'h-1 flex-1 rounded-full transition-colors ' + colors[score-1]; }
    else { el.className = 'h-1 flex-1 rounded-full transition-colors bg-slate-700/50'; }
  }
  const label = document.getElementById('strLabel');
  if (pw.length === 0) { label.textContent = ''; label.className = 'text-xs mt-1'; }
  else { label.textContent = labels[score-1] || 'Weak'; label.className = 'text-xs mt-1 ' + (textColors[score-1] || 'text-red-500'); }
}
</script>

<?php require_once 'includes/footer.php'; ?>