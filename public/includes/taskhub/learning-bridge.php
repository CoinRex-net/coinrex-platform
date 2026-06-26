<?php
/**
 * TaskHub Learning Bridge Page
 * 
 * This page opens in a new tab when the user clicks "Open & Validate" on a learning task.
 * It displays the learning material with a mandatory reading timer (30-60 seconds).
 * After the timer completes, the user can click "I've Read It" to validate.
 * Uses postMessage to communicate back to the parent taskhub.php tab.
 * 
 * URL Parameters:
 *   th_session    - Learning session token (required)
 *   th_task_key   - Task key (required)
 *   th_url        - The actual learning URL to display/redirect to
 *   th_seconds    - Required reading seconds (default 45)
 *   th_title      - Learning material title
 */

// Load config for BASE_URL and other constants
// File is at public/includes/taskhub/learning-bridge.php
// Config is at includes/config.php (3 levels up from taskhub/)
require_once __DIR__ . '/../../../includes/config.php';


// Start output buffering
ob_start();


// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Get parameters
$session_token = trim((string) ($_GET['th_session'] ?? ''));
$task_key = trim((string) ($_GET['th_task_key'] ?? ''));
$learning_url = trim((string) ($_GET['th_url'] ?? ''));
$required_seconds = max(15, min(120, (int) ($_GET['th_seconds'] ?? 45)));
$learning_title = trim((string) ($_GET['th_title'] ?? 'Learning Material'));

// Validate required params
if ($session_token === '' || $task_key === '') {
    http_response_code(400);
    echo '<h1>Invalid learning session</h1>';
    echo '<p>Missing required parameters. Please close this tab and try again from LearnHub.</p>';
    exit;
}

// If no learning URL provided, show a message
if ($learning_url === '') {
    $learning_url = 'about:blank';
} else {
    $learning_url = taskHubNormalizeLearningUrlForCurrentHost($learning_url);
}

// Determine if the learning URL is internal (same domain) or external
$base_host = parse_url(BASE_URL ?? 'http://localhost', PHP_URL_HOST);
$learning_host = parse_url($learning_url, PHP_URL_HOST);
$is_internal = ($learning_host === null || $learning_host === '' || $learning_host === $base_host);

if ($is_internal && $learning_url !== 'about:blank') {
    $fragment = '';
    $fragment_pos = strpos($learning_url, '#');
    if ($fragment_pos !== false) {
        $fragment = substr($learning_url, $fragment_pos);
        $learning_url = substr($learning_url, 0, $fragment_pos);
    }

    $separator = strpos($learning_url, '?') === false ? '?' : '&';
    if (!preg_match('/(?:^|[?&])th_embed=/', $learning_url)) {
        $learning_url .= $separator . 'th_embed=1';
    }

    $learning_url .= $fragment;
}

// API base URL for heartbeats and verification
$api_base = rtrim((defined('BASE_URL') ? BASE_URL : 'http://localhost/coinrex/public'), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($learning_title, ENT_QUOTES, 'UTF-8'); ?> — CoinRex Learning</title>
    <meta name="base-url" content="<?php echo htmlspecialchars($api_base, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        /* ============================================================
           RESET & BASE
           ============================================================ */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0a0a0f;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           TOP BAR — Compact strip combining title + timer
           ============================================================ */
        .lb-top-bar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .lb-top-bar-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            gap: 12px;
        }

        .lb-top-bar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .lb-top-bar-title {
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lb-top-bar-title-icon {
            font-size: 14px;
            flex-shrink: 0;
        }

        .lb-top-bar-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .lb-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .lb-status-dot.is-reading { background: #3b82f6; animation: pulse 1.5s infinite; }
        .lb-status-dot.is-validated { background: #22c55e; }
        .lb-status-dot.is-paused { background: #f59e0b; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .lb-top-bar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .lb-timer-count {
            font-size: 18px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #f59e0b;
            transition: color 0.3s ease;
            min-width: 50px;
            text-align: right;
        }

        .lb-timer-count.is-done {
            color: #22c55e;
        }

        .lb-timer-bar-track {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }

        .lb-timer-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #22c55e);
            transition: width 0.5s ease;
            width: 0%;
        }

        .lb-timer-bar-fill.is-done {
            background: linear-gradient(90deg, #22c55e, #16a34a);
        }

        /* ============================================================
           CONTENT AREA
           ============================================================ */
        .lb-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            min-height: 400px;
        }

        .lb-content-iframe-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            min-height: 300px;
        }

        .lb-content-iframe {
            flex: 1;
            width: 100%;
            border: none;
            background: #0f172a;
            min-height: 300px;
        }

        .lb-content-iframe-fallback {
            display: none;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            text-align: center;
            background: #0f172a;
            min-height: 300px;
        }

        .lb-content-iframe-fallback.is-visible {
            display: flex;
        }

        .lb-content-iframe-fallback-icon {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .lb-content-iframe-fallback p {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 16px;
            max-width: 320px;
            line-height: 1.5;
        }

        .lb-content-iframe-fallback-link {
            color: #60a5fa;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: all 0.2s ease;
        }

        .lb-content-iframe-fallback-link:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .lb-content-fallback {
            text-align: center;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.95);
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .lb-content-fallback a {
            color: #60a5fa;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: all 0.2s ease;
        }

        .lb-content-fallback a:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .lb-content-placeholder {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            text-align: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .lb-content-placeholder-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .lb-content-placeholder h2 {
            font-size: 20px;
            color: #e2e8f0;
            margin-bottom: 8px;
        }

        .lb-content-placeholder p {
            font-size: 14px;
            color: #94a3b8;
            max-width: 400px;
            line-height: 1.6;
        }

        /* ============================================================
           FOOTER / VALIDATE BUTTON
           ============================================================ */
        .lb-footer {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 16px 24px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .lb-footer-info {
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }

        .lb-footer-info strong {
            color: #94a3b8;
        }

        .lb-validate-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #64748b;
            color: #94a3b8;
            opacity: 0.5;
            pointer-events: none;
            white-space: nowrap;
        }

        .lb-validate-btn.is-ready {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            opacity: 1;
            pointer-events: auto;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        .lb-validate-btn.is-ready:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        .lb-validate-btn.is-ready:active {
            transform: translateY(0);
        }

        .lb-validate-btn.is-loading {
            background: #3b82f6;
            color: #ffffff;
            opacity: 1;
            pointer-events: none;
        }

        .lb-validate-btn.is-validated {
            background: #22c55e;
            color: #ffffff;
            opacity: 1;
            pointer-events: none;
            cursor: default;
        }

        .lb-validate-btn .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .lb-validate-btn.is-loading .spinner {
            display: inline-block;
        }

        .lb-validate-btn.is-loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ============================================================
           VALIDATED STATE OVERLAY
           ============================================================ */
        .lb-validated-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .lb-validated-overlay.is-visible {
            display: flex;
        }

        .lb-validated-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .lb-validated-card .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .lb-validated-card h2 {
            font-size: 24px;
            color: #22c55e;
            margin-bottom: 8px;
        }

        .lb-validated-card p {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .lb-validated-card .close-btn {
            padding: 10px 24px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background: transparent;
            color: #e2e8f0;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .lb-validated-card .close-btn:hover {
            background: rgba(255,255,255,0.05);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 640px) {
            .lb-top-bar-main {
                padding: 8px 12px;
            }
            .lb-top-bar-title {
                font-size: 12px;
            }
            .lb-timer-count {
                font-size: 16px;
                min-width: 40px;
            }
            .lb-footer {
                flex-direction: column;
                text-align: center;
            }
            .lb-content {
                min-height: 0;
                background: #0f172a;
            }
            .lb-content-iframe-wrapper {
                min-height: 260px;
            }
            .lb-content-iframe {
                display: block;
                min-height: 260px;
            }
            .lb-content-iframe-fallback {
                display: none;
                min-height: 260px;
                background: linear-gradient(135deg, #0f172a 0%, #172033 100%);
            }
            .lb-content-iframe-fallback p {
                font-size: 13px;
            }
            .lb-content-iframe-fallback-link {
                width: 100%;
                max-width: 260px;
                justify-content: center;
            }
            .lb-content-fallback {
                display: block;
            }
            .lb-validate-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- ============================================================
         TOP BAR — Compact strip with title + countdown + progress bar
         ============================================================ -->
    <div class="lb-top-bar">
        <div class="lb-top-bar-main">
            <div class="lb-top-bar-left">
                <span class="lb-top-bar-title-icon">📖</span>
                <span class="lb-top-bar-title"><?php echo htmlspecialchars($learning_title, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="lb-top-bar-status">
                    <span class="lb-status-dot is-reading" id="statusDot"></span>
                    <span id="statusText">Reading...</span>
                </span>
            </div>
            <div class="lb-top-bar-right">
                <span class="lb-timer-count" id="timerCount"><?php echo $required_seconds; ?>s</span>
            </div>
        </div>
        <div class="lb-timer-bar-track">
            <div class="lb-timer-bar-fill" id="timerBarFill"></div>
        </div>
    </div>

    <!-- ============================================================
         CONTENT AREA
         ============================================================ -->
    <section class="lb-content">
        <?php if ($learning_url !== 'about:blank'): ?>
            <div class="lb-content-iframe-wrapper">
                <iframe src="<?php echo htmlspecialchars($learning_url, ENT_QUOTES, 'UTF-8'); ?>" 
                        class="lb-content-iframe" 
                        id="contentFrame"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"
                        loading="lazy"></iframe>
                <div class="lb-content-iframe-fallback" id="iframeFallback">
                    <div class="lb-content-iframe-fallback-icon">🔗</div>
                    <p>This content couldn't be displayed in the frame.</p>
                    <a href="<?php echo htmlspecialchars($learning_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="lb-content-iframe-fallback-link">↗ Open in new tab to read</a>
                </div>
            </div>
            <div class="lb-content-fallback">
                <a href="<?php echo htmlspecialchars($learning_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">↗ Open in new tab</a>
            </div>
        <?php else: ?>
            <div class="lb-content-placeholder">
                <div class="lb-content-placeholder-icon">📖</div>
                <h2>Learning Material</h2>
                <p>Please read the material carefully. Once you've finished reading, click the "I've Read It" button below to proceed.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ============================================================
         FOOTER / VALIDATE BUTTON
         ============================================================ -->
    <footer class="lb-footer">
        <div class="lb-footer-info">
            <strong>Session:</strong> <?php echo htmlspecialchars(substr($session_token, 0, 12), ENT_QUOTES, 'UTF-8'); ?>...<br>
            <span id="footerTimerInfo">Please read the material for <strong><?php echo $required_seconds; ?> seconds</strong></span>
        </div>
        <button class="lb-validate-btn" id="validateBtn" disabled>
            <span class="spinner"></span>
            <span class="btn-text">✅ I've Read It — Complete</span>
        </button>
    </footer>

    <!-- ============================================================
         VALIDATED OVERLAY
         ============================================================ -->
    <div class="lb-validated-overlay" id="validatedOverlay">
        <div class="lb-validated-card">
            <div class="icon">✅</div>
            <h2>Learning Verified!</h2>
            <p>Your reading has been confirmed. You can now close this tab and return to LearnHub to complete the quiz.</p>
            <button class="close-btn" onclick="window.close()">Close This Tab</button>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        // ============================================================
        // CONFIGURATION
        // ============================================================
        const CONFIG = {
            sessionToken: '<?php echo htmlspecialchars($session_token, ENT_QUOTES, 'UTF-8'); ?>',
            taskKey: '<?php echo htmlspecialchars($task_key, ENT_QUOTES, 'UTF-8'); ?>',
            requiredSeconds: <?php echo $required_seconds; ?>,
            apiBase: document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '',
            heartbeatInterval: 3000, // 3 seconds
            verifyEndpoint: '/api/learning/verify_session.php',
            heartbeatEndpoint: '/api/learning/heartbeat_session.php',
        };

        // ============================================================
        // DOM REFS
        // ============================================================
        const timerCount = document.getElementById('timerCount');
        const timerBarFill = document.getElementById('timerBarFill');
        const validateBtn = document.getElementById('validateBtn');
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const footerTimerInfo = document.getElementById('footerTimerInfo');
        const validatedOverlay = document.getElementById('validatedOverlay');
        const contentFrame = document.getElementById('contentFrame');
        const iframeFallback = document.getElementById('iframeFallback');

        // ============================================================
        // STATE
        // ============================================================
        let elapsedSeconds = 0;
        let isComplete = false;
        let isVerified = false;
        let heartbeatIntervalId = null;
        let timerIntervalId = null;
        let isTabVisible = true;
        let isTabFocused = true;
        let scrollDepth = 0;

        function isLocalLearningHost(hostname) {
            const host = String(hostname || '').toLowerCase().replace(/^\[|\]$/g, '');
            return host === 'localhost'
                || host === '127.0.0.1'
                || host === '::1'
                || /^10\./.test(host)
                || /^192\.168\./.test(host)
                || /^172\.(1[6-9]|2\d|3[0-1])\./.test(host);
        }

        function normalizeLearningFrameUrl() {
            if (!contentFrame) return;

            try {
                const originalUrl = new URL(contentFrame.getAttribute('src') || '', window.location.href);
                const shouldRehost = isLocalLearningHost(originalUrl.hostname)
                    || originalUrl.protocol !== window.location.protocol;

                if (!shouldRehost) return;

                originalUrl.protocol = window.location.protocol;
                originalUrl.host = window.location.host;
                originalUrl.searchParams.set('th_embed', '1');
                const normalizedUrl = originalUrl.toString();
                contentFrame.src = normalizedUrl;

                document.querySelectorAll('.lb-content-iframe-fallback-link, .lb-content-fallback a').forEach(function(link) {
                    link.href = normalizedUrl;
                });
            } catch (e) {
                // Keep the server-rendered URL if the browser cannot parse it.
            }
        }

        // ============================================================
        // HEARTBEAT — Send regular updates to the server
        // ============================================================
        async function sendHeartbeat() {
            if (isVerified) return;

            try {
                const response = await fetch(CONFIG.apiBase + CONFIG.heartbeatEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({
                        session_token: CONFIG.sessionToken,
                        task_key: CONFIG.taskKey,
                        active_seconds: elapsedSeconds,
                        is_visible: isTabVisible ? '1' : '0',
                        is_focused: isTabFocused ? '1' : '0',
                        scroll_depth: scrollDepth,
                    }),
                });
                const data = await response.json();
                if (data.success && data.session) {
                    // Update elapsed from server if it's more accurate
                    if (data.session.active_seconds > elapsedSeconds) {
                        elapsedSeconds = data.session.active_seconds;
                    }
                }
            } catch (e) {
                // Silently fail — heartbeats are best-effort
                console.warn('Heartbeat failed:', e);
            }
        }

        // ============================================================
        // VERIFY — Call server to mark learning as complete
        // ============================================================
        async function verifyLearning() {
            if (isVerified) return;

            validateBtn.className = 'lb-validate-btn is-loading';
            statusText.textContent = 'Verifying...';
            statusDot.className = 'lb-status-dot is-reading';

            try {
                const response = await fetch(CONFIG.apiBase + CONFIG.verifyEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({
                        session_token: CONFIG.sessionToken,
                        task_key: CONFIG.taskKey,
                        elapsed_seconds: elapsedSeconds,
                    }),
                });
                const data = await response.json();

                if (data.success) {
                    isVerified = true;
                    validateBtn.className = 'lb-validate-btn is-validated';
                    statusText.textContent = '✅ Verified!';
                    statusDot.className = 'lb-status-dot is-validated';
                    footerTimerInfo.innerHTML = '✅ Learning verified! You can close this tab.';

                    // Show the validated overlay
                    validatedOverlay.classList.add('is-visible');

                    // Stop heartbeats
                    if (heartbeatIntervalId) {
                        clearInterval(heartbeatIntervalId);
                        heartbeatIntervalId = null;
                    }

                    // Notify the opener (taskhub.php) via postMessage
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.opener.postMessage({
                                type: 'th-learning-verified',
                                sessionToken: CONFIG.sessionToken,
                                taskKey: CONFIG.taskKey,
                            }, '*');
                        }
                    } catch (e) {
                        // Cross-origin opener — silently fail
                    }

                    // Also try to notify via a redirect-based beacon
                    try {
                        const img = new Image();
                        img.src = CONFIG.apiBase + '/api/learning/verify_session.php?beacon=1&session_token=' 
                            + encodeURIComponent(CONFIG.sessionToken) 
                            + '&task_key=' + encodeURIComponent(CONFIG.taskKey);
                    } catch (e) {
                        // Silently fail
                    }
                } else {
                    throw new Error(data.message || 'Verification failed');
                }
            } catch (e) {
                validateBtn.className = 'lb-validate-btn is-ready';
                statusText.textContent = '⚠ Verification failed — try again';
                statusDot.className = 'lb-status-dot is-paused';
                footerTimerInfo.innerHTML = '⚠ ' + (e.message || 'Could not verify. Please try again.');
            }
        }

        // ============================================================
        // TIMER
        // ============================================================
        function updateTimer() {
            elapsedSeconds++;
            
            const remaining = Math.max(0, CONFIG.requiredSeconds - elapsedSeconds);
            const progress = Math.min(100, (elapsedSeconds / CONFIG.requiredSeconds) * 100);

            // Update display
            timerCount.textContent = remaining > 0 ? remaining + 's' : '✅ Done!';
            timerCount.className = 'lb-timer-count' + (remaining <= 0 ? ' is-done' : '');
            timerBarFill.style.width = progress + '%';
            timerBarFill.className = 'lb-timer-bar-fill' + (remaining <= 0 ? ' is-done' : '');

            // Update footer info
            if (remaining > 0) {
                footerTimerInfo.innerHTML = 'Please read for <strong>' + remaining + ' more seconds</strong>';
            } else {
                footerTimerInfo.innerHTML = '✅ Reading complete! Click the button to verify.';
            }

            // Enable button when timer is done
            if (remaining <= 0 && !isComplete) {
                isComplete = true;
                validateBtn.disabled = false;
                validateBtn.className = 'lb-validate-btn is-ready';
                footerTimerInfo.innerHTML = '✅ You have read for the required time. Click "I\'ve Read It" to verify.';
                statusText.textContent = 'Ready to verify';
                statusDot.className = 'lb-status-dot is-paused';
            }
        }

        // ============================================================
        // SCROLL TRACKING
        // ============================================================
        function updateScrollDepth() {
            const docEl = document.documentElement;
            const scrollTop = window.pageYOffset || docEl.scrollTop || 0;
            const scrollHeight = Math.max(
                document.body.scrollHeight,
                docEl.scrollHeight,
                document.body.offsetHeight,
                docEl.offsetHeight,
                document.body.clientHeight,
                docEl.clientHeight
            );
            const windowHeight = window.innerHeight || docEl.clientHeight || 0;
            const maxScroll = scrollHeight - windowHeight;
            if (maxScroll > 0) {
                scrollDepth = Math.min(100, Math.round((scrollTop / maxScroll) * 100));
            } else {
                scrollDepth = 100;
            }
        }

        // ============================================================
        // VISIBILITY & FOCUS TRACKING
        // ============================================================
        document.addEventListener('visibilitychange', function() {
            isTabVisible = !document.hidden;
            if (!isTabVisible) {
                statusDot.className = 'lb-status-dot is-paused';
                statusText.textContent = isComplete ? 'Ready to verify' : 'Tab hidden — timer paused';
            } else {
                statusDot.className = 'lb-status-dot is-reading';
                statusText.textContent = isComplete ? 'Ready to verify' : 'Reading...';
            }
        });

        window.addEventListener('focus', function() {
            isTabFocused = true;
        });

        window.addEventListener('blur', function() {
            isTabFocused = false;
        });

        window.addEventListener('scroll', updateScrollDepth, { passive: true });

        // ============================================================
        // BUTTON CLICK HANDLER
        // ============================================================
        validateBtn.addEventListener('click', function() {
            if (!isComplete || isVerified) return;
            verifyLearning();
        });

        // ============================================================
        // KEYBOARD SHORTCUT: Enter to verify when ready
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && isComplete && !isVerified) {
                verifyLearning();
            }
        });

        // ============================================================
        // IFRAME ERROR DETECTION
        // ============================================================
        function detectIframeError() {
            return;
            if (!contentFrame || !iframeFallback) return;

            // If the iframe loaded about:blank or failed, show fallback
            try {
                const iframeDoc = contentFrame.contentDocument || contentFrame.contentWindow?.document;
                if (iframeDoc) {
                    // Check if the iframe body is essentially empty/blank
                    const body = iframeDoc.body;
                    if (body) {
                        const text = (body.textContent || '').trim();
                        const hasImages = body.querySelectorAll('img').length > 0;
                        const hasLinks = body.querySelectorAll('a').length > 0;
                        const hasHeadings = body.querySelectorAll('h1, h2, h3, h4, h5, h6').length > 0;
                        
                        // If body is essentially blank (no meaningful content)
                        if (text.length < 20 && !hasImages && !hasLinks && !hasHeadings) {
                            contentFrame.style.display = 'none';
                            iframeFallback.classList.add('is-visible');
                            return;
                        }
                    }
                }
            } catch (e) {
                // Cross-origin error — iframe loaded external content that blocks access
                // This is expected for external URLs (YouTube, Medium, etc.)
                // Show the fallback with "Open in new tab" option
                return;
            }
        }

        // ============================================================
        // INIT
        // ============================================================
        function init() {
            // Start the timer
            timerIntervalId = setInterval(updateTimer, 1000);
            
            // Start heartbeats
            heartbeatIntervalId = setInterval(sendHeartbeat, CONFIG.heartbeatInterval);
            
            // Send initial heartbeat
            sendHeartbeat();

            // Update scroll depth on load
            setTimeout(updateScrollDepth, 500);

            if (contentFrame && iframeFallback) {
                contentFrame.addEventListener('load', function() {
                    setTimeout(detectIframeError, 500);
                });

                contentFrame.addEventListener('error', function() {
                    console.warn('Learning iframe reported a load error.');
                });
            }

            normalizeLearningFrameUrl();

            // If the opener sends us a message, handle it
            window.addEventListener('message', function(event) {
                // We only handle messages from our own origin
                if (event.data && event.data.type === 'th-ping') {
                    event.source.postMessage({ type: 'th-pong', sessionToken: CONFIG.sessionToken }, '*');
                }
            });

            // Auto-verify if the timer is 0 (instant validation mode)
            if (CONFIG.requiredSeconds <= 0) {
                isComplete = true;
                validateBtn.disabled = false;
                validateBtn.className = 'lb-validate-btn is-ready';
                timerCount.textContent = '✅';
                timerBarFill.style.width = '100%';
                timerBarFill.className = 'lb-timer-bar-fill is-done';
                footerTimerInfo.innerHTML = 'Click "I\'ve Read It" to verify.';
            }
        }

        // Start when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
