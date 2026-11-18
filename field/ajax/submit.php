<?php
/** Receives a new KYC capture from the field web app. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
requireFieldLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Upload bigger than PHP allows → $_POST and $_FILES arrive empty
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    jsonResponse(['success' => false, 'message' => 'The photos are too large for the server (limit ' . ini_get('post_max_size') . '). Retake them and try again.'], 413);
}

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    jsonResponse(['success' => false, 'message' => 'Your session timed out. Refresh the page — your typed details are saved.'], 419);
}

$me = currentUser();
$officerId = (int)$me['id'];

// Same capture sent twice (e.g. retry on a bad network) → return the first result
$key = preg_replace('/[^a-zA-Z0-9-]/', '', (string)($_POST['submission_key'] ?? ''));
if ($key !== '' && isset($_SESSION['kyc_submitted'][$key])) {
    jsonResponse(['success' => true, 'duplicate' => true] + $_SESSION['kyc_submitted'][$key]);
}

$in = fn(string $k, int $max = 190) => trim(mb_substr((string)($_POST[$k] ?? ''), 0, $max));
$type = $in('account_type') === 'business' ? 'business' : ($in('account_type') === 'personal' ? 'personal' : '');

$data = [
    'account_type'             => $type,
    'customer_first_name'      => $in('customer_first_name', 80),
    'customer_last_name'       => $in('customer_last_name', 80),
    'date_of_birth'            => $in('date_of_birth', 10),
    'gender'                   => $in('gender', 10),
    'nationality'              => $in('nationality', 40),
    'id_type'                  => $in('id_type', 40),
    'id_number'                => strtoupper($in('id_number', 60)),
    'phone_number'             => preg_replace('/[\s-]/', '', $in('phone_number', 30)),
    'email'                    => strtolower($in('email', 120)),
    'address_line'             => $in('address_line', 255),
    'district'                 => $in('district', 60),
    'country'                  => $in('country', 60),
    'home_village'             => $in('home_village', 80),
    'home_ta'                  => $in('home_ta', 80),
    'home_district'            => $in('home_district', 80),
    'employer_name'            => $in('employer_name', 120),
    'monthly_income'           => preg_replace('/[^\d.]/', '', $in('monthly_income', 20)),
    'business_name'            => $in('business_name', 120),
    'trading_name'             => $in('trading_name', 120),
    'business_registration_no' => strtoupper($in('business_registration_no', 60)),
    'tax_pin'                  => $in('tax_pin', 40),
    'contact_person_name'      => $in('contact_person_name', 120),
    'contact_person_phone'     => preg_replace('/[\s-]/', '', $in('contact_person_phone', 30)),
];

/* ---------------- Validation ---------------- */
$errors = [];
$need = function (string $field, string $label) use (&$errors, $data) {
    if ($data[$field] === '') $errors[$field] = "$label is required";
};
$phoneOk = fn($p) => (bool)preg_match('/^(\+?265|0)[1-9]\d{8}$/', $p);

if ($type === '') {
    $errors['account_type'] = 'Choose personal or business';
}
$need('customer_first_name', 'First name');
$need('customer_last_name', 'Last name');
$need('id_type', 'ID type');
$need('id_number', 'ID number');
$need('phone_number', 'Phone number');
$need('address_line', 'Address');
$need('district', 'District');
$need('date_of_birth', 'Date of birth');
$need('gender', 'Gender');
if ($data['gender'] !== '' && !in_array($data['gender'], ['Male', 'Female'], true)) {
    $errors['gender'] = 'Choose a gender';
}

if ($type === 'business') {
    $need('business_name', 'Business name');
    $need('business_registration_no', 'Registration number');
    $need('contact_person_name', 'Contact person');
    $need('contact_person_phone', 'Contact phone');
    if ($data['contact_person_phone'] !== '' && !$phoneOk($data['contact_person_phone'])) {
        $errors['contact_person_phone'] = 'Enter a valid Malawi phone number';
    }
} else {
    // Personal accounts don't use the business fields
    foreach (['business_name', 'trading_name', 'business_registration_no', 'tax_pin', 'contact_person_name', 'contact_person_phone'] as $f) {
        $data[$f] = '';
    }
}
if ($data['phone_number'] !== '' && !$phoneOk($data['phone_number'])) {
    $errors['phone_number'] = 'Enter a valid Malawi phone number';
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address';
}
if ($data['date_of_birth'] !== '') {
    $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
    if (!$dob || $dob > new DateTime('today') || $dob < new DateTime('-120 years')) {
        $errors['date_of_birth'] = 'Enter a valid date of birth';
    }
}
if ($data['monthly_income'] !== '' && !is_numeric($data['monthly_income'])) {
    $errors['monthly_income'] = 'Enter an amount in numbers';
}
if (($_POST['consent'] ?? '') !== '1') {
    $errors['consent'] = 'Customer consent is required';
}

/* ---------------- Files ---------------- */
$fileRules = [
    'id_document'     => ['label' => 'ID document',     'required' => true,  'pdf' => true],
    'face_photo'      => ['label' => 'Face photo',      'required' => true,  'pdf' => false],
    'income_document' => ['label' => 'Income document', 'required' => false, 'pdf' => true],
    'signature'       => ['label' => 'Signature',       'required' => true,  'pdf' => false],
];
$maxBytes = 10 * 1024 * 1024;
$finfo = new finfo(FILEINFO_MIME_TYPE);
$extFor = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
$accepted = [];

foreach ($fileRules as $field => $rule) {
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($rule['required']) $errors[$field] = $rule['label'] . ' is required';
        continue;
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $errors[$field] = $rule['label'] . ' is larger than the server allows (' . ini_get('upload_max_filesize') . ')';
        continue;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[$field] = $rule['label'] . ' did not upload completely — retake it';
        continue;
    }
    if ($f['size'] > $maxBytes) {
        $errors[$field] = $rule['label'] . ' is larger than 10 MB';
        continue;
    }
    $mime = $finfo->file($f['tmp_name']) ?: '';
    $ok = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ($rule['pdf'] && $mime === 'application/pdf');
    if (!$ok) {
        $errors[$field] = $rule['label'] . ' must be a photo' . ($rule['pdf'] ? ' or PDF' : '');
        continue;
    }
    $accepted[$field] = ['tmp' => $f['tmp_name'], 'ext' => $extFor[$mime]];
}

if ($errors) {
    jsonResponse(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
}

/* ---------------- Recapture of a rejected record (optional) ---------------- */
$recaptureOf = null;
if (!empty($_POST['recapture_of'])) {
    $rq = $pdo->prepare("SELECT id, reference_no, rejection_reason FROM kyc_submissions WHERE id = ? AND officer_id = ? AND LOWER(status) = 'rejected'");
    $rq->execute([(int)$_POST['recapture_of'], $officerId]);
    $recaptureOf = $rq->fetch() ?: null;
    if ($recaptureOf && isRecaptured($pdo, (int)$recaptureOf['id'])) {
        jsonResponse(['success' => false, 'message' => 'This returned KYC was already recaptured.'], 409);
    }
}

/* ---------------- Save ---------------- */
$dir = date('Ymd');
$baseDir = __DIR__ . '/../../storage/kyc_uploads/' . $dir;
if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
    jsonResponse(['success' => false, 'message' => 'Server storage is not writable. Contact IT.'], 500);
}

$saved = [];
try {
    foreach ($accepted as $field => $f) {
        $name = $field . '_' . bin2hex(random_bytes(8)) . '.' . $f['ext'];
        if (!move_uploaded_file($f['tmp'], $baseDir . '/' . $name)) {
            throw new RuntimeException("Could not store $field");
        }
        $saved[$field] = 'storage/kyc_uploads/' . $dir . '/' . $name;
    }

    $reference = generateReference();
    $row = $data + [
        'officer_id'           => $officerId,
        'id_document_path'     => $saved['id_document'] ?? null,
        'face_photo_path'      => $saved['face_photo'] ?? null,
        'income_document_path' => $saved['income_document'] ?? null,
        'signature_path'       => $saved['signature'] ?? null,
        'reference_no'         => $reference,
        'status'               => 'submitted',
        'review_stage'         => 'submitted',
    ];
    if ($row['date_of_birth'] === '') $row['date_of_birth'] = null;

    // Only write columns that exist in this database
    $cols = kycColumns($pdo);
    $row = array_filter($row, fn($k) => isset($cols[$k]), ARRAY_FILTER_USE_KEY);

    $pdo->beginTransaction();
    $names = array_keys($row);
    $sql = 'INSERT INTO kyc_submissions (' . implode(', ', $names) . ', submitted_at) VALUES ('
         . implode(', ', array_fill(0, count($names), '?')) . ', NOW())';
    $pdo->prepare($sql)->execute(array_values($row));
    $newId = (int)$pdo->lastInsertId();

    if ($recaptureOf) {
        try {
            $note = 'Recapture of ' . $recaptureOf['reference_no']
                  . ($recaptureOf['rejection_reason'] ? ' (rejected: ' . $recaptureOf['rejection_reason'] . ')' : '');
            $log = $pdo->prepare('INSERT INTO kyc_audit_log (kyc_id, action, performed_by, role, notes) VALUES (?, ?, ?, ?, ?)');
            $log->execute([$newId, 'resubmit', $officerId, 'officer', $note]);
            $log->execute([(int)$recaptureOf['id'], 'recaptured', $officerId, 'officer', 'Recaptured as ' . $reference]);
        } catch (Throwable $e) {
            // audit table missing — the record itself is still fine
        }
    }
    $pdo->commit();

    $result = ['id' => $newId, 'reference_no' => $reference, 'message' => 'KYC submitted for review'];
    if ($key !== '') {
        $_SESSION['kyc_submitted'][$key] = $result;
        $_SESSION['kyc_submitted'] = array_slice($_SESSION['kyc_submitted'], -20, null, true);
    }
    jsonResponse(['success' => true] + $result);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($saved as $path) {
        @unlink(__DIR__ . '/../../' . $path);
    }
    error_log('Field KYC submit failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Could not save the KYC. Please try again — nothing was lost.'], 500);
}
