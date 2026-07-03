<?php
require_once 'config.php';
if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NicheScraper AI — Hyper-Targeted Email Lists from Niche Communities</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 30%, #f8fafc 70%, #fef3c7 100%); }
        .gradient-cta { background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%); }
        .gradient-text { color: #0ea5e9; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.12); }
        .stat-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
        .nav-blur { backdrop-filter: blur(12px); background: rgba(255,255,255,0.85); }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .step-connector { background: linear-gradient(90deg, #0ea5e9, #6366f1); }
        .feature-icon { background: linear-gradient(135deg, #e0f2fe, #ede9fe); }
        .badge-free { background: #dcfce7; color: #166534; }
        .badge-pro { background: #dbeafe; color: #1e40af; }
        .badge-agency { background: #fce7f3; color: #9d174d; }
        @keyframes countUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-count { animation: countUp 0.8s ease forwards; }
    </style>
</head>
<body class="bg-slate-800/50 text-slate-800">

<!-- NAV -->
<nav class="fixed top-0 left-0 right-0 z-50 nav-blur border-b border-slate-200/60">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl gradient-cta flex items-center justify-center shadow-lg">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <span class="text-xl font-bold text-slate-900">NicheScraper <span class="gradient-text font-extrabold">AI</span></span>
        </div>
        <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">Features</a>
            <a href="#how-it-works" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">How It Works</a>
            <a href="#pricing" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">Pricing</a>
            <a href="<?= url('login.php') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">Sign In</a>
            <a href="<?= url('register.php') ?>" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">Start Free</a>
        </div>
        <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div id="mobileMenu" class="hidden md:hidden px-6 pb-4 space-y-2 border-t border-slate-100 pt-4">
        <a href="#features" class="block py-2 text-sm text-slate-600">Features</a>
        <a href="#how-it-works" class="block py-2 text-sm text-slate-600">How It Works</a>
        <a href="<?= url('login.php') ?>" class="block py-2 text-sm text-slate-600">Sign In</a>
        <a href="<?= url('register.php') ?>" class="block py-2 px-4 rounded-xl text-sm font-semibold text-white text-center" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">Start Free</a>
    </div>
</nav>

<!-- HERO -->
<section class="gradient-hero pt-32 pb-24 px-6">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col lg:flex-row items-center gap-16">
            <div class="flex-1 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-100 border border-sky-200 text-sky-700 text-sm font-medium mb-8">
                    <span class="w-2 h-2 rounded-full bg-sky-500 pulse-dot inline-block"></span>
                    Fresh leads. No stale databases. No guessing.
                </div>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-extrabold text-slate-900 leading-tight mb-6">
                    Extract Targeted Emails
                    <span class="block" style="color: #0ea5e9;">From Any Niche Forum</span>
                    <span class="block text-slate-900">On Demand.</span>
                </h1>
                <p class="text-xl text-slate-600 leading-relaxed mb-10 max-w-2xl">
                    Stop paying for outdated B2B databases. NicheScraper AI pulls fresh, hyper-targeted email addresses directly from the communities where your ideal prospects are already active — Reddit, phpBB, Discourse, vBulletin, and more.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                    <a href="<?= url('register.php') ?>" class="px-8 py-4 rounded-xl text-base font-semibold text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-[1.02] text-center" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">
                        Start Scraping Free
                        <span class="ml-2">→</span>
                    </a>
                    <a href="<?= url('login.php') ?>" class="px-8 py-4 rounded-xl text-base font-semibold text-slate-700 bg-slate-800/50 border border-slate-200 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-[1.02] text-center">
                        Sign In to Dashboard
                    </a>
                </div>
                <p class="mt-6 text-sm text-slate-500">Free tier includes 10 scrape jobs/month. No credit card required.</p>
            </div>
            <div class="flex-1 w-full max-w-lg">
                <!-- Mock UI Card -->
                <div class="bg-slate-800/50 rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="bg-slate-800 px-4 py-3 flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        <span class="ml-3 text-xs text-slate-400 font-mono">nichescraper.ai — job running</span>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Scrape Job #4821</p>
                                <p class="text-sm font-semibold text-slate-800">woodworking-forum.com/members</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 pulse-dot inline-block"></span>
                                Running
                            </span>
                        </div>
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-slate-500 mb-1">
                                <span>Progress</span><span>73%</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width: 73%; background: linear-gradient(90deg, #0ea5e9, #6366f1);"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-50">
                                <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                                <span class="text-xs font-mono text-slate-600">j.thornton@woodcraft.net</span>
                                <span class="ml-auto text-xs text-slate-400">personal</span>
                            </div>
                            <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-50">
                                <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                                <span class="text-xs font-mono text-slate-600">m.rivers [deobfuscated]</span>
                                <span class="ml-auto text-xs text-slate-400">personal</span>
                            </div>
                            <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-yellow-50">
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-xs font-mono text-slate-600">admin@woodshop.io</span>
                                <span class="ml-auto text-xs text-yellow-600">role-based</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-800">347 emails found</span>
                            <button class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">Export CSV</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="py-16 px-6 bg-slate-800/50 border-y border-slate-100">
    <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="text-4xl font-extrabold text-slate-900 mb-1">85%+</div>
                <div class="text-sm text-slate-500 font-medium">Scrape success rate on major forum platforms</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-extrabold" style="color:#0ea5e9;">6+</div>
                <div class="text-sm text-slate-500 font-medium">Email obfuscation patterns deobfuscated</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-extrabold text-slate-900 mb-1">&lt;5 min</div>
                <div class="text-sm text-slate-500 font-medium">Average time to a clean, export-ready CSV</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-extrabold" style="color:#6366f1;">100%</div>
                <div class="text-sm text-slate-500 font-medium">Publicly visible emails — fully CAN-SPAM compliant</div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-24 px-6 bg-slate-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-sm font-semibold tracking-widest uppercase" style="color:#0ea5e9;">What Makes NicheScraper AI Different</span>
            <h2 class="text-4xl md:text-5xl font-extrabold text-slate-900 mt-3 mb-5">Anti-Bot Evasion Is<br>Not a Nice-to-Have. It's the Product.</h2>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto">Every feature is built around one goal: getting you fresh, hyper-targeted emails from niche communities that generic B2B databases will never have.</p>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">Stealth Headless Browser Engine</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Playwright with stealth plugins mimics real human browsing. Randomized 2–8 second Gaussian delays, rotating residential proxies, and automatic Cloudflare WAF bypass mean you get the HTML — every time.</p>
            </div>
            <!-- Feature 2 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">6-Pattern Email Deobfuscation</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Catches emails that every other scraper misses. Handles 'user [at] domain [dot] com', unicode lookalikes, base64-encoded mailto links, ROT13, space-separated, and parenthetical formats — all normalized to clean RFC-compliant addresses.</p>
            </div>
            <!-- Feature 3 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">Scheduled Recurring Jobs</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Set up a cron-based schedule (daily, weekly, monthly) and your lead lists refresh automatically. Get notified via email or webhook when new emails are found — no manual re-runs, ever.</p>
            </div>
            <!-- Feature 4 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">Enriched Lead Intelligence</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Every email comes with source URL, page title, domain, date scraped, and obfuscation type. Segment by community, recency, or role. This isn't a raw email list — it's a sales intelligence asset.</p>
            </div>
            <!-- Feature 5 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">Clean CSV & XLSX Export</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Deduplicated results (globally within jobs or across all historical jobs) exported to CSV, XLSX, or JSON. Choose your columns. Role-based addresses flagged for marketer's discretion. Disposable domains auto-stripped.</p>
            </div>
            <!-- Feature 6 -->
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="w-12 h-12 rounded-xl feature-icon flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" style="color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-3">REST API for Pro & Agency</h3>
                <p class="text-slate-600 text-sm leading-relaxed">Submit jobs programmatically, poll for results, or push via webhooks. Manage API keys with per-key rate limits. Agency tier unlocks multi-seat access and 500 req/hr API throughput for scale.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section id="how-it-works" class="py-24 px-6 bg-slate-800/50">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-sm font-semibold tracking-widest uppercase" style="color:#6366f1;">Simple By Design</span>
            <h2 class="text-4xl md:text-5xl font-extrabold text-slate-900 mt-3 mb-5">From URL to CSV<br>in Three Steps</h2>
            <p class="text-lg text-slate-600 max-w-xl mx-auto">No proxy setup required. No Python scripts to run. Just paste URLs, configure depth, and let the engine do the work.</p>
        </div>
        <div class="relative">
            <div class="hidden md:block absolute top-16 left-1/2 -translate-x-1/2 w-2/3 h-0.5 step-connector opacity-30"></div>
            <div class="grid md:grid-cols-3 gap-10">
                <div class="text-center relative">
                    <div class="w-16 h-16 rounded-2xl mx-auto mb-6 flex items-center justify-center text-white text-2xl font-extrabold shadow-xl" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">1</div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Paste Your Forum URLs</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Add up to 50 URLs from Reddit threads, phpBB member directories, Discourse communities, or any public niche forum. Set your crawl depth (1–5 pages). Name your job.</p>
                </div>
                <div class="text-center relative">
                    <div class="w-16 h-16 rounded-2xl mx-auto mb-6 flex items-center justify-center text-white text-2xl font-extrabold shadow-xl" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">2</div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Our Engine Scrapes & Deobfuscates</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">The async job queue picks it up instantly. Playwright with stealth plugins fetches fully-rendered pages, extracts all email patterns (6 deobfuscation passes), deduplicates globally, and enriches each row with metadata.</p>
                </div>
                <div class="text-center relative">
                    <div class="w-16 h-16 rounded-2xl mx-auto mb-6 flex items-center justify-center text-white text-2xl font-extrabold shadow-xl" style="background: linear-gradient(135deg, #8b5cf6, #ec4899);">3</div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Download Your Lead List</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Watch results populate in real time. Filter by role-based, sort by domain, and export a clean deduplicated CSV in one click. Add to named Lead Lists for cross-job segmentation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PRICING -->
<section id="pricing" class="py-24 px-6 bg-slate-50">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-sm font-semibold tracking-widest uppercase" style="color:#0ea5e9;">Pricing</span>
            <h2 class="text-4xl md:text-5xl font-extrabold text-slate-900 mt-3 mb-5">Try It. Get Real Results. Then Upgrade.</h2>
            <p class="text-lg text-slate-600">The free tier is generous enough to prove value on your first job.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="mb-6">
                    <span class="badge-free px-3 py-1 rounded-full text-xs font-semibold">Free</span>
                    <div class="mt-4 text-4xl font-extrabold text-slate-900">$0<span class="text-lg font-normal text-slate-500">/mo</span></div>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>10 scrape jobs/month</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Crawl depth up to 2 pages</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>CSV export</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>All 6 deobfuscation patterns</li>
                    <li class="flex items-center gap-2 text-sm text-slate-400"><svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>No scheduling</li>
                    <li class="flex items-center gap-2 text-sm text-slate-400"><svg class="w-4 h-4 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>No API access</li>
                </ul>
                <a href="<?= url('register.php') ?>" class="block w-full py-3 rounded-xl text-sm font-semibold text-center border border-slate-200 text-slate-700 hover:bg-slate-50 transition-all">Start Free</a>
            </div>
            <div class="bg-slate-800/50 rounded-2xl p-8 border-2 card-hover relative" style="border-color: #0ea5e9;">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full text-xs font-bold text-white" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">Most Popular</div>
                <div class="mb-6">
                    <span class="badge-pro px-3 py-1 rounded-full text-xs font-semibold">Pro</span>
                    <div class="mt-4 text-4xl font-extrabold text-slate-900">$49<span class="text-lg font-normal text-slate-500">/mo</span></div>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Unlimited scrape jobs</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Full crawl depth (1–5 pages)</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Scheduled recurring jobs</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Email & webhook notifications</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>REST API (100 req/hr)</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>CSV, XLSX & JSON export</li>
                </ul>
                <a href="<?= url('register.php') ?>" class="block w-full py-3 rounded-xl text-sm font-semibold text-center text-white shadow-lg hover:shadow-xl transition-all" style="background: linear-gradient(135deg, #0ea5e9, #6366f1);">Upgrade to Pro</a>
            </div>
            <div class="bg-slate-800/50 rounded-2xl p-8 border border-slate-200 card-hover">
                <div class="mb-6">
                    <span class="badge-agency px-3 py-1 rounded-full text-xs font-semibold">Agency</span>
                    <div class="mt-4 text-4xl font-extrabold text-slate-900">$149<span class="text-lg font-normal text-slate-500">/mo</span></div>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Everything in Pro</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Multi-seat team access</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>API (500 req/hr)</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Priority scrape queue</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Webhook push for job results</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Compliance audit log export</li>
                </ul>
                <a href="<?= url('register.php') ?>" class="block w-full py-3 rounded-xl text-sm font-semibold text-center border border-slate-200 text-slate-700 hover:bg-slate-50 transition-all">Contact Sales</a>
            </div>
        </div>
    </div>
</section>

<!-- BOTTOM CTA -->
<section class="py-24 px-6">
    <div class="max-w-4xl mx-auto">
        <div class="gradient-cta rounded-3xl p-12 md:p-16 text-center shadow-2xl">
            <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-6">Stop Buying Stale Lists.<br>Build Fresh Ones.</h2>
            <p class="text-xl text-sky-100 mb-10 max-w-2xl mx-auto">Every email you extract from NicheScraper AI is someone already active in your niche. That's a qualitatively different kind of lead — and it shows in your reply rates.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= url('register.php') ?>" class="px-10 py-4 rounded-xl text-base font-bold text-sky-600 bg-slate-800/50 shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-[1.02]">Start Scraping Free — No CC Required</a>
                <a href="<?= url('login.php') ?>" class="px-10 py-4 rounded-xl text-base font-semibold text-white border-2 border-white/40 hover:bg-slate-800/50/10 transition-all duration-300">Already have an account?</a>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="py-12 px-6 bg-slate-900">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg gradient-cta flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <span class="text-lg font-bold text-white">NicheScraper AI</span>
            </div>
            <div class="flex items-center gap-8">
                <a href="#features" class="text-sm text-slate-400 hover:text-white transition-colors">Features</a>
                <a href="#pricing" class="text-sm text-slate-400 hover:text-white transition-colors">Pricing</a>
                <a href="<?= url('login.php') ?>" class="text-sm text-slate-400 hover:text-white transition-colors">Sign In</a>
                <a href="<?= url('register.php') ?>" class="text-sm text-slate-400 hover:text-white transition-colors">Register</a>
            </div>
        </div>
        <div class="mt-8 pt-8 border-t border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-sm text-slate-500">&copy; <?= date('Y') ?> NicheScraper AI. All rights reserved.</p>
            <p class="text-xs text-slate-600">For publicly accessible pages only. Users must comply with CAN-SPAM, GDPR, and applicable ToS.</p>
        </div>
    </div>
</footer>

<script>
document.getElementById('mobileMenuBtn').addEventListener('click', function() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
});
// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});
</script>
</body>
</html>