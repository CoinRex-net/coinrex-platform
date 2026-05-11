<?php
/**
 * CoinRex DevHub Functions - SIMPLE VERSION
 * Location: /coinrex/devhub/includes/functions.php
 */

// Don't redeclare functions that already exist
if (!function_exists('devSanitize')) {
    function devSanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('getUserProjectCount')) {
    function getUserProjectCount($user_id) {
        if (!$user_id) return 0;
        $db = getDevHubDB();
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE created_by = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['total'] : 0;
    }
}

if (!function_exists('getDeveloperVerificationStatus')) {
    function getDeveloperVerificationStatus($user_id) {
        if (!$user_id) return null;
        $db = getDevHubDB();
        $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

if (!function_exists('isVerifiedDeveloper')) {
    function isVerifiedDeveloper($user_id) {
        if (!$user_id) return false;
        $db = getDevHubDB();
        // Check developer_verification table for approved status
        $stmt = $db->prepare("SELECT id FROM developer_verification WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch() ? true : false;
    }
}
if (!function_exists('getCurrentDevUserId')) {
    function getCurrentDevUserId() {
        return $_SESSION['devhub_user_id'] ?? null;
    }
}

if (!function_exists('getCurrentDevUsername')) {
    function getCurrentDevUsername() {
        return $_SESSION['devhub_username'] ?? null;
    }
}

if (!function_exists('getCurrentDevRole')) {
    function getCurrentDevRole() {
        return $_SESSION['devhub_role'] ?? null;
    }
}
if (!function_exists('getDeveloperStatus')) {
    function getDeveloperStatus($user_id) {
        if (!$user_id) return null;
        $db = getDevHubDB();
        $stmt = $db->prepare("SELECT status FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['status'] : null;
    }
}
?>