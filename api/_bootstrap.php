<?php
/**
 * CoinRex Reward API Bootstrap
 * Shared helpers for ledger / claim endpoints.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!defined('COINREX_SKIP_REWARD_SCHEMA_INIT') || !COINREX_SKIP_REWARD_SCHEMA_INIT) {
    ensureRewardClaimSchema();
}

function apiJsonResponse($status_code, array $payload) {
    http_response_code((int) $status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiErrorResponse($status_code, $message, array $extra = []) {
    apiJsonResponse($status_code, array_merge([
        'success' => false,
        'message' => (string) $message,
    ], $extra));
}

function apiSuccessResponse(array $payload = [], $status_code = 200) {
    apiJsonResponse($status_code, array_merge(['success' => true], $payload));
}

function apiRequireMethod($method) {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper((string) $method)) {
        apiErrorResponse(405, 'Method not allowed.');
    }
}

function apiSanitizeDecimal($value) {
    $value = trim((string) $value);
    if ($value === '' || !preg_match('/^\d+(\.\d{1,8})?$/', $value)) {
        throw new InvalidArgumentException('Amount must be a valid decimal value with up to 8 decimal places.');
    }

    $normalized = number_format((float) $value, 8, '.', '');
    if ((float) $normalized <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }

    return $normalized;
}

function apiGetRequestedUserId($field = 'user_id') {
    $value = $_POST[$field] ?? $_GET[$field] ?? null;
    $user_id = (int) $value;

    if ($user_id <= 0) {
        throw new InvalidArgumentException('Valid user_id is required.');
    }

    return $user_id;
}

function apiIsAdminSession() {
    return !empty($_SESSION['admin_id']);
}

function apiGetAuthenticatedUser() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            return [
                'type' => 'user',
                'admin_id' => null,
                'user_id' => (int) ($user['id'] ?? 0),
                'role' => strtolower(trim((string) ($user['role'] ?? 'user'))),
                'user' => $user,
            ];
        }
    }

    if (apiIsAdminSession()) {
        return [
            'type' => 'admin',
            'admin_id' => (int) ($_SESSION['admin_id'] ?? 0),
            'user_id' => null,
            'role' => 'admin',
        ];
    }

    apiErrorResponse(401, 'Authentication required.');
}

function apiRequireRewardIssuer() {
    $actor = apiGetAuthenticatedUser();

    if ($actor['type'] === 'admin') {
        return $actor;
    }

    if (in_array((string) ($actor['role'] ?? ''), ['admin', 'system'], true)) {
        return $actor;
    }

    apiErrorResponse(403, 'You are not allowed to issue reward credits.');
}

function apiResolveAuthorizedUserId($requested_user_id = null) {
    $actor = apiGetAuthenticatedUser();

    if ($requested_user_id === null || $requested_user_id <= 0) {
        if ($actor['type'] === 'user' && !empty($actor['user_id'])) {
            return [(int) $actor['user_id'], $actor];
        }

        apiErrorResponse(422, 'Valid user_id is required.');
    }

    if ($actor['type'] === 'admin') {
        return [(int) $requested_user_id, $actor];
    }

    if ((int) $actor['user_id'] !== (int) $requested_user_id) {
        apiErrorResponse(403, 'You can only access your own reward data.');
    }

    return [(int) $requested_user_id, $actor];
}

function apiSanitizeLedgerSource($value) {
    $raw_source = strtolower(trim((string) $value));
    if (!in_array($raw_source, ['mini_task', 'referral', 'review', 'bonus'], true)) {
        throw new InvalidArgumentException('Invalid reward source.');
    }

    return normalizeRewardLedgerSource($raw_source);
}
