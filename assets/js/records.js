/* Records page: AJAX filtering, search, pagination, live refresh */
(function () {
    'use strict';
    var esc = SB.esc;
    var card = document.getElementById('recordsCard');
    var state = JSON.parse(card.getAttribute('data-initial'));
    var tbody = document.getElementById('rows');
    var qInput = document.getElementById('q');
    var typeSel = document.getElementById('type');
    var sortSel = document.getElementById('sort');
    var searchField = document.getElementById('searchField');
    var live = document.getElementById('liveBadge'), liveText = document.getElementById('liveText');
    var prevBtn = document.getElementById('prevBtn'), nextBtn = document.getElementById('nextBtn');
    var reqId = 0, loaded = false, lastPages = 1;

    typeSel.value = state.type;
    sortSel.value = state.sort;

    var CHEVRON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

    function setTabs() {
        document.querySelectorAll('#statusTabs .tab').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-status') === state.status);
        });
    }

    function syncUrl() {
        var p = new URLSearchParams();
        if (state.q) p.set('q', state.q);
        if (state.status !== 'all') p.set('status', state.status);
        if (state.type) p.set('type', state.type);
        if (state.queue) p.set('queue', state.queue);
        if (state.sort !== (state.queue ? 'oldest' : 'newest')) p.set('sort', state.sort);
        if (state.page > 1) p.set('page', state.page);
        var qs = p.toString();
        history.replaceState(null, '', 'records.php' + (qs ? '?' + qs : ''));
    }

    function rowHtml(r) {
        var biz = r.account_type === 'business';
        return '<tr data-href="view.php?id=' + r.id + '">' +
            '<td><div class="cell-customer"><span class="row-avatar ' + (biz ? 'business' : '') + '">' + esc(SB.initials(r.name)) + '</span>' +
            '<div><strong>' + esc(r.name) + '</strong><small>' + (biz ? '<span class="tag business">Business</span>' : '<span class="tag personal">Personal</span>') +
            (r.phone_number ? ' · ' + esc(r.phone_number) : '') + '</small></div></div></td>' +
            '<td><span class="mono">' + esc(r.reference_no) + '</span></td>' +
            '<td>' + esc(r.id_number || '—') + '<span class="cell-sub">' + esc(r.id_type || '') + '</span></td>' +
            '<td>' + esc(r.officer_name) + '</td>' +
            '<td>' + SB.pill(r.status) + '</td>' +
            '<td>' + esc(SB.fmtDate(r.submitted_at)) + '<span class="cell-sub">' + esc(SB.ago(r.submitted_at)) + '</span></td>' +
            '<td style="text-align:right"><a class="row-open" href="view.php?id=' + r.id + '" aria-label="Open record">' + CHEVRON + '</a></td>' +
            '</tr>';
    }

    function emptyHtml() {
        var filtered = state.q || state.type || state.status !== 'all';
        return '<tr><td colspan="7" style="cursor:default"><div class="empty-state">' +
            '<div class="empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>' +
            '<strong>' + (filtered ? 'No matching records' : (state.queue ? 'Your queue is empty' : 'No KYC records yet')) + '</strong>' +
            (filtered ? 'Try a different search term or clear the filters.' : (state.queue ? 'Nothing is waiting for your action right now.' : 'Submissions from the mobile app will appear here.')) +
            (filtered ? '<div style="margin-top:14px"><button class="btn btn-light btn-sm" id="clearFilters"><span>Clear filters</span></button></div>' : '') +
            '</div></td></tr>';
    }

    function load(opts) {
        opts = opts || {};
        var id = ++reqId;
        if (loaded && !opts.silent) tbody.classList.add('is-refreshing');
        if (opts.fromSearch) searchField.classList.add('busy');
        live.classList.add('syncing');
        liveText.textContent = 'Syncing…';
        syncUrl();

        var p = new URLSearchParams({
            q: state.q, status: state.status, type: state.type, queue: state.queue,
            sort: state.sort, page: state.page, per_page: 12
        });
        return SB.api('ajax/records.php?' + p.toString(), { silent: opts.silent }).then(function (res) {
            if (id !== reqId) return; // a newer request is in flight
            loaded = true;
            Object.keys(res.counts).forEach(function (k) {
                var el = document.querySelector('[data-c="' + k + '"]');
                if (el) el.textContent = res.counts[k];
            });
            state.page = res.page;
            lastPages = res.pages;
            tbody.innerHTML = res.rows.length ? res.rows.map(rowHtml).join('') : emptyHtml();
            var from = res.total ? (res.page - 1) * res.per_page + 1 : 0;
            var to = Math.min(res.total, res.page * res.per_page);
            document.getElementById('summary').textContent = res.total
                ? 'Showing ' + from + '–' + to + ' of ' + SB.num(res.total) + ' record' + (res.total === 1 ? '' : 's')
                : 'No records';
            document.getElementById('pageInfo').textContent = 'Page ' + res.page + ' of ' + res.pages;
            prevBtn.disabled = res.page <= 1;
            nextBtn.disabled = res.page >= res.pages;
            var now = new Date();
            liveText.textContent = 'Updated ' + ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
            syncUrl();
        }).catch(function (err) {
            if (id !== reqId) return;
            liveText.textContent = 'Offline';
            if (!opts.silent) {
                SB.toast('error', 'Could not load records', err.message);
                if (!loaded) tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><strong>Something went wrong</strong>' + esc(err.message) +
                    '<div style="margin-top:14px"><button class="btn btn-primary btn-sm" id="retryBtn"><span>Try again</span></button></div></div></td></tr>';
            }
        }).finally(function () {
            if (id !== reqId) return;
            tbody.classList.remove('is-refreshing');
            searchField.classList.remove('busy');
            live.classList.remove('syncing');
        });
    }

    /* ---------- Events ---------- */
    document.getElementById('statusTabs').addEventListener('click', function (e) {
        var t = e.target.closest('.tab');
        if (!t) return;
        state.status = t.getAttribute('data-status');
        state.page = 1;
        setTabs();
        load();
    });

    var debounce;
    qInput.addEventListener('input', function () {
        clearTimeout(debounce);
        searchField.classList.add('busy');
        debounce = setTimeout(function () {
            state.q = qInput.value.trim();
            state.page = 1;
            load({ fromSearch: true });
        }, 350);
    });
    qInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    typeSel.addEventListener('change', function () { state.type = typeSel.value; state.page = 1; load(); });
    sortSel.addEventListener('change', function () { state.sort = sortSel.value; state.page = 1; load(); });
    prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page--; load(); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
    nextBtn.addEventListener('click', function () { if (state.page < lastPages) { state.page++; load(); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
    live.style.cursor = 'pointer';
    live.addEventListener('click', function () { load(); });

    tbody.addEventListener('click', function (e) {
        if (e.target.closest('#clearFilters')) {
            state.q = ''; state.type = ''; state.status = 'all'; state.page = 1;
            qInput.value = ''; typeSel.value = ''; setTabs(); load(); return;
        }
        if (e.target.closest('#retryBtn')) { load(); return; }
        if (e.target.closest('a')) return;
        var tr = e.target.closest('tr[data-href]');
        if (tr) { SB.progress.start(); location.href = tr.getAttribute('data-href'); }
    });

    setTabs();
    load();
    setInterval(function () { if (!document.hidden && !qInput.matches(':focus')) load({ silent: true }); }, 30000);
})();
