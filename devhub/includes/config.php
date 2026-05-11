<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../includes/config.php';
}

// DevHub Session Keys (legacy support)
define('DEVHUB_SESSION_KEY', 'devhub_logged_in');
define('DEVHUB_USER_ID', 'devhub_user_id');
define('DEVHUB_USERNAME', 'devhub_username');
define('DEVHUB_ROLE', 'devhub_role');

// Project Limits
define('MAX_PROJECTS_PER_DEVELOPER', 3);

// Database Connection
if (!function_exists('getDevHubDB')) {
    function getDevHubDB() {
        return getDBConnection();
    }
}

// Auth functions
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
}

if (!function_exists('getCurrentRole')) {
    function getCurrentRole() {
        return $_SESSION['role'] ?? null;
    }
}

function login($user_id, $username, $role) {
    if (function_exists('establishAuthenticatedSession')) {
        establishAuthenticatedSession(['id' => $user_id, 'username' => $username, 'role' => $role]);
    } else {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
    }
}

function logout() {
    if (function_exists('logoutUser')) {
        logoutUser();
        return;
    }

    unset($_SESSION[DEVHUB_SESSION_KEY]);
    unset($_SESSION[DEVHUB_USER_ID]);
    unset($_SESSION[DEVHUB_USERNAME]);
    unset($_SESSION[DEVHUB_ROLE]);
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['role']);
}

// Developer Verification Functions
if (!function_exists('isVerifiedDeveloper')) {
    function isVerifiedDeveloper($user_id) {
        if (!$user_id) return false;
        $db = getDevHubDB();

        $stmt = $db->prepare("
            SELECT status
            FROM developer_verification
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $verification = $stmt->fetch();

        if ($verification && isset($verification['status'])) {
            $status = strtolower(trim((string) $verification['status']));
            if ($status === 'approved') {
                return true;
            }
        }

        $stmt = $db->prepare("
            SELECT is_developer_verified, has_verified_badge
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user
            && (
                (int) ($user['is_developer_verified'] ?? 0) === 1
                || (int) ($user['has_verified_badge'] ?? 0) === 1
            );
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

function getUserProjectCount($user_id) {
    if (!$user_id) return 0;
    $db = getDevHubDB();
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE created_by = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['total'] : 0;
}

if (!function_exists('getDeveloperProjectEditState')) {
    /**
     * Compute project-edit state for DevHub.
     * Returns moderation + global cooldown state used by edit page.
     *
     * @param PDO   $db
     * @param int   $user_id
     * @param array $project
     * @param int   $cooldown_days
     * @return array{approval_status:string,is_pending_review_state:bool,is_cooldown_state:bool,can_edit_now:bool,cooldown_days:int,cooldown_end_ts:int,cooldown_remaining:int}
     */
    function getDeveloperProjectEditState(PDO $db, $user_id, array $project, $cooldown_days = 30) {
        $approval_status = strtolower(trim((string) ($project['approval_status'] ?? 'pending')));
        $cooldown_days = max(1, (int) $cooldown_days);
        $cooldown_seconds = $cooldown_days * 24 * 60 * 60;

        // Global developer-level cooldown: latest approved project update anchors the window.
        $cooldown_anchor_stmt = $db->prepare("\n            SELECT MAX(updated_at) AS latest_approved_update\n            FROM projects\n            WHERE created_by = ?\n              AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'\n        ");
        $cooldown_anchor_stmt->execute([(int) $user_id]);
        $cooldown_anchor_row = $cooldown_anchor_stmt->fetch() ?: [];
        $cooldown_anchor_ts = strtotime((string) ($cooldown_anchor_row['latest_approved_update'] ?? '')) ?: 0;

        $cooldown_end_ts = $cooldown_anchor_ts > 0 ? ($cooldown_anchor_ts + $cooldown_seconds) : 0;
        $cooldown_remaining = $cooldown_end_ts > 0 ? max(0, $cooldown_end_ts - time()) : 0;

        $is_pending_review_state = in_array($approval_status, ['pending', 'under_review'], true);
        $is_cooldown_state = ($cooldown_remaining > 0);
        $can_edit_now = !$is_pending_review_state && !$is_cooldown_state;

        return [
            'approval_status' => $approval_status,
            'is_pending_review_state' => $is_pending_review_state,
            'is_cooldown_state' => $is_cooldown_state,
            'can_edit_now' => $can_edit_now,
            'cooldown_days' => $cooldown_days,
            'cooldown_end_ts' => $cooldown_end_ts,
            'cooldown_remaining' => $cooldown_remaining,
        ];
    }
}

if (!function_exists('getLatestDeveloperVerification')) {
    /**
     * Fetch latest developer verification row for a user.
     */
    function getLatestDeveloperVerification(PDO $db, $user_id) {
        $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int) $user_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('buildDeveloperVerificationState')) {
    /**
     * Build normalized verification state for DevHub apply flow.
     */
    function buildDeveloperVerificationState(?array $verification_row, array $user_row, $current_user_id, $cooldown_window, $identity_wait_window) {
        $status = strtolower(trim((string) ($verification_row['status'] ?? '')));
        $has_post_submitted = trim((string) ($verification_row['verification_post_url'] ?? '')) !== '';
        $has_code_submitted = trim((string) ($verification_row['verification_url'] ?? '')) !== ''
            && trim((string) ($verification_row['verification_code'] ?? '')) !== '';
        $has_any_submission = $has_post_submitted || $has_code_submitted;
        $user_has_badge = (int) ($user_row['has_verified_badge'] ?? 0) === 1;
        $is_change_requested = $status === 'change_requested';
        $is_rejected_status = $status === 'rejected';
        $is_pending_status = $status === 'pending';

        $is_verified = false;
        if ($status === 'approved') {
            $is_verified = true;
        } elseif ($is_change_requested && $user_has_badge) {
            $is_verified = true;
        } elseif ($status === '') {
            $is_verified = isVerifiedDeveloper($current_user_id);
        }

        if ($is_rejected_status || $is_pending_status) {
            $is_verified = false;
        }

        $is_pending = $status === 'pending' && $has_any_submission;
        $is_rejected = $status === 'rejected' && $has_any_submission;
        $needs_revision = $is_change_requested && !$user_has_badge;
        $cooldown_starts_at = null;
        $cooldown_ends_at = null;
        $cooldown_active = false;
        $identity_change_reference_at = null;
        $identity_change_available_at = null;
        $can_request_identity_change = false;

        if ($is_rejected) {
            $timestamp_source = (string) ($verification_row['updated_at'] ?? $verification_row['created_at'] ?? '');
            $cooldown_starts_at = strtotime($timestamp_source) ?: null;

            if ($cooldown_starts_at !== null) {
                $cooldown_ends_at = $cooldown_starts_at + (int) $cooldown_window;
                $cooldown_active = time() < $cooldown_ends_at;
            }
        }

        if ($is_verified && !$is_change_requested) {
            $timestamp_source = (string) ($verification_row['updated_at'] ?? $verification_row['created_at'] ?? '');
            $identity_change_reference_at = strtotime($timestamp_source) ?: null;

            if ($identity_change_reference_at !== null) {
                $identity_change_available_at = $identity_change_reference_at + (int) $identity_wait_window;
                $can_request_identity_change = time() >= $identity_change_available_at;
            }
        }

        $submitted_method_label = 'No proof submitted yet';
        if ($has_post_submitted) {
            $submitted_method_label = 'Social media proof submitted';
        } elseif ($has_code_submitted) {
            $submitted_method_label = 'Website meta-tag proof submitted';
        }

        return [
            'status' => $status,
            'has_post_submitted' => $has_post_submitted,
            'has_code_submitted' => $has_code_submitted,
            'has_any_submission' => $has_any_submission,
            'is_verified' => $is_verified,
            'is_pending' => $is_pending,
            'is_rejected' => $is_rejected,
            'is_change_requested' => $is_change_requested,
            'needs_revision' => $needs_revision,
            'cooldown_active' => $cooldown_active,
            'cooldown_starts_at' => $cooldown_starts_at,
            'cooldown_ends_at' => $cooldown_ends_at,
            'identity_change_reference_at' => $identity_change_reference_at,
            'identity_change_available_at' => $identity_change_available_at,
            'can_request_identity_change' => $can_request_identity_change,
            'submitted_method_label' => $submitted_method_label,
        ];
    }
}

if (!function_exists('formatDevhubDateTime')) {
    /**
     * Human-friendly date label with absolute fallback.
     */
    function formatDevhubDateTime($value) {
        $ts = strtotime((string) $value);
        if (!$ts) {
            return '-';
        }

        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . 'm ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . 'h ago';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . 'd ago';
        }

        return date('M j, Y g:i A', $ts);
    }
}
?>
