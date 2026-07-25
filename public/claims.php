<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireFeatureAccess('claim_center');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$claim_pairing_test_mode = defined('CLAIM_PAIRING_TEST_MODE') && CLAIM_PAIRING_TEST_MODE;
$level_state = syncUserLevelStatus((int) $user['id'], $db) ?: getUserLevelState($user, $db);
if (!$claim_pairing_test_mode && !userCanAccessClaimCenter($level_state)) {
    http_response_code(403);
    $page_title = 'Claim Center Locked';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
    <main class="reward-page">
        <div class="reward-page-shell">
            <section class="reward-panel">
                <div>
                    <span class="reward-tag">PRO Access</span>
                    <h1>Claim Center unlocks at PRO</h1>
                    <p>Complete the 10-day LearnHub mission to automatically unlock PRO access and REX claiming.</p>
                    <div class="page-actions">
                        <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="secondary-btn">Back to Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="primary-btn">Continue LearnHub</a>
                    </div>
                </div>
                <div class="reward-balance-box">
                    <span>Your level</span>
                    <strong><?php echo htmlspecialchars(levelDisplayName($level_state['level'] ?? 'beginner'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p class="reward-note">Only PRO-level accounts can open Claim Center.</p>
                </div>
            </section>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$user = getUserById((int) $user['id']) ?: $user;
$user['level'] = $level_state['level'] ?? ($user['level'] ?? 'beginner');
$user_id = (int) $user['id'];
unlockPendingEarlyAirdropForUser($user_id, $db);
syncSubmittedClaimTransactionsForUser($user_id, $db);
syncStaleClaimApprovalsForUser($user_id, $db);
$claim_eligibility = getClaimEligibility($user_id, $db);
$real_claim_eligibility = $claim_eligibility;
if ($claim_pairing_test_mode) {
    $claim_eligibility = [
        'eligible' => true,
        'message' => 'RexLink Pairing Test Mode is active. You can test wallet pairing only; real claims remain disabled.',
        'pairing_test_mode' => true,
        'real_eligibility' => $real_claim_eligibility,
    ];
}
$balances = [
    'available' => getRewardLedgerBalance($user_id, 'available', $db),
    'locked' => getRewardLedgerBalance($user_id, 'locked', $db),
    'pending' => getRewardLedgerBalance($user_id, 'pending', $db),
    'claimed' => getRewardLedgerBalance($user_id, 'claimed', $db),
];

$open_claim_stmt = $db->prepare("
    SELECT id, total_amount, nonce, status, created_at
    FROM claim_snapshots
    WHERE user_id = ?
      AND status = 'generated'
    ORDER BY id DESC
    LIMIT 1
");
$open_claim_stmt->execute([$user_id]);
$open_claim = $open_claim_stmt->fetch() ?: null;

$history_stmt = $db->prepare("
    SELECT id, total_amount, status, created_at
    FROM claim_snapshots
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 8
");
$history_stmt->execute([$user_id]);
$claim_history = $history_stmt->fetchAll();
$latest_claim = $claim_history[0] ?? null;
$has_claimed_rewards = (float) ($balances['claimed'] ?? 0) > 0 && (float) ($balances['available'] ?? 0) <= 0;
$claim_status_label = !empty($claim_eligibility['eligible']) ? 'Ready' : 'Locked';
$claim_status_note = (string) ($claim_eligibility['message'] ?? '');
$claim_security_review_locked = empty($claim_eligibility['eligible']) && !empty($claim_eligibility['signals']);
if ($claim_pairing_test_mode) {
    $claim_status_label = 'Test Mode';
    $claim_security_review_locked = false;
} elseif ($open_claim) {
    $claim_status_label = 'Processing';
    $claim_status_note = 'Claim is prepared and waiting for the on-chain transaction to finish.';
    $claim_security_review_locked = false;
} elseif ($has_claimed_rewards) {
    $claim_status_label = 'Claimed';
    $claim_status_note = 'Your latest REX claim has been sent to your wallet.';
    $claim_security_review_locked = false;
}

function claimUiStatusLabel($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'used') {
        return 'Claimed';
    }
    if ($status === 'generated') {
        return 'Processing';
    }
    if ($status === 'expired') {
        return 'Expired';
    }
    return $status !== '' ? ucfirst($status) : 'Pending';
}

function claimTokenDeploymentConfig() {
    $root = dirname(__DIR__);
    $candidates = [
        [
            'networkSlug' => 'polygon',
            'networkLabel' => 'Polygon',
            'network' => 'polygon',
            'chainId' => 137,
            'explorerUrl' => 'https://polygonscan.com',
            'tokenFile' => $root . '/deployments/polygon-rex-token.json',
        ],
        [
            'networkSlug' => 'polygon-amoy',
            'networkLabel' => 'Polygon Amoy',
            'network' => 'amoy',
            'chainId' => 80002,
            'explorerUrl' => 'https://amoy.polygonscan.com',
            'tokenFile' => $root . '/deployments/polygon-amoy-rex-token.json',
        ],
    ];

    foreach ($candidates as $candidate) {
        if (!is_readable($candidate['tokenFile'])) {
            continue;
        }

        $deployment = json_decode((string) file_get_contents($candidate['tokenFile']), true);
        if (!is_array($deployment) || empty($deployment['contractAddress'])) {
            continue;
        }

        return array_merge($candidate, [
            'contractAddress' => (string) ($deployment['contractAddress'] ?? ''),
            'tokenName' => (string) ($deployment['tokenName'] ?? 'CoinRex Token'),
            'symbol' => (string) ($deployment['symbol'] ?? 'REX'),
            'decimals' => (int) ($deployment['decimals'] ?? 18),
            'chainId' => (int) ($deployment['chainId'] ?? $candidate['chainId']),
            'network' => (string) ($deployment['network'] ?? $candidate['network']),
        ]);
    }

    return [
        'contractAddress' => '0x995C586c19De4003522b3A23dD7C9c9b112e4c71',
        'tokenName' => 'CoinRex Token',
        'symbol' => 'REX',
        'decimals' => 18,
        'network' => 'amoy',
        'networkSlug' => 'polygon-amoy',
        'networkLabel' => 'Polygon Amoy',
        'chainId' => 80002,
        'explorerUrl' => 'https://amoy.polygonscan.com',
    ];
}

$rex_token = claimTokenDeploymentConfig();

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rexlink-claims.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/rexlink-claims.css'); ?>">

<main class="reward-page">
    <div class="claim-toast" id="claimToast" hidden></div>
    <div class="reward-page-shell">
        <?php if ($claim_pairing_test_mode): ?>
            <div class="claim-test-mode-banner">
                <strong>RexLink Pairing Test Mode</strong>
                <span>Wallet pairing is unlocked for this session so you can test QR/code connection. Real reward claims and approval submission remain disabled.</span>
            </div>
        <?php endif; ?>
        <section class="reward-panel">
            <div>
                <span class="reward-tag">Claim Center</span>
                <h1>Your REX balance</h1>
                <p>Claim available REX with a quick RexLink approval.</p>
                <div class="page-actions">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="secondary-btn">Back to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/taskhub.php" class="secondary-btn">LearnHub</a>
                </div>
            </div>
            <div class="reward-balance-box">
                <span>Claim status</span>
                <strong id="heroClaimState"><?php echo htmlspecialchars($claim_status_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                <p class="reward-note" id="heroClaimNote"><?php echo htmlspecialchars($claim_status_note, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </section>

        <section class="claim-balance-landing" aria-label="REX claim balance">
            <div class="claim-primary-stack">
                <div class="claim-balance-hero">
                    <div class="claim-balance-main">
                        <span>Available to claim</span>
                        <strong id="claimLandingAvailable"><?php echo number_format((float) $balances['available'], 2); ?></strong>
                        <small id="claimLandingStatus"><?php echo htmlspecialchars($claim_status_note, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <div class="claim-main-cta">
                        <button type="button" id="openClaimModalButton" class="primary-btn" <?php echo (!$claim_pairing_test_mode && (empty($claim_eligibility['eligible']) || $open_claim || (float) ($balances['available'] ?? 0) <= 0)) ? 'disabled' : ''; ?>>
                            <?php echo $claim_pairing_test_mode ? 'Test RexLink Pairing' : ($open_claim ? 'Claim Processing' : (empty($claim_eligibility['eligible']) ? 'Claim Locked' : 'Claim REX')); ?>
                        </button>
                        <?php if ($open_claim): ?>
                            <button type="button" id="trackOpenClaimButton" class="secondary-btn">Track Claim</button>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/public/contact.php" id="claimSupportCta" class="secondary-btn claim-support-cta" <?php echo $claim_security_review_locked ? '' : 'hidden'; ?>>Contact Support</a>
                    </div>
                </div>
                <div class="claim-detail-card">
                    <span>Token</span>
                    <div class="claim-contract-line">
                        <span>REX on <?php echo htmlspecialchars((string) ($rex_token['networkLabel'] ?? 'Polygon'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <a href="<?php echo htmlspecialchars(rtrim((string) ($rex_token['explorerUrl'] ?? 'https://polygonscan.com'), '/') . '/address/' . (string) $rex_token['contractAddress'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <?php echo htmlspecialchars((string) $rex_token['contractAddress'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="claim-detail-stack">
                <div class="claim-detail-card">
                    <span>Balance details</span>
                    <div class="claim-detail-grid">
                        <div class="claim-metric"><span>Locked</span><strong id="claimLandingLocked"><?php echo number_format((float) $balances['locked'], 2); ?></strong></div>
                        <div class="claim-metric"><span>Pending</span><strong id="claimLandingPending"><?php echo number_format((float) $balances['pending'], 2); ?></strong></div>
                        <div class="claim-metric"><span>Claimed</span><strong id="claimLandingClaimed"><?php echo number_format((float) $balances['claimed'], 2); ?></strong></div>
                        <div class="claim-metric"><span>Status</span><strong id="claimLandingState"><?php echo htmlspecialchars($claim_status_label, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    </div>
                </div>
                <div class="claim-detail-card claim-session-card" id="claimSessionCard">
                    <div class="claim-session-head">
                        <div>
                            <span>RexLink session</span>
                            <h3 id="claimSessionTitle">No wallet connected</h3>
                        </div>
                        <strong class="claim-session-status" id="claimSessionStatus">Not connected</strong>
                    </div>
                    <div class="claim-session-body">
                        <strong class="claim-session-wallet" id="claimSessionWallet">Ready to pair with RexLink.</strong>
                        <small class="claim-session-countdown" id="claimLandingCountdownText" hidden>Session expired</small>
                        <p class="claim-session-note" id="claimSessionNote">Scan the QR or enter the 6 digit code.</p>
                    </div>
                    <div class="claim-session-actions">
                        <button type="button" id="claimSessionConnectButton" class="primary-btn">Connect RexLink</button>
                        <button type="button" id="claimSessionContinueButton" class="primary-btn" hidden>Continue Claim</button>
                        <button type="button" id="claimSessionDisconnectButton" class="secondary-btn" hidden>Disconnect</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="claim-history-card" aria-label="Claim history">
            <h3>Claim history</h3>
            <div class="claim-history-compact">
                <?php if (!empty($claim_history)): ?>
                    <?php foreach ($claim_history as $snapshot): ?>
                        <div class="history-row">
                            <div>
                                <strong>Snapshot #<?php echo (int) $snapshot['id']; ?></strong>
                                <span><?php echo htmlspecialchars((string) $snapshot['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="history-row-right">
                                <strong><?php echo number_format((float) ($snapshot['total_amount'] ?? 0), 2); ?> $REX</strong>
                                <span><?php echo htmlspecialchars(claimUiStatusLabel($snapshot['status'] ?? 'generated'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="history-row">
                        <div>
                            <strong>No claim history yet</strong>
                            <span>Completed claims will appear here.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <div class="claim-modal" id="claimCheckoutModal" role="dialog" aria-modal="true" aria-labelledby="claimModalTitle" hidden>
        <div class="claim-modal-backdrop" id="claimModalBackdrop"></div>
        <div class="claim-modal-dialog">
            <div>
                <div class="claim-modal-head">
                    <div>
                        <span class="reward-tag" id="claimModalTag">Claim checkout</span>
                        <h3 id="claimModalTitle">Claim REX</h3>
                    </div>
                    <button type="button" class="claim-modal-close" id="claimModalClose" aria-label="Close claim checkout">&times;</button>
                </div>
                <div class="claim-modal-progress" aria-label="Claim checkout progress">
                    <span id="claimModalProgressDuration">1. Session</span>
                    <span id="claimModalProgressConnect">2. Connect</span>
                    <span id="claimModalProgressAmount">3. Amount</span>
                    <span id="claimModalProgressApprove">4. Approve</span>
                </div>
            </div>

            <div class="claim-modal-body">
                <section class="claim-modal-step" id="claimModalDurationStep">
                    <div class="claim-modal-copy">
                        <h4>Choose session time</h4>
                        <p id="claimModalDurationCopy">Pick how long RexLink should stay connected for this claim.</p>
                    </div>
                    <div class="claim-duration-panel" id="claimModalDurationPanel">
                        <span>Session time</span>
                        <div class="claim-duration-options" id="claimModalDurationOptions">
                            <button type="button" class="duration-option" data-duration="5">5 min</button>
                            <button type="button" class="duration-option is-active" data-duration="10">10 min</button>
                            <button type="button" class="duration-option" data-duration="30">30 min</button>
                            <button type="button" class="duration-option" data-duration="60">60 min</button>
                        </div>
                    </div>
                </section>

                <section class="claim-modal-step" id="claimModalLoadingStep">
                    <div class="claim-modal-loading">
                        <i class="fas fa-spinner"></i>
                        <strong id="claimModalLoadingTitle">Preparing next step...</strong>
                        <span id="claimModalLoadingText">This usually takes a moment.</span>
                    </div>
                </section>

                <section class="claim-modal-step" id="claimModalConnectStep">
                    <div class="claim-modal-copy">
                        <h4>Connect RexLink</h4>
                        <p id="claimModalConnectCopy">Your QR code is being prepared.</p>
                        <ul class="claim-connect-tips" aria-label="RexLink connection steps">
                            <li><i class="fas fa-mobile-screen-button"></i><span>Open RexLink on your phone.</span></li>
                            <li><i class="fas fa-qrcode"></i><span>Scan the QR or enter the 6 digit code.</span></li>
                            <li><i class="fas fa-bolt"></i><span>After connection, this modal moves to amount automatically.</span></li>
                        </ul>
                        <div class="claim-connect-actions">
                            <button type="button" class="primary-btn" id="claimModalInlineQrButton">Refresh QR</button>
                        </div>
                    </div>
                    <div class="claim-modal-qr-card">
                        <div class="claim-modal-qr-stage">
                            <div class="claim-modal-qr-placeholder" id="claimModalQrPlaceholder">
                                        <span>Pick a session time, then create your QR code.</span>
                            </div>
                            <img id="claimModalQrImage" alt="RexLink pairing QR" hidden>
                            <div class="claim-modal-logo-badge" id="claimModalLogoBadge">
                                <img src="<?php echo ASSETS_URL; ?>/images/favicon.png" alt="CoinRex">
                            </div>
                        </div>
                        <div class="claim-modal-code">
                            <div class="claim-code-row">
                                <strong id="claimModalPairingCode" class="claim-code-display">No code yet</strong>
                                <button type="button" class="claim-copy-code-button" id="claimModalCopyCodeButton" aria-label="Copy pairing code" title="Copy code" disabled>
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <small class="claim-pairing-expiry" id="claimModalPairingExpiry" hidden>QR expires soon</small>
                        </div>
                    </div>
                </section>

                <section class="claim-modal-step" id="claimModalAmountStep">
                    <div class="claim-modal-copy">
                        <h4>Choose claim amount</h4>
                        <p id="claimModalAmountCopy">Confirm the REX amount for this claim.</p>
                    </div>
                    <div class="claim-modal-session">
                        <div class="claim-metric"><span>Wallet</span><strong id="claimModalWalletText">Connected</strong></div>
                        <div class="claim-metric"><span>Session</span><strong><small class="claim-session-countdown" id="claimModalCountdownText" hidden>Session expired</small></strong></div>
                    </div>
                    <div class="claim-modal-amount-box">
                        <span class="claim-amount-hint" id="claimModalAvailableHint">Available: <?php echo number_format((float) $balances['available'], 2); ?> REX</span>
                        <div class="claim-modal-amount-row">
                            <input
                                type="number"
                                id="claimModalAmountInput"
                                min="0.00000001"
                                step="0.00000001"
                                inputmode="decimal"
                                value="<?php echo htmlspecialchars(number_format((float) $balances['available'], 8, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <button type="button" id="claimModalMaxButton">Max</button>
                        </div>
                        <span class="claim-inline-alert" id="claimModalAmountAlert"></span>
                    </div>
                </section>

                <section class="claim-modal-step" id="claimModalApprovalStep">
                    <div class="claim-result-stage">
                        <div class="claim-result-icon is-loading" id="claimResultIcon">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="claim-modal-copy">
                            <h4 id="claimResultTitle">Waiting for approval</h4>
                            <p id="claimResultMessage">Open RexLink and approve this claim request.</p>
                            <div class="claim-approval-steps" aria-label="Claim approval status">
                                <div class="claim-approval-step is-active" id="claimApprovalStepWaiting"><span></span>Sent to RexLink</div>
                                <div class="claim-approval-step" id="claimApprovalStepApproved"><span></span>Mobile approval</div>
                                <div class="claim-approval-step" id="claimApprovalStepSubmitted"><span></span>Transaction status</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="claim-modal-footer">
                <button type="button" id="claimModalSecondaryButton" class="secondary-btn">Close</button>
                <button type="button" id="claimModalPrimaryButton" class="primary-btn">Continue</button>
            </div>
        </div>
    </div>

</main>

<script src="<?php echo ASSETS_URL; ?>/js/qrcode-browser.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/qrcode-browser.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-pairing.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-pairing.js'); ?>"></script>
<script>
window.CoinRexClaimsConfig = {
    overviewUrl: <?php echo json_encode(BASE_URL . '/api/reward_overview.php'); ?>,
    baseUri: <?php echo json_encode(BASE_URI); ?>,
    configuredApiBaseUrl: <?php echo json_encode(BASE_URL); ?>,
    publicApiBaseUrl: <?php echo json_encode(defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL); ?>,
    hasConfiguredPublicApiBaseUrl: <?php echo defined('PUBLIC_BASE_URL_CONFIGURED') && PUBLIC_BASE_URL_CONFIGURED ? 'true' : 'false'; ?>,
    rexlinkApiBaseUrl: <?php echo json_encode(BASE_URL); ?>,
    realtimeDebug: <?php echo in_array(strtolower(trim((string) (getenv('COINREX_REALTIME_DEBUG') ?: ''))), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false'; ?>,
    serverClaimPairingTestMode: <?php echo $claim_pairing_test_mode ? 'true' : 'false'; ?>,
    serverClaimEligible: <?php echo !empty($claim_eligibility['eligible']) ? 'true' : 'false'; ?>,
    serverClaimSecurityReviewLocked: <?php echo $claim_security_review_locked ? 'true' : 'false'; ?>,
    initialAvailableBalance: <?php echo json_encode((float) ($balances['available'] ?? 0)); ?>,
    initialClaimedBalance: <?php echo json_encode((float) ($balances['claimed'] ?? 0)); ?>,
    initialOpenClaim: <?php echo $open_claim ? 'true' : 'false'; ?>,
    rexTokenNetworkSlug: <?php echo json_encode((string) ($rex_token['networkSlug'] ?? 'polygon')); ?>,
    rexTokenNetworkLabel: <?php echo json_encode((string) ($rex_token['networkLabel'] ?? 'Polygon')); ?>,
    rexTokenChainId: <?php echo (int) ($rex_token['chainId'] ?? 137); ?>
};
</script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-claims.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-claims.js'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
