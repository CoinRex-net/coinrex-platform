(function () {
  'use strict';

  var scriptUrl = (document.currentScript && document.currentScript.src)
    ? new URL(document.currentScript.src, window.location.href)
    : new URL('/widget.js', window.location.href);

  var scriptRootPath = scriptUrl.pathname.replace(/\/widget\.js$/i, '').replace(/\/$/, '');
  var apiBase = scriptUrl.origin + scriptRootPath + '/api/v1';
  var widgetSelector = '.coinrex-widget';
  var defaultConfig = {
    layout: 'single',
    bg: '#111111',
    opacity: 0.85,
    blur: 16,
    radius: 18,
    shadow: 'medium',
    spacing: 0,
    refresh: 300
  };

  var shadowPresets = {
    none: 'none',
    soft: '0 10px 24px rgba(2, 6, 23, 0.18)',
    medium: '0 12px 28px rgba(2, 6, 23, 0.22)',
    strong: '0 18px 44px rgba(2, 6, 23, 0.32)'
  };

  function clamp(value, min, max, fallback) {
    var num = Number(value);
    if (!isFinite(num)) {
      return fallback;
    }
    return Math.min(max, Math.max(min, num));
  }

  function sanitizeHex(value, fallback) {
    var raw = String(value || '').trim();
    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(raw)) {
      return raw.toUpperCase();
    }
    return fallback;
  }

  function hexToRgba(hex, opacity) {
    var normalized = sanitizeHex(hex, '#111111').slice(1);
    if (normalized.length === 3) {
      normalized = normalized.split('').map(function (char) { return char + char; }).join('');
    }
    var int = parseInt(normalized, 16);
    var r = (int >> 16) & 255;
    var g = (int >> 8) & 255;
    var b = int & 255;
    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + opacity + ')';
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatScore(value) {
    var score = Number(value);
    if (!isFinite(score)) {
      score = 0;
    }
    return score.toFixed(1) + '/5.0';
  }

  function formatReviews(value) {
    var reviews = Number(value);
    if (!isFinite(reviews)) {
      reviews = 0;
    }
    return new Intl.NumberFormat().format(reviews) + ' reviews';
  }

  function sanitizeProject(value) {
    var raw = String(value || '').trim().toLowerCase();
    raw = raw.replace(/[^a-z0-9\-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    return raw;
  }

  function sanitizeLayout(value) {
    var raw = String(value || '').trim().toLowerCase();
    if (raw === 'glass' || raw === 'multiline') {
      return 'glass';
    }
    return 'single';
  }

  function sanitizeShadow(value) {
    var raw = String(value || '').trim().toLowerCase();
    if (shadowPresets[raw]) {
      return raw;
    }
    if (raw === '0') return 'none';
    if (raw === '1') return 'soft';
    if (raw === '2') return 'medium';
    if (raw === '3') return 'strong';
    return defaultConfig.shadow;
  }

  function sanitizeToken(value) {
    var raw = String(value || '').trim();
    return /^[A-Za-z0-9\-_.]+$/.test(raw) ? raw : '';
  }

  function buildConfig(node) {
    var project = sanitizeProject(node.getAttribute('data-project'));
    return {
      project: project,
      layout: sanitizeLayout(node.getAttribute('data-layout')),
      bg: sanitizeHex(node.getAttribute('data-bg'), defaultConfig.bg),
      opacity: clamp(node.getAttribute('data-opacity'), 0.2, 1, defaultConfig.opacity),
      blur: clamp(node.getAttribute('data-blur'), 0, 36, defaultConfig.blur),
      radius: clamp(node.getAttribute('data-radius'), 0, 32, defaultConfig.radius),
      shadow: sanitizeShadow(node.getAttribute('data-shadow')),
      spacing: clamp(node.getAttribute('data-spacing'), 0, 48, defaultConfig.spacing),
      refresh: clamp(node.getAttribute('data-refresh'), 60, 3600, defaultConfig.refresh),
      token: sanitizeToken(node.getAttribute('data-token'))
    };
  }

  function starSvg(filled) {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" class="crx-icon crx-icon--star ' + (filled ? 'is-filled' : 'is-outline') + '"><path d="M12 2.8l2.7 5.47 6.03.88-4.37 4.25 1.03 6.01L12 16.72 6.61 19.41l1.03-6.01L3.27 9.15l6.03-.88L12 2.8z"></path></svg>';
  }

  function verifiedSvg() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" class="crx-icon crx-icon--verified"><path d="M12 2l2.55 2.07 3.24-.17 1.55 2.86 2.86 1.55-.17 3.24L24 12l-2.07 2.55.17 3.24-2.86 1.55-1.55 2.86-3.24-.17L12 24l-2.55-2.07-3.24.17-1.55-2.86-2.86-1.55.17-3.24L0 12l2.07-2.55-.17-3.24 2.86-1.55 1.55-2.86 3.24.17L12 2zm-1.02 14.2l6.01-6-1.41-1.42-4.6 4.59-2.55-2.54-1.41 1.41 3.96 3.96z"></path></svg>';
  }

  function brandHtml(data) {
    return '<span class="crx-brand">'
      + '<span class="crx-brand-coin">Coin</span>'
      + '<span class="crx-brand-rex">Rex</span>'
      + '<span class="crx-brand-suffix"> Ratings:</span>'
      + (data.verified ? '<span class="crx-verified-badge" title="Verified project">' + verifiedSvg() + '</span>' : '')
      + '</span>';
  }

  function renderSingle(data) {
    return '<div class="crx-shell crx-shell--single" role="img" aria-label="CoinRex rating ' + escapeHtml(formatScore(data.rating)) + ' based on ' + escapeHtml(formatReviews(data.reviews)) + '">'
      + '<div class="crx-row-label">' + brandHtml(data) + '</div>'
      + '<div class="crx-row-score" aria-hidden="true">'
      + '<span class="crx-row-score-star">' + starSvg(true) + '</span>'
      + '<span class="crx-row-score-text">' + escapeHtml(formatScore(data.rating)) + '</span>'
      + '</div>'
      + '</div>';
  }

  function renderGlass(data) {
    var stars = '';
    var filled = Math.max(0, Math.min(5, Math.floor(Number(data.rating) || 0)));
    for (var i = 1; i <= 5; i += 1) {
      stars += '<span class="crx-star-shell">' + starSvg(i <= filled) + '</span>';
    }

    return '<div class="crx-shell crx-shell--glass" role="img" aria-label="CoinRex rating ' + escapeHtml(formatScore(data.rating)) + ' based on ' + escapeHtml(formatReviews(data.reviews)) + '">'
      + '<div class="crx-box-header">'
      + '<div class="crx-box-label">' + brandHtml(data) + '</div>'
      + '<div class="crx-score-inline">' + escapeHtml(formatScore(data.rating)) + '</div>'
      + '</div>'
      + '<div class="crx-divider" aria-hidden="true"></div>'
      + '<div class="crx-box-stars" aria-hidden="true">' + stars + '</div>'
      + '</div>';
  }

  function styleText(config) {
    return [
      ':host{all:initial;display:inline-block;max-width:100%;font-family:Inter,Roboto,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}',
      '.crx-root,.crx-root *{box-sizing:border-box;}',
      '.crx-root{display:inline-block;max-width:100%;margin:' + config.spacing + 'px;}',
      '.crx-shell{width:100%;max-width:100%;color:#fff;border:1px solid #4169E1;border-radius:' + config.radius + 'px;background:' + hexToRgba(config.bg, config.opacity) + ';box-shadow:' + shadowPresets[config.shadow] + ';backdrop-filter:blur(' + config.blur + 'px);-webkit-backdrop-filter:blur(' + config.blur + 'px);overflow:hidden;}',
      '.crx-shell--single{display:inline-flex;align-items:center;justify-content:space-between;gap:12px;width:auto;max-width:100%;min-width:0;padding:11px 14px;}',
      '.crx-shell--glass{display:flex;flex-direction:column;align-items:stretch;gap:0;padding:18px 20px 20px;max-width:460px;min-width:260px;}',
      '.crx-brand{display:inline-flex;align-items:center;gap:0;min-width:0;max-width:100%;font-weight:800;line-height:1.1;white-space:nowrap;}',
      '.crx-brand-coin,.crx-brand-suffix{color:#FFFFFF;}',
      '.crx-brand-rex{color:#4169E1;}',
      '.crx-verified-badge{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;margin-left:8px;border-radius:999px;background:rgba(37,99,235,.2);border:1px solid rgba(96,165,250,.45);color:#60A5FA;flex:0 0 auto;}',
      '.crx-icon{display:block;width:1em;height:1em;fill:currentColor;}',
      '.crx-icon--star{width:1em;height:1em;color:#FFD700;filter:drop-shadow(0 4px 8px rgba(255,215,0,.18));}',
      '.crx-icon--star.is-outline{opacity:.4;}',
      '.crx-icon--verified{width:10px;height:10px;color:#60A5FA;}',
      '.crx-shell--single .crx-brand{font-size:clamp(.95rem,1.5vw,1.15rem);line-height:1.15;white-space:nowrap;}',
      '.crx-row-label{min-width:0;flex:1 1 auto;overflow:hidden;}',
      '.crx-row-score{display:inline-flex;align-items:center;justify-content:flex-end;gap:6px;flex:0 0 auto;margin-left:auto;white-space:nowrap;color:#FFF;}',
      '.crx-row-score-star{display:inline-flex;align-items:center;justify-content:center;color:#FFD700;flex:0 0 auto;}',
      '.crx-row-score-star .crx-icon--star{width:18px;height:18px;}',
      '.crx-row-score-text{font-size:clamp(.98rem,1.8vw,1.2rem);font-weight:800;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}',
      '.crx-box-header{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}',
      '.crx-box-label{min-width:0;flex:1 1 auto;}',
      '.crx-shell--glass .crx-brand{font-size:clamp(1.1rem,2vw,1.5rem);white-space:normal;}',
      '.crx-score-inline{flex:0 0 auto;color:#FFF;font-size:clamp(1.1rem,2.1vw,1.45rem);font-weight:800;line-height:1;white-space:nowrap;letter-spacing:-.03em;font-variant-numeric:tabular-nums;}',
      '.crx-divider{width:100%;height:1px;margin:14px 0 16px;background:#4169E1;box-shadow:0 0 10px rgba(65,105,225,.24);}',
      '.crx-box-stars{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:nowrap;}',
      '.crx-star-shell{display:inline-flex;align-items:center;justify-content:center;color:#FFD700;}',
      '.crx-box-stars .crx-icon--star{width:clamp(1.75rem,2.8vw,2rem);height:clamp(1.75rem,2.8vw,2rem);}',
      '.crx-loading,.crx-error{display:inline-flex;align-items:center;justify-content:center;min-width:220px;padding:12px 14px;border-radius:' + config.radius + 'px;border:1px solid rgba(65,105,225,.4);background:' + hexToRgba(config.bg, Math.max(.2, config.opacity - .1)) + ';color:#dbeafe;box-shadow:' + shadowPresets[config.shadow] + ';backdrop-filter:blur(' + config.blur + 'px);-webkit-backdrop-filter:blur(' + config.blur + 'px);font-size:12px;font-weight:700;}',
      '.crx-error{color:#fecaca;border-color:rgba(239,68,68,.45);}',
      '@media (max-width:768px){.crx-shell--glass{max-width:100%;padding:16px 16px 18px;}.crx-shell--glass .crx-brand{font-size:clamp(1rem,4.6vw,1.3rem);}.crx-score-inline{font-size:clamp(1rem,4.8vw,1.2rem);}.crx-box-stars{gap:10px;}}',
      '@media (max-width:640px){.crx-shell--single{padding:8px 10px;gap:8px;}.crx-shell--single .crx-brand{font-size:.82rem;}.crx-row-score-text{font-size:.82rem;}.crx-row-score{gap:4px;}.crx-row-score-star .crx-icon--star{width:14px;height:14px;}.crx-shell--glass{padding:16px 16px 18px;}.crx-divider{margin:14px 0 16px;}.crx-box-stars{justify-content:space-between;}.crx-box-stars .crx-icon--star{width:1.4rem;height:1.4rem;}}',
      '@media (max-width:520px){.crx-shell--single{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:6px;width:100%;}.crx-shell--single .crx-brand{font-size:.68rem;}.crx-row-score{gap:3px;min-width:max-content;}.crx-row-score-text{font-size:.68rem;}.crx-row-score-star .crx-icon--star{width:12px;height:12px;}.crx-box-stars .crx-icon--star{width:1.2rem;height:1.2rem;}.crx-verified-badge{width:14px;height:14px;}}'
    ].join('');
  }

  function buildEndpoint(config) {
    var url = new URL(apiBase + '/project/' + encodeURIComponent(config.project) + '/' + (config.token ? 'widget' : 'rating'));
    if (config.token) {
      url.searchParams.set('token', config.token);
    }
    return url.toString();
  }

  function renderState(shadowRoot, config, html) {
    shadowRoot.innerHTML = '<style>' + styleText(config) + '</style><div class="crx-root">' + html + '</div>';
  }

  function renderLoading(shadowRoot, config) {
    renderState(shadowRoot, config, '<div class="crx-loading">Loading CoinRex rating…</div>');
  }

  function renderError(shadowRoot, config, message) {
    renderState(shadowRoot, config, '<div class="crx-error">' + escapeHtml(message || 'Unable to load CoinRex rating.') + '</div>');
  }

  function normalizePayload(payload) {
    return {
      project_name: String(payload.project_name || ''),
      slug: String(payload.slug || ''),
      rating: Number(payload.rating || 0),
      reviews: Number(payload.reviews || 0),
      verified: Boolean(payload.verified),
      updated_at: String(payload.updated_at || '')
    };
  }

  function renderWidget(shadowRoot, config, payload) {
    var data = normalizePayload(payload);
    var html = config.layout === 'glass' ? renderGlass(data) : renderSingle(data);
    renderState(shadowRoot, config, html);
  }

  async function fetchPayload(config) {
    var response = await fetch(buildEndpoint(config), {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'omit',
      mode: 'cors'
    });

    var json = {};
    try {
      json = await response.json();
    } catch (error) {
      json = {};
    }

    if (!response.ok) {
      throw new Error((json.error && json.error.message) || 'CoinRex widget request failed.');
    }

    return json;
  }

  async function hydrate(node) {
    if (!node || node.__coinrexWidgetMounted) {
      return;
    }

    node.__coinrexWidgetMounted = true;
    var config = buildConfig(node);
    var shadowRoot = node.shadowRoot || node.attachShadow({ mode: 'open' });

    if (!config.project) {
      renderError(shadowRoot, config, 'Missing data-project attribute.');
      return;
    }

    renderLoading(shadowRoot, config);

    var refreshHandle = null;

    var load = async function () {
      try {
        var payload = await fetchPayload(config);
        renderWidget(shadowRoot, config, payload);
      } catch (error) {
        renderError(shadowRoot, config, error && error.message ? error.message : 'Unable to load CoinRex rating.');
      }
    };

    await load();

    refreshHandle = window.setInterval(function () {
      if (document.visibilityState === 'hidden') {
        return;
      }
      load();
    }, config.refresh * 1000);

    node.__coinrexWidgetDestroy = function () {
      if (refreshHandle) {
        window.clearInterval(refreshHandle);
      }
    };
  }

  function scanAndMount(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = scope.querySelectorAll(widgetSelector);
    if (!nodes.length) {
      return;
    }

    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries, currentObserver) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }
          currentObserver.unobserve(entry.target);
          hydrate(entry.target);
        });
      }, { rootMargin: '160px 0px' });

      nodes.forEach(function (node) {
        if (!node.__coinrexWidgetObserved) {
          node.__coinrexWidgetObserved = true;
          observer.observe(node);
        }
      });
      return;
    }

    nodes.forEach(hydrate);
  }

  function boot() {
    scanAndMount(document);

    if ('MutationObserver' in window && document.body) {
      var mutationObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          mutation.addedNodes.forEach(function (node) {
            if (!node || node.nodeType !== 1) {
              return;
            }
            if (node.matches && node.matches(widgetSelector)) {
              scanAndMount(node.parentNode || document);
              return;
            }
            if (node.querySelectorAll) {
              scanAndMount(node);
            }
          });
        });
      });
      mutationObserver.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();