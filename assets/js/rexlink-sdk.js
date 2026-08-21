/**
 * RexLink SDK v1.0
 * Single-file frontend SDK for RexLink pairing.
 * Supports WebSocket realtime + HTTP polling fallback.
 * Auto-detects transport. 500ms polling during pairing for fast completion.
 */
(function(window) {
    'use strict';

    var state = {
        apiBaseUrl: '',
        appId: 'coinrex',
        transport: 'auto', // 'auto' | 'websocket' | 'polling'
        wsConnected: false,
        realtimeSocket: null,
        realtimeReconnectTimer: null,
        realtimePingTimer: null,
        realtimeReconnectDelay: 1000,
        pollingTimers: {},
        initialized: false,
        webActorToken: '',
        requestTimeoutMs: 3000,
        pairingStatusTokens: {},
    };

    function sleep(ms) {
        return new Promise(function(resolve) { setTimeout(resolve, ms); });
    }

    function apiFetch(path, opts) {
        opts = opts || {};
        if (!state.apiBaseUrl) {
            state.apiBaseUrl = window.location.protocol + '//' + window.location.hostname + ':18083';
        }
        var url = state.apiBaseUrl + path;
        var method = opts.method || (opts.body ? 'POST' : 'GET');
        var timeoutMs = Math.max(0, Number(opts.timeoutMs || state.requestTimeoutMs || 0));
        var controller = timeoutMs > 0 && 'AbortController' in window ? new AbortController() : null;
        var timeoutId = controller ? window.setTimeout(function() { controller.abort(); }, timeoutMs) : null;
        var fetchOpts = {
            method: method,
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-RexLink-App-ID': state.appId,
            },
            cache: 'no-store',
            signal: controller ? controller.signal : undefined,
        };
        if (state.webActorToken) {
            fetchOpts.headers['X-CoinRex-Web-Actor'] = state.webActorToken;
        }
        if (opts.body) {
            fetchOpts.body = JSON.stringify(opts.body);
        }
        return fetch(url, fetchOpts).then(function(res) {
            return res.json().then(function(data) {
                if (!res.ok || data.success === false) {
                    var err = new Error(data.message || ('RexLink request failed (' + res.status + ').'));
                    err.data = data;
                    err.status = res.status;
                    throw err;
                }
                return data;
            });
        }).catch(function(error) {
            if (error && error.name === 'AbortError') {
                throw new Error('RexLink did not respond within ' + timeoutMs + 'ms.');
            }
            throw error;
        }).finally(function() {
            if (timeoutId) window.clearTimeout(timeoutId);
        });
    }

    function fire(eventName, detail) {
        try {
            var event = new CustomEvent(eventName, { detail: detail });
            document.dispatchEvent(event);
        } catch (e) {}
    }

    // ── QR Rendering (wraps CoinRexPairing) ──────────────────────────

    function renderQR(payload, container, opts) {
        opts = opts || {};
        if (window.CoinRexPairing && window.CoinRexPairing.renderQr) {
            var el = typeof container === 'string' ? document.querySelector(container) : container;
            if (!el) return Promise.resolve(false);
            return window.CoinRexPairing.renderQr(payload, {
                placeholder: el,
                image: opts.image || null,
                logoBadge: opts.logoBadge || null,
                qrOptions: opts.qrOptions || {},
                preferCanvas: opts.preferCanvas !== false,
                fallbackUrl: opts.fallbackUrl || (state.apiBaseUrl + '/api/v1/pairing/qr'),
                fallbackText: 'Use the 6 digit code below.',
            });
        }
        return Promise.resolve(false);
    }

    // ── Transport: WebSocket ─────────────────────────────────────────

    function connectRealtime() {
        if (state.transport === 'polling') return Promise.resolve(null);
        if (!('WebSocket' in window)) {
            state.transport = 'polling';
            fire('rexlink:transport-change', { transport: 'polling' });
            return Promise.resolve(null);
        }
        if (state.realtimeSocket && [WebSocket.CONNECTING, WebSocket.OPEN].indexOf(state.realtimeSocket.readyState) !== -1) {
            return Promise.resolve(state.realtimeSocket);
        }

        return apiFetch('/api/v1/realtime/auth').then(function(data) {
            if (!data.ws_url || !data.token) return null;
            var wsUrl = data.ws_url;
            var sep = wsUrl.indexOf('?') !== -1 ? '&' : '?';
            var fullUrl = wsUrl + sep + 'token=' + encodeURIComponent(data.token);
            var ws = new WebSocket(fullUrl);

            ws.onopen = function() {
                state.wsConnected = true;
                state.realtimeReconnectDelay = 1000;
                fire('rexlink:transport-change', { transport: 'websocket' });
                // Start heartbeat ping
                clearInterval(state.realtimePingTimer);
                state.realtimePingTimer = setInterval(function() {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({ type: 'ping' }));
                    }
                }, 25000);
            };

            ws.onmessage = function(message) {
                try {
                    var evt = JSON.parse(message.data);
                    handleRealtimeEvent(evt);
                } catch (e) {}
            };

            ws.onclose = function() {
                state.wsConnected = false;
                clearInterval(state.realtimePingTimer);
                scheduleRealtimeReconnect();
            };

            ws.onerror = function() {
                state.wsConnected = false;
                if (state.transport === 'auto') {
                    state.transport = 'polling';
                    fire('rexlink:transport-change', { transport: 'polling' });
                }
            };

            state.realtimeSocket = ws;
            return ws;
        }).catch(function() {
            if (state.transport === 'auto') {
                state.transport = 'polling';
                fire('rexlink:transport-change', { transport: 'polling' });
            }
            return null;
        });
    }

    function scheduleRealtimeReconnect() {
        clearTimeout(state.realtimeReconnectTimer);
        state.realtimeReconnectTimer = setTimeout(function() {
            connectRealtime().catch(function() {});
        }, state.realtimeReconnectDelay);
        state.realtimeReconnectDelay = Math.min(state.realtimeReconnectDelay * 2, 15000);
    }

    function handleRealtimeEvent(evt) {
        if (!evt || !evt.type) return;
        if (evt.type === 'realtime.ready' || evt.type === 'pong') return;
        if (evt.type === 'session.connected') {
            fire('rexlink:session-connected', evt.payload || {});
        } else if (evt.type === 'session.revoked') {
            fire('rexlink:session-revoked', evt.payload || {});
        } else if (evt.type === 'approval.created') {
            fire('rexlink:approval-created', evt.payload || {});
        } else if (evt.type === 'approval.updated') {
            fire('rexlink:approval-updated', evt.payload || {});
        } else if (evt.type === 'claim.tx.updated') {
            fire('rexlink:claim-tx-updated', evt.payload || {});
        }
    }

    // ── Transport: HTTP Polling ──────────────────────────────────────

    function getPollInterval() {
        return state.wsConnected ? 12000 : 500;
    }

    function startPolling(type, callback, interval) {
        stopPolling(type);
        var iv = interval || getPollInterval();
        state.pollingTimers[type] = setInterval(function() {
            callback();
        }, iv);
    }

    function stopPolling(type) {
        if (state.pollingTimers[type]) {
            clearInterval(state.pollingTimers[type]);
            delete state.pollingTimers[type];
        }
    }

    function stopAllPolling() {
        Object.keys(state.pollingTimers).forEach(function(type) {
            stopPolling(type);
        });
    }

    // ── Core Pairing ────────────────────────────────────────────────

    function createPairing(opts) {
        opts = opts || {};
        var body = {
            purpose: opts.purpose || 'claim',
            duration_minutes: opts.durationMinutes || 10,
            app_id: opts.appId || state.appId,
            force_new_pairing: opts.forceNewPairing || false,
            meta: opts.meta || {},
        };
        if (opts.referralCode) body.referral_code = opts.referralCode;
        if (opts.requestedWalletAddress) body.requested_wallet_address = opts.requestedWalletAddress;
        if (Array.isArray(opts.networkSlugs)) {
            body.network_slugs = opts.networkSlugs.map(function(slug) {
                return String(slug || '').trim().toLowerCase();
            }).filter(Boolean);
        }

        return apiFetch('/api/v1/pairing/create', { body: body, timeoutMs: opts.timeoutMs }).then(function(data) {
            if (data && data.pairing_id && data.status_token) {
                state.pairingStatusTokens[String(data.pairing_id)] = String(data.status_token);
            }
            return data;
        });
    }

    function cancelPairing(pairingId) {
        var statusToken = String(state.pairingStatusTokens[String(pairingId)] || '');
        return apiFetch('/api/v1/pairing/cancel', {
            body: { pairing_id: pairingId, status_token: statusToken },
        }).finally(function() {
            delete state.pairingStatusTokens[String(pairingId)];
        });
    }

    /**
     * Poll pairing status with fast polling (500ms) for 3-5 second completion.
     * @param {number} pairingId
     * @param {object} opts - { interval, timeout, onStatus }
     * @returns {Promise} resolves with session/status data
     */
    function pollPairingStatus(pairingId, opts) {
        opts = opts || {};
        var interval = opts.interval || 500; // 500ms for fast pairing
        var timeout = opts.timeout || 300000; // 5 min
        var onStatus = opts.onStatus || function() {};
        var shouldContinue = opts.shouldContinue || function() { return true; };
        var statusToken = String(opts.statusToken || state.pairingStatusTokens[String(pairingId)] || '');
        var start = Date.now();

        function poll() {
            if (!shouldContinue()) return Promise.reject(new Error('Pairing watch cancelled.'));
            return apiFetch('/api/v1/sessions/status', { body: { pairing_id: pairingId, status_token: statusToken } }).then(function(data) {
                onStatus(data.status || 'pending');
                if (['authenticated', 'active', 'connected'].indexOf(data.status) !== -1) {
                    return data;
                }
                if (['expired', 'revoked', 'failed'].indexOf(data.status) !== -1) {
                    var terminalError = new Error(data.message || 'Pairing failed: ' + data.status);
                    terminalError.rexLinkTerminal = true;
                    throw terminalError;
                }
                if (Date.now() - start >= timeout) {
                    var timeoutError = new Error('Pairing timed out after ' + Math.round(timeout / 1000) + 's');
                    timeoutError.rexLinkTerminal = true;
                    throw timeoutError;
                }
                return sleep(interval).then(poll);
            }).catch(function(error) {
                if (error && error.rexLinkTerminal) throw error;
                if (Date.now() - start >= timeout) throw error;
                if (error && error.status && error.status < 500 && error.status !== 408 && error.status !== 429) throw error;
                onStatus('reconnecting');
                return sleep(Math.max(interval, 500)).then(poll);
            });
        }

        return poll();
    }

    // ── Auth Flow ───────────────────────────────────────────────────

    function loginWithPairing(opts) {
        opts = opts || {};
        var pairingId = opts.pairingId;
        var interval = opts.interval || 500; // 500ms for fast pairing
        var timeout = opts.timeout || 300000;
        var onStatus = opts.onStatus || function() {};
        var shouldContinue = opts.shouldContinue || function() { return true; };
        var statusToken = String(opts.statusToken || state.pairingStatusTokens[String(pairingId)] || '');
        var start = Date.now();

        function poll() {
            if (!shouldContinue()) return Promise.reject(new Error('Pairing watch cancelled.'));
            return apiFetch('/api/v1/sessions/status', { body: { pairing_id: pairingId, status_token: statusToken } }).then(function(data) {
                onStatus(data.status || 'pending');
                if (data.status === 'authenticated') {
                    return { redirectUrl: data.redirect_url, walletAddress: data.wallet_address, sessionId: data.session_id };
                }
                if (['expired', 'revoked', 'failed', 'none'].indexOf(data.status) !== -1) {
                    throw new Error(data.message || 'Login failed: ' + data.status);
                }
                if (Date.now() - start >= timeout) {
                    throw new Error('Login timed out');
                }
                return sleep(interval).then(poll);
            });
        }

        return poll();
    }

    // ── Session Management ──────────────────────────────────────────

    function getSessions() {
        return apiFetch('/api/v1/sessions');
    }

    function revokeSession(sessionId) {
        return apiFetch('/api/v1/sessions/revoke', { body: { session_id: sessionId } });
    }

    // ── Review Flow ─────────────────────────────────────────────────

    function createReviewPairing(opts) {
        opts = opts || {};
        var body = {
            duration_minutes: opts.durationMinutes || 10,
        };
        if (opts.forceNewPairing) body.force_new_pairing = true;
        if (Array.isArray(opts.networkSlugs)) body.network_slugs = opts.networkSlugs;
        return apiFetch('/api/v1/review/pairing/create', { body: body });
    }

    function pollReviewStatus(opts) {
        opts = opts || {};
        var pairingId = opts.pairingId || 0;
        var walletAddress = opts.walletAddress || '';
        var projectId = opts.projectId || 0;
        var interval = opts.interval || getPollInterval();
        var timeout = opts.timeout || 300000;
        var onStatus = opts.onStatus || function() {};
        var start = Date.now();

        function poll() {
            return apiFetch('/api/v1/review/wallet/status', {
                body: { pairing_id: pairingId, wallet_address: walletAddress, project_id: projectId }
            }).then(function(data) {
                onStatus(data.status || 'pending');
                if (data.status === 'connected') {
                    return data;
                }
                if (['expired', 'revoked', 'failed', 'wallet_used'].indexOf(data.status) !== -1) {
                    throw new Error(data.message || 'Review pairing failed: ' + data.status);
                }
                if (Date.now() - start >= timeout) {
                    throw new Error('Review pairing timed out');
                }
                return sleep(interval).then(poll);
            });
        }

        return poll();
    }

    // ── Claim Flow ──────────────────────────────────────────────────

    function createClaimApproval(opts) {
        opts = opts || {};
        return apiFetch('/api/v1/claims/approval', {
            body: { claim_amount: opts.claimAmount }
        });
    }

    function pollClaimStatus(requestId, opts) {
        opts = opts || {};
        var interval = opts.interval || (state.wsConnected ? 12000 : 1000);
        var timeout = opts.timeout || 600000;
        var onStatus = opts.onStatus || function() {};
        var start = Date.now();

        function poll() {
            var baseUrl = state.apiBaseUrl || (window.location.protocol + '//' + window.location.hostname + ':18083');
            var claimHeaders = { 'Content-Type': 'application/json' };
            if (state.webActorToken) {
                claimHeaders['X-CoinRex-Web-Actor'] = state.webActorToken;
            }
            return fetch(baseUrl + '/api/v1/claims/status?request_id=' + requestId, {
                credentials: 'include',
                headers: claimHeaders
            }).then(function(res) { return res.json(); }).then(function(data) {
                var approval = data.approval_request || data;
                var status = approval.status || 'pending';
                onStatus(status);
                if (['approved', 'rejected', 'expired', 'cancelled'].indexOf(status) !== -1) {
                    return approval;
                }
                if (Date.now() - start >= timeout) {
                    throw new Error('Claim approval timed out');
                }
                return sleep(interval).then(poll);
            });
        }

        return poll();
    }

    function decideClaim(requestId, decision, note) {
        return apiFetch('/api/v1/claims/decision', {
            body: { request_id: requestId, decision: decision, note: note || '' }
        });
    }

    function completeClaimTx(requestId, txHash, txStatus) {
        return apiFetch('/api/v1/claims/complete', {
            body: { request_id: requestId, tx_hash: txHash, tx_status: txStatus || 'submitted' }
        });
    }

    // ── Utilities ───────────────────────────────────────────────────

    function formatClock(seconds) {
        var s = Math.max(0, Math.floor(Number(seconds || 0)));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }

    function expiryLabel(seconds) {
        var s = Math.max(0, Math.floor(Number(seconds || 0)));
        return 'QR expires in ' + Math.floor(s / 60) + 'm ' + String(s % 60).padStart(2, '0') + 's';
    }

    function shortAddress(address) {
        var v = String(address || '');
        if (v.length <= 14) return v || 'Connected';
        return v.slice(0, 6) + '...' + v.slice(-4);
    }

    function copyText(value, button, resetMs) {
        if (window.CoinRexPairing && window.CoinRexPairing.copyText) {
            return window.CoinRexPairing.copyText(value, button, resetMs);
        }
        return Promise.resolve(false);
    }

    // ── Init ────────────────────────────────────────────────────────

    function init(opts) {
        opts = opts || {};
        if (opts.apiBaseUrl) state.apiBaseUrl = String(opts.apiBaseUrl).replace(/\/+$/, '');
        if (opts.appId) state.appId = String(opts.appId);
        if (opts.transport) state.transport = opts.transport;
        if (opts.webActorToken) state.webActorToken = String(opts.webActorToken);
        if (opts.requestTimeoutMs) state.requestTimeoutMs = Math.max(500, Number(opts.requestTimeoutMs));

        // Auto-detect API base from page URL if not set
        if (!state.apiBaseUrl) {
            var loc = window.location;
            // Default: same origin, port 18083
            state.apiBaseUrl = loc.protocol + '//' + loc.hostname + ':18083';
        }

        state.initialized = true;
        if (opts.autoConnectRealtime !== false && state.webActorToken) {
            connectRealtime().catch(function() {});
        }
    }

    function destroy() {
        stopAllPolling();
        if (state.realtimeSocket) {
            state.realtimeSocket.close();
            state.realtimeSocket = null;
        }
        clearTimeout(state.realtimeReconnectTimer);
        clearInterval(state.realtimePingTimer);
        state.wsConnected = false;
        state.initialized = false;
    }

    // ── Public API ──────────────────────────────────────────────────

    window.RexLink = {
        // Config
        init: init,
        destroy: destroy,

        // Core pairing
        createPairing: createPairing,
        cancelPairing: cancelPairing,
        pollPairingStatus: pollPairingStatus,
        renderQR: renderQR,

        // Auth flow
        loginWithPairing: loginWithPairing,

        // Session
        getSessions: getSessions,
        revokeSession: revokeSession,

        // Review flow
        createReviewPairing: createReviewPairing,
        pollReviewStatus: pollReviewStatus,

        // Claim flow
        createClaimApproval: createClaimApproval,
        pollClaimStatus: pollClaimStatus,
        decideClaim: decideClaim,
        completeClaimTx: completeClaimTx,

        // Transport
        connectRealtime: connectRealtime,

        // Utilities
        formatClock: formatClock,
        expiryLabel: expiryLabel,
        shortAddress: shortAddress,
        copyText: copyText,

        // Internal (for advanced use)
        _state: state,
    };

})(window);
