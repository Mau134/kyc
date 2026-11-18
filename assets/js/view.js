/* Record view: approve / reject with confirmation, critical-action loader, toasts */
(function () {
    'use strict';
    var form = document.getElementById('decisionForm');

    // Remember where we came from so "Back" returns to the filtered list
    var back = document.getElementById('backLink');
    if (back && document.referrer && /records\.php|index\.php/.test(document.referrer) && document.referrer.indexOf(location.host) !== -1) {
        back.href = document.referrer;
    }

    if (!form) return;

    var modal = document.getElementById('confirmModal');
    var notes = document.getElementById('notes');
    var confirmBtn = document.getElementById('mConfirm');
    var role = form.getAttribute('data-role');
    var pendingAction = null;

    var ICONS = {
        approve: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16 9.5"/></svg>',
        reject: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
    };

    function openModal(action) {
        pendingAction = action;
        var approve = action === 'approve';
        document.getElementById('mIcon').className = 'm-icon ' + action;
        document.getElementById('mIcon').innerHTML = ICONS[action];
        document.getElementById('mTitle').textContent = approve
            ? (role === 'supervisor' ? 'Approve and send to Compliance?' : 'Give final approval?')
            : 'Reject this KYC submission?';
        document.getElementById('mText').textContent = approve
            ? (role === 'supervisor'
                ? 'The record will move to the compliance queue for final sign-off.'
                : 'The customer will be marked as fully KYC-approved. This decision is recorded in the audit trail.')
            : 'The record will be closed as rejected and the field officer will need to recapture it. This cannot be undone.';
        confirmBtn.className = 'btn ' + (approve ? 'btn-success' : 'btn-danger-solid');
        document.getElementById('mConfirmLabel').textContent = approve ? 'Yes, approve' : 'Yes, reject';
        modal.classList.add('show');
        setTimeout(function () { confirmBtn.focus(); }, 50);
    }
    function closeModal() { modal.classList.remove('show'); pendingAction = null; }

    form.addEventListener('click', function (e) {
        var b = e.target.closest('[data-action]');
        if (!b) return;
        var action = b.getAttribute('data-action');
        if (action === 'reject' && !notes.value.trim()) {
            notes.focus();
            notes.style.borderColor = '#EF4444';
            SB.toast('error', 'Reason required', 'Please enter a reason before rejecting.');
            return;
        }
        notes.style.borderColor = '';
        openModal(action);
    });
    notes.addEventListener('input', function () { notes.style.borderColor = ''; });

    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.closest('[data-close]')) closeModal();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });

    confirmBtn.addEventListener('click', function () {
        if (!pendingAction) return;
        var action = pendingAction;
        SB.btnLoading(confirmBtn, true);
        form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        setTimeout(function () {
            closeModal();
            SB.overlay.show(
                action === 'approve' ? 'Recording approval…' : 'Recording rejection…',
                'Updating the record and writing to the audit trail'
            );
        }, 150);

        SB.api('approve.php', {
            method: 'POST',
            body: { kyc_id: form.getAttribute('data-id'), action: action, notes: notes.value.trim(), csrf: SB.csrf }
        }).then(function (res) {
            document.getElementById('overlayTitle').textContent = 'Decision saved';
            document.getElementById('overlayText').textContent = 'Refreshing record…';
            sessionStorage.setItem('sb_flash', JSON.stringify({ type: 'success', title: res.message || 'Decision saved' }));
            setTimeout(function () { location.reload(); }, 700);
        }).catch(function (err) {
            SB.overlay.hide();
            SB.btnLoading(confirmBtn, false);
            form.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
            SB.toast('error', 'Decision not saved', err.message);
        });
    });
})();

/* Flash message after reload */
(function () {
    try {
        var f = sessionStorage.getItem('sb_flash');
        if (f) {
            sessionStorage.removeItem('sb_flash');
            f = JSON.parse(f);
            SB.toast(f.type, f.title, f.text);
        }
    } catch (e) { /* storage unavailable */ }
})();
