<?php
// api/ensure_user.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username required']);
    exit;
}

// adjust column names if your users table is different
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $pdo->prepare('INSERT INTO users (username) VALUES (?)');
    $stmt->execute([$username]);
    $id = (int)$pdo->lastInsertId();
    $user = ['id' => $id, 'username' => $username];
}

echo json_encode($user);
