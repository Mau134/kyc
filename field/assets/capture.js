/* =====================================================================
   Field KYC capture wizard
   - 6 steps with validation, phone back-button support
   - Photos compressed on the phone, kept in IndexedDB until submitted
   - Typed details auto-saved as a draft (survives the camera killing the tab)
   - Signature pad, upload with % progress, duplicate-safe retries
   ===================================================================== */
(function () {
    'use strict';
    var esc = SB.esc;
    var form = document.getElementById('kycForm');
    var OFFICER = form.getAttribute('data-officer');
    var PREFILL = JSON.parse(form.getAttribute('data-prefill') || 'null');
    var RECAPTURE = JSON.parse(form.getAttribute('data-recapture') || 'null');
    var DRAFT_KEY = 'sbkyc_draft_' + OFFICER + (RECAPTURE ? '_r' + RECAPTURE.id : '');
    var STEPS = 6;
    var step = 1;
    var files = {};          // field -> { blob, name, type, url }
    var dirty = false;
    var submitted = false;
    var submissionKey = null;

    var steps = form.querySelectorAll('.w-step');
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var nextLabel = document.getElementById('nextLabel');

    /* ================= IndexedDB (photos + signature) ================= */
    var idb = (function () {
        var dbp = null;
        function open() {
            if (dbp) return dbp;
            dbp = new Promise(function (res, rej) {
                if (!window.indexedDB) return rej(new Error('no idb'));
                var r = indexedDB.open('sbkyc', 1);
                r.onupgradeneeded = function () { r.result.createObjectStore('files'); };
                r.onsuccess = function () { res(r.result); };
                r.onerror = function () { rej(r.error); };
            });
            return dbp;
        }
        function tx(mode, fn) {
            return open().then(function (db) {
                return new Promise(function (res, rej) {
                    var t = db.transaction('files', mode);
                    var out = fn(t.objectStore('files'));
                    t.oncomplete = function () { res(out && out.result); };
                    t.onerror = function () { rej(t.error); };
                });
            }).catch(function () { return null; });
        }
        return {
            put: function (k, v) { return tx('readwrite', function (s) { s.put(v, DRAFT_KEY + ':' + k); }); },
            get: function (k) { return tx('readonly', function (s) { return s.get(DRAFT_KEY + ':' + k); }); },
            del: function (k) { return tx('readwrite', function (s) { s.delete(DRAFT_KEY + ':' + k); }); }
        };
    })();

    /* ================= Helpers ================= */
    function val(name) {
        var el = form.elements[name];
        if (!el) return '';
        if (el instanceof RadioNodeList) return el.value || '';
        if (el.type === 'checkbox') return el.checked ? '1' : '';
        return (el.value || '').trim();
    }
    function isBiz() { return val('account_type') === 'business'; }
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }
    function fmtBytes(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB'; }

    /* ================= Draft (typed fields) ================= */
    function collect() {
        var data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.type === 'file') return;
            if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; return; }
            if (el.type === 'checkbox') { data[el.name] = el.checked; return; }
            data[el.name] = el.value;
        });
        return data;
    }
    function apply(data) {
        Object.keys(data || {}).forEach(function (k) {
            var el = form.elements[k];
            if (!el) return;
            if (el instanceof RadioNodeList) {
                Array.prototype.forEach.call(el, function (r) { r.checked = r.value === data[k]; });
            } else if (el.type === 'checkbox') {
                el.checked = !!data[k];
            } else {
                el.value = data[k];
            }
        });
    }
    var saveTimer;
    function saveDraft() {
        if (submitted || !dirty) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify({ at: Date.now(), step: step, key: submissionKey, data: collect() }));
                var n = document.getElementById('draftNote');
                n.style.opacity = 1;
            } catch (e) { /* storage full or blocked */ }
        }, 300);
    }
    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        ['id_document', 'face_photo', 'income_document', 'signature'].forEach(function (k) { idb.del(k); });
    }

    /* ================= Steps ================= */
    function show(n, back, fromHistory) {
        step = Math.max(1, Math.min(STEPS, n));
        steps.forEach(function (s) {
            var on = +s.getAttribute('data-step') === step;
            s.classList.toggle('active', on);
            s.classList.toggle('back', on && !!back);
        });
        document.querySelectorAll('#dots i').forEach(function (d, i) {
            d.className = i + 1 < step ? 'done' : (i + 1 === step ? 'current' : '');
        });
        document.getElementById('stepNo').textContent = step;
        document.getElementById('stepName').textContent = steps[step - 1].getAttribute('data-name');
        prevBtn.style.visibility = step === 1 ? 'hidden' : 'visible';
        nextLabel.textContent = step === STEPS ? 'Submit KYC' : (step === STEPS - 1 ? 'Review' : 'Continue');
        nextBtn.className = 'btn ' + (step === STEPS ? 'btn-success' : 'btn-primary');
        if (step === 2) applyType();
        if (step === 5) sig.resize();
        if (step === STEPS) renderReview();
        if (!fromHistory) history.pushState({ kycStep: step }, '', location.pathname + location.search);
        window.scrollTo(0, 0);
        saveDraft();
    }
    window.addEventListener('popstate', function (e) {
        if (submitted) return;
        var s = e.state && e.state.kycStep;
        if (s) show(s, s < step, true);
        else if (step > 1) { show(step - 1, true, true); history.pushState({ kycStep: step }, '', location.href); }
    });
    // Header back arrow steps back through the wizard instead of leaving
    var headerBack = document.querySelector('.f-bar-btn');
    if (headerBack) headerBack.addEventListener('click', function (e) {
        if (step > 1 && !submitted) { e.preventDefault(); e.stopPropagation(); show(step - 1, true); }
    }, true);

    prevBtn.addEventListener('click', function () { show(step - 1, true); });
    nextBtn.addEventListener('click', function () {
        if (step === STEPS) return submit();
        if (!validateStep(step)) return;
        show(step + 1);
    });

    function applyType() {
        var biz = isBiz();
        form.querySelectorAll('.biz-only').forEach(function (el) { el.classList.toggle('hidden', !biz); });
        document.getElementById('detailsTitle').textContent = biz ? 'Business & signatory' : 'Customer details';
        document.getElementById('personTitle').textContent = biz ? 'Authorised signatory' : 'Personal & identity';
    }

    /* ================= Validation ================= */
    var RULES = {
        phone: function (v) { return /^(\+?265|0)[1-9]\d{8}$/.test(v.replace(/[\s-]/g, '')) || 'Enter a Malawi number, e.g. 0999 123 456'; },
        email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || 'Enter a valid email address'; },
        dob: function (v) {
            var d = new Date(v + 'T00:00:00'), now = new Date();
            if (isNaN(d) || d > now) return 'Enter a valid date of birth';
            var age = (now - d) / 31557600000;
            if (age > 120) return 'Check the year of birth';
            return true;
        },
        idno: function (v) { return v.replace(/\s/g, '').length >= 6 || 'ID number looks too short'; },
        money: function (v) { return /^\d+(\.\d{1,2})?$/.test(v.replace(/,/g, '')) || 'Numbers only, e.g. 250000'; }
    };
    function fieldWrap(el) { return el.closest('.f-field'); }
    function setError(el, msg) {
        var w = fieldWrap(el);
        if (!w) return;
        w.classList.toggle('invalid', !!msg);
        var e = w.querySelector('.f-error');
        if (e) e.textContent = msg || '';
    }
    function checkField(el) {
        var v = el.type === 'radio' ? val(el.name) : (el.value || '').trim();
        var required = el.hasAttribute('data-req') || (el.hasAttribute('data-req-biz') && isBiz());
        if (!v) {
            if (required) { setError(el, 'This field is required'); return false; }
            setError(el, ''); return true;
        }
        var t = el.getAttribute('data-type');
        if (t && RULES[t]) {
            var r = RULES[t](v);
            if (r !== true) { setError(el, r); return false; }
        }
        setError(el, '');
        return true;
    }
    function validateStep(n) {
        var sec = steps[n - 1];
        var ok = true, first = null;
        if (n === 1) {
            ok = !!val('account_type');
            document.getElementById('typeError').style.display = ok ? 'none' : 'block';
            if (!ok) SB.toast('error', 'Choose an account type');
            return ok;
        }
        if (n === 4) return validateDocs(true);
        if (n === 5) {
            var signed = sig.isSigned();
            document.getElementById('sigWrap').classList.toggle('invalid', !signed);
            document.getElementById('sigError').style.display = signed ? 'none' : 'block';
            var consent = val('consent') === '1';
            document.getElementById('consentRow').classList.toggle('invalid', !consent);
            document.getElementById('consentError').style.display = consent ? 'none' : 'block';
            if (!signed || !consent) SB.toast('error', !signed ? 'Signature required' : 'Consent required');
            return signed && consent;
        }
        var seen = {};
        sec.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || el.type === 'file' || seen[el.name]) return;
            if (el.closest('.hidden')) { setError(el, ''); return; }
            seen[el.name] = true;
            if (!checkField(el)) { ok = false; if (!first) first = el; }
        });
        if (!ok) {
            SB.toast('error', 'Check the highlighted fields');
            var w = fieldWrap(first);
            (w || first).scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (first.type !== 'radio') setTimeout(function () { first.focus({ preventScroll: true }); }, 300);
        }
        return ok;
    }
    form.addEventListener('input', function (e) {
        dirty = true;
        var w = e.target.closest && e.target.closest('.f-field.invalid');
        if (w) checkField(e.target);
        saveDraft();
    });
    form.addEventListener('change', function (e) {
        dirty = true;
        if (e.target.name === 'account_type') document.getElementById('typeError').style.display = 'none';
        if (e.target.name === 'consent') {
            document.getElementById('consentRow').classList.remove('invalid');
            document.getElementById('consentError').style.display = 'none';
        }
        if (e.target.type === 'radio' || e.target.tagName === 'SELECT') checkField(e.target);
        saveDraft();
    });
    form.addEventListener('focusout', function (e) {
        var el = e.target;
        if (el.name && el.type !== 'file' && el.type !== 'radio' && el.value) checkField(el);
    });
    // Account type: tapping a card moves on straight away
    form.querySelectorAll('input[name="account_type"]').forEach(function (r) {
        r.addEventListener('change', function () { setTimeout(function () { if (step === 1) show(2); }, 220); });
    });

    /* ================= Documents ================= */
    var MAX_DIM = 1600, QUALITY = 0.82;

    function compress(file) {
        if (file.type === 'application/pdf') return Promise.resolve(file);
        return new Promise(function (resolve) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                var scale = Math.min(1, MAX_DIM / Math.max(img.naturalWidth, img.naturalHeight));
                var w = Math.round(img.naturalWidth * scale), h = Math.round(img.naturalHeight * scale);
                var c = document.createElement('canvas');
                c.width = w; c.height = h;
                var ctx = c.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                URL.revokeObjectURL(url);
                c.toBlob(function (b) { resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', QUALITY);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); }; // e.g. HEIC the browser can't draw
            img.src = url;
        });
    }

    function setCapture(field, blob, persist) {
        var tile = form.querySelector('[data-cap="' + field + '"]');
        if (!tile) return;
        if (files[field] && files[field].url) URL.revokeObjectURL(files[field].url);
        var isPdf = blob.type === 'application/pdf';
        var url = URL.createObjectURL(blob);
        files[field] = { blob: blob, type: blob.type, url: url, name: field + (isPdf ? '.pdf' : '.jpg') };
        var img = tile.querySelector('.cap-preview img');
        var pdf = tile.querySelector('.cap-preview .pdf');
        img.classList.toggle('hidden', isPdf);
        pdf.classList.toggle('hidden', !isPdf);
        if (!isPdf) img.src = url;
        tile.querySelector('.cap-size').textContent = fmtBytes(blob.size);
        tile.classList.add('has-file');
        tile.classList.remove('invalid');
        if (persist) idb.put(field, blob);
    }
    function removeCapture(field) {
        var tile = form.querySelector('[data-cap="' + field + '"]');
        if (files[field] && files[field].url) URL.revokeObjectURL(files[field].url);
        delete files[field];
        tile.classList.remove('has-file');
        tile.querySelector('.cap-preview img').removeAttribute('src');
        idb.del(field);
    }
    function validateDocs(toast) {
        var missing = [];
        form.querySelectorAll('.cap[data-required="1"]').forEach(function (t) {
            var f = t.getAttribute('data-cap');
            var miss = !files[f];
            t.classList.toggle('invalid', miss);
            if (miss) missing.push(t.querySelector('.cap-title span').textContent);
        });
        var e = document.getElementById('docsError');
        e.textContent = missing.length ? 'Still needed: ' + missing.join(', ') : '';
        e.style.display = missing.length ? 'block' : 'none';
        if (missing.length && toast) SB.toast('error', 'Photos missing', missing.join(', '));
        return !missing.length;
    }

    form.querySelectorAll('.cap').forEach(function (tile) {
        var field = tile.getAttribute('data-cap');
        tile.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-pick]');
            if (e.target.closest('[data-remove]')) { removeCapture(field); return; }
            if (pick) {
                e.stopPropagation();
                tile.querySelector('input[data-input="' + pick.getAttribute('data-pick') + '"]').click();
                return;
            }
            if (e.target.closest('.cap-preview') && files[field] && files[field].type !== 'application/pdf') {
                SB.lightbox(files[field].url, tile.querySelector('.cap-title span').textContent);
            }
        });
        tile.querySelectorAll('input[type=file]').forEach(function (input) {
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                input.value = '';
                if (!f) return;
                if (!/^image\//.test(f.type) && f.type !== 'application/pdf') {
                    SB.toast('error', 'Unsupported file', 'Use a photo (JPG/PNG) or a PDF.');
                    return;
                }
                tile.classList.add('processing');
                compress(f).then(function (blob) {
                    if (blob.size > 10 * 1024 * 1024) {
                        SB.toast('error', 'File too large', 'Keep files under 10 MB.');
                        return;
                    }
                    setCapture(field, blob, true);
                    dirty = true;
                    validateDocs(false);
                    document.getElementById('docsError').style.display = 'none';
                }).finally(function () { tile.classList.remove('processing'); });
            });
        });
    });

    /* ================= Signature pad ================= */
    var sig = (function () {
        var canvas = document.getElementById('sigPad');
        var wrap = document.getElementById('sigWrap');
        var ctx = canvas.getContext('2d');
        var drawing = false, signed = false, last = null, strokes = 0;
        var snapshot = null;

        function resize() {
            var r = canvas.getBoundingClientRect();
            if (!r.width) return;
            var dpr = Math.max(1, window.devicePixelRatio || 1);
            var keep = signed ? canvas.toDataURL('image/png') : snapshot;
            canvas.width = Math.round(r.width * dpr);
            canvas.height = Math.round(r.height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0F1A33';
            if (keep) {
                var im = new Image();
                im.onload = function () { ctx.drawImage(im, 0, 0, r.width, r.height); };
                im.src = keep;
            }
        }
        function pos(e) {
            var r = canvas.getBoundingClientRect();
            return { x: e.clientX - r.left, y: e.clientY - r.top, p: e.pressure || 0.5 };
        }
        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            canvas.setPointerCapture(e.pointerId);
            drawing = true; last = pos(e);
            ctx.beginPath(); ctx.arc(last.x, last.y, 1.1, 0, Math.PI * 2); ctx.fillStyle = '#0F1A33'; ctx.fill();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            e.preventDefault();
            var p = pos(e);
            var mid = { x: (last.x + p.x) / 2, y: (last.y + p.y) / 2 };
            ctx.lineWidth = 1.6 + p.p * 1.6;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.quadraticCurveTo(last.x, last.y, mid.x, mid.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            last = p;
            strokes++;
        });
        function end() {
            if (!drawing) return;
            drawing = false;
            if (strokes > 6) {
                signed = true;
                wrap.classList.add('signed');
                wrap.classList.remove('invalid');
                document.getElementById('sigError').style.display = 'none';
                document.getElementById('sigState').innerHTML = '<span style="color:#047857;font-weight:600">✓ Signed</span>';
                persist();
            }
        }
        canvas.addEventListener('pointerup', end);
        canvas.addEventListener('pointercancel', end);
        canvas.addEventListener('pointerleave', end);

        function clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            signed = false; strokes = 0; snapshot = null;
            wrap.classList.remove('signed');
            document.getElementById('sigState').textContent = 'Not signed yet';
            delete files.signature;
            idb.del('signature');
        }
        document.getElementById('sigClear').addEventListener('click', clear);

        function toBlob() {
            // Export on white so it prints and previews cleanly
            return new Promise(function (res) {
                var out = document.createElement('canvas');
                out.width = canvas.width; out.height = canvas.height;
                var o = out.getContext('2d');
                o.fillStyle = '#fff'; o.fillRect(0, 0, out.width, out.height);
                o.drawImage(canvas, 0, 0);
                out.toBlob(res, 'image/png');
            });
        }
        function persist() {
            toBlob().then(function (b) {
                if (!b) return;
                if (files.signature && files.signature.url) URL.revokeObjectURL(files.signature.url);
                files.signature = { blob: b, type: 'image/png', url: URL.createObjectURL(b), name: 'signature.png' };
                idb.put('signature', b);
            });
        }
        function restore(blob) {
            snapshot = URL.createObjectURL(blob);
            files.signature = { blob: blob, type: 'image/png', url: snapshot, name: 'signature.png' };
            signed = true; strokes = 99;
            wrap.classList.add('signed');
            document.getElementById('sigState').innerHTML = '<span style="color:#047857;font-weight:600">✓ Signed</span>';
            resize();
        }
        var rt;
        window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { if (step === 5) resize(); }, 120); });
        return { resize: resize, isSigned: function () { return signed; }, restore: restore, clear: clear };
    })();

    /* ================= Review ================= */
    function row(label, value) {
        var v = String(value == null ? '' : value).trim();
        return '<div class="rv-row"><span>' + esc(label) + '</span><b class="' + (v ? '' : 'missing') + '">' + (v ? esc(v) : '—') + '</b></div>';
    }
    function section(title, goTo, body) {
        return '<div class="rv-section"><div class="rv-head"><h4>' + esc(title) + '</h4><button type="button" data-goto="' + goTo + '">Edit</button></div>' + body + '</div>';
    }
    function thumb(field, label, sig) {
        var f = files[field];
        var inner = !f ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>'
            : (f.type === 'application/pdf' ? '<b style="font-size:11px;color:var(--sb-blue)">PDF</b>' : '<img src="' + f.url + '" alt="">');
        return '<figure><div class="thumb' + (sig ? ' sig' : '') + '">' + inner + '</div>' + esc(label) + (f ? '' : '<br><em style="color:#B42318;font-style:normal">' + (field === 'income_document' ? 'none' : 'missing') + '</em>') + '</figure>';
    }
    function renderReview() {
        var biz = isBiz();
        var money = val('monthly_income') ? 'MWK ' + Number(val('monthly_income').replace(/,/g, '')).toLocaleString() : '';
        var dob = val('date_of_birth') ? SB.fmtDate(val('date_of_birth')) : '';
        var h = '';
        h += section('Account', 1, row('Account type', biz ? 'Business' : 'Personal') +
            (RECAPTURE ? row('Replaces', RECAPTURE.reference_no) : ''));
        if (biz) {
            h += section('Business', 2, row('Business name', val('business_name')) + row('Trading name', val('trading_name')) +
                row('Registration no.', val('business_registration_no')) + row('TPIN', val('tax_pin')) +
                row('Contact person', val('contact_person_name')) + row('Contact phone', val('contact_person_phone')));
        }
        h += section(biz ? 'Signatory' : 'Customer', 2,
            row('Name', val('customer_first_name') + ' ' + val('customer_last_name')) + row('Date of birth', dob) +
            row('Gender', val('gender')) + row('Nationality', val('nationality')) +
            row(val('id_type') || 'ID', val('id_number')) + row('Phone', val('phone_number')) + row('Email', val('email')));
        h += section('Address & income', 3, row('Address', val('address_line')) + row('District', val('district')) +
            row('Home village', [val('home_village'), val('home_ta') ? 'TA ' + val('home_ta') : '', val('home_district')].filter(Boolean).join(', ')) +
            row('Employer', val('employer_name')) + row('Monthly income', money));
        h += section('Documents & signature', 4, '<div class="rv-docs">' + thumb('id_document', 'ID') + thumb('face_photo', 'Photo') +
            thumb('income_document', 'Income') + thumb('signature', 'Signature', true) + '</div>');
        document.getElementById('review').innerHTML = h;
    }
    document.getElementById('review').addEventListener('click', function (e) {
        var b = e.target.closest('[data-goto]');
        if (b) show(+b.getAttribute('data-goto'), true);
    });

    /* ================= Submit ================= */
    var overlayRing = document.getElementById('uploadRing');
    var overlaySpin = document.getElementById('overlaySpinner');
    function setUpload(pct) {
        overlayRing.classList.remove('hidden');
        overlaySpin.classList.add('hidden');
        document.getElementById('uploadPct').textContent = Math.round(pct) + '%';
        document.getElementById('uploadBar').style.strokeDashoffset = 213.6 * (1 - pct / 100);
    }
    function resetOverlay() {
        overlayRing.classList.add('hidden');
        overlaySpin.classList.remove('hidden');
        SB.overlay.hide();
    }

    function stepOf(field) {
        if (field === 'account_type') return 1;
        if (['id_document', 'face_photo', 'income_document'].indexOf(field) > -1) return 4;
        if (field === 'signature' || field === 'consent') return 5;
        var el = form.elements[field];
        var node = el && (el.length && !el.tagName ? el[0] : el);
        var s = node && node.closest('.w-step');
        return s ? +s.getAttribute('data-step') : 2;
    }

    function submit() {
        // Re-check every step before sending
        for (var i = 1; i < STEPS; i++) {
            if (!validateStep(i)) { show(i, true); return; }
        }
        if (!navigator.onLine) {
            SB.toast('error', 'No internet connection', 'Your KYC is saved on this phone. Submit again when you have network.');
            return;
        }
        if (!submissionKey) { submissionKey = uuid(); saveDraft(); }

        var fd = new FormData();
        var data = collect();
        Object.keys(data).forEach(function (k) {
            if (k === 'consent') fd.append('consent', data[k] ? '1' : '');
            else fd.append(k, data[k]);
        });
        Object.keys(files).forEach(function (k) { fd.append(k, files[k].blob, files[k].name); });
        fd.append('csrf', SB.csrf);
        fd.append('submission_key', submissionKey);
        if (RECAPTURE) fd.append('recapture_of', RECAPTURE.id);

        SB.btnLoading(nextBtn, true);
        prevBtn.disabled = true;
        SB.overlay.show('Uploading KYC…', 'Sending documents securely — keep this screen open');
        setUpload(0);
        SB.progress.start();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/submit.php');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 180000;
        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable) return;
            var pct = e.loaded / e.total * 100;
            setUpload(Math.min(99, pct));
            if (pct >= 100) {
                document.getElementById('overlayTitle').textContent = 'Saving record…';
                document.getElementById('overlayText').textContent = 'Almost done';
            }
        };
        xhr.onload = function () {
            var res;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = { success: false, message: 'Unexpected server response (' + xhr.status + ')' }; }
            if (xhr.status === 401) {
                fail('Session expired', 'Sign in again — this KYC is saved on the phone.');
                setTimeout(function () { location.href = '../login.php'; }, 1600);
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300 && res.success) {
                setUpload(100);
                done(res);
                return;
            }
            if (res.errors) {
                var fields = Object.keys(res.errors);
                fields.forEach(function (f) {
                    var el = form.elements[f];
                    if (el && !(el instanceof RadioNodeList) && el.type !== 'checkbox') setError(el, res.errors[f]);
                });
                var target = Math.min.apply(null, fields.map(stepOf));
                fail('Please fix and resubmit', res.message);
                show(target, true);
                return;
            }
            fail('KYC not submitted', res.message || 'Server error. Try again.');
        };
        xhr.onerror = function () { fail('Upload failed', 'Network problem. Your KYC is saved — tap Submit to try again.'); };
        xhr.ontimeout = function () { fail('Upload timed out', 'Network is slow. Your KYC is saved — try again.'); };
        xhr.send(fd);
    }
    function fail(title, msg) {
        SB.progress.done();
        resetOverlay();
        SB.btnLoading(nextBtn, false);
        prevBtn.disabled = false;
        SB.toast('error', title, msg, 6000);
    }
    function done(res) {
        submitted = true;
        SB.progress.done();
        clearDraft();
        setTimeout(function () {
            resetOverlay();
            form.classList.add('hidden');
            document.querySelector('.w-progress').classList.add('hidden');
            document.getElementById('doneView').classList.remove('hidden');
            document.getElementById('doneRef').textContent = res.reference_no;
            document.getElementById('doneView2').href = 'record.php?id=' + res.id;
            var bar = document.querySelector('.f-bar-title h1');
            if (bar) bar.textContent = 'Submitted';
            window.scrollTo(0, 0);
            if (navigator.vibrate) navigator.vibrate(60);
        }, 450);
    }
    document.getElementById('copyRef').addEventListener('click', function () {
        var t = document.getElementById('doneRef').textContent;
        var ok = function () { SB.toast('success', 'Reference copied'); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(ok); return; }
        // Plain-HTTP fallback (office LAN)
        var ta = document.createElement('textarea');
        ta.value = t; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); ok(); } catch (e) {}
        ta.remove();
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty && !submitted) { saveDraft(); e.preventDefault(); e.returnValue = ''; }
    });

    /* ================= Start over ================= */
    var leave = document.getElementById('leaveModal');
    leave.addEventListener('click', function (e) { if (e.target === leave || e.target.closest('[data-close]')) leave.classList.remove('show'); });
    document.getElementById('discardDraft').addEventListener('click', function () { leave.classList.add('show'); });
    document.getElementById('confirmDiscard').addEventListener('click', function () {
        clearDraft();
        dirty = false;
        location.replace(location.pathname + location.search);
    });

    /* ================= Boot ================= */
    var draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
    if (draft && draft.data) {
        apply(draft.data);
        submissionKey = draft.key || null;
        dirty = true;
        var mins = Math.round((Date.now() - draft.at) / 60000);
        document.getElementById('draftWhen').textContent = mins < 1 ? 'just now' : (mins < 60 ? mins + ' min ago' : 'from ' + new Date(draft.at).toLocaleString());
        document.getElementById('draftBanner').classList.remove('hidden');
    } else if (PREFILL) {
        apply(PREFILL);
    }
    applyType();

    // Restore photos and signature kept on the device
    Promise.all(['id_document', 'face_photo', 'income_document', 'signature'].map(function (k) {
        return idb.get(k).then(function (blob) {
            if (!blob) return;
            if (k === 'signature') sig.restore(blob);
            else setCapture(k, blob, false);
        });
    })).then(function () {
        history.replaceState({ kycStep: 1 }, '', location.href);
        var start = draft && draft.step ? draft.step : 1;
        // Don't jump past a step that is now incomplete
        for (var i = 1; i < start; i++) { if (!quietValid(i)) { start = i; break; } }
        if (start > 1) {
            for (var j = 2; j <= start; j++) history.pushState({ kycStep: j }, '', location.href);
        }
        show(start, false, true);
    });
    function quietValid(n) {
        if (n === 1) return !!val('account_type');
        if (n === 4) return !!(files.id_document && files.face_photo);
        if (n === 5) return sig.isSigned() && val('consent') === '1';
        var ok = true;
        steps[n - 1].querySelectorAll('[data-req], [data-req-biz]').forEach(function (el) {
            if (el.closest('.hidden')) return;
            if (el.hasAttribute('data-req-biz') && !isBiz()) return;
            if (!val(el.name)) ok = false;
        });
        return ok;
    }
})();
