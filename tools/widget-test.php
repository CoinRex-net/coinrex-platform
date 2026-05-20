<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$db = getDBConnection();
$requested_slug = coinrexWidgetNormalizeSlug($_GET['slug'] ?? '');
$selected_project = null;

if (tableHasColumn('projects', 'slug')) {
    if ($requested_slug !== '') {
        $stmt = $db->prepare("SELECT name, slug FROM projects WHERE LOWER(slug) = LOWER(?) AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved' LIMIT 1");
        $stmt->execute([$requested_slug]);
        $selected_project = $stmt->fetch();
    }

    if (!$selected_project) {
        $stmt = $db->query("SELECT name, slug FROM projects WHERE LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved' AND TRIM(COALESCE(slug, '')) <> '' ORDER BY id DESC LIMIT 1");
        $selected_project = $stmt->fetch();
    }
}

$project_name = trim((string) ($selected_project['name'] ?? ''));
$project_slug = coinrexWidgetNormalizeSlug((string) ($selected_project['slug'] ?? ''));
$allowed_domain = coinrexWidgetNormalizeDomain($_SERVER['HTTP_HOST'] ?? 'localhost');
$widget_token = $project_slug !== '' ? coinrexGenerateWidgetToken($project_slug, [$allowed_domain !== '' ? $allowed_domain : 'localhost'], 86400) : '';
$rating_url = BASE_URL . '/api/v1/project/' . rawurlencode($project_slug) . '/rating';
$widget_url = BASE_URL . '/api/v1/project/' . rawurlencode($project_slug) . '/widget?token=' . rawurlencode($widget_token);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CoinRex Widget Test</title>
</head>
<body style="margin:0;background:#0b1220;color:#e2ecff;padding:40px;font-family:Inter,Arial,sans-serif;">
  <div style="max-width:960px;margin:0 auto;display:grid;gap:24px;">
    <div style="padding:20px;border-radius:18px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);">
      <h1 style="margin:0 0 12px;font-size:28px;color:#fff;">CoinRex Widget Local Test</h1>
      <?php if ($project_slug === ''): ?>
        <p style="margin:0;color:#fecaca;">No approved project slug was found in your local database. Approve a project first, then reload this page.</p>
      <?php else: ?>
        <p style="margin:0 0 10px;">Testing approved project: <strong><?php echo htmlspecialchars($project_name !== '' ? $project_name : $project_slug, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <p style="margin:0 0 10px;">Slug: <code><?php echo htmlspecialchars($project_slug, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <p style="margin:0 0 10px;">Allowed domain: <code><?php echo htmlspecialchars($allowed_domain !== '' ? $allowed_domain : 'localhost', ENT_QUOTES, 'UTF-8'); ?></code></p>
        <div style="display:grid;gap:8px;font-size:14px;">
          <a href="<?php echo htmlspecialchars($rating_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" style="color:#60a5fa;">Open rating API</a>
          <a href="<?php echo htmlspecialchars($widget_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" style="color:#60a5fa;">Open widget API with token</a>
          <a href="<?php echo htmlspecialchars(BASE_URL . '/generate-widget-token.php?slug=' . rawurlencode($project_slug), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" style="color:#60a5fa;">Open token generator JSON</a>
        </div>
      <?php endif; ?>
    </div>

    <script src="<?php echo htmlspecialchars(BASE_URL . '/widget.js?v=20260508-3', ENT_QUOTES, 'UTF-8'); ?>" async></script>

    <?php if ($project_slug !== ''): ?>
      <div style="display:grid;gap:18px;align-items:start;">
        <div>
          <h2 style="margin:0 0 12px;font-size:18px;color:#fff;">Single Layout</h2>
          <div
            class="coinrex-widget"
            data-project="<?php echo htmlspecialchars($project_slug, ENT_QUOTES, 'UTF-8'); ?>"
            data-layout="single"
            data-bg="#111111"
            data-opacity="0.85"
            data-radius="18"
          ></div>
        </div>

        <div>
          <h2 style="margin:0 0 12px;font-size:18px;color:#fff;">Glass Layout</h2>
          <div
            class="coinrex-widget"
            data-project="<?php echo htmlspecialchars($project_slug, ENT_QUOTES, 'UTF-8'); ?>"
            data-layout="glass"
            data-token="<?php echo htmlspecialchars($widget_token, ENT_QUOTES, 'UTF-8'); ?>"
            data-bg="#111111"
            data-opacity="0.88"
            data-blur="18"
            data-radius="20"
            data-shadow="strong"
          ></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
