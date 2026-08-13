<?php
/**
 * RexLink Wallet Platform — Privacy Policy.
 * Location: /coinrex/wallet/privacy.php
 */

require_once __DIR__ . '/includes/config.php';

$page_title   = 'Privacy Policy — RexLink';
$meta_description = 'How RexLink collects, uses, and protects your data across the wallet platform.';
$updated = 'January 2025';

$sections = [
    ['id' => 'sec-intro', 'title' => '1. Introduction',
     'body' => ['RexLink respects your privacy and is committed to protecting your personal information. This Privacy Policy explains how we collect, use, and safeguard your data when you use the RexLink wallet platform.']],
    ['id' => 'sec-collect', 'title' => '2. Information we collect',
     'body' => [
        'We collect the minimum data required to operate the platform securely:',
        '<ul><li><strong>Account data:</strong> name, email address, and wallet address (if paired).</li><li><strong>Usage & security data:</strong> IP address, device and browser information, platform actions, download events, and anti-abuse signals.</li><li><strong>Contact messages:</strong> information you submit through our contact form.</li></ul>',
        'We do not collect your private keys, seed phrases, or passwords — those remain exclusively on your device.',
     ]],
    ['id' => 'sec-use', 'title' => '3. How we use your data',
     'body' => [
        '<ul><li>Provide and manage wallet features and download access.</li><li>Process wallet-linked review eligibility and claim approvals.</li><li>Prevent fraud, abuse, and unauthorized access.</li><li>Respond to support requests.</li><li>Improve platform reliability and user experience.</li></ul>',
     ]],
    ['id' => 'sec-cookies', 'title' => '4. Cookies & tracking',
     'body' => ['RexLink uses essential cookies to maintain sessions and basic analytics to understand platform usage. You can disable cookies in your browser settings, though some features may not function correctly.']],
    ['id' => 'sec-share', 'title' => '5. Data sharing',
     'body' => ['<strong>We do not sell your personal data.</strong> We may share limited data with trusted service providers (hosting, email, infrastructure) and when required by law or to protect platform security.']],
    ['id' => 'sec-security', 'title' => '6. Data security',
     'body' => ['We implement security measures including encrypted connections, non-custodial storage, and monitoring to reduce the risk of unauthorized access. No system is 100% secure, and we cannot guarantee absolute security.']],
    ['id' => 'sec-rights', 'title' => '7. Your rights',
     'body' => [
        '<ul><li>Access the personal data we hold about you.</li><li>Request correction or deletion of your data.</li><li>Stop using the platform at any time.</li></ul>',
        'To exercise any of these rights, contact us at <a href="mailto:' . WALLET_SUPPORT_EMAIL . '">' . WALLET_SUPPORT_EMAIL . '</a>.',
     ]],
];

require_once __DIR__ . '/includes/header.php';
?>
<main class="wallet-page">

    <section class="wallet-resource-hero">
        <div class="wallet-container">
            <span class="wallet-hero-kicker"><i class="fas fa-shield-halved"></i> Privacy Policy</span>
            <h1>Your privacy, <span class="wallet-gradient-text">protected</span></h1>
            <p class="wallet-resource-hero-lead">
                Last updated: <?php echo htmlspecialchars($updated, ENT_QUOTES, 'UTF-8'); ?>.
                This policy explains what data RexLink collects, why it is needed, and how we protect your information.
            </p>
        </div>
    </section>

    <section class="wallet-legal-section">
        <div class="wallet-container">
            <div class="wallet-legal-grid">
                <nav class="wallet-legal-toc">
                    <h4>On this page</h4>
                    <?php foreach ($sections as $section): ?>
                        <a href="#<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="wallet-legal-content">
                    <?php foreach ($sections as $section): ?>
                        <article class="wallet-legal-block" id="<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <h2><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <?php foreach ($section['body'] as $para): ?>
                                <p><?php echo $para; ?></p>
                            <?php endforeach; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
