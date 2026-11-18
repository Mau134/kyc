/* Field: my submissions list */
(function () {
    'use strict';
    var chips = document.getElementById('chips');
    var list = document.getElementById('list');
    var more = document.getElementById('moreBtn');
    var q = document.getElementById('q');
    var sf = document.getElementById('searchField');
    var state = { status: chips.getAttribute('data-status'), q: '', page: 1 };
    var req = 0, loaded = false;

    function setChips() {
        chips.querySelectorAll('.chip').forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-s') === state.status); });
        var active = chips.querySelector('.chip.active');
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    function emptyHtml() {
        var msg = state.q ? ['No matches', 'Try a different name, reference or ID number.']
            : state.status === 'rejected' ? ['Nothing returned', 'None of your KYC submissions have been rejected.']
            : state.status === 'review' ? ['Nothing in review', 'Your submitted KYC will show here while the office reviews it.']
            : state.status === 'approved' ? ['No approvals yet', 'Approved customers will appear here.']
            : ['No submissions yet', 'Tap the + button to capture your first customer.'];
        return '<div class="f-empty"><div class="empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div><strong>' + msg[0] + '</strong>' + msg[1] + '</div>';
    }

    function load(append) {
        var id = ++req;
        if (append) SB.btnLoading(more, true);
        else if (loaded) list.classList.add('is-refreshing');
        var p = new URLSearchParams({ status: state.status, q: state.q, page: state.page });
        history.replaceState(null, '', 'submissions.php' + (state.status !== 'all' ? '?status=' + state.status : ''));
        SB.api('ajax/my_records.php?' + p.toString()).then(function (res) {
            if (id !== req) return;
            loaded = true;
            Object.keys(res.counts).forEach(function (k) {
                var el = chips.querySelector('[data-c="' + k + '"]');
                if (el) el.textContent = res.counts[k];
            });
            var html = res.rows.map(SBField.itemHtml).join('');
            if (append) list.insertAdjacentHTML('beforeend', html);
            else list.innerHTML = html || emptyHtml();
            more.classList.toggle('hidden', !res.has_more);
        }).catch(function (e) {
            if (id !== req) return;
            SB.toast('error', 'Could not load submissions', e.message);
            if (!loaded) list.innerHTML = '<div class="f-empty"><strong>Connection problem</strong>Check your network and pull down to refresh.</div>';
        }).finally(function () {
            if (id !== req) return;
            list.classList.remove('is-refreshing');
            sf.classList.remove('busy');
            SB.btnLoading(more, false);
        });
    }

    chips.addEventListener('click', function (e) {
        var c = e.target.closest('.chip');
        if (!c) return;
        state.status = c.getAttribute('data-s');
        state.page = 1;
        setChips();
        load(false);
    });
    var t;
    q.addEventListener('input', function () {
        clearTimeout(t);
        sf.classList.add('busy');
        t = setTimeout(function () { state.q = q.value.trim(); state.page = 1; load(false); }, 350);
    });
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); q.blur(); } });
    more.addEventListener('click', function () { state.page++; load(true); });

    setChips();
    load(false);
})();
