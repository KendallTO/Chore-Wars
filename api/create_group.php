<?php
// api/create_group.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // must provide $pdo (PDO connection)

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

// Ensure user is logged in
if (!isset($_SESSION['user']['id'])) {
    send_json(['error' => 'Not authenticated'], 401);
}

$userId = (int)$_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(['error' => 'Invalid JSON body'], 400);
}

$name        = trim((string)($input['name'] ?? ''));
$description = trim((string)($input['description'] ?? ''));

if ($name === '') {
    send_json(['error' => 'Group name cannot be empty'], 422);
}

try {
    $pdo->beginTransaction();

    // IMPORTANT: backticks around `groups`
    $stmt = $pdo->prepare(
        'INSERT INTO `groups` (name, description, invite_code, created_at)
         VALUES (?, ?, NULL, NOW())'
    );
    $stmt->execute([
        $name,
        $description !== '' ? $description : null,
    ]);

    $groupId = (int)$pdo->lastInsertId();

    // Add creator as owner
    $stmt = $pdo->prepare(
        'INSERT INTO group_members (group_id, user_id, role, joined_at)
         VALUES (?, ?, "owner", NOW())'
    );
    $stmt->execute([$groupId, $userId]);

    $pdo->commit();

    send_json([
        'id'          => $groupId,
        'name'        => $name,
        'description' => $description !== '' ? $description : null,
        'created_at'  => date('Y-m-d H:i:s'),
        'role'        => 'owner',
        'inviteCode'  => null
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // For debugging you *can* temporarily expose the error message:
    // send_json(['error' => 'Failed to create group: ' . $e->getMessage()], 500);
    // But in production, keep it generic:
    send_json(['error' => 'Failed to create group'], 500);
}
