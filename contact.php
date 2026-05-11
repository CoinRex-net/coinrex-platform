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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $form['audience'] = trim((string) ($_POST['audience'] ?? 'user'));
    $form['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $form['message'] = trim((string) ($_POST['message'] ?? ''));

    $audiences = ['user', 'developer', 'project_owner', 'promoter'];

    if ($form['name'] === '' || $form['email'] === '' || $form['subject'] === '' || $form['message'] === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($form['audience'], $audiences, true)) {
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
.contact-wrap{max-width:1100px;margin:24px auto;padding:0 16px}.contact-hero{background:linear-gradient(135deg,#0f172a,#111827);border:1px solid rgba(148,163,184,.2);border-radius:18px;padding:26px}.contact-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;margin-top:16px}.panelx{background:#0f172a;border:1px solid rgba(148,163,184,.15);border-radius:16px;padding:18px}.panelx h3{margin:0 0 12px}.field{display:grid;gap:6px;margin-bottom:10px}.field input,.field select,.field textarea{width:100%;background:#0b1220;border:1px solid #334155;color:#f8fafc;border-radius:10px;padding:10px}.btn-send{background:linear-gradient(135deg,#22c55e,#16a34a);border:0;color:#fff;padding:11px 16px;border-radius:12px;font-weight:600;cursor:pointer}.msg-ok{background:#14532d;color:#bbf7d0;padding:10px;border-radius:8px;margin-bottom:10px}.msg-err{background:#7f1d1d;color:#fecaca;padding:10px;border-radius:8px;margin-bottom:10px}.official a{color:#86efac}@media(max-width:900px){.contact-grid{grid-template-columns:1fr}}
</style>

<div class="contact-wrap">
    <div class="contact-hero">
        <h1 style="margin:0 0 8px;">Contact CoinRex</h1>
        <p style="margin:0;color:#cbd5e1;">Premium support channel for users, developers, project owners, and promoters.</p>
    </div>

    <div class="contact-grid">
        <div class="panelx">
            <h3>Send a Message</h3>
            <?php if ($success !== ''): ?><div class="msg-ok"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="msg-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="field">
                    <label>Full Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="field">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="field">
                    <label>You are a *</label>
                    <select name="audience" required>
                        <option value="user" <?php echo $form['audience'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="developer" <?php echo $form['audience'] === 'developer' ? 'selected' : ''; ?>>Developer</option>
                        <option value="project_owner" <?php echo $form['audience'] === 'project_owner' ? 'selected' : ''; ?>>Project Owner</option>
                        <option value="promoter" <?php echo $form['audience'] === 'promoter' ? 'selected' : ''; ?>>Promoter</option>
                    </select>
                </div>
                <div class="field">
                    <label>Subject *</label>
                    <input type="text" name="subject" value="<?php echo htmlspecialchars($form['subject'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="field">
                    <label>Message *</label>
                    <textarea name="message" rows="6" required><?php echo htmlspecialchars($form['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <button class="btn-send" type="submit">Send to CoinRex</button>
            </form>
        </div>

        <div class="panelx official">
            <h3>Official Contact Channels</h3>
            <p>Use these verified addresses for direct category support.</p>
            <p><i class="fas fa-headset"></i> <strong>Support:</strong> <a href="mailto:support@coinrex.net">support@coinrex.net</a></p>
            <p><i class="fas fa-user-shield"></i> <strong>Admin:</strong> <a href="mailto:admin@coinrex.net">admin@coinrex.net</a></p>
            <p><i class="fas fa-diagram-project"></i> <strong>Projects:</strong> <a href="mailto:projects@coinrex.net"> projects@coinrex.net</a></p>
            <p><i class="fas fa-bullhorn"></i> <strong>Promotions:</strong> <a href="mailto:promotions@coinrex.net">promotions@coinrex.net</a></p>
            <hr style="border-color:#334155;">
            <p style="color:#cbd5e1;">We're always active in our community, Reach us through social Platforms:</p>
            <p><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i> <strong>X (Twitter)</strong></a></p>
            <p><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-telegram-plane"></i> <strong>Telegram</strong></a></p>
            <p style="margin-top:12px;"><strong>Response time:</strong> within 24 hours</p>
            <p><strong>Support Hours:</strong> Mon-Fri, 9AM-6PM UTC</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
