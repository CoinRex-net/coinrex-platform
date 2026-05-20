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

<style>
.contact-premium-page {
    background: var(--theme-public-page-bg);
    color: var(--color-text-secondary);
    padding: 28px 0 72px;
}

.contact-premium-shell {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
}

.contact-hero-premium {
    position: relative;
    overflow: hidden;
    background: var(--theme-public-hero-bg);
    border: 1px solid var(--color-border-card);
    border-radius: 32px;
    box-shadow: var(--shadow-hero);
    padding: 34px;
}

.contact-hero-premium::before,
.contact-hero-premium::after {
    content: "";
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.contact-hero-premium::before {
    width: 260px;
    height: 260px;
    top: -110px;
    right: -80px;
    background: radial-gradient(circle, rgba(96, 165, 250, 0.24) 0%, rgba(96, 165, 250, 0) 72%);
}

.contact-hero-premium::after {
    width: 220px;
    height: 220px;
    bottom: -120px;
    left: -60px;
    background: radial-gradient(circle, rgba(212, 175, 55, 0.18) 0%, rgba(212, 175, 55, 0) 72%);
}

.contact-hero-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
    gap: 26px;
    align-items: stretch;
}

.contact-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: var(--theme-kicker-bg);
    border: 1px solid var(--theme-kicker-border);
    color: var(--color-accent-soft);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.contact-hero-copy h1 {
    margin: 18px 0 14px;
    color: var(--color-text-primary);
    font-size: clamp(2.2rem, 4vw, 4rem);
    line-height: 1.02;
    letter-spacing: -0.03em;
}

.contact-hero-copy p {
    margin: 0;
    max-width: 640px;
    color: var(--color-text-muted);
    font-size: 0.98rem;
    line-height: 1.55;
}

.contact-hero-highlights {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.contact-highlight-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 16px;
    background: var(--theme-glass-bg-strong);
    border: 1px solid var(--theme-glass-border);
    color: var(--color-text-primary);
    font-size: 0.94rem;
}

.contact-highlight-pill i {
    color: var(--color-accent-light);
}

.contact-hero-side {
    display: grid;
    gap: 14px;
}

.contact-side-card {
    background: var(--theme-public-info-card);
    border: 1px solid var(--color-border-slate-soft);
    border-radius: 24px;
    padding: 22px;
    box-shadow: var(--shadow-card);
}

.contact-side-card strong,
.contact-stat-value {
    color: var(--color-text-primary);
}

.contact-side-card p,
.contact-side-card li,
.contact-stat-label {
    color: var(--color-text-muted);
}

.contact-stat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.contact-stat {
    padding: 16px;
    border-radius: 18px;
    background: rgba(2, 6, 23, 0.28);
    border: 1px solid var(--color-border-slate-soft);
}

.contact-stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.contact-support-list {
    list-style: none;
    margin: 14px 0 0;
    padding: 0;
    display: grid;
    gap: 12px;
}

.contact-support-list li {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.contact-support-list i {
    margin-top: 3px;
    color: var(--color-primary-light);
}

.contact-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
    gap: 22px;
    margin-top: 22px;
}

.contact-panel {
    background: var(--theme-public-info-card);
    border: 1px solid var(--color-border-slate-soft);
    border-radius: 28px;
    padding: 28px;
    box-shadow: var(--shadow-panel);
}

.contact-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 22px;
}

.contact-panel-header h2,
.contact-sidebar-title {
    margin: 0;
    color: var(--color-text-primary);
}

.contact-panel-header p,
.contact-sidebar-copy,
.contact-note-copy,
.contact-field-help {
    margin: 6px 0 0;
    color: var(--color-text-muted);
}

.contact-field-help {
    font-size: 0.86rem;
}

.contact-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(29, 78, 216, 0.12);
    border: 1px solid rgba(96, 165, 250, 0.24);
    color: var(--color-info-soft);
    font-size: 0.85rem;
    font-weight: 700;
}

.contact-alert {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border-radius: 18px;
    padding: 16px 18px;
    margin-bottom: 18px;
    border: 1px solid transparent;
}

.contact-alert i {
    margin-top: 2px;
}

.contact-alert strong {
    display: block;
    color: var(--color-text-primary);
    margin-bottom: 4px;
}

.contact-alert p {
    margin: 0;
    color: inherit;
}

.contact-alert-success {
    background: rgba(34, 197, 94, 0.12);
    border-color: rgba(34, 197, 94, 0.22);
    color: #bbf7d0;
}

.contact-alert-error {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.22);
    color: var(--color-danger-soft);
}

.contact-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.contact-field,
.contact-field-full {
    display: grid;
    gap: 8px;
}

.contact-field-full {
    grid-column: 1 / -1;
}

.contact-field label {
    color: var(--color-text-primary);
    font-weight: 700;
    font-size: 0.95rem;
}

.contact-required {
    color: var(--color-accent-light);
}

.contact-field input,
.contact-field select,
.contact-field textarea,
.contact-field-full input,
.contact-field-full select,
.contact-field-full textarea {
    width: 100%;
    border-radius: 16px;
    padding: 14px 16px;
    background: var(--color-input-bg);
    color: var(--color-input-text);
    border: 2px solid var(--color-input-border);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.contact-field textarea,
.contact-field-full textarea {
    resize: vertical;
    min-height: 180px;
}

.contact-field input:focus,
.contact-field select:focus,
.contact-field textarea:focus,
.contact-field-full input:focus,
.contact-field-full select:focus,
.contact-field-full textarea:focus {
    outline: none;
    border-color: var(--color-input-focus);
    box-shadow: 0 0 0 4px var(--color-input-focus-ring);
}

.contact-submit-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.contact-submit-copy {
    color: var(--color-text-muted);
    font-size: 0.92rem;
    max-width: 420px;
}

.contact-submit-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: none;
    border-radius: 18px;
    padding: 15px 22px;
    font-size: 0.96rem;
    font-weight: 800;
    color: var(--color-text-inverse);
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    box-shadow: var(--shadow-primary);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.contact-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px rgba(29, 78, 216, 0.28);
}

.contact-sidebar-stack {
    display: grid;
    gap: 18px;
}

.contact-channel-list {
    display: grid;
    gap: 14px;
}

.contact-channel-card {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 16px;
    border-radius: 20px;
    background: rgba(2, 6, 23, 0.28);
    border: 1px solid var(--color-border-slate-soft);
}

.contact-channel-icon {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(29, 78, 216, 0.14);
    color: var(--color-info-soft);
}

.contact-channel-card strong,
.contact-meta-card strong {
    display: block;
    color: var(--color-text-primary);
    margin-bottom: 4px;
}

.contact-meta-card p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.94rem;
    line-height: 1.55;
}

.contact-channel-email {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
    font-size: 0.94rem;
}

.contact-channel-card a,
.contact-social-card a {
    color: var(--color-info-soft);
    text-decoration: none;
}

.contact-channel-card a:hover,
.contact-social-card a:hover {
    color: var(--color-text-primary);
}

.contact-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.contact-meta-card,
.contact-note-card,
.contact-social-card {
    padding: 18px;
    border-radius: 20px;
    background: rgba(2, 6, 23, 0.28);
    border: 1px solid var(--color-border-slate-soft);
}

.contact-note-card {
    background: var(--theme-public-note-bg);
}

.contact-note-card h3,
.contact-social-card h3 {
    margin: 0 0 8px;
    color: var(--color-text-primary);
}

.contact-social-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 14px;
}

.contact-social-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 14px;
    background: var(--theme-glass-bg);
    border: 1px solid var(--theme-glass-border-soft);
    color: var(--color-text-primary);
}

@media (max-width: 1100px) {
    .contact-hero-grid,
    .contact-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .contact-premium-page {
        padding: 18px 0 56px;
    }

    .contact-premium-shell {
        padding: 0 16px;
    }

    .contact-hero-premium,
    .contact-panel {
        border-radius: 24px;
        padding: 22px;
    }

    .contact-form-grid,
    .contact-meta-grid,
    .contact-stat-grid {
        grid-template-columns: 1fr;
    }

    .contact-panel-header,
    .contact-submit-row {
        flex-direction: column;
        align-items: stretch;
    }

    .contact-submit-btn {
        justify-content: center;
        width: 100%;
    }
}

@media (max-width: 520px) {
    .contact-hero-copy h1 {
        line-height: 1.08;
    }

    .contact-highlight-pill,
    .contact-channel-card,
    .contact-meta-card,
    .contact-note-card,
    .contact-social-link {
        border-radius: 16px;
    }
}
</style>

<main class="contact-premium-page">
    <div class="contact-premium-shell">
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

        <section class="contact-content-grid">
            <div class="contact-panel">
                <div class="contact-panel-header">
                    <div>
                        <h2>Send a message</h2>
                        <p>Simple and direct.</p>
                    </div>
                    <span class="contact-badge"><i class="fas fa-lock"></i> Secure form</span>
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
                            <strong>Please review your submission</strong>
                            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="contact-name">Full name <span class="contact-required">*</span></label>
                            <input id="contact-name" type="text" name="name" value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your full name" required>
                        </div>

                        <div class="contact-field">
                            <label for="contact-email">Email address <span class="contact-required">*</span></label>
                            <input id="contact-email" type="email" name="email" value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com" required>
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-audience">You are contacting us as <span class="contact-required">*</span></label>
                            <select id="contact-audience" name="audience" required>
                                <?php foreach ($audiences as $audienceKey => $audienceMeta): ?>
                                    <option value="<?php echo htmlspecialchars($audienceKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['audience'] === $audienceKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($audienceMeta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="contact-field-help"><i class="fas fa-arrow-right"></i> Choose the closest match.</span>
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-subject">Subject <span class="contact-required">*</span></label>
                            <input id="contact-subject" type="text" name="subject" value="<?php echo htmlspecialchars($form['subject'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Briefly summarize your request" required>
                        </div>

                        <div class="contact-field contact-field-full">
                            <label for="contact-message">Message <span class="contact-required">*</span></label>
                            <textarea id="contact-message" name="message" rows="7" placeholder="Include the full context, project details, links, or issue summary here." required><?php echo htmlspecialchars($form['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <span class="contact-field-help"><i class="fas fa-circle-info"></i> Keep it clear and specific.</span>
                        </div>
                    </div>

                    <div class="contact-submit-row">
                        <div class="contact-submit-copy"><i class="fas fa-shield-halved"></i> Sent securely to CoinRex.</div>
                        <button class="contact-submit-btn" type="submit">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send to CoinRex</span>
                        </button>
                    </div>
                </form>
            </div>

            <aside class="contact-sidebar-stack">
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
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
