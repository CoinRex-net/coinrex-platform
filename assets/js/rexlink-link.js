const linkConfig = window.CoinRexLinkWalletConfig || {};
(function() {
    'use strict';

    const rexlinkApiBaseUrl = String(linkConfig.rexlinkApiBaseUrl || window.location.origin).replace(/\/+$/, '');
    const browserBaseUrl = String(linkConfig.browserBaseUrl || linkConfig.baseUrl || window.location.origin).replace(/\/+$/, '');
    const phpApiBaseUrl = String(linkConfig.baseUrl || linkConfig.phpApiBaseUrl || window.location.origin).replace(/\/+$/, '');
    const createPairingUrl = phpApiBaseUrl + '/api/rex-signer/create_pairing.php';
    const pairingStatusUrl = phpApiBaseUrl + '/api/link_wallet_pairing_status.php';
    const persistUrl = String(linkConfig.persistUrl || '');
    const csrfToken = String(linkConfig.csrfToken || '');
    const redirectAfterLink = String(linkConfig.redirectAfterLink || '');
    const pairing = window.CoinRexPairing || {};
    const RexLink = window.RexLink;
    if (RexLink && typeof RexLink.init === 'function') {
        RexLink.init({
            apiBaseUrl: rexlinkApiBaseUrl,
            appId: 'coinrex',
            transport: 'auto',
            webActorToken: String(linkConfig.webActorToken || ''),
            requestTimeoutMs: 2600,
        });
    }

    const modal = document.getElementById('rexLinkModal');
    const placeholder = document.getElementById('rexLinkQrPlaceholder');
    const qrImage = document.getElementById('rexLinkQrImage');
    const logoBadge = document.getElementById('rexLinkQrLogoBadge');
    const pairingCode = document.getElementById('rexLinkPairingCode');
    const copyCodeButton = document.getElementById('rexLinkCopyCodeButton');
    const countdownText = document.getElementById('rexLinkCountdown');
    const sessionNote = document.getElementById('rexLinkSessionNote');
    const statusText = document.getElementById('rexLinkStatus');
    const primaryButton = document.getElementById('rexLinkPrimaryButton');
    const successStep = document.getElementById('rexLinkSuccessStep');
    const qrStep = document.getElementById('rexLinkQrStep');
    const progressDuration = document.getElementById('rexLinkProgressDuration');
    const progressSuccess = document.getElementById('rexLinkProgressSuccess');
    const successMessage = document.getElementById('rexLinkSuccessMessage');
    const successCountdown = document.getElementById('rexLinkSuccessCountdown');
    const backdrop = document.getElementById('rexLinkModalBackdrop');
    const closeButton = document.getElementById('rexLinkModalClose');

    let pairingId = 0;
    let pollTimer = null;
    let countdownTimer = null;
    let redirectTimer = null;
    let statusRequestInFlight = false;
    let pairingRequestInFlight = false;
    let linkCompleted = false;
    let pollGeneration = 0;

    function shortAddress(address) {
        return pairing.shortAddress ? pairing.shortAddress(address) : (String(address || '').slice(0, 6) + '...' + String(address || '').slice(-4));
    }

    function formatClock(seconds) {
        if (pairing.formatClock) {
            return pairing.formatClock(seconds);
        }
        const safe = Math.max(0, Math.floor(Number(seconds || 0)));
        return Math.floor(safe / 60) + ':' + String(safe % 60).padStart(2, '0');
    }

    function countdownLabel(seconds) {
        const safe = Math.max(0, Math.floor(Number(seconds || 0)));
        return 'QR expires in ' + Math.floor(safe / 60) + 'm ' + String(safe % 60).padStart(2, '0') + 's';
    }

    function setStatus(message, state) {
        if (!statusText) return;
        statusText.textContent = message;
        statusText.classList.toggle('is-error', state === 'error');
        statusText.classList.toggle('is-success', state === 'success');
    }

    function setStep(step) {
        if (qrStep) qrStep.classList.toggle('is-active', step === 'link');
        if (successStep) successStep.classList.toggle('is-active', step === 'success');
        if (progressDuration) {
            progressDuration.classList.toggle('is-active', step === 'link');
            progressDuration.classList.toggle('is-complete', step === 'success');
        }
        if (progressSuccess) {
            progressSuccess.classList.toggle('is-active', step === 'success');
        }
        if (primaryButton && step !== 'link') {
            primaryButton.hidden = true;
        }
    }

    function stopPolling() {
        pollGeneration += 1;
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function scheduleNextPoll(delay, generation) {
        if (generation !== pollGeneration) return;
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
        pollTimer = window.setTimeout(function() {
            pollTimer = null;
            if (generation !== pollGeneration) return;
            pollLinkStatus();
        }, Math.max(100, Number(delay) || 500));
    }

    function stopCountdown() {
        if (countdownTimer) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    function setCopyButtonState(copied) {
        if (!copyCodeButton) return;
        copyCodeButton.innerHTML = copied ? '<i class="fas fa-check"></i>' : '<i class="fas fa-copy"></i>';
        copyCodeButton.title = copied ? 'Copied' : 'Copy code';
        copyCodeButton.setAttribute('aria-label', copied ? 'Pairing code copied' : 'Copy pairing code');
    }

    function resetModal() {
        stopPolling();
        stopCountdown();
        linkCompleted = false;
        pairingId = 0;
        statusRequestInFlight = false;
        pairingRequestInFlight = false;
        if (redirectTimer) {
            window.clearTimeout(redirectTimer);
            redirectTimer = null;
        }
        if (qrImage) {
            qrImage.hidden = true;
            qrImage.onload = null;
            qrImage.onerror = null;
            qrImage.removeAttribute('src');
        }
        if (placeholder) {
            placeholder.hidden = false;
            placeholder.classList.remove('is-rendered');
            placeholder.innerHTML = '';
        }
        if (logoBadge) {
            logoBadge.classList.remove('is-visible');
        }
        if (pairingCode) {
            pairingCode.textContent = 'No code yet';
        }
        if (copyCodeButton) {
            copyCodeButton.disabled = true;
            setCopyButtonState(false);
        }
        if (countdownText) {
            countdownText.textContent = 'Waiting for code';
        }
        if (sessionNote) {
            sessionNote.textContent = 'Your wallet address will be linked to your CoinRex account.';
        }
        if (successCountdown) {
            successCountdown.textContent = 'RexLink session is active.';
        }
        if (primaryButton) {
            primaryButton.hidden = true;
            primaryButton.disabled = false;
            primaryButton.textContent = 'Generate New QR';
        }
        setStep('link');
        setStatus('Creating RexLink code...', '');
    }

    function initSdk() {
        if (!RexLink || typeof RexLink.init !== 'function') return;
        RexLink.init({
            apiBaseUrl: rexlinkApiBaseUrl,
            appId: 'coinrex',
            transport: 'auto',
            webActorToken: String(linkConfig.webActorToken || ''),
            requestTimeoutMs: 2600,
        });
        if (typeof RexLink.connectRealtime === 'function') {
            RexLink.connectRealtime().catch(function() {});
        }
    }

    function openModal() {
        if (!modal) return;
        initSdk();
        resetModal();
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        createPairing();
    }

    function closeModal() {
        stopPolling();
        stopCountdown();
        if (redirectTimer) {
            window.clearTimeout(redirectTimer);
            redirectTimer = null;
        }
        if (modal) {
            modal.hidden = true;
        }
        document.body.style.overflow = '';
    }

    function showExpired(message) {
        if (linkCompleted) return;
        stopPolling();
        stopCountdown();
        setStatus(message || 'This RexLink QR expired. Create a fresh code.', 'error');
        if (countdownText) {
            countdownText.textContent = 'QR expired';
        }
        if (qrImage) {
            qrImage.hidden = true;
            qrImage.onload = null;
            qrImage.onerror = null;
            qrImage.removeAttribute('src');
        }
        if (logoBadge) {
            logoBadge.classList.remove('is-visible');
        }
        if (placeholder) {
            placeholder.hidden = false;
            placeholder.classList.remove('is-rendered');
            placeholder.innerHTML = '<span class="rexlink-expired-placeholder"><i class="fas fa-clock"></i><strong>QR expired</strong><small>Generate a new QR to keep pairing.</small></span>';
        }
        if (pairingCode) {
            pairingCode.textContent = 'QR expired';
        }
        if (copyCodeButton) {
            copyCodeButton.disabled = true;
            setCopyButtonState(false);
        }
        if (primaryButton) {
            primaryButton.hidden = false;
            primaryButton.disabled = false;
            primaryButton.textContent = 'Generate New QR';
        }
    }

    function startCountdown(seconds, expiresAtUnix) {
        const fallbackSeconds = Math.min(300, Math.max(0, Number(seconds || 300)));
        const localDeadline = Date.now() + fallbackSeconds * 1000;
        const suppliedDeadline = Number(expiresAtUnix || 0) * 1000;
        const deadlineMs = suppliedDeadline > Date.now()
            ? Math.min(suppliedDeadline, localDeadline)
            : localDeadline;
        stopCountdown();
        const update = function() {
            const remaining = Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
            if (countdownText) {
                countdownText.textContent = countdownLabel(remaining);
            }
            if (remaining <= 0) {
                showExpired('This RexLink QR expired. Create a fresh code.');
                return;
            }
        };
        update();
        countdownTimer = window.setInterval(update, 1000);
    }

    function renderQrPayload(payload) {
        if (!placeholder || !payload) return;
        payload = Object.assign({}, payload);
        const serverRoot = String(payload.api_base_url || payload.base_url || phpApiBaseUrl)
            .replace(/\/+$/, '');
        payload.api_base_url = serverRoot;
        payload.base_url = serverRoot;
        payload.u = serverRoot;
        // Keep the transport URL returned by create_pairing.php. Installed
        // RexLink builds expect the legacy /api/rex-signer API base in QR data.
        const qrApiBaseUrl = String(payload.api_base_url || payload.base_url || linkConfig.qrApiBaseUrl || rexlinkApiBaseUrl).replace(/\/+$/, '');
        if (RexLink && typeof RexLink.renderQR === 'function') {
            RexLink.renderQR(payload, placeholder, {
                image: qrImage,
                logoBadge: logoBadge,
                qrOptions: { margin: 1, errorCorrectionLevel: 'L' },
            }).then(function(rendered) {
                if (!rendered) {
                    setStatus('QR could not load. Use the code below.', 'error');
                }
            });
            return;
        }
        if (pairing.renderQr) {
            pairing.renderQr(payload, {
                placeholder: placeholder,
                image: qrImage,
                logoBadge: logoBadge,
                fallbackUrl: rexlinkApiBaseUrl + '/api/v1/pairing/qr',
                fallbackText: 'QR could not load. Use the code below.',
                qrOptions: { margin: 1, errorCorrectionLevel: 'L' },
                payloadDefaults: {
                    purpose: 'link',
                    apiBaseUrl: qrApiBaseUrl,
                    baseUrl: qrApiBaseUrl,
                    dappName: 'CoinRex',
                    dappUrl: browserBaseUrl,
                    networkSlug: 'polygon',
                    chainId: 137,
                    durationMinutes: 10,
                },
            }).then(function(rendered) {
                if (!rendered) {
                    setStatus('QR could not load. Use the code below.', 'error');
                }
            });
            return;
        }
        placeholder.innerHTML = '<span>Use the code below.</span>';
    }

    function finishWalletLink(sessionId, wallet) {
        if (linkCompleted || !Number(sessionId || 0) || !String(wallet || '')) return;
        if (!persistUrl || !csrfToken) {
            setStatus('Wallet session found, but CoinRex could not save it. Refresh and try again.', 'error');
            if (primaryButton) {
                primaryButton.hidden = false;
                primaryButton.disabled = false;
                primaryButton.textContent = 'Generate New QR';
            }
            return;
        }
        linkCompleted = true;
        stopPolling();
        stopCountdown();
        if (redirectTimer) window.clearTimeout(redirectTimer);
        const walletAddress = String(wallet || '');
        if (successMessage) {
            successMessage.textContent = 'Wallet ' + shortAddress(walletAddress) + ' connected. Saving to your CoinRex account...';
        }
        if (successCountdown) {
            successCountdown.textContent = 'RexLink session active.';
        }
        setStep('success');
        setStatus('Wallet connected.', 'success');
        postJson(persistUrl, { session_id: Number(sessionId), csrf_token: csrfToken }, 8000)
            .then(function(data) {
                const savedWallet = String(data.wallet_address || walletAddress);
                if (successMessage) {
                    successMessage.textContent = 'Wallet ' + shortAddress(savedWallet) + ' is now linked to your CoinRex account. Redirecting...';
                }
                if (successCountdown) {
                    successCountdown.textContent = 'RexLink session active.';
                }
                setStep('success');
                setStatus('Wallet linked successfully.', 'success');
                redirectTimer = window.setTimeout(function() {
                    window.location.href = redirectAfterLink || (browserBaseUrl + '/public/dashboard.php');
                }, 900);
            })
            .catch(function(error) {
                linkCompleted = false;
                setStep('link');
                setStatus(error.message || 'CoinRex could not save this wallet. Please try again.', 'error');
                if (primaryButton) {
                    primaryButton.hidden = false;
                    primaryButton.disabled = false;
                    primaryButton.textContent = 'Generate New QR';
                }
            });
    }

    function pollLinkStatus() {
        if (linkCompleted || statusRequestInFlight || pairingId <= 0) return;
        const generation = pollGeneration;
        const requestedPairingId = pairingId;
        statusRequestInFlight = true;
        // Query the exact PHP pairing row created for this browser. This avoids
        // relying on a separate RexLink WebSocket or its slower SDK poller.
        postJson(pairingStatusUrl, { pairing_id: requestedPairingId }, 4000).then(function(data) {
            if (generation !== pollGeneration || requestedPairingId !== pairingId || linkCompleted) return;
            const session = data && data.session ? data.session : {};
            const sessionId = Number(data.session_id || session.id || session.session_id || 0);
            const wallet = String(data.wallet_address || session.wallet_address || '');
            if (String(data.status || '') === 'connected' && sessionId && wallet) {
                finishWalletLink(sessionId, wallet);
            } else if (['expired', 'revoked', 'failed'].indexOf(String(data.status || '')) !== -1) {
                showExpired(data.message || 'RexLink pairing expired. Create a new code.');
            }
        }).catch(function(error) {
            if (generation !== pollGeneration || requestedPairingId !== pairingId) return;
            if (error && error.status_code === 401) {
                stopPolling();
                setStatus('Your CoinRex session expired. Sign in again, then create a new pairing QR.', 'error');
                return;
            }
            setStatus((error.message || 'Could not check pairing.') + ' Retrying automatically...', 'error');
        }).finally(function() {
            if (generation !== pollGeneration || requestedPairingId !== pairingId) return;
            statusRequestInFlight = false;
            if (!linkCompleted && modal && !modal.hidden && pollTimer === null) {
                scheduleNextPoll(500, generation);
            }
        });
    }

    function startPolling() {
        stopPolling();
        // Poll the exact pairing row immediately, then schedule the next check
        // after the current request settles to prevent concurrent requests.
        pollTimer = null;
        pollLinkStatus();
    }

    function postJson(url, payload, timeoutMs, extraHeaders) {
        const requestStartedAt = Date.now();
        const timeout = Math.max(0, Number(timeoutMs || 4000));
        const controller = timeout > 0 && 'AbortController' in window ? new AbortController() : null;
        const timeoutId = controller ? window.setTimeout(function() { controller.abort(); }, timeout) : null;
        return fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: Object.assign({ 'Content-Type': 'application/json' }, extraHeaders || {}),
            cache: 'no-store',
            signal: controller ? controller.signal : undefined,
            body: JSON.stringify(payload || {}),
        }).then(function(response) {
            return response.json().then(function(data) {
                data = data || {};
                data.status_code = response.status;
                if (!response.ok || data.success === false) {
                    const err = new Error(data.message || 'Request failed');
                    err.status_code = response.status;
                    err.data = data;
                    throw err;
                }
                return data;
            });
        }).finally(function() {
            if (timeoutId) window.clearTimeout(timeoutId);
        }).catch(function(error) {
            // Do not log payloads: they can contain CSRF tokens or wallet details.
            if (window.console && typeof window.console.warn === 'function') {
                window.console.warn('[CoinRex wallet-link]', {
                    endpoint: String(url).split('?')[0],
                    elapsed_ms: Date.now() - requestStartedAt,
                    http_status: error.status_code || null,
                    error: error.name === 'AbortError' ? 'request_timeout' : error.message,
                });
            }
            if (error && error.name === 'AbortError') {
                throw new Error('CoinRex is taking too long to confirm the connection.');
            }
            throw error;
        });
    }

    function createLinkPairingCode() {
        const payload = {
            purpose: 'claim',
            duration_minutes: 5,
            dapp_name: 'CoinRex',
            dapp_url: browserBaseUrl,
            network_slug: linkConfig.networkSlug || 'polygon',
            network_name: linkConfig.networkName || 'Polygon',
            chain_id: Number(linkConfig.chainId || 137),
        };

        // Create through the site's authenticated PHP endpoint first. This is
        // the same pairing store RexLink completes, without depending on the
        // node SDK's short request timeout just to display a QR code.
        return postJson(createPairingUrl, payload, 12000);
    }

    function createPairing() {
        if (pairingRequestInFlight) return;
        resetModal();
        pairingRequestInFlight = true;
        setStatus('Creating RexLink code...', '');
        if (primaryButton) {
            primaryButton.hidden = false;
            primaryButton.disabled = true;
            primaryButton.textContent = 'Generating QR...';
        }

        createLinkPairingCode().then(function(data) {
            if (data.already_connected && data.session) {
                const sessionId = Number(data.session.id || data.session.session_id || 0);
                const wallet = String(data.session.wallet_address || '');
                if (sessionId) {
                    finishWalletLink(sessionId, wallet);
                    return;
                }
            }
            pairingId = Number(data.pairing_id || 0);
            if (pairingCode) {
                pairingCode.textContent = data.display_code || 'Code ready';
            }
            if (data.qr_payload) {
                renderQrPayload(data.qr_payload);
            }
            if (placeholder) {
                placeholder.hidden = !data.qr_payload;
            }
            if (logoBadge) {
                logoBadge.classList.remove('is-visible');
            }
            if (copyCodeButton) {
                copyCodeButton.disabled = !data.display_code;
            }
            if (primaryButton) {
                primaryButton.hidden = true;
                primaryButton.disabled = false;
                primaryButton.textContent = 'Generate New QR';
            }
            if (sessionNote) {
                sessionNote.textContent = 'Your wallet address will be linked to your CoinRex account.';
            }
            startCountdown(
                data.expires_in_seconds || 300,
                data.expires_at_unix || (data.qr_payload && data.qr_payload.expires_at_unix) || 0
            );
            setStatus('Open RexLink and connect with this QR or code.', '');
            setStep('link');
            startPolling();
        }).catch(function(error) {
            setStatus(error.message || 'RexLink linking could not start.', 'error');
            setStep('link');
            if (placeholder) {
                placeholder.hidden = false;
                placeholder.classList.remove('is-rendered');
                placeholder.innerHTML = '';
            }
            if (logoBadge) {
                logoBadge.classList.remove('is-visible');
            }
            if (primaryButton) {
                primaryButton.hidden = false;
                primaryButton.disabled = false;
                primaryButton.textContent = 'Generate New QR';
            }
        }).finally(function() {
            pairingRequestInFlight = false;
        });
    }

    const linkButton = document.getElementById('linkWalletPairButton');
    if (linkButton) {
        linkButton.addEventListener('click', function() {
            openModal();
        });
    }

    if (copyCodeButton) {
        copyCodeButton.addEventListener('click', function() {
            const code = pairingCode ? pairingCode.textContent.trim() : '';
            if (!code || code === 'No code yet') return;
            if (pairing.copyText) {
                pairing.copyText(code, copyCodeButton, 1400);
                return;
            }
            setCopyButtonState(true);
            window.setTimeout(function() {
                setCopyButtonState(false);
            }, 1400);
        });
    }

    if (primaryButton) {
        primaryButton.addEventListener('click', function() {
            createPairing();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeModal);
    }
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });

    function resumePairingStatusCheck() {
        if (!modal || modal.hidden || linkCompleted || pairingId <= 0) return;
        // Background tabs may have heavily throttled the polling timer. Check
        // immediately when the browser lets this page run again.
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
        pollLinkStatus();
    }

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') resumePairingStatusCheck();
    });
    window.addEventListener('focus', resumePairingStatusCheck);

    // rexlink-sdk.js dispatches this CustomEvent on document (not window).
    // Listening on window silently misses the immediate connection signal and
    // leaves this page waiting for the slower HTTP polling fallback.
    document.addEventListener('rexlink:session-connected', function(event) {
        if (!modal || modal.hidden) return;
        const detail = event && event.detail ? event.detail : {};
        const eventPairingId = Number(detail.pairing_id || (detail.session && detail.session.pairing_code_id) || 0);
        if (eventPairingId && pairingId && eventPairingId !== pairingId) return;
        const session = detail.session && typeof detail.session === 'object' ? detail.session : {};
        const sessionId = Number(detail.session_id || session.id || session.session_id || 0);
        const wallet = String(detail.wallet_address || session.wallet_address || '');
        if (sessionId && wallet) {
            finishWalletLink(sessionId, wallet);
            return;
        }
        pollLinkStatus();
    });
})();
