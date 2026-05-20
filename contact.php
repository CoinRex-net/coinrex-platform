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
    'user' => 'Report issues, ask general questions, or share feedback about the platform.',
    'developer' => 'Technical inquiries, API access, DevHub integration, or bug reports.',
    'project_owner' => 'Project listing, verification, updates, or partnership opportunities.',
    'promoter' => 'Marketing collaborations, affiliate programs, or promotional campaigns.',
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

<main class="contact-premium-page">
    <div class="contact-premium-shell">
        <!-- Hero Section -->
        <section class="contact-hero-premium">
            <div class="contact-hero-grid">
                <div class="contact-hero-copy">
                    <span class="contact-kicker"><i class="fas fa-headset"></i> Premium contact experience</span>
                    <h1>Let’s connect with the right CoinRex team faster.</h1>
                    <p>Fast, clear support for users, developers, project owners, and promoters.</p>

                    <div class="contact-hero-highlights">
                        <span class="contact-highlight-pill"><i class="fas fa-bolt"></i> Priority-routed inquiries</span>
                        <span class="contact-highlight-pill"><i class="fas fa-shield-check"></i> Verified official channels</span>
                        <span class="contact-highlight-pill"><i class="fas fa-clock"></i> Human response within 24 hours</span>
                    </div>
                </div>

                <aside class="contact-hero-side">
                    <div class="contact-side-card">
                        <div class="contact-stat-grid">
                            <div class="contact-stat">
                                <span class="contact-stat-value">24h</span>
                                <span class="contact-stat-label">Target response window</span>
                            </div>
                            <div class="contact-stat">
                                <span class="contact-stat-value">4</span>
                                <span class="contact-stat-label">Dedicated support routes</span>
                            </div>
                            <div class="contact-stat">
                                <span class="contact-stat-value">Mon–Fri</span>
                                <span class="contact-stat-label">Core support coverage</span>
                            </div>
                            <div class="contact-stat">
                                <span class="contact-stat-value">Secure</span>
                                <span class="contact-stat-label">Protected by CSRF validation</span>
                            </div>
                        </div>
                    </div>

                    <div class="contact-side-card">
                        <strong>Best results, faster replies</strong>
                        <ul class="contact-support-list">
                            <li><i class="fas fa-check-circle"></i><span>Pick the right contact type.</span></li>
                            <li><i class="fas fa-file-lines"></i><span>Use a clear subject.</span></li>
                            <li><i class="fas fa-link"></i><span>Add useful links or references.</span></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Content Grid: Form + Sidebar -->
        <section class="contact-content-grid">
            <div class="contact-panel contact-fade-in">
                <div class="contact-panel-header">
                    <div>
                        <h2>Send a message</h2>
                        <p>Simple and direct.</p>
                    </div>
                    <span class="contact-badge"><i class="fas fa-lock"></i> Secure form</span>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="contact-alert contact-alert-success" id="contactSuccessAlert">
                        <i class="fas fa-circle-check"></i>
                        <div>
                            <strong>Message delivered</strong>
                            <p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="contact-alert contact-alert-error" id="contactErrorAlert">
                        <i class="fas fa-circle-exclamation"></i>
                        <div>
                            <strong>Please review your submission</strong>
                            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="contact-name">Full name <span class="contact-required">*</span></label>
                            <input id="contact-name" type="text" name="name" value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your full name" required autocomplete="name">
                        </div>

                        <div class="contact-field">
                            <label for="contact-email">Email address <span class="contact-required">*</span></label>
                            <input id="contact-email" type="email" name="email" value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" required autocomplete="email">
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-audience">You are contacting us as <span class="contact-required">*</span></label>
                            <select id="contact-audience" name="audience" required>
                                <?php foreach ($audiences as $audienceKey => $audienceMeta): ?>
                                    <option value="<?php echo htmlspecialchars($audienceKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['audience'] === $audienceKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($audienceMeta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="contactAudienceHint" class="contact-audience-hint">
                                <i class="fas fa-arrow-right"></i>
                                <span><?php echo htmlspecialchars($audienceHints[$form['audience']] ?? $audienceHints['user'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-subject">Subject <span class="contact-required">*</span></label>
                            <input id="contact-subject" type="text" name="subject" value="<?php echo htmlspecialchars($form['subject'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Briefly summarize your request" required minlength="5">
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-message">Message <span class="contact-required">*</span></label>
                            <textarea id="contact-message" name="message" rows="7" placeholder="Include the full context, project details, links, or issue summary here." required minlength="20"><?php echo htmlspecialchars($form['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="contact-char-counter" id="contactCharCounter">0 characters</div>
                        </div>
                    </div>

                    <div class="contact-submit-row">
                        <div class="contact-submit-copy"><i class="fas fa-shield-halved"></i> Sent securely to CoinRex.</div>
                        <button class="contact-submit-btn" type="submit" id="contactSubmitBtn">
                            <span class="btn-spinner"></span>
                            <span class="btn-text"><i class="fas fa-paper-plane"></i> Send to CoinRex</span>
                            <span class="btn-loading-text"><i class="fas fa-spinner"></i> Sending...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sidebar -->
            <aside class="contact-sidebar-stack contact-fade-in">
                <div class="contact-panel">
                    <h2 class="contact-sidebar-title">Official contact channels</h2>
                    <p class="contact-sidebar-copy">Verified channels only.</p>

                    <div class="contact-channel-list" style="margin-top:18px;">
                        <div class="contact-channel-card">
                            <span class="contact-channel-icon"><i class="fas fa-headset"></i></span>
                            <div>
                                <strong>Support</strong>
                                <a class="contact-channel-email" href="mailto:support@coinrex.net"><i class="fas fa-envelope"></i><span>support@coinrex.net</span></a>
                            </div>
                        </div>

                        <div class="contact-channel-card">
                            <span class="contact-channel-icon"><i class="fas fa-user-shield"></i></span>
                            <div>
                                <strong>Admin</strong>
                                <a class="contact-channel-email" href="mailto:admin@coinrex.net"><i class="fas fa-envelope"></i><span>admin@coinrex.net</span></a>
                            </div>
                        </div>

                        <div class="contact-channel-card">
                            <span class="contact-channel-icon"><i class="fas fa-diagram-project"></i></span>
                            <div>
                                <strong>Projects</strong>
                                <a class="contact-channel-email" href="mailto:projects@coinrex.net"><i class="fas fa-envelope"></i><span>projects@coinrex.net</span></a>
                            </div>
                        </div>

                        <div class="contact-channel-card">
                            <span class="contact-channel-icon"><i class="fas fa-bullhorn"></i></span>
                            <div>
                                <strong>Promotions</strong>
                                <a class="contact-channel-email" href="mailto:promotions@coinrex.net"><i class="fas fa-envelope"></i><span>promotions@coinrex.net</span></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="contact-panel">
                    <div class="contact-meta-grid">
                        <div class="contact-meta-card">
                            <strong>Response time</strong>
                            <p>Within 24 hours for standard inquiries.</p>
                        </div>
                        <div class="contact-meta-card">
                            <strong>Support hours</strong>
                            <p>Monday to Friday, 9AM–6PM UTC.</p>
                        </div>
                    </div>

                    <div class="contact-note-card" style="margin-top:14px;">
                        <h3><i class="fas fa-lightbulb"></i> Quick tip</h3>
                        <p class="contact-note-copy">Short subject. Clear message. Useful links.</p>
                    </div>

                    <div class="contact-social-card" style="margin-top:14px;">
                        <h3><i class="fas fa-hashtag"></i> Community</h3>

                        <div class="contact-social-links">
                            <a class="contact-social-link" href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i><span>X (Twitter)</span></a>
                            <a class="contact-social-link" href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-telegram-plane"></i><span>Telegram</span></a>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <!-- FAQ Accordion Section -->
        <section class="contact-faq-section contact-fade-in">
            <div class="contact-faq-panel">
                <h2>Frequently asked questions</h2>
                <p>Quick answers to common inquiries before you reach out.</p>

                <div class="contact-faq-list">
                    <div class="contact-faq-item">
                        <button class="contact-faq-question" type="button">
                            <span>How quickly will I get a response?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-answer">
                            <p>We aim to respond to all inquiries within 24 hours during business days (Monday–Friday). Complex issues may require additional time for thorough investigation.</p>
                        </div>
                    </div>

                    <div class="contact-faq-item">
                        <button class="contact-faq-question" type="button">
                            <span>What information should I include in my message?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-answer">
                            <p>Include a clear subject line, detailed description of your issue or request, relevant links (project URLs, transaction hashes), and any steps you've already taken. The more context you provide, the faster we can help.</p>
                        </div>
                    </div>

                    <div class="contact-faq-item">
                        <button class="contact-faq-question" type="button">
                            <span>How do I choose the right contact type?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-answer">
                            <p>Select the option that best matches your role: <strong>User</strong> for general platform questions, <strong>Developer</strong> for API/DevHub inquiries, <strong>Project Owner</strong> for project listings and verification, and <strong>Promoter</strong> for marketing and affiliate partnerships.</p>
                        </div>
                    </div>

                    <div class="contact-faq-item">
                        <button class="contact-faq-question" type="button">
                            <span>Can I report a scam or suspicious project?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-answer">
                            <p>Yes. Please use the contact form and select <strong>User</strong> as your contact type. Include the project name, URL, and any evidence you have. You can also use our dedicated report system for urgent cases.</p>
                        </div>
                    </div>

                    <div class="contact-faq-item">
                        <button class="contact-faq-question" type="button">
                            <span>Is my information kept private?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="contact-faq-answer">
                            <p>Absolutely. All messages are encrypted in transit and stored securely. Your personal information is only used to respond to your inquiry and is never shared with third parties. See our <a href="<?php echo BASE_URL; ?>/privacy.php" style="color:var(--color-info-soft);">Privacy Policy</a> for details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
(function() {
    'use strict';

    // ── Scroll-triggered fade-in ──
    const fadeElements = document.querySelectorAll('.contact-fade-in');
    if (fadeElements.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        fadeElements.forEach(function(el) {
            observer.observe(el);
        });
    } else {
        fadeElements.forEach(function(el) {
            el.classList.add('is-visible');
        });
    }

    // ── FAQ Accordion ──
    const faqItems = document.querySelectorAll('.contact-faq-item');
    faqItems.forEach(function(item) {
        const question = item.querySelector('.contact-faq-question');
        if (question) {
            question.addEventListener('click', function() {
                const isOpen = item.classList.contains('is-open');
                // Close all other items
                faqItems.forEach(function(other) {
                    other.classList.remove('is-open');
                });
                // Toggle this one
                if (!isOpen) {
                    item.classList.add('is-open');
                }
            });
        }
    });

    // ── Audience Dynamic Hint ──
    const audienceSelect = document.getElementById('contact-audience');
    const audienceHint = document.getElementById('contactAudienceHint');
    if (audienceSelect && audienceHint) {
        const hints = <?php echo json_encode($audienceHints, JSON_UNESCAPED_UNICODE); ?>;
        const hintSpan = audienceHint.querySelector('span');

        audienceSelect.addEventListener('change', function() {
            const value = this.value;
            const text = hints[value] || hints['user'];
            if (hintSpan) {
                hintSpan.textContent = text;
            }
            // Re-trigger animation
            audienceHint.style.animation = 'none';
            void audienceHint.offsetHeight; // force reflow
            audienceHint.style.animation = 'contactFadeIn 0.3s ease';
        });
    }

    // ── Character Counter ──
    const messageField = document.getElementById('contact-message');
    const charCounter = document.getElementById('contactCharCounter');
    if (messageField && charCounter) {
        function updateCharCount() {
            const len = messageField.value.length;
            charCounter.textContent = len + ' characters';
            charCounter.classList.remove('is-warning', 'is-danger');
            if (len > 1000) {
                charCounter.classList.add('is-danger');
            } else if (len > 500) {
                charCounter.classList.add('is-warning');
            }
        }
        messageField.addEventListener('input', updateCharCount);
        updateCharCount();
    }

    // ── Auto-resize textarea ──
    if (messageField) {
        function autoResize() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 400) + 'px';
        }
        messageField.addEventListener('input', autoResize);
    }

    // ── Form Submit Loading State ──
    const contactForm = document.getElementById('contactForm');
    const submitBtn = document.getElementById('contactSubmitBtn');
    if (contactForm && submitBtn) {
        contactForm.addEventListener('submit', function() {
            // Basic client-side validation
            const name = document.getElementById('contact-name');
            const email = document.getElementById('contact-email');
            const subject = document.getElementById('contact-subject');
            const message = document.getElementById('contact-message');

            let valid = true;
            [name, email, subject, message].forEach(function(field) {
                if (field && !field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'var(--color-danger)';
                    setTimeout(function() {
                        field.style.borderColor = '';
                    }, 2000);
                }
            });

            if (valid) {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
            }
        });
    }

    // ── Input field focus border reset ──
    document.querySelectorAll('.contact-field input, .contact-field select, .contact-field textarea, .contact-field-full input, .contact-field-full select, .contact-field-full textarea').forEach(function(input) {
        input.addEventListener('input', function() {
            this.style.borderColor = '';
        });
    });

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
