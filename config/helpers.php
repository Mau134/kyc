<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Roles allowed to sign in to the office dashboard. */
const DASHBOARD_ROLES = ['admin', 'supervisor', 'compliance'];

/** Roles allowed to use the field (mobile web) app. */
const FIELD_ROLES = ['officer'];

const MALAWI_DISTRICTS = [
    'Balaka', 'Blantyre', 'Chikwawa', 'Chiradzulu', 'Chitipa', 'Dedza', 'Dowa', 'Karonga', 'Kasungu', 'Likoma',
    'Lilongwe', 'Machinga', 'Mangochi', 'Mchinji', 'Mulanje', 'Mwanza', 'Mzimba', 'Neno', 'Nkhata Bay', 'Nkhotakota',
    'Nsanje', 'Ntcheu', 'Ntchisi', 'Phalombe', 'Rumphi', 'Salima', 'Thyolo', 'Zomba',
];

/* ------------------------------------------------------------------
 * Generic helpers (also used by the mobile API — keep signatures)
 * ------------------------------------------------------------------ */

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

function sanitizeFileName(string $name): string {
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

function requireApiOfficer(): int {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $userId = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? null;

    if (!$userId || !ctype_digit((string)$userId)) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    return (int)$userId;
}

function generateReference(): string {
    return 'KYC-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function saveUploadedFile(array $file, string $subDir): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error');
    }

    $baseDir = __DIR__ . '/../storage/kyc_uploads/' . $subDir;
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }

    $original = sanitizeFileName($file['name'] ?? 'file.bin');
    $ext = pathinfo($original, PATHINFO_EXTENSION);
    $name = uniqid('', true) . ($ext ? '.' . $ext : '');
    $fullPath = $baseDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Failed to save uploaded file');
    }

    return 'storage/kyc_uploads/' . $subDir . '/' . $name;
}

/**
 * Accepts both hashed (password_hash) and legacy plain-text passwords,
 * so existing accounts keep working while you migrate to hashes.
 */
function verifyPassword(string $input, string $stored): bool {
    if ($stored === '') {
        return false;
    }
    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

/* ------------------------------------------------------------------
 * Dashboard helpers
 * ------------------------------------------------------------------ */

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isAjax(): bool {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function requireDashboardLogin(): void {
    $user = currentUser();
    if (empty($user) || !in_array($user['role'] ?? '', DASHBOARD_ROLES, true)) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Your session has expired. Please sign in again.'], 401);
        }
        header('Location: login.php');
        exit;
    }
}

function requireFieldLogin(): void {
    $user = currentUser();
    if (empty($user) || !in_array($user['role'] ?? '', FIELD_ROLES, true)) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Your session has expired. Please sign in again.'], 401);
        }
        header('Location: ../login.php');
        exit;
    }
}

/** Where a signed-in user belongs. */
function homeFor(string $role): string {
    return in_array($role, FIELD_ROLES, true) ? 'field/index.php' : 'index.php';
}

/** Column names of kyc_submissions (so inserts work on older and newer schemas). */
function kycColumns(PDO $pdo): array {
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM kyc_submissions') as $c) {
            $cols[$c['Field']] = true;
        }
    }
    return $cols;
}

/** SQL: rejected and not yet recaptured by the field officer. */
function notRecapturedSql(string $a = 'k'): string {
    return "NOT EXISTS (SELECT 1 FROM kyc_audit_log rl WHERE rl.kyc_id = $a.id AND rl.action = 'recaptured')";
}

function isRecaptured(PDO $pdo, int $kycId): ?string {
    try {
        $s = $pdo->prepare("SELECT notes FROM kyc_audit_log WHERE kyc_id = ? AND action = 'recaptured' ORDER BY id DESC LIMIT 1");
        $s->execute([$kycId]);
        $n = $s->fetchColumn();
        return $n === false ? null : (string)$n;
    } catch (Throwable $e) {
        return null;
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function roleLabel(string $role): string {
    return [
        'admin'      => 'Operations Admin',
        'supervisor' => 'Branch Supervisor',
        'compliance' => 'Compliance Officer',
        'officer'    => 'Field Officer',
    ][$role] ?? ucfirst($role);
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

/**
 * SQL expression giving one workflow status per record:
 * pending (awaiting supervisor) → compliance (awaiting final) → approved / rejected.
 */
function statusCaseSql(string $a = 'k'): string {
    return "CASE
        WHEN LOWER($a.status) = 'rejected' THEN 'rejected'
        WHEN LOWER($a.status) = 'approved' THEN 'approved'
        WHEN LOWER(COALESCE($a.review_stage, '')) = 'reviewed' THEN 'compliance'
        ELSE 'pending'
    END";
}

function statusMeta(string $key): array {
    return [
        'pending'    => ['label' => 'Pending Review',      'class' => 'pending'],
        'compliance' => ['label' => 'Awaiting Compliance', 'class' => 'compliance'],
        'approved'   => ['label' => 'Approved',            'class' => 'approved'],
        'rejected'   => ['label' => 'Rejected',            'class' => 'rejected'],
    ][$key] ?? ['label' => ucfirst($key), 'class' => 'neutral'];
}

function workflowStatus(array $row): string {
    $status = strtolower((string)($row['status'] ?? ''));
    $stage  = strtolower((string)($row['review_stage'] ?? ''));
    if ($status === 'rejected') return 'rejected';
    if ($status === 'approved') return 'approved';
    if ($stage === 'reviewed')  return 'compliance';
    return 'pending';
}

function customerName(array $row): string {
    if (($row['account_type'] ?? '') === 'business') {
        return $row['business_name'] ?: 'Business record';
    }
    $name = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
    return $name !== '' ? $name : 'Unnamed customer';
}

/** Which records the signed-in role can act on right now. */
function queueSql(string $role, string $a = 'k'): string {
    $status = statusCaseSql($a);
    if ($role === 'supervisor') return "($status) = 'pending'";
    if ($role === 'compliance') return "($status) = 'compliance'";
    return "($status) IN ('pending','compliance')";
}

function formatDateTime(?string $value, string $format = 'd M Y, H:i'): string {
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : $value;
}
