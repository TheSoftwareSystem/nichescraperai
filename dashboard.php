<?php
require_once 'config.php';
require_once 'includes/functions.php';
requireAuth();

$user = getCurrentUser();
$db = getDB();

// --- Real DB Stats ---
$totalJobs = (int)$db->query("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = " . (int)$user['id'])->fetchColumn();
$totalEmails = (int)$db->query("SELECT COUNT(*) FROM scraped_emails WHERE job_id IN (SELECT id FROM scrape_jobs WHERE user_id = " . (int)$user['id'] . ")")->fetchColumn();
$totalLists = (int)$db->query("SELECT COUNT(*) FROM lead_lists WHERE user_id = " . (int)$user['id'])->fetchColumn();
$runningJobs = (int)$db->query("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = " . (int)$user['id'] . " AND status = 'running'")->fetchColumn();

// Credits info
$creditsUsed = (int)($user['credits_used_this_month'] ?? 0);
$creditsLimit = 10; // Free tier default
if (($user['plan'] ?? 'free') === 'pro') $creditsLimit = 999;
if (($user['plan'] ?? 'free') === 'agency') $creditsLimit = 9999;

// Recent scrape jobs
$recentJobs = $db->query(
    "SELECT id, job_name, status, emails_found, pages_scraped, created_at 
     FROM scrape_jobs 
     WHERE user_id = " . (int)$user['id'] . " 
     ORDER BY created_at DESC 
     LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

// Recent emails extracted
$recentEmails = $db->query(
    "SELECT se.email, se.source_url, se.domain, se.is_role_based, se.created_at 
     FROM scraped_emails se 
     INNER JOIN scrape_jobs sj ON se.job_id = sj.id 
     WHERE sj.user_id = " . (int)$user['id'] . " 
     ORDER BY se.created_at DESC 
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$flash = getFlash();

function statusBadge($status) {
    $map = [
        'queued'    => 'bg-slate-100 text-slate-600',
        'running'   => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'failed'    => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-orange-100 text-orange-700',
    ];
    $cls = $map[$status] ?? 'bg-slate-800/40 text-slate-300';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' . $cls . '">'
        . ucfirst(htmlspecialchars($status)) . '</span>';
}

function planBadge($plan) {
    $map = [
        'free'   => 'bg-slate-100 text-slate-600 border border-slate-300',
        'pro'    => 'bg-indigo-100 text-indigo-700 border border-indigo-300',
        'agency' => 'bg-amber-100 text-amber-700 border border-amber-300',
    ];
    $cls = $map[$plan] ?? 'bg-slate-100 text-slate-600';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold ' . $cls . '">'
        . strtoupper(htmlspecialchars($plan)) . '</span>';
}

require_once 'includes/header.php';
?>

<div class="space-y-8">

  <?php if ($flash): ?>
    <div class="rounded-xl px-4 py-3 text-sm font-medium <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">
        Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
        <?= e(explode(' ', $user['name'] ?? 'Marketer')[0]) ?> 👋
      </h1>
      <p class="mt-1 text-sm text-slate-500">Here's what's happening with your scrape campaigns today.</p>
    </div>
    <div class="flex items-center gap-3">
      <?= planBadge($user['plan'] ?? 'free') ?>
      <a href="<?= url('contacts.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all duration-200 hover:shadow-md">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        New Scrape Job
      </a>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

    <!-- Total Jobs -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
      <div class="flex items-center justify-between mb-4">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
        </div>
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">All Time</span>
      </div>
      <div class="text-3xl font-extrabold text-slate-900 mb-1"><?= number_format($totalJobs) ?></div>
      <div class="text-sm font-medium text-slate-500">Total Scrape Jobs</div>
      <?php if ($runningJobs > 0): ?>
        <div class="mt-3 flex items-center gap-1.5 text-xs text-blue-600 font-medium">
          <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
          <?= $runningJobs ?> running now
        </div>
      <?php endif; ?>
    </div>

    <!-- Emails Extracted -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
      <div class="flex items-center justify-between mb-4">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
        </div>
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Extracted</span>
      </div>
      <div class="text-3xl font-extrabold text-slate-900 mb-1"><?= number_format($totalEmails) ?></div>
      <div class="text-sm font-medium text-slate-500">Total Emails Found</div>
      <div class="mt-3 text-xs text-slate-400">Across all scrape jobs</div>
    </div>

    <!-- Lead Lists -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
      <div class="flex items-center justify-between mb-4">
        <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
        </div>
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Organized</span>
      </div>
      <div class="text-3xl font-extrabold text-slate-900 mb-1"><?= number_format($totalLists) ?></div>
      <div class="text-sm font-medium text-slate-500">Named Lead Lists</div>
      <div class="mt-3 text-xs text-slate-400">Segmented &amp; deduplicated</div>
    </div>

    <!-- Credits -->
    <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
      <div class="flex items-center justify-between mb-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
        </div>
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">This Month</span>
      </div>
      <div class="text-3xl font-extrabold text-slate-900 mb-1">
        <?php if (($user['plan'] ?? 'free') === 'free'): ?>
          <?= $creditsUsed ?><span class="text-base font-medium text-slate-400">/<?= $creditsLimit ?></span>
        <?php else: ?>
          <span class="text-emerald-600">&infin;</span>
        <?php endif; ?>
      </div>
      <div class="text-sm font-medium text-slate-500">Credits Used</div>
      <?php if (($user['plan'] ?? 'free') === 'free'): ?>
        <div class="mt-3">
          <div class="w-full bg-slate-100 rounded-full h-1.5">
            <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($creditsUsed / $creditsLimit) * 100) ?>%"></div>
          </div>
          <div class="mt-1.5 text-xs text-slate-400"><?= $creditsLimit - $creditsUsed ?> remaining — <a href="<?= url('settings.php') ?>" class="text-indigo-600 hover:underline">Upgrade</a></div>
        </div>
      <?php else: ?>
        <div class="mt-3 text-xs text-emerald-600 font-medium">Unlimited on <?= ucfirst($user['plan']) ?> plan</div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Main Content Grid -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Recent Scrape Jobs -->
    <div class="xl:col-span-2 bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold text-slate-900">Recent Scrape Jobs</h2>
          <p class="text-xs text-slate-400 mt-0.5">Your latest extraction runs</p>
        </div>
        <a href="<?= url('contacts.php') ?>" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">View all &rarr;</a>
      </div>

      <?php if (empty($recentJobs)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-center px-6">
          <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
          </div>
          <h3 class="text-sm font-semibold text-slate-700 mb-1">No scrape jobs yet</h3>
          <p class="text-xs text-slate-400 mb-4 max-w-xs">Submit your first URL and let the engine extract targeted, publicly visible emails from your niche community.</p>
          <a href="<?= url('contacts.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            Start Your First Scrape
          </a>
        </div>
      <?php else: ?>
        <div class="divide-y divide-slate-50">
          <?php foreach ($recentJobs as $job): ?>
            <div class="px-6 py-4 hover:bg-slate-50 transition-colors">
              <div class="flex items-center gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm font-semibold text-slate-900 truncate"><?= e($job['job_name'] ?? 'Untitled Job') ?></span>
                    <?= statusBadge($job['status'] ?? 'queued') ?>
                  </div>
                  <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span class="flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                      <?= number_format((int)($job['emails_found'] ?? 0)) ?> emails
                    </span>
                    <span class="flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                      <?= number_format((int)($job['pages_scraped'] ?? 0)) ?> pages
                    </span>
                    <span><?= isset($job['created_at']) ? timeAgo($job['created_at']) : '' ?></span>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <?php if (($job['status'] ?? '') === 'running'): ?>
                    <div class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>
                  <?php endif; ?>
                  <a href="<?= url('contacts.php') ?>?job=<?= (int)$job['id'] ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors">View &rarr;</a>
                </div>
              </div>
              <?php if (($job['status'] ?? '') === 'running' && isset($job['progress'])): ?>
                <div class="mt-2">
                  <div class="w-full bg-slate-100 rounded-full h-1">
                    <div class="bg-blue-500 h-1 rounded-full transition-all" style="width: <?= (int)$job['progress'] ?>%"></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div class="space-y-5">

      <!-- Quick Actions -->
      <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-bold text-slate-900 mb-4">Quick Actions</h2>
        <div class="space-y-2">
          <a href="<?= url('contacts.php') ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-indigo-50 hover:border-indigo-200 border border-transparent transition-all duration-200 group">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            </div>
            <div>
              <div class="text-sm font-semibold text-slate-800">New Scrape Job</div>
              <div class="text-xs text-slate-400">Extract emails from any URL</div>
            </div>
          </a>

          <a href="<?= url('contacts.php') ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-violet-50 hover:border-violet-200 border border-transparent transition-all duration-200 group">
            <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
            </div>
            <div>
              <div class="text-sm font-semibold text-slate-800">Manage Lead Lists</div>
              <div class="text-xs text-slate-400">Organize &amp; merge your emails</div>
            </div>
          </a>

          <a href="<?= url('settings.php') ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100 hover:border-slate-200 border border-transparent transition-all duration-200 group">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-slate-200 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <div>
              <div class="text-sm font-semibold text-slate-800">Account Settings</div>
              <div class="text-xs text-slate-400">Proxies, notifications, billing</div>
            </div>
          </a>

          <?php if (($user['plan'] ?? 'free') !== 'free'): ?>
          <a href="<?= url('user-refresh-tokens.php') ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-emerald-50 hover:border-emerald-200 border border-transparent transition-all duration-200 group">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
            </div>
            <div>
              <div class="text-sm font-semibold text-slate-800">API Keys</div>
              <div class="text-xs text-slate-400">Programmatic access &amp; webhooks</div>
            </div>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recently Extracted Emails -->
      <div class="bg-slate-800/50 rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
          <h2 class="text-base font-bold text-slate-900">Latest Emails Found</h2>
          <p class="text-xs text-slate-400 mt-0.5">Most recently extracted addresses</p>
        </div>
        <?php if (empty($recentEmails)): ?>
          <div class="py-8 text-center px-4">
            <p class="text-sm text-slate-400">No emails extracted yet. Run your first scrape job!</p>
          </div>
        <?php else: ?>
          <div class="divide-y divide-slate-50">
            <?php foreach ($recentEmails as $email): ?>
              <div class="px-6 py-3 hover:bg-slate-50 transition-colors">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <span class="text-sm font-medium text-slate-900 truncate"><?= e($email['email']) ?></span>
                      <?php if ($email['is_role_based']): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-50 text-amber-600 font-medium border border-amber-200 flex-shrink-0">role</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-400 mt-0.5 truncate"><?= e($email['domain'] ?? '') ?></div>
                  </div>
                  <span class="text-xs text-slate-300 flex-shrink-0 mt-0.5"><?= isset($email['created_at']) ? timeAgo($email['created_at']) : '' ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Plan Upgrade Banner (Free Tier Only) -->
      <?php if (($user['plan'] ?? 'free') === 'free'): ?>
      <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-6 text-white">
        <div class="flex items-center gap-2 mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
          <span class="text-sm font-bold">Unlock Pro</span>
        </div>
        <p class="text-xs text-indigo-200 mb-4 leading-relaxed">Unlimited scrape jobs, scheduling, API access, and residential proxy rotation. The full engine — uncapped.</p>
        <a href="<?= url('settings.php') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800/50 text-indigo-700 text-xs font-bold rounded-xl hover:bg-indigo-50 transition-colors shadow-sm">
          Upgrade to Pro &rarr;
        </a>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Platform Stats / Info Bar -->
  <div class="rounded-2xl bg-slate-50 border border-slate-200 p-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
      <div class="text-center">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Deobfuscation Patterns</div>
        <div class="text-2xl font-extrabold text-slate-900">6+</div>
        <div class="text-xs text-slate-500 mt-1">[at], (at), AT, Unicode, ROT13, base64</div>
      </div>
      <div class="text-center border-x border-slate-200">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Target Platforms</div>
        <div class="text-2xl font-extrabold text-slate-900">5+</div>
        <div class="text-xs text-slate-500 mt-1">Reddit, phpBB, Discourse, vBulletin &amp; more</div>
      </div>
      <div class="text-center">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Avg. Scrape Success</div>
        <div class="text-2xl font-extrabold text-emerald-600">&gt;85%</div>
        <div class="text-xs text-slate-500 mt-1">With proxy rotation &amp; stealth engine</div>
      </div>
    </div>
  </div>

</div>

<script>
// Animated stat counters
document.addEventListener('DOMContentLoaded', function() {
  const counters = document.querySelectorAll('[data-counter]');
  counters.forEach(function(el) {
    const target = parseInt(el.getAttribute('data-counter'), 10);
    if (isNaN(target)) return;
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(function() {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString();
      if (current >= target) clearInterval(timer);
    }, 30);
  });
});

// Auto-refresh running jobs badge every 10 seconds
<?php if ($runningJobs > 0): ?>
setTimeout(function() { window.location.reload(); }, 10000);
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
