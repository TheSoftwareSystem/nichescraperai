<?php
/**
 * NicheScraper AI - Helper Functions
 */

function formatDate($date) {
    return $date ? date(DATE_FORMAT, strtotime($date)) : '';
}

function formatDateTime($date) {
    return $date ? date(DATETIME_FORMAT, strtotime($date)) : '';
}

function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return formatDate($datetime);
}

function truncate($text, $length = 100) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function getDashboardStats() {
    $db = getDB();
    $stats = ['total_users' => 0, 'total_records' => 0];
    try {
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
        $stats['total_users'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Exception $e) {}
    return $stats;
}
