/* Standard Bank KYC Dashboard — shared UI helpers (no external libraries) */
(function () {
    'use strict';

    var SB = window.SB = {};
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    SB.csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    /* ---------------- Text helpers ---------------- */
    SB.esc = function (v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };
    SB.initials = function (name) {
        var p = String(name || '').trim().split(/\s+/).slice(0, 2);
        return p.map(function (s) { return s.charAt(0).toUpperCase(); }).join('') || '?';
    };
    SB.status = {
        pending:    { label: 'Pending Review',      color: '#F59E0B' },
        compliance: { label: 'Awaiting Compliance', color: '#1E9BE9' },
        approved:   { label: 'Approved',            color: '#10B981' },
        rejected:   { label: 'Rejected',            color: '#EF4444' }
    };
    SB.pill = function (key) {
        var m = SB.status[key] || { label: key };
        return '<span class="pill ' + SB.esc(key) + '">' + SB.esc(m.label) + '</span>';
    };
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    SB.parseDate = function (s) {
        if (!s) return null;
        var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        if (!m) return null;
        return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0));
    };
    SB.fmtDate = function (s, withTime) {
        var d = SB.parseDate(s);
        if (!d) return '—';
        var out = ('0' + d.getDate()).slice(-2) + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
        if (withTime) out += ', ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
        return out;
    };
    SB.ago = function (s) {
        var d = SB.parseDate(s);
        if (!d) return '';
        var sec = Math.round((Date.now() - d.getTime()) / 1000);
        if (sec < 60) return 'just now';
        var min = Math.round(sec / 60); if (min < 60) return min + ' min ago';
        var h = Math.round(min / 60); if (h < 24) return h + ' hr' + (h > 1 ? 's' : '') + ' ago';
        var days = Math.round(h / 24); if (days < 30) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        return SB.fmtDate(s);
    };
    SB.num = function (n) { return Number(n || 0).toLocaleString(); };

    /* ---------------- Top progress bar ---------------- */
    var bar = document.getElementById('topProgress');
    var barTimer = null, barCount = 0, barWidth = 0;
    SB.progress = {
        start: function () {
            if (!bar) return;
            barCount++;
            if (barCount > 1) return;
            barWidth = 8;
            bar.classList.add('active');
            bar.style.width = barWidth + '%';
            clearInterval(barTimer);
            barTimer = setInterval(function () {
                barWidth += (90 - barWidth) * 0.08;
                bar.style.width = barWidth + '%';
            }, 200);
        },
        done: function () {
            if (!bar) return;
            barCount = Math.max(0, barCount - 1);
            if (barCount > 0) return;
            clearInterval(barTimer);
            bar.style.width = '100%';
            setTimeout(function () { bar.classList.remove('active'); }, 250);
            setTimeout(function () { if (!barCount) bar.style.width = '0'; }, 600);
        },
        reset: function () { barCount = 1; SB.progress.done(); }
    };

    /* ---------------- Full-screen overlay ---------------- */
    var overlay = document.getElementById('overlayLoader');
    SB.overlay = {
        show: function (title, text) {
            if (!overlay) return;
            document.getElementById('overlayTitle').textContent = title || 'Processing…';
            document.getElementById('overlayText').textContent = text || "Please don't close this window.";
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
        },
        hide: function () {
            if (!overlay) return;
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        }
    };

    /* ---------------- Toasts ---------------- */
    var stack = document.getElementById('toastStack');
    var TOAST_ICONS = {
        success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>',
        error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    SB.toast = function (type, title, text, ms) {
        if (!stack) return;
        var el = document.createElement('div');
        el.className = 'toast ' + (type || 'info');
        el.innerHTML = '<span class="t-ic">' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '</span>' +
            '<div><strong>' + SB.esc(title) + '</strong>' + (text ? '<small>' + SB.esc(text) + '</small>' : '') + '</div>';
        stack.appendChild(el);
        setTimeout(function () {
            el.classList.add('leaving');
            setTimeout(function () { el.remove(); }, 260);
        }, ms || 4200);
    };

    /* ---------------- AJAX helper ---------------- */
    SB.api = function (url, options) {
        options = options || {};
        var opts = {
            method: options.method || 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        };
        if (options.body) {
            if (options.body instanceof FormData) {
                opts.body = options.body;
            } else {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                opts.body = new URLSearchParams(options.body).toString();
            }
        }
        if (!options.silent) SB.progress.start();
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return { success: false, message: 'Unexpected server response (' + res.status + ')' }; })
                .then(function (data) {
                    if (res.status === 401) {
                        SB.toast('error', 'Session expired', 'Redirecting to sign in…');
                        setTimeout(function () { location.href = document.body.getAttribute('data-login') || 'login.php'; }, 1200);
                    }
                    if (!res.ok || data.success === false) {
                        var err = new Error(data.message || 'Request failed');
                        err.data = data;
                        throw err;
                    }
                    return data;
                });
        }).finally(function () {
            if (!options.silent) SB.progress.done();
        });
    };

    /* ---------------- Button loading ---------------- */
    SB.btnLoading = function (btn, on) {
        if (!btn) return;
        btn.classList.toggle('is-loading', !!on);
        btn.disabled = !!on;
        btn.setAttribute('aria-busy', on ? 'true' : 'false');
    };

    // Any classic form marked data-loading shows a spinner on its submit button
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-loading]')) return;
        if (e.defaultPrevented) return;
        var btn = form.querySelector('button[type="submit"], button:not([type])');
        SB.btnLoading(btn, true);
        SB.progress.start();
    });

    /* ---------------- Page navigation progress ---------------- */
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || /^(mailto:|tel:|javascript:)/i.test(href)) return;
        if (a.dataset.loading) SB.overlay.show(a.dataset.loading, ' ');
        SB.progress.start();
    });
    document.querySelectorAll('.global-search').forEach(function (f) {
        f.addEventListener('submit', function () { SB.progress.start(); });
    });
    window.addEventListener('pageshow', function () { SB.progress.reset(); SB.overlay.hide(); });

    /* ---------------- Image loaders ---------------- */
    SB.bindImages = function (root) {
        (root || document).querySelectorAll('img[data-load]').forEach(function (img) {
            if (img.dataset.bound) return;
            img.dataset.bound = '1';
            var done = function () {
                img.classList.add('loaded');
                var sk = img.parentNode.querySelector('.skel');
                if (sk) sk.remove();
            };
            var fail = function () {
                img.parentNode.classList.add('error');
                var sk = img.parentNode.querySelector('.skel');
                if (sk) sk.remove();
                img.remove();
            };
            if (img.complete && img.naturalWidth) done();
            else if (img.complete && img.getAttribute('src')) fail();
            else { img.addEventListener('load', done); img.addEventListener('error', fail); }
        });
    };
    SB.bindImages();

    /* ---------------- Lightbox ---------------- */
    var lb = null;
    SB.lightbox = function (src, title) {
        if (!lb) {
            lb = document.createElement('div');
            lb.className = 'lightbox';
            lb.innerHTML = '<button class="icon-btn lb-close" aria-label="Close">' +
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
                '<div class="spinner lb-spinner"></div><img alt=""><div class="lb-bar"><strong class="lb-title"></strong>' +
                '<a class="btn btn-ghost-white btn-sm lb-open" target="_blank" rel="noopener">Open original</a></div>';
            document.body.appendChild(lb);
            lb.addEventListener('click', function (e) {
                if (e.target === lb || e.target.closest('.lb-close')) close();
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        }
        var img = lb.querySelector('img');
        var sp = lb.querySelector('.lb-spinner');
        img.classList.remove('loaded');
        sp.style.display = 'block';
        img.onload = function () { img.classList.add('loaded'); sp.style.display = 'none'; };
        img.onerror = function () { sp.style.display = 'none'; SB.toast('error', 'Could not load image'); };
        img.src = src;
        lb.querySelector('.lb-title').textContent = title || '';
        lb.querySelector('.lb-open').href = src;
        lb.classList.add('show');
        function close() { lb.classList.remove('show'); }
    };
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('[data-lightbox]');
        if (!t || t.classList.contains('error')) return;
        e.preventDefault();
        SB.lightbox(t.getAttribute('data-lightbox'), t.getAttribute('data-title'));
    });

    /* ---------------- Mobile sidebar ---------------- */
    var sidebar = document.getElementById('sidebar');
    var scrim = document.getElementById('sidebarScrim');
    var menuBtn = document.getElementById('menuBtn');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function () { sidebar.classList.add('open'); scrim.classList.add('show'); });
        scrim.addEventListener('click', function () { sidebar.classList.remove('open'); scrim.classList.remove('show'); });
    }

    /* ---------------- Animate meters on first paint ---------------- */
    SB.animateBars = function (root) {
        requestAnimationFrame(function () {
            (root || document).querySelectorAll('[data-w]').forEach(function (el) { el.style.width = el.getAttribute('data-w') + '%'; });
        });
    };
    SB.animateBars();
})();
