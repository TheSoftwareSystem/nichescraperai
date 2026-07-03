<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errors = [];
$vals = ['name' => '', 'email' => ''];
$strength_score = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $tos = $_POST['tos'] ?? '';
    
    $vals['name'] = $name;
    $vals['email'] = $email;
    
    // Validate
    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    
    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    
    if (empty($tos)) {
        $errors['tos'] = 'You must agree to the Terms of Service to continue.';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            $cols = array_column($db->query('DESCRIBE users')->fetchAll(), 'Field');
            $pwCol = in_array('password_hash', $cols) ? 'password_hash' : 'password';
            $nameCol = in_array('first_name', $cols) ? 'first_name' : (in_array('name', $cols) ? 'name' : null);
            
            // Check for existing email
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Build dynamic INSERT
                $insertCols = ['email', $pwCol];
                $insertVals = [$email, $password_hash];
                $placeholders = ['?', '?'];
                
                if ($nameCol && in_array($nameCol, $cols)) {
                    $insertCols[] = $nameCol;
                    $insertVals[] = $name;
                    $placeholders[] = '?';
                }
                
                if (in_array('plan', $cols)) {
                    $insertCols[] = 'plan';
                    $insertVals[] = 'free';
                    $placeholders[] = '?';
                }
                
                if (in_array('notify_email', $cols)) {
                    $insertCols[] = 'notify_email';
                    $insertVals[] = 1;
                    $placeholders[] = '?';
                }
                
                if (in_array('notify_webhook', $cols)) {
                    $insertCols[] = 'notify_webhook';
                    $insertVals[] = 0;
                    $placeholders[] = '?';
                }
                
                if (in_array('credits_used_this_month', $cols)) {
                    $insertCols[] = 'credits_used_this_month';
                    $insertVals[] = 0;
                    $placeholders[] = '?';
                }
                
                if (in_array('credits_reset_at', $cols)) {
                    $insertCols[] = 'credits_reset_at';
                    $insertVals[] = date('Y-m-d H:i:s', strtotime('+1 month'));
                    $placeholders[] = '?';
                }
                
                $sql = 'INSERT INTO users (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $db->prepare($sql);
                $stmt->execute($insertVals);
                
                $userId = $db->lastInsertId();
                
                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_plan'] = 'free';
                
                setFlash('success', 'Account created! You have 10 free scrape credits to get started.');
                header('Location: ' . url('dashboard.php'));
                exit;
            }
        } catch (Exception $e) {
            setFlash('error', 'Registration failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — NicheScraper AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }
        .input-focus:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .input-error {
            border-color: #ef4444 !important;
        }
        .input-error:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4 py-12">

    <!-- Top brand bar -->
    <div class="fixed top-0 left-0 right-0 flex items-center justify-between px-8 py-4 bg-slate-800/50/80 backdrop-blur-sm border-b border-slate-200 z-10">
        <a href="<?= url('index.php') ?>" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <span class="font-bold text-slate-800 text-lg">NicheScraper <span class="text-indigo-600">AI</span></span>
        </a>
        <a href="<?= url('login.php') ?>" class="text-sm text-slate-600 hover:text-indigo-600 transition-colors font-medium">
            Already have an account? <span class="text-indigo-600 font-semibold">Sign in</span>
        </a>
    </div>

    <div class="w-full max-w-lg mt-16">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-full px-4 py-1.5 mb-4">
                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Free tier — 10 scrape credits included</span>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-800 mb-2">Start scraping in minutes</h1>
            <p class="text-slate-500 text-base">Build hyper-targeted email lists from any niche forum or community</p>
        </div>

        <!-- Card -->
        <div class="bg-slate-800/50 rounded-2xl shadow-xl border border-slate-200 p-8">
            
            <?php if (!empty($errors['general'])): ?>
            <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-xl text-sm">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($errors['general']) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('register.php') ?>" class="space-y-5" id="registerForm">
                <?= csrfField() ?>

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Full name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg style="width:18px;height:18px" class="text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            id="name" 
                            name="name"
                            value="<?= e($vals['name']) ?>"
                            placeholder="Jane Smith"
                            autocomplete="name"
                            class="input-focus w-full pl-10 pr-4 py-3 border rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200 <?= isset($errors['name']) ? 'border-red-400 input-error' : 'border-slate-300' ?>"
                        >
                    </div>
                    <?php if (isset($errors['name'])): ?>
                    <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= e($errors['name']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Work email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg style="width:18px;height:18px" class="text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email"
                            value="<?= e($vals['email']) ?>"
                            placeholder="you@company.com"
                            autocomplete="email"
                            class="input-focus w-full pl-10 pr-4 py-3 border rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200 <?= isset($errors['email']) ? 'border-red-400 input-error' : 'border-slate-300' ?>"
                        >
                    </div>
                    <?php if (isset($errors['email'])): ?>
                    <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= e($errors['email']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg style="width:18px;height:18px" class="text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            placeholder="Min. 8 characters"
                            autocomplete="new-password"
                            class="input-focus w-full pl-10 pr-12 py-3 border rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200 <?= isset($errors['password']) ? 'border-red-400 input-error' : 'border-slate-300' ?>"
                            oninput="updateStrength(this.value)"
                        >
                        <button type="button" onclick="togglePw('password')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                            <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Password strength -->
                    <div class="mt-2">
                        <div class="flex gap-1 mb-1">
                            <div class="strength-bar flex-1 bg-slate-200" id="bar1"></div>
                            <div class="strength-bar flex-1 bg-slate-200" id="bar2"></div>
                            <div class="strength-bar flex-1 bg-slate-200" id="bar3"></div>
                            <div class="strength-bar flex-1 bg-slate-200" id="bar4"></div>
                        </div>
                        <p class="text-xs text-slate-400" id="strengthLabel">Enter a password to check strength</p>
                    </div>
                    
                    <?php if (isset($errors['password'])): ?>
                    <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= e($errors['password']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirm" class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg style="width:18px;height:18px" class="text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm"
                            placeholder="Repeat your password"
                            autocomplete="new-password"
                            class="input-focus w-full pl-10 pr-12 py-3 border rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200 <?= isset($errors['password_confirm']) ? 'border-red-400 input-error' : 'border-slate-300' ?>"
                            oninput="checkMatch()"
                        >
                        <button type="button" onclick="togglePw('password_confirm')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                            <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs hidden" id="matchMsg"></p>
                    <?php if (isset($errors['password_confirm'])): ?>
                    <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= e($errors['password_confirm']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- ToS -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="tos" 
                            value="1"
                            class="w-4 h-4 mt-0.5 text-indigo-600 border-slate-300 rounded cursor-pointer flex-shrink-0"
                            <?= !empty($_POST['tos']) ? 'checked' : '' ?>
                        >
                        <span class="text-sm text-slate-600">
                            I agree to the <a href="#" class="text-indigo-600 hover:underline font-medium">Terms of Service</a> and <a href="#" class="text-indigo-600 hover:underline font-medium">Privacy Policy</a>. I will only scrape publicly accessible pages and comply with CAN-SPAM and GDPR regulations in my outreach campaigns.
                        </span>
                    </label>
                    <?php if (isset($errors['tos'])): ?>
                    <p class="mt-2 text-xs text-red-600 flex items-center gap-1">
                        <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?= e($errors['tos']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
                <button 
                    type="submit"
                    class="btn-primary w-full text-white font-semibold py-3.5 px-6 rounded-xl text-sm flex items-center justify-center gap-2"
                >
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Create Free Account — Start Scraping
                </button>
            </form>

            <!-- Already have account -->
            <p class="text-center text-sm text-slate-500 mt-5">
                Already using NicheScraper? 
                <a href="<?= url('login.php') ?>" class="text-indigo-600 font-semibold hover:underline">Sign in</a>
            </p>
        </div>

        <!-- Features -->
        <div class="mt-8 grid grid-cols-3 gap-4">
            <div class="bg-slate-800/50/80 rounded-xl border border-slate-200 p-4 text-center">
                <div class="w-9 h-9 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <svg style="width:18px;height:18px" class="text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <p class="text-xs font-semibold text-slate-700">6+ Deobfuscation</p>
                <p class="text-xs text-slate-400 mt-0.5">Patterns decoded</p>
            </div>
            <div class="bg-slate-800/50/80 rounded-xl border border-slate-200 p-4 text-center">
                <div class="w-9 h-9 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <svg style="width:18px;height:18px" class="text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <p class="text-xs font-semibold text-slate-700">85%+ Success Rate</p>
                <p class="text-xs text-slate-400 mt-0.5">On major forums</p>
            </div>
            <div class="bg-slate-800/50/80 rounded-xl border border-slate-200 p-4 text-center">
                <div class="w-9 h-9 bg-cyan-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <svg style="width:18px;height:18px" class="text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-xs font-semibold text-slate-700">Clean CSV Export</p>
                <p class="text-xs text-slate-400 mt-0.5">Deduplicated lists</p>
            </div>
        </div>
    </div>

    <script>
        function togglePw(fieldId) {
            const f = document.getElementById(fieldId);
            f.type = f.type === 'password' ? 'text' : 'password';
        }

        function updateStrength(pw) {
            let score = 0;
            const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
            const label = document.getElementById('strengthLabel');
            
            if (pw.length >= 8) score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw)) score++;
            
            const colors = ['bg-red-400', 'bg-orange-400', 'bg-yellow-400', 'bg-green-500'];
            const labels = ['Too weak', 'Fair', 'Good', 'Strong'];
            const textColors = ['text-red-600', 'text-orange-600', 'text-yellow-600', 'text-green-600'];
            
            bars.forEach((bar, i) => {
                bar.className = 'strength-bar flex-1 ' + (i < score ? colors[score - 1] : 'bg-slate-200');
            });
            
            if (pw.length === 0) {
                label.textContent = 'Enter a password to check strength';
                label.className = 'text-xs text-slate-400';
            } else {
                label.textContent = labels[score - 1] || 'Too weak';
                label.className = 'text-xs ' + (textColors[score - 1] || 'text-red-600');
            }
        }

        function checkMatch() {
            const pw = document.getElementById('password').value;
            const conf = document.getElementById('password_confirm').value;
            const msg = document.getElementById('matchMsg');
            if (conf.length === 0) { msg.classList.add('hidden'); return; }
            msg.classList.remove('hidden');
            if (pw === conf) {
                msg.textContent = '✓ Passwords match';
                msg.className = 'mt-1.5 text-xs text-green-600 flex items-center gap-1';
            } else {
                msg.textContent = '✗ Passwords do not match';
                msg.className = 'mt-1.5 text-xs text-red-600 flex items-center gap-1';
            }
        }

        document.getElementById('name').focus();
    </script>
</body>
</html>