<?php
require __DIR__ . '/includes/bootstrap.php';

// Recapture a rejected record: prefill the typed details (photos must be retaken)
$prefill = null;
$recapture = null;
if (!empty($_GET['from'])) {
    $s = $pdo->prepare("SELECT * FROM kyc_submissions WHERE id = ? AND officer_id = ? AND LOWER(status) = 'rejected' LIMIT 1");
    $s->execute([(int)$_GET['from'], (int)$me['id']]);
    if (($r = $s->fetch()) && !isRecaptured($pdo, (int)$r['id'])) {
        $recapture = ['id' => (int)$r['id'], 'reference_no' => $r['reference_no'], 'reason' => $r['rejection_reason'] ?? ''];
        $prefill = [];
        foreach (['account_type', 'customer_first_name', 'customer_last_name', 'date_of_birth', 'gender', 'nationality', 'id_type',
                  'id_number', 'phone_number', 'email', 'address_line', 'district', 'country', 'home_village', 'home_ta',
                  'home_district', 'employer_name', 'monthly_income', 'business_name', 'trading_name',
                  'business_registration_no', 'tax_pin', 'contact_person_name', 'contact_person_phone'] as $f) {
            if (isset($r[$f]) && $r[$f] !== null) $prefill[$f] = (string)$r[$f];
        }
    }
}

$fTitle = $recapture ? 'Recapture KYC' : 'New KYC';
$fSubtitle = $recapture ? 'Replacing ' . $recapture['reference_no'] : 'Customer onboarding';
$fBack = 'index.php';
$fHideNav = true;
$fJs = ['capture.js'];
require __DIR__ . '/includes/top.php';

$districts = MALAWI_DISTRICTS;
function opts(array $list, string $placeholder): string {
    $o = '<option value="">' . e($placeholder) . '</option>';
    foreach ($list as $v) $o .= '<option>' . e($v) . '</option>';
    return $o;
}
?>

<div class="w-progress" style="margin:-16px -16px 0">
    <div class="w-meta"><span id="stepName">Account type</span><span>Step <b id="stepNo">1</b> of 6</span></div>
    <div class="w-dots" id="dots"><i></i><i></i><i></i><i></i><i></i><i></i></div>
</div>

<form id="kycForm" novalidate autocomplete="off"
      data-officer="<?= (int)$me['id'] ?>"
      data-prefill='<?= e(json_encode($prefill)) ?>'
      data-recapture='<?= e(json_encode($recapture)) ?>'>

    <div id="draftBanner" class="notice hidden" style="margin-bottom:14px">
        <?= icon('clock', 18) ?>
        <span style="flex:1">Draft restored <span id="draftWhen"></span>.</span>
        <button type="button" class="btn btn-light btn-sm" id="discardDraft"><span>Start over</span></button>
    </div>

    <?php if ($recapture): ?>
    <div class="notice bad" style="margin-bottom:14px"><?= icon('alert', 18) ?>
        <span><strong>Returned by the office.</strong> <?= $recapture['reason'] ? 'Reason: ' . e($recapture['reason']) : 'Fix the issue and recapture.' ?><br>
        <small>Details are pre-filled. Retake all photos and the signature.</small></span>
    </div>
    <?php endif; ?>

    <!-- STEP 1: account type -->
    <section class="w-step" data-step="1" data-name="Account type">
        <div class="w-title"><h2>What are we opening?</h2><p>Choose the type of account this customer needs.</p></div>
        <div class="choice-grid">
            <label class="choice">
                <input type="radio" name="account_type" value="personal" data-req>
                <span class="c-ic"><?= icon('user', 26) ?></span>
                <span><strong>Personal account</strong><small>Individual customer — savings, current or salary account</small></span>
                <span class="tick"><?= icon('check', 14) ?></span>
            </label>
            <label class="choice business">
                <input type="radio" name="account_type" value="business">
                <span class="c-ic"><?= icon('briefcase', 26) ?></span>
                <span><strong>Business account</strong><small>Registered company, partnership or sole trader</small></span>
                <span class="tick"><?= icon('check', 14) ?></span>
            </label>
        </div>
        <div class="f-error" id="typeError" style="margin-top:10px">Choose personal or business to continue.</div>
    </section>

    <!-- STEP 2: details -->
    <section class="w-step" data-step="2" data-name="Customer details">
        <div class="w-title"><h2 id="detailsTitle">Customer details</h2><p>Type the details exactly as they appear on the ID.</p></div>

        <div class="w-card biz-only">
            <h4><?= icon('briefcase', 15) ?>Business</h4>
            <div class="f-field"><label>Registered business name <span class="req">*</span></label>
                <input class="f-input" name="business_name" data-req-biz autocapitalize="words"><div class="f-error"></div></div>
            <div class="f-field"><label>Trading name <span class="opt">(if different)</span></label>
                <input class="f-input" name="trading_name" autocapitalize="words"><div class="f-error"></div></div>
            <div class="f-row">
                <div class="f-field"><label>Registration no. <span class="req">*</span></label>
                    <input class="f-input" name="business_registration_no" data-req-biz autocapitalize="characters"><div class="f-error"></div></div>
                <div class="f-field"><label>TPIN <span class="opt">(optional)</span></label>
                    <input class="f-input" name="tax_pin" inputmode="numeric"><div class="f-error"></div></div>
            </div>
            <div class="f-field"><label>Contact person <span class="req">*</span></label>
                <input class="f-input" name="contact_person_name" data-req-biz autocapitalize="words"><div class="f-error"></div></div>
            <div class="f-field" style="margin-bottom:0"><label>Contact person phone <span class="req">*</span></label>
                <input class="f-input" name="contact_person_phone" type="tel" inputmode="tel" data-req-biz data-type="phone" placeholder="0999 123 456"><div class="f-error"></div></div>
        </div>

        <div class="w-card">
            <h4><?= icon('id', 15) ?><span id="personTitle">Personal & identity</span></h4>
            <div class="f-row">
                <div class="f-field"><label>First name <span class="req">*</span></label>
                    <input class="f-input" name="customer_first_name" data-req autocapitalize="words" autocomplete="off"><div class="f-error"></div></div>
                <div class="f-field"><label>Last name <span class="req">*</span></label>
                    <input class="f-input" name="customer_last_name" data-req autocapitalize="words"><div class="f-error"></div></div>
            </div>
            <div class="f-row">
                <div class="f-field"><label>Date of birth <span class="req">*</span></label>
                    <input class="f-input" name="date_of_birth" type="date" data-req data-type="dob" max="<?= date('Y-m-d') ?>"><div class="f-error"></div></div>
                <div class="f-field"><label>Nationality</label>
                    <input class="f-input" name="nationality" value="Malawian" autocapitalize="words"><div class="f-error"></div></div>
            </div>
            <div class="f-field"><span class="f-label">Gender <span class="req">*</span></span>
                <div class="seg" data-group="gender">
                    <label><input type="radio" name="gender" value="Male" data-req><span>Male</span></label>
                    <label><input type="radio" name="gender" value="Female"><span>Female</span></label>
                </div><div class="f-error"></div></div>
            <div class="f-row">
                <div class="f-field"><label>ID type <span class="req">*</span></label>
                    <select class="f-select" name="id_type" data-req>
                        <option value="National ID" selected>National ID</option>
                        <option>Passport</option>
                        <option>Driver's licence</option>
                        <option>Voter ID</option>
                    </select><div class="f-error"></div></div>
                <div class="f-field"><label>ID number <span class="req">*</span></label>
                    <input class="f-input" name="id_number" data-req data-type="idno" autocapitalize="characters" placeholder="e.g. AB12CD34"><div class="f-error"></div></div>
            </div>
            <div class="f-field"><label>Phone number <span class="req">*</span></label>
                <input class="f-input" name="phone_number" type="tel" inputmode="tel" data-req data-type="phone" placeholder="0999 123 456"><div class="f-error"></div></div>
            <div class="f-field" style="margin-bottom:0"><label>Email <span class="opt">(optional)</span></label>
                <input class="f-input" name="email" type="email" inputmode="email" data-type="email" autocapitalize="off" placeholder="name@example.com"><div class="f-error"></div></div>
        </div>
    </section>

    <!-- STEP 3: address & income -->
    <section class="w-step" data-step="3" data-name="Address & income">
        <div class="w-title"><h2>Where do they live?</h2><p>Current residence, home origin and source of income.</p></div>
        <div class="w-card">
            <h4><?= icon('home', 15) ?>Current residence</h4>
            <div class="f-field"><label>Physical address <span class="req">*</span></label>
                <input class="f-input" name="address_line" data-req placeholder="e.g. Area 25, Plot 112, Lilongwe"><div class="f-error"></div></div>
            <div class="f-row">
                <div class="f-field"><label>District <span class="req">*</span></label>
                    <select class="f-select" name="district" data-req><?= opts($districts, 'Select…') ?></select><div class="f-error"></div></div>
                <div class="f-field"><label>Country</label>
                    <input class="f-input" name="country" value="Malawi"><div class="f-error"></div></div>
            </div>
        </div>
        <div class="w-card">
            <h4><?= icon('users', 15) ?>Home of origin</h4>
            <div class="f-field"><label>Home village</label>
                <input class="f-input" name="home_village" autocapitalize="words"><div class="f-error"></div></div>
            <div class="f-row">
                <div class="f-field" style="margin-bottom:0"><label>Traditional authority</label>
                    <input class="f-input" name="home_ta" autocapitalize="words"><div class="f-error"></div></div>
                <div class="f-field" style="margin-bottom:0"><label>Home district</label>
                    <select class="f-select" name="home_district"><?= opts($districts, 'Select…') ?></select><div class="f-error"></div></div>
            </div>
        </div>
        <div class="w-card">
            <h4><?= icon('wallet', 15) ?>Income</h4>
            <div class="f-field"><label>Employer / source of income</label>
                <input class="f-input" name="employer_name" placeholder="e.g. Ministry of Health, Self-employed"><div class="f-error"></div></div>
            <div class="f-field" style="margin-bottom:0"><label>Monthly income</label>
                <div class="f-prefix"><span>MWK</span><input class="f-input" name="monthly_income" inputmode="decimal" data-type="money" placeholder="0"></div>
                <div class="f-error"></div></div>
        </div>
    </section>

    <!-- STEP 4: documents -->
    <section class="w-step" data-step="4" data-name="Documents">
        <div class="w-title"><h2>Capture documents</h2><p>Take clear photos — all corners visible, no glare.</p></div>
        <div class="cap-grid">
            <?php
            $caps = [
                ['id_document', 'ID document', 'Front of the ID', 'id', 'environment', true, true, false],
                ['face_photo', 'Face photo', 'Customer selfie', 'user', 'user', true, false, false],
                ['income_document', 'Income proof', 'Payslip or statement', 'file', 'environment', false, true, true],
            ];
            foreach ($caps as [$name, $title, $hint, $ic, $facing, $req, $pdf, $wide]): ?>
            <div class="cap <?= $wide ? 'wide' : '' ?>" data-cap="<?= $name ?>" data-required="<?= $req ? '1' : '0' ?>">
                <input type="file" accept="image/*" capture="<?= $facing ?>" data-input="camera">
                <input type="file" accept="image/*<?= $pdf ? ',application/pdf' : '' ?>" data-input="file">
                <div class="cap-empty" data-pick="camera">
                    <span class="c-ic"><?= icon($ic, 22) ?></span>
                    <strong><?= e($title) ?><?= $req ? ' <span style="color:#D92D20">*</span>' : ' <span class="muted" style="font-weight:400">(optional)</span>' ?></strong>
                    <small><?= e($hint) ?></small>
                    <span class="btn btn-primary btn-sm" style="margin-top:6px"><?= icon('eye', 14) ?><span>Take photo</span></span>
                    <button type="button" class="btn btn-light btn-sm" data-pick="file" style="margin-top:2px"><span>Choose file</span></button>
                </div>
                <div class="cap-preview" data-lightbox-cap>
                    <img alt="">
                    <div class="pdf hidden"><?= icon('file', 34) ?><span class="pdf-name">PDF</span></div>
                    <span class="ok"><?= icon('check', 12) ?>Captured</span>
                </div>
                <div class="cap-title"><span><?= e($title) ?></span><small class="cap-size"></small></div>
                <div class="cap-actions">
                    <button type="button" data-pick="camera"><?= icon('refresh', 14) ?>Retake</button>
                    <button type="button" data-remove><?= icon('x', 14) ?>Remove</button>
                </div>
                <div class="cap-busy"><span class="spinner"></span>Optimising photo…</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="f-error" id="docsError" style="margin-top:10px"></div>
        <div class="f-tip" style="margin-top:14px"><?= icon('shield', 18) ?>
            <span>Photos are compressed on this phone before upload to save data, and kept on the device until you submit.</span></div>
    </section>

    <!-- STEP 5: signature & consent -->
    <section class="w-step" data-step="5" data-name="Signature">
        <div class="w-title"><h2>Customer signature</h2><p>Hand the phone to the customer to sign inside the box.</p></div>
        <div class="sig-wrap" id="sigWrap">
            <canvas id="sigPad"></canvas>
            <span class="sig-line"></span><span class="sig-x">✕</span>
            <span class="sig-placeholder">Sign here with a finger</span>
        </div>
        <div class="sig-tools">
            <span class="muted" style="font-size:12px" id="sigState">Not signed yet</span>
            <button type="button" class="btn btn-light btn-sm" id="sigClear"><?= icon('refresh', 14) ?><span>Clear</span></button>
        </div>
        <div class="f-error" id="sigError">The customer must sign before you continue.</div>

        <label class="check-row" id="consentRow" style="margin-top:18px">
            <input type="checkbox" name="consent" value="1">
            <span>The customer confirms the information given is true and consents to Standard Bank verifying it and keeping it for KYC and anti-money-laundering purposes.</span>
        </label>
        <div class="f-error" id="consentError" style="display:none">Customer consent is required.</div>
    </section>

    <!-- STEP 6: review -->
    <section class="w-step" data-step="6" data-name="Review & submit">
        <div class="w-title"><h2>Review before submitting</h2><p>Check everything with the customer. Tap Edit to correct a section.</p></div>
        <div id="review"></div>
    </section>

    <div class="draft-note" id="draftNote" style="margin-top:auto;padding:14px 0 0;opacity:0;transition:opacity .3s"><i></i><span>Draft saved on this phone</span></div>
    <div class="w-foot" id="wFoot">
        <button type="button" class="btn btn-light" id="prevBtn"><span>Back</span></button>
        <button type="button" class="btn btn-primary" id="nextBtn"><span id="nextLabel">Continue</span></button>
    </div>
</form>

<!-- Success view (shown after submit) -->
<section class="hidden" id="doneView">
    <div class="done-screen">
        <div class="done-badge"><?= icon('check', 44) ?></div>
        <h2>KYC submitted</h2>
        <p>Sent to the supervisor for review. You'll see the status change in <strong>My KYC</strong>.</p>
        <div class="ref-box">
            <span class="kpi-icon total" style="width:42px;height:42px;border-radius:12px"><?= icon('file', 20) ?></span>
            <span class="grow"><small>Reference number</small><strong id="doneRef"></strong></span>
            <button type="button" class="icon-btn" id="copyRef" aria-label="Copy reference"><?= icon('file', 16) ?></button>
        </div>
        <div style="display:grid;gap:10px">
            <a class="btn btn-primary btn-lg" href="new.php"><?= icon('id', 18) ?><span>Capture another customer</span></a>
            <a class="btn btn-light btn-lg" id="doneView2" href="submissions.php"><span>View submission</span></a>
            <a class="btn btn-light btn-lg" href="index.php"><span>Back to home</span></a>
        </div>
    </div>
</section>

<div class="modal" id="leaveModal">
    <div class="modal-card">
        <div class="m-body">
            <div class="m-icon reject"><?= icon('alert', 22) ?></div>
            <h3>Start over?</h3>
            <p>This clears all typed details, photos and the signature for this customer.</p>
        </div>
        <div class="m-foot">
            <button class="btn btn-light" data-close><span>Keep editing</span></button>
            <button class="btn btn-danger-solid" id="confirmDiscard"><span>Clear everything</span></button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/bottom.php'; ?>
