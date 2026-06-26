<?php
/**
 * Claim LearnHub Mystery Box Reward.
 * Reward amount is calculated server-side from missed mission days.
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $db = getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    for ($day = 1; $day < TASKHUB_TOTAL_DAYS; $day++) {
        $day_info = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        if (empty($day_info['all_completed'])) {
            throw new RuntimeException('Complete Days 1-9 before claiming mystery box reward.');
        }
    }

    $day10_checkin_completed = false;
    $day10_checkin_completed_at = null;
    $day10_info = taskHubGetDayCompletionInfo((int) $user_id, TASKHUB_TOTAL_DAYS, $db);
    foreach (($day10_info['tasks'] ?? []) as $task_state) {
        $task = (array) ($task_state['task'] ?? []);
        $log = (array) ($task_state['log'] ?? []);
        if (
            taskHubIsCheckinTaskKey((string) ($task['task_key'] ?? ''))
            && (string) ($log['status'] ?? '') === 'completed'
        ) {
            $day10_checkin_completed = true;
            $day10_checkin_completed_at = (string) ($log['task_completed_at'] ?? $log['completed_at'] ?? date('Y-m-d H:i:s'));
            break;
        }
    }

    if (!$day10_checkin_completed) {
        throw new RuntimeException('Complete Day 10 check-in before opening the mystery box.');
    }

    $mystery_task_stmt = $db->prepare("
        SELECT *
        FROM mini_tasks
        WHERE task_group = 'mission'
          AND mission_day = ?
          AND verification_mode = 'mystery'
          AND is_active = 1
        ORDER BY mission_step ASC, id ASC
        LIMIT 1
    ");
    $mystery_task_stmt->execute([TASKHUB_TOTAL_DAYS]);
    $mystery_task = $mystery_task_stmt->fetch();
    if (!$mystery_task) {
        throw new RuntimeException('Mystery box task is not available right now.');
    }

    $mystery_log = getTaskHubLatestLog(
        (int) $user_id,
        (int) $mystery_task['id'],
        TASKHUB_TOTAL_DAYS,
        $db
    );

    if (!$mystery_log) {
        taskHubCreateFollowupTasksAfterCheckIn(
            (int) $user_id,
            TASKHUB_TOTAL_DAYS,
            $day10_checkin_completed_at ?: date('Y-m-d H:i:s'),
            $db
        );
        $mystery_log = getTaskHubLatestLog(
            (int) $user_id,
            (int) $mystery_task['id'],
            TASKHUB_TOTAL_DAYS,
            $db
        );
    }

    if (!$mystery_log) {
        throw new RuntimeException('Mystery box is not ready yet. Please refresh LearnHub and try again.');
    }

    $mystery_available_at = !empty($mystery_log['task_available_at']) ? strtotime((string) $mystery_log['task_available_at']) : 0;
    if ($mystery_available_at > time()) {
        throw new RuntimeException('Mystery box is locked until ' . date('Y-m-d H:i:s', $mystery_available_at) . '.');
    }

    $reference_id = 'taskhub:mystery_box';
    $claimed_stmt = $db->prepare("
        SELECT COUNT(*)
        FROM reward_ledger
        WHERE user_id = ?
          AND source = 'bonus'
          AND reference_id = ?
          AND action_type = 'taskhub_mystery_box'
    ");
    $claimed_stmt->execute([(int) $user_id, $reference_id]);
    if ((int) $claimed_stmt->fetchColumn() > 0) {
        throw new RuntimeException('Mystery box reward already claimed.');
    }

    $missed_day_count = taskHubGetMissedDayCount((int) $user_id, $db);
    $reward_amount = taskHubGetMysteryBoxReward((int) $user_id, $db);
    $has_perfect_streak = $missed_day_count === 0;
    $old_level = normalizeUserLevel((string) ($user['level'] ?? 'beginner'));

    addRewardLedgerEntry(
        (int) $user_id,
        $reward_amount,
        'bonus',
        'taskhub_mystery_box',
        'available',
        $reference_id,
        $db,
        'phase1',
        $user['level'] ?? 'beginner'
    );

    $metadata = [];
    if (!empty($mystery_log['metadata'])) {
        $metadata = json_decode((string) $mystery_log['metadata'], true) ?: [];
    }
    $metadata['claimed_reward'] = round((float) $reward_amount, 8);
    $metadata['missed_day_count'] = (int) $missed_day_count;
    $metadata['has_perfect_streak'] = $has_perfect_streak;
    $metadata['claimed_at'] = date('c');

    taskHubUpdateLog((int) $mystery_log['id'], [
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s'),
        'task_completed_at' => date('Y-m-d H:i:s'),
        'metadata' => $metadata,
    ], $db);

    $pending_airdrop_stmt = $db->prepare("
        SELECT id
        FROM reward_ledger
        WHERE user_id = ?
          AND status = 'pending'
          AND action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
    ");
    $pending_airdrop_stmt->execute([(int) $user_id]);
    $pending_airdrop_ids = array_map('intval', $pending_airdrop_stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);

    syncTaskHubDayProgress((int) $user_id, $db);
    $level_state = syncUserLevelStatus((int) $user_id, $db);
    $new_level = normalizeUserLevel((string) ($level_state['level'] ?? $old_level));
    $level_stats = (array) ($level_state['stats'] ?? []);
    $pro_policy = getLevelPolicy('pro');
    $pro_security_signals = getUserSecuritySignals((int) $user_id, $db);
    $pro_required_age_days = (int) ($pro_policy['promotion_account_age_days'] ?? PRO_MIN_ACCOUNT_AGE_DAYS);
    $pro_required_referrals = (int) ($pro_policy['promotion_valid_referrals'] ?? PRO_MIN_VALID_REFERRALS);
    $pro_account_age_days = (int) ($level_stats['account_age_days'] ?? 0);
    $pro_valid_referrals = (int) ($level_stats['valid_referrals'] ?? 0);
    $pro_mission_complete = !empty($level_stats['mission_completed']);
    $pro_security_clear = empty($pro_security_signals['is_suspicious']);
    $pro_requirements = [
        [
            'key' => 'mission',
            'label' => '10-day LearnHub mission',
            'complete' => $pro_mission_complete,
            'meta' => $pro_mission_complete ? 'Completed' : 'Finish all 10 days and claim the mystery box',
        ],
        [
            'key' => 'account_age',
            'label' => 'Account age',
            'complete' => $pro_account_age_days >= $pro_required_age_days,
            'meta' => number_format($pro_account_age_days) . '/' . number_format(max(1, $pro_required_age_days)) . ' days',
        ],
        [
            'key' => 'valid_referral',
            'label' => 'Valid referral',
            'complete' => $pro_valid_referrals >= $pro_required_referrals,
            'meta' => number_format($pro_valid_referrals) . '/' . number_format(max(1, $pro_required_referrals)) . ' valid referral',
        ],
        [
            'key' => 'security',
            'label' => 'Account security review',
            'complete' => $pro_security_clear,
            'meta' => $pro_security_clear ? 'Clear' : 'Under review',
        ],
    ];
    $pro_eligible = $pro_mission_complete
        && $pro_account_age_days >= $pro_required_age_days
        && $pro_valid_referrals >= $pro_required_referrals
        && $pro_security_clear;
    $pro_unlocked = $pro_eligible
        && $new_level !== 'beginner'
        && $new_level !== $old_level;
    $airdrop_result = unlockPendingEarlyAirdropForUser((int) $user_id, $db);
    $airdrop_unlocked = !empty($airdrop_result['unlocked']);
    $airdrop_amount = (float) ($airdrop_result['amount'] ?? 0);

    if (!$airdrop_unlocked && !empty($pending_airdrop_ids)) {
        $placeholders = implode(',', array_fill(0, count($pending_airdrop_ids), '?'));
        $unlocked_airdrop_stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS unlocked_amount
            FROM reward_ledger
            WHERE user_id = ?
              AND id IN ($placeholders)
              AND status = 'available'
        ");
        $unlocked_airdrop_stmt->execute(array_merge([(int) $user_id], $pending_airdrop_ids));
        $airdrop_amount = round((float) ($unlocked_airdrop_stmt->fetch()['unlocked_amount'] ?? 0), 8);
        $airdrop_unlocked = $airdrop_amount > 0;
    }

    apiSuccessResponse([
        'message' => 'Mystery box reward claimed successfully!',
        'reward' => number_format((float) $reward_amount, 8, '.', ''),
        'missed_day_count' => $missed_day_count,
        'has_perfect_streak' => $has_perfect_streak,
        'new_level' => $new_level,
        'level_promoted' => $new_level !== $old_level,
        'pro_eligible' => $pro_eligible,
        'pro_unlocked' => $pro_unlocked,
        'pro_requirements' => $pro_requirements,
        'airdrop_unlocked' => $airdrop_unlocked,
        'airdrop_amount' => number_format((float) $airdrop_amount, 8, '.', ''),
        'airdrop_message' => (string) ($airdrop_result['message'] ?? ''),
        'balance' => number_format(getRewardLedgerBalance((int) $user_id, 'available', $db), 8, '.', ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
