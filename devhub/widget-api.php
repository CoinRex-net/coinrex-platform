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
$widget_generation_requested = isset($_GET['widget_project_id']) || isset($_GET['widget_domain']);

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
            }
        }

        if ($widget_can_generate) {
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
?>

<div class="dashboard-wrapper">
    <div class="widget-api-section" id="widget-api-generator">
        <div class="widget-api-hero">
            <div class="widget-api-hero-copy">
                <span class="tracker-kicker">Widget API Generator</span>
                <h3>Generate polished API links and embeddable rating widgets</h3>
                <p>Select an approved project, set the allowed domain, preview the widget styles, and copy production-ready assets without changing your existing workflow.</p>
                <div class="widget-api-hero-pills">
                    <span class="widget-api-pill"><i class="fas fa-shield-alt"></i> Signed domain token</span>
                    <span class="widget-api-pill"><i class="fas fa-th-large"></i> Live preview cards</span>
                    <span class="widget-api-pill"><i class="fas fa-copy"></i> Faster developer handoff</span>
                </div>
            </div>
            <div class="widget-api-hero-side">
                <div class="widget-api-hero-metric">
                    <span class="widget-api-hero-label">Projects Ready</span>
                    <strong><?php echo number_format(count($widget_projects)); ?></strong>
                    <small>Projects with usable widget slugs</small>
                </div>
                <div class="widget-api-hero-metric">
                    <span class="widget-api-hero-label">Preview Modes</span>
                    <strong>2</strong>
                    <small>Single and Glass layouts</small>
                </div>
            </div>
        </div>

        <?php if (empty($widget_projects)): ?>
            <div class="tracker-empty">
                <i class="fas fa-code"></i>
                <h4>No projects available for widget generation</h4>
                <p>Submit and approve a project first. Once available in your DevHub account, you can generate API links and widget embeds here.</p>
            </div>
        <?php else: ?>
            <div class="widget-api-workspace">
                <div class="widget-api-builder-card">
                    <div class="widget-api-section-head">
                        <div>
                            <span class="widget-api-section-kicker">Phase 1 · Setup</span>
                            <h4>Configure widget generation</h4>
                            <p>Use your project slug and allowlist domain to generate secure API and embed assets.</p>
                        </div>
                    </div>

                    <form method="GET" action="" class="widget-api-form" id="widgetApiForm">
                        <div class="widget-api-grid">
                            <div class="widget-api-field">
                                <label for="widgetProjectId">Choose Your Project</label>
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
                                <small>Only approved projects can publish public widget assets.</small>
                            </div>

                            <div class="widget-api-field">
                                <label for="widgetDomain">Allowed Embed Domain</label>
                                <input type="text" id="widgetDomain" name="widget_domain" value="<?php echo htmlspecialchars($widget_domain_value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="example.com or localhost">
                                <small>Use your real website domain in production. For local testing, keep <strong>localhost</strong>.</small>
                            </div>
                        </div>

                        <div class="widget-api-actions">
                            <button type="submit" class="btn-primary" id="widgetGenerateBtn">
                                <i class="fas fa-wand-magic-sparkles"></i> Generate API &amp; Widget Code
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="widget-api-side-panel">
                    <div class="widget-api-side-card">
                        <span class="widget-api-section-kicker">Phase 2 · Preview</span>
                        <h4>What you’re building</h4>
                        <p>Generate two visual widget variants for your project and deliver them to your product or marketing pages.</p>
                        <ul class="widget-api-checklist">
                            <li><i class="fas fa-check-circle"></i> Lightweight public rating endpoint</li>
                            <li><i class="fas fa-check-circle"></i> Secure widget endpoint with token</li>
                            <li><i class="fas fa-check-circle"></i> Ready-made embed snippets</li>
                        </ul>
                    </div>
                    <div class="widget-api-side-card widget-api-side-card--tip">
                        <span class="widget-api-section-kicker">Best Practice</span>
                        <p>Use a production domain like <strong>app.example.com</strong> for live deployment and keep <strong>localhost</strong> only for local preview or staging tests.</p>
                    </div>
                </aside>
            </div>

            <?php if ($widget_selected_project): ?>
                <div class="widget-api-summary">
                    <div class="widget-api-summary-card">
                        <span class="widget-api-summary-label">Selected Project</span>
                        <strong><?php echo htmlspecialchars((string) ($widget_selected_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="widget-api-summary-meta">Slug: <?php echo htmlspecialchars($widget_selected_slug, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="widget-api-summary-card">
                        <span class="widget-api-summary-label">Status</span>
                        <strong><?php echo htmlspecialchars(ucfirst($widget_selected_status), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="widget-api-summary-meta">Only approved projects can generate public embed code.</span>
                    </div>
                    <div class="widget-api-summary-card">
                        <span class="widget-api-summary-label">Allowed Domain</span>
                        <strong><?php echo htmlspecialchars($widget_domain_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="widget-api-summary-meta">This domain is baked into the widget token.</span>
                    </div>
                </div>

                <?php if ($widget_generation_message !== ''): ?>
                    <div class="widget-inline-notice widget-inline-notice--<?php echo htmlspecialchars($widget_generation_message_type, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas <?php echo $widget_generation_message_type === 'success' ? 'fa-check-circle' : ($widget_generation_message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?>"></i>
                        <span><?php echo htmlspecialchars($widget_generation_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$widget_can_generate): ?>
                    <div class="feature-progress feature-progress-blocked">
                        <i class="fas fa-shield-alt"></i>
                        <span>
                            <?php if ($widget_requested_domain === ''): ?>
                                Enter a valid domain first, such as <strong>localhost</strong> for testing or your real website domain for production.
                            <?php else: ?>
                                This project is currently <strong><?php echo htmlspecialchars($widget_selected_status, ENT_QUOTES, 'UTF-8'); ?></strong>. Public API/embed generation is enabled only for approved projects.
                            <?php endif; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="widget-preview-grid">
                        <div class="widget-preview-card">
                            <div class="widget-preview-head">
                                <div>
                                    <span class="widget-api-section-kicker">Single Widget</span>
                                    <h4>Compact trust signal</h4>
                                </div>
                                <span class="widget-preview-badge">Preview</span>
                            </div>
                            <div class="widget-preview-stage widget-preview-stage--single">
                                <div class="widget-preview-real widget-preview-real--single">
                                    <?php echo $widget_single_preview_html; ?>
                                    <span class="widget-preview-meta">Compact original CoinRex rating row for cards, lists, and side panels.</span>
                                </div>
                            </div>
                        </div>

                        <div class="widget-preview-card widget-preview-card--glass-full">
                            <div class="widget-preview-head">
                                <div>
                                    <span class="widget-api-section-kicker">Glass Widget</span>
                                    <h4>Premium featured look</h4>
                                </div>
                                <span class="widget-preview-badge">Preview</span>
                            </div>
                            <div class="widget-preview-stage widget-preview-stage--glass">
                                <div class="widget-preview-real widget-preview-real--glass">
                                    <?php echo $widget_glass_preview_html; ?>
                                    <div class="widget-preview-project-meta">
                                        <strong><?php echo htmlspecialchars((string) ($widget_selected_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo number_format($widget_preview_reviews); ?> total reviews on record</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="widget-api-output-grid">
                        <div class="widget-code-card widget-code-card--priority">
                            <div class="widget-code-head">
                                <div>
                                    <h4><i class="fas fa-link"></i> Rating API URL</h4>
                                    <p>Public endpoint for plain rating data.</p>
                                </div>
                                <button type="button" class="btn-secondary widget-copy-btn" data-copy-target="widgetRatingUrl">Copy</button>
                            </div>
                            <input id="widgetRatingUrl" class="widget-code-input" type="text" readonly value="<?php echo htmlspecialchars($widget_rating_url, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="widget-code-card widget-code-card--priority">
                            <div class="widget-code-head">
                                <div>
                                    <h4><i class="fas fa-lock"></i> Widget API URL</h4>
                                    <p>Protected endpoint signed for the selected domain.</p>
                                </div>
                                <button type="button" class="btn-secondary widget-copy-btn" data-copy-target="widgetApiUrl">Copy</button>
                            </div>
                            <textarea id="widgetApiUrl" class="widget-code-area" readonly><?php echo htmlspecialchars($widget_api_url, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="widget-code-card">
                            <div class="widget-code-head">
                                <div>
                                    <h4><i class="fas fa-key"></i> Signed Widget Token</h4>
                                    <p>Use this only where a secure widget response is required.</p>
                                </div>
                                <button type="button" class="btn-secondary widget-copy-btn" data-copy-target="widgetTokenValue">Copy</button>
                            </div>
                            <textarea id="widgetTokenValue" class="widget-code-area" readonly><?php echo htmlspecialchars($widget_token, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="widget-code-card widget-code-card--wide">
                            <div class="widget-code-head">
                                <div>
                                    <h4><i class="fas fa-minus"></i> Single Widget Embed</h4>
                                    <p>Use this compact version inside feature rows, sidebars, or review summaries.</p>
                                </div>
                                <button type="button" class="btn-secondary widget-copy-btn" data-copy-target="widgetSingleEmbed">Copy</button>
                            </div>
                            <textarea id="widgetSingleEmbed" class="widget-code-area" readonly><?php echo htmlspecialchars($widget_single_embed, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="widget-code-card widget-code-card--wide">
                            <div class="widget-code-head">
                                <div>
                                    <h4><i class="fas fa-grip-lines"></i> Glass Widget Embed</h4>
                                    <p>Use this for elevated, premium widget placement with signed access.</p>
                                </div>
                                <button type="button" class="btn-secondary widget-copy-btn" data-copy-target="widgetGlassEmbed">Copy</button>
                            </div>
                            <textarea id="widgetGlassEmbed" class="widget-code-area" readonly><?php echo htmlspecialchars($widget_glass_embed, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="widget-toast-stack" id="widgetToastStack" aria-live="polite" aria-atomic="true"></div>

<script>
(function () {
    var toastStack = document.getElementById('widgetToastStack');
    var generationMessage = <?php echo json_encode($widget_generation_message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var generationType = <?php echo json_encode($widget_generation_message_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function showToast(message, type) {
        if (!toastStack || !message) {
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'widget-toast widget-toast--' + (type || 'info');

        var iconClass = 'fa-info-circle';
        if (type === 'success') {
            iconClass = 'fa-check-circle';
        } else if (type === 'error') {
            iconClass = 'fa-times-circle';
        } else if (type === 'warning') {
            iconClass = 'fa-exclamation-triangle';
        }

        toast.innerHTML = '<i class="fas ' + iconClass + '"></i><span>' + message + '</span>';
        toastStack.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('is-visible');
        }, 20);

        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, 2800);
    }

    if (generationMessage && <?php echo $widget_generation_requested ? 'true' : 'false'; ?>) {
        showToast(generationMessage, generationType || 'info');
    }

    var copyButtons = document.querySelectorAll('.widget-copy-btn');
    if (!copyButtons.length) {
        return;
    }

    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-copy-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                showToast('Unable to find the selected field for copying.', 'error');
                return;
            }

            var value = target.value || target.textContent || '';
            if (!value) {
                showToast('There is no generated content to copy yet.', 'warning');
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    var original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () { button.textContent = original; }, 1400);
                    showToast('Copied to clipboard successfully.', 'success');
                }).catch(function () {
                    showToast('Clipboard access failed. Please copy manually.', 'error');
                });
                return;
            }

            target.focus();
            target.select();
            try {
                document.execCommand('copy');
                showToast('Copied to clipboard successfully.', 'success');
            } catch (e) {
                showToast('Clipboard access failed. Please copy manually.', 'error');
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>