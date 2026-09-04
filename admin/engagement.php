<?php
$page_title = 'Engagement Control';
$activePage = 'engagement';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$message = '';
$error = '';

function engagementAdminDate($value): ?string {
    $value = trim((string) $value);
    return $value === '' ? null : str_replace('T', ' ', $value);
}
function engagementAdminNotify(PDO $db, int $userId, string $message): void {
    createNotification('user', $userId, [
        'title' => 'Social proof update',
        'message' => $message,
        'event_key' => 'social_gate.review',
        'action_url' => '/public/dashboard.php',
        'priority' => 'high',
    ], $db);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
        $action = (string) ($_POST['action'] ?? '');
        $adminId = (int) $current_admin['id'];
        $targetId = (int) ($_POST['id'] ?? $_POST['evidence_id'] ?? $_POST['assignment_id'] ?? 0);

        if ($action === 'campaign_create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $title = trim((string) ($_POST['modal_title'] ?? ''));
            $instructions = trim((string) ($_POST['modal_message'] ?? ''));
            $ctaLabel = trim((string) ($_POST['cta_label'] ?? ''));
            $ctaUrl = trim((string) ($_POST['cta_url'] ?? ''));
            $platform = ($_POST['platform'] ?? '') === 'telegram' ? 'telegram' : 'x';
            if ($name === '' || $title === '' || $instructions === '' || $ctaLabel === '') throw new RuntimeException('Please complete every required campaign field.');
            if (!filter_var($ctaUrl, FILTER_VALIDATE_URL)) throw new RuntimeException('Please enter a valid channel URL starting with https://.');
            if (!engagementValidProfileUrl($platform, $ctaUrl)) throw new RuntimeException($platform === 'x' ? 'X campaigns must use an x.com or twitter.com link.' : 'Telegram campaigns must use a t.me or telegram.me link.');
            $stmt = $db->prepare('INSERT INTO social_gate_campaigns (name,platform,modal_title,modal_message,cta_label,cta_url,max_strikes,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $platform, $title, $instructions, $ctaLabel, $ctaUrl, max(1, min(20, (int) ($_POST['max_strikes'] ?? 3))), $adminId, $adminId]);
            $targetId = (int) $db->lastInsertId();
            $message = 'Campaign saved as paused. Review it below, then activate it when ready.';
        } elseif ($action === 'campaign_toggle') {
            $enabled = !empty($_POST['enabled']);
            $db->beginTransaction();
            if ($enabled) $db->exec('UPDATE social_gate_campaigns SET is_active = 0');
            $db->prepare('UPDATE social_gate_campaigns SET is_active=?, activated_at=IF(?=1,NOW(),activated_at), updated_by=? WHERE id=?')->execute([$enabled ? 1 : 0, $enabled ? 1 : 0, $adminId, $targetId]);
            $db->commit();
            $message = $enabled ? 'Campaign is live. New registrations will now receive it.' : 'Campaign paused. Assigned users can access the platform until it is resumed.';
        } elseif ($action === 'campaign_limit') {
            $limit = max(1, min(20, (int) ($_POST['max_strikes'] ?? 3)));
            $db->beginTransaction();
            $db->prepare('UPDATE social_gate_campaigns SET max_strikes=?,updated_by=? WHERE id=?')->execute([$limit, $adminId, $targetId]);
            $db->prepare("UPDATE social_gate_assignments SET status='waived',waived_at=NOW() WHERE campaign_id=? AND status IN ('required','pending') AND strike_count>=?")->execute([$targetId, $limit]);
            $db->commit();
            $message = 'Attempt limit updated. Users already at the new limit were safely waived.';
        } elseif ($action === 'announcement_create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['message'] ?? ''));
            $ctaUrl = trim((string) ($_POST['cta_url'] ?? ''));
            if ($title === '' || $body === '') throw new RuntimeException('Announcement title and message are required.');
            if ($ctaUrl !== '' && !filter_var($ctaUrl, FILTER_VALIDATE_URL)) throw new RuntimeException('Optional CTA URL must start with https://.');
            $audience = ($_POST['audience'] ?? 'all') === 'registered_after' ? 'registered_after' : 'all';
            $audienceAfter = engagementAdminDate($_POST['audience_after'] ?? '');
            if ($audience === 'registered_after' && $audienceAfter === null) throw new RuntimeException('Choose an audience date, or select All users.');
            $stmt = $db->prepare('INSERT INTO engagement_announcements (title,message,cta_label,cta_url,audience,audience_after,starts_at,ends_at,is_active,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,1,?,?)');
            $stmt->execute([$title, $body, trim((string) ($_POST['cta_label'] ?? '')) ?: null, $ctaUrl ?: null, $audience, $audienceAfter, engagementAdminDate($_POST['starts_at'] ?? ''), engagementAdminDate($_POST['ends_at'] ?? ''), $adminId, $adminId]);
            $targetId = (int) $db->lastInsertId();
            $message = 'Announcement published successfully.';
        } elseif ($action === 'announcement_toggle') {
            $enabled = !empty($_POST['enabled']);
            $db->prepare('UPDATE engagement_announcements SET is_active=?,updated_by=? WHERE id=?')->execute([$enabled ? 1 : 0, $adminId, $targetId]);
            $message = $enabled ? 'Announcement enabled.' : 'Announcement disabled.';
        } elseif ($action === 'evidence_review') {
            $decision = (string) ($_POST['decision'] ?? '');
            $note = trim((string) ($_POST['note'] ?? ''));
            if (!in_array($decision, ['approve', 'return'], true)) throw new RuntimeException('Choose Approve or Return.');
            if ($decision === 'return' && $note === '') throw new RuntimeException('Please explain what the user needs to fix.');
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT e.*,a.user_id,a.strike_count,c.max_strikes FROM social_gate_evidence e JOIN social_gate_assignments a ON a.id=e.assignment_id JOIN social_gate_campaigns c ON c.id=a.campaign_id WHERE e.id=? FOR UPDATE');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch();
            if (!$row || $row['status'] !== 'pending') throw new RuntimeException('This proof was already reviewed.');
            $evidenceStatus = $decision === 'approve' ? 'approved' : 'returned';
            $db->prepare('UPDATE social_gate_evidence SET status=?,review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?')->execute([$evidenceStatus, $note ?: null, $adminId, $targetId]);
            if ($decision === 'approve') {
                $db->prepare("UPDATE social_gate_assignments SET status='approved',approved_at=NOW(),reviewed_by=?,admin_note=? WHERE id=?")->execute([$adminId, $note ?: null, $row['assignment_id']]);
                $userMessage = 'Your social proof was approved. Thank you for supporting CoinRex!';
                $message = 'Proof approved and the user was notified.';
            } else {
                $strikes = (int) $row['strike_count'] + 1;
                $status = $strikes >= max(1, (int) $row['max_strikes']) ? 'waived' : 'required';
                $db->prepare('UPDATE social_gate_assignments SET status=?,strike_count=?,waived_at=IF(?=1,NOW(),NULL),cta_clicked_at=NULL,reviewed_by=?,admin_note=? WHERE id=?')->execute([$status, $strikes, $status === 'waived' ? 1 : 0, $adminId, $note, $row['assignment_id']]);
                $userMessage = $status === 'waived' ? 'Your social setup requirement has been waived. You can continue using CoinRex.' : 'Your proof needs a small correction: ' . $note;
                $message = $status === 'waived' ? 'Maximum attempts reached; the user was waived and notified.' : 'Proof returned with clear instructions.';
            }
            engagementAdminNotify($db, (int) $row['user_id'], $userMessage);
            $db->commit();
        } elseif ($action === 'assignment_override') {
            $mode = (string) ($_POST['mode'] ?? '');
            if (!in_array($mode, ['approve', 'waive', 'reset', 'relock'], true)) throw new RuntimeException('Invalid user action.');
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT * FROM social_gate_assignments WHERE id=? FOR UPDATE');
            $stmt->execute([$targetId]);
            $assignment = $stmt->fetch();
            if (!$assignment) throw new RuntimeException('User assignment was not found.');
            $status = $mode === 'approve' ? 'approved' : ($mode === 'waive' ? 'waived' : 'required');
            $pendingStatus = $mode === 'approve' ? 'approved' : 'returned';
            $db->prepare("UPDATE social_gate_evidence SET status=?,review_note='Closed by admin override',reviewed_by=?,reviewed_at=NOW() WHERE assignment_id=? AND status='pending'")->execute([$pendingStatus, $adminId, $targetId]);
            $db->prepare('UPDATE social_gate_assignments SET status=?,strike_count=IF(?=1,0,strike_count),cta_clicked_at=IF(?=1,NULL,cta_clicked_at),approved_at=IF(?=1,NOW(),approved_at),waived_at=IF(?=1,NOW(),waived_at),reviewed_by=? WHERE id=?')->execute([$status, $mode === 'reset' ? 1 : 0, in_array($mode, ['reset','relock'], true) ? 1 : 0, $mode === 'approve' ? 1 : 0, $mode === 'waive' ? 1 : 0, $adminId, $targetId]);
            $copy = ['approve'=>'Your social requirement was manually approved.','waive'=>'Your social requirement was waived.','reset'=>'Your social attempts were reset. You can submit again.','relock'=>'Your social setup needs to be completed again.'];
            engagementAdminNotify($db, (int) $assignment['user_id'], $copy[$mode]);
            $db->commit();
            $message = 'User access updated and the user was notified.';
        } else {
            throw new RuntimeException('Unknown engagement action.');
        }
        $logPayload = $_POST;
        unset($logPayload['csrf_token']);
        logAdminActivity($adminId, 'engagement_' . $action, 'engagement', (string) $targetId, json_encode($logPayload, JSON_UNESCAPED_SLASHES));
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    $error = $e->getMessage();
}

$campaigns = $db->query("SELECT c.*,(SELECT COUNT(*) FROM social_gate_assignments a WHERE a.campaign_id=c.id) enrolled,(SELECT COUNT(*) FROM social_gate_assignments a WHERE a.campaign_id=c.id AND a.status='pending') pending FROM social_gate_campaigns c ORDER BY c.id DESC")->fetchAll();
$announcements = $db->query('SELECT n.*,COALESCE(SUM(e.view_count),0) views,SUM(e.dismissed_forever_at IS NOT NULL) optouts FROM engagement_announcements n LEFT JOIN engagement_announcement_events e ON e.announcement_id=n.id GROUP BY n.id ORDER BY n.id DESC')->fetchAll();
$queue = $db->query("SELECT e.*,a.id assignment_id,a.strike_count,c.platform,c.max_strikes,u.username,u.email FROM social_gate_evidence e JOIN social_gate_assignments a ON a.id=e.assignment_id JOIN social_gate_campaigns c ON c.id=a.campaign_id JOIN users u ON u.id=a.user_id WHERE e.status='pending' ORDER BY e.created_at")->fetchAll();
$statusFilter = in_array($_GET['status'] ?? '', ['required','pending','approved','waived'], true) ? $_GET['status'] : '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = []; $params = [];
if ($statusFilter !== '') { $where[] = 'a.status=?'; $params[] = $statusFilter; }
if ($search !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ?)'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
$sql = "SELECT a.*,c.name campaign_name,c.platform,u.username,u.email FROM social_gate_assignments a JOIN social_gate_campaigns c ON c.id=a.campaign_id JOIN users u ON u.id=a.user_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.updated_at DESC LIMIT 100';
$stmt = $db->prepare($sql); $stmt->execute($params); $assignments = $stmt->fetchAll();
$summary = $db->query("SELECT COUNT(*) enrolled,SUM(status='required') required_count,SUM(status='pending') pending_count,SUM(status='approved') approved_count,SUM(status='waived') waived_count FROM social_gate_assignments")->fetch() ?: [];
$csrf = htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/engagement.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/engagement.css'); ?>">
<div class="eng-admin">
 <?php if ($message): ?><div class="dashboard-message is-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
 <?php if ($error): ?><div class="dashboard-message is-error"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
 <section class="eng-welcome"><div class="eng-welcome-copy"><h1>Engagement Control</h1><p>Set up beginner-friendly social onboarding, share announcements, and review user proof from one place.</p></div><a class="btn eng-help-link" href="#how-it-works"><i class="fas fa-circle-question"></i> How it works</a></section>
 <section class="eng-guide" id="how-it-works"><div class="eng-guide-step"><span class="eng-guide-number">1</span><div><strong>Create a campaign</strong><span>Choose X or Telegram and write the instructions users will see.</span></div></div><div class="eng-guide-step"><span class="eng-guide-number">2</span><div><strong>Activate when ready</strong><span>Only users who register while it is active will be enrolled.</span></div></div><div class="eng-guide-step"><span class="eng-guide-number">3</span><div><strong>Review proof</strong><span>Approve clear proof or return it with a simple correction message.</span></div></div></section>
 <section class="eng-stats"><div class="eng-stat"><span>Total enrolled</span><strong><?php echo number_format((int) ($summary['enrolled'] ?? 0)); ?></strong></div><div class="eng-stat is-warning"><span>Waiting for review</span><strong><?php echo number_format((int) ($summary['pending_count'] ?? 0)); ?></strong></div><div class="eng-stat is-success"><span>Approved</span><strong><?php echo number_format((int) ($summary['approved_count'] ?? 0)); ?></strong></div><div class="eng-stat"><span>Need to submit</span><strong><?php echo number_format((int) ($summary['required_count'] ?? 0)); ?></strong></div></section>
 <nav class="eng-tabs"><a class="eng-tab" href="#campaigns"><i class="fas fa-user-check"></i> Social campaigns</a><a class="eng-tab" href="#announcements"><i class="fas fa-bullhorn"></i> Announcements</a><a class="eng-tab" href="#proofs"><i class="fas fa-clipboard-check"></i> Proof review <span><?php echo count($queue); ?></span></a><a class="eng-tab" href="#users"><i class="fas fa-users-gear"></i> User access</a></nav>

 <section class="eng-section" id="campaigns"><header class="eng-section-head"><div><h2>Social campaigns</h2><p>Create a paused draft first. Activate it only after checking the wording and link.</p></div></header><div class="eng-section-body eng-two-col">
  <form method="post" class="eng-form"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="campaign_create">
   <div class="eng-field"><label>Internal campaign name *</label><input name="name" required placeholder="Example: September X Follow"><small>Only admins see this name.</small></div>
   <div class="eng-inline-fields"><div class="eng-field"><label>Social platform *</label><select name="platform"><option value="x">X / Twitter</option><option value="telegram">Telegram</option></select></div><div class="eng-field"><label>Maximum returned attempts *</label><input name="max_strikes" type="number" min="1" max="20" value="3" required><small>Default: 3 attempts.</small></div></div>
   <div class="eng-field"><label>Popup heading *</label><input name="modal_title" required placeholder="Join the CoinRex community"></div>
   <div class="eng-field"><label>Friendly instructions *</label><textarea name="modal_message" required placeholder="Follow our official channel to receive product updates and community news."></textarea><small>Use plain language. The frontend already explains the three submission steps.</small></div>
   <div class="eng-inline-fields"><div class="eng-field"><label>Button text *</label><input name="cta_label" required placeholder="Open CoinRex on X"></div><div class="eng-field"><label>Official channel URL *</label><input name="cta_url" type="url" required placeholder="https://x.com/coinrex"></div></div>
   <div class="eng-form-note"><i class="fas fa-shield-halved"></i><span>New campaigns are saved as paused, so nothing changes for users until you activate one.</span></div><button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk"></i> Save campaign draft</button>
  </form>
  <div class="eng-list"><?php if (!$campaigns): ?><div class="eng-empty"><i class="fas fa-user-plus"></i><strong>No campaigns yet</strong><p>Create your first draft using the form.</p></div><?php endif; ?><?php foreach ($campaigns as $campaign): ?><article class="eng-item"><div class="eng-item-top"><div><span class="eng-item-title"><?php echo htmlspecialchars($campaign['name']); ?></span><div class="eng-item-meta"><span class="eng-badge <?php echo $campaign['is_active'] ? 'is-active' : 'is-paused'; ?>"><i class="fas fa-circle"></i><?php echo $campaign['is_active'] ? 'Live now' : 'Paused'; ?></span><span><?php echo strtoupper(htmlspecialchars($campaign['platform'])); ?></span><span><?php echo (int) $campaign['enrolled']; ?> enrolled</span><span><?php echo (int) $campaign['pending']; ?> pending</span></div></div><form method="post" data-confirm="<?php echo $campaign['is_active'] ? 'Pause this campaign? Assigned users will be temporarily unlocked.' : 'Activate this campaign? New registrations will start receiving it.'; ?>"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="campaign_toggle"><input type="hidden" name="id" value="<?php echo (int) $campaign['id']; ?>"><input type="hidden" name="enabled" value="<?php echo $campaign['is_active'] ? '0' : '1'; ?>"><button class="btn <?php echo $campaign['is_active'] ? 'btn-danger' : 'btn-primary'; ?>" type="submit"><?php echo $campaign['is_active'] ? 'Pause' : 'Activate'; ?></button></form></div><div class="eng-item-meta"><span>Button: <?php echo htmlspecialchars($campaign['cta_label']); ?></span><span>Created <?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></span></div><form method="post" class="eng-item-actions" style="margin-top:10px"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="campaign_limit"><input type="hidden" name="id" value="<?php echo (int) $campaign['id']; ?>"><div class="eng-field"><label>Attempt limit</label><input name="max_strikes" type="number" min="1" max="20" value="<?php echo (int) $campaign['max_strikes']; ?>" style="width:85px"></div><button class="btn" type="submit">Update limit</button></form></article><?php endforeach; ?></div>
 </div></section>

 <section class="eng-section" id="announcements"><header class="eng-section-head"><div><h2>Announcements</h2><p>Share a friendly update at login. Users can close it or hide that specific update forever.</p></div></header><div class="eng-section-body eng-two-col">
  <form method="post" class="eng-form"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="announcement_create"><div class="eng-field"><label>Announcement title *</label><input name="title" required placeholder="Welcome to the new CoinRex dashboard"></div><div class="eng-field"><label>Message *</label><textarea name="message" required placeholder="Here is what changed and where you can find it..."></textarea></div><div class="eng-inline-fields"><div class="eng-field"><label>Optional button text</label><input name="cta_label" placeholder="Read the guide"></div><div class="eng-field"><label>Optional button URL</label><input name="cta_url" type="url" placeholder="https://coinrex.xyz/guide"></div></div><div class="eng-field"><label>Who should see it? *</label><select name="audience" id="announcementAudience"><option value="all">All users</option><option value="registered_after">Users registered after a date</option></select></div><div class="eng-field" id="announcementAudienceDate" hidden><label>Registered after</label><input name="audience_after" type="datetime-local"></div><div class="eng-inline-fields"><div class="eng-field"><label>Start time (optional)</label><input name="starts_at" type="datetime-local"></div><div class="eng-field"><label>End time (optional)</label><input name="ends_at" type="datetime-local"></div></div><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Publish announcement</button></form>
  <div class="eng-list"><?php if (!$announcements): ?><div class="eng-empty"><i class="fas fa-bullhorn"></i><strong>No announcements yet</strong><p>Publish a welcome message or important update.</p></div><?php endif; ?><?php foreach ($announcements as $announcement): ?><article class="eng-item"><div class="eng-item-top"><div><span class="eng-item-title"><?php echo htmlspecialchars($announcement['title']); ?></span><div class="eng-item-meta"><span class="eng-badge <?php echo $announcement['is_active'] ? 'is-active' : 'is-paused'; ?>"><?php echo $announcement['is_active'] ? 'Enabled' : 'Disabled'; ?></span><span><?php echo number_format((int) $announcement['views']); ?> views</span><span><?php echo number_format((int) $announcement['optouts']); ?> opted out</span></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="announcement_toggle"><input type="hidden" name="id" value="<?php echo (int) $announcement['id']; ?>"><input type="hidden" name="enabled" value="<?php echo $announcement['is_active'] ? '0' : '1'; ?>"><button class="btn" type="submit"><?php echo $announcement['is_active'] ? 'Disable' : 'Enable'; ?></button></form></div><p class="eng-item-meta"><?php echo htmlspecialchars(mb_strimwidth($announcement['message'], 0, 150, '...')); ?></p></article><?php endforeach; ?></div>
 </div></section>

 <section class="eng-section" id="proofs"><header class="eng-section-head"><div><h2>Proof waiting for review</h2><p>Approve proof that clearly matches the profile. Return unclear proof with a helpful reason.</p></div><span class="eng-badge is-pending"><?php echo count($queue); ?> waiting</span></header><div class="eng-section-body"><div class="eng-proof-grid"><?php if (!$queue): ?><div class="eng-empty"><i class="fas fa-circle-check"></i><strong>You're all caught up</strong><p>New submissions will appear here.</p></div><?php endif; ?><?php foreach ($queue as $proof): ?><article class="eng-proof"><div class="eng-item-top"><div class="eng-proof-user"><span class="eng-avatar"><?php echo strtoupper(substr($proof['username'], 0, 1)); ?></span><div><strong><?php echo htmlspecialchars($proof['username']); ?></strong><span><?php echo htmlspecialchars($proof['email']); ?></span></div></div><span class="eng-badge is-pending">Attempt <?php echo (int) $proof['strike_count'] + 1; ?>/<?php echo (int) $proof['max_strikes']; ?></span></div><div class="eng-proof-preview"><a href="<?php echo htmlspecialchars($proof['screenshot_url']); ?>" target="_blank" rel="noopener"><img src="<?php echo htmlspecialchars($proof['screenshot_url']); ?>" alt="Proof screenshot from <?php echo htmlspecialchars($proof['username']); ?>"></a><div class="eng-proof-data"><strong><?php echo strtoupper(htmlspecialchars($proof['platform'])); ?> profile</strong><a href="<?php echo htmlspecialchars($proof['profile_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($proof['handle']); ?> <i class="fas fa-arrow-up-right-from-square"></i></a><span>Submitted <?php echo date('M j, Y g:i A', strtotime($proof['created_at'])); ?></span><span>Click screenshot to view full size.</span></div></div><div class="eng-review-actions"><form method="post" data-confirm="Approve this proof and permanently unlock the user?"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="evidence_review"><input type="hidden" name="evidence_id" value="<?php echo (int) $proof['id']; ?>"><input type="hidden" name="decision" value="approve"><button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Approve proof</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="evidence_review"><input type="hidden" name="evidence_id" value="<?php echo (int) $proof['id']; ?>"><input type="hidden" name="decision" value="return"><input name="note" required placeholder="What should the user fix?"><button class="btn btn-danger" type="submit"><i class="fas fa-rotate-left"></i> Return with reason</button></form></div><details class="eng-advanced"><summary>Advanced user actions</summary><div class="eng-item-actions"><?php foreach (['waive'=>'Waive requirement','reset'=>'Reset attempts','relock'=>'Lock again'] as $mode=>$label): ?><form method="post" data-confirm="<?php echo htmlspecialchars($label); ?> for this user?"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="assignment_override"><input type="hidden" name="assignment_id" value="<?php echo (int) $proof['assignment_id']; ?>"><input type="hidden" name="mode" value="<?php echo $mode; ?>"><button class="btn" type="submit"><?php echo $label; ?></button></form><?php endforeach; ?></div></details></article><?php endforeach; ?></div></div></section>

 <section class="eng-section" id="users"><header class="eng-section-head"><div><h2>User access</h2><p>Search enrolled users and safely correct their gate status.</p></div><form method="get" class="eng-filter"><input name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search username or email"><select name="status"><option value="">All statuses</option><?php foreach (['required','pending','approved','waived'] as $status): ?><option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select><button class="btn" type="submit"><i class="fas fa-search"></i> Search</button></form></header><div class="eng-table-wrap"><table class="eng-table"><thead><tr><th>User</th><th>Campaign</th><th>Status</th><th>Returned</th><th>Last updated</th><th>Actions</th></tr></thead><tbody><?php if (!$assignments): ?><tr><td colspan="6"><div class="eng-empty"><strong>No matching users</strong><p>Try a different search or status.</p></div></td></tr><?php endif; ?><?php foreach ($assignments as $userAssignment): ?><tr><td class="eng-table-user"><strong><?php echo htmlspecialchars($userAssignment['username']); ?></strong><span><?php echo htmlspecialchars($userAssignment['email']); ?></span></td><td><?php echo htmlspecialchars($userAssignment['campaign_name']); ?><br><small><?php echo strtoupper(htmlspecialchars($userAssignment['platform'])); ?></small></td><td><span class="eng-badge is-<?php echo htmlspecialchars($userAssignment['status']); ?>"><?php echo htmlspecialchars($userAssignment['status']); ?></span></td><td><?php echo (int) $userAssignment['strike_count']; ?></td><td><?php echo date('M j, Y', strtotime($userAssignment['updated_at'])); ?></td><td><details class="eng-advanced"><summary>Manage</summary><div class="eng-item-actions"><?php foreach (['approve'=>'Approve','waive'=>'Waive','reset'=>'Reset','relock'=>'Lock again'] as $mode=>$label): ?><form method="post" data-confirm="<?php echo $label; ?> this user's requirement?"><input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="assignment_override"><input type="hidden" name="assignment_id" value="<?php echo (int) $userAssignment['id']; ?>"><input type="hidden" name="mode" value="<?php echo $mode; ?>"><button class="btn" type="submit"><?php echo $label; ?></button></form><?php endforeach; ?></div></details></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function(form){form.addEventListener('submit',function(event){if(!window.confirm(form.dataset.confirm||'Continue?'))event.preventDefault();});});
const audience=document.getElementById('announcementAudience'),dateField=document.getElementById('announcementAudienceDate');
function syncAudience(){if(dateField)dateField.hidden=!audience||audience.value!=='registered_after';}
audience?.addEventListener('change',syncAudience);syncAudience();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
