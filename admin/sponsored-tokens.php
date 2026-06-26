<?php
/**
 * CoinRex Admin - Sponsored Application Tokens
 * Generate and manage one-time-use tokens for sponsored project applications.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$activePage = 'sponsored-tokens';
$page_title = 'Sponsored Tokens';

$db = getDBConnection();
$errors = [];
$success = '';

// Handle token generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_token') {
        $expiry_days = max(1, min(30, (int) ($_POST['expiry_days'] ?? 7)));
        $project_id = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;

        try {
            $token = generateSponsoredToken($db, $expiry_days, $project_id);
            $success = 'Token generated successfully!';
            $new_token = $token;
        } catch (Exception $e) {
            $errors[] = 'Failed to generate token: ' . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'regenerate_token' && !empty($_POST['token_id'])) {
        $token_id = (int) $_POST['token_id'];

        // Get existing token info
        $stmt = $db->prepare("SELECT * FROM sponsored_tokens WHERE id = ?");
        $stmt->execute([$token_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $expiry_days = max(1, min(30, (int) ($_POST['expiry_days'] ?? 7)));
            try {
                $token = generateSponsoredToken($db, $expiry_days, $existing['project_id']);
                $success = 'New token generated for project #' . ($existing['project_id'] ?? 'N/A');
                $new_token = $token;
            } catch (Exception $e) {
                $errors[] = 'Failed to generate token: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Token not found.';
        }
    }
}

// Fetch all tokens
$tokens_stmt = $db->query("
    SELECT st.*, p.name AS project_name, p.approval_status
    FROM sponsored_tokens st
    LEFT JOIN projects p ON p.id = st.project_id
    ORDER BY st.created_at DESC
");
$tokens = $tokens_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending projects for dropdown
$pending_stmt = $db->query("
    SELECT id, name
    FROM projects
    WHERE approval_status = 'pending'
       OR LOWER(COALESCE(NULLIF(TRIM(sponsored_status), ''), 'none')) = 'requested'
    ORDER BY created_at DESC
    LIMIT 50
");
$pending_projects = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1><i class="fas fa-ticket-alt"></i> Sponsored Application Tokens</h1>
            <p class="muted">Generate one-time-use links for developers to submit sponsored project applications.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="dashboard-alert is-error">
            <?php foreach ($errors as $err): ?>
                <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($err); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="dashboard-alert is-success">
            <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></p>
            <?php if (isset($new_token)): ?>
                <div class="token-display-box">
                    <label>Share this link with the developer:</label>
                    <div class="token-url-wrapper">
                        <input type="text" class="token-url-input" value="<?php echo rtrim(BASE_URL, '/'); ?>/sponsored-apply.php?token=<?php echo htmlspecialchars($new_token); ?>" readonly onclick="this.select();">
                        <button type="button" class="btn btn-primary" onclick="copyTokenUrl(this)" data-url="<?php echo rtrim(BASE_URL, '/'); ?>/sponsored-apply.php?token=<?php echo htmlspecialchars($new_token); ?>">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-plus-circle"></i> Generate New Token</h3>
        </div>
        <div class="dashboard-panel-body">
            <form method="POST" class="token-generate-form">
                <input type="hidden" name="action" value="generate_token">

                <div class="form-row">
                    <div class="form-group">
                        <label for="expiry_days">Token Expiry (days)</label>
                        <input type="number" id="expiry_days" name="expiry_days" value="7" min="1" max="30" class="form-control">
                        <small class="hint">Token will expire after this many days if unused.</small>
                    </div>

                    <div class="form-group">
                        <label for="project_id">Link to Existing Project (optional)</label>
                        <select id="project_id" name="project_id" class="form-control">
                            <option value="">-- New application (no project) --</option>
                            <?php foreach ($pending_projects as $pp): ?>
                                <option value="<?php echo (int) $pp['id']; ?>">
                                    #<?php echo (int) $pp['id']; ?> - <?php echo htmlspecialchars($pp['name'] ?? 'Unnamed'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="hint">Select a pending project to allow the developer to edit their submission.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Generate Token
                </button>
            </form>
        </div>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-list"></i> All Tokens (<?php echo count($tokens); ?>)</h3>
        </div>
        <div class="dashboard-panel-body" style="padding:0;">
            <?php if (empty($tokens)): ?>
                <div class="empty-state">
                    <p>No tokens generated yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tokens as $t): ?>
                                <?php
                                $is_expired = strtotime($t['expires_at']) < time();
                                $is_used = (int) ($t['used'] ?? 0) === 1;
                                $status_class = $is_used ? 'is-approved' : ($is_expired ? 'is-rejected' : 'is-pending');
                                $status_label = $is_used ? 'Used' : ($is_expired ? 'Expired' : 'Active');
                                ?>
                                <tr>
                                    <td>
                                        <code style="font-size:11px;"><?php echo htmlspecialchars(substr($t['token'], 0, 16) . '...'); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($t['project_name']): ?>
                                            <a href="<?php echo ADMIN_BASE_URL; ?>/projects.php?project_id=<?php echo (int) $t['project_id']; ?>">
                                                <?php echo htmlspecialchars($t['project_name']); ?>
                                            </a>
                                            <br><small class="muted"><?php echo htmlspecialchars($t['approval_status'] ?? 'N/A'); ?></small>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="dashboard-pill <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($t['expires_at']); ?>">
                                            <?php echo date('M j, Y', strtotime($t['expires_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($t['created_at']); ?>">
                                            <?php echo date('M j, Y', strtotime($t['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$is_used && !$is_expired): ?>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="copyTokenUrl(this)" data-url="<?php echo rtrim(BASE_URL, '/'); ?>/sponsored-apply.php?token=<?php echo htmlspecialchars($t['token']); ?>">
                                                <i class="fas fa-copy"></i> Copy Link
                                            </button>
                                        <?php elseif ($is_used && $t['project_id']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="regenerate_token">
                                                <input type="hidden" name="token_id" value="<?php echo (int) $t['id']; ?>">
                                                <input type="hidden" name="expiry_days" value="7">
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-redo"></i> New Token
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.token-generate-form {
    padding: 20px;
}
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.form-row .form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 13px;
    color: var(--text-primary, #e0e0e0);
}
.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color, #333);
    border-radius: 6px;
    background: var(--bg-secondary, #1a1a2e);
    color: var(--text-primary, #e0e0e0);
    font-size: 14px;
}
.form-control:focus {
    outline: none;
    border-color: var(--accent-gold, #d4af37);
    box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.15);
}
.hint {
    display: block;
    font-size: 12px;
    color: var(--text-muted, #888);
    margin-top: 4px;
}
.token-display-box {
    margin-top: 16px;
    padding: 16px;
    background: var(--bg-secondary, #1a1a2e);
    border: 1px solid var(--accent-gold, #d4af37);
    border-radius: 8px;
}
.token-display-box label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 13px;
}
.token-url-wrapper {
    display: flex;
    gap: 8px;
}
.token-url-input {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid var(--border-color, #333);
    border-radius: 6px;
    background: var(--bg-primary, #0d0d1a);
    color: var(--accent-gold, #d4af37);
    font-size: 13px;
    font-family: monospace;
}
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-muted, #888);
}
</style>

<script>
function copyTokenUrl(btn) {
    const url = btn.getAttribute('data-url');
    if (!url) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.classList.add('is-success');
            setTimeout(function() {
                btn.innerHTML = original;
                btn.classList.remove('is-success');
            }, 2000);
        }).catch(function() {
            fallbackCopy(url, btn);
        });
    } else {
        fallbackCopy(url, btn);
    }
}

function fallbackCopy(text, btn) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(function() {
        btn.innerHTML = original;
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
