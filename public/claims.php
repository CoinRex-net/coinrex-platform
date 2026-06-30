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
$level_state = syncUserLevelStatus((int) $user['id'], $db) ?: getUserLevelState($user, $db);
if (!userCanAccessClaimCenter($level_state)) {
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
                    <p>Complete your LearnHub mission, referral, account-age, and security requirements to unlock REX claiming.</p>
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
if ($open_claim) {
    $claim_status_label = 'Processing';
    $claim_status_note = 'Claim is prepared and waiting for the on-chain transaction to finish.';
} elseif ($has_claimed_rewards) {
    $claim_status_label = 'Claimed';
    $claim_status_note = 'Your latest REX claim has been sent to your wallet.';
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

$rex_token = [
    'contractAddress' => '0x995C586c19De4003522b3A23dD7C9c9b112e4c71',
    'tokenName' => 'CoinRex Token',
    'symbol' => 'REX',
    'decimals' => 18,
    'network' => 'amoy',
    'chainId' => 80002,
];
$deployment_path = dirname(__DIR__) . '/deployments/polygon-amoy-rex-token.json';
if (is_readable($deployment_path)) {
    $deployment_json = json_decode((string) file_get_contents($deployment_path), true);
    if (is_array($deployment_json) && !empty($deployment_json['contractAddress'])) {
        $rex_token = array_merge($rex_token, [
            'contractAddress' => (string) ($deployment_json['contractAddress'] ?? $rex_token['contractAddress']),
            'tokenName' => (string) ($deployment_json['tokenName'] ?? $rex_token['tokenName']),
            'symbol' => (string) ($deployment_json['symbol'] ?? $rex_token['symbol']),
            'decimals' => (int) ($deployment_json['decimals'] ?? $rex_token['decimals']),
            'network' => (string) ($deployment_json['network'] ?? $rex_token['network']),
            'chainId' => (int) ($deployment_json['chainId'] ?? $rex_token['chainId']),
        ]);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
<style>
.claim-code-display {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 5px 9px;
    border-radius: 8px;
    border: 1px solid rgba(250, 204, 21, .34);
    background: rgba(212, 175, 55, .10);
    color: #facc15;
    font-size: clamp(14px, 2.3vw, 19px);
    font-weight: 900;
    letter-spacing: .08em;
    overflow-wrap: anywhere;
}
.claim-code-display.is-connected {
    border-color: rgba(34, 197, 94, .34);
    background: rgba(22, 163, 74, .12);
    color: #86efac;
    letter-spacing: 0;
}
.claim-code-display:not(.is-pending):not(.is-connected) {
    color: #b9c7e8;
    letter-spacing: 0;
    font-size: 13px;
}
.claim-inline-alert {
    min-height: 22px;
    color: #facc15;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.45;
}
.claim-toast {
    background: #0f172a;
}
.claim-toast.is-success {
    background: #102817;
}
.claim-toast.is-error {
    background: #33131a;
}
.page-actions {
    min-width: 0;
}
.claim-inline-alert.is-error {
    color: #fca5a5;
}
.claim-inline-alert.is-success {
    color: #86efac;
}
.claim-duration-panel {
    margin-top: 14px;
    display: grid;
    gap: 9px;
}
.claim-duration-panel > span {
    color: #b9c7e8;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}
.claim-duration-options {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}
.duration-option {
    min-height: 38px;
    border: 1px solid rgba(148, 163, 184, .16);
    border-radius: 8px;
    background: rgba(8, 17, 32, .62);
    color: #b9c7e8;
    font-weight: 900;
    cursor: pointer;
}
.duration-option.is-active {
    border-color: rgba(250, 204, 21, .55);
    color: #facc15;
    background: rgba(212, 175, 55, .11);
}
.duration-option:disabled {
    cursor: not-allowed;
    opacity: .58;
}
.claim-session-countdown {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    margin-top: 8px;
    padding: 6px 10px;
    border: 1px solid rgba(34, 197, 94, .26);
    border-radius: 8px;
    background: rgba(34, 197, 94, .10);
    color: #86efac;
    font-size: 12px;
    font-weight: 900;
}
.claim-session-countdown.is-warning {
    border-color: rgba(250, 204, 21, .38);
    background: rgba(250, 204, 21, .11);
    color: #facc15;
}
.claim-session-countdown.is-expired {
    border-color: rgba(248, 113, 113, .34);
    background: rgba(239, 68, 68, .10);
    color: #fca5a5;
}
.claim-amount-hint {
    color: #91a4bd;
    font-size: 12px;
    line-height: 1.45;
}
.claim-history-compact {
    display: grid;
    gap: 10px;
}
.claim-history-compact .history-row {
    padding: 10px 0;
}
@media (max-width: 560px) {
    .claim-duration-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .claim-code-display { max-width: 100%; justify-content: center; }
}
.claim-balance-landing {
    display: grid;
    grid-template-columns: minmax(0, 1.08fr) minmax(290px, .92fr);
    gap: 14px;
    align-items: start;
}
.claim-balance-hero,
.claim-detail-card,
.claim-history-card {
    border: 1px solid var(--color-border-card);
    border-radius: 16px;
    background: var(--theme-public-info-card);
    box-shadow: var(--shadow-card);
    min-width: 0;
}
.claim-primary-stack {
    display: grid;
    gap: 12px;
}
.claim-balance-hero {
    padding: clamp(20px, 3.2vw, 30px);
    display: grid;
    gap: 16px;
    position: relative;
    overflow: hidden;
}
.claim-balance-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(135deg, rgba(29, 78, 216, .18), transparent 46%, rgba(212, 175, 55, .12));
}
.claim-balance-hero > * {
    position: relative;
    z-index: 1;
}
.claim-balance-main span,
.claim-detail-card span {
    color: #91a4bd;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
}
.claim-balance-main strong {
    display: block;
    margin-top: 8px;
    color: #f8fafc;
    font-size: clamp(2.3rem, 7vw, 4.75rem);
    line-height: .95;
    overflow-wrap: anywhere;
}
.claim-balance-main small {
    display: block;
    margin-top: 8px;
    color: #b9c7e8;
    font-size: 13px;
    line-height: 1.45;
}
.claim-main-cta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.claim-main-cta .primary-btn {
    min-height: 50px;
    padding-inline: 22px;
    font-size: 1rem;
}
.claim-detail-stack {
    display: grid;
    gap: 12px;
}
.claim-detail-card {
    padding: 16px;
    display: grid;
    gap: 10px;
}
.claim-session-card {
    border-color: rgba(148, 163, 184, .18);
    background: linear-gradient(145deg, rgba(15, 23, 42, .96), rgba(8, 17, 32, .88));
}
.claim-session-card.is-connected {
    border-color: rgba(34, 197, 94, .28);
    background: linear-gradient(145deg, rgba(22, 101, 52, .18), rgba(8, 17, 32, .90));
}
.claim-session-card.is-expired {
    border-color: rgba(248, 113, 113, .28);
    background: linear-gradient(145deg, rgba(127, 29, 29, .18), rgba(8, 17, 32, .90));
}
.claim-session-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}
.claim-session-head h3 {
    margin: 4px 0 0;
    color: #f8fafc;
    font-size: 1rem;
}
.claim-session-status {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(148, 163, 184, .10);
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
}
.claim-session-card.is-connected .claim-session-status {
    border-color: rgba(34, 197, 94, .28);
    background: rgba(34, 197, 94, .12);
    color: #86efac;
}
.claim-session-card.is-expired .claim-session-status {
    border-color: rgba(248, 113, 113, .28);
    background: rgba(239, 68, 68, .12);
    color: #fca5a5;
}
.claim-session-body {
    display: grid;
    gap: 6px;
}
.claim-session-wallet {
    color: #f8fafc;
    font-weight: 900;
    overflow-wrap: anywhere;
}
.claim-session-note {
    color: #9fb1c9;
    font-size: 13px;
    line-height: 1.45;
    margin: 0;
}
.claim-session-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.claim-session-actions .primary-btn,
.claim-session-actions .secondary-btn {
    min-height: 40px;
    padding: 0 14px;
}
.claim-session-actions [hidden] {
    display: none !important;
}
.claim-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.claim-detail-grid .claim-metric {
    border-radius: 8px;
    padding: 11px;
}
.claim-contract-line {
    min-width: 0;
}
.claim-history-card {
    margin-top: 14px;
    padding: 16px;
}
.claim-history-card h3 {
    margin: 0 0 10px;
    color: #f8fafc;
    font-size: 1rem;
}
.claim-modal[hidden] {
    display: none;
}
.claim-modal {
    position: fixed;
    inset: 0;
    z-index: 1400;
    overflow-y: auto;
    padding: 12px 0;
    box-sizing: border-box;
}
.claim-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, .78);
    backdrop-filter: blur(6px);
}
.claim-modal-dialog {
    position: relative;
    width: min(700px, calc(100vw - 28px));
    margin: 0 auto;
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 18px;
    background: #0f172a;
    box-shadow: 0 26px 80px rgba(0, 0, 0, .46);
    overflow: hidden;
    display: grid;
    grid-template-rows: auto auto auto;
}
.claim-modal-head {
    padding: 12px 18px 8px;
    border-bottom: 1px solid rgba(148, 163, 184, .12);
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
}
.claim-modal-head h3 {
    margin: 3px 0 0;
    color: #f8fafc;
    font-size: clamp(1.05rem, 2vw, 1.35rem);
}
.claim-modal-close {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(30, 41, 59, .92);
    color: #f8fafc;
    cursor: pointer;
    flex: 0 0 auto;
}
.claim-modal-progress {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    padding: 0 18px 9px;
    border-bottom: 1px solid rgba(148, 163, 184, .12);
}
.claim-modal-progress span {
    min-width: 0;
    border-radius: 8px;
    background: rgba(148, 163, 184, .10);
    color: #91a4bd;
    font-size: 11px;
    font-weight: 900;
    text-align: center;
    padding: 6px 6px;
}
.claim-modal-progress span.is-active {
    background: rgba(250, 204, 21, .14);
    color: #facc15;
}
.claim-modal-progress span.is-complete {
    background: rgba(34, 197, 94, .14);
    color: #86efac;
}
.claim-modal-body {
    overflow: visible;
    padding: 12px 18px;
}
.claim-modal-step {
    display: none;
}
.claim-modal-step.is-active {
    display: grid;
    gap: 12px;
}
#claimModalConnectStep.is-active {
    grid-template-columns: minmax(240px, .9fr) minmax(290px, 1.1fr);
    align-items: stretch;
    column-gap: 14px;
}
#claimModalDurationStep.is-active,
#claimModalLoadingStep.is-active {
    align-content: center;
    min-height: clamp(190px, 28vh, 260px);
}
#claimModalDurationStep .claim-modal-copy {
    max-width: 520px;
    margin: 0 auto;
    text-align: center;
}
#claimModalDurationStep .claim-duration-panel {
    width: min(100%, 520px);
    margin: 0 auto;
}
#claimModalConnectStep .claim-modal-copy {
    grid-column: 1;
    align-self: stretch;
    max-width: none;
    border: 1px solid rgba(148, 163, 184, .14);
    border-radius: 16px;
    padding: 13px;
    background:
        radial-gradient(circle at top left, rgba(250, 204, 21, .12), transparent 34%),
        rgba(8, 17, 32, .62);
    display: grid;
    align-content: start;
    gap: 10px;
}
#claimModalConnectStep .claim-modal-qr-card {
    grid-column: 2;
}
.claim-connect-tips {
    display: grid;
    gap: 6px;
    margin: 4px 0 0;
    padding: 0;
    list-style: none;
}
.claim-connect-tips li {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    color: #cbd5e1;
    font-size: 11.5px;
    line-height: 1.35;
}
.claim-connect-tips i {
    width: 24px;
    height: 24px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: rgba(250, 204, 21, .12);
    color: #facc15;
    font-size: 11px;
}
.claim-connect-actions {
    display: flex;
    justify-content: flex-start;
    margin-top: 2px;
}
.claim-connect-actions .primary-btn {
    min-height: 36px;
    padding: 0 13px;
    font-size: 12px;
}
.claim-modal-copy h4 {
    margin: 0;
    color: #f8fafc;
    font-size: .98rem;
}
.claim-modal-copy p {
    margin: 4px 0 0;
    color: #b9c7e8;
    font-size: 12px;
    line-height: 1.45;
}
.claim-modal-footer {
    padding: 10px 18px 12px;
    border-top: 1px solid rgba(148, 163, 184, .12);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}
.claim-modal-footer.is-hidden {
    display: none;
}
.claim-modal-qr-card {
    width: min(100%, 390px);
    justify-self: center;
    border-radius: 16px;
    padding: 12px;
    border: 1px solid rgba(250, 204, 21, .32);
    background: linear-gradient(145deg, rgba(13, 27, 52, .98), rgba(8, 17, 32, .94));
    box-shadow: 0 22px 48px rgba(2, 6, 23, .38);
    display: grid;
    place-items: center;
    gap: 8px;
}
.claim-modal-qr-stage {
    position: relative;
    width: min(100%, 268px);
    aspect-ratio: 1;
    margin: 0 auto;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: rgba(2, 6, 23, .86);
    border: 1px solid rgba(147, 197, 253, .20);
}
.claim-modal-qr-stage > img {
    width: 232px;
    height: 232px;
    border-radius: 8px;
    background: #fff;
    padding: 8px;
}
.claim-modal-qr-placeholder {
    width: 232px;
    height: 232px;
    border-radius: 8px;
    border: 1px dashed rgba(212, 175, 55, .36);
    display: grid;
    place-items: center;
    text-align: center;
    color: #b9c7e8;
    padding: 18px;
}
.claim-modal-qr-placeholder.is-rendered {
    border-style: solid;
    background: #fff;
    padding: 8px;
}
.claim-modal-qr-placeholder.is-rendered svg {
    display: block;
    width: 232px;
    height: 232px;
}
.claim-modal-qr-placeholder[hidden],
.claim-modal-qr-stage > img[hidden] {
    display: none !important;
}
.claim-modal-logo-badge {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: none;
    align-items: center;
    justify-content: center;
    background: #081120;
    border: 4px solid #fff;
    pointer-events: none;
    z-index: 2;
}
.claim-modal-logo-badge.is-visible {
    display: flex;
}
.claim-modal-logo-badge img {
    width: 35px;
    height: 35px;
    object-fit: contain;
    padding: 0;
    background: transparent;
    border-radius: 0;
}
.claim-modal-code {
    margin-top: 8px;
    text-align: center;
    display: grid;
    gap: 8px;
}
.claim-pairing-expiry {
    color: #91a4bd;
    font-size: 11px;
    font-weight: 800;
}
.claim-pairing-expiry.is-warning {
    color: #facc15;
}
.claim-pairing-expiry.is-expired {
    color: #fca5a5;
}
.claim-modal-code strong {
    justify-self: center;
}
.claim-code-row {
    display: inline-grid;
    grid-template-columns: minmax(0, auto) 32px;
    gap: 6px;
    align-items: center;
    justify-content: center;
    max-width: 100%;
}
.claim-copy-code-button {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid rgba(250, 204, 21, .32);
    background: rgba(212, 175, 55, .10);
    color: #facc15;
    cursor: pointer;
    display: inline-grid;
    place-items: center;
}
.claim-copy-code-button:disabled {
    cursor: not-allowed;
    opacity: .48;
}
.claim-modal-session {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.claim-modal-amount-box {
    border: 1px solid rgba(148, 163, 184, .14);
    border-radius: 14px;
    padding: 12px;
    background: rgba(2, 6, 23, .34);
    display: grid;
    gap: 10px;
}
.claim-modal-amount-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
}
.claim-modal-amount-row input {
    min-width: 0;
    min-height: 52px;
    border: 1px solid rgba(148, 163, 184, .20);
    border-radius: 12px;
    background: rgba(8, 17, 32, .86);
    color: #f8fafc;
    padding: 0 14px;
    font: inherit;
    font-size: clamp(1.18rem, 4vw, 1.7rem);
    font-weight: 900;
}
.claim-modal-amount-row button {
    min-height: 52px;
    border-radius: 12px;
    border: 1px solid rgba(250, 204, 21, .38);
    background: rgba(212, 175, 55, .12);
    color: #facc15;
    font-weight: 900;
    padding: 0 16px;
    cursor: pointer;
}
.claim-result-stage {
    min-height: 160px;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    text-align: left;
}
.claim-result-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    background: rgba(250, 204, 21, .13);
    color: #facc15;
    font-size: 24px;
}
.claim-approval-steps {
    display: grid;
    gap: 6px;
    padding: 10px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, .12);
    background: rgba(2, 6, 23, .28);
}
.claim-approval-step {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #91a4bd;
    font-size: 13px;
    font-weight: 800;
}
.claim-approval-step span {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: rgba(148, 163, 184, .36);
    flex: 0 0 auto;
}
.claim-approval-step.is-active {
    color: #facc15;
}
.claim-approval-step.is-active span {
    background: #facc15;
}
.claim-approval-step.is-complete {
    color: #86efac;
}
.claim-approval-step.is-complete span {
    background: #86efac;
}
.claim-approval-step.is-error {
    color: #fca5a5;
}
.claim-approval-step.is-error span {
    background: #fca5a5;
}
.claim-result-icon.is-loading i {
    animation: claimSpin 1s linear infinite;
}
.claim-modal-loading {
    display: grid;
    justify-items: center;
    gap: 10px;
    padding: 22px 12px;
    text-align: center;
}
.claim-modal-loading i {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: rgba(250, 204, 21, .12);
    color: #facc15;
    animation: claimSpin 1s linear infinite;
}
.claim-modal-loading strong {
    color: #f8fafc;
    font-size: 1rem;
}
.claim-modal-loading span {
    color: #b9c7e8;
    font-size: 13px;
}
.claim-result-icon.is-success {
    background: rgba(34, 197, 94, .14);
    color: #86efac;
}
.claim-result-icon.is-error {
    background: rgba(239, 68, 68, .14);
    color: #fca5a5;
}
@keyframes claimSpin {
    to { transform: rotate(360deg); }
}
@media (max-width: 860px) {
    .claim-balance-landing {
        grid-template-columns: 1fr;
    }
    #claimModalConnectStep.is-active {
        grid-template-columns: 1fr;
    }
    #claimModalConnectStep .claim-modal-copy,
    #claimModalConnectStep .claim-modal-qr-card {
        grid-column: auto;
        grid-row: auto;
    }
    #claimModalConnectStep .claim-modal-qr-card {
        order: 2;
    }
    #claimModalConnectStep .claim-modal-copy {
        order: 1;
        align-content: start;
    }
}
@media (max-width: 560px) {
    .claim-detail-grid,
    .claim-modal-session {
        grid-template-columns: 1fr;
    }
    .claim-modal-dialog {
        width: min(420px, calc(100vw - 18px));
        margin: 0 auto;
        border-radius: 16px;
    }
    .claim-modal-head {
        padding: 10px 12px 7px;
    }
    .claim-modal-head h3 {
        font-size: 1rem;
    }
    .claim-modal-close {
        width: 34px;
        height: 34px;
        border-radius: 10px;
    }
    .claim-modal-progress {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 5px;
        padding: 0 12px 7px;
    }
    .claim-modal-progress span {
        min-height: 27px;
        padding: 5px 3px;
        font-size: 9.5px;
        line-height: 1.15;
        white-space: nowrap;
    }
    .claim-modal-body {
        padding: 10px 12px;
    }
    #claimModalDurationStep.is-active,
    #claimModalLoadingStep.is-active,
    #claimModalConnectStep.is-active {
        gap: 10px;
    }
    .claim-modal-footer {
        display: grid;
        gap: 8px;
        padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
        background: #0f172a;
    }
    .claim-modal-footer .primary-btn,
    .claim-modal-footer .secondary-btn {
        width: 100%;
        min-height: 38px;
        justify-content: center;
    }
    .claim-modal-copy h4 {
        font-size: .95rem;
    }
    .claim-modal-copy p {
        font-size: 12px;
        line-height: 1.35;
    }
    .claim-modal-qr-card {
        padding: 10px;
        border-radius: 14px;
        width: 100%;
    }
    .claim-modal-qr-stage {
        width: min(100%, 196px);
        border-radius: 14px;
    }
    .claim-modal-code {
        margin-top: 8px;
        gap: 6px;
    }
    .claim-duration-panel {
        gap: 7px;
    }
    .claim-duration-options {
        gap: 6px;
    }
    .duration-option {
        min-height: 34px;
        font-size: 12px;
    }
    .claim-modal-amount-row {
        grid-template-columns: 1fr;
    }
    .claim-result-stage {
        grid-template-columns: 1fr;
        text-align: center;
        justify-items: center;
    }
    .claim-modal-qr-stage > img,
    .claim-modal-qr-placeholder,
    .claim-modal-qr-placeholder.is-rendered svg {
        width: min(190px, calc(100vw - 116px));
        height: min(190px, calc(100vw - 116px));
    }
    .claim-modal-logo-badge {
        width: 44px;
        height: 44px;
        border-width: 3px;
        border-radius: 12px;
    }
    .claim-modal-logo-badge img {
        width: 31px;
        height: 31px;
    }
}
</style>

<main class="reward-page">
    <div class="claim-toast" id="claimToast" hidden></div>
    <div class="reward-page-shell">
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
                        <button type="button" id="openClaimModalButton" class="primary-btn" <?php echo empty($claim_eligibility['eligible']) || $open_claim || (float) ($balances['available'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                            <?php echo $open_claim ? 'Claim Processing' : (empty($claim_eligibility['eligible']) ? 'Claim Locked' : 'Claim REX'); ?>
                        </button>
                        <?php if ($open_claim): ?>
                            <button type="button" id="trackOpenClaimButton" class="secondary-btn">Track Claim</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="claim-detail-card">
                    <span>Token</span>
                    <div class="claim-contract-line">
                        <span>REX on Polygon Amoy</span>
                        <a href="https://amoy.polygonscan.com/address/<?php echo htmlspecialchars((string) $rex_token['contractAddress'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
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
<script>
(function() {
    const overviewUrl = <?php echo json_encode(BASE_URL . '/api/reward_overview.php'); ?>;
    const browserBaseUrl = window.location.origin + <?php echo json_encode(BASE_URI); ?>;
    const configuredApiBaseUrl = <?php echo json_encode(BASE_URL); ?>;
    const publicApiBaseUrl = <?php echo json_encode(defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL); ?>;
    const hasConfiguredPublicApiBaseUrl = <?php echo defined('PUBLIC_BASE_URL_CONFIGURED') && PUBLIC_BASE_URL_CONFIGURED ? 'true' : 'false'; ?>;
    const rexlinkApiBaseUrl = <?php echo json_encode(defined('REXLINK_API_BASE_URL') ? REXLINK_API_BASE_URL : BASE_URL); ?>;
    const createPairingUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_pairing.php';
    const pairingQrUrl = rexlinkApiBaseUrl + '/api/rex-signer/pairing_qr.php';
    const sessionsUrl = rexlinkApiBaseUrl + '/api/rex-signer/sessions.php';
    const revokeSessionUrl = rexlinkApiBaseUrl + '/api/rex-signer/revoke_session.php';
    const createClaimApprovalUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_claim_approval.php';
    const approvalStatusUrl = rexlinkApiBaseUrl + '/api/rex-signer/approval_status.php';
    const approvalsUrl = rexlinkApiBaseUrl + '/api/rex-signer/approval_requests.php';
    const realtimeAuthUrl = rexlinkApiBaseUrl + '/api/rex-signer/realtime_auth.php';
    const realtimeDebug = <?php echo in_array(strtolower(trim((string) (getenv('COINREX_REALTIME_DEBUG') ?: ''))), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false'; ?>;
    const serverClaimEligible = <?php echo !empty($claim_eligibility['eligible']) ? 'true' : 'false'; ?>;
    const initialAvailableBalance = <?php echo json_encode((float) ($balances['available'] ?? 0)); ?>;
    const initialClaimedBalance = <?php echo json_encode((float) ($balances['claimed'] ?? 0)); ?>;
    const initialOpenClaim = <?php echo $open_claim ? 'true' : 'false'; ?>;

    let currentClaimEligible = serverClaimEligible;
    let hasOpenClaim = initialOpenClaim;
    let availableBalanceValue = Number(initialAvailableBalance || 0);
    let claimedBalanceValue = Number(initialClaimedBalance || 0);
    let activeSessionCount = 0;
    let activeSessionId = 0;
    let activeWalletAddress = '';
    let activeSessionRemainingSeconds = 0;
    let activeSessionCountdownStartedAt = 0;
    let activeRequestId = 0;
    let selectedDuration = 10;
    let modalStep = 'duration';
    let modalLoadingMessage = '';
    let modalLoadingDetail = '';
    let modalLoadingTimer = null;
    let modalResultState = 'waiting';
    let approvalDecisionMessage = '';
    let claimFailureMessage = '';
    let hasPendingPairingCode = false;
    let sessionPollTimer = null;
    let sessionPollIntervalMs = 0;
    let approvalPollTimer = null;
    let countdownTimer = null;
    let sessionExpiryRefreshQueued = false;
    let sessionInactiveMessage = '';
    let creatingPairing = false;
    let realtimeSocket = null;
    let realtimeConnected = false;
    let realtimeReconnectTimer = null;
    let realtimePingTimer = null;
    let realtimeReconnectDelay = 1000;
    let sessionRefreshInFlight = false;
    let approvalPollInFlight = false;
    let amountInputTouched = false;
    let pairingGenerationFailed = false;
    let pairingExpired = false;
    let pairingExpiresAtMs = 0;
    let pairingCountdownTimer = null;
    let modalLoadingProgressStep = 'duration';

    const modal = document.getElementById('claimCheckoutModal');
    const openButton = document.getElementById('openClaimModalButton');
    const trackButton = document.getElementById('trackOpenClaimButton');
    const closeButton = document.getElementById('claimModalClose');
    const backdrop = document.getElementById('claimModalBackdrop');
    const primaryButton = document.getElementById('claimModalPrimaryButton');
    const secondaryButton = document.getElementById('claimModalSecondaryButton');
    const inlineQrButton = document.getElementById('claimModalInlineQrButton');
    const modalFooter = document.querySelector('.claim-modal-footer');
    const modalTitle = document.getElementById('claimModalTitle');
    const durationStep = document.getElementById('claimModalDurationStep');
    const loadingStep = document.getElementById('claimModalLoadingStep');
    const connectStep = document.getElementById('claimModalConnectStep');
    const amountStep = document.getElementById('claimModalAmountStep');
    const approvalStep = document.getElementById('claimModalApprovalStep');
    const progressDuration = document.getElementById('claimModalProgressDuration');
    const progressConnect = document.getElementById('claimModalProgressConnect');
    const progressAmount = document.getElementById('claimModalProgressAmount');
    const progressApprove = document.getElementById('claimModalProgressApprove');
    const qrPlaceholder = document.getElementById('claimModalQrPlaceholder');
    const qrImage = document.getElementById('claimModalQrImage');
    const logoBadge = document.getElementById('claimModalLogoBadge');
    const pairingCode = document.getElementById('claimModalPairingCode');
    const copyCodeButton = document.getElementById('claimModalCopyCodeButton');
    const qrNote = document.getElementById('claimModalQrNote');
    const pairingExpiryText = document.getElementById('claimModalPairingExpiry');
    const durationCopy = document.getElementById('claimModalDurationCopy');
    const durationOptions = document.getElementById('claimModalDurationOptions');
    const loadingTitle = document.getElementById('claimModalLoadingTitle');
    const loadingText = document.getElementById('claimModalLoadingText');
    const connectCopy = document.getElementById('claimModalConnectCopy');
    const modalWalletText = document.getElementById('claimModalWalletText');
    const modalCountdownText = document.getElementById('claimModalCountdownText');
    const amountInput = document.getElementById('claimModalAmountInput');
    const maxButton = document.getElementById('claimModalMaxButton');
    const availableHint = document.getElementById('claimModalAvailableHint');
    const amountAlert = document.getElementById('claimModalAmountAlert');
    const resultIcon = document.getElementById('claimResultIcon');
    const resultTitle = document.getElementById('claimResultTitle');
    const resultMessage = document.getElementById('claimResultMessage');
    const approvalStepWaiting = document.getElementById('claimApprovalStepWaiting');
    const approvalStepApproved = document.getElementById('claimApprovalStepApproved');
    const approvalStepSubmitted = document.getElementById('claimApprovalStepSubmitted');
    const landingAvailable = document.getElementById('claimLandingAvailable');
    const landingLocked = document.getElementById('claimLandingLocked');
    const landingPending = document.getElementById('claimLandingPending');
    const landingClaimed = document.getElementById('claimLandingClaimed');
    const landingState = document.getElementById('claimLandingState');
    const landingStatus = document.getElementById('claimLandingStatus');
    const sessionCard = document.getElementById('claimSessionCard');
    const sessionTitle = document.getElementById('claimSessionTitle');
    const sessionStatus = document.getElementById('claimSessionStatus');
    const sessionWallet = document.getElementById('claimSessionWallet');
    const sessionNote = document.getElementById('claimSessionNote');
    const landingCountdownText = document.getElementById('claimLandingCountdownText');
    const sessionConnectButton = document.getElementById('claimSessionConnectButton');
    const sessionContinueButton = document.getElementById('claimSessionContinueButton');
    const sessionDisconnectButton = document.getElementById('claimSessionDisconnectButton');
    const heroClaimState = document.getElementById('heroClaimState');
    const heroClaimNote = document.getElementById('heroClaimNote');
    let currentPairingDisplayCode = '';

    async function postJson(url, body, options) {
        const requestOptions = options || {};
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timeoutMs = Number(requestOptions.timeoutMs || 0);
        let timeoutId = null;

        if (controller && timeoutMs > 0) {
            timeoutId = window.setTimeout(function() {
                controller.abort();
            }, timeoutMs);
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body || {}),
                signal: controller ? controller.signal : undefined,
            });

            const rawText = await response.text();
            let data = null;
            if (rawText) {
                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    throw new Error(rawText.slice(0, 180) || 'Server returned an invalid response.');
                }
            }

            if (!response.ok) {
                throw new Error((data && data.message) || ('Request failed with status ' + response.status + '.'));
            }

            return data || {};
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw new Error('Request timed out. Please try again.');
            }
            throw error;
        } finally {
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }
        }
    }

    function formatRex(amount, digits) {
        return Number(amount || 0).toLocaleString(undefined, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        });
    }

    function shortAddress(address) {
        return address ? address.slice(0, 6) + '...' + address.slice(-4) : 'Connected';
    }

    function hostFromUrl(value) {
        try {
            return new URL(String(value || '').includes('://') ? String(value || '') : 'https://' + String(value || '')).hostname.toLowerCase();
        } catch (error) {
            return '';
        }
    }

    function isLocalOrPrivateHost(host) {
        host = String(host || '').toLowerCase();
        return host === 'localhost'
            || host === '127.0.0.1'
            || host === '::1'
            || /^10\./.test(host)
            || /^192\.168\./.test(host)
            || /^172\.(1[6-9]|2\d|3[0-1])\./.test(host);
    }

    function isLoopbackHost(host) {
        host = String(host || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1' || host === '::1';
    }

    function pairingReachabilityWarning() {
        const apiHost = hostFromUrl(publicApiBaseUrl || configuredApiBaseUrl);
        const pageHost = hostFromUrl(browserBaseUrl);
        if (isLoopbackHost(apiHost)) {
            return 'Phone may not reach this localhost URL. Add COINREX_PUBLIC_BASE_URL with your LAN IP or use a live HTTPS domain.';
        }
        if (isLoopbackHost(pageHost)) {
            return 'Phone may not reach this localhost URL. Open CoinRex with your LAN IP or set COINREX_PUBLIC_BASE_URL.';
        }
        if (isLocalOrPrivateHost(pageHost) && !isLoopbackHost(pageHost)) {
            return '';
        }
        if (!hasConfiguredPublicApiBaseUrl && apiHost && pageHost && apiHost !== pageHost) {
            return 'Phone may not reach this website because the QR API host differs from the page host. Set COINREX_PUBLIC_BASE_URL to the same LAN IP.';
        }
        return '';
    }

    function compactPairingPayload(payload) {
        payload = payload && typeof payload === 'object' ? payload : {};
        const normalizedPublicBaseUrl = String(publicApiBaseUrl || configuredApiBaseUrl).replace(/\/+$/, '');
        return {
            type: payload.type || 'coinrex.rex_signer.pairing',
            version: Number(payload.version || 2),
            code: payload.code || '',
            purpose: 'claim',
            api_base_url: normalizedPublicBaseUrl,
            base_url: normalizedPublicBaseUrl,
            dapp_name: payload.dapp_name || 'CoinRex',
            dapp_url: payload.dapp_url || browserBaseUrl.replace(/\/+$/, ''),
            network_slug: payload.network_slug || 'polygon-amoy',
            chain_id: Number(payload.chain_id || 80002),
            requested_duration_minutes: Number(payload.requested_duration_minutes || selectedDuration || 10),
            expires_at: payload.expires_at || '',
        };
    }

    function selectedAmount() {
        const value = Number(amountInput ? amountInput.value : 0);
        return Number.isFinite(value) ? Math.round(value * 100000000) / 100000000 : 0;
    }

    function remainingFromSession(session) {
        if (!session) {
            return 0;
        }
        const remainingSeconds = Number(session.remaining_seconds);
        if (Number.isFinite(remainingSeconds) && remainingSeconds >= 0) {
            return Math.floor(remainingSeconds);
        }
        const expiresAt = String(session.expires_at || '').trim();
        const expiryMs = expiresAt ? Date.parse(expiresAt.replace(' ', 'T')) : NaN;
        if (Number.isFinite(expiryMs)) {
            return Math.max(0, Math.floor((expiryMs - Date.now()) / 1000));
        }
        const expiryUnix = Number(session.expires_at_unix || 0);
        return expiryUnix > 0 ? Math.max(0, expiryUnix - Math.floor(Date.now() / 1000)) : 0;
    }

    function remainingNow() {
        if (activeSessionRemainingSeconds <= 0 || !activeSessionCountdownStartedAt) {
            return 0;
        }
        const elapsed = Math.floor((Date.now() - activeSessionCountdownStartedAt) / 1000);
        return Math.max(0, activeSessionRemainingSeconds - elapsed);
    }

    function countdownLabel(seconds) {
        const remaining = Math.max(0, Math.floor(Number(seconds || 0)));
        if (remaining <= 0) {
            return 'Session expired';
        }
        return 'Session expires in ' + Math.floor(remaining / 60) + 'm ' + String(remaining % 60).padStart(2, '0') + 's';
    }

    function renderCountdown() {
        const remaining = remainingNow();
        [modalCountdownText, landingCountdownText].forEach(function(element) {
            if (!element) {
                return;
            }
            element.hidden = activeSessionCount <= 0 && remaining <= 0;
            element.textContent = countdownLabel(remaining);
            element.classList.toggle('is-warning', remaining > 0 && remaining <= 120);
            element.classList.toggle('is-expired', remaining <= 0);
        });
        if (activeSessionCount > 0 && remaining <= 0 && !sessionExpiryRefreshQueued) {
            sessionExpiryRefreshQueued = true;
            refreshSessions().catch(function() {});
        }
    }

    function startCountdown() {
        window.clearInterval(countdownTimer);
        renderCountdown();
        countdownTimer = window.setInterval(renderCountdown, 1000);
    }

    function setProgressState(el, state) {
        if (!el) {
            return;
        }
        el.classList.toggle('is-active', state === 'active');
        el.classList.toggle('is-complete', state === 'complete');
    }

    function setClaimModalStep(step) {
        const previousStep = modalStep;
        modalStep = step;
        if (step !== 'loading') {
            modalLoadingMessage = '';
            modalLoadingDetail = '';
            modalLoadingProgressStep = 'duration';
        }
        if (step === 'amount' && previousStep !== 'amount' && amountInput && !amountInputTouched) {
            amountInput.value = availableBalanceValue > 0 ? availableBalanceValue.toFixed(8) : '';
        }
        if (step === 'duration') {
            setDurationOptionsLocked(false);
        }
        if (durationStep) durationStep.classList.toggle('is-active', step === 'duration');
        if (loadingStep) loadingStep.classList.toggle('is-active', step === 'loading');
        if (connectStep) connectStep.classList.toggle('is-active', step === 'connect');
        if (amountStep) amountStep.classList.toggle('is-active', step === 'amount');
        if (approvalStep) approvalStep.classList.toggle('is-active', step === 'approval');
        startSessionPolling();
        renderClaimModal();
    }

    function showModalLoading(message, detail, progressStep) {
        modalLoadingMessage = message || 'Preparing next step...';
        modalLoadingDetail = detail || 'This usually takes a moment.';
        modalLoadingProgressStep = progressStep || modalLoadingProgressStep || 'duration';
        setClaimModalStep('loading');
    }

    function delayedModalStep(message, detail, nextStep, delayMs, progressStep) {
        window.clearTimeout(modalLoadingTimer);
        showModalLoading(message, detail, progressStep || nextStep);
        modalLoadingTimer = window.setTimeout(function() {
            setClaimModalStep(nextStep);
        }, Number(delayMs || 700));
    }

    function setQrState(state, note) {
        if (qrPlaceholder) {
            const hadRenderedQr = qrPlaceholder.classList.contains('is-rendered');
            qrPlaceholder.hidden = state === 'qr-image';
            qrPlaceholder.classList.toggle('is-rendered', state === 'qr-svg');
            if (state !== 'qr-svg' && hadRenderedQr) {
                qrPlaceholder.innerHTML = '<span></span>';
            }
            const text = qrPlaceholder.querySelector('span');
            if (text) {
                if (state === 'loading') {
                    text.textContent = 'Preparing your QR code...';
                } else if (state === 'qr-svg' || state === 'qr-image') {
                    text.textContent = '';
                } else {
                    text.textContent = note || 'QR could not load. Please use the code below instead.';
                }
            }
        }
        if (qrImage) {
            qrImage.hidden = state !== 'qr-image';
            if (state !== 'qr-image' && state !== 'loading') {
                qrImage.removeAttribute('src');
            }
        }
    if (logoBadge) {
            logoBadge.classList.toggle('is-visible', state === 'qr-svg' || state === 'qr-image');
        }
        if (qrNote) {
            qrNote.textContent = '';
        }
    }

    function renderPairingQrPayload(payload, note) {
        const qrPayload = compactPairingPayload(payload);
        const qrText = JSON.stringify(qrPayload);
        const fallbackToImage = function() {
            if (!qrImage) {
                setQrState('empty', '');
                return;
            }
            qrImage.onload = function() {
                setQrState('qr-image', '');
            };
            qrImage.onerror = function() {
                setQrState('empty', '');
            };
            qrImage.src = pairingQrUrl + '?payload=' + encodeURIComponent(qrText);
        };

        if (window.CoinRexQRCode && typeof window.CoinRexQRCode.toString === 'function' && qrPlaceholder) {
            window.CoinRexQRCode.toString(qrText, {
                type: 'svg',
                width: 232,
                margin: 2,
                errorCorrectionLevel: 'M',
                color: {
                    dark: '#081120',
                    light: '#ffffff',
                },
            }).then(function(svg) {
                qrPlaceholder.hidden = false;
                qrPlaceholder.classList.add('is-rendered');
                qrPlaceholder.innerHTML = svg;
                if (qrImage) {
                    qrImage.hidden = true;
                    qrImage.removeAttribute('src');
                }
                if (logoBadge) {
                    logoBadge.classList.add('is-visible');
                }
            }).catch(fallbackToImage);
            return;
        }

        fallbackToImage();
    }

    function setDurationOptionsLocked(locked) {
        if (!durationOptions) {
            return;
        }
        durationOptions.querySelectorAll('[data-duration]').forEach(function(item) {
            item.disabled = Boolean(locked);
        });
    }

    function setPairingCopyState(code, copied) {
        currentPairingDisplayCode = code || '';
        if (!copyCodeButton) {
            return;
        }
        copyCodeButton.disabled = currentPairingDisplayCode === '';
        copyCodeButton.innerHTML = copied ? '<i class="fas fa-check"></i>' : '<i class="fas fa-copy"></i>';
        copyCodeButton.title = copied ? 'Copied' : 'Copy code';
        copyCodeButton.setAttribute('aria-label', copied ? 'Pairing code copied' : 'Copy pairing code');
    }

    function clearPairingExpiry() {
        window.clearInterval(pairingCountdownTimer);
        pairingCountdownTimer = null;
        pairingExpiresAtMs = 0;
        pairingExpired = false;
        if (pairingExpiryText) {
            pairingExpiryText.hidden = true;
            pairingExpiryText.classList.remove('is-warning', 'is-expired');
        }
    }

    function pairingRemainingSeconds() {
        if (!pairingExpiresAtMs) {
            return 0;
        }
        return Math.max(0, Math.ceil((pairingExpiresAtMs - Date.now()) / 1000));
    }

    function hasUsablePendingPairing() {
        return hasPendingPairingCode && !pairingExpired && pairingRemainingSeconds() > 0;
    }

    function renderPairingExpiry() {
        if (!pairingExpiryText) {
            return;
        }
        if (!hasPendingPairingCode || !pairingExpiresAtMs) {
            pairingExpiryText.hidden = true;
            pairingExpiryText.classList.remove('is-warning', 'is-expired');
            return;
        }
        const remaining = pairingRemainingSeconds();
        pairingExpiryText.hidden = false;
        pairingExpiryText.textContent = remaining > 0
            ? 'QR expires in ' + Math.floor(remaining / 60) + 'm ' + String(remaining % 60).padStart(2, '0') + 's'
            : 'QR expired. Create a new QR code.';
        pairingExpiryText.classList.toggle('is-warning', remaining > 0 && remaining <= 60);
        pairingExpiryText.classList.toggle('is-expired', remaining <= 0);
        if (remaining > 0 || pairingExpired) {
            return;
        }
        pairingExpired = true;
        hasPendingPairingCode = false;
        setPairingCopyState('', false);
        setQrState('empty', 'QR expired. Please create a new QR code.');
        if (pairingCode) {
            pairingCode.textContent = 'QR expired';
            pairingCode.classList.remove('is-pending', 'is-connected');
        }
        window.clearInterval(pairingCountdownTimer);
        pairingCountdownTimer = null;
        renderClaimModal();
    }

    function startPairingExpiry(seconds) {
        clearPairingExpiry();
        const ttl = Math.max(1, Number(seconds || 300));
        pairingExpiresAtMs = Date.now() + ttl * 1000;
        pairingExpired = false;
        renderPairingExpiry();
        pairingCountdownTimer = window.setInterval(renderPairingExpiry, 1000);
    }

    function resetPairingDraft() {
        if (activeSessionCount > 0 || hasUsablePendingPairing() || creatingPairing) {
            return;
        }
        hasPendingPairingCode = false;
        pairingGenerationFailed = false;
        clearPairingExpiry();
        if (pairingCode) {
            pairingCode.textContent = 'No code yet';
            pairingCode.classList.remove('is-pending', 'is-connected');
        }
        setPairingCopyState('', false);
        if (connectCopy) {
            connectCopy.textContent = 'Pick a session time, then create your QR code.';
        }
        setQrState('empty', 'Pick a session time to create your QR code.');
    }

    function renderLandingSessionCard(message) {
        const remaining = remainingNow();
        const isConnected = activeSessionCount > 0;
        const isExpired = !isConnected && (sessionInactiveMessage !== '' || (activeSessionRemainingSeconds > 0 && remaining <= 0));
        if (sessionCard) {
            sessionCard.classList.toggle('is-connected', isConnected);
            sessionCard.classList.toggle('is-expired', isExpired);
        }
        if (sessionTitle) {
            sessionTitle.textContent = isConnected
                ? 'RexLink is connected'
                : (isExpired ? 'Session expired' : 'No wallet connected');
        }
        if (sessionStatus) {
            sessionStatus.textContent = isConnected ? 'Connected' : (isExpired ? 'Expired' : 'Not connected');
        }
        if (sessionWallet) {
            sessionWallet.textContent = isConnected
                ? 'Connected to ' + shortAddress(activeWalletAddress)
                : (isExpired ? sessionInactiveMessage || 'This session has expired. Please connect again.' : 'Ready to pair with RexLink.');
        }
        if (sessionNote) {
            sessionNote.textContent = message || (isConnected
                ? 'You can continue claiming from this connected session.'
                : (isExpired ? 'Create a new session before you claim.' : (!currentClaimEligible ? 'Pairing is available, but claiming is locked until account review completes.' : 'Scan the QR or enter the 6 digit code.')));
        }
        if (sessionConnectButton) {
            sessionConnectButton.hidden = isConnected;
            sessionConnectButton.textContent = isExpired ? 'Connect again' : 'Connect RexLink';
        }
        if (sessionContinueButton) {
            sessionContinueButton.hidden = !isConnected;
        }
        if (sessionDisconnectButton) {
            sessionDisconnectButton.hidden = !isConnected || activeSessionId <= 0;
        }
        renderCountdown();
    }

    function renderBalanceLanding(message) {
        const stateLabel = hasOpenClaim ? 'Processing' : (currentClaimEligible ? 'Ready' : 'Locked');
        const statusText = message || (hasOpenClaim
            ? 'Waiting for your claim to be submitted.'
            : (currentClaimEligible ? 'Ready to be approved in RexLink.' : 'You are not ready to claim yet.'));
        if (landingAvailable) landingAvailable.textContent = formatRex(availableBalanceValue, 2);
        if (landingClaimed) landingClaimed.textContent = formatRex(claimedBalanceValue, 2);
        if (landingState) landingState.textContent = stateLabel;
        if (landingStatus) landingStatus.textContent = statusText;
        if (heroClaimState) heroClaimState.textContent = stateLabel;
        if (heroClaimNote) heroClaimNote.textContent = statusText;
        if (openButton) {
            openButton.disabled = !currentClaimEligible || hasOpenClaim || availableBalanceValue <= 0;
            openButton.textContent = hasOpenClaim ? 'Claim Processing' : (!currentClaimEligible ? 'Claim Locked' : 'Claim REX');
        }
        renderLandingSessionCard();
    }

    function validateAmount() {
        const amount = selectedAmount();
        let message = '';
        if (!currentClaimEligible) {
            message = 'You are not ready to claim yet. Please complete the required steps first.';
        } else if (hasOpenClaim) {
            message = 'Your claim is already being processed. Please wait a moment.';
        } else if (amount <= 0) {
            message = 'Please enter an amount greater than 0.';
        } else if (amount > availableBalanceValue) {
            message = 'This amount is higher than your available REX balance.';
        }
        if (amountAlert) {
            amountAlert.textContent = message || 'This amount is ready to be approved in RexLink.';
            amountAlert.classList.toggle('is-error', Boolean(message));
            amountAlert.classList.toggle('is-success', !message);
        }
        return !message;
    }

    function renderAmountStep() {
        if (modalWalletText) {
            modalWalletText.textContent = activeWalletAddress ? shortAddress(activeWalletAddress) : 'Connected';
        }
        if (availableHint) {
            availableHint.textContent = 'Available: ' + formatRex(availableBalanceValue, 2) + ' REX';
        }
        if (amountInput) {
            amountInput.max = String(availableBalanceValue);
            amountInput.disabled = !currentClaimEligible || hasOpenClaim || activeRequestId > 0 || availableBalanceValue <= 0;
            if (amountInput.value !== '' && Number(amountInput.value) > availableBalanceValue) {
                amountInput.value = availableBalanceValue > 0 ? availableBalanceValue.toFixed(8) : '0.00000000';
            }
        }
        if (maxButton) {
            maxButton.disabled = !currentClaimEligible || hasOpenClaim || activeRequestId > 0 || availableBalanceValue <= 0;
        }
        validateAmount();
    }

    function resultCopy() {
        if (modalResultState === 'success') {
            return ['Claim sent', 'Your claim request was sent successfully.', 'fa-check', 'success'];
        }
        if (modalResultState === 'claimed') {
            return ['Claim completed', 'Your REX rewards have been marked as claimed.', 'fa-check', 'success'];
        }
        if (modalResultState === 'rejected') {
            return ['Approval declined', approvalDecisionMessage || 'The approval was rejected in RexLink. You can try again anytime.', 'fa-xmark', 'error'];
        }
        if (modalResultState === 'rejection_received') {
            return ['Rejection received', approvalDecisionMessage || 'RexLink replied with a decline. We are updating the request status...', 'fa-spinner', 'loading'];
        }
        if (modalResultState === 'approval_received') {
            return ['Approval received', 'RexLink approved it. Getting your claim ready...', 'fa-spinner', 'loading'];
        }
        if (modalResultState === 'gas') {
            return ['Not enough POL', 'Please add a little POL for gas in RexLink, then try again.', 'fa-gas-pump', 'error'];
        }
        if (modalResultState === 'network') {
            return ['Claim problem', claimFailureMessage || 'We could not complete this claim request. Please try again in a moment.', 'fa-circle-exclamation', 'error'];
        }
        if (modalResultState === 'expired') {
            return ['Request expired', 'The approval window ended. Please send a new request to RexLink.', 'fa-clock', 'error'];
        }
        if (modalResultState === 'cancelled') {
            return ['Session ended', 'The request stopped because the RexLink session ended. Please connect again.', 'fa-ban', 'error'];
        }
        if (modalResultState === 'submitting') {
            return ['Approved', 'RexLink approved it. Sending your claim now...', 'fa-spinner', 'loading'];
        }
        return ['Waiting for approval', 'Your request was sent to RexLink. Keep the wallet app open.', 'fa-spinner', 'loading'];
    }

    function setApprovalStepState(element, state) {
        if (!element) {
            return;
        }
        element.classList.toggle('is-active', state === 'active');
        element.classList.toggle('is-complete', state === 'complete');
        element.classList.toggle('is-error', state === 'error');
    }

    function renderApprovalTimeline() {
        const terminalError = ['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState);
        const terminalSuccess = ['success', 'claimed'].includes(modalResultState);
        const approvalAccepted = ['approval_received', 'submitting'].includes(modalResultState);
        setApprovalStepState(approvalStepWaiting, terminalError || terminalSuccess || approvalAccepted || modalResultState === 'rejection_received' ? 'complete' : 'active');
        setApprovalStepState(approvalStepApproved, terminalError ? 'error' : (terminalSuccess || modalResultState === 'submitting' ? 'complete' : (approvalAccepted ? 'active' : '')));
        setApprovalStepState(approvalStepSubmitted, terminalError ? 'error' : (terminalSuccess ? 'complete' : (modalResultState === 'submitting' ? 'active' : '')));
    }

    function renderApprovalStep() {
        const copy = resultCopy();
        if (resultTitle) resultTitle.textContent = copy[0];
        if (resultMessage) resultMessage.textContent = copy[1];
        if (resultIcon) {
            resultIcon.className = 'claim-result-icon is-' + copy[3];
            resultIcon.innerHTML = '<i class="fas ' + copy[2] + '"></i>';
        }
        renderApprovalTimeline();
    }

    function renderClaimModal() {
        const isDuration = modalStep === 'duration';
        const isLoading = modalStep === 'loading';
        const isConnect = modalStep === 'connect';
        const isAmount = modalStep === 'amount';
        const isApproval = modalStep === 'approval';
        const loadingTarget = isLoading ? modalLoadingProgressStep : '';
        if (modalTitle) {
            modalTitle.textContent = isDuration || (isLoading && loadingTarget === 'duration')
                ? 'Session time'
                : ((isConnect || (isLoading && loadingTarget === 'connect')) ? 'Connect RexLink' : ((isAmount || (isLoading && loadingTarget === 'amount')) ? 'Choose amount' : 'Approve claim'));
        }
        setProgressState(progressDuration, isDuration || (isLoading && loadingTarget === 'duration') ? 'active' : 'complete');
        setProgressState(progressConnect, isConnect || (isLoading && loadingTarget === 'connect') ? 'active' : (isAmount || isApproval || (isLoading && ['amount', 'approval'].includes(loadingTarget)) ? 'complete' : ''));
        setProgressState(progressAmount, isAmount || (isLoading && loadingTarget === 'amount') ? 'active' : (isApproval || (isLoading && loadingTarget === 'approval') ? 'complete' : ''));
        setProgressState(progressApprove, isApproval || (isLoading && loadingTarget === 'approval') ? 'active' : '');
        if (modalFooter) {
            modalFooter.classList.toggle('is-hidden', isConnect);
        }
        if (primaryButton) {
            primaryButton.hidden = false;
            primaryButton.disabled = false;
            if (isDuration || isLoading) {
                primaryButton.hidden = true;
            } else if (isConnect) {
                primaryButton.hidden = true;
                primaryButton.textContent = creatingPairing
                    ? 'Creating QR...'
                    : (pairingExpired ? 'New QR' : (hasPendingPairingCode ? 'Refresh QR' : (pairingGenerationFailed ? 'Try Again' : 'Create QR')));
                primaryButton.disabled = creatingPairing || (!hasPendingPairingCode && !pairingGenerationFailed && !pairingExpired);
            } else if (isAmount) {
                primaryButton.textContent = 'Request Mobile Approval';
                primaryButton.disabled = !validateAmount();
            } else if (['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState)) {
                primaryButton.textContent = 'Try Again';
            } else if (['success', 'claimed'].includes(modalResultState)) {
                primaryButton.textContent = 'Close';
            } else {
                primaryButton.textContent = 'Waiting...';
                primaryButton.disabled = true;
            }
        }
        if (secondaryButton) {
            secondaryButton.hidden = isConnect;
            secondaryButton.textContent = isApproval && ['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState) ? 'Change Amount' : 'Close';
        }
        if (inlineQrButton) {
            inlineQrButton.hidden = !isConnect;
            inlineQrButton.textContent = creatingPairing
                ? 'Creating QR...'
                : (pairingExpired ? 'New QR' : (hasPendingPairingCode ? 'Refresh QR' : (pairingGenerationFailed ? 'Try Again' : 'Create QR')));
            inlineQrButton.disabled = creatingPairing || (!hasPendingPairingCode && !pairingGenerationFailed && !pairingExpired);
        }
        if (durationCopy) {
            durationCopy.textContent = creatingPairing
                ? 'Creating your RexLink QR. This usually takes a moment.'
                : 'Pick how long RexLink should stay connected for this claim.';
        }
        if (durationOptions) {
            durationOptions.querySelectorAll('[data-duration]').forEach(function(item) {
                item.classList.toggle('is-active', Number(item.getAttribute('data-duration') || 0) === Number(selectedDuration));
            });
            if (isDuration) {
                setDurationOptionsLocked(false);
            }
        }
        if (isLoading) {
            if (loadingTitle) {
                loadingTitle.textContent = modalLoadingMessage || 'Preparing next step...';
            }
            if (loadingText) {
                loadingText.textContent = modalLoadingDetail || 'This usually takes a moment.';
            }
        }
        if (isConnect) {
            setDurationOptionsLocked(creatingPairing || hasPendingPairingCode || activeSessionCount > 0);
            if (connectCopy) {
                connectCopy.textContent = hasPendingPairingCode
                    ? 'Scan the QR or enter the 6 digit code in RexLink.'
                    : (pairingExpired ? 'This QR expired. Create a new QR code to continue.' : (creatingPairing ? 'Creating your QR code...' : 'Choose a session time first.'));
            }
        }
        if (isAmount) {
            renderAmountStep();
        }
        if (isApproval) {
            renderApprovalStep();
        }
    }

    function openClaimModal(trackOnly) {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (trackOnly || hasOpenClaim) {
            modalResultState = hasOpenClaim ? 'submitting' : 'waiting';
            setClaimModalStep('approval');
            refreshOverview().catch(function() {});
            return;
        }
        amountInputTouched = false;
        showModalLoading('Checking RexLink session...', 'Looking for an active wallet connection.');
        refreshSessions().then(function() {
            if (activeSessionCount > 0 && activeWalletAddress) {
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 700);
                return;
            }
            if (hasUsablePendingPairing()) {
                delayedModalStep('QR already ready.', 'Opening your RexLink QR code...', 'connect', 500, 'connect');
                return;
            }
            resetPairingDraft();
            setClaimModalStep('duration');
        }).catch(function(error) {
            setQrState('empty', error.message || 'Could not start RexLink pairing.');
            setClaimModalStep('duration');
        });
    }

    function closeClaimModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    async function refreshOverview() {
        const response = await fetch(overviewUrl, { credentials: 'same-origin' });
        const data = await response.json();
        if (!data.success) {
            return;
        }
        availableBalanceValue = Number(data.balances.available || 0);
        claimedBalanceValue = Number(data.balances.claimed || 0);
        currentClaimEligible = !!data.claim_eligibility.eligible;
        hasOpenClaim = !!data.open_claim;
        if (landingLocked) landingLocked.textContent = formatRex(Number(data.balances.locked || 0), 2);
        if (landingPending) landingPending.textContent = formatRex(Number(data.balances.pending || 0), 2);
        renderBalanceLanding(data.claim_eligibility.message || '');
        renderClaimModal();
    }

    async function refreshSessions() {
        if (sessionRefreshInFlight) {
            return null;
        }
        sessionRefreshInFlight = true;
        try {
        const response = await fetch(sessionsUrl, { credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Could not load RexLink sessions.');
        }
        const previousSessionCount = activeSessionCount;
        activeSessionCount = Number(data.active_session_count || 0);
        const sessionState = String(data.session_state || '').toLowerCase();
        const activeSession = data.current_session || (data.sessions || []).find(function(session) {
            return session.status === 'active' && Number(session.remaining_seconds || 0) > 0;
        }) || null;
        activeSessionId = activeSession ? Number(activeSession.id || 0) : 0;
        activeWalletAddress = activeSession && activeSession.wallet_address ? String(activeSession.wallet_address) : '';
        if (activeSessionCount > 0 && activeWalletAddress) {
            hasPendingPairingCode = false;
            pairingGenerationFailed = false;
            pairingExpired = false;
            clearPairingExpiry();
            sessionInactiveMessage = '';
            sessionExpiryRefreshQueued = false;
            activeSessionRemainingSeconds = remainingFromSession(activeSession);
            activeSessionCountdownStartedAt = Date.now();
            startCountdown();
            setQrState('empty', 'Wallet connected. Continue to claim amount.');
            if (pairingCode) {
                pairingCode.textContent = 'Connected';
                pairingCode.classList.add('is-connected');
                pairingCode.classList.remove('is-pending');
            }
            setPairingCopyState('', false);
            if (!modal.hidden && ['duration', 'connect', 'loading'].includes(modalStep)) {
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 700);
            }
        } else if (['expired', 'revoked', 'none'].includes(sessionState)) {
            if (previousSessionCount > 0 || sessionState !== 'none' || activeRequestId > 0 || (modal && !modal.hidden && !['duration', 'connect', 'loading'].includes(modalStep))) {
                const message = sessionState === 'expired'
                    ? 'This session has expired. Please connect again to continue.'
                    : 'Session disconnected. Please connect again to continue.';
                resetModalAfterSessionLoss(message);
                return data;
            } else {
                activeSessionId = 0;
                activeWalletAddress = '';
                sessionInactiveMessage = '';
                activeSessionRemainingSeconds = 0;
                activeSessionCountdownStartedAt = 0;
            }
        }
        renderLandingSessionCard();
        renderClaimModal();
        return data;
        } finally {
            sessionRefreshInFlight = false;
        }
    }

    async function createPairing() {
        if ((activeSessionCount > 0 && activeWalletAddress) || creatingPairing) {
            return;
        }
        stopSessionPolling();
        window.clearTimeout(modalLoadingTimer);
        creatingPairing = true;
        hasPendingPairingCode = false;
        pairingGenerationFailed = false;
        clearPairingExpiry();
        showModalLoading('Creating RexLink QR...', 'Preparing your secure pairing code.');
        if (pairingCode) {
            pairingCode.textContent = 'Creating code...';
            pairingCode.classList.remove('is-pending', 'is-connected');
        }
        setPairingCopyState('', false);
        if (connectCopy) {
            connectCopy.textContent = 'Creating your QR code...';
        }
        setQrState('loading', 'Preparing your secure pairing code...');
        renderClaimModal();
        try {
            const data = await postJson(createPairingUrl, {
                purpose: 'claim',
                duration_minutes: selectedDuration,
                dapp_name: 'CoinRex',
                dapp_url: (publicApiBaseUrl || browserBaseUrl).replace(/\/+$/, ''),
                network_slug: 'polygon-amoy',
                network_name: 'Polygon Amoy',
                chain_id: 80002
            });
            if (!data.success) {
                throw new Error(data.message || 'Pairing code could not be created.');
            }
        if (data.already_connected) {
                activeSessionCount = 1;
                activeSessionId = Number(data.session && data.session.id || 0);
                activeWalletAddress = data.session && data.session.wallet_address ? String(data.session.wallet_address) : '';
                sessionInactiveMessage = '';
                sessionExpiryRefreshQueued = false;
                activeSessionRemainingSeconds = remainingFromSession(data.session || null);
                activeSessionCountdownStartedAt = Date.now();
                hasPendingPairingCode = false;
                pairingGenerationFailed = false;
                clearPairingExpiry();
                startCountdown();
                renderLandingSessionCard();
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 700);
                return;
            }
            hasPendingPairingCode = true;
            pairingGenerationFailed = false;
            const displayCode = data.display_code || 'REX code ready';
            if (pairingCode) {
                pairingCode.textContent = displayCode;
                pairingCode.classList.add('is-pending');
                pairingCode.classList.remove('is-connected');
            }
            setPairingCopyState(data.display_code || '', false);
            startPairingExpiry(Number(data.expires_in_seconds || 300));
            startSessionPolling();
            if (data.qr_payload) {
                const qrPayload = Object.assign({}, data.qr_payload || {}, {
                    purpose: 'claim',
                    api_base_url: String(data.qr_payload.api_base_url || rexlinkApiBaseUrl).replace(/\/+$/, ''),
                    base_url: String(data.qr_payload.base_url || rexlinkApiBaseUrl).replace(/\/+$/, ''),
                    dapp_url: (publicApiBaseUrl || browserBaseUrl).replace(/\/+$/, '')
                });
                renderPairingQrPayload(
                    qrPayload,
                    ''
                );
            }
            delayedModalStep('Creating RexLink QR...', 'Your QR is almost ready.', 'connect', 700);
        } catch (error) {
            hasPendingPairingCode = false;
            pairingGenerationFailed = true;
            clearPairingExpiry();
            if (pairingCode) {
                pairingCode.textContent = 'No code yet';
                pairingCode.classList.remove('is-pending', 'is-connected');
            }
            setPairingCopyState('', false);
            setQrState('empty', error.message || 'Pairing code could not be created.');
            setClaimModalStep('connect');
        } finally {
            creatingPairing = false;
            startSessionPolling();
            renderClaimModal();
        }
    }

    function clearSessionState(message) {
        activeSessionCount = 0;
        activeSessionId = 0;
        activeWalletAddress = '';
        hasPendingPairingCode = false;
        pairingGenerationFailed = false;
        clearPairingExpiry();
        sessionInactiveMessage = message || 'Session disconnected. Please connect again to continue.';
        activeSessionRemainingSeconds = 0;
        activeSessionCountdownStartedAt = 0;
        sessionExpiryRefreshQueued = false;
        amountInputTouched = false;
        if (pairingCode) {
            pairingCode.textContent = 'No code yet';
            pairingCode.classList.remove('is-pending', 'is-connected');
        }
        setPairingCopyState('', false);
        setQrState('empty', 'Connect again to create a new RexLink session.');
        renderLandingSessionCard();
        renderClaimModal();
    }

    function resetModalAfterSessionLoss(message) {
        const wasAwaitingApproval = activeRequestId > 0 || modalStep === 'approval';
        stopApprovalPolling();
        activeRequestId = 0;
        clearSessionState(message || 'Session disconnected. Please connect again to continue.');
        modalResultState = wasAwaitingApproval ? 'cancelled' : 'waiting';
        if (modal && !modal.hidden) {
            setClaimModalStep('duration');
        } else {
            renderClaimModal();
        }
    }

    async function disconnectSignerSession() {
        if (!activeSessionId) {
            resetModalAfterSessionLoss('No active RexLink session found.');
            return;
        }
        const data = await postJson(revokeSessionUrl, {
            session_id: activeSessionId,
            reason: 'Revoked from claim checkout page',
        });
        if (!data.success) {
            throw new Error(data.message || 'Could not disconnect RexLink.');
        }
        resetModalAfterSessionLoss('Session disconnected. Please connect again to continue.');
        refreshSessions().catch(function() {});
    }

    async function requestClaimApproval() {
        if (!validateAmount() || activeRequestId > 0) {
            return;
        }
        modalResultState = 'waiting';
        approvalDecisionMessage = '';
        claimFailureMessage = '';
        showModalLoading('Sending approval request...', 'Preparing the RexLink approval screen.', 'approval');
        const amount = selectedAmount();
        const data = await postJson(createClaimApprovalUrl, { claim_amount: amount.toFixed(8) }, { timeoutMs: 8000 });
        if (!data.success) {
            modalResultState = classifyFailure(data.message || 'Claim approval could not be created.');
            setClaimModalStep('approval');
            return;
        }
        activeRequestId = Number(data.request_id || 0);
        if (resultMessage) {
            resultMessage.textContent = 'Request sent to RexLink. Keep the wallet open.';
        }
        refreshSessions().catch(function() {});
        startApprovalPolling();
        pollApproval().catch(function() {});
        delayedModalStep('Request sent to RexLink.', 'Waiting for approval in your wallet...', 'approval', 500, 'approval');
    }

    function classifyFailure(message) {
        const text = String(message || '').toLowerCase();
        claimFailureMessage = String(message || '').trim() || 'Claim approval could not be completed.';
        if (/gas|pol|fund|balance|fee/.test(text)) {
            return 'gas';
        }
        return 'network';
    }

    async function pollApproval() {
        if (!activeRequestId || approvalPollInFlight) {
            return;
        }
        approvalPollInFlight = true;
        try {
        const response = await fetch(approvalStatusUrl + '?request_id=' + encodeURIComponent(String(activeRequestId)), { credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            return;
        }
        const request = data.approval_request || null;
        if (!request) {
            return;
        }
        if (request.wallet_address) {
            activeWalletAddress = String(request.wallet_address);
        }
            if (request.status === 'approved') {
                const result = request.result || {};
                if (result.tx_status === 'failed') {
                    stopApprovalPolling();
                    activeRequestId = 0;
                hasOpenClaim = false;
                modalResultState = classifyFailure(result.tx_error || 'Claim transaction could not be submitted. Add POL for gas, then try again.');
                renderClaimModal();
                    await refreshOverview();
                    return;
                }
            if (result.tx_status === 'confirmed' || result.claim_snapshot_status === 'used') {
                stopApprovalPolling();
                activeRequestId = 0;
                modalResultState = 'claimed';
                renderClaimModal();
                await refreshOverview();
                return;
            }
            if (request.tx_hash || result.tx_hash || result.tx_status === 'submitted') {
                hasOpenClaim = true;
                modalResultState = 'submitting';
                renderClaimModal();
                await refreshOverview();
                return;
            }
            hasOpenClaim = true;
            modalResultState = 'submitting';
            renderClaimModal();
        } else if (request.status === 'rejected') {
            stopApprovalPolling();
            activeRequestId = 0;
            approvalDecisionMessage = request.decision_note || '';
            modalResultState = 'rejected';
            renderClaimModal();
        } else if (request.status === 'expired') {
            stopApprovalPolling();
            activeRequestId = 0;
            modalResultState = 'expired';
            renderClaimModal();
        } else if (request.status === 'cancelled') {
            stopApprovalPolling();
            activeRequestId = 0;
            modalResultState = 'cancelled';
            renderClaimModal();
        }
        } finally {
            approvalPollInFlight = false;
        }
    }

    function startSessionPolling() {
        const wantsFastPairingPoll = modal && !modal.hidden && ['connect', 'loading'].includes(modalStep) && hasPendingPairingCode;
        const nextInterval = wantsFastPairingPoll ? 1000 : (realtimeConnected ? 12000 : 2000);
        if (sessionPollTimer && sessionPollIntervalMs === nextInterval) {
            return;
        }
        window.clearInterval(sessionPollTimer);
        sessionPollIntervalMs = nextInterval;
        sessionPollTimer = window.setInterval(function() {
            refreshSessions().catch(function() {});
        }, nextInterval);
    }

    function stopSessionPolling() {
        window.clearInterval(sessionPollTimer);
        sessionPollTimer = null;
        sessionPollIntervalMs = 0;
    }

    function startApprovalPolling() {
        window.clearInterval(approvalPollTimer);
        approvalPollTimer = window.setInterval(function() {
            pollApproval().catch(function() {});
        }, 1000);
        pollApproval().catch(function() {});
    }

    function stopApprovalPolling() {
        window.clearInterval(approvalPollTimer);
        approvalPollTimer = null;
    }

    function refreshPairingFromModal() {
        refreshSessions().then(function() {
            if (activeSessionCount <= 0 || !activeWalletAddress) {
                return createPairing();
            }
            return null;
        }).catch(function() {});
    }

    function realtimeUrlWithToken(wsUrl, token) {
        const separator = String(wsUrl).includes('?') ? '&' : '?';
        return String(wsUrl) + separator + 'token=' + encodeURIComponent(token);
    }

    function scheduleRealtimeReconnect() {
        window.clearTimeout(realtimeReconnectTimer);
        realtimeReconnectTimer = window.setTimeout(function() {
            connectRealtime().catch(function() {});
        }, realtimeReconnectDelay);
        realtimeReconnectDelay = Math.min(realtimeReconnectDelay * 2, 15000);
    }

    async function handleRealtimeEvent(event) {
        const type = String(event.type || '');
        if (type === 'realtime.ready' || type === 'pong') {
            return;
        }
        if (realtimeDebug && event.created_at_ms && window.console && console.debug) {
            console.debug('[CoinRex realtime]', type, 'ageMs=', Math.max(0, Date.now() - Number(event.created_at_ms || 0)));
        }
        if (type === 'session.connected') {
            if (modal && !modal.hidden && ['duration', 'connect', 'loading'].includes(modalStep)) {
                showModalLoading('Wallet connected.', 'Preparing claim amount...', 'amount');
            }
            await refreshSessions();
            return;
        }
        if (type === 'pairing.rejected') {
            const message = event.message || (event.payload && event.payload.message) || 'RexLink pairing was rejected. Please use the wallet linked to this CoinRex account.';
            resetModalAfterSessionLoss(message);
            return;
        }
        if (type === 'approval.intent') {
            const payload = event.payload || {};
            if (Number(payload.request_id || 0) === Number(activeRequestId || 0)) {
                const decision = String(payload.decision || '').toLowerCase();
                if (decision === 'approved') {
                    modalResultState = 'approval_received';
                    renderClaimModal();
                } else if (decision === 'rejected') {
                    approvalDecisionMessage = 'The request was rejected in RexLink.';
                    modalResultState = 'rejected';
                    activeRequestId = 0;
                    stopApprovalPolling();
                    renderClaimModal();
                    refreshOverview().catch(function() {});
                }
            }
            return;
        }
        if (type === 'approval.updated') {
            const payload = event.payload || {};
            if (Number(payload.request_id || 0) === Number(activeRequestId || 0)) {
                const status = String(payload.status || '').toLowerCase();
                if (status === 'approved') {
                    modalResultState = payload.has_result ? 'approval_received' : 'submitting';
                    renderClaimModal();
                } else if (status === 'rejected') {
                    approvalDecisionMessage = payload.decision_note || '';
                    modalResultState = 'rejected';
                    activeRequestId = 0;
                    stopApprovalPolling();
                    renderClaimModal();
                }
            }
        }
        if (type === 'approval.created' || type === 'approval.updated' || type === 'approval.cancelled' || type === 'claim.tx.updated') {
            if (activeRequestId > 0) {
                await pollApproval();
            }
            await refreshOverview();
            return;
        }
        if (type === 'session.revoked' || type === 'session.expired') {
            await refreshSessions();
            if (activeRequestId > 0) {
                await pollApproval();
            }
        }
    }

    async function connectRealtime() {
        if (!('WebSocket' in window) || (realtimeSocket && [WebSocket.CONNECTING, WebSocket.OPEN].includes(realtimeSocket.readyState))) {
            return;
        }

        const response = await fetch(realtimeAuthUrl, { credentials: 'include' });
        const data = await response.json();
        if (!data.success || !data.ws_url || !data.token) {
            throw new Error(data.message || 'Realtime auth failed.');
        }

        realtimeSocket = new WebSocket(realtimeUrlWithToken(data.ws_url, data.token));
        realtimeSocket.addEventListener('open', function() {
            realtimeConnected = true;
            realtimeReconnectDelay = 1000;
            startSessionPolling();
            if (approvalPollTimer) {
                startApprovalPolling();
            }
            window.clearInterval(realtimePingTimer);
            realtimePingTimer = window.setInterval(function() {
                if (realtimeSocket && realtimeSocket.readyState === WebSocket.OPEN) {
                    realtimeSocket.send(JSON.stringify({ type: 'ping' }));
                }
            }, 25000);
            refreshSessions().catch(function() {});
            if (activeRequestId > 0) {
                pollApproval().catch(function() {});
            }
        });
        realtimeSocket.addEventListener('message', function(message) {
            try {
                handleRealtimeEvent(JSON.parse(message.data)).catch(function() {});
            } catch (error) {}
        });
        realtimeSocket.addEventListener('close', function() {
            realtimeConnected = false;
            window.clearInterval(realtimePingTimer);
            refreshSessions().catch(function() {});
            if (activeRequestId > 0) {
                pollApproval().catch(function() {});
            }
            startSessionPolling();
            if (approvalPollTimer) {
                startApprovalPolling();
            }
            scheduleRealtimeReconnect();
        });
        realtimeSocket.addEventListener('error', function() {
            realtimeConnected = false;
            refreshSessions().catch(function() {});
            if (activeRequestId > 0) {
                pollApproval().catch(function() {});
            }
        });
    }

    openButton?.addEventListener('click', function() {
        openClaimModal(false);
    });
    sessionConnectButton?.addEventListener('click', function() {
        openClaimModal(false);
    });
    sessionContinueButton?.addEventListener('click', function() {
        if (hasOpenClaim || activeRequestId > 0) {
            openClaimModal(true);
            return;
        }
        openClaimModal(false);
    });
    sessionDisconnectButton?.addEventListener('click', function() {
        disconnectSignerSession().catch(function(error) {
            sessionInactiveMessage = error.message || 'Could not disconnect RexLink.';
            renderLandingSessionCard(sessionInactiveMessage);
        });
    });
    trackButton?.addEventListener('click', function() {
        openClaimModal(true);
    });
    closeButton?.addEventListener('click', closeClaimModal);
    backdrop?.addEventListener('click', closeClaimModal);
    secondaryButton?.addEventListener('click', function() {
        if (modalStep === 'approval' && ['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState)) {
            setClaimModalStep('amount');
            return;
        }
        closeClaimModal();
    });
    primaryButton?.addEventListener('click', function() {
        if (modalStep === 'connect') {
            refreshPairingFromModal();
            return;
        }
        if (modalStep === 'amount') {
            requestClaimApproval().catch(function(error) {
                modalResultState = classifyFailure(error.message || 'Claim approval could not be created.');
                setClaimModalStep('approval');
            });
            return;
        }
        if (modalStep === 'approval' && ['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState)) {
            activeRequestId = 0;
            setClaimModalStep('amount');
            return;
        }
        closeClaimModal();
    });
    inlineQrButton?.addEventListener('click', refreshPairingFromModal);
    durationOptions?.addEventListener('click', function(event) {
        const target = event.target.closest('[data-duration]');
        if (!target || creatingPairing || hasPendingPairingCode || (activeSessionCount > 0 && activeWalletAddress)) {
            return;
        }
        selectedDuration = Number(target.getAttribute('data-duration') || 10);
        durationOptions.querySelectorAll('[data-duration]').forEach(function(item) {
            item.classList.toggle('is-active', item === target);
        });
        createPairing().catch(function(error) {
            pairingGenerationFailed = true;
            setQrState('empty', error.message || 'Pairing code could not be created.');
            setClaimModalStep('connect');
        });
    });
    maxButton?.addEventListener('click', function() {
        if (amountInput) {
            amountInput.value = availableBalanceValue > 0 ? availableBalanceValue.toFixed(8) : '0.00000000';
            amountInputTouched = true;
        }
        renderClaimModal();
    });
    copyCodeButton?.addEventListener('click', function() {
        const code = String(currentPairingDisplayCode || '').trim();
        if (!code) {
            return;
        }
        const fallbackCopy = function() {
            const textarea = document.createElement('textarea');
            textarea.value = code;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (!copied) {
                throw new Error('Copy failed.');
            }
        };
        const copyPromise = navigator.clipboard && window.isSecureContext
            ? navigator.clipboard.writeText(code)
            : new Promise(function(resolve, reject) {
                try {
                    fallbackCopy();
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        copyPromise.then(function() {
            setPairingCopyState(code, true);
            window.setTimeout(function() {
                setPairingCopyState(code, false);
            }, 1800);
        }).catch(function() {});
    });
    amountInput?.addEventListener('input', function() {
        amountInputTouched = true;
        renderClaimModal();
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeClaimModal();
        }
    });

    renderBalanceLanding();
    refreshOverview().catch(function() {});
    refreshSessions().catch(function() {}).finally(startSessionPolling);
    connectRealtime().catch(function() {
        scheduleRealtimeReconnect();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
