<?php
// api/auth.php
// Unified authentication endpoint: signup, login, logout, session check.
// Uses PDO + password_hash/password_verify and PHP sessions.

declare(strict_types=1);
session_start();

// CORS headers - allow requests from same origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/db.php'; // provides $pdo (PDO connection)

// --------------------- helpers ---------------------
function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --------------------- GET: session check ---------------------
if ($method === 'GET') {
    // /api/auth.php?action=session
    $action = $_GET['action'] ?? '';
    if ($action === 'session') {
        if (isset($_SESSION['user'])) {
            send_json(['ok' => true, 'user' => $_SESSION['user']]);
        } else {
            send_json(['ok' => false, 'user' => null]);
        }
    }
    send_json(['error' => 'GET not supported here'], 405);
}

// --------------------- POST: signup / login / logout ---------------------
if ($method !== 'POST') {
    send_json(['error' => 'Only POST allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(['error' => 'Invalid JSON body'], 400);
}

$action   = $input['action']   ?? '';
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
$email    = trim((string)($input['email'] ?? ''));

// ---------- SIGNUP ----------
if ($action === 'signup') {
    // Basic validation – tweak as needed
    if ($username === '' || strlen($username) < 3) {
        send_json(['error' => 'Username must be at least 3 characters'], 422);
    }
    if ($password === '' || strlen($password) < 4) {
        send_json(['error' => 'Password must be at least 4 characters'], 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['error' => 'Invalid email address'], 422);
    }

    // Check if username already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        send_json(['error' => 'Username already taken'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$username, $email !== '' ? $email : null, $hash]);

    $userId = (int)$pdo->lastInsertId();
    $_SESSION['user'] = [
        'id'       => $userId,
        'username' => $username,
        'email'    => $email !== '' ? $email : null,
    ];

    send_json(['ok' => true, 'user' => $_SESSION['user']]);
}

// ---------- LOGIN ----------
if ($action === 'login') {
    if ($username === '' || $password === '') {
        send_json(['error' => 'Username and password are required'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, email, password_hash
         FROM users
         WHERE username = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Deliberately vague to not leak which field is wrong
        send_json(['error' => 'Invalid username or password'], 401);
    }

    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
    ];

    send_json(['ok' => true, 'user' => $_SESSION['user']]);
}

// ---------- LOGOUT ----------
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    send_json(['ok' => true]);
}

send_json(['error' => 'Unknown action'], 400);
