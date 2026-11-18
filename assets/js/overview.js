/* Overview page: loads ajax/stats.php, renders KPIs + charts, auto-refreshes every 30s */
(function () {
    'use strict';
    var esc = SB.esc;
    var REFRESH_MS = 30000;
    var data = null;
    var firstLoad = true;
    var ICON = {
        total: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        pending: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        compliance: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        approved: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>',
        rejected: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
    };

    function pct(a, b) { return b ? Math.round((a / b) * 100) : 0; }

    /* ---------------- KPIs ---------------- */
    function renderKpis(k) {
        var c = k.counts, t = k.total;
        var decided = c.approved + c.rejected;
        var delta = k.today - k.yesterday;
        var deltaTxt = delta === 0 ? 'same as yesterday' : (delta > 0 ? '+' + delta : delta) + ' vs yesterday';
        var tiles = [
            { key: 'total', label: 'Total submissions', value: t, href: 'records.php',
              foot: '<b>' + SB.num(k.today) + '</b> today · ' + esc(deltaTxt), bar: null },
            { key: 'pending', label: 'Pending review', value: c.pending, href: 'records.php?status=pending',
              foot: '<b>' + pct(c.pending, t) + '%</b> of all records', bar: pct(c.pending, t), color: '#F59E0B' },
            { key: 'compliance', label: 'Awaiting compliance', value: c.compliance, href: 'records.php?status=compliance',
              foot: '<b>' + pct(c.compliance, t) + '%</b> of all records', bar: pct(c.compliance, t), color: '#1E9BE9' },
            { key: 'approved', label: 'Approved', value: c.approved, href: 'records.php?status=approved',
              foot: '<b>' + pct(c.approved, decided) + '%</b> approval rate', bar: pct(c.approved, t), color: '#10B981' },
            { key: 'rejected', label: 'Rejected', value: c.rejected, href: 'records.php?status=rejected',
              foot: '<b>' + pct(c.rejected, decided) + '%</b> of decisions', bar: pct(c.rejected, t), color: '#EF4444' }
        ];
        document.getElementById('kpis').innerHTML = tiles.map(function (x) {
            return '<a class="card kpi" href="' + x.href + '">' +
                '<div class="kpi-top"><span class="kpi-label">' + esc(x.label) + '</span><span class="kpi-icon ' + x.key + '">' + ICON[x.key] + '</span></div>' +
                '<div class="kpi-value" data-count="' + x.value + '">' + (firstLoad ? 0 : SB.num(x.value)) + '</div>' +
                (x.bar !== null ? '<div class="kpi-bar"><span style="background:' + x.color + '" data-w="' + x.bar + '"></span></div>'
                                : '<div class="kpi-bar"><span style="background:linear-gradient(90deg,#0033A1,#1E9BE9)" data-w="' + (k.week ? 100 : 0) + '"></span></div>') +
                '<div class="kpi-foot">' + x.foot + '</div></a>';
        }).join('');
        SB.animateBars(document.getElementById('kpis'));
        if (firstLoad) countUp();
    }

    function countUp() {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            var target = +el.getAttribute('data-count'), start = null, dur = 700;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min(1, (ts - start) / dur);
                el.textContent = SB.num(Math.round(target * (1 - Math.pow(1 - p, 3))));
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }

    /* ---------------- Trend chart (stacked bars, SVG) ---------------- */
    function renderTrend(trend) {
        var box = document.getElementById('trendChart');
        var W = Math.max(320, box.clientWidth), H = box.clientHeight || 240;
        var pad = { l: 34, r: 8, t: 12, b: 28 };
        var cw = W - pad.l - pad.r, ch = H - pad.t - pad.b;
        var max = Math.max(4, Math.max.apply(null, trend.map(function (d) { return d.total; })));
        var step = Math.ceil(max / 4);
        max = step * 4;
        var bw = cw / trend.length;
        var barW = Math.min(28, bw * 0.58);
        var keys = ['approved', 'compliance', 'pending', 'rejected'];
        var colors = { pending: '#F59E0B', compliance: '#1E9BE9', approved: '#10B981', rejected: '#EF4444' };

        var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none">';
        for (var g = 0; g <= 4; g++) {
            var y = pad.t + ch - (ch * g / 4);
            s += '<line x1="' + pad.l + '" x2="' + (W - pad.r) + '" y1="' + y + '" y2="' + y + '" stroke="#EEF2F8" />';
            s += '<text x="' + (pad.l - 8) + '" y="' + (y + 4) + '" text-anchor="end" font-size="11" fill="#8A93AB">' + (step * g) + '</text>';
        }
        trend.forEach(function (d, i) {
            var x = pad.l + i * bw + (bw - barW) / 2;
            var yCursor = pad.t + ch;
            var segs = keys.filter(function (k) { return d[k] > 0; });
            segs.forEach(function (k, idx) {
                var h = (d[k] / max) * ch;
                yCursor -= h;
                var isTop = idx === segs.length - 1;
                s += '<rect class="bar" x="' + x + '" y="' + yCursor + '" width="' + barW + '" height="' + Math.max(0, h - (isTop ? 0 : 1)) + '" fill="' + colors[k] + '" rx="' + (isTop ? 4 : 0) + '">' +
                     '<animate attributeName="height" from="0" to="' + Math.max(0, h - (isTop ? 0 : 1)) + '" dur="0.5s" fill="freeze" begin="0s" calcMode="spline" keySplines="0.2 0.8 0.2 1" keyTimes="0;1"/>' +
                     '<animate attributeName="y" from="' + (pad.t + ch) + '" to="' + yCursor + '" dur="0.5s" fill="freeze" calcMode="spline" keySplines="0.2 0.8 0.2 1" keyTimes="0;1"/></rect>';
            });
            if (!d.total) {
                s += '<rect x="' + x + '" y="' + (pad.t + ch - 2) + '" width="' + barW + '" height="2" fill="#E4E9F2" rx="1"/>';
            }
            var dt = SB.parseDate(d.date);
            if (i % (trend.length > 10 && W < 560 ? 2 : 1) === 0) {
                s += '<text x="' + (x + barW / 2) + '" y="' + (H - 8) + '" text-anchor="middle" font-size="11" fill="#8A93AB">' + dt.getDate() + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][dt.getMonth()] + '</text>';
            }
            s += '<rect class="hit" data-i="' + i + '" x="' + (pad.l + i * bw) + '" y="' + pad.t + '" width="' + bw + '" height="' + ch + '" fill="transparent"/>';
        });
        s += '</svg><div class="chart-tip" id="chartTip"></div>';
        box.innerHTML = s;

        var tip = document.getElementById('chartTip');
        box.querySelectorAll('.hit').forEach(function (r) {
            r.addEventListener('mouseenter', function () {
                var d = trend[+r.getAttribute('data-i')];
                var rect = box.getBoundingClientRect(), rr = r.getBoundingClientRect();
                tip.innerHTML = '<b>' + SB.fmtDate(d.date) + '</b> · ' + d.total + ' submission' + (d.total === 1 ? '' : 's') +
                    (d.total ? '<br>' + keys.filter(function (k) { return d[k]; }).map(function (k) { return SB.status[k].label + ': ' + d[k]; }).join('<br>') : '');
                tip.style.left = (rr.left - rect.left + rr.width / 2) + 'px';
                tip.style.top = '20px';
                tip.style.opacity = 1;
                r.setAttribute('fill', 'rgba(0,51,161,.04)');
            });
            r.addEventListener('mouseleave', function () { tip.style.opacity = 0; r.setAttribute('fill', 'transparent'); });
        });
    }

    /* ---------------- Donut ---------------- */
    function renderDonut(counts, types) {
        var keys = ['pending', 'compliance', 'approved', 'rejected'];
        var total = keys.reduce(function (a, k) { return a + counts[k]; }, 0);
        var R = 15.915, C = 2 * Math.PI * R, offset = 0;
        var circles = '<circle cx="21" cy="21" r="' + R + '" fill="none" stroke="#EEF2F8" stroke-width="5"/>';
        keys.forEach(function (k) {
            if (!counts[k]) return;
            var len = (counts[k] / total) * C;
            circles += '<circle cx="21" cy="21" r="' + R + '" fill="none" stroke="' + SB.status[k].color + '" stroke-width="5" ' +
                'stroke-dasharray="' + Math.max(0, len - 0.6) + ' ' + (C - len + 0.6) + '" stroke-dashoffset="' + (-offset) + '"/>';
            offset += len;
        });
        var legend = keys.map(function (k) {
            return '<div class="row"><i style="background:' + SB.status[k].color + '"></i><span>' + SB.status[k].label +
                '</span><b>' + SB.num(counts[k]) + '</b><em>' + pct(counts[k], total) + '%</em></div>';
        }).join('');
        var tTotal = (types.personal || 0) + (types.business || 0);
        document.getElementById('donutBox').innerHTML =
            '<div class="donut-wrap"><div class="donut"><svg viewBox="0 0 42 42">' + circles + '</svg>' +
            '<div class="donut-center"><strong>' + SB.num(total) + '</strong><small>records</small></div></div>' +
            '<div class="donut-legend">' + legend + '</div></div>' +
            '<div style="margin-top:22px"><div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px">' +
            '<span><span class="tag personal">Personal</span> ' + SB.num(types.personal || 0) + '</span>' +
            '<span>' + SB.num(types.business || 0) + ' <span class="tag business">Business</span></span></div>' +
            '<div style="display:flex;height:8px;border-radius:8px;overflow:hidden;background:#EEF2F8">' +
            '<span style="width:' + pct(types.personal || 0, tTotal) + '%;background:#0033A1"></span>' +
            '<span style="width:' + pct(types.business || 0, tTotal) + '%;background:#7C5CE0"></span></div></div>';
    }

    /* ---------------- Lists ---------------- */
    function waitLabel(s) {
        var d = SB.parseDate(s);
        if (!d) return '';
        var h = (Date.now() - d.getTime()) / 36e5;
        var cls = h > 48 ? 'rejected' : (h > 24 ? 'pending' : 'neutral');
        return '<span class="pill ' + cls + '" title="Waiting since ' + SB.fmtDate(s, true) + '">' + SB.ago(s).replace(' ago', '') + '</span>';
    }

    function renderQueue(rows, total) {
        document.getElementById('queueCount').textContent = total ? '(' + total + ')' : '';
        var ul = document.getElementById('queueList');
        if (!rows.length) {
            ul.innerHTML = '<li style="border:0"><div class="empty-state" style="width:100%;padding:30px 0">' +
                '<div class="empty-icon">' + ICON.approved + '</div><strong>You\'re all caught up</strong>No records are waiting for your action.</div></li>';
            return;
        }
        ul.innerHTML = rows.map(function (r) {
            var biz = r.account_type === 'business';
            return '<li><span class="row-avatar ' + (biz ? 'business' : '') + '">' + esc(SB.initials(r.name)) + '</span>' +
                '<div class="grow"><a href="view.php?id=' + r.id + '"><strong style="color:var(--ink)">' + esc(r.name) + '</strong></a>' +
                '<small><span class="mono">' + esc(r.reference_no) + '</span> · by ' + esc(r.officer_name) + '</small></div>' +
                SB.pill(r.status) + ' ' + waitLabel(r.submitted_at) +
                '<a class="btn btn-light btn-sm" href="view.php?id=' + r.id + '"><span>Review</span></a></li>';
        }).join('');
    }

    function renderOfficers(list) {
        var ul = document.getElementById('officerList');
        if (!list.length) { ul.innerHTML = '<li class="muted">No submissions yet.</li>'; return; }
        var max = list[0].count || 1;
        ul.innerHTML = list.map(function (o) {
            return '<li><span class="avatar sm">' + esc(SB.initials(o.name)) + '</span><div class="grow">' +
                '<div style="display:flex;justify-content:space-between;gap:8px"><strong>' + esc(o.name) + '</strong><b style="font-variant-numeric:tabular-nums">' + o.count + '</b></div>' +
                '<div class="meter"><span data-w="' + Math.round(o.count / max * 100) + '"></span></div>' +
                '<small>' + o.approved + ' approved</small></div></li>';
        }).join('');
        SB.animateBars(ul);
    }

    function renderActivity(list) {
        var box = document.getElementById('activityBox');
        if (!list.length) { box.innerHTML = '<p class="muted" style="margin:0">No decisions recorded yet.</p>'; return; }
        box.innerHTML = '<ul class="timeline">' + list.map(function (a) {
            var act = String(a.action || '').toLowerCase();
            var cls = act === 'approve' ? 'approve' : (act === 'reject' ? 'reject' : '');
            var verb = act === 'approve' ? (a.role === 'compliance' ? 'gave final approval' : 'reviewed & approved')
                     : act === 'reject' ? 'rejected' : act === 'resubmit' ? 'recaptured a returned KYC' : act === 'recaptured' ? 'replaced a returned KYC' : esc(act);
            return '<li><span class="t-dot ' + cls + '">' + (cls === 'reject'
                    ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                    : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>') + '</span>' +
                '<strong>' + esc(a.actor) + '</strong> <span class="muted">' + verb + '</span>' +
                '<div class="t-meta"><a class="mono" href="view.php?id=' + (+a.kyc_id) + '">' + esc(a.reference_no || ('#' + a.kyc_id)) + '</a> · ' + esc(SB.ago(a.created_at)) + '</div></li>';
        }).join('') + '</ul>';
    }

    /* ---------------- Load / refresh ---------------- */
    var live = document.getElementById('liveBadge'), liveText = document.getElementById('liveText');

    function load(silent) {
        live.classList.add('syncing');
        liveText.textContent = 'Syncing…';
        return SB.api('ajax/stats.php', { silent: silent }).then(function (res) {
            data = res;
            var q = res.queue_total;
            document.getElementById('heroText').textContent = q
                ? 'You have ' + q + ' record' + (q === 1 ? '' : 's') + ' waiting in your queue.'
                : 'Your queue is clear — nice work.';
            renderKpis(res.kpis);
            renderTrend(res.trend);
            renderDonut(res.kpis.counts, res.types);
            renderQueue(res.queue, res.queue_total);
            renderOfficers(res.officers);
            renderActivity(res.activity);
            firstLoad = false;
            liveText.textContent = 'Updated ' + res.generated_at.slice(11, 16);
        }).catch(function (err) {
            liveText.textContent = 'Offline';
            if (!silent) SB.toast('error', 'Could not load dashboard', err.message);
        }).finally(function () {
            live.classList.remove('syncing');
        });
    }

    load(false);
    setInterval(function () { if (!document.hidden) load(true); }, REFRESH_MS);
    live.style.cursor = 'pointer';
    live.addEventListener('click', function () { load(false); });

    var rt;
    window.addEventListener('resize', function () {
        clearTimeout(rt);
        rt = setTimeout(function () { if (data) renderTrend(data.trend); }, 150);
    });
})();
