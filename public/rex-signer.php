<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.signer-page { min-height: 100vh; background: #081120; color: #fff; padding: 48px 20px 96px; }
.signer-shell { max-width: 1040px; margin: 0 auto; }
.signer-hero { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr); gap: 20px; align-items: stretch; }
.signer-panel, .signer-card { border: 1px solid rgba(148, 163, 184, .16); border-radius: 8px; background: #0d1b34; box-shadow: 0 18px 44px rgba(0, 0, 0, .24); }
.signer-panel { padding: 28px; }
.signer-tag { display: inline-flex; align-items: center; gap: 8px; color: #facc15; font-size: 13px; font-weight: 800; text-transform: uppercase; }
.signer-title { margin: 14px 0 10px; font-size: clamp(32px, 5vw, 56px); line-height: 1; letter-spacing: 0; }
.signer-copy { max-width: 660px; color: #b9c7e8; font-size: 16px; line-height: 1.7; }
.signer-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
.signer-button { min-height: 46px; border: 0; border-radius: 8px; padding: 0 16px; color: #fff; background: #1d4ed8; font-weight: 900; cursor: pointer; }
.signer-button.secondary { border: 1px solid rgba(212, 175, 55, .35); color: #facc15; background: transparent; }
.signer-button:disabled { cursor: not-allowed; opacity: .55; }
.signer-card { padding: 20px; }
.signer-card h3 { margin: 0 0 14px; font-size: 18px; }
.signer-code-box { display: grid; place-items: center; min-height: 120px; border-radius: 8px; border: 1px dashed rgba(212, 175, 55, .35); background: #0b1220; color: #facc15; font-size: clamp(20px, 4vw, 32px); font-weight: 950; letter-spacing: 1px; text-align: center; padding: 16px; }
.signer-note, .signer-status { color: #b9c7e8; font-size: 14px; line-height: 1.6; }
.signer-status { min-height: 22px; margin-top: 12px; }
.signer-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
.signer-session-row { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid rgba(148, 163, 184, .14); }
.signer-session-row strong { display: block; color: #fff; }
.signer-session-row span { color: #93c5fd; font-size: 13px; }
@media (max-width: 760px) { .signer-hero, .signer-grid { grid-template-columns: 1fr; } }
</style>

<main class="signer-page">
    <div class="signer-shell">
        <section class="signer-hero">
            <div class="signer-panel">
                <span class="signer-tag"><i class="fas fa-qrcode"></i> RexLink</span>
                <h1 class="signer-title">Pair RexLink</h1>
                <p class="signer-copy">Create a short-lived pairing code, then enter it in the RexLink app. This only creates a timed account session for approvals; it does not store keys, sign transactions, or move funds.</p>
                <div class="signer-actions">
                    <button type="button" class="signer-button" id="createPairingButton">Create Pairing Code</button>
                    <button type="button" class="signer-button secondary" id="refreshSessionsButton">Refresh Sessions</button>
                    <a class="signer-button secondary" href="<?php echo BASE_URL; ?>/public/dashboard.php" style="display:inline-flex;align-items:center;text-decoration:none;">Back to RexHub</a>
                </div>
                <p class="signer-status" id="signerStatus">Ready to create a pairing code.</p>
            </div>

            <div class="signer-card">
                <h3>Pairing Code</h3>
                <div class="signer-code-box" id="pairingCodeBox">No code yet</div>
                <p class="signer-note">Code expires in 5 minutes. Session duration is currently 10 minutes.</p>
            </div>
        </section>

        <section class="signer-grid">
            <div class="signer-card">
                <h3>Active Sessions</h3>
                <div id="sessionsList"><p class="signer-note">Create a session from the app, then refresh this list.</p></div>
            </div>

            <div class="signer-card">
                <h3>Quick Test Approval</h3>
                <p class="signer-note">After pairing, create a pending request and approve/reject it from the app.</p>
                <div class="signer-actions">
                    <button type="button" class="signer-button secondary" id="createApprovalButton">Create Test Claim Approval</button>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
(function() {
    const createPairingUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/create_pairing.php'); ?>;
    const sessionsUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/sessions.php'); ?>;
    const approvalUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/create_approval_request.php'); ?>;
    const createPairingButton = document.getElementById('createPairingButton');
    const refreshSessionsButton = document.getElementById('refreshSessionsButton');
    const createApprovalButton = document.getElementById('createApprovalButton');
    const pairingCodeBox = document.getElementById('pairingCodeBox');
    const signerStatus = document.getElementById('signerStatus');
    const sessionsList = document.getElementById('sessionsList');

    function setStatus(message) {
        signerStatus.textContent = message;
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        });
        return response.json();
    }

    function renderSessions(items) {
        if (!Array.isArray(items) || items.length === 0) {
            sessionsList.innerHTML = '<p class="signer-note">No sessions yet.</p>';
            return;
        }

        sessionsList.innerHTML = items.map(function(session) {
            return '<div class="signer-session-row">' +
                '<div><strong>' + String(session.device_name || 'RexLink') + '</strong><span>Expires: ' + String(session.expires_at || '-') + '</span></div>' +
                '<span>' + String(session.status || '-') + '</span>' +
            '</div>';
        }).join('');
    }

    async function refreshSessions() {
        setStatus('Loading sessions...');
        const response = await fetch(sessionsUrl, { credentials: 'same-origin' });
        const data = await response.json();
        if (!data.success) {
            setStatus(data.message || 'Could not load sessions.');
            return;
        }
        renderSessions(data.sessions || []);
        setStatus('Sessions refreshed. Active: ' + String(data.active_session_count || 0));
    }

    createPairingButton.addEventListener('click', async function() {
        createPairingButton.disabled = true;
        setStatus('Creating pairing code...');
        try {
            const data = await postJson(createPairingUrl, { duration_minutes: 10 });
            if (!data.success) {
                setStatus(data.message || 'Pairing code could not be created.');
                return;
            }
            pairingCodeBox.textContent = data.display_code || 'Code created';
            setStatus('Pairing code created. Enter it in RexLink.');
        } catch (error) {
            setStatus('Pairing code could not be created.');
        } finally {
            createPairingButton.disabled = false;
        }
    });

    refreshSessionsButton.addEventListener('click', refreshSessions);

    createApprovalButton.addEventListener('click', async function() {
        createApprovalButton.disabled = true;
        setStatus('Creating test approval...');
        try {
            const data = await postJson(approvalUrl, {
                network_slug: 'polygon-amoy',
                request_type: 'claim',
                title: 'Claim 125 REX',
                summary: 'Test approval request from CoinRex web.',
                amount: '125 REX',
                fee_estimate: '0.02 POL',
                expires_minutes: 10
            });
            setStatus(data.success ? 'Test approval created. Check the app.' : (data.message || 'Approval could not be created.'));
        } catch (error) {
            setStatus('Approval could not be created.');
        } finally {
            createApprovalButton.disabled = false;
        }
    });

    refreshSessions().catch(function() {
        setStatus('Ready to create a pairing code.');
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
