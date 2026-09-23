<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

function linkWalletB64Url($value)
{
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function linkWalletNodeActorToken($user_id)
{
    $secret = (string) (getenv('COINREX_REALTIME_SECRET') ?: (getenv('COINREX_ENCRYPTION_KEY') ?: (getenv('COINREX_CSRF_KEY') ?: 'coinrex-dev-realtime-secret')));
    $payload = linkWalletB64Url(json_encode([
        'user_id' => (int) $user_id,
        'iat' => time(),
        'exp' => time() + 900,
        'scope' => 'review_pairing',
    ], JSON_UNESCAPED_SLASHES));
    $signature = linkWalletB64Url(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $signature;
}

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
$user = getCurrentUser();
$user = getUserById((int) $user['id']) ?: $user;

$wallet_address = strtolower(trim((string) ($user['wallet_address'] ?? '')));
$wallet_verified_at = (string) ($user['wallet_verified_at'] ?? '');
$wallet_linked = $wallet_address !== '' && preg_match('/^0x[a-f0-9]{40}$/', $wallet_address);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'reset_wallet') {
        setFlashMessage('dashboard_success', 'Wallet changes are handled by CoinRex Support. Please contact support if you need to change your linked wallet.');
        redirect(BASE_URL . '/public/contact.php?subject=Wallet%20reset%20request');
    }
}
$page_title = 'Link Wallet - ' . SITE_NAME;

$link_wallet_actor_token = linkWalletNodeActorToken((int) ($user['id'] ?? 0));

// Capture session-backed values before releasing the session file lock so the
// browser's pairing/polling requests never block behind this page render.
$page_csrf_token = appCsrfToken();
@session_write_close();

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rexlink-auth.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/rexlink-auth.css'); ?>">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/link-wallet.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/link-wallet.css'); ?>">

<main class="link-wallet-main">
    <div class="link-wallet-shell">
        <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="link-wallet-back">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <section class="link-wallet-hero">
            <div>
                <span class="link-wallet-tag"><i class="fas fa-link"></i> RexLink Wallet</span>
                <h1>Manage your wallet</h1>
                <p>Link your RexLink wallet to receive rewards, participate in review eligibility checks, and prepare for claims.</p>
            </div>
            <div class="link-wallet-status-box">
                <span>Wallet status</span>
                <strong class="link-wallet-status-text">
                    <?php if ($wallet_linked): ?>
                        <span class="link-wallet-status-dot is-linked"></span> Linked
                    <?php else: ?>
                        <span class="link-wallet-status-dot"></span> Not linked
                    <?php endif; ?>
                </strong>
                <p class="link-wallet-note">
                    <?php echo $wallet_linked
                        ? 'Wallet verified and ready for rewards.'
                        : 'Connect a RexLink wallet to unlock wallet features.'; ?>
                </p>
            </div>
        </section>

        <section class="link-wallet-card <?php echo $wallet_linked ? 'is-linked' : ''; ?>">
            <div class="link-wallet-card-head">
                <div class="link-wallet-card-title">
                    <span class="link-wallet-card-icon">
                        <?php if ($wallet_linked): ?>
                            <i class="fas fa-wallet"></i>
                        <?php else: ?>
                            <i class="fas fa-plug"></i>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong><?php echo $wallet_linked ? 'Linked wallet' : 'No wallet linked'; ?></strong>
                        <small>
                            <?php echo $wallet_linked
                                ? 'This wallet is linked to your CoinRex account.'
                                : 'Link a RexLink wallet to connect your account.'; ?>
                        </small>
                    </div>
                </div>
                <?php if ($wallet_linked): ?>
                    <span class="link-wallet-badge is-verified">
                        <i class="fas fa-circle-check"></i> Verified
                    </span>
                <?php else: ?>
                    <span class="link-wallet-badge">
                        <i class="fas fa-clock"></i> Not linked
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($wallet_linked): ?>
                <div class="link-wallet-address-row">
                    <span class="link-wallet-address-label">Wallet address</span>
                    <div class="link-wallet-address-box">
                        <code class="link-wallet-address" id="linkedWalletAddress"><?php echo htmlspecialchars($wallet_address, ENT_QUOTES, 'UTF-8'); ?></code>
                        <button type="button" class="link-wallet-copy" data-copy-text="<?php echo htmlspecialchars($wallet_address, ENT_QUOTES, 'UTF-8'); ?>" title="Copy wallet address" aria-label="Copy wallet address">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <?php if ($wallet_verified_at !== ''): ?>
                    <p class="link-wallet-verified-note">
                        <i class="fas fa-shield-halved"></i>
                        Verified on <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($wallet_verified_at)), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="link-wallet-empty">
                    <span class="link-wallet-empty-icon">🦁</span>
                    <strong>Connect your RexLink wallet</strong>
                    <p>Scan the QR code or enter the 6-digit pairing code in the RexLink app to securely link your wallet.</p>
                </div>
            <?php endif; ?>

            <div class="link-wallet-actions">
                <?php if ($wallet_linked): ?>
                    <a class="link-wallet-btn-reset" href="<?php echo BASE_URL; ?>/public/contact.php?subject=Wallet%20reset%20request">
                        <i class="fas fa-headset"></i>
                        Contact Support to Change Wallet
                    </a>
                <?php else: ?>
                    <button type="button" class="link-wallet-btn-link" id="linkWalletPairButton">
                        <i class="fas fa-qrcode"></i>
                        Link Wallet with RexLink
                    </button>
                <?php endif; ?>
            </div>
            <div class="link-wallet-faq">
                <div class="link-wallet-faq-item">
                    <i class="fas fa-mobile-screen-button"></i>
                    <div>
                        <strong>What is RexLink?</strong>
                        <p>RexLink is the secure mobile companion app for CoinRex. It keeps your private keys on your phone and lets you approve actions with one tap.</p>
                    </div>
                </div>
                <div class="link-wallet-faq-item">
                    <i class="fas fa-shield-halved"></i>
                    <div>
                        <strong>Is linking safe?</strong>
                        <p>Your wallet address is stored on your CoinRex account. No private keys ever leave the RexLink app, and you stay in control of every approval.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- RexLink pairing modal -->
<div class="rexlink-modal" id="rexLinkModal" role="dialog" aria-modal="true" aria-labelledby="rexLinkModalTitle" hidden>
    <div class="rexlink-backdrop" id="rexLinkModalBackdrop"></div>
    <div class="rexlink-dialog">
        <div class="rexlink-head">
            <div>
                <span class="rexlink-tag"><i class="fas fa-link"></i> RexLink</span>
                <h3 id="rexLinkModalTitle">Link your wallet</h3>
            </div>
            <button type="button" class="rexlink-close" id="rexLinkModalClose" aria-label="Close RexLink wallet linking">&times;</button>
        </div>
        <div class="rexlink-progress" aria-label="RexLink wallet linking progress">
            <span id="rexLinkProgressDuration" class="is-active">1. Scan QR</span>
            <span id="rexLinkProgressSuccess">2. Connected</span>
        </div>
        <div class="rexlink-body">
            <section class="rexlink-step is-active" id="rexLinkQrStep">
                <div class="rexlink-link-grid">
                    <div class="rexlink-copy">
                        <div class="rexlink-link-title">
                            <div>
                                <h4>Link this browser</h4>
                            </div>
                            <div class="rexlink-countdown" id="rexLinkCountdown">Waiting for code</div>
                        </div>
                        <p class="rexlink-session-note" id="rexLinkSessionNote">Your wallet address will be linked to your CoinRex account.</p>
                        <ul class="rexlink-guide">
                            <li><i class="fas fa-mobile-screen-button"></i><span>Open RexLink app.</span></li>
                            <li><i class="fas fa-qrcode"></i><span>Scan QR or enter this 6 digit code.</span></li>
                            <li><i class="fas fa-wallet"></i><span>Approve the link to save your wallet.</span></li>
                        </ul>
                        <div class="rexlink-link-actions">
                            <button type="button" class="rexlink-primary" id="rexLinkPrimaryButton" hidden>Generate New QR</button>
                        </div>
                    </div>
                    <div class="rexlink-qr-card">
                        <div class="rexlink-qr-stage">
                            <div class="rexlink-qr-placeholder" id="rexLinkQrPlaceholder" aria-label="RexLink pairing QR"></div>
                            <img id="rexLinkQrImage" alt="RexLink pairing QR" hidden>
                            <span class="rexlink-qr-logo-badge" id="rexLinkQrLogoBadge" aria-hidden="true">
                                <img src="<?php echo ASSETS_URL; ?>/images/favicon.png" alt="">
                            </span>
                        </div>
                        <div class="rexlink-code-row">
                            <strong class="rexlink-code" id="rexLinkPairingCode">No code yet</strong>
                            <button type="button" class="rexlink-copy-button" id="rexLinkCopyCodeButton" title="Copy code" aria-label="Copy RexLink code" disabled>
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <p class="rexlink-status" id="rexLinkStatus">Waiting for RexLink.</p>
            </section>

            <section class="rexlink-step" id="rexLinkSuccessStep">
                <div class="rexlink-success">
                    <div>
                        <div class="rexlink-success-icon"><i class="fas fa-check"></i></div>
                        <h4>Wallet linked</h4>
                        <p id="rexLinkSuccessMessage">Your wallet is now linked to CoinRex.</p>
                        <p class="rexlink-session-note" id="rexLinkSuccessCountdown">RexLink session is active.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="<?php echo ASSETS_URL; ?>/js/qrcode-browser.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/qrcode-browser.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-pairing.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-pairing.js'); ?>"></script>
<script>
<?php
$rexlink_link_api_base = BASE_URL;
$rexlink_link_persist_url = BASE_URL . '/api/link_wallet_session.php';
?>
window.CoinRexLinkWalletConfig = {
    rexlinkApiBaseUrl: <?php echo json_encode($rexlink_link_api_base); ?>,
    baseUrl: window.location.origin + <?php echo json_encode(BASE_URI); ?>,
    browserBaseUrl: window.location.origin + <?php echo json_encode(BASE_URI); ?>,
    redirectAfterLink: window.location.origin + <?php echo json_encode(BASE_URI . '/public/dashboard.php?wallet=linked'); ?>,
    persistUrl: window.location.origin + <?php echo json_encode(BASE_URI . '/api/link_wallet_session.php'); ?>,
    csrfToken: <?php echo json_encode($page_csrf_token); ?>,
    webActorToken: <?php echo json_encode($link_wallet_actor_token); ?>,
    walletAlreadyLinked: <?php echo $wallet_linked ? 'true' : 'false' ?>
};
</script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-sdk.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-sdk.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-link.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-link.js'); ?>"></script>
<script>
document.querySelectorAll('.link-wallet-copy[data-copy-text]').forEach(function(button) {
    button.addEventListener('click', async function() {
        const text = this.dataset.copyText || '';
        if (!text) return;
        const originalHtml = this.innerHTML;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const temp = document.createElement('input');
                temp.value = text;
                temp.setAttribute('readonly', 'readonly');
                temp.style.position = 'fixed';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                temp.setSelectionRange(0, temp.value.length);
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
            this.innerHTML = '<i class="fas fa-check"></i>';
            this.classList.add('is-copied');
        } catch (error) {
            this.innerHTML = '<i class="fas fa-triangle-exclamation"></i>';
        }
        window.setTimeout(function() {
            this.innerHTML = originalHtml;
            this.classList.remove('is-copied');
        }.bind(this), 1500);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>