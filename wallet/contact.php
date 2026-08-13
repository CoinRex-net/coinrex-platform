<?php
/**
 * RexLink Wallet Platform — Contact page.
 * Location: /coinrex/wallet/contact.php
 *
 * Self-contained: stores messages in its own table (wallet_contact_messages)
 * so it works independently of the main app.
 */

require_once __DIR__ . '/includes/config.php';

$page_title   = 'Contact RexLink — ' . WALLET_SITE_NAME . ' Wallet Support';
$meta_description = 'Get help with RexLink downloads, wallet pairing, and ' . WALLET_SITE_NAME . ' rewards.';

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = strtolower(trim((string) ($_POST['email'] ?? '')));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (mb_strlen($message) < 10) {
        $error = 'Your message must be at least 10 characters long.';
    } else {
        // Optional honeypot (anti-spam).
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $error = 'Submission rejected.';
        } else {
            $db = walletDb();
            if ($db !== null) {
                try {
                    $stmt = $db->prepare(
                        'CREATE TABLE IF NOT EXISTS wallet_contact_messages (
                            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            name VARCHAR(120) NOT NULL DEFAULT "",
                            email VARCHAR(190) NOT NULL DEFAULT "",
                            subject VARCHAR(190) NOT NULL DEFAULT "",
                            message TEXT NOT NULL,
                            ip_address VARCHAR(45) NOT NULL DEFAULT "",
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (id),
                            KEY idx_wallet_contact_created (created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                    );
                    $stmt->execute();

                    $stmt = $db->prepare(
                        'INSERT INTO wallet_contact_messages (name, email, subject, message, ip_address)
                         VALUES (:name, :email, :subject, :message, :ip)'
                    );
                    $stmt->execute([
                        ':name'    => $name,
                        ':email'   => $email,
                        ':subject' => $subject,
                        ':message' => $message,
                        ':ip'      => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    ]);
                    $sent = true;

                    // Forward to the CoinRex admin inbox (shared `messages` table),
                    // so RexLink messages reach the main admin / support side too.
                    try {
                        $db->prepare(
                            'INSERT INTO messages (title, body, status, recipient_admin_id, created_at)
                             VALUES (:title, :body, \'unread\', NULL, NOW())'
                        )->execute([
                            ':title' => '[RexLink Contact] ' . $subject,
                            ':body'  => 'Name: ' . $name . PHP_EOL
                                . 'Email: ' . $email . PHP_EOL
                                . 'Source: RexLink wallet platform' . PHP_EOL
                                . 'IP: ' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) . PHP_EOL
                                . 'User Agent: ' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500) . PHP_EOL . PHP_EOL
                                . 'Message:' . PHP_EOL . $message,
                        ]);
                    } catch (Throwable $e) {
                        // Non-fatal: the wallet copy is already stored.
                    }
                } catch (Throwable $e) {
                    $error = 'We could not save your message right now. Please email us directly at ' . WALLET_SUPPORT_EMAIL . '.';
                }
            } else {
                $error = 'We could not save your message right now. Please email us directly at ' . WALLET_SUPPORT_EMAIL . '.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<main class="wallet-page">

    <section class="wallet-resource-hero">
        <div class="wallet-container">
            <span class="wallet-hero-kicker"><i class="fas fa-envelope"></i> Contact Us</span>
            <h1>We're here to <span class="wallet-gradient-text">help</span></h1>
            <p class="wallet-resource-hero-lead">
                Questions about downloads, wallet pairing, or rewards? Send us a message and we'll get back to you within 24 hours.
            </p>
        </div>
    </section>

    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-contact-grid">

                <div>
                    <?php if ($sent): ?>
                        <div class="wallet-alert-success">
                            <i class="fas fa-circle-check"></i>
                            <div>
                                <strong>Message sent!</strong><br>
                                Thanks for reaching out — we'll reply to <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?> soon.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="wallet-alert-error">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$sent): ?>
                    <div class="wallet-contact-panel">
                        <h3>Send us a message</h3>
                        <form method="POST" action="<?php echo htmlspecialchars(WALLET_BASE_URL . '/contact.php', ENT_QUOTES, 'UTF-8'); ?>" class="wallet-form" novalidate>
                            <div class="wallet-form-grid">
                                <div class="wallet-form-field">
                                    <label for="contact-name">Name <span class="wallet-required">*</span></label>
                                    <input type="text" id="contact-name" name="name" value="<?php echo htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="Your name" autocomplete="name">
                                </div>
                                <div class="wallet-form-field">
                                    <label for="contact-email">Email <span class="wallet-required">*</span></label>
                                    <input type="email" id="contact-email" name="email" value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="you@example.com" autocomplete="email">
                                </div>
                                <div class="wallet-form-field-full">
                                    <label for="contact-subject">Subject <span class="wallet-required">*</span></label>
                                    <input type="text" id="contact-subject" name="subject" value="<?php echo htmlspecialchars($subject ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="Brief summary">
                                </div>
                                <div class="wallet-form-field-full">
                                    <label for="contact-message">Message <span class="wallet-required">*</span></label>
                                    <textarea id="contact-message" name="message" rows="6" required placeholder="Tell us how we can help..."><?php echo htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div class="wallet-form-field-full" style="display:none;" aria-hidden="true">
                                    <label>Website</label>
                                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                                </div>
                            </div>
                            <div class="wallet-submit-row">
                                <span class="wallet-submit-note"><i class="fas fa-shield-halved"></i> Sent securely</span>
                                <button type="submit" class="wallet-btn-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <aside>
                    <div class="wallet-contact-panel">
                        <h3>Contact channels</h3>
                        <div class="wallet-contact-channels">
                            <div class="wallet-contact-channel">
                                <span class="wallet-contact-channel-icon"><i class="fas fa-headset"></i></span>
                                <div>
                                    <strong>Support</strong>
                                    <a href="mailto:<?php echo htmlspecialchars(WALLET_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(WALLET_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                            </div>
                            <div class="wallet-contact-channel">
                                <span class="wallet-contact-channel-icon"><i class="fas fa-globe"></i></span>
                                <div>
                                    <strong>Main Website</strong>
                                    <a href="<?php echo htmlspecialchars(WALLET_MAIN_SITE_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(WALLET_MAIN_SITE_URL, ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wallet-contact-panel">
                        <h3>Response times</h3>
                        <ul class="wallet-contact-times">
                            <li><strong>Support</strong> — within 24 hours</li>
                            <li><strong>Partnerships</strong> — within 3 business days</li>
                            <li><strong>Security issues</strong> — immediate review</li>
                        </ul>
                    </div>
                </aside>

            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
