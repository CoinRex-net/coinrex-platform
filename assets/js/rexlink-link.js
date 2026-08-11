const linkConfig = window.CoinRexLinkWalletConfig || {};
(function() {
    'use strict';

    const rexlinkApiBaseUrl = String(linkConfig.rexlinkApiBaseUrl || linkConfig.baseUrl || window.location.origin).replace(/\/+$/, '');
    const browserBaseUrl = String(linkConfig.browserBaseUrl || linkConfig.baseUrl || window.location.origin).replace(/\/+$/, '');
    const createPairingUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_pairing.php';
    const pairingQrUrl = rexlinkApiBaseUrl + '/api/rex-signer/pairing_qr.php';
    const sessionsUrl = rexlinkApiBaseUrl + '/api/rex-signer/sessions.php';
    const redirectAfterLink = String(linkConfig.redirectAfterLink || '');
    const pairing = window.CoinRexPairing || {};

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
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
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

    function openModal() {
        if (!modal) return;
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

    function startCountdown(seconds) {
        let remaining = Math.max(0, Number(seconds || 300));
        stopCountdown();
        const update = function() {
            if (countdownText) {
                countdownText.textContent = countdownLabel(remaining);
            }
            if (remaining <= 0) {
                showExpired('This RexLink QR expired. Create a fresh code.');
                return;
            }
            remaining -= 1;
        };
        update();
        countdownTimer = window.setInterval(update, 1000);
    }

    function renderQrPayload(payload) {
        if (!placeholder || !payload) return;
        if (pairing.renderQr) {
            pairing.renderQr(payload, {
                placeholder: placeholder,
                image: qrImage,
                logoBadge: logoBadge,
                fallbackUrl: pairingQrUrl,
                fallbackText: 'QR could not load. Use the code below.',
                payloadDefaults: {
                    purpose: 'claim',
                    apiBaseUrl: rexlinkApiBaseUrl,
                    baseUrl: rexlinkApiBaseUrl,
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

    function pollLinkStatus() {
        if (linkCompleted || statusRequestInFlight) return;
        statusRequestInFlight = true;
        fetch(sessionsUrl, {
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (linkCompleted) return;
            if (!data || data.success !== true) {
                throw new Error((data && data.message) || 'Could not check RexLink status.');
            }
            const session = data.current_session || null;
            const isConnected = session
                && String(session.status || '') === 'active'
                && Number(session.remaining_seconds || 0) > 0
                && session.wallet_address;

            if (isConnected) {
                linkCompleted = true;
                stopPolling();
                stopCountdown();
                if (redirectTimer) window.clearTimeout(redirectTimer);
                const wallet = String(session.wallet_address || '');
                if (successMessage) {
                    successMessage.textContent = 'Wallet ' + shortAddress(wallet) + ' is now linked to your CoinRex account. Redirecting...';
                }
                if (successCountdown) {
                    successCountdown.textContent = 'RexLink session: ' + formatClock(session.remaining_seconds || 0) + ' remaining';
                }
                setStep('success');
                setStatus('Wallet linked successfully.', 'success');
                redirectTimer = window.setTimeout(function() {
                    window.location.href = redirectAfterLink || (browserBaseUrl + '/public/dashboard.php');
                }, 1400);
                return;
            }

            const sessionState = String(data.session_state || (session && session.status) || 'none').toLowerCase();
            if (['expired', 'revoked', 'none'].includes(sessionState) && !session) {
                if (sessionState === 'expired') {
                    showExpired('This RexLink QR expired. Create a fresh code.');
                } else {
                    setStatus('Waiting for RexLink to connect this browser.', '');
                }
                return;
            }
            setStatus('Waiting for RexLink to connect this browser.', '');
        }).catch(function(error) {
            if (linkCompleted) return;
            setStatus(error.message || 'Could not check RexLink status.', 'error');
            if (primaryButton) {
                primaryButton.hidden = false;
            }
        }).finally(function() {
            statusRequestInFlight = false;
        });
    }

    function createPairing() {
        if (pairingRequestInFlight) return;
        pairingRequestInFlight = true;
        resetModal();
        setStatus('Creating RexLink code...', '');
        if (primaryButton) {
            primaryButton.hidden = false;
            primaryButton.disabled = true;
            primaryButton.textContent = 'Generating QR...';
        }

        fetch(createPairingUrl, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                purpose: 'claim',
                duration_minutes: 10,
                dapp_name: 'CoinRex',
                dapp_url: browserBaseUrl,
            }),
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (!data || !data.success) {
                throw new Error((data && data.message) || 'Could not create RexLink code.');
            }
            if (data.already_connected && data.session && data.session.wallet_address) {
                linkCompleted = true;
                stopPolling();
                stopCountdown();
                if (successMessage) {
                    successMessage.textContent = 'Wallet ' + shortAddress(String(data.session.wallet_address)) + ' is already linked to this account. Redirecting...';
                }
                setStep('success');
                setStatus('Wallet already linked.', 'success');
                redirectTimer = window.setTimeout(function() {
                    window.location.href = redirectAfterLink || (browserBaseUrl + '/public/dashboard.php');
                }, 1200);
                return;
            }
            pairingId = Number(data.pairing_id || 0);
            if (pairingCode) {
                pairingCode.textContent = data.display_code || 'Code ready';
            }
            if (data.qr_payload) {
                const qrPayload = Object.assign({}, data.qr_payload || {}, {
                    purpose: 'claim',
                    base_url: String(data.qr_payload.base_url || rexlinkApiBaseUrl).replace(/\/+$/, ''),
                    api_base_url: String(data.qr_payload.api_base_url || rexlinkApiBaseUrl).replace(/\/+$/, '')
                });
                renderQrPayload(qrPayload);
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
            startCountdown(data.expires_in_seconds || 300);
            setStatus('Open RexLink and connect with this QR or code.', '');
            setStep('link');
            stopPolling();
            pollTimer = window.setInterval(pollLinkStatus, 1500);
            pollLinkStatus();
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

    window.addEventListener('rexlink:session-disconnected', function() {
        if (!modal || modal.hidden) return;
        pollLinkStatus();
    });
})();