<?php
require_once 'config.php';

// Clear remember token cookie and DB token if present
if (!empty($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        $stmt = $db->prepare('UPDATE user_refresh_tokens SET revoked_at = NOW() WHERE token_hash = ?');
        $stmt->execute([$token_hash]);
    } catch (Exception $e) {
        // Silently handle DB errors during logout
    }
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy the session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

// Redirect to landing page
header('Location: ' . url('index.php'));
exit;