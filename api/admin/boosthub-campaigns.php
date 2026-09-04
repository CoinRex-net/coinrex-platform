<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(__DIR__, 2) . '/admin/includes/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!adminGuardIsLoggedIn() || !canCurrentAdmin('moderate_tasks')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}
$db = getDBConnection();
try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_REQUEST['action'] ?? $_REQUEST['action_type'] ?? 'list'));
    if ($method === 'GET') { boostHubCampaignAdminGet($action, $db); }
    if ($method === 'POST') { boostHubCampaignAdminPost($action, $db, getCurrentAdmin()); }
    throw new RuntimeException('Method not allowed.');
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function boostHubCampaignAdminGet(string $action, PDO $db): void {
    if ($action === 'analytics') {
        echo json_encode(['success' => true, 'data' => boostHubCampaignAnalytics((int) ($_GET['id'] ?? 0), $db)]);
        exit;
    }
    if ($action === 'view') {
        $id = (int) ($_GET['id'] ?? 0);
        $data = boostHubCampaignAnalytics($id, $db);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    echo json_encode(['success' => true, 'data' => boostHubCampaignList($db)]);
    exit;
}

function boostHubCampaignAdminPost(string $action, PDO $db, array $admin): void {
    if ($action === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        if ($id < 1 || !in_array($status, boostHubCampaignStatuses(), true)) { throw new RuntimeException('Invalid campaign status.'); }
        $q = $db->prepare('UPDATE boosthub_campaigns SET status=:status WHERE id=:id');
        $q->execute(['status' => $status, 'id' => $id]);
        logAdminActivity((int) $admin['id'], 'boosthub_campaign_status', 'boosthub_campaign', (string) $id, $status);
        echo json_encode(['success' => true, 'message' => 'Campaign status updated.']);
        exit;
    }
    if ($action !== 'save') { throw new RuntimeException('Unknown action.'); }
    boostHubCampaignAdminSave($db, $admin);
}

function boostHubCampaignAdminSave(PDO $db, array $admin): void {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['campaign_name'] ?? ''));
    $project = trim((string) ($_POST['project_name'] ?? ''));
    $website = trim((string) ($_POST['project_website'] ?? ''));
    $logo = trim((string) ($_POST['project_logo'] ?? ''));
    $logo = boostHubCampaignAdminLogoUpload($_FILES['project_logo_file'] ?? null, $logo);
    $cover = trim((string) ($_POST['project_cover'] ?? ''));
    $cover = boostHubCampaignAdminCoverUpload($_FILES['project_cover_file'] ?? null, $cover);
    $start = boostHubCampaignAdminDateTime((string) ($_POST['start_at'] ?? ''));
    $end = boostHubCampaignAdminDateTime((string) ($_POST['end_at'] ?? ''));
    $max = (int) ($_POST['max_participants'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'draft'));
    if ($name === '' || $project === '' || !$start || !$end || $max < 1) { throw new RuntimeException('Name, project, dates, and capacity are required.'); }
    if (strtotime($end) < strtotime($start)) { throw new RuntimeException('Campaign end must be after its start.'); }
    if (!in_array($status, boostHubCampaignStatuses(), true)) { throw new RuntimeException('Invalid campaign status.'); }
    foreach ([$website, $logo, $cover] as $url) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true))) {
            throw new RuntimeException('Website, logo, and cover must use valid HTTP(S) URLs.');
        }
    }
    boostHubCampaignAdminPersist($db, $admin, $id, compact('name','project','website','logo','cover','start','end','max','status'));
}

function boostHubCampaignAdminDateTime(string $value): string {
    return boostHubCampaignStorageDateTime($value);
}

function boostHubCampaignAdminLogoUpload(?array $file, string $current): string {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) { return $current; }
    if ($error !== UPLOAD_ERR_OK) { throw new RuntimeException('The logo upload did not complete. Please choose the file again.'); }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Campaign logo must be 2 MB or smaller.');
    }
    $temporary = (string) ($file['tmp_name'] ?? '');
    if ($temporary === '' || !is_uploaded_file($temporary)) { throw new RuntimeException('Invalid campaign logo upload.'); }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) { throw new RuntimeException('Campaign logo must be a JPG, PNG, or WebP image.'); }
    $dimensions = @getimagesize($temporary);
    if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
        throw new RuntimeException('Campaign logo dimensions must be between 1 and 4096 pixels.');
    }

    $directory = dirname(__DIR__, 2) . '/assets/uploads/campaign-logos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Campaign logo storage is not available.');
    }
    $filename = 'campaign_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporary, $directory . '/' . $filename)) {
        throw new RuntimeException('Campaign logo could not be saved.');
    }
    return rtrim(ASSETS_URL, '/') . '/uploads/campaign-logos/' . $filename;
}

function boostHubCampaignAdminCoverUpload(?array $file, string $current): string {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) { return $current; }
    if ($error !== UPLOAD_ERR_OK) { throw new RuntimeException('The cover upload did not complete. Please choose the file again.'); }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Campaign cover must be 5 MB or smaller.');
    }
    $temporary = (string) ($file['tmp_name'] ?? '');
    if ($temporary === '' || !is_uploaded_file($temporary)) { throw new RuntimeException('Invalid campaign cover upload.'); }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) { throw new RuntimeException('Campaign cover must be a JPG, PNG, or WebP image.'); }
    $dimensions = @getimagesize($temporary);
    if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
        throw new RuntimeException('Campaign cover dimensions must be between 1 and 6000 pixels.');
    }

    $directory = dirname(__DIR__, 2) . '/assets/uploads/campaign-covers';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Campaign cover storage is not available.');
    }
    $filename = 'cover_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporary, $directory . '/' . $filename)) {
        throw new RuntimeException('Campaign cover could not be saved.');
    }
    return rtrim(ASSETS_URL, '/') . '/uploads/campaign-covers/' . $filename;
}

function boostHubCampaignAdminPersist(PDO $db, array $admin, int $id, array $v): void {
    $v['description'] = trim((string) ($_POST['short_description'] ?? ''));
    $v['notes'] = trim((string) ($_POST['internal_notes'] ?? ''));
    if ($id > 0) {
        $sql = 'UPDATE boosthub_campaigns SET campaign_name=:name,project_name=:project,project_logo=:logo,project_cover=:cover,project_website=:website,short_description=:description,start_at=:start,end_at=:end,max_participants=:max,status=:status,internal_notes=:notes WHERE id=:id';
        $v['id'] = $id;
    } else {
        $sql = 'INSERT INTO boosthub_campaigns(campaign_name,project_name,project_logo,project_cover,project_website,short_description,start_at,end_at,max_participants,status,internal_notes) VALUES(:name,:project,:logo,:cover,:website,:description,:start,:end,:max,:status,:notes)';
    }
    $db->prepare($sql)->execute($v);
    $id = $id ?: (int) $db->lastInsertId();
    logAdminActivity((int) $admin['id'], 'boosthub_campaign_save', 'boosthub_campaign', (string) $id, $v['name']);
    echo json_encode(['success' => true, 'message' => 'Campaign saved.', 'id' => $id]);
    exit;
}
