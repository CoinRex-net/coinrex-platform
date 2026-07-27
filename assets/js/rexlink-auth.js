const cfg = window.CoinRexAuthConfig || {};
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const loginContainer = document.getElementById('loginForm');
    const registerContainer = document.getElementById('registerForm');
    const slider = document.querySelector('.auth-tab-slider');
    const registerForm = document.getElementById('registerAuthForm');
    const deviceFingerprintField = document.getElementById('deviceFingerprintField');
    const rexSignerAuthButtons = Array.from(document.querySelectorAll('.rex-signer-auth-trigger'));
    const rexLinkModal = document.getElementById('rexLinkModal');
    const rexLinkModalBackdrop = document.getElementById('rexLinkModalBackdrop');
    const rexLinkModalClose = document.getElementById('rexLinkModalClose');
    const rexLinkQrStep = document.getElementById('rexLinkQrStep');
    const rexLinkSuccessStep = document.getElementById('rexLinkSuccessStep');
    const rexLinkProgressDuration = document.getElementById('rexLinkProgressDuration');
    const rexLinkProgressSuccess = document.getElementById('rexLinkProgressSuccess');
    const rexLinkQrPlaceholder = document.getElementById('rexLinkQrPlaceholder');
    const rexLinkQrImage = document.getElementById('rexLinkQrImage');
    const rexLinkQrLogoBadge = document.getElementById('rexLinkQrLogoBadge');
    const rexLinkPairingCode = document.getElementById('rexLinkPairingCode');
    const rexLinkCopyCodeButton = document.getElementById('rexLinkCopyCodeButton');
    const rexLinkCountdown = document.getElementById('rexLinkCountdown');
    const rexLinkSessionNote = document.getElementById('rexLinkSessionNote');
    const rexLinkStatus = document.getElementById('rexLinkStatus');
    const rexLinkSuccessMessage = document.getElementById('rexLinkSuccessMessage');
    const rexLinkSuccessCountdown = document.getElementById('rexLinkSuccessCountdown');
    const rexLinkPrimaryButton = document.getElementById('rexLinkPrimaryButton');
    const rexlinkApiBaseUrl = String(cfg.browserBaseUrl || cfg.baseUrl || cfg.rexlinkApiBaseUrl || window.location.origin).replace(/\/+$/, '');
    const rexlinkFallbackBaseUrl = String(cfg.browserBaseUrl || cfg.baseUrl || window.location.origin).replace(/\/+$/, '');
    const rexSignerCreatePairingUrl = rexlinkApiBaseUrl + '/api/rex-signer/create_pairing.php';
    const rexSignerPairingQrUrl = rexlinkApiBaseUrl + '/api/rex-signer/pairing_qr.php';
    const rexSignerLoginFromSessionUrl = rexlinkApiBaseUrl + '/api/rex-signer/auth/login_from_session.php';
    const authRedirectTo = String(cfg.authRedirectTo || '');
    const rexLinkReferralCode = String(cfg.rexLinkReferralCode || '');
    const rexLinkAuthAccessible = Boolean(cfg.rexLinkAuthAccessible);
    const pairing = window.CoinRexPairing || {};
    let rexSignerAuthPollTimer = null;
    let rexLinkCountdownTimer = null;
    let rexLinkRedirectTimer = null;
    let rexLinkAuthCompleted = false;
    let rexLinkStatusRequestInFlight = false;
    let rexLinkPairingRequestInFlight = false;
    let rexLinkAuthPairingId = 0;
    function buildDeviceFingerprint() {
        const nav = window.navigator || {};
        const screenInfo = window.screen || {};
        const timezone = (Intl.DateTimeFormat && Intl.DateTimeFormat().resolvedOptions().timeZone) || '';
        const raw = [
            nav.userAgent || '',
            nav.language || '',
            (nav.languages || []).join(','),
            String(screenInfo.width || ''),
            String(screenInfo.height || ''),
            String(screenInfo.colorDepth || ''),
            timezone,
            String(new Date().getTimezoneOffset()),
            String(nav.hardwareConcurrency || ''),
            String(nav.platform || ''),
        ].join('|');

        let hash = 0;
        for (let i = 0; i < raw.length; i += 1) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }

        return 'fp_' + Math.abs(hash).toString(16) + '_' + btoa(raw).slice(0, 24).replace(/[^a-zA-Z0-9]/g, '');
    }

    if (deviceFingerprintField) {
        deviceFingerprintField.value = buildDeviceFingerprint();
    }

    function rexLinkSetStatus(message, state) {
        if (!rexLinkStatus) {
            return;
        }
        rexLinkStatus.textContent = message;
        rexLinkStatus.classList.toggle('is-error', state === 'error');
        rexLinkStatus.classList.toggle('is-success', state === 'success');
    }

    function rexLinkFallbackUrl(url) {
        const value = String(url || '');
        return rexlinkFallbackBaseUrl && rexlinkApiBaseUrl && value.indexOf(rexlinkApiBaseUrl) === 0
            ? rexlinkFallbackBaseUrl + value.slice(rexlinkApiBaseUrl.length)
            : value;
    }

    function rexAuthFetchJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        }).then(function(response) {
            return response.json();
        });
    }

    function rexAuthPostJson(url, body) {
        return rexAuthFetchJson(url, body).catch(function(error) {
            const fallbackUrl = rexLinkFallbackUrl(url);
            if (fallbackUrl !== url) {
                return rexAuthFetchJson(fallbackUrl, body);
            }
            const message = error && error.message ? error.message : 'network error';
            throw new Error('RexLink API is not reachable at ' + url + ' (' + message + '). Open CoinRex with the current LAN URL and make sure the API host in the QR is reachable from your phone.');
        });
    }

    function rexAuthStopPolling() {
        if (rexSignerAuthPollTimer) {
            window.clearInterval(rexSignerAuthPollTimer);
            rexSignerAuthPollTimer = null;
        }
    }

    function rexLinkStopCountdown() {
        if (rexLinkCountdownTimer) {
            window.clearInterval(rexLinkCountdownTimer);
            rexLinkCountdownTimer = null;
        }
    }

    function rexLinkShortAddress(address) {
        return pairing.shortAddress ? pairing.shortAddress(address) : String(address || '');
    }

    function rexLinkFormatClock(seconds) {
        if (pairing.formatClock) {
            return pairing.formatClock(seconds);
        }
        const safeSeconds = Math.max(0, Number(seconds || 0));
        return Math.floor(safeSeconds / 60) + ':' + String(safeSeconds % 60).padStart(2, '0');
    }

    function rexLinkSetStep(step) {
        [rexLinkQrStep, rexLinkSuccessStep].forEach(function(element) {
            if (element) {
                element.classList.remove('is-active');
            }
        });
        if (step === 'link' && rexLinkQrStep) {
            rexLinkQrStep.classList.add('is-active');
        }
        if (step === 'success' && rexLinkSuccessStep) {
            rexLinkSuccessStep.classList.add('is-active');
        }

        if (rexLinkProgressDuration) {
            rexLinkProgressDuration.classList.toggle('is-active', step === 'link');
            rexLinkProgressDuration.classList.toggle('is-complete', step === 'success');
        }
        if (rexLinkProgressSuccess) {
            rexLinkProgressSuccess.classList.toggle('is-active', step === 'success');
        }
        if (rexLinkPrimaryButton) {
            if (step !== 'link') {
                rexLinkPrimaryButton.hidden = true;
            }
        }
    }

    function rexLinkResetQr() {
        rexAuthStopPolling();
        rexLinkStopCountdown();
        rexLinkAuthCompleted = false;
        rexLinkAuthPairingId = 0;
        rexLinkStatusRequestInFlight = false;
        if (rexLinkRedirectTimer) {
            window.clearTimeout(rexLinkRedirectTimer);
            rexLinkRedirectTimer = null;
        }
        if (rexLinkQrImage) {
            rexLinkQrImage.hidden = true;
            rexLinkQrImage.onload = null;
            rexLinkQrImage.onerror = null;
            rexLinkQrImage.removeAttribute('src');
        }
        if (rexLinkQrPlaceholder) {
            rexLinkQrPlaceholder.hidden = false;
            rexLinkQrPlaceholder.classList.remove('is-rendered');
            rexLinkQrPlaceholder.innerHTML = '';
        }
        if (rexLinkQrLogoBadge) {
            rexLinkQrLogoBadge.classList.remove('is-visible');
        }
        if (rexLinkPairingCode) {
            rexLinkPairingCode.textContent = 'No code yet';
        }
        if (rexLinkCopyCodeButton) {
            rexLinkCopyCodeButton.disabled = true;
            rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
        }
        if (rexLinkCountdown) {
            rexLinkCountdown.textContent = 'Waiting for code';
        }
        if (rexLinkSessionNote) {
            rexLinkSessionNote.textContent = "You'll be paired with CoinRex for 10 minutes after linking.";
        }
        if (rexLinkSuccessCountdown) {
            rexLinkSuccessCountdown.textContent = 'RexLink session is active.';
        }
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = true;
            rexLinkPrimaryButton.disabled = false;
            rexLinkPrimaryButton.textContent = 'Generate New QR';
        }
        rexLinkSetStatus('Creating RexLink code...', '');
    }

    function rexLinkOpenModal() {
        if (!rexLinkModal) {
            return;
        }
        rexLinkResetQr();
        rexLinkSetStep('link');
        rexLinkModal.hidden = false;
        document.body.style.overflow = 'hidden';
        rexLinkCreatePairing();
    }

    function rexLinkCloseModal() {
        rexAuthStopPolling();
        rexLinkStopCountdown();
        if (rexLinkRedirectTimer) {
            window.clearTimeout(rexLinkRedirectTimer);
            rexLinkRedirectTimer = null;
        }
        if (rexLinkModal) {
            rexLinkModal.hidden = true;
        }
        document.body.style.overflow = '';
    }

    function rexLinkShowExpired(message) {
        if (rexLinkAuthCompleted) {
            return;
        }
        rexAuthStopPolling();
        rexLinkStopCountdown();
        rexLinkSetStatus(message || 'This RexLink QR expired. Create a fresh code.', 'error');
        if (rexLinkCountdown) {
            rexLinkCountdown.textContent = 'QR expired';
        }
        if (rexLinkQrImage) {
            rexLinkQrImage.hidden = true;
            rexLinkQrImage.onload = null;
            rexLinkQrImage.onerror = null;
            rexLinkQrImage.removeAttribute('src');
        }
        if (rexLinkQrLogoBadge) {
            rexLinkQrLogoBadge.classList.remove('is-visible');
        }
        if (rexLinkQrPlaceholder) {
            rexLinkQrPlaceholder.hidden = false;
            rexLinkQrPlaceholder.classList.remove('is-rendered');
            rexLinkQrPlaceholder.innerHTML = '<span class="rexlink-expired-placeholder"><i class="fas fa-clock"></i><strong>QR expired</strong><small>Generate a new QR to keep pairing.</small></span>';
        }
        if (rexLinkPairingCode) {
            rexLinkPairingCode.textContent = 'QR expired';
        }
        if (rexLinkCopyCodeButton) {
            rexLinkCopyCodeButton.disabled = true;
            rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
        }
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = false;
            rexLinkPrimaryButton.disabled = false;
            rexLinkPrimaryButton.textContent = 'Generate New QR';
        }
    }

    function rexLinkStartCountdown(seconds) {
        let remaining = Math.max(0, Number(seconds || 300));
        rexLinkStopCountdown();
        const updateCountdown = function() {
            const minutes = Math.floor(remaining / 60);
            const secs = String(remaining % 60).padStart(2, '0');
            if (rexLinkCountdown) {
                rexLinkCountdown.textContent = 'QR expires in ' + minutes + 'm ' + secs + 's';
            }
            if (remaining <= 0) {
                rexLinkShowExpired('This RexLink QR expired. Create a fresh code.');
                return;
            }
            remaining -= 1;
        };
        updateCountdown();
        rexLinkCountdownTimer = window.setInterval(updateCountdown, 1000);
    }

    function rexAuthPollStatus() {
        if (rexLinkAuthCompleted || rexLinkStatusRequestInFlight) {
            return;
        }
        rexLinkStatusRequestInFlight = true;
        rexAuthPostJson(rexSignerLoginFromSessionUrl, { pairing_id: rexLinkAuthPairingId })
            .then(function(data) {
                if (rexLinkAuthCompleted) {
                    return;
                }
                if (!data.success) {
                    throw new Error(data.message || 'Could not check RexLink status.');
                }
                const status = String(data.status || 'pending');
                if (status === 'authenticated') {
                    rexLinkAuthCompleted = true;
                    rexAuthStopPolling();
                    rexLinkStopCountdown();
                    window.clearTimeout(rexLinkRedirectTimer);
                    if (rexLinkSuccessMessage) {
                        const wallet = rexLinkShortAddress(data.wallet_address || data.wallet || '');
                        rexLinkSuccessMessage.textContent = wallet
                            ? 'Wallet ' + wallet + ' connected. Signing you in...'
                            : 'RexLink connected. Signing you in...';
                    }
                    if (rexLinkSuccessCountdown) {
                        rexLinkSuccessCountdown.textContent = 'RexLink session: ' + rexLinkFormatClock(data.session_remaining_seconds || 0) + ' remaining';
                    }
                    rexLinkSetStep('success');
                    rexLinkRedirectTimer = window.setTimeout(function() {
                        window.location.href = data.redirect_url || authRedirectTo;
                    }, 1200);
                    return;
                }
                if (status === 'expired' || status === 'revoked' || status === 'none') {
                    rexLinkShowExpired(status === 'expired' ? 'This RexLink QR expired. Create a fresh code.' : (data.message || 'This RexLink request is no longer active.'));
                    return;
                }
                rexLinkSetStatus('Waiting for RexLink to connect this browser.', '');
            })
            .catch(function(error) {
                if (rexLinkAuthCompleted) {
                    return;
                }
                rexLinkSetStatus(error.message || 'Could not check RexLink status.', 'error');
                if (rexLinkPrimaryButton) {
                    rexLinkPrimaryButton.hidden = false;
                }
            })
            .finally(function() {
                rexLinkStatusRequestInFlight = false;
            });
    }

    function rexLinkRenderQrPayload(qrPayload) {
        if (!rexLinkQrPlaceholder || !qrPayload) {
            return;
        }

        if (pairing.renderQr) {
            pairing.renderQr(qrPayload, {
                placeholder: rexLinkQrPlaceholder,
                image: rexLinkQrImage,
                logoBadge: rexLinkQrLogoBadge,
                fallbackUrl: rexSignerPairingQrUrl,
                fallbackText: 'QR could not load. Use the code below.',
                payloadDefaults: {
                    purpose: 'auth',
                    apiBaseUrl: rexlinkApiBaseUrl,
                    baseUrl: rexlinkApiBaseUrl,
                    dappName: 'CoinRex',
                    dappUrl: cfg.baseUrl || window.location.origin,
                    durationMinutes: 10,
                },
            }).then(function(rendered) {
                if (!rendered) {
                    rexLinkSetStatus('QR could not load. Use the code below.', 'error');
                }
            });
            return;
        }

        rexLinkQrPlaceholder.innerHTML = '<span>Use the code below.</span>';
    }

    function rexLinkCreatePairing() {
        if (rexLinkPairingRequestInFlight) {
            return;
        }
        rexLinkPairingRequestInFlight = true;
        rexLinkResetQr();
        rexLinkSetStep('link');
        rexLinkSetStatus('Creating RexLink code...', '');
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = false;
            rexLinkPrimaryButton.disabled = true;
            rexLinkPrimaryButton.textContent = 'Generating QR...';
        }

        rexAuthPostJson(rexSignerCreatePairingUrl, {
            purpose: 'auth',
            duration_minutes: 10,
            referral_code: rexLinkReferralCode,
            device_fingerprint: deviceFingerprintField ? deviceFingerprintField.value : '',
        }).then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'Could not create RexLink code.');
            }
            rexLinkAuthPairingId = Number(data.pairing_id || 0);
            if (rexLinkPairingCode) {
                rexLinkPairingCode.textContent = data.display_code || 'Code ready';
            }
            if (data.qr_payload) {
                const qrPayload = Object.assign({}, data.qr_payload || {}, {
                    purpose: 'auth',
                    base_url: data.qr_payload.base_url || rexlinkApiBaseUrl,
                    api_base_url: data.qr_payload.api_base_url || rexlinkApiBaseUrl
                });
                rexLinkRenderQrPayload(qrPayload);
            }
            if (rexLinkQrPlaceholder) {
                rexLinkQrPlaceholder.hidden = !data.qr_payload;
            }
            if (rexLinkQrLogoBadge) {
                rexLinkQrLogoBadge.classList.remove('is-visible');
            }
            if (rexLinkCopyCodeButton) {
                rexLinkCopyCodeButton.disabled = !data.display_code;
            }
            if (rexLinkPrimaryButton) {
                rexLinkPrimaryButton.hidden = true;
                rexLinkPrimaryButton.disabled = false;
                rexLinkPrimaryButton.textContent = 'Generate New QR';
            }
            if (rexLinkSessionNote) {
                rexLinkSessionNote.textContent = "You'll be paired with CoinRex for 10 minutes after linking.";
            }
            rexLinkStartCountdown(data.expires_in_seconds || 300);
            rexLinkSetStatus('Open RexLink and connect with this QR or code.', '');
            rexLinkSetStep('link');
            rexSignerAuthPollTimer = window.setInterval(rexAuthPollStatus, 1000);
            rexAuthPollStatus();
        }).catch(function(error) {
            rexLinkSetStatus(error.message || 'RexLink sign-in could not start.', 'error');
            rexLinkSetStep('link');
            if (rexLinkQrPlaceholder) {
                rexLinkQrPlaceholder.hidden = false;
                rexLinkQrPlaceholder.classList.remove('is-rendered');
                rexLinkQrPlaceholder.innerHTML = '';
            }
            if (rexLinkQrLogoBadge) {
                rexLinkQrLogoBadge.classList.remove('is-visible');
            }
            if (rexLinkPrimaryButton) {
                rexLinkPrimaryButton.hidden = false;
                rexLinkPrimaryButton.disabled = false;
                rexLinkPrimaryButton.textContent = 'Generate New QR';
            }
        }).finally(function() {
            rexLinkPairingRequestInFlight = false;
        });
    }

    rexSignerAuthButtons.forEach(function(button) {
        button.addEventListener('click', function(event) {
            if (!rexLinkAuthAccessible || button.disabled) {
                event.preventDefault();
                return;
            }
            rexLinkOpenModal();
        });
    });

    if (rexLinkCopyCodeButton) {
        rexLinkCopyCodeButton.addEventListener('click', function() {
            const code = rexLinkPairingCode ? rexLinkPairingCode.textContent.trim() : '';
            if (!code || code === 'No code yet') {
                return;
            }
            if (pairing.copyText) {
                pairing.copyText(code, rexLinkCopyCodeButton, 1400);
                return;
            }
            rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-check"></i>';
            window.setTimeout(function() {
                rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
            }, 1400);
        });
    }

    if (rexLinkPrimaryButton) {
        rexLinkPrimaryButton.addEventListener('click', function() {
            rexLinkCreatePairing();
        });
    }

    window.addEventListener('rexlink:session-connected', function() {
        if (!rexLinkModal || rexLinkModal.hidden) {
            return;
        }
        rexAuthPollStatus();
    });

    [rexLinkModalBackdrop, rexLinkModalClose].forEach(function(element) {
        if (element) {
            element.addEventListener('click', rexLinkCloseModal);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && rexLinkModal && !rexLinkModal.hidden) {
            rexLinkCloseModal();
        }
    });

    const registerSubmitButton = document.getElementById('registerSubmitButton');
    const registerTermsCheckbox = document.getElementById('registerTermsCheckbox');
    const regEmail = document.getElementById('regEmail');
    const regPassword = document.getElementById('regPassword');
    const regConfirmPassword = document.getElementById('regConfirmPassword');
    const regReferral = document.getElementById('regReferral');
    const emailFeedback = document.getElementById('emailFeedback');
    const passwordFeedback = document.getElementById('passwordFeedback');
    const confirmFeedback = document.getElementById('confirmFeedback');
    const referralFeedback = document.getElementById('referralFeedback');
    const passwordChecklist = document.getElementById('passwordChecklist');
    const passwordFieldGroup = document.getElementById('passwordFieldGroup');
    const confirmPasswordFieldGroup = regConfirmPassword ? regConfirmPassword.closest('.input-field') : null;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const referralPattern = /^[A-Z0-9]{6,16}$/;
    let emailTimer = null;
    let emailRequestId = 0;
    let referralTimer = null;
    let referralRequestId = 0;
    
    function switchTab(tabId) {
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });
        
        if (tabId === 'login') {
            loginContainer.classList.add('active');
            registerContainer.classList.remove('active');
        } else {
            registerContainer.classList.add('active');
            loginContainer.classList.remove('active');
        }

        togglePasswordChecklist(false);
        
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
        
        const activeTab = document.querySelector('.auth-tab.active');
        if (activeTab && slider) {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = activeTab.parentElement.getBoundingClientRect();
            slider.style.width = tabRect.width + 'px';
            slider.style.transform = `translateX(${tabRect.left - containerRect.left}px)`;
        }
    }

    function setFieldState(input, state) {
        const control = input ? input.closest('.input-control') : null;
        if (!control) {
            return;
        }

        control.classList.remove('is-valid', 'is-invalid', 'is-checking');
        if (state) {
            control.classList.add(state);
        }
    }

    function setFeedback(element, status, message) {
        if (!element) {
            return;
        }

        element.classList.remove('is-valid', 'is-invalid', 'is-checking');
        element.textContent = message || '';

        if (status) {
            element.classList.add(status);
        }
    }

    function syncRegisterSubmitState() {
        if (!registerSubmitButton || !registerTermsCheckbox) {
            return;
        }

        registerSubmitButton.disabled = !registerTermsCheckbox.checked;
    }

    function updateRequirement(rule, passed) {
        const item = passwordChecklist ? passwordChecklist.querySelector(`[data-rule="${rule}"]`) : null;
        if (!item) {
            return;
        }

        const icon = item.querySelector('i');
        item.classList.toggle('passed', passed);
        item.classList.toggle('failed', !passed && regPassword.value.length > 0);

        if (icon) {
            icon.classList.toggle('fa-check-circle', passed);
            icon.classList.toggle('fa-times-circle', !passed && regPassword.value.length > 0);
            icon.classList.toggle('fa-circle', regPassword.value.length === 0);
        }
    }

    function togglePasswordChecklist(shouldShow) {
        if (!passwordFieldGroup) {
            return;
        }

        passwordFieldGroup.classList.toggle('show-password-modal', shouldShow);
    }

    function syncPasswordChecklistVisibility() {
        const activeElement = document.activeElement;
        const shouldShow = Boolean(
            activeElement
            && passwordFieldGroup
            && passwordFieldGroup.contains(activeElement)
        );

        togglePasswordChecklist(shouldShow);
    }

    function validatePasswordField() {
        if (!regPassword) {
            return false;
        }

        const password = regPassword.value;
        const checks = {
            length: password.length >= 9,
            uppercase: /[A-Z]/.test(password),
            digit: /\d/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        Object.entries(checks).forEach(([rule, passed]) => updateRequirement(rule, passed));

        const isValid = Object.values(checks).every(Boolean);
        regPassword.setCustomValidity(password && !isValid ? 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character.' : '');

        if (!password) {
            setFieldState(regPassword, '');
            setFeedback(passwordFeedback, '', '');
        } else {
            setFieldState(regPassword, isValid ? 'is-valid' : 'is-invalid');
            setFeedback(
                passwordFeedback,
                isValid ? 'is-valid' : 'is-invalid',
                isValid ? 'Valid Password' : 'Invalid Password Format'
            );
        }

        return isValid;
    }

    function validateConfirmPasswordField() {
        if (!regPassword || !regConfirmPassword) {
            return false;
        }

        const confirmValue = regConfirmPassword.value;
        if (!confirmValue) {
            regConfirmPassword.setCustomValidity('');
            setFieldState(regConfirmPassword, '');
            setFeedback(confirmFeedback, '', '');
            return false;
        }

        const matches = regPassword.value === confirmValue;
        regConfirmPassword.setCustomValidity(matches ? '' : 'Confirm password must match the password field.');
        setFieldState(regConfirmPassword, matches ? 'is-valid' : 'is-invalid');
        setFeedback(confirmFeedback, matches ? 'is-valid' : 'is-invalid', matches ? 'Passwords match' : 'Passwords do not match');

        return matches;
    }

    async function validateEmailField() {
        if (!regEmail) {
            return false;
        }

        const email = regEmail.value.trim().toLowerCase();
        emailRequestId += 1;
        const requestId = emailRequestId;

        if (!email) {
            regEmail.setCustomValidity('');
            setFieldState(regEmail, '');
            setFeedback(emailFeedback, '', '');
            return false;
        }

        if (!emailPattern.test(email)) {
            regEmail.setCustomValidity('Please enter a valid email address.');
            setFieldState(regEmail, 'is-invalid');
            setFeedback(emailFeedback, 'is-invalid', 'Please enter a valid email address');
            return false;
        }

        regEmail.value = email;
        regEmail.setCustomValidity('');
        setFieldState(regEmail, 'is-checking');
        setFeedback(emailFeedback, 'is-checking', 'Checking email availability...');

        try {
            const checkUrl = new URL(window.location.href);
            checkUrl.search = '';
            checkUrl.searchParams.set('check_email', '1');
            checkUrl.searchParams.set('email', email);

            const response = await fetch(checkUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            if (requestId !== emailRequestId) {
                return false;
            }

            if (!result.valid || result.exists || result.disposable) {
                regEmail.setCustomValidity(result.message || 'This email is already registered.');
                setFieldState(regEmail, 'is-invalid');
                setFeedback(emailFeedback, 'is-invalid', result.message || 'This email is not allowed');
                return false;
            }

            regEmail.setCustomValidity('');
            setFieldState(regEmail, 'is-valid');
            setFeedback(emailFeedback, 'is-valid', result.message || 'Email is available');
            return true;
        } catch (error) {
            regEmail.setCustomValidity('');
            setFieldState(regEmail, '');
            setFeedback(emailFeedback, '', 'We will recheck this email on submit');
            return true;
        }
    }

    async function validateReferralField() {
        if (!regReferral) {
            return true;
        }

        const referralCode = regReferral.value.trim().toUpperCase();
        referralRequestId += 1;
        const requestId = referralRequestId;

        regReferral.value = referralCode;

        if (!referralCode) {
            regReferral.setCustomValidity('');
            setFieldState(regReferral, '');
            setFeedback(referralFeedback, '', '');
            return true;
        }

        if (!referralPattern.test(referralCode)) {
            regReferral.setCustomValidity('Referral code format is invalid.');
            setFieldState(regReferral, 'is-invalid');
            setFeedback(referralFeedback, 'is-invalid', 'Referral code format is invalid');
            return false;
        }

        regReferral.setCustomValidity('');
        setFieldState(regReferral, 'is-checking');
        setFeedback(referralFeedback, 'is-checking', 'Checking referral code...');

        try {
            const checkUrl = new URL(window.location.href);
            checkUrl.search = '';
            checkUrl.searchParams.set('check_referral', '1');
            checkUrl.searchParams.set('referral_code', referralCode);

            const response = await fetch(checkUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            if (requestId !== referralRequestId) {
                return false;
            }

            if (!result.valid) {
                regReferral.setCustomValidity(result.message || 'Referral code not found.');
                setFieldState(regReferral, 'is-invalid');
                setFeedback(referralFeedback, 'is-invalid', result.message || 'Referral code not found');
                return false;
            }

            regReferral.setCustomValidity('');
            setFieldState(regReferral, 'is-valid');
            setFeedback(referralFeedback, 'is-valid', result.message || 'Referral code applied successfully');
            return true;
        } catch (error) {
            regReferral.setCustomValidity('');
            setFieldState(regReferral, '');
            setFeedback(referralFeedback, '', 'We will verify this referral code on submit');
            return true;
        }
    }
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });
    
    setTimeout(() => {
        const activeTab = document.querySelector('.auth-tab.active');
        if (activeTab && slider) {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = activeTab.parentElement.getBoundingClientRect();
            slider.style.width = tabRect.width + 'px';
            slider.style.transform = `translateX(${tabRect.left - containerRect.left}px)`;
        }
    }, 100);
    
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    document.querySelectorAll('.input-field input').forEach(input => {
        input.addEventListener('focus', () => {
            const control = input.closest('.input-control');
            if (control) {
                control.classList.add('focused');
            }
        });
        input.addEventListener('blur', () => {
            const control = input.closest('.input-control');
            if (control && !input.value) {
                control.classList.remove('focused');
            }
        });
        if (input.value) {
            const control = input.closest('.input-control');
            if (control) {
                control.classList.add('focused');
            }
        }
    });

    if (regPassword) {
        regPassword.addEventListener('focus', syncPasswordChecklistVisibility);
        regPassword.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        regPassword.addEventListener('input', function() {
            validatePasswordField();
            validateConfirmPasswordField();
        });
        validatePasswordField();
    }

    if (regConfirmPassword) {
        regConfirmPassword.addEventListener('focus', function() {
            togglePasswordChecklist(false);
        });
        regConfirmPassword.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        regConfirmPassword.addEventListener('input', validateConfirmPasswordField);
    }

    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('focus', syncPasswordChecklistVisibility);
        button.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
    });

    if (regEmail) {
        regEmail.addEventListener('input', function() {
            clearTimeout(emailTimer);
            setFeedback(emailFeedback, '', '');
            setFieldState(regEmail, '');
            regEmail.setCustomValidity('');

            if (!regEmail.value.trim()) {
                return;
            }

            emailTimer = setTimeout(() => {
                validateEmailField();
            }, 300);
        });

        regEmail.addEventListener('blur', function() {
            clearTimeout(emailTimer);
            validateEmailField();
        });

        if (regEmail.value.trim()) {
            validateEmailField();
        }
    }

    if (regReferral) {
        regReferral.addEventListener('input', function() {
            clearTimeout(referralTimer);
            regReferral.value = regReferral.value.toUpperCase();
            setFeedback(referralFeedback, '', '');
            setFieldState(regReferral, '');
            regReferral.setCustomValidity('');

            if (!regReferral.value.trim()) {
                return;
            }

            referralTimer = setTimeout(() => {
                validateReferralField();
            }, 300);
        });

        regReferral.addEventListener('blur', function() {
            clearTimeout(referralTimer);
            validateReferralField();
        });

        if (regReferral.value.trim()) {
            validateReferralField();
        }
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async function(event) {
            syncRegisterSubmitState();
            if (registerSubmitButton && registerSubmitButton.disabled) {
                return;
            }

            event.preventDefault();
            switchTab('register');

            const passwordValid = validatePasswordField();
            const confirmValid = validateConfirmPasswordField();
            const emailValid = await validateEmailField();
            const referralValid = await validateReferralField();

            if (!registerForm.reportValidity()) {
                return;
            }

            if (passwordValid && confirmValid && emailValid && referralValid) {
                HTMLFormElement.prototype.submit.call(registerForm);
            }
        });
    }

    if (registerTermsCheckbox) {
        registerTermsCheckbox.addEventListener('change', syncRegisterSubmitState);
        syncRegisterSubmitState();
    }

    syncPasswordChecklistVisibility();
});
