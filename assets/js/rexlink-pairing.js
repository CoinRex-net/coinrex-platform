(function(window) {
    'use strict';

    const DEFAULT_QR_OPTIONS = {
        type: 'svg',
        width: 232,
        margin: 2,
        errorCorrectionLevel: 'M',
        color: {
            dark: '#081120',
            light: '#ffffff',
        },
    };

    function trimTrailingSlash(value) {
        return String(value || '').replace(/\/+$/, '');
    }

    function compactPayload(payload, defaults) {
        payload = payload && typeof payload === 'object' ? payload : {};
        defaults = defaults && typeof defaults === 'object' ? defaults : {};
        const apiBaseUrl = trimTrailingSlash(payload.api_base_url || payload.base_url || defaults.apiBaseUrl || defaults.baseUrl);
        const baseUrl = trimTrailingSlash(payload.base_url || payload.api_base_url || defaults.baseUrl || apiBaseUrl);
        const compact = {
            type: payload.type || defaults.type || 'coinrex.rex_signer.pairing',
            version: Number(payload.version || defaults.version || 2),
            code: payload.code || defaults.code || '',
            purpose: payload.purpose || defaults.purpose || 'claim',
            api_base_url: apiBaseUrl,
            base_url: baseUrl,
            dapp_name: payload.dapp_name || defaults.dappName || 'CoinRex',
            dapp_url: payload.dapp_url || defaults.dappUrl || baseUrl || window.location.origin,
            network_slug: payload.network_slug || defaults.networkSlug || 'polygon',
            chain_id: Number(payload.chain_id || defaults.chainId || 137),
            requested_duration_minutes: Number(payload.requested_duration_minutes || defaults.durationMinutes || 10),
            expires_at: payload.expires_at || defaults.expiresAt || '',
            expires_in_seconds: Number(payload.expires_in_seconds || defaults.expiresInSeconds || 0),
            expires_at_unix: Number(payload.expires_at_unix || defaults.expiresAtUnix || 0),
        };

        if (payload.coinrex_purpose || defaults.coinrexPurpose) {
            compact.coinrex_purpose = payload.coinrex_purpose || defaults.coinrexPurpose;
        }
        if (payload.requested_wallet_address || defaults.requestedWalletAddress) {
            compact.requested_wallet_address = String(payload.requested_wallet_address || defaults.requestedWalletAddress).toLowerCase();
        }

        return compact;
    }

    function qrText(payload, defaults) {
        return JSON.stringify(compactPayload(payload, defaults));
    }

    function setLogoVisible(logoBadge, visible) {
        if (logoBadge) {
            logoBadge.classList.toggle('is-visible', Boolean(visible));
        }
    }

    function clearImage(image) {
        if (!image) {
            return;
        }
        image.hidden = true;
        image.onload = null;
        image.onerror = null;
        image.removeAttribute('src');
    }

    function renderQr(payload, options) {
        options = options && typeof options === 'object' ? options : {};
        const placeholder = options.placeholder || null;
        const image = options.image || null;
        const logoBadge = options.logoBadge || null;
        const text = options.text || qrText(payload, options.payloadDefaults || {});
        const qrOptions = Object.assign({}, DEFAULT_QR_OPTIONS, options.qrOptions || {});
        qrOptions.color = Object.assign({}, DEFAULT_QR_OPTIONS.color, (options.qrOptions && options.qrOptions.color) || {});
        const fallbackUrl = options.fallbackUrl || '';
        const fallbackText = options.fallbackText || 'Use the 6 digit code below.';
        const beforeSlowRender = typeof options.beforeSlowRender === 'function' ? options.beforeSlowRender : null;

        function showFallbackMessage() {
            if (placeholder) {
                placeholder.hidden = false;
                placeholder.classList.remove('is-rendered');
                placeholder.innerHTML = '<span>' + fallbackText + '</span>';
            }
            clearImage(image);
            setLogoVisible(logoBadge, false);
            return false;
        }

        function renderImageFallback() {
            if (!fallbackUrl || !image) {
                return Promise.resolve(showFallbackMessage());
            }
            clearImage(image);
            if (placeholder) {
                placeholder.hidden = false;
                placeholder.classList.remove('is-rendered');
            }
            setLogoVisible(logoBadge, false);
            return new Promise(function(resolve) {
                image.onload = function() {
                    if (placeholder) {
                        placeholder.hidden = true;
                        placeholder.classList.remove('is-rendered');
                        placeholder.innerHTML = '';
                    }
                    image.hidden = false;
                    setLogoVisible(logoBadge, true);
                    resolve(true);
                };
                image.onerror = function() {
                    resolve(showFallbackMessage());
                };
                image.hidden = true;
                image.src = fallbackUrl + '?payload=' + encodeURIComponent(text);
            });
        }

        if (!placeholder) {
            return renderImageFallback();
        }

        if (window.CoinRexQRCode && typeof window.CoinRexQRCode.toCanvas === 'function' && options.preferCanvas) {
            let rendered = false;
            const canvas = document.createElement('canvas');
            canvas.width = Number(qrOptions.width || 232);
            canvas.height = Number(qrOptions.width || 232);
            canvas.setAttribute('aria-label', options.ariaLabel || 'RexLink pairing QR');
            if (beforeSlowRender) {
                window.setTimeout(function() {
                    if (!rendered && placeholder && !placeholder.classList.contains('is-rendered')) {
                        beforeSlowRender(placeholder);
                    }
                }, Number(options.slowRenderMs || 250));
            }
            return Promise.resolve(window.CoinRexQRCode.toCanvas(canvas, text, qrOptions)).then(function() {
                rendered = true;
                placeholder.hidden = false;
                placeholder.classList.add('is-rendered');
                placeholder.innerHTML = '';
                placeholder.appendChild(canvas);
                clearImage(image);
                setLogoVisible(logoBadge, true);
                return true;
            }).catch(function() {
                rendered = true;
                return renderImageFallback();
            });
        }

        if (window.CoinRexQRCode && typeof window.CoinRexQRCode.toString === 'function') {
            return Promise.resolve(window.CoinRexQRCode.toString(text, qrOptions)).then(function(svg) {
                placeholder.hidden = false;
                placeholder.classList.add('is-rendered');
                placeholder.innerHTML = svg;
                clearImage(image);
                setLogoVisible(logoBadge, true);
                return true;
            }).catch(renderImageFallback);
        }

        return renderImageFallback();
    }

    function formatClock(seconds) {
        const safeSeconds = Math.max(0, Math.floor(Number(seconds || 0)));
        return Math.floor(safeSeconds / 60) + ':' + String(safeSeconds % 60).padStart(2, '0');
    }

    function expiryLabel(seconds) {
        const safeSeconds = Math.max(0, Math.floor(Number(seconds || 0)));
        return 'QR expires in ' + Math.floor(safeSeconds / 60) + 'm ' + String(safeSeconds % 60).padStart(2, '0') + 's';
    }

    function setCopyButton(button, copied) {
        if (!button) {
            return;
        }
        button.innerHTML = copied ? '<i class="fas fa-check"></i>' : '<i class="fas fa-copy"></i>';
        button.title = copied ? 'Copied' : 'Copy code';
        button.setAttribute('aria-label', copied ? 'Pairing code copied' : 'Copy pairing code');
    }

    function copyText(value, button, resetMs) {
        const text = String(value || '').trim();
        if (!text) {
            return Promise.resolve(false);
        }
        const markCopied = function() {
            setCopyButton(button, true);
            window.setTimeout(function() {
                setCopyButton(button, false);
            }, Number(resetMs || 1400));
            return true;
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(markCopied).catch(markCopied);
        }

        return new Promise(function(resolve, reject) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
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
                resolve(markCopied());
            } catch (error) {
                reject(error);
            }
        });
    }

    function shortAddress(address) {
        const value = String(address || '');
        if (value.length <= 14) {
            return value || 'Connected';
        }
        return value.slice(0, 6) + '...' + value.slice(-4);
    }

    window.CoinRexPairing = {
        compactPayload: compactPayload,
        qrText: qrText,
        renderQr: renderQr,
        formatClock: formatClock,
        expiryLabel: expiryLabel,
        copyText: copyText,
        setCopyButton: setCopyButton,
        shortAddress: shortAddress,
        trimTrailingSlash: trimTrailingSlash,
    };
})(window);
