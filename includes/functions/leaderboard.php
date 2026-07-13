<?php
/** Public leaderboard helpers. */

function getLeaderboardMetricOptions(): array {
    return [
        'valid_referrals' => [
            'label' => 'Valid Referral',
            'short_label' => 'Referrals',
            'description' => 'Qualified referrals counted by qualification date.',
            'score_suffix' => 'Valid Referrals',
            'icon' => 'fas fa-user-plus',
        ],
        'boosthub_active' => [
            'label' => 'Most BoostHub Active',
            'short_label' => 'BoostHub',
            'description' => 'Completed BoostHub tasks in the selected time period.',
            'score_suffix' => 'Tasks Completed',
            'icon' => 'fas fa-bolt',
        ],
        'rex_earned' => [
            'label' => 'Most $REX Earn',
            'short_label' => '$REX Earned',
            'description' => 'Net earned $REX from reward ledger activity in the selected time period.',
            'score_suffix' => '$REX Earned',
            'icon' => 'fas fa-coins',
        ],
    ];
}

function getLeaderboardPeriodOptions(): array {
    return [
        'today' => [
            'label' => 'Today',
            'days' => 1,
        ],
        '7d' => [
            'label' => 'Last 7 Days',
            'days' => 7,
        ],
        '30d' => [
            'label' => 'Last 30 Days',
            'days' => 30,
        ],
    ];
}

function normalizeLeaderboardMetric($metric): string {
    $metric = strtolower(trim((string) $metric));
    $options = getLeaderboardMetricOptions();
    return isset($options[$metric]) ? $metric : 'valid_referrals';
}

function normalizeLeaderboardPeriod($period): string {
    $period = strtolower(trim((string) $period));
    $options = getLeaderboardPeriodOptions();
    return isset($options[$period]) ? $period : 'today';
}

function getLeaderboardPeriodStart(string $period): string {
    $period = normalizeLeaderboardPeriod($period);
    $timezone = new DateTimeZone(date_default_timezone_get());
    $now = new DateTimeImmutable('now', $timezone);

    if ($period === 'today') {
        return $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    }

    $days = (int) (getLeaderboardPeriodOptions()[$period]['days'] ?? 1);
    return $now->modify('-' . max(1, $days - 1) . ' days')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
}

function leaderboardBuildUserCard(array $row): array {
    $username = trim((string) ($row['username'] ?? ''));
    $full_name = trim((string) ($row['full_name'] ?? ''));
    $display_name = $username !== '' ? $username : ($full_name !== '' ? $full_name : 'User');
    $level = normalizeUserLevel((string) ($row['level'] ?? 'beginner'));

    return [
        'user_id' => (int) ($row['user_id'] ?? $row['id'] ?? 0),
        'username' => $display_name,
        'full_name' => $full_name,
        'avatar_url' => coinrexNormalizeMediaUrl((string) ($row['avatar'] ?? '')),
        'avatar_initial' => strtoupper(substr($display_name, 0, 1)),
        'level' => $level,
        'level_label' => levelDisplayName($level),
    ];
}

function formatLeaderboardScore(string $metric, $score): string {
    $metric = normalizeLeaderboardMetric($metric);
    $score = (float) $score;

    if ($metric === 'rex_earned') {
        return number_format($score, 2) . ' $REX';
    }

    return number_format((int) round($score));
}

function getLeaderboardEntries($metric = 'valid_referrals', $period = 'today', $limit = 10, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);
    ensureLevelEngineSchema($db);

    $metric = normalizeLeaderboardMetric($metric);
    $period = normalizeLeaderboardPeriod($period);
    $limit = max(1, min(10, (int) $limit));
    $period_start = getLeaderboardPeriodStart($period);

    if ($metric === 'boosthub_active') {
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.full_name,
                u.avatar,
                u.level,
                COUNT(*) AS score,
                MAX(COALESCE(utl.task_completed_at, utl.completed_at)) AS last_activity_at
            FROM user_task_logs utl
            INNER JOIN mini_tasks mt
                ON mt.id = utl.task_id
            INNER JOIN users u
                ON u.id = utl.user_id
            WHERE mt.task_group = 'boosthub'
              AND utl.status = 'completed'
              AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
            GROUP BY u.id, u.username, u.full_name, u.avatar, u.level
            HAVING score > 0
            ORDER BY score DESC, last_activity_at ASC, u.id ASC
            LIMIT {$limit}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$period_start]);
    } elseif ($metric === 'rex_earned') {
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.full_name,
                u.avatar,
                u.level,
                ROUND(COALESCE(SUM(rl.amount), 0), 8) AS score,
                MAX(rl.created_at) AS last_activity_at
            FROM reward_ledger rl
            INNER JOIN users u
                ON u.id = rl.user_id
            WHERE rl.created_at >= ?
            GROUP BY u.id, u.username, u.full_name, u.avatar, u.level
            HAVING score > 0
            ORDER BY score DESC, last_activity_at ASC, u.id ASC
            LIMIT {$limit}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$period_start]);
    } else {
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.full_name,
                u.avatar,
                u.level,
                COUNT(*) AS score,
                MAX(child.referral_qualified_at) AS last_activity_at
            FROM users child
            INNER JOIN users u
                ON u.id = child.referred_by
            WHERE child.referred_by IS NOT NULL
              AND child.referred_by > 0
              AND child.referral_review_status = 'qualified'
              AND child.referral_qualified_at IS NOT NULL
              AND child.referral_qualified_at >= ?
            GROUP BY u.id, u.username, u.full_name, u.avatar, u.level
            HAVING score > 0
            ORDER BY score DESC, last_activity_at ASC, u.id ASC
            LIMIT {$limit}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$period_start]);
    }

    $rows = $stmt->fetchAll() ?: [];
    $entries = [];
    foreach ($rows as $index => $row) {
        $entries[] = array_merge(leaderboardBuildUserCard($row), [
            'rank' => $index + 1,
            'score' => $metric === 'rex_earned'
                ? round((float) ($row['score'] ?? 0), 2)
                : (int) ($row['score'] ?? 0),
            'score_display' => formatLeaderboardScore($metric, $row['score'] ?? 0),
            'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
        ]);
    }

    return $entries;
}

