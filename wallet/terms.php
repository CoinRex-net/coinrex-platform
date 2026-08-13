<?php
/**
 * RexLink Wallet Platform — Terms of Service.
 * Location: /coinrex/wallet/terms.php
 */

require_once __DIR__ . '/includes/config.php';

$page_title   = 'Terms of Service — RexLink';
$meta_description = 'The terms that govern the use of the RexLink wallet platform.';
$updated = 'January 2025';

$sections = [
    ['id' => 't1', 'title' => '1. Acceptance of terms',
     'body' => ['By accessing or using <strong>RexLink</strong> (the "Platform"), you agree to be bound by these Terms of Service. If you do not agree, you must not use the Platform.']],
    ['id' => 't2', 'title' => '2. Eligibility',
     'body' => ['You must be at least <strong>18 years old</strong> to use RexLink. By using the Platform, you confirm that you meet this requirement.']],
    ['id' => 't3', 'title' => '3. Accounts',
     'body' => ['You agree to provide accurate information, keep your credentials secure, and take responsibility for all activity under your account. We may suspend accounts that provide false or misleading information.']],
    ['id' => 't4', 'title' => '4. Platform usage',
     'body' => [
        '<strong>Allowed:</strong> completing tasks, submitting honest reviews, exploring projects, connecting your wallet, and earning internal rewards.',
        '<strong>Restricted:</strong> fraudulent activity, spam or bots, fake reviews, misleading content, and abuse of referral or reward systems.',
     ]],
    ['id' => 't5', 'title' => '5. Rewards & $REX',
     'body' => ['$REX is an internal platform reward and does not guarantee real monetary value. Rewards, calculations, and qualification rules may change over time. Abuse of the reward system may result in account suspension.']],
    ['id' => 't6', 'title' => '6. Wallet features',
     'body' => ['RexLink is non-custodial. You are responsible for your wallet address and device. Blockchain network fees, failed transactions, and third-party RPC issues are outside our direct control.']],
    ['id' => 't7', 'title' => '7. User content',
     'body' => ['Content you submit must be truthful and lawful. RexLink may display, use, or remove content at its discretion and reserves the right to remove content that violates our policies.']],
    ['id' => 't8', 'title' => '8. Termination',
     'body' => ['We may suspend or terminate accounts that violate these terms, engage in suspicious or abusive behavior, or attempt to exploit the Platform. Decisions regarding account suspension are final.']],
    ['id' => 't9', 'title' => '9. Contact',
     'body' => ['For questions about these terms, contact us at <a href="mailto:' . WALLET_SUPPORT_EMAIL . '">' . WALLET_SUPPORT_EMAIL . '</a>.']],
];

require_once __DIR__ . '/includes/header.php';
?>
<main class="wallet-page">

    <section class="wallet-resource-hero">
        <div class="wallet-container">
            <span class="wallet-hero-kicker"><i class="fas fa-gavel"></i> Terms of Service</span>
            <h1>Fair rules for a <span class="wallet-gradient-text">secure platform</span></h1>
            <p class="wallet-resource-hero-lead">
                Last updated: <?php echo htmlspecialchars($updated, ENT_QUOTES, 'UTF-8'); ?>.
                These terms explain how RexLink should be used and what we expect from the community.
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
