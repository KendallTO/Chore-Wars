<?php
// Unified authentication endpoint: signup, login, logout, session
// Uses PDO + password_hash/password_verify and PHP sessions.
// Actions:
//   POST { action: 'signup', username, password, email? }
//   POST { action: 'login', username, password }
//   POST { action: 'logout' }
//   GET  /api/auth.php?action=session  -> current session user

declare(strict_types=1);
session_start();

header('Content-Type: application/json');

// Load DB constants (mysqli file defines them); safe if included multiple times.
require_once __DIR__ . '/db_config.php';

function send_json($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Auth DB connect failed: ' . $e->getMessage());
        send_json(['error' => 'Database connection failed'], 500);
    }
}

function normalize_username(string $u): string {
    // Trim & collapse internal whitespace, lower for uniqueness guard
    $u = trim($u);
    // Optional: further restrictions could be applied here
    return $u;
}

// SESSION action (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'session') {
        if (!empty($_SESSION['user'])) {
            send_json(['ok' => true, 'user' => $_SESSION['user']]);
        } else {
            send_json(['ok' => false, 'user' => null]);
        }
    }
    send_json(['error' => 'Unknown action'], 400);
}

// Parse JSON body for POST
$raw = file_get_contents('php://input');
$input = json_decode($raw, true); // returns null on invalid JSON
if (!is_array($input)) {
    $input = [];
}
$action = $input['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

// SIGNUP
if ($action === 'signup') {
    $username = isset($input['username']) ? normalize_username((string)$input['username']) : '';
    $password = isset($input['password']) ? (string)$input['password'] : '';
    $email    = isset($input['email']) ? trim((string)$input['email']) : '';

    if ($username === '' || $password === '') {
        send_json(['error' => 'Username and password required'], 400);
    }
    if (strlen($username) < 3) {
        send_json(['error' => 'Username must be at least 3 characters'], 400);
    }
    if (strlen($password) < 4) {
        send_json(['error' => 'Password must be at least 4 characters'], 400);
    }

    $pdo = get_pdo();
    try {
        // Enforce case-insensitive uniqueness by querying before insert
        $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
        $check->execute([':u' => $username]);
        if ($check->fetch()) {
            send_json(['error' => 'Username already taken'], 409);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :p)');
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user'] = ['id' => $userId, 'username' => $username, 'email' => $email];
        send_json(['ok' => true, 'user' => $_SESSION['user']]);
    } catch (PDOException $e) {
        error_log('Signup error: ' . $e->getMessage());
        // Unique constraint fallback
        if ($e->getCode() === '23000') {
            send_json(['error' => 'Username already taken'], 409);
        }
        send_json(['error' => 'Server error'], 500);
    }
}

// LOGIN
if ($action === 'login') {
    $username = isset($input['username']) ? normalize_username((string)$input['username']) : '';
    $password = isset($input['password']) ? (string)$input['password'] : '';
    if ($username === '' || $password === '') {
        send_json(['error' => 'Username and password required'], 400);
    }
    $pdo = get_pdo();
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            send_json(['error' => 'Invalid credentials'], 401);
        }
        $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'email' => $user['email']];
        send_json(['ok' => true, 'user' => $_SESSION['user']]);
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        send_json(['error' => 'Server error'], 500);
    }
}

// LOGOUT
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    send_json(['ok' => true]);
}

send_json(['error' => 'Unknown action'], 400);
?>
