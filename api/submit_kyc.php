<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

    $officerId = (int)($_SERVER['HTTP_X_USER_ID'] ?? 0);

    if ($officerId <= 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid officer ID'
        ]);
        exit;
    }

    $accountType = trim($_POST['account_type'] ?? 'personal');

    $firstName = trim($_POST['customer_first_name'] ?? '');
    $lastName = trim($_POST['customer_last_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $address = trim($_POST['address_line'] ?? '');
    $employer = trim($_POST['employer_name'] ?? '');
    $monthlyIncome = trim($_POST['monthly_income'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $businessRegistrationNo = trim($_POST['business_registration_no'] ?? '');
    $taxPin = trim($_POST['tax_pin'] ?? '');
    $contactPersonName = trim($_POST['contact_person_name'] ?? '');
    $contactPersonPhone = trim($_POST['contact_person_phone'] ?? '');

    if ($accountType === 'personal') {
        if ($firstName === '' || $lastName === '' || $phone === '' || $address === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required personal KYC fields'
            ]);
            exit;
        }
    } elseif ($accountType === 'business') {
        if ($businessName === '' || $contactPersonName === '' || $contactPersonPhone === '' || $phone === '' || $address === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required business KYC fields'
            ]);
            exit;
        }
    } else {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid account type'
        ]);
        exit;
    }

    if (!isset($_FILES['id_document']) || !isset($_FILES['face_photo'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'ID document and face photo are required'
        ]);
        exit;
    }

    $uploadBaseDir = dirname(__DIR__) . '/storage/kyc_uploads/' . date('Ymd');
    if (!is_dir($uploadBaseDir) && !mkdir($uploadBaseDir, 0777, true) && !is_dir($uploadBaseDir)) {
        throw new Exception('Failed to create upload directory');
    }

    function saveUpload(string $field, string $uploadBaseDir): ?string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = $_FILES[$field]['tmp_name'];
        $original = $_FILES[$field]['name'] ?? 'file';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $name = uniqid('', true) . ($ext ? '.' . $ext : '');

        $target = $uploadBaseDir . '/' . $name;

        if (!move_uploaded_file($tmp, $target)) {
            throw new Exception("Failed to save uploaded file: {$field}");
        }

        return 'storage/kyc_uploads/' . date('Ymd') . '/' . $name;
    }

    $idDocumentPath = saveUpload('id_document', $uploadBaseDir);
    $facePhotoPath = saveUpload('face_photo', $uploadBaseDir);
    $incomeDocumentPath = saveUpload('income_document', $uploadBaseDir);
    $signaturePath = saveUpload('signature', $uploadBaseDir);

    $referenceNo = 'KYC-' . date('Ymd-His') . '-' . random_int(1000, 9999);

$stmt = $pdo->prepare("
    INSERT INTO kyc_submissions (
        officer_id,
        account_type,
        customer_first_name,
        customer_last_name,
        phone_number,
        email,
        id_type,
        id_number,
        address_line,
        employer_name,
        monthly_income,
        business_name,
        trading_name,
        business_registration_no,
        tax_pin,
        contact_person_name,
        contact_person_phone,
        id_document_path,
        face_photo_path,
        income_document_path,
        signature_path,  -- ✅ ADD
        reference_no,
        status,
        review_stage,
        submitted_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )
");

$stmt->execute([
    $officerId,
    $accountType,
    $firstName,
    $lastName,
    $phone,
    $email,
    $idType,
    $idNumber,
    $address,
    $employer,
    $monthlyIncome,
    $businessName,
    $_POST['trading_name'] ?? '',
    $businessRegistrationNo,
    $taxPin,
    $contactPersonName,
    $contactPersonPhone,
    $idDocumentPath,
    $facePhotoPath,
    $incomeDocumentPath,
    $signaturePath, // ✅ ADD THIS
    $referenceNo,
    'submitted',
    'submitted'
]);
    echo json_encode([
        'success' => true,
        'reference_no' => $referenceNo
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}