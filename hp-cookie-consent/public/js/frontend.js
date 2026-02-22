/**
 * HP Cookie Consent - Frontend JavaScript
 *
 * @package HPCookieConsent
 */
(function () {
    'use strict';

    var config = window.hpccConfig;
    if (!config) return;

    var COOKIE_NAME = 'hpcc_consent';
    var CONSENT_ID_NAME = 'hpcc_consent_id';

    /* ── Helpers ── */

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(value) +
            ';expires=' + d.toUTCString() +
            ';path=/;SameSite=Lax;Secure';
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function generateId() {
        return 'hpcc-' + Date.now().toString(36) + '-' + Math.random().toString(36).substr(2, 9);
    }

    function getConsentId() {
        var id = getCookie(CONSENT_ID_NAME);
        if (!id) {
            id = generateId();
            setCookie(CONSENT_ID_NAME, id, config.cookieLifetime);
        }
        return id;
    }

    function getConsent() {
        var raw = getCookie(COOKIE_NAME);
        if (!raw) return null;
        try {
            var data = JSON.parse(raw);
            // Support legacy flat format (just categories object)
            if (data && !data.categories && typeof data.necessary !== 'undefined') {
                return data;
            }
            return data && data.categories ? data.categories : null;
        } catch (e) {
            return null;
        }
    }

    function getConsentData() {
        var raw = getCookie(COOKIE_NAME);
        if (!raw) return null;
        try {
            var data = JSON.parse(raw);
            // Legacy format
            if (data && !data.categories && typeof data.necessary !== 'undefined') {
                return { categories: data, timestamp: 0, version: '0' };
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function needsReconsent(consentData) {
        if (!consentData) return true;

        // Check version
        var currentVersion = config.reconsentVersion || '1';
        if (consentData.version && consentData.version !== currentVersion) {
            return true;
        }

        // Check time-based re-consent
        var days = config.reconsentDays || 0;
        if (days > 0 && consentData.timestamp) {
            var elapsed = Date.now() - consentData.timestamp;
            var maxAge = days * 86400000;
            if (elapsed > maxAge) {
                return true;
            }
        }

        return false;
    }

    function saveConsent(categories) {
        var previous = getConsent() || {};
        var consentData = {
            categories: categories,
            timestamp: Date.now(),
            version: config.reconsentVersion || '1'
        };
        setCookie(COOKIE_NAME, JSON.stringify(consentData), config.cookieLifetime);
        deleteRevokedCookies(previous, categories);
        logConsent(categories);
        fireConsentEvent(categories);
        loadConditionalScripts(categories);
        unblockScripts(categories);
        unblockIframes(categories);
    }

    /* ── Cookie-Löschung bei Widerruf ── */

    var CATEGORY_COOKIES = {
        statistics: [
            '_ga', '_ga_*', '_gid', '_gat', '_gat_*',
            '__utma', '__utmb', '__utmc', '__utmz', '__utmt',
            '_hjid', '_hjSession*', '_hjSessionUser*',
            '_clck', '_clsk', 'CLID',
            'mtm_*', '_pk_*',
            'plausible_*'
        ],
        marketing: [
            '_fbp', '_fbc', 'fr',
            '_gcl_*', 'IDE', 'DSID', 'test_cookie',
            'li_sugr', 'bcookie', 'lidc', 'UserMatchHistory',
            '_ttp', '_tt_*',
            '_pin_unauth',
            '__hssc', '__hssrc', '__hstc', 'hubspotutk'
        ],
        external: [
            'VISITOR_INFO1_LIVE', 'YSC', 'CONSENT', 'LOGIN_INFO',
            'vuid'
        ]
    };

    function deleteRevokedCookies(previous, current) {
        var keys = Object.keys(CATEGORY_COOKIES);
        for (var i = 0; i < keys.length; i++) {
            var cat = keys[i];
            if (previous[cat] && !current[cat]) {
                var patterns = CATEGORY_COOKIES[cat];
                for (var j = 0; j < patterns.length; j++) {
                    removeCookiesByPattern(patterns[j]);
                }
            }
        }
    }

    function removeCookiesByPattern(pattern) {
        var allCookies = document.cookie.split(';');
        for (var i = 0; i < allCookies.length; i++) {
            var name = allCookies[i].split('=')[0].trim();
            if (matchPattern(name, pattern)) {
                // Delete for current path and root path
                document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;';
                document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=.' + location.hostname + ';';
                // Try parent domain (e.g. .example.com)
                var parts = location.hostname.split('.');
                if (parts.length > 2) {
                    var parentDomain = '.' + parts.slice(-2).join('.');
                    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=' + parentDomain + ';';
                }
            }
        }
    }

    function matchPattern(name, pattern) {
        if (pattern.indexOf('*') === -1) {
            return name === pattern;
        }
        var prefix = pattern.replace('*', '');
        return name.indexOf(prefix) === 0;
    }

    function logConsent(categories) {
        var formData = new FormData();
        formData.append('action', 'hpcc_log_consent');
        formData.append('nonce', config.nonce);
        formData.append('consent_id', getConsentId());
        formData.append('categories', JSON.stringify(categories));
        formData.append('consent_given', '1');

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function () { /* silent fail */ });
    }

    function fireConsentEvent(categories) {
        var event = new CustomEvent('hpcc:consent', { detail: categories });
        document.dispatchEvent(event);

        if (window.dataLayer) {
            window.dataLayer.push({
                event: 'hpcc_consent_update',
                hpcc_consent: categories
            });
        }
    }

    function loadConditionalScripts(categories) {
        if (!categories.statistics) return;

        if (config.gtmId && !document.querySelector('script[src*="googletagmanager.com/gtm.js"]')) {
            (function (w, d, s, l, i) {
                w[l] = w[l] || []; w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', config.gtmId);
        }

        if (config.gaId && !document.querySelector('script[src*="googletagmanager.com/gtag/js"]')) {
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + config.gaId;
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            function gtag() { window.dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', config.gaId, { anonymize_ip: true });
        }
    }

    /* ── Script/iframe Unblocking ── */

    function unblockScripts(categories) {
        var blocked = document.querySelectorAll('script[type="text/plain"][data-hpcc-category]');
        for (var i = 0; i < blocked.length; i++) {
            var el = blocked[i];
            var cat = el.getAttribute('data-hpcc-category');
            if (!categories[cat]) continue;

            var newScript = document.createElement('script');

            // Copy attributes except type and data-hpcc-*
            for (var j = 0; j < el.attributes.length; j++) {
                var attr = el.attributes[j];
                if (attr.name === 'type' || attr.name.indexOf('data-hpcc-') === 0) continue;
                newScript.setAttribute(attr.name, attr.value);
            }

            // Restore src from data-hpcc-src
            var originalSrc = el.getAttribute('data-hpcc-src');
            if (originalSrc) {
                newScript.src = originalSrc;
                newScript.async = true;
            } else {
                newScript.textContent = el.textContent;
            }

            el.parentNode.replaceChild(newScript, el);
        }
    }

    function unblockIframes(categories) {
        var placeholders = document.querySelectorAll('.hpcc-iframe-placeholder[data-hpcc-category]');
        for (var i = 0; i < placeholders.length; i++) {
            var el = placeholders[i];
            var cat = el.getAttribute('data-hpcc-category');
            if (!categories[cat]) continue;

            var originalHtml = el.getAttribute('data-hpcc-iframe');
            if (!originalHtml) continue;

            var wrapper = document.createElement('div');
            wrapper.innerHTML = originalHtml;
            el.parentNode.replaceChild(wrapper.firstChild, el);
        }
    }

    /* ── Banner Rendering ── */

    function buildBanner() {
        var container = document.getElementById('hpcc-cookie-consent');
        if (!container) return;

        var posClass = 'hpcc-banner--' + config.position;
        var styleClass = 'hpcc-banner--' + config.style;

        // Overlay (for center modal)
        var overlay = '';
        if (config.position === 'center') {
            overlay = '<div class="hpcc-overlay" id="hpcc-overlay"></div>';
        }

        // Links
        var links = '';
        if (config.privacyUrl || config.imprintUrl) {
            links = '<div class="hpcc-banner__links">';
            if (config.privacyUrl) {
                links += '<a href="' + escapeHtml(config.privacyUrl) + '">' + escapeHtml(config.i18n.privacy) + '</a>';
            }
            if (config.imprintUrl) {
                links += '<a href="' + escapeHtml(config.imprintUrl) + '">' + escapeHtml(config.i18n.imprint) + '</a>';
            }
            links += '</div>';
        }

        // Categories
        var catHtml = '';
        var catKeys = Object.keys(config.categories);
        for (var i = 0; i < catKeys.length; i++) {
            var key = catKeys[i];
            var cat = config.categories[key];
            var checked = cat.required || cat.enabled ? ' checked' : '';
            var disabled = cat.required ? ' disabled' : '';
            var requiredLabel = cat.required ? ' <span class="hpcc-category__required">' + escapeHtml(config.i18n.required) + '</span>' : '';

            var cookieTable = '';
            if (cat.cookies && cat.cookies.length > 0) {
                cookieTable = '<div class="hpcc-cookie-details">' +
                    '<button class="hpcc-cookie-details__toggle" type="button">▸ ' + escapeHtml(config.i18n.cookieDetails || 'Cookie-Details') + ' (' + cat.cookies.length + ')</button>' +
                    '<table class="hpcc-cookie-details__table" style="display:none;">' +
                    '<tr><th>' + escapeHtml(config.i18n.cookieName || 'Name') + '</th><th>' + escapeHtml(config.i18n.cookieProvider || 'Anbieter') + '</th><th>' + escapeHtml(config.i18n.cookiePurpose || 'Zweck') + '</th><th>' + escapeHtml(config.i18n.cookieDuration || 'Laufzeit') + '</th></tr>';
                for (var ci = 0; ci < cat.cookies.length; ci++) {
                    var c = cat.cookies[ci];
                    cookieTable += '<tr><td>' + escapeHtml(c.name) + '</td><td>' + escapeHtml(c.provider) + '</td><td>' + escapeHtml(c.purpose) + '</td><td>' + escapeHtml(c.duration) + '</td></tr>';
                }
                cookieTable += '</table></div>';
            }

            catHtml += '<div class="hpcc-category">' +
                '<div class="hpcc-category__info">' +
                '<div class="hpcc-category__label">' + escapeHtml(cat.label) + requiredLabel + '</div>' +
                '<div class="hpcc-category__desc">' + escapeHtml(cat.description) + '</div>' +
                cookieTable +
                '</div>' +
                '<label class="hpcc-toggle">' +
                '<input type="checkbox" data-category="' + escapeHtml(key) + '"' + checked + disabled + '>' +
                '<span class="hpcc-toggle__slider"></span>' +
                '</label>' +
                '</div>';
        }

        var html = overlay +
            '<div class="hpcc-banner ' + posClass + ' ' + styleClass + '" id="hpcc-banner" role="dialog" aria-label="Cookie Consent"' +
            ' style="background:' + escapeHtml(config.bgColor) + ';color:' + escapeHtml(config.textColor) + ';--hpcc-primary:' + escapeHtml(config.primaryColor) + '">' +

            // Main view
            '<div class="hpcc-main" id="hpcc-main">' +
            '<h2 class="hpcc-banner__title">' + escapeHtml(config.title) + '</h2>' +
            '<p class="hpcc-banner__text">' + escapeHtml(config.text) + '</p>' +
            links +
            '<div class="hpcc-banner__buttons">' +
            '<button class="hpcc-btn hpcc-btn--primary" id="hpcc-accept-all" style="background:' + escapeHtml(config.primaryColor) + '">' + escapeHtml(config.i18n.acceptAll) + '</button>' +
            '<button class="hpcc-btn hpcc-btn--secondary" id="hpcc-reject-all">' + escapeHtml(config.i18n.rejectAll) + '</button>' +
            '<button class="hpcc-btn hpcc-btn--link" id="hpcc-show-settings">' + escapeHtml(config.i18n.settings) + '</button>' +
            '</div>' +
            '</div>' +

            // Details view
            '<div class="hpcc-details" id="hpcc-details">' +
            '<div class="hpcc-details__header">' +
            '<button class="hpcc-details__back" id="hpcc-back" style="color:' + escapeHtml(config.textColor) + '">←</button>' +
            '<h2 class="hpcc-details__title">' + escapeHtml(config.i18n.settings) + '</h2>' +
            '</div>' +
            catHtml +
            '<div class="hpcc-banner__buttons" style="margin-top:16px;">' +
            '<button class="hpcc-btn hpcc-btn--primary" id="hpcc-save" style="background:' + escapeHtml(config.primaryColor) + '">' + escapeHtml(config.i18n.save) + '</button>' +
            '<button class="hpcc-btn hpcc-btn--primary" id="hpcc-accept-all-detail" style="background:' + escapeHtml(config.primaryColor) + '">' + escapeHtml(config.i18n.acceptAll) + '</button>' +
            '</div>' +
            '</div>' +

            '</div>';

        container.innerHTML = html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ── Banner Logic ── */

    function showBanner() {
        var banner = document.getElementById('hpcc-banner');
        var overlay = document.getElementById('hpcc-overlay');
        var revokeBtn = document.getElementById('hpcc-revoke-btn');

        if (!banner) return;

        if (revokeBtn) revokeBtn.style.display = 'none';

        requestAnimationFrame(function () {
            banner.classList.add('hpcc-visible');
            if (overlay) overlay.classList.add('hpcc-visible');
            trapFocus(banner);
        });
    }

    function trapFocus(container) {
        if (!container) return;
        var focusable = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        first.focus();

        container.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                return; // Don't close on escape - user must make a choice
            }
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function hideBanner() {
        var banner = document.getElementById('hpcc-banner');
        var overlay = document.getElementById('hpcc-overlay');
        var revokeBtn = document.getElementById('hpcc-revoke-btn');

        if (banner) banner.classList.remove('hpcc-visible');
        if (overlay) overlay.classList.remove('hpcc-visible');

        setTimeout(function () {
            var container = document.getElementById('hpcc-cookie-consent');
            if (container) container.innerHTML = '';
            if (revokeBtn) revokeBtn.style.display = 'flex';
        }, 400);
    }

    function getSelectedCategories() {
        var categories = {};
        var inputs = document.querySelectorAll('#hpcc-details input[data-category]');
        for (var i = 0; i < inputs.length; i++) {
            categories[inputs[i].getAttribute('data-category')] = inputs[i].checked;
        }
        return categories;
    }

    function acceptAll() {
        var categories = {};
        var keys = Object.keys(config.categories);
        for (var i = 0; i < keys.length; i++) {
            categories[keys[i]] = true;
        }
        saveConsent(categories);
        hideBanner();
    }

    function rejectAll() {
        var categories = {};
        var keys = Object.keys(config.categories);
        for (var i = 0; i < keys.length; i++) {
            categories[keys[i]] = !!config.categories[keys[i]].required;
        }
        saveConsent(categories);
        hideBanner();
    }

    function saveSelected() {
        saveConsent(getSelectedCategories());
        hideBanner();
    }

    function showSettings() {
        var main = document.getElementById('hpcc-main');
        var details = document.getElementById('hpcc-details');
        if (main) main.style.display = 'none';
        if (details) details.classList.add('hpcc-visible');
    }

    function showMain() {
        var main = document.getElementById('hpcc-main');
        var details = document.getElementById('hpcc-details');
        if (main) main.style.display = '';
        if (details) details.classList.remove('hpcc-visible');
    }

    /* ── Event Binding ── */

    function bindEvents() {
        document.addEventListener('click', function (e) {
            var target = e.target;
            if (!target) return;

            if (target.id === 'hpcc-accept-all' || target.id === 'hpcc-accept-all-detail') {
                acceptAll();
            } else if (target.id === 'hpcc-reject-all') {
                rejectAll();
            } else if (target.id === 'hpcc-show-settings') {
                showSettings();
            } else if (target.id === 'hpcc-back') {
                showMain();
            } else if (target.id === 'hpcc-save') {
                saveSelected();
            } else if (target.id === 'hpcc-revoke-btn') {
                buildBanner();
                showBanner();
                showSettings();
            } else if (target.classList && target.classList.contains('hpcc-iframe-accept-btn')) {
                var iframeCat = target.getAttribute('data-hpcc-category');
                if (iframeCat) {
                    var current = getConsent() || {};
                    current[iframeCat] = true;
                    saveConsent(current);
                }
            } else if (target.classList && target.classList.contains('hpcc-cookie-details__toggle')) {
                var table = target.nextElementSibling;
                if (table) {
                    var isHidden = table.style.display === 'none';
                    table.style.display = isHidden ? 'table' : 'none';
                    target.textContent = (isHidden ? '▾ ' : '▸ ') + target.textContent.substr(2);
                }
            }
        });
    }

    /* ── Init ── */

    function init() {
        var consentData = getConsentData();
        var existing = consentData ? consentData.categories || getConsent() : null;

        if (existing && !needsReconsent(consentData)) {
            var revokeBtn = document.getElementById('hpcc-revoke-btn');
            if (revokeBtn) revokeBtn.style.display = 'flex';
            loadConditionalScripts(existing);
            unblockScripts(existing);
            unblockIframes(existing);
        } else {
            buildBanner();
            showBanner();
        }
    }

    bindEvents();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    window.HPCookieConsent = {
        getConsent: getConsent,
        showBanner: function () {
            buildBanner();
            showBanner();
        },
        showSettings: function () {
            buildBanner();
            showBanner();
            showSettings();
        },
        hasConsent: function (category) {
            var consent = getConsent();
            return consent ? !!consent[category] : false;
        }
    };
})();
