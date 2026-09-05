<?php
/** BoostHub Partner Campaign Program - Pilot v1. Additive campaign helpers. */

function boostHubCampaignStatuses(): array {
    return ['draft', 'scheduled', 'active', 'paused', 'completed'];
}

function boostHubCampaignTimezone(): DateTimeZone {
    static $timezone = null;
    if ($timezone instanceof DateTimeZone) { return $timezone; }
    $name = trim((string) (getenv('COINREX_CAMPAIGN_TIMEZONE') ?: 'Asia/Karachi'));
    try { $timezone = new DateTimeZone($name); }
    catch (Throwable $e) { $timezone = new DateTimeZone('Asia/Karachi'); }
    return $timezone;
}

function boostHubCampaignDateTime(string $value): ?DateTimeImmutable {
    $value = trim(str_replace('T', ' ', $value));
    if ($value === '') { return null; }
    $timezone = boostHubCampaignTimezone();
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $timezone);
    if (!$date) {
        try { $date = new DateTimeImmutable($value, $timezone); }
        catch (Throwable $e) { return null; }
    }
    return $date;
}

function boostHubCampaignTimestamp(string $value): int {
    $date = boostHubCampaignDateTime($value);
    return $date ? $date->getTimestamp() : 0;
}

function boostHubCampaignStorageDateTime(string $value): string {
    $date = boostHubCampaignDateTime($value);
    return $date ? $date->format('Y-m-d H:i:s') : '';
}

function boostHubCampaignClientDateTime(string $value): string {
    $date = boostHubCampaignDateTime($value);
    return $date ? $date->format('Y-m-d\TH:i:sP') : '';
}

function boostHubCampaignParticipantCount(int $id, PDO $db = null): int {
    $db = $db ?: getDBConnection();
    $sql = 'SELECT COUNT(DISTINCT l.user_id) FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE t.campaign_id=:id AND l.status=\'completed\'';
    $q = $db->prepare($sql);
    $q->execute(['id' => $id]);
    return (int) $q->fetchColumn();
}

function boostHubCampaignUserIsParticipant(int $id, int $user, PDO $db = null): bool {
    $db = $db ?: getDBConnection();
    $sql = 'SELECT 1 FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE t.campaign_id=:id AND l.user_id=:user AND l.status=\'completed\' LIMIT 1';
    $q = $db->prepare($sql);
    $q->execute(['id' => $id, 'user' => $user]);
    return (bool) $q->fetchColumn();
}

function boostHubCampaignCapacityAllows(int $approved, int $maximum, bool $existing): bool {
    return $existing || ($maximum > 0 && $approved < $maximum);
}

function boostHubCampaignAssertParticipation(array $c, int $user, PDO $db = null): void {
    $db = $db ?: getDBConnection();
    $state = boostHubCampaignEffectiveState($c);
    if ($state !== 'active') { throw new RuntimeException(boostHubCampaignAvailabilityMessage($state)); }
    $id = (int) ($c['id'] ?? 0);
    $existing = boostHubCampaignUserIsParticipant($id, $user, $db);
    $approved = boostHubCampaignParticipantCount($id, $db);
    if (!boostHubCampaignCapacityAllows($approved, (int) ($c['max_participants'] ?? 0), $existing)) {
        throw new RuntimeException(boostHubCampaignAvailabilityMessage('full'));
    }
}

function boostHubCampaignGet(int $id, PDO $db = null): ?array {
    $db = $db ?: getDBConnection();
    $q = $db->prepare('SELECT * FROM boosthub_campaigns WHERE id=:id LIMIT 1');
    $q->execute(['id' => $id]);
    return $q->fetch() ?: null;
}

function boostHubCampaignList(PDO $db = null, bool $selectable = false): array {
    $db = $db ?: getDBConnection();
    $where = $selectable ? 'WHERE c.status <> \'completed\'' : '';
    $sql = 'SELECT c.*,COUNT(DISTINCT t.id) task_count,COUNT(DISTINCT CASE WHEN l.status=\'completed\' THEN l.user_id END) approved_participants FROM boosthub_campaigns c LEFT JOIN mini_tasks t ON t.campaign_id=c.id AND t.task_group=\'boosthub\' LEFT JOIN user_task_logs l ON l.task_id=t.id '.$where.' GROUP BY c.id ORDER BY c.created_at DESC';
    return $db->query($sql)->fetchAll();
}

function boostHubCampaignMapForUser(int $user, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $approved = [];
    $q = $db->prepare('SELECT DISTINCT t.campaign_id FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE l.user_id=:user AND l.status=\'completed\' AND t.campaign_id IS NOT NULL');
    $q->execute(['user' => $user]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) { $approved[(int) $id] = true; }
    $map = [];
    foreach (boostHubCampaignList($db) as $c) {
        $id = (int) $c['id'];
        $state = boostHubCampaignEffectiveState($c);
        if ($state === 'active' && empty($approved[$id]) && (int) $c['approved_participants'] >= (int) $c['max_participants']) { $state = 'full'; }
        $c['effective_state'] = $state;
        $c['user_is_participant'] = !empty($approved[$id]);
        $map[$id] = $c;
    }
    return $map;
}

/** Rebind locally uploaded campaign media to the host currently serving CoinRex. */
function boostHubCampaignPublicMediaUrl(string $value): string {
    $value = trim($value);
    if ($value === '') { return ''; }
    $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
    $candidate = ltrim($path !== '' ? $path : $value, '/');
    if (preg_match('~(?:^|/)(assets/uploads/campaign-(?:logos|covers)/[A-Za-z0-9._-]+)$~', $candidate, $match)) {
        return rtrim(BASE_URL, '/') . '/' . $match[1];
    }
    return $value;
}

function boostHubPublicCampaignsForUser(int $user, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $campaigns = [];
    foreach (boostHubCampaignMapForUser($user, $db) as $id => $campaign) {
        $state = (string) ($campaign['effective_state'] ?? 'closed');
        if (!in_array($state, ['active', 'full', 'scheduled', 'paused'], true)) { continue; }
        $maximum = (int) $campaign['max_participants'];
        $approved = (int) $campaign['approved_participants'];
        $campaigns[$id] = [
            'id' => $id,
            'campaign_name' => (string) $campaign['campaign_name'],
            'project_name' => (string) $campaign['project_name'],
            'project_logo' => boostHubCampaignPublicMediaUrl((string) ($campaign['project_logo'] ?? '')),
            'project_cover' => boostHubCampaignPublicMediaUrl((string) ($campaign['project_cover'] ?? '')),
            'project_website' => (string) ($campaign['project_website'] ?? ''),
            'short_description' => (string) ($campaign['short_description'] ?? ''),
            'start_at' => (string) $campaign['start_at'],
            'end_at' => (string) $campaign['end_at'],
            'effective_state' => $state,
            'approved_participants' => $approved,
            'max_participants' => $maximum,
            'remaining_slots' => max(0, $maximum - $approved),
            'tasks' => [],
        ];
    }
    if (!$campaigns) { return []; }
    return boostHubPublicCampaignsAttachTasks($campaigns, $user, $db);
}

function boostHubPublicCampaignsAttachTasks(array $campaigns, int $user, PDO $db): array {
    $ids = implode(',', array_map('intval', array_keys($campaigns)));
    $taskSql = 'SELECT id,campaign_id,title,description,reward,task_category FROM mini_tasks WHERE task_group=\'boosthub\' AND is_active=1 AND campaign_id IN (' . $ids . ') ORDER BY campaign_id,id';
    $tasks = $db->query($taskSql)->fetchAll();
    $logSql = 'SELECT l.task_id,l.status,l.metadata FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE l.user_id=:user AND t.campaign_id IN (' . $ids . ') ORDER BY l.id DESC';
    $logs = $db->prepare($logSql);
    $logs->execute(['user' => $user]);
    $flags = [];
    foreach ($logs->fetchAll() as $log) {
        $task = (int) $log['task_id'];
        $status = (string) $log['status'];
        $meta = !empty($log['metadata']) ? (json_decode((string) $log['metadata'], true) ?: []) : [];
        if ($status === 'completed') { $flags[$task]['completed'] = true; }
        if ($status === 'submitted') { $flags[$task]['submitted'] = true; }
        if ($status === 'pending') { $flags[$task]['assigned'] = true; }
        if ($status === 'failed' && !empty($meta['correction_requested'])) { $flags[$task]['correction'] = true; }
    }
    foreach ($tasks as $task) {
        $id = (int) $task['id'];
        $state = !empty($flags[$id]['completed']) ? 'completed'
            : (!empty($flags[$id]['submitted']) ? 'under_review'
            : (!empty($flags[$id]['correction']) ? 'correction'
            : (!empty($flags[$id]['assigned']) ? 'assigned' : 'available')));
        $task['user_state'] = $state;
        $campaigns[(int) $task['campaign_id']]['tasks'][] = $task;
    }
    foreach ($campaigns as &$campaign) {
        $total = count($campaign['tasks']);
        $completed = count(array_filter($campaign['tasks'], static function(array $task): bool {
            return ($task['user_state'] ?? '') === 'completed';
        }));
        $campaign['progress_completed'] = $completed;
        $campaign['progress_total'] = $total;
        $campaign['progress_percent'] = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }
    unset($campaign);
    return array_values($campaigns);
}

function boostHubStartCampaignTask(int $user, int $task, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $db->beginTransaction();
    try {
        $sql = 'SELECT t.id task_id,t.title,c.* FROM mini_tasks t JOIN boosthub_campaigns c ON c.id=t.campaign_id WHERE t.id=:task AND t.task_group=\'boosthub\' AND t.is_active=1 LIMIT 1 FOR UPDATE';
        $q = $db->prepare($sql);
        $q->execute(['task' => $task]);
        $campaign = $q->fetch();
        if (!$campaign) { throw new RuntimeException('This campaign task is not available.'); }
        boostHubCampaignAssertParticipation($campaign, $user, $db);
        $result = boostHubSwitchPendingAssignment($user, $task, (string) $campaign['title'], $db);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}

function boostHubSwitchPendingAssignment(int $user, int $task, string $title, PDO $db): array {
    $sql = 'SELECT l.id,l.task_id,l.metadata FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE l.user_id=:user AND l.status=\'pending\' AND t.task_group=\'boosthub\' ORDER BY l.id DESC LIMIT 1 FOR UPDATE';
    $q = $db->prepare($sql);
    $q->execute(['user' => $user]);
    $current = $q->fetch();
    if (!$current) {
        throw new RuntimeException('No task can be switched right now. Reload BoostHub after your cooldown ends.');
    }
    if ((int) $current['task_id'] === $task) {
        return ['task_id' => $task, 'task_title' => $title, 'already_assigned' => true];
    }
    $allowed = false;
    foreach (boostHubGetAssignableTasks($user, $db, (int) $current['task_id']) as $candidate) {
        if ((int) $candidate['id'] === $task) { $allowed = true; break; }
    }
    if (!$allowed) { throw new RuntimeException('This campaign task is not available for your account.'); }
    $metadata = !empty($current['metadata']) ? (json_decode((string) $current['metadata'], true) ?: []) : [];
    $metadata['skipped'] = true;
    $metadata['switched_to_campaign_task'] = $task;
    $metadata['skipped_at'] = date('c');
    taskHubUpdateLog((int) $current['id'], ['status' => 'failed', 'task_completed_at' => date('Y-m-d H:i:s'), 'metadata' => $metadata], $db);
    taskHubInsertLog($user, $task, 'pending', ['task_available_at' => date('Y-m-d H:i:s'), 'metadata' => ['boosthub_assigned' => 1, 'campaign_selected' => true]], $db);
    return ['task_id' => $task, 'task_title' => $title, 'already_assigned' => false];
}

function boostHubCampaignAnalytics(int $id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $campaign = boostHubCampaignGet($id, $db);
    if (!$campaign) { throw new RuntimeException('Campaign not found.'); }
    $summarySql = 'SELECT COUNT(l.id) total_submissions,COUNT(DISTINCT CASE WHEN l.status=\'completed\' THEN l.user_id END) unique_approved_participants,COALESCE(SUM(l.status=\'submitted\'),0) pending_submissions,COALESCE(SUM(l.status=\'completed\'),0) approved_submissions,COALESCE(SUM(l.status=\'failed\'),0) rejected_submissions,COALESCE(SUM(CASE WHEN l.status=\'completed\' THEN t.reward ELSE 0 END),0) total_rewards_issued FROM mini_tasks t LEFT JOIN user_task_logs l ON l.task_id=t.id WHERE t.campaign_id=:id';
    $actual = ' AND (l.status IN (0x7375626d6974746564,0x636f6d706c65746564) OR (l.status=0x6661696c6564 AND l.metadata LIKE 0x257265766965775f6f7574636f6d652572656a656374656425))';
    $summarySql = str_replace(' WHERE t.campaign_id=:id', $actual . ' WHERE t.campaign_id=:id', $summarySql);
    $q = $db->prepare($summarySql);
    $q->execute(['id' => $id]);
    $summary = $q->fetch();
    $rewardSql = 'SELECT COALESCE(SUM(r.amount),0) FROM reward_ledger r JOIN user_task_logs l ON l.user_id=r.user_id AND l.status=\'completed\' JOIN mini_tasks t ON t.id=l.task_id AND t.campaign_id=:id WHERE r.action_type=\'boosthub_manual_approval\' AND r.reference_id=CONCAT(\'boosthub:\',COALESCE(t.task_key,t.id))';
    $rewards = $db->prepare($rewardSql);
    $rewards->execute(['id' => $id]);
    $summary['total_rewards_issued'] = $rewards->fetchColumn();
    $taskSql = 'SELECT t.id,t.title,COUNT(l.id) total_submissions,COALESCE(SUM(l.status=\'completed\'),0) approved,COALESCE(SUM(l.status=\'submitted\'),0) pending,COALESCE(SUM(l.status=\'failed\'),0) rejected FROM mini_tasks t LEFT JOIN user_task_logs l ON l.task_id=t.id WHERE t.campaign_id=:id GROUP BY t.id,t.title ORDER BY t.id';
    $taskSql = str_replace(' WHERE t.campaign_id=:id', $actual . ' WHERE t.campaign_id=:id', $taskSql);
    $tasks = $db->prepare($taskSql);
    $tasks->execute(['id' => $id]);
    return boostHubCampaignFormatAnalytics($campaign, $summary, $tasks->fetchAll());
}

function boostHubCampaignFormatAnalytics(array $campaign, array $s, array $tasks): array {
    $participants = (int) ($s['unique_approved_participants'] ?? 0);
    $maximum = (int) ($campaign['max_participants'] ?? 0);
    $approved = (int) ($s['approved_submissions'] ?? 0);
    $rejected = (int) ($s['rejected_submissions'] ?? 0);
    $s['maximum_participants'] = $maximum;
    $s['remaining_participant_slots'] = max(0, $maximum - $participants);
    $s['capacity_utilization_percent'] = $maximum ? round($participants * 100 / $maximum, 2) : 0;
    $s['approval_rate'] = ($approved + $rejected) ? round($approved * 100 / ($approved + $rejected), 2) : 0;
    return ['campaign' => $campaign, 'summary' => $s, 'tasks' => $tasks];
}

function reviewTaskHubSubmissionSafely(int $log, bool $approve, PDO $db = null, array $options = []): array {
    $db = $db ?: getDBConnection();

    // These legacy schema guards may execute DDL. MySQL implicitly commits an
    // active transaction around DDL, so initialize them before taking the
    // campaign-capacity lock and starting the atomic approval write set.
    ensureRewardClaimSchema($db);
    ensureLevelEngineSchema($db);
    ensureEarlyAirdropSchema($db);

    $owns = !$db->inTransaction();
    if ($owns) { $db->beginTransaction(); }
    try {
        $sql = 'SELECT l.user_id,t.campaign_id,t.task_group FROM user_task_logs l JOIN mini_tasks t ON t.id=l.task_id WHERE l.id=:id AND l.status=\'submitted\' LIMIT 1 FOR UPDATE';
        $q = $db->prepare($sql);
        $q->execute(['id' => $log]);
        $submission = $q->fetch();
        if (!$submission) { throw new RuntimeException('Submission not found.'); }
        boostHubCampaignLockForApproval($submission, $approve, $db);
        $result = reviewTaskHubSubmission($log, $approve, $db, $options);
        if ($owns) { $db->commit(); }
        return $result;
    } catch (Throwable $e) {
        if ($owns && $db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}

function boostHubCampaignLockForApproval(array $submission, bool $approve, PDO $db): void {
    if (!$approve || $submission['task_group'] !== 'boosthub' || empty($submission['campaign_id'])) { return; }
    $q = $db->prepare('SELECT * FROM boosthub_campaigns WHERE id=:id LIMIT 1 FOR UPDATE');
    $q->execute(['id' => (int) $submission['campaign_id']]);
    $campaign = $q->fetch();
    if (!$campaign) { throw new RuntimeException('Campaign not found.'); }
    $id = (int) $campaign['id'];
    $existing = boostHubCampaignUserIsParticipant($id, (int) $submission['user_id'], $db);
    $approved = boostHubCampaignParticipantCount($id, $db);
    if (!boostHubCampaignCapacityAllows($approved, (int) $campaign['max_participants'], $existing)) {
        throw new RuntimeException(boostHubCampaignAvailabilityMessage('full'));
    }
}

function boostHubCampaignEffectiveState(array $campaign, ?int $now = null): string {
    $now = $now ?? time();
    $status = strtolower(trim((string) ($campaign['status'] ?? 'draft')));
    if (!in_array($status, boostHubCampaignStatuses(), true)) { return 'closed'; }
    if (in_array($status, ['draft', 'paused', 'completed'], true)) { return $status; }
    $start = boostHubCampaignTimestamp((string) ($campaign['start_at'] ?? ''));
    $end = boostHubCampaignTimestamp((string) ($campaign['end_at'] ?? ''));
    if (!$start || !$end || $now < $start) { return 'scheduled'; }
    if ($now > $end) { return 'expired'; }
    return $status === 'active' ? 'active' : 'scheduled';
}

function boostHubCampaignAvailabilityMessage(string $state): string {
    $messages = [
        'draft' => 'This campaign is not open yet.',
        'scheduled' => 'This campaign has not started yet.',
        'paused' => 'This campaign is currently paused.',
        'completed' => 'This campaign has been completed.',
        'expired' => 'This campaign has ended.',
        'full' => 'This campaign has reached its participant cap.',
    ];
    return $messages[$state] ?? 'This campaign is not accepting participation.';
}
