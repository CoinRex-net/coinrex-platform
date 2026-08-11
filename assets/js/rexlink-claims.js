const cfg = window.CoinRexClaimsConfig || {};
(function() {
    const overviewUrl = String(cfg.overviewUrl || '');
    const browserBaseUrl = window.location.origin + String(cfg.baseUri || '/');
    const configuredApiBaseUrl = String(cfg.configuredApiBaseUrl || '');
    const publicApiBaseUrl = String(cfg.publicApiBaseUrl || '');
    const hasConfiguredPublicApiBaseUrl = Boolean(cfg.hasConfiguredPublicApiBaseUrl);
    const rexlinkApiBaseUrl = String(cfg.configuredApiBaseUrl || cfg.rexlinkApiBaseUrl || browserBaseUrl).replace(/\/+$/, '');
    const createPairingUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_pairing.php';
    const pairingQrUrl = rexlinkApiBaseUrl + '/api/rex-signer/pairing_qr.php';
    const sessionsUrl = rexlinkApiBaseUrl + '/api/rex-signer/sessions.php';
    const revokeSessionUrl = rexlinkApiBaseUrl + '/api/rex-signer/revoke_session.php';
    const createClaimApprovalUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_claim_approval.php';
    const approvalStatusUrl = rexlinkApiBaseUrl + '/api/rex-signer/approval_status.php';
    const approvalsUrl = rexlinkApiBaseUrl + '/api/rex-signer/approval_requests.php';
    const realtimeAuthUrl = rexlinkApiBaseUrl + '/api/rex-signer/realtime_auth.php';
    const realtimeDebug = Boolean(cfg.realtimeDebug);
    const serverClaimPairingTestMode = Boolean(cfg.serverClaimPairingTestMode);
    const serverClaimEligible = Boolean(cfg.serverClaimEligible);
    const serverClaimSecurityReviewLocked = Boolean(cfg.serverClaimSecurityReviewLocked);
    const initialAvailableBalance = Number(cfg.initialAvailableBalance || 0);
    const initialClaimedBalance = Number(cfg.initialClaimedBalance || 0);
    const initialOpenClaim = Boolean(cfg.initialOpenClaim);
    const pairing = window.CoinRexPairing || {};

    let currentClaimEligible = serverClaimEligible;
    let currentClaimPairingTestMode = serverClaimPairingTestMode;
    let currentClaimSecurityReviewLocked = serverClaimSecurityReviewLocked;
    let hasOpenClaim = currentClaimPairingTestMode ? false : initialOpenClaim;
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
    const claimSupportCta = document.getElementById('claimSupportCta');
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
        return pairing.shortAddress ? pairing.shortAddress(address) : (address ? address.slice(0, 6) + '...' + address.slice(-4) : 'Connected');
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
        if (modalCountdownText) {
            modalCountdownText.hidden = activeSessionCount <= 0 && remaining <= 0;
            modalCountdownText.textContent = countdownLabel(remaining);
            modalCountdownText.classList.toggle('is-warning', remaining > 0 && remaining <= 120);
            modalCountdownText.classList.toggle('is-expired', remaining <= 0);
        }
        if (landingCountdownText) {
            const showLandingCountdown = activeSessionCount > 0 && remaining > 0;
            landingCountdownText.hidden = !showLandingCountdown;
            landingCountdownText.textContent = countdownLabel(remaining);
            landingCountdownText.classList.toggle('is-warning', remaining > 0 && remaining <= 120);
            landingCountdownText.classList.toggle('is-expired', remaining <= 0);
        }
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
        if (pairing.renderQr) {
            pairing.renderQr(payload, {
                placeholder: qrPlaceholder,
                image: qrImage,
                logoBadge: logoBadge,
                fallbackUrl: pairingQrUrl,
                fallbackText: note || 'QR could not load. Please use the code below instead.',
                payloadDefaults: {
                    purpose: 'claim',
                    apiBaseUrl: String(publicApiBaseUrl || configuredApiBaseUrl).replace(/\/+$/, ''),
                    baseUrl: String(publicApiBaseUrl || configuredApiBaseUrl).replace(/\/+$/, ''),
                    dappName: 'CoinRex',
                    dappUrl: browserBaseUrl.replace(/\/+$/, ''),
                    networkSlug: cfg.rexTokenNetworkSlug || 'polygon',
                    chainId: cfg.rexTokenChainId || 137,
                    durationMinutes: selectedDuration || 10,
                },
            }).then(function(rendered) {
                if (!rendered) {
                    setQrState('empty', note || '');
                }
            });
            return;
        }

        setQrState('empty', note || '');
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
                ? (currentClaimPairingTestMode ? 'Pairing test passed. RexLink is connected for this browser session.' : 'You can continue claiming from this connected session.')
                : (isExpired ? 'Create a new session before you claim.' : (currentClaimPairingTestMode ? 'Scan the QR or enter the 6 digit code to test pairing.' : (!currentClaimEligible ? 'Pairing is available, but claiming is locked until account review completes.' : 'Scan the QR or enter the 6 digit code.'))));
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
        const stateLabel = currentClaimPairingTestMode ? 'Test Mode' : (hasOpenClaim ? 'Processing' : (currentClaimEligible ? 'Ready' : 'Locked'));
        const statusText = currentClaimPairingTestMode
            ? 'RexLink pairing test is active. Real claim approval is disabled.'
            : (message || (hasOpenClaim
            ? 'Waiting for your claim to be submitted.'
            : (currentClaimEligible ? 'Ready to be approved in RexLink.' : 'You are not ready to claim yet.')));
        if (landingAvailable) landingAvailable.textContent = formatRex(availableBalanceValue, 2);
        if (landingClaimed) landingClaimed.textContent = formatRex(claimedBalanceValue, 2);
        if (landingState) landingState.textContent = stateLabel;
        if (landingStatus) landingStatus.textContent = statusText;
        if (heroClaimState) heroClaimState.textContent = stateLabel;
        if (heroClaimNote) heroClaimNote.textContent = statusText;
        if (openButton) {
            openButton.disabled = currentClaimPairingTestMode ? false : (!currentClaimEligible || hasOpenClaim || availableBalanceValue <= 0);
            openButton.textContent = currentClaimPairingTestMode ? 'Test RexLink Pairing' : (hasOpenClaim ? 'Claim Processing' : (!currentClaimEligible ? 'Claim Locked' : 'Claim REX'));
        }
        if (claimSupportCta) {
            claimSupportCta.hidden = currentClaimPairingTestMode || !currentClaimSecurityReviewLocked || hasOpenClaim || currentClaimEligible;
        }
        renderLandingSessionCard();
    }

    function validateAmount() {
        const amount = selectedAmount();
        let message = '';
        if (currentClaimPairingTestMode) {
            message = 'Pairing test mode only checks RexLink connection. Real claim approval is disabled.';
        } else if (!currentClaimEligible) {
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
            availableHint.textContent = currentClaimPairingTestMode ? 'Pairing test mode: approval submission is disabled.' : 'Available: ' + formatRex(availableBalanceValue, 2) + ' REX';
        }
        if (amountInput) {
            amountInput.max = String(availableBalanceValue);
            amountInput.disabled = currentClaimPairingTestMode || !currentClaimEligible || hasOpenClaim || activeRequestId > 0 || availableBalanceValue <= 0;
            if (amountInput.value !== '' && Number(amountInput.value) > availableBalanceValue) {
                amountInput.value = availableBalanceValue > 0 ? availableBalanceValue.toFixed(8) : '0.00000000';
            }
        }
        if (maxButton) {
            maxButton.disabled = currentClaimPairingTestMode || !currentClaimEligible || hasOpenClaim || activeRequestId > 0 || availableBalanceValue <= 0;
        }
        validateAmount();
    }

    function resultCopy() {
        if (modalResultState === 'pairing_test_connected') {
            return ['RexLink connected', 'Pairing test passed. Your wallet session is connected, and no claim approval was created.', 'fa-link', 'success'];
        }
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

    function setApprovalStepLabel(element, label) {
        if (!element) {
            return;
        }
        const marker = element.querySelector('span');
        element.textContent = '';
        if (marker) {
            element.appendChild(marker);
        }
        element.appendChild(document.createTextNode(label));
    }

    function renderApprovalTimeline() {
        if (modalResultState === 'pairing_test_connected') {
            setApprovalStepLabel(approvalStepWaiting, 'Pairing code scanned');
            setApprovalStepLabel(approvalStepApproved, 'Wallet session connected');
            setApprovalStepLabel(approvalStepSubmitted, 'Test complete');
        } else {
            setApprovalStepLabel(approvalStepWaiting, 'Sent to RexLink');
            setApprovalStepLabel(approvalStepApproved, 'Mobile approval');
            setApprovalStepLabel(approvalStepSubmitted, 'Transaction status');
        }
        const terminalError = ['gas', 'network', 'rejected', 'expired', 'cancelled'].includes(modalResultState);
        const terminalSuccess = ['success', 'claimed', 'pairing_test_connected'].includes(modalResultState);
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
            modalTitle.textContent = currentClaimPairingTestMode && isApproval
                ? 'Pairing test result'
                : (isDuration || (isLoading && loadingTarget === 'duration')
                ? 'Session time'
                : ((isConnect || (isLoading && loadingTarget === 'connect')) ? 'Connect RexLink' : ((isAmount || (isLoading && loadingTarget === 'amount')) ? 'Choose amount' : 'Approve claim')));
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
            } else if (['success', 'claimed', 'pairing_test_connected'].includes(modalResultState)) {
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
                if (currentClaimPairingTestMode) {
                    modalResultState = 'pairing_test_connected';
                    delayedModalStep('RexLink connected.', 'Pairing test passed.', 'approval', 120, 'approval');
                    return;
                }
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 120);
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
        currentClaimPairingTestMode = !!data.pairing_test_mode;
        currentClaimEligible = !!data.claim_eligibility.eligible;
        currentClaimSecurityReviewLocked = !currentClaimEligible && !!data.claim_eligibility.signals;
        hasOpenClaim = currentClaimPairingTestMode ? false : !!data.open_claim;
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
        const response = await fetch(sessionsUrl, { credentials: 'include', cache: 'no-store' });
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
            setQrState('empty', currentClaimPairingTestMode ? 'Wallet connected. Pairing test passed.' : 'Wallet connected. Continue to claim amount.');
            if (pairingCode) {
                pairingCode.textContent = 'Connected';
                pairingCode.classList.add('is-connected');
                pairingCode.classList.remove('is-pending');
            }
            setPairingCopyState('', false);
            if (!modal.hidden && ['duration', 'connect', 'loading'].includes(modalStep)) {
                if (currentClaimPairingTestMode) {
                    modalResultState = 'pairing_test_connected';
                    delayedModalStep('RexLink connected.', 'Pairing test passed.', 'approval', 120, 'approval');
                    return data;
                }
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 120);
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
                network_slug: cfg.rexTokenNetworkSlug || 'polygon',
                network_name: cfg.rexTokenNetworkLabel || 'Polygon',
                chain_id: Number(cfg.rexTokenChainId || 137)
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
                delayedModalStep('Wallet connected.', 'Preparing claim amount...', 'amount', 120);
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
            window.setTimeout(function() {
                refreshSessions().catch(function() {});
            }, 350);
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
            delayedModalStep('Creating RexLink QR...', 'Your QR is almost ready.', 'connect', 120);
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
        const sessionId = activeSessionId;
        resetModalAfterSessionLoss('Session disconnected. Please connect again to continue.');
        const data = await postJson(revokeSessionUrl, {
            session_id: sessionId,
            reason: 'Revoked from claim checkout page',
        });
        if (!data.success) {
            sessionInactiveMessage = 'Local session cleared. Server disconnect will retry when sessions refresh.';
            renderLandingSessionCard(sessionInactiveMessage);
            return;
        }
        refreshSessions().catch(function() {});
    }

    async function requestClaimApproval() {
        if (currentClaimPairingTestMode) {
            modalResultState = 'pairing_test_connected';
            setClaimModalStep('approval');
            return;
        }
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
        const nextInterval = wantsFastPairingPoll ? 500 : (realtimeConnected ? 12000 : 1500);
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
    window.addEventListener('rexlink:session-expired', function() {
        clearSessionState('Session disconnected. Please connect again to continue.');
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
            if (currentClaimPairingTestMode) {
                modalResultState = 'pairing_test_connected';
                setClaimModalStep('approval');
                return;
            }
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
        if (pairing.copyText) {
            pairing.copyText(code, copyCodeButton, 1800).then(function() {
                setPairingCopyState(code, true);
                window.setTimeout(function() {
                    setPairingCopyState(code, false);
                }, 1800);
            }).catch(function() {
                setPairingCopyState(code, false);
            });
        }
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
