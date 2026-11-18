/* Field home: personal stats, 7-day bars, recent submissions */
(function () {
    'use strict';
    var esc = SB.esc;
    var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var IC = {
        today: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        review: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        approved: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>',
        rejected: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
    };

    function stat(href, label, value, icon, cls, foot) {
        return '<a class="f-stat" href="' + href + '"><div class="top"><span class="label">' + esc(label) + '</span>' +
            '<span class="kpi-icon ' + cls + '">' + icon + '</span></div><span class="value">' + SB.num(value) + '</span>' +
            (foot ? '<span class="kpi-foot">' + foot + '</span>' : '') + '</a>';
    }

    function render(d) {
        var review = d.counts.pending + d.counts.compliance;
        document.getElementById('stats').innerHTML =
            stat('submissions.php', 'Today', d.today, IC.today, 'total', '<b>' + d.month + '</b> this month') +
            stat('submissions.php?status=review', 'In review', review, IC.review, 'pending', 'with the office') +
            stat('submissions.php?status=approved', 'Approved', d.counts.approved, IC.approved, 'approved', d.total ? '<b>' + Math.round(d.counts.approved / d.total * 100) + '%</b> of your KYC' : '') +
            stat('submissions.php?status=rejected', 'Returned', d.returned, IC.rejected, 'rejected', d.returned ? 'need recapture' : 'nothing to fix');

        var max = Math.max(1, Math.max.apply(null, d.days.map(function (x) { return x.count; })));
        document.getElementById('weekBars').innerHTML = d.days.map(function (x, i) {
            var h = x.count ? Math.max(8, Math.round(x.count / max * 54)) : 4;
            var last = i === d.days.length - 1;
            return '<span title="' + x.count + '" style="flex:1;height:0;border-radius:6px;transition:height .6s cubic-bezier(.2,.8,.2,1);background:' +
                (x.count ? (last ? 'linear-gradient(180deg,#1E9BE9,#0033A1)' : '#BFD0F5') : '#E4E9F2') + '" data-h="' + h + '"></span>';
        }).join('');
        document.getElementById('weekLabels').innerHTML = d.days.map(function (x, i) {
            var dt = SB.parseDate(x.date);
            return '<span style="flex:1;text-align:center;' + (i === 6 ? 'color:var(--sb-blue);font-weight:700' : '') + '">' + (i === 6 ? 'Today' : DAYS[dt.getDay()]) + '<br><b style="color:var(--ink)">' + x.count + '</b></span>';
        }).join('');
        requestAnimationFrame(function () {
            document.querySelectorAll('#weekBars [data-h]').forEach(function (b) { b.style.height = b.getAttribute('data-h') + 'px'; });
        });

        var list = document.getElementById('recent');
        if (!d.recent.length) {
            list.innerHTML = '<div class="f-empty"><div class="empty-icon">' + IC.today + '</div><strong>No submissions yet</strong>Tap “Start new KYC” to capture your first customer.</div>';
        } else {
            list.innerHTML = d.recent.map(SBField.itemHtml).join('');
        }
    }


    function load(silent) {
        return SB.api('ajax/home.php', { silent: silent }).then(render).catch(function (e) {
            if (!silent) SB.toast('error', 'Could not load your dashboard', e.message);
        });
    }
    load(false);
    setInterval(function () { if (!document.hidden) load(true); }, 45000);

    // Hide the install tip once the app runs from the home screen
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        var tip = document.getElementById('installTip');
        if (tip) tip.remove();
    }
})();
