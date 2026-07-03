<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$error = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $email_val = $email;
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $db = getDB();
            $cols = array_column($db->query('DESCRIBE users')->fetchAll(), 'Field');
            $pwCol = in_array('password_hash', $cols) ? 'password_hash' : 'password';
            
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user[$pwCol])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'] ?? ($user['first_name'] ?? 'User');
                $_SESSION['user_plan'] = $user['plan'] ?? 'free';
                
                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $stmtT = $db->prepare('INSERT INTO user_refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
                    $stmtT->execute([$user['id'], $token_hash, $expires]);
                    setcookie('remember_token', $token, time() + 60*60*24*30, '/', '', false, true);
                }
                
                setFlash('success', 'Welcome back! Your scrape jobs are ready.');
                header('Location: ' . url('dashboard.php'));
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — NicheScraper AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-border {
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4);
            padding: 2px;
            border-radius: 16px;
        }
        .gradient-border-inner {
            background: #fff;
            border-radius: 14px;
        }
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
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4">

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
        <a href="<?= url('register.php') ?>" class="text-sm text-slate-600 hover:text-indigo-600 transition-colors font-medium">
            Don't have an account? <span class="text-indigo-600 font-semibold">Sign up free</span>
        </a>
    </div>

    <div class="w-full max-w-md mt-16">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-slate-800 mb-2">Welcome back</h1>
            <p class="text-slate-500 text-base">Sign in to access your scrape jobs and lead lists</p>
        </div>

        <!-- Card -->
        <div class="bg-slate-800/50 rounded-2xl shadow-xl border border-slate-200 p-8">
            
            <?php if ($error): ?>
            <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-xl text-sm">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <?php $flash = getFlash(); if ($flash && $flash['type'] === 'success'): ?>
            <div class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 text-green-700 px-4 py-3.5 rounded-xl text-sm">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($flash['message']) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('login.php') ?>" class="space-y-5">
                <?= csrfField() ?>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4.5 h-4.5 text-slate-400" style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?= e($email_val) ?>"
                            placeholder="you@company.com"
                            required
                            autocomplete="email"
                            class="input-focus w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                        <a href="#" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium transition-colors">Forgot password?</a>
                    </div>
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
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                            class="input-focus w-full pl-10 pr-12 py-3 border border-slate-300 rounded-xl text-slate-800 text-sm placeholder-slate-400 bg-slate-800/50 transition-all duration-200"
                        >
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                            <svg id="eyeIcon" style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center gap-2.5">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember" 
                        value="1"
                        class="w-4 h-4 text-indigo-600 border-slate-300 rounded cursor-pointer"
                    >
                    <label for="remember" class="text-sm text-slate-600 cursor-pointer">Keep me signed in for 30 days</label>
                </div>

                <!-- Submit -->
                <button 
                    type="submit"
                    class="btn-primary w-full text-white font-semibold py-3 px-6 rounded-xl text-sm flex items-center justify-center gap-2 mt-2"
                >
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign in to Dashboard
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="px-3 bg-slate-800/50 text-slate-400">New to NicheScraper?</span>
                </div>
            </div>

            <a 
                href="<?= url('register.php') ?>"
                class="block w-full text-center py-3 px-6 border-2 border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 text-slate-700 font-semibold rounded-xl text-sm transition-all duration-200"
            >
                Create your free account
            </a>
        </div>

        <!-- Trust indicators -->
        <div class="mt-6 flex items-center justify-center gap-6 text-xs text-slate-400">
            <span class="flex items-center gap-1.5">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                SSL Encrypted
            </span>
            <span class="flex items-center gap-1.5">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Secure login
            </span>
            <span class="flex items-center gap-1.5">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                No credit card required
            </span>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const pwField = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwField.type === 'password') {
                pwField.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            } else {
                pwField.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
            }
        });

        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>