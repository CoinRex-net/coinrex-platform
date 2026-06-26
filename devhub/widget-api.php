<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Widgets & API';
$activePage = 'widget-api';

require_once __DIR__ . '/includes/header.php';

echo '<link rel="stylesheet" href="' . BASE_URL . '/devhub/assets/css/dashboard.css">';
echo '<link rel="stylesheet" href="' . ASSETS_URL . '/css/rating-badge.css">';

$user_id = (int) (getCurrentUserId() ?? 0);
$db = getDevHubDB();

$widget_projects = [];
$widget_selected_project_id = (int) ($_GET['widget_project_id'] ?? 0);
$widget_requested_domain = trim((string) ($_GET['widget_domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')));
$widget_requested_domain = coinrexWidgetNormalizeDomain($widget_requested_domain);
$widget_domain_value = $widget_requested_domain !== '' ? $widget_requested_domain : 'localhost';
$widget_domain_label = $widget_domain_value;

$widget_selected_project = null;
$widget_selected_slug = '';
$widget_selected_status = 'pending';
$widget_can_generate = false;
// Only generate when user explicitly clicks "Generate Widget"
$widget_generation_requested = isset($_GET['generate']) && $_GET['generate'] === '1';

$widget_rating_url = '';
$widget_api_url = '';
$widget_token = '';
$widget_single_embed = '';
$widget_glass_embed = '';
$widget_generation_message = '';
$widget_generation_message_type = 'info';
$widget_preview_value = 0.0;
$widget_preview_reviews = 0;
$widget_single_preview_html = '';
$widget_glass_preview_html = '';

if ($user_id > 0 && tableHasColumn('projects', 'slug')) {
    $stmt = $db->prepare("\n        SELECT id, name, slug, LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) AS approval_status\n        FROM projects\n        WHERE created_by = ? AND TRIM(COALESCE(slug, '')) <> ''\n        ORDER BY updated_at DESC, id DESC\n    ");
    $stmt->execute([$user_id]);
    $widget_projects = $stmt->fetchAll() ?: [];

    if ($widget_selected_project_id <= 0 && !empty($widget_projects)) {
        $widget_selected_project_id = (int) ($widget_projects[0]['id'] ?? 0);
    }

    foreach ($widget_projects as $candidate_project) {
        if ((int) ($candidate_project['id'] ?? 0) === $widget_selected_project_id) {
            $widget_selected_project = $candidate_project;
            break;
        }
    }

    if ($widget_selected_project) {
        try {
            $project_preview_stmt = $db->prepare("\n                SELECT avg_rating, total_reviews\n                FROM projects\n                WHERE id = ?\n                LIMIT 1\n            ");
            $project_preview_stmt->execute([(int) ($widget_selected_project['id'] ?? 0)]);
            $project_preview_data = $project_preview_stmt->fetch() ?: [];
            $widget_preview_value = max(0.0, min(5.0, (float) ($project_preview_data['avg_rating'] ?? 0)));
            $widget_preview_reviews = max(0, (int) ($project_preview_data['total_reviews'] ?? 0));
        } catch (Throwable $e) {
            $widget_preview_value = 0.0;
            $widget_preview_reviews = 0;
        }

        if ($widget_preview_value <= 0) {
            $widget_preview_value = 4.8;
        }
        if ($widget_preview_reviews <= 0) {
            $widget_preview_reviews = 128;
        }

        $widget_selected_slug = coinrexWidgetNormalizeSlug((string) ($widget_selected_project['slug'] ?? ''));
        $widget_selected_status = strtolower(trim((string) ($widget_selected_project['approval_status'] ?? 'pending')));
        $widget_can_generate = ($widget_selected_slug !== '' && $widget_selected_status === 'approved' && $widget_requested_domain !== '');

        // Render real widget previews for sample cards (no generation — just visual)
        $widget_single_preview_html = renderUniversalRating([
            'provider' => 'coinrex',
            'variant' => 'cr-row-small',
            'size' => 'md',
            'value' => $widget_preview_value,
            'scale' => 5,
            'show_stars' => true,
            'show_score' => true,
            'show_count' => false,
            'class' => 'widget-preview-rating widget-preview-rating--single',
            'aria_label' => 'Single widget preview for ' . (string) ($widget_selected_project['name'] ?? 'Project'),
        ]);

        $widget_glass_preview_html = renderUniversalRating([
            'provider' => 'coinrex',
            'variant' => 'cr-box-large',
            'size' => 'lg',
            'value' => $widget_preview_value,
            'scale' => 5,
            'show_stars' => true,
            'show_score' => true,
            'show_count' => false,
            'class' => 'widget-preview-rating widget-preview-rating--glass',
            'aria_label' => 'Glass widget preview for ' . (string) ($widget_selected_project['name'] ?? 'Project'),
        ]);

        if ($widget_generation_requested) {
            if ($widget_requested_domain === '') {
                $widget_generation_message = 'Please enter a valid domain before generating widget assets.';
                $widget_generation_message_type = 'error';
            } elseif ($widget_selected_status !== 'approved') {
                $widget_generation_message = 'This project must be approved before public widget assets can be generated.';
                $widget_generation_message_type = 'warning';
            } elseif ($widget_can_generate) {
                $widget_token = coinrexGenerateWidgetToken($widget_selected_slug, [$widget_requested_domain], 86400);
                $widget_rating_url = BASE_URL . '/api/v1/project/' . rawurlencode($widget_selected_slug) . '/rating';
                $widget_api_url = BASE_URL . '/api/v1/project/' . rawurlencode($widget_selected_slug) . '/widget?token=' . rawurlencode($widget_token);
                $widget_single_embed = '<script src="' . BASE_URL . '/widget.js" async><\/script>' . "\n"
                    . '<div class="coinrex-widget" data-project="' . htmlspecialchars($widget_selected_slug, ENT_QUOTES, 'UTF-8') . '" data-layout="single"></div>';
                $widget_glass_embed = '<script src="' . BASE_URL . '/widget.js" async><\/script>' . "\n"
                    . '<div class="coinrex-widget" data-project="' . htmlspecialchars($widget_selected_slug, ENT_QUOTES, 'UTF-8') . '" data-layout="glass" data-token="' . htmlspecialchars($widget_token, ENT_QUOTES, 'UTF-8') . '"></div>';
                $widget_generation_message = 'Widget assets generated successfully. Preview them below or copy the code into your site.';
                $widget_generation_message_type = 'success';
            }
        }
    }
}

// Check if user is verified
$is_verified_widget = $user_id > 0 ? isVerifiedDeveloper($user_id) : false;
?>
<div class="dashboard-wrapper">
    <div class="widget-api-section" id="widget-api-generator">

        <!-- ─── HERO ─── -->
        <div class="widget-api-v2-hero">
            <div class="widget-api-v2-hero-text">
                <h2><i class="fas fa-puzzle-piece" style="color:var(--dh-accent-soft);margin-right:10px;"></i>Widget & API Generator</h2>
                <p>Create embeddable rating widgets for your approved projects.</p>
            </div>
            <div class="widget-api-v2-hero-actions">
                <button type="button" class="btn-create-widget" id="createWidgetBtn">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span>Create Widget</span>
                </button>
            </div>
        </div>

        <!-- ─── SPONSORED MARKETING CARD ─── -->
        <div class="sponsored-marketing-card" style="margin-bottom:24px;">
            <div class="sponsored-marketing-content">
                <div class="sponsored-marketing-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="sponsored-marketing-text">
                    <h3>Get Sponsored Placement — No Verification Needed</h3>
                    <p>Want your project to stand out? Sponsored placement gives you <strong>top priority visibility</strong> with a premium sponsored badge, appearing prominently across CoinRex for all users — no developer verification required.</p>
                    <div class="sponsored-marketing-benefits">
                        <span><i class="fas fa-check-circle"></i> Top Priority Placement</span>
                        <span><i class="fas fa-check-circle"></i> Premium Sponsored Badge</span>
                        <span><i class="fas fa-check-circle"></i> Visible to All Users</span>
                        <span><i class="fas fa-check-circle"></i> No Verification Needed</span>
                    </div>
                </div>
                <div class="sponsored-marketing-action">
                    <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-sponsored-cta">
                        <i class="fas fa-envelope"></i> Contact Now
                    </a>
                    <p>Get in touch with our team</p>
                </div>
            </div>
        </div>

        <!-- ─── UNVERIFIED CTA ─── -->
        <?php if (!$is_verified_widget): ?>
        <div class="verify-cta-banner" style="margin-bottom:24px;">
            <div class="verify-cta-content">
                <div class="verify-cta-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="verify-cta-text">
                    <h3>Get Verified to Generate Widgets</h3>
                    <p>Developer verification is required to create embeddable rating widgets for your projects. Verify your identity to unlock this feature and build trust with the CoinRex community.</p>
                    <div class="verify-cta-benefits">
                        <span><i class="fas fa-check-circle"></i> Embeddable Rating Widgets</span>
                        <span><i class="fas fa-check-circle"></i> API Access</span>
                        <span><i class="fas fa-check-circle"></i> Project Registration</span>
                        <span><i class="fas fa-check-circle"></i> Verified Badge</span>
                    </div>
                </div>
                <div class="verify-cta-action">
                    <a href="<?php echo BASE_URL; ?>/devhub/apply.php" class="btn-verify-cta">
                        <i class="fas fa-check-double"></i> Get Verified Now
                    </a>
                    <p>Takes only a few minutes</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($widget_projects) && $is_verified_widget): ?>
            <!-- ─── EMPTY STATE ─── -->
            <div class="widget-api-v2-empty">
                <i class="fas fa-code"></i>
                <h4>No projects available</h4>
                <p>Submit and get a project approved first. Then come back here to generate widgets.</p>
            </div>
        <?php else: ?>

            <!-- ─── COLLAPSIBLE FORM ─── -->
            <div class="widget-api-v2-form-wrap" id="widgetFormWrap">
                <div class="widget-api-v2-form-card">
                    <form method="GET" action="" id="widgetApiForm">
                        <div class="widget-api-v2-form-grid">
                            <div class="widget-api-v2-form-field">
                                <label for="widgetProjectId">Project</label>
                                <select id="widgetProjectId" name="widget_project_id">
                                    <?php foreach ($widget_projects as $widget_project_option): ?>
                                        <?php
                                        $option_status = strtolower(trim((string) ($widget_project_option['approval_status'] ?? 'pending')));
                                        $option_label = (string) ($widget_project_option['name'] ?? 'Project');
                                        $option_slug = (string) ($widget_project_option['slug'] ?? '');
                                        ?>
                                        <option value="<?php echo (int) ($widget_project_option['id'] ?? 0); ?>" <?php echo (int) ($widget_project_option['id'] ?? 0) === $widget_selected_project_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option_label . ' [' . $option_slug . '] - ' . ucfirst($option_status), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Only approved projects can generate public widgets.</small>
                            </div>

                            <div class="widget-api-v2-form-field">
                                <label for="widgetDomain">Allowed Domain</label>
                                <input type="text" id="widgetDomain" name="widget_domain" value="<?php echo htmlspecialchars($widget_domain_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="example.com or localhost">
                                <small>Use your real domain in production. Keep <strong>localhost</strong> for testing.</small>
                            </div>
                        </div>

                        <div class="widget-api-v2-form-actions">
                            <input type="hidden" name="generate" value="1">
                            <button type="submit" class="btn-generate" id="widgetGenerateBtn">
                                <i class="fas fa-wand-magic-sparkles"></i> Generate Widget
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($widget_selected_project): ?>
                <!-- ─── STATUS BAR ─── -->
                <div class="widget-api-v2-status">
                    <span class="widget-api-v2-status-item">
                        <strong><?php echo htmlspecialchars((string) ($widget_selected_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </span>
                    <span class="widget-api-v2-status-divider"></span>
                    <span class="widget-api-v2-status-item">
                        Slug: <strong><?php echo htmlspecialchars($widget_selected_slug, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </span>
                    <span class="widget-api-v2-status-divider"></span>
                    <span class="widget-api-v2-status-item">
                        Domain: <strong><?php echo htmlspecialchars($widget_domain_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </span>
                    <span class="widget-api-v2-status-divider"></span>
                    <span class="widget-api-v2-status-item" style="color:<?php echo $widget_selected_status === 'approved' ? '#4ade80' : '#fbbf24'; ?>;">
                        <?php echo htmlspecialchars(ucfirst($widget_selected_status), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <!-- ─── NOTICE ─── -->
                <?php if ($widget_generation_message !== ''): ?>
                    <div class="widget-api-v2-notice widget-api-v2-notice--<?php echo htmlspecialchars($widget_generation_message_type, ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom:20px;">
                        <i class="fas <?php echo $widget_generation_message_type === 'success' ? 'fa-check-circle' : ($widget_generation_message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?>"></i>
                        <span><?php echo htmlspecialchars($widget_generation_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($widget_generation_requested && !$widget_can_generate): ?>
                    <!-- ─── BLOCKED STATE ─── -->
                    <div class="widget-api-v2-blocked">
                        <i class="fas fa-shield-alt"></i>
                        <span>
                            <?php if ($widget_requested_domain === ''): ?>
                                Enter a valid domain first, such as <strong>localhost</strong> for testing or your real website domain for production.
                            <?php else: ?>
                                This project is currently <strong><?php echo htmlspecialchars($widget_selected_status, ENT_QUOTES, 'UTF-8'); ?></strong>. Public widget generation is enabled only for approved projects.
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- ─── SAMPLE PREVIEW CARDS (real rendered widgets, no generation) ─── -->
                <div class="widget-api-v2-preview-grid">
                    <div class="widget-api-v2-preview-card">
                        <div class="widget-api-v2-preview-head">
                            <h4><i class="fas fa-minus" style="color:var(--dh-accent-soft);margin-right:6px;"></i>Single Widget</h4>
                            <span class="widget-api-v2-preview-badge">Compact</span>
                        </div>
                        <div class="widget-api-v2-preview-stage">
                            <?php echo $widget_single_preview_html; ?>
                        </div>
                        <p style="color:var(--dh-text-secondary);font-size:13px;line-height:1.5;margin:0;">A compact rating row — perfect for cards, lists, and side panels.</p>
                        <button type="button" class="widget-api-v2-sample-select" data-layout="single">
                            <i class="fas fa-arrow-right"></i> Use Single Widget
                        </button>
                    </div>

                    <div class="widget-api-v2-preview-card">
                        <div class="widget-api-v2-preview-head">
                            <h4><i class="fas fa-grip-lines" style="color:var(--dh-accent-soft);margin-right:6px;"></i>Glass Widget</h4>
                            <span class="widget-api-v2-preview-badge">Premium</span>
                        </div>
                        <div class="widget-api-v2-preview-stage widget-api-v2-preview-stage--glass">
                            <?php echo $widget_glass_preview_html; ?>
                            <div class="widget-api-v2-preview-meta">
                                <strong><?php echo htmlspecialchars((string) ($widget_selected_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo number_format($widget_preview_reviews); ?> reviews on record</span>
                            </div>
                        </div>
                        <p style="color:var(--dh-text-secondary);font-size:13px;line-height:1.5;margin:0;">A premium featured box with glass effect — great for hero sections.</p>
                        <button type="button" class="widget-api-v2-sample-select" data-layout="glass">
                            <i class="fas fa-arrow-right"></i> Use Glass Widget
                        </button>
                    </div>
                </div>

                <?php if ($widget_generation_requested && $widget_can_generate): ?>
                    <!-- ─── RESULTS (only after generation) ─── -->
                    <div class="widget-api-v2-results">

                        <!-- Code Output -->
                        <div class="widget-api-v2-output">

                            <!-- Group 1: Rating Data -->
                            <div class="widget-api-v2-output-group">
                                <div class="widget-api-v2-output-group-head">
                                    <i class="fas fa-database"></i>
                                    <h4>Rating Data</h4>
                                    <p>For custom implementations</p>
                                </div>
                                <div class="widget-api-v2-output-items">
                                    <div class="widget-api-v2-output-item">
                                        <div class="widget-api-v2-output-item-label">
                                            <i class="fas fa-link"></i>
                                            <span>Rating API URL</span>
                                        </div>
                                        <div class="widget-api-v2-output-item-code">
                                            <input id="widgetRatingUrl" type="text" readonly value="<?php echo htmlspecialchars($widget_rating_url, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="button" class="btn-copy-mini" data-copy-target="widgetRatingUrl"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="widget-api-v2-output-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Use this endpoint to fetch raw rating data in your own custom widget or script.</span>
                                </div>
                            </div>

                            <!-- Group 2: Secure Widget -->
                            <div class="widget-api-v2-output-group">
                                <div class="widget-api-v2-output-group-head">
                                    <i class="fas fa-lock"></i>
                                    <h4>Secure Widget</h4>
                                    <p>Signed access for premium placements</p>
                                </div>
                                <div class="widget-api-v2-output-items">
                                    <div class="widget-api-v2-output-item">
                                        <div class="widget-api-v2-output-item-label">
                                            <i class="fas fa-lock"></i>
                                            <span>Widget API URL</span>
                                        </div>
                                        <div class="widget-api-v2-output-item-code">
                                            <textarea id="widgetApiUrl" readonly><?php echo htmlspecialchars($widget_api_url, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <button type="button" class="btn-copy-mini" data-copy-target="widgetApiUrl"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                    </div>
                                    <div class="widget-api-v2-output-item">
                                        <div class="widget-api-v2-output-item-label">
                                            <i class="fas fa-key"></i>
                                            <span>Widget Token</span>
                                        </div>
                                        <div class="widget-api-v2-output-item-code">
                                            <textarea id="widgetTokenValue" readonly><?php echo htmlspecialchars($widget_token, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <button type="button" class="btn-copy-mini" data-copy-target="widgetTokenValue"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="widget-api-v2-output-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>For advanced use with signed access. The token is tied to your allowed domain.</span>
                                </div>
                            </div>

                            <!-- Group 3: Ready-Made Embeds -->
                            <div class="widget-api-v2-output-group">
                                <div class="widget-api-v2-output-group-head">
                                    <i class="fas fa-code"></i>
                                    <h4>Ready-Made Embeds</h4>
                                    <p>Copy & paste into your HTML</p>
                                </div>
                                <div class="widget-api-v2-output-items">
                                    <div class="widget-api-v2-output-item">
                                        <div class="widget-api-v2-output-item-label">
                                            <i class="fas fa-minus"></i>
                                            <span>Single Widget Embed</span>
                                        </div>
                                        <div class="widget-api-v2-output-item-code">
                                            <textarea id="widgetSingleEmbed" readonly><?php echo htmlspecialchars($widget_single_embed, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <button type="button" class="btn-copy-mini" data-copy-target="widgetSingleEmbed"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                    </div>
                                    <div class="widget-api-v2-output-item">
                                        <div class="widget-api-v2-output-item-label">
                                            <i class="fas fa-grip-lines"></i>
                                            <span>Glass Widget Embed</span>
                                        </div>
                                        <div class="widget-api-v2-output-item-code">
                                            <textarea id="widgetGlassEmbed" readonly><?php echo htmlspecialchars($widget_glass_embed, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <button type="button" class="btn-copy-mini" data-copy-target="widgetGlassEmbed"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="widget-api-v2-output-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Just paste these into your website's <strong><body></strong> tag — no coding needed! The widget will render automatically.</span>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ─── VERIFICATION MODAL ─── -->
<div class="verify-modal-overlay" id="verifyModalOverlay" style="display:none;">
    <div class="verify-modal-box">
        <div class="verify-modal-header">
            <i class="fas fa-shield-alt"></i>
            <h3>Verification Required</h3>
        </div>
        <div class="verify-modal-body">
            <p>You can't create a widget until your project is live on CoinRex.</p>
            <p><strong>Please first verify your developer account.</strong></p>
            <div class="verify-modal-timer">
                <span class="verify-modal-timer-label">Redirecting in</span>
                <span class="verify-modal-timer-count" id="verifyModalTimer">10</span>
                <span class="verify-modal-timer-label">seconds</span>
            </div>
        </div>
        <div class="verify-modal-actions">
            <button type="button" class="btn-verify-modal-cancel" id="verifyModalCancel">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<!-- ─── TOAST STACK ─── -->
<div class="widget-toast-stack" id="widgetToastStack" aria-live="polite" aria-atomic="true"></div>

<script>
(function () {
    'use strict';

    /* ── User verification state ── */
    var isVerifiedWidget = <?php echo $is_verified_widget ? 'true' : 'false'; ?>;
    var verifyUrl = '<?php echo BASE_URL; ?>/devhub/apply.php';

    /* ── Elements ── */
    var toastStack = document.getElementById('widgetToastStack');
    var createBtn = document.getElementById('createWidgetBtn');
    var formWrap = document.getElementById('widgetFormWrap');
    var form = document.getElementById('widgetApiForm');
    var generateBtn = document.getElementById('widgetGenerateBtn');
    var projectSelect = document.getElementById('widgetProjectId');
    var domainInput = document.getElementById('widgetDomain');
    var sampleBtns = document.querySelectorAll('.widget-api-v2-sample-select');

    /* ── Toast System ── */
    var toastCount = 0;
    var MAX_TOASTS = 3;

    function showToast(message, type) {
        if (!toastStack || !message) return;

        // Limit visible toasts
        var visible = toastStack.querySelectorAll('.widget-toast.is-visible');
        while (visible.length >= MAX_TOASTS) {
            var oldest = visible[0];
            if (oldest && oldest.parentNode) oldest.parentNode.removeChild(oldest);
            visible = toastStack.querySelectorAll('.widget-toast.is-visible');
        }

        var toast = document.createElement('div');
        toast.className = 'widget-toast widget-toast--' + (type || 'info');

        var iconMap = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        var iconClass = iconMap[type] || 'fa-info-circle';

        toast.innerHTML = '<i class="fas ' + iconClass + '"></i><span>' + message + '</span>';
        toastStack.appendChild(toast);

        window.setTimeout(function () { toast.classList.add('is-visible'); }, 20);
        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 2800);
    }

    /* ── Verification Modal System ── */
    var modalOverlay = document.getElementById('verifyModalOverlay');
    var modalTimer = document.getElementById('verifyModalTimer');
    var modalCancel = document.getElementById('verifyModalCancel');
    var modalCountdownInterval = null;

    function showVerifyModal() {
        if (!modalOverlay || !modalTimer) return;

        // Reset timer
        var countdown = 10;
        modalTimer.textContent = countdown;
        modalOverlay.style.display = 'flex';

        // Clear any existing interval
        if (modalCountdownInterval) {
            window.clearInterval(modalCountdownInterval);
        }

        // Start countdown
        modalCountdownInterval = window.setInterval(function () {
            countdown--;
            modalTimer.textContent = countdown;

            if (countdown <= 0) {
                window.clearInterval(modalCountdownInterval);
                modalCountdownInterval = null;
                modalOverlay.style.display = 'none';
                window.location.href = verifyUrl;
            }
        }, 1000);
    }

    function hideVerifyModal() {
        if (modalCountdownInterval) {
            window.clearInterval(modalCountdownInterval);
            modalCountdownInterval = null;
        }
        if (modalOverlay) {
            modalOverlay.style.display = 'none';
        }
    }

    // Cancel button closes the modal
    if (modalCancel) {
        modalCancel.addEventListener('click', hideVerifyModal);
    }

    // Click outside modal content closes it
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) {
                hideVerifyModal();
            }
        });
    }

    /* ── Show generation message on load ── */
    var generationMessage = <?php echo json_encode($widget_generation_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var generationType = <?php echo json_encode($widget_generation_message_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var generationRequested = <?php echo $widget_generation_requested ? 'true' : 'false'; ?>;

    if (generationMessage && generationRequested) {
        showToast(generationMessage, generationType || 'info');
    }

    /* ── Toggle Form ── */
    if (createBtn && formWrap) {
        // Auto-open if generation was requested
        if (generationRequested) {
            formWrap.classList.add('is-open');
            createBtn.classList.add('is-active');
            createBtn.querySelector('span').textContent = 'Close Form';
        }

        createBtn.addEventListener('click', function () {
            // Show modal with countdown for unverified users
            if (!isVerifiedWidget) {
                showVerifyModal();
                return;
            }
            var isOpen = formWrap.classList.contains('is-open');
            if (isOpen) {
                formWrap.classList.remove('is-open');
                createBtn.classList.remove('is-active');
                createBtn.querySelector('span').textContent = 'Create Widget';
            } else {
                formWrap.classList.add('is-open');
                createBtn.classList.add('is-active');
                createBtn.querySelector('span').textContent = 'Close Form';
                // Scroll to form
                window.setTimeout(function () {
                    formWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        });
    }

    /* ── Sample Select Buttons ── */
    sampleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Show modal with countdown for unverified users
            if (!isVerifiedWidget) {
                showVerifyModal();
                return;
            }
            // Open the form
            if (formWrap && !formWrap.classList.contains('is-open')) {
                formWrap.classList.add('is-open');
                if (createBtn) {
                    createBtn.classList.add('is-active');
                    createBtn.querySelector('span').textContent = 'Close Form';
                }
            }

            // Scroll to form
            if (formWrap) {
                window.setTimeout(function () {
                    formWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 150);
            }

            showToast('Form opened — select your project and domain, then generate!', 'info');
        });
    });

    /* ── Copy Buttons ── */
    var copyButtons = document.querySelectorAll('.btn-copy-mini');
    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-copy-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                showToast('Unable to find the field for copying.', 'error');
                return;
            }

            var value = target.value || target.textContent || '';
            if (!value) {
                showToast('No content to copy yet.', 'warning');
                return;
            }

            var doCopy = function (text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        button.classList.add('is-copied');
                        var original = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-check"></i> Copied';
                        window.setTimeout(function () {
                            button.classList.remove('is-copied');
                            button.innerHTML = original;
                        }, 1400);
                        showToast('Copied to clipboard!', 'success');
                    }).catch(function () {
                        showToast('Clipboard access failed. Please copy manually.', 'error');
                    });
                } else {
                    target.focus();
                    target.select();
                    try {
                        document.execCommand('copy');
                        button.classList.add('is-copied');
                        var original = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-check"></i> Copied';
                        window.setTimeout(function () {
                            button.classList.remove('is-copied');
                            button.innerHTML = original;
                        }, 1400);
                        showToast('Copied to clipboard!', 'success');
                    } catch (e) {
                        showToast('Clipboard access failed. Please copy manually.', 'error');
                    }
                }
            };

            doCopy(value);
        });
    });

    /* ── Auto-scroll to results if generation succeeded ── */
    if (generationRequested && generationType === 'success') {
        var resultsSection = document.querySelector('.widget-api-v2-results');
        if (resultsSection) {
            window.setTimeout(function () {
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        }
    }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
