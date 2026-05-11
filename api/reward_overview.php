<?php
/**
 * Consolidated reward dashboard data for the authenticated user.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $requested_user_id = isset($_GET['user_id']) ? apiGetRequestedUserId('user_id') : null;
    [$user_id] = apiResolveAuthorizedUserId($requested_user_id);

    $db = getDBConnection();
    $user = getUserById($user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    $level_state = getUserLevelState($user, $db);
    $level_progress = getUserLevelProgressData($level_state, $db);
    $eligibility = getClaimEligibility($user_id, $db);
    $tasks = normalizeUserLevel($user['level'] ?? 'beginner') === 'beginner'
        ? getMiniTasksForUser($user_id, $db)
        : [];

    $balances = [
        'available' => number_format(getRewardLedgerBalance($user_id, 'available', $db), 8, '.', ''),
        'locked' => number_format(getRewardLedgerBalance($user_id, 'locked', $db), 8, '.', ''),
        'pending' => number_format(getRewardLedgerBalance($user_id, 'pending', $db), 8, '.', ''),
        'claimed' => number_format(getRewardLedgerBalance($user_id, 'claimed', $db), 8, '.', ''),
    ];

    $open_claim_stmt = $db->prepare("
        SELECT id, total_amount, nonce, status, created_at
        FROM claim_snapshots
        WHERE user_id = ?
          AND status = 'generated'
        ORDER BY id DESC
        LIMIT 1
    ");
    $open_claim_stmt->execute([$user_id]);
    $open_claim = $open_claim_stmt->fetch();

    $recent_claims_stmt = $db->prepare("
        SELECT id, total_amount, nonce, status, created_at
        FROM claim_snapshots
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");
    $recent_claims_stmt->execute([$user_id]);
    $recent_claims = array_map(static function ($row) {
        return [
            'id' => (int) $row['id'],
            'amount' => number_format((float) $row['total_amount'], 8, '.', ''),
            'nonce' => (string) $row['nonce'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ];
    }, $recent_claims_stmt->fetchAll());

    $recent_ledger_stmt = $db->prepare("
        SELECT id, source, reward_phase, action_type, amount, status, reference_id, created_at
        FROM reward_ledger
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 8
    ");
    $recent_ledger_stmt->execute([$user_id]);
    $recent_ledger = $recent_ledger_stmt->fetchAll();

    foreach ($recent_ledger as &$ledger_row) {
        $ledger_row['direction'] = (((float) ($ledger_row['amount'] ?? 0) < 0) || ((string) ($ledger_row['status'] ?? '') === 'claimed'))
            ? 'outgoing'
            : 'incoming';
    }
    unset($ledger_row);

    apiSuccessResponse([
        'user_id' => $user_id,
        'user_level' => normalizeUserLevel($user['level'] ?? 'beginner'),
        'reward_frozen' => !empty($user['reward_frozen']),
        'balances' => $balances,
        'level_state' => $level_state,
        'level_progress' => $level_progress,
        'claim_eligibility' => $eligibility,
        'open_claim' => $open_claim ? [
            'id' => (int) $open_claim['id'],
            'amount' => number_format((float) $open_claim['total_amount'], 8, '.', ''),
            'nonce' => (string) $open_claim['nonce'],
            'status' => (string) $open_claim['status'],
            'created_at' => (string) $open_claim['created_at'],
        ] : null,
        'recent_claims' => $recent_claims,
        'recent_ledger' => $recent_ledger,
        'tasks' => $tasks,
        'task_stats' => getUserMiniTaskStats($user_id, $db),
        'security' => getUserSecuritySignals($user_id, $db),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
