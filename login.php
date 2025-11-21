<?php

header('Content-Type: application/json');

// Helper for JSON output
function send_json($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(405, ['error' => 'Method not allowed']);
}

// Read JSON from JS fetch()
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['username']) || empty($input['password'])) {
    send_json(400, ['error' => 'Missing username or password']);
}

$username = trim($input['username']);
$password = $input['password'];

// ==== YOUR IONOS DATABASE CREDENTIALS ====
$dbHost = 'db5019042997.hosting-data.io';
$dbName = 'dbs14985870';      // <-- IMPORTANT — this must match the DB name in phpMyAdmin
$dbUser = 'dbu5466581';       // database user
$dbPass = 'passwordfordatabase1'; // <-- replace with your real db password
// ==========================================

try {
    // Connect to MySQL using PDO
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Look up the user
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash 
        FROM users 
        WHERE username = :u 
        LIMIT 1
    ");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no user found
    if (!$user) {
        send_json(401, ['error' => 'Invalid username or password']);
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        send_json(401, ['error' => 'Invalid username or password']);
    }

    // Success!
    send_json(200, [
        'ok'       => true,
        'username' => $user['username'],
        'email'    => $user['email']
    ]);

} catch (PDOException $e) {
    // While you're testing, you can temporarily show the error:
    // send_json(500, ['error' => 'Server error: ' . $e->getMessage()]);
    send_json(500, ['error' => 'Server error']);
}
