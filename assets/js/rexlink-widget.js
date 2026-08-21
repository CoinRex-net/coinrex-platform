/**
 * RexLink Connect Widget v1.0
 * <script src="https://coinrex.xyz/assets/js/rexlink-widget.js" data-app-id="your-dapp" async></script>
 */
(function(window, document) {
  'use strict';

  var script = document.currentScript;
  var scriptUrl = new URL(script && script.src ? script.src : '/assets/js/rexlink-widget.js', window.location.href);
  var assetBase = scriptUrl.href.replace(/\/rexlink-widget(?:\.min)?\.js(?:\?.*)?$/i, '');
  var instances = [];
  var dependencyPromise = null;

  function text(value) { return String(value == null ? '' : value).trim(); }
  function flag(value, fallback) {
    var normalized = text(value).toLowerCase();
    return normalized ? ['1', 'true', 'yes', 'on'].indexOf(normalized) !== -1 : fallback;
  }
  function number(value, fallback, min, max) {
    var parsed = Number(value);
    return isFinite(parsed) ? Math.max(min, Math.min(max, parsed)) : fallback;
  }
  function networks(value) {
    var seen = {};
    return text(value).split(',').map(function(item) { return text(item).toLowerCase(); }).filter(function(item) {
      if (!/^[a-z0-9][a-z0-9_-]{0,31}$/.test(item) || seen[item]) return false;
      seen[item] = true;
      return true;
    });
  }
  function defaultApiBase() {
    if (scriptUrl.port && scriptUrl.port !== '80' && scriptUrl.port !== '443') return scriptUrl.origin;
    return scriptUrl.protocol + '//' + scriptUrl.hostname + ':18083';
  }
  function config(options) {
    var data = script && script.dataset ? script.dataset : {};
    var input = options || {};
    var appId = text(input.appId || data.appId).toLowerCase();
    return {
      appId: /^[a-z0-9][a-z0-9._-]{1,63}$/.test(appId) ? appId : '',
      appName: text(input.appName || data.appName || 'RexLink dApp'),
      apiBaseUrl: text(input.apiBaseUrl || data.apiBase || defaultApiBase()).replace(/\/+$/, ''),
      buttonLabel: text(input.buttonLabel || data.buttonLabel || 'Connect RexLink'),
      durationMinutes: number(input.durationMinutes || data.durationMinutes, 10, 1, 1440),
      networkSlugs: networks(Array.isArray(input.networkSlugs) ? input.networkSlugs.join(',') : (input.networks || data.networks)),
      showButton: flag(input.showButton != null ? input.showButton : data.showButton, true),
      autoOpen: flag(input.autoOpen != null ? input.autoOpen : data.autoOpen, false),
      position: text(input.position || data.position).toLowerCase() === 'left' ? 'left' : 'right',
      requestTimeoutMs: number(input.requestTimeoutMs || data.requestTimeout, 3000, 1000, 15000),
    };
  }
  function emit(name, detail) {
    try {
      window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
      document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    } catch (error) {}
  }
  function load(url, ready) {
    if (ready()) return Promise.resolve();
    return new Promise(function(resolve, reject) {
      var node = document.createElement('script');
      node.src = url;
      node.async = false;
      node.crossOrigin = 'anonymous';
      node.onload = function() { ready() ? resolve() : reject(new Error('RexLink dependency did not initialize.')); };
      node.onerror = function() { reject(new Error('RexLink dependency could not load.')); };
      (document.head || document.documentElement).appendChild(node);
    });
  }
  function dependencies() {
    if (dependencyPromise) return dependencyPromise;
    dependencyPromise = load(assetBase + '/qrcode-browser.js', function() { return Boolean(window.CoinRexQRCode); })
      .then(function() { return load(assetBase + '/rexlink-pairing.js', function() { return Boolean(window.CoinRexPairing); }); })
      .then(function() { return load(assetBase + '/rexlink-sdk.js', function() { return Boolean(window.RexLink && window.RexLink.createPairing); }); });
    return dependencyPromise;
  }
  function shortAddress(value) {
    var address = text(value);
    return address.length > 14 ? address.slice(0, 6) + '...' + address.slice(-4) : (address || 'Connected');
  }
  function css(side) {
    return ':host{all:initial;font-family:Inter,system-ui,sans-serif;color:#f8fafc}*{box-sizing:border-box}button{font:inherit}'
      + '.launch{position:fixed;z-index:2147483000;bottom:20px;' + side + ':20px;display:flex;align-items:center;gap:9px;padding:12px 17px;border:1px solid #806b16;border-radius:999px;background:#111827;color:#fff;box-shadow:0 16px 40px #02061759;font-weight:800;cursor:pointer}'
      + '.launch[hidden],.overlay[hidden],.retry[hidden]{display:none!important}.mark{display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#facc15;color:#111827;font-weight:900}'
      + '.overlay{position:fixed;z-index:2147483001;inset:0;display:grid;place-items:center;padding:18px;background:#020617bd;backdrop-filter:blur(8px)}.dialog{width:min(94vw,720px);max-height:94vh;overflow:auto;border:1px solid #26334a;border-radius:22px;background:#0f172a;box-shadow:0 28px 80px #0008}'
      + '.head{display:flex;justify-content:space-between;gap:16px;padding:22px;border-bottom:1px solid #243047}.tag{margin:0 0 6px;color:#facc15;font-size:12px;font-weight:900;text-transform:uppercase}h2{margin:0;font-size:24px}.sub{margin:8px 0 0;color:#aebbd0;font-size:14px}.close{width:38px;height:38px;border:1px solid #334155;border-radius:11px;background:#172033;color:#fff;font-size:22px;cursor:pointer}'
      + '.body{display:grid;grid-template-columns:1fr 300px;gap:20px;padding:22px}.status{padding:12px;border:1px solid #334155;border-radius:12px;background:#111c30;color:#cbd5e1;font-size:14px;line-height:1.45}.status.error{color:#fecaca;border-color:#f8717180}.status.success{color:#a7f3d0;border-color:#34d39980}'
      + '.steps{display:grid;gap:12px;padding:4px 0 0;list-style:none;color:#cbd5e1;font-size:14px}.steps li{display:flex;align-items:center;gap:10px}.steps b{display:grid;place-items:center;width:28px;height:28px;border-radius:8px;background:#facc151f;color:#facc15}.scope{color:#8493aa;font-size:12px}'
      + '.card{display:flex;flex-direction:column;align-items:center;padding:16px;border:1px solid #facc1559;border-radius:18px;background:#0b1323}.qr{display:grid;place-items:center;width:248px;min-height:248px;padding:8px;border-radius:13px;background:#fff;color:#334155;text-align:center;font-size:13px}.qr canvas,.qr svg{width:232px!important;height:232px!important;max-width:100%}'
      + '.code{margin-top:14px;padding:9px 12px;border:1px solid #facc1566;border-radius:10px;color:#facc15;font-size:20px;letter-spacing:.06em}.copy,.retry{margin-top:10px;padding:9px 12px;border:1px solid #3d4b63;border-radius:10px;background:#162137;color:#fff;cursor:pointer}.retry{background:#facc15;color:#111827;font-weight:900}.timer{margin:10px 0 0;color:#91a0b8;font-size:12px}'
      + '.spinner{width:34px;height:34px;border:3px solid #dbe4f0;border-top-color:#1d4ed8;border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:620px){.body{grid-template-columns:1fr}.info{order:2}.launch{bottom:14px;' + side + ':14px}}';
  }
  function markup(options) {
    var scope = options.networkSlugs.length ? options.networkSlugs.join(', ') : 'All enabled networks';
    return '<style>' + css(options.position) + '</style><button class="launch" type="button"><span class="mark">R</span><span class="label"></span></button>'
      + '<div class="overlay" hidden><section class="dialog" role="dialog" aria-modal="true"><header class="head"><div><p class="tag">Secure wallet pairing</p><h2>Connect with RexLink</h2><p class="sub"></p></div><button class="close" type="button" aria-label="Close">&times;</button></header>'
      + '<div class="body"><div class="info"><p class="status" aria-live="polite">Preparing RexLink...</p><ol class="steps"><li><b>1</b>Open RexLink on your phone.</li><li><b>2</b>Scan the QR or enter the code.</li><li><b>3</b>Confirm the dApp connection.</li></ol><p class="scope">Networks: ' + scope + '</p></div>'
      + '<div class="card"><div class="qr"><span class="spinner"></span></div><strong class="code">------</strong><button class="copy" type="button" disabled>Copy code</button><p class="timer">Creating secure code...</p><button class="retry" type="button" hidden>Generate new QR</button></div></div></section></div>';
  }

  function createInstance(options) {
    var host = document.createElement('div');
    document.body.appendChild(host);
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    root.innerHTML = markup(options);
    var launch = root.querySelector('.launch');
    var label = root.querySelector('.label');
    var overlay = root.querySelector('.overlay');
    var status = root.querySelector('.status');
    var qr = root.querySelector('.qr');
    var code = root.querySelector('.code');
    var copy = root.querySelector('.copy');
    var timer = root.querySelector('.timer');
    var retry = root.querySelector('.retry');
    var pairingId = 0;
    var countdown = null;
    var generation = 0;
    var pending = null;
    var resolvePending = null;
    var rejectPending = null;
    var connectedSession = null;
    label.textContent = options.buttonLabel;
    launch.hidden = !options.showButton;
    root.querySelector('.sub').textContent = options.appName + ' is requesting a wallet connection.';

    function setStatus(message, state) {
      status.textContent = message;
      status.className = 'status' + (state ? ' ' + state : '');
      emit('rexlink:status', { appId: options.appId, status: state || 'pending', message: message });
    }
    function stopCountdown() { if (countdown) window.clearInterval(countdown); countdown = null; }
    function startCountdown(seconds, activeGeneration) {
      var ttlSeconds = Math.min(300, Math.max(0, Number(seconds || 300)));
      var deadline = Date.now() + ttlSeconds * 1000;
      stopCountdown();
      function tick() {
        if (activeGeneration !== generation) return stopCountdown();
        var left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        timer.textContent = 'QR expires in ' + Math.floor(left / 60) + 'm ' + String(left % 60).padStart(2, '0') + 's';
        if (!left) { stopCountdown(); retry.hidden = false; setStatus('QR expired. Generate a fresh code.', 'error'); }
      }
      tick();
      countdown = window.setInterval(tick, 1000);
    }
    function finish(result, activeGeneration) {
      if (activeGeneration !== generation) return;
      stopCountdown();
      var source = result.session || {};
      connectedSession = {
        appId: options.appId,
        sessionId: Number(result.session_id || source.id || source.session_id || 0),
        walletAddress: text(result.wallet_address || source.wallet_address),
        remainingSeconds: Number(result.session_remaining_seconds || source.remaining_seconds || 0),
      };
      label.textContent = shortAddress(connectedSession.walletAddress);
      setStatus('RexLink connected successfully.', 'success');
      timer.textContent = shortAddress(connectedSession.walletAddress) + ' connected';
      emit('rexlink:connected', connectedSession);
      if (resolvePending) resolvePending(connectedSession);
      pending = resolvePending = rejectPending = null;
      window.setTimeout(function() { close(false); }, 800);
    }
    function begin() {
      var activeGeneration = ++generation;
      pairingId = 0;
      qr.innerHTML = '<span class="spinner"></span>';
      code.textContent = '------';
      copy.disabled = true;
      retry.hidden = true;
      setStatus('Creating a secure RexLink code...');
      dependencies().then(function() {
        if (!options.appId) throw new Error('Missing data-app-id on the RexLink script.');
        window.RexLink.init({ apiBaseUrl: options.apiBaseUrl, appId: options.appId, requestTimeoutMs: options.requestTimeoutMs, autoConnectRealtime: false });
        return window.RexLink.createPairing({ appId: options.appId, purpose: 'auth', durationMinutes: options.durationMinutes, networkSlugs: options.networkSlugs, timeoutMs: options.requestTimeoutMs });
      }).then(function(data) {
        if (activeGeneration !== generation) return;
        pairingId = Number(data.pairing_id || 0);
        code.textContent = text(data.display_code || '------');
        copy.disabled = !data.display_code;
        startCountdown(data.expires_in_seconds, activeGeneration);
        setStatus('Scan the QR in RexLink or enter the code.');
        return window.RexLink.renderQR(data.qr_payload, qr, { preferCanvas: true, fallbackUrl: options.apiBaseUrl + '/api/v1/pairing/qr' }).then(function() {
          return window.RexLink.pollPairingStatus(pairingId, { interval: 500, timeout: 300000, statusToken: data.status_token, shouldContinue: function() { return activeGeneration === generation; } });
        });
      }).then(function(result) { if (result) finish(result, activeGeneration); }).catch(function(error) {
        if (activeGeneration !== generation) return;
        stopCountdown();
        retry.hidden = false;
        setStatus(error.message || 'RexLink could not start.', 'error');
        emit('rexlink:error', { appId: options.appId, error: error });
        if (rejectPending) rejectPending(error);
        pending = resolvePending = rejectPending = null;
      });
    }
    function open() {
      overlay.hidden = false;
      document.documentElement.style.overflow = 'hidden';
      if (!pending) { pending = new Promise(function(resolve, reject) { resolvePending = resolve; rejectPending = reject; }); begin(); }
      emit('rexlink:opened', { appId: options.appId });
      return pending;
    }
    function close(cancel) {
      overlay.hidden = true;
      document.documentElement.style.overflow = '';
      if (cancel !== false && pairingId) {
        generation += 1;
        stopCountdown();
        window.RexLink.cancelPairing(pairingId).catch(function() {});
        if (rejectPending) rejectPending(new Error('RexLink pairing was cancelled.'));
        pending = resolvePending = rejectPending = null;
        pairingId = 0;
      }
      emit('rexlink:closed', { appId: options.appId });
    }
    launch.addEventListener('click', function() { open().catch(function() {}); });
    root.querySelector('.close').addEventListener('click', function() { close(true); });
    overlay.addEventListener('click', function(event) { if (event.target === overlay) close(true); });
    retry.addEventListener('click', begin);
    copy.addEventListener('click', function() { window.RexLink.copyText(code.textContent, copy, 1200).catch(function() {}); });
    var instance = { connect: open, open: open, close: function() { close(true); }, getSession: function() { return connectedSession; }, config: options, host: host };
    instances.push(instance);
    emit('rexlink:ready', { appId: options.appId, instance: instance });
    if (options.autoOpen) window.setTimeout(function() { open().catch(function() {}); }, 0);
    return instance;
  }
  function mount(options) { return Promise.resolve(createInstance(config(options))); }
  window.RexLinkWidget = {
    mount: mount,
    connect: function(options) { return instances[0] && !options ? instances[0].connect() : mount(options).then(function(instance) { return instance.connect(); }); },
    open: function() { return instances[0] ? instances[0].open() : Promise.reject(new Error('RexLink widget is not ready.')); },
    close: function() { if (instances[0]) instances[0].close(); },
    getSession: function() { return instances[0] ? instances[0].getSession() : null; },
    instances: instances,
    version: '1.0.0',
  };
  function boot() { mount().catch(function(error) { emit('rexlink:error', { error: error }); }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})(window, document);
