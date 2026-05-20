<?php
/**
 * Claim Mystery Box Reward (Day 10 completion)
 * POST: reward (float amount)
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);

    $reward_amount = trim((string) ($_POST['reward'] ?? ''));
    if ($reward_amount === '' || (float) $reward_amount <= 0) {
        throw new InvalidArgumentException('Valid reward amount is required.');
    }

    $reward_amount = number_format((float) $reward_amount, 8, '.', '');

    $db = getDBConnection();

    // Verify user has actually completed day 10
    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    // Check if user has completed all 10 days
    $completed_days_stmt = $db->prepare("
        SELECT COUNT(DISTINCT mission_day) as completed_days
        FROM mini_task_logs l
        JOIN mini_tasks t ON t.id = l.task_id
        WHERE l.user_id = ? AND t.task_group = 'mission' AND l.status = 'completed'
    ");
    $completed_days_stmt->execute([$user_id]);
    $completed_days = (int) $completed_days_stmt->fetchColumn();

    if ($completed_days < 10) {
        throw new RuntimeException('Complete all 10 days before claiming mystery box reward.');
    }

    // Check if already claimed
    $claimed_stmt = $db->prepare("
        SELECT COUNT(*) FROM reward_ledger
        WHERE user_id = ? AND source = 'bonus' AND description LIKE 'Mystery Box Reward%'
    ");
    $claimed_stmt->execute([$user_id]);
    if ((int) $claimed_stmt->fetchColumn() > 0) {
        throw new RuntimeException('Mystery box reward already claimed.');
    }

    // Add reward
    $old_balance = (float) ($user['balance'] ?? 0);
    $new_balance = $old_balance + (float) $reward_amount;

    $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$new_balance, $user_id]);

    $db->prepare("
        INSERT INTO reward_ledger (user_id, amount, balance_before, balance_after, source, description, created_at)
        VALUES (?, ?, ?, ?, 'bonus', ?, NOW())
    ")->execute([
        $user_id,
        $reward_amount,
        $old_balance,
        $new_balance,
        'Mystery Box Reward (' . $reward_amount . ' $REX)',
    ]);

    apiSuccessResponse([
        'message' => 'Mystery box reward claimed successfully!',
        'reward' => $reward_amount,
        'new_balance' => $new_balance,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
