<?php
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$success = '';
$error = '';

$form = [
    'name' => '',
    'email' => '',
    'audience' => 'user',
    'subject' => '',
    'message' => '',
];

$audiences = [
    'user' => ['label' => 'User', 'icon' => 'fa-user'],
    'developer' => ['label' => 'Developer', 'icon' => 'fa-code'],
    'project_owner' => ['label' => 'Project Owner', 'icon' => 'fa-diagram-project'],
    'promoter' => ['label' => 'Promoter', 'icon' => 'fa-bullhorn'],
];

$audienceHints = [
    'user' => 'Report issues, ask questions, or share feedback.',
    'developer' => 'API access, DevHub integration, or technical inquiries.',
    'project_owner' => 'Project listing, verification, or partnerships.',
    'promoter' => 'Marketing collaborations or affiliate programs.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $form['audience'] = trim((string) ($_POST['audience'] ?? 'user'));
    $form['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $form['message'] = trim((string) ($_POST['message'] ?? ''));

    if ($form['name'] === '' || $form['email'] === '' || $form['subject'] === '' || $form['message'] === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!array_key_exists($form['audience'], $audiences)) {
        $error = 'Invalid contact type selected.';
    } elseif (mb_strlen($form['subject']) < 5) {
        $error = 'Subject must be at least 5 characters.';
    } elseif (mb_strlen($form['message']) < 20) {
        $error = 'Message must be at least 20 characters.';
    } else {
        $title = '[Contact] ' . ucfirst(str_replace('_', ' ', $form['audience'])) . ' - ' . $form['subject'];
        $body = "Name: {$form['name']}\n"
            . "Email: {$form['email']}\n"
            . "Audience: {$form['audience']}\n"
            . "IP: " . (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . "\n"
            . "User Agent: " . (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\n"
            . "Message:\n{$form['message']}";

        $stmt = $db->prepare("INSERT INTO messages (title, body, status, recipient_admin_id, created_at) VALUES (?, ?, 'unread', NULL, NOW())");
        $stmt->execute([$title, $body]);

        $success = 'Your message has been sent successfully. Our team will get back to you soon.';
        $form = ['name' => '', 'email' => '', 'audience' => 'user', 'subject' => '', 'message' => ''];
    }
}
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/contact.css">

<main class="contact-page">
    <div class="contact-shell">

        <!-- Hero -->
        <section class="contact-hero">
            <div class="contact-hero-grid">
                <div class="contact-hero-copy">
                    <span class="contact-kicker"><i class="fas fa-headset"></i> Get in touch</span>
                    <h1>Talk to the right team, fast.</h1>
                    <p>Direct support for users, developers, project owners, and promoters.</p>
                    <div class="contact-pills">
                        <span class="contact-pill"><i class="fas fa-bolt"></i> Priority routing</span>
                        <span class="contact-pill"><i class="fas fa-shield-check"></i> Verified channels</span>
                        <span class="contact-pill"><i class="fas fa-clock"></i> 24h response</span>
                    </div>
                </div>
                <aside>
                    <div class="contact-stats">
                        <div class="contact-stat">
                            <span class="contact-stat-value">24h</span>
                            <span class="contact-stat-label">Response window</span>
                        </div>
                        <div class="contact-stat">
                            <span class="contact-stat-value">4</span>
                            <span class="contact-stat-label">Support routes</span>
                        </div>
                        <div class="contact-stat">
                            <span class="contact-stat-value">Mon–Fri</span>
                            <span class="contact-stat-label">Coverage</span>
                        </div>
                        <div class="contact-stat">
                            <span class="contact-stat-value">CSRF</span>
                            <span class="contact-stat-label">Protected</span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Form + Sidebar -->
        <section class="contact-grid">
            <div class="contact-panel ct-reveal">
                <div class="contact-panel-header">
                    <div>
                        <h2>Send a message</h2>
                        <p>We'll route it to the right team.</p>
                    </div>
                    <span class="contact-badge"><i class="fas fa-lock"></i> Secure</span>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="contact-alert contact-alert-success">
                        <i class="fas fa-circle-check"></i>
                        <div>
                            <strong>Message delivered</strong>
                            <p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="contact-alert contact-alert-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <div>
                            <strong>Please review</strong>
                            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="name">Name <span class="contact-required">*</span></label>
                            <input id="name" type="text" name="name" value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your name" required autocomplete="name">
                        </div>
                        <div class="contact-field">
                            <label for="email">Email <span class="contact-required">*</span></label>
                            <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" required autocomplete="email">
                        </div>
                        <div class="contact-field-full">
                            <label for="audience">I am a <span class="contact-required">*</span></label>
                            <select id="audience" name="audience" required>
                                <?php foreach ($audiences as $key => $meta): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['audience'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="audienceHint" class="contact-hint">
                                <i class="fas fa-arrow-right"></i>
                                <span><?php echo htmlspecialchars($audienceHints[$form['audience']] ?? $audienceHints['user'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <div class="contact-field-full">
                            <label for="subject">Subject <span class="contact-required">*</span></label>
                            <input id="subject" type="text" name="subject" value="<?php echo htmlspecialchars($form['subject'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Brief summary" required minlength="5">
                        </div>
                        <div class="contact-field-full">
                            <label for="message">Message <span class="contact-required">*</span></label>
                            <textarea id="message" name="message" rows="6" placeholder="Include context, links, or issue details." required minlength="20"><?php echo htmlspecialchars($form['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="contact-counter" id="charCounter">0 characters</div>
                        </div>
                    </div>

                    <div class="contact-submit-row">
                        <span class="contact-submit-note"><i class="fas fa-shield-halved"></i> Sent securely to CoinRex</span>
                        <button class="contact-submit-btn" type="submit" id="submitBtn">
                            <span class="spinner"></span>
                            <span class="btn-text"><i class="fas fa-paper-plane"></i> Send</span>
                            <span class="btn-load">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sidebar -->
            <aside class="contact-sidebar ct-reveal">
                <div class="contact-panel">
                    <h3>Contact channels</h3>
                    <p>Verified official emails.</p>
                    <div class="contact-channels">
                        <div class="contact-channel">
                            <span class="contact-channel-icon"><i class="fas fa-headset"></i></span>
                            <div class="contact-channel-info">
                                <strong>Support</strong>
                                <a href="mailto:support@coinrex.net">support@coinrex.net</a>
                            </div>
                        </div>
                        <div class="contact-channel">
                            <span class="contact-channel-icon"><i class="fas fa-user-shield"></i></span>
                            <div class="contact-channel-info">
                                <strong>Admin</strong>
                                <a href="mailto:admin@coinrex.net">admin@coinrex.net</a>
                            </div>
                        </div>
                        <div class="contact-channel">
                            <span class="contact-channel-icon"><i class="fas fa-diagram-project"></i></span>
                            <div class="contact-channel-info">
                                <strong>Projects</strong>
                                <a href="mailto:projects@coinrex.net">projects@coinrex.net</a>
                            </div>
                        </div>
                        <div class="contact-channel">
                            <span class="contact-channel-icon"><i class="fas fa-bullhorn"></i></span>
                            <div class="contact-channel-info">
                                <strong>Promotions</strong>
                                <a href="mailto:promotions@coinrex.net">promotions@coinrex.net</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="contact-panel">
                    <div class="contact-meta-grid">
                        <div class="contact-meta-item">
                            <strong>Response</strong>
                            <p>Within 24 hours</p>
                        </div>
                        <div class="contact-meta-item">
                            <strong>Hours</strong>
                            <p>Mon–Fri, 9AM–6PM UTC</p>
                        </div>
                    </div>
                    <div class="contact-tip">
                        <h4><i class="fas fa-lightbulb"></i> Tip</h4>
                        <p>Clear subject + full context = faster reply.</p>
                    </div>
                    <div class="contact-social">
                        <h4>Community</h4>
                        <div class="contact-social-links">
                            <a class="contact-social-link" href="#" target="_blank" rel="noopener"><i class="fab fa-twitter"></i> X</a>
                            <a class="contact-social-link" href="#" target="_blank" rel="noopener"><i class="fab fa-telegram-plane"></i> Telegram</a>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <!-- FAQ -->
        <section class="contact-faq ct-reveal">
            <div class="contact-faq-panel">
                <h2>FAQs</h2>
                <p>Quick answers before you reach out.</p>
                <div class="contact-faq-list">
                    <div class="contact-faq-item">
                        <button class="contact-faq-q" type="button">
                            <span>How fast will I get a reply?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-a"><p>Within 24 hours on business days. Complex issues may take longer.</p></div>
                    </div>
                    <div class="contact-faq-item">
                        <button class="contact-faq-q" type="button">
                            <span>What should I include in my message?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-a"><p>A clear subject, detailed description, and any relevant links or transaction hashes.</p></div>
                    </div>
                    <div class="contact-faq-item">
                        <button class="contact-faq-q" type="button">
                            <span>Which contact type should I pick?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-a"><p><strong>User</strong> — general questions. <strong>Developer</strong> — API/DevHub. <strong>Project Owner</strong> — listings. <strong>Promoter</strong> — marketing.</p></div>
                    </div>
                    <div class="contact-faq-item">
                        <button class="contact-faq-q" type="button">
                            <span>Is my data private?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-a"><p>Yes. All messages are encrypted and never shared. See our <a href="<?php echo BASE_URL; ?>/privacy.php" style="color:var(--color-primary-light);">Privacy Policy</a>.</p></div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</main>

<script>
(function() {
    'use strict';

    // Scroll reveal
    const reveals = document.querySelectorAll('.ct-reveal');
    if (reveals.length && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        reveals.forEach(function(el) { obs.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add('visible'); });
    }

    // FAQ accordion
    const faqs = document.querySelectorAll('.contact-faq-item');
    faqs.forEach(function(item) {
        var q = item.querySelector('.contact-faq-q');
        if (q) {
            q.addEventListener('click', function() {
                var open = item.classList.contains('open');
                faqs.forEach(function(f) { f.classList.remove('open'); });
                if (!open) item.classList.add('open');
            });
        }
    });

    // Audience hint
    var sel = document.getElementById('audience');
    var hint = document.getElementById('audienceHint');
    if (sel && hint) {
        var hints = <?php echo json_encode($audienceHints, JSON_UNESCAPED_UNICODE); ?>;
        var span = hint.querySelector('span');
        sel.addEventListener('change', function() {
            if (span) span.textContent = hints[this.value] || hints['user'];
            hint.style.animation = 'none';
            void hint.offsetHeight;
            hint.style.animation = 'ctFadeUp 0.25s ease';
        });
    }

    // Char counter
    var msg = document.getElementById('message');
    var counter = document.getElementById('charCounter');
    if (msg && counter) {
        function count() {
            var len = msg.value.length;
            counter.textContent = len + ' characters';
            counter.classList.remove('warn', 'danger');
            if (len > 1000) counter.classList.add('danger');
            else if (len > 500) counter.classList.add('warn');
        }
        msg.addEventListener('input', count);
        count();
    }

    // Auto-resize textarea
    if (msg) {
        msg.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 350) + 'px';
        });
    }

    // Submit loading
    var form = document.getElementById('contactForm');
    var btn = document.getElementById('submitBtn');
    if (form && btn) {
        form.addEventListener('submit', function() {
            var fields = ['name', 'email', 'subject', 'message'];
            var valid = true;
            fields.forEach(function(id) {
                var f = document.getElementById(id);
                if (f && !f.value.trim()) {
                    valid = false;
                    f.style.borderColor = 'var(--color-danger)';
                    setTimeout(function() { f.style.borderColor = ''; }, 2000);
                }
            });
            if (valid) { btn.disabled = true; btn.classList.add('loading'); }
        });
    }

    // Reset error borders on input
    document.querySelectorAll('.contact-field input, .contact-field select, .contact-field textarea, .contact-field-full input, .contact-field-full select, .contact-field-full textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.style.borderColor = ''; });
    });

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
