<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Preflight OK']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload',
        'raw' => $raw
    ]);
    exit;
}

$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required'
    ]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, password, full_name, role FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit;
}

$stored = (string)$user['password'];
$info = password_get_info($stored);
$passwordOk = !empty($info['algo']) ? password_verify($password, $stored) : hash_equals($stored, $password);

if (!$passwordOk) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Password incorrect'
    ]);
    exit;
}

if ($user['role'] !== 'officer') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'User is not an officer'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ]
]);