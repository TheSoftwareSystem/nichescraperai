<?php $currentUser = getCurrentUser(); $flash = getFlash(); ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) : SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#8B5CF6',secondary:'#06B6D4',accent:'#10B981'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(30,41,59,0.5); backdrop-filter: blur(8px); border: 1px solid rgba(51,65,85,0.5); }
        .glass-card:hover { border-color: #8B5CF64D; box-shadow: 0 0 20px #8B5CF60D; }
        .btn-primary { background: #10B981; color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .sidebar { width: 260px; transition: width 0.3s ease; }
        .sidebar.collapsed { width: 72px; }
        .sidebar.collapsed .sidebar-label { display: none; }
        .sidebar.collapsed .sidebar-brand-text { display: none; }
        .main-content { margin-left: 260px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 72px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; width: 260px; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen text-slate-300">

<?php if (isLoggedIn()): ?>
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="document.getElementById('sidebar').classList.remove('mobile-open');this.classList.add('hidden');"></div>

<aside id="sidebar" class="sidebar fixed top-0 left-0 h-full bg-slate-800 border-r border-slate-700/50 flex flex-col z-50">
    <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-700/50">
        <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center flex-shrink-0">
            <span class="text-white font-bold text-sm"><?= strtoupper(substr(SITE_NAME, 0, 2)) ?></span>
        </div>
        <span class="sidebar-brand-text text-white font-semibold text-sm truncate"><?= e(SITE_NAME) ?></span>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <a href="<?= url('dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-primary/20 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"></path></svg>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="<?= url('user-refresh-tokens.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) === 'user-refresh-tokens.php' ? 'bg-primary/20 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="sidebar-label">User Refresh Tokens</span>
            </a>
            <a href="<?= url('contacts.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'bg-primary/20 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span class="sidebar-label">Contacts</span>
            </a>
            <a href="<?= url('settings.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-primary/20 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="sidebar-label">Settings</span>
            </a>
            <?php if (isset($currentUser['role']) && $currentUser['role'] === 'admin'): ?>
            <a href="<?= url('admin.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'bg-primary/20 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span class="sidebar-label">Admin</span>
            </a>
            <?php endif; ?>
    </nav>
    <div class="px-3 py-3 border-t border-slate-700/50">
        <button onclick="document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main-content').classList.toggle('expanded');" class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-700/50 text-sm w-full transition-colors">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
            <span class="sidebar-label">Collapse</span>
        </button>
    </div>
</aside>

<div id="main-content" class="main-content min-h-screen flex flex-col">
    <header class="bg-slate-800/80 backdrop-blur-sm border-b border-slate-700/50 sticky top-0 z-40">
        <div class="flex items-center justify-between h-16 px-6">
            <button class="md:hidden text-slate-400 hover:text-white" onclick="document.getElementById('sidebar').classList.toggle('mobile-open');document.getElementById('sidebar-overlay').classList.toggle('hidden');">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <h1 class="text-lg font-semibold text-white hidden md:block"><?= isset($pageTitle) ? e($pageTitle) : '' ?></h1>
            <div class="flex-1 md:hidden"></div>
            <div class="relative" id="profile-dropdown-container">
                <button onclick="document.getElementById('profile-menu').classList.toggle('hidden')" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-700/50 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-primary/30 flex items-center justify-center">
                        <span class="text-primary font-semibold text-xs"><?= strtoupper(substr($currentUser['name'] ?? $currentUser['email'] ?? 'U', 0, 1)) ?></span>
                    </div>
                    <span class="text-sm text-slate-300 hidden sm:block"><?= e($currentUser['name'] ?? $currentUser['username'] ?? $currentUser['email'] ?? '') ?></span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div id="profile-menu" class="hidden absolute right-0 mt-2 w-48 bg-slate-800 border border-slate-700/50 rounded-xl shadow-xl py-1 z-50">
                    <a href="<?= url('admin.php') ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Settings
                    </a>
                    <div class="border-t border-slate-700/50 my-1"></div>
                    <a href="<?= url('logout.php') ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </header>

<?php if ($flash): ?>
    <div class="px-6 pt-4">
        <div class="rounded-xl p-4 <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/10 text-red-300 border border-red-500/30' ?>">
            <?= e($flash['message']) ?>
        </div>
    </div>
<?php endif; ?>

    <main class="flex-1 px-6 py-8">
<?php endif; ?>
<script>document.addEventListener('click',function(e){var c=document.getElementById('profile-dropdown-container');var m=document.getElementById('profile-menu');if(c&&m&&!c.contains(e.target))m.classList.add('hidden');});</script>
