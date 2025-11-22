<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['username']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username or password']);
    exit;
}

$username = $input['username'];
$email = isset($input['email']) ? $input['email'] : null;
$passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

// ==== YOUR REAL DATABASE CREDENTIALS ====
$dbHost = 'db5019042997.hosting-data.io';
$dbName = 'dbs14985870';
$dbUser = 'dbu5466581';
$dbPass = 'passwordfordatabase1';  // replace this!
// ========================================

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :p)'
    );
    $stmt->execute([
        ':u' => $username,
        ':e' => $email,
        ':p' => $passwordHash
    ]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
}
