/* Shared helpers for the field app */
(function () {
    'use strict';
    var esc = SB.esc;
    var MAP = {
        pending:    ['pending', 'With supervisor'],
        compliance: ['compliance', 'With compliance'],
        approved:   ['approved', 'Approved'],
        rejected:   ['rejected', 'Returned']
    };
    function fieldPill(s) {
        var m = MAP[s] || ['neutral', s];
        return '<span class="pill ' + m[0] + '">' + m[1] + '</span>';
    }
    function itemHtml(r) {
        var biz = r.account_type === 'business';
        return '<a class="f-item" href="record.php?id=' + r.id + '"><span class="row-avatar ' + (biz ? 'business' : '') + '">' + esc(SB.initials(r.name)) + '</span>' +
            '<span class="grow"><strong>' + esc(r.name) + '</strong><small class="mono">' + esc(r.reference_no) + '</small>' +
            (r.status === 'rejected' && !r.recaptured && r.rejection_reason ? '<small style="color:#B42318">' + esc(r.rejection_reason) + '</small>' : '') + '</span>' +
            '<span class="right">' + (r.recaptured ? '<span class="pill neutral">Recaptured</span>' : fieldPill(r.status)) + '<small class="muted">' + esc(SB.ago(r.submitted_at)) + '</small></span></a>';
    }
    window.SBField = { itemHtml: itemHtml, fieldPill: fieldPill };
})();
