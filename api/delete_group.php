<?php
// api/delete_group.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // PDO connection ($pdo)

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

// Must be logged in
if (empty($_SESSION['user']['id'])) {
    send_json(['error' => 'Not authenticated'], 401);
}

$userId = (int) $_SESSION['user']['id'];

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(['error' => 'Invalid JSON body'], 400);
}

$groupId = isset($input['groupId']) ? (int)$input['groupId'] : 0;
if ($groupId <= 0) {
    send_json(['error' => 'Missing or invalid groupId'], 400);
}

try {
    $pdo->beginTransaction();

    // 1) Make sure user is the OWNER of this group
    $stmt = $pdo->prepare(
        'SELECT role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        send_json(['error' => 'You are not a member of this group'], 403);
    }

    if ($row['role'] !== 'owner') {
        $pdo->rollBack();
        send_json(['error' => 'Only the group owner can delete this group'], 403);
    }

    // 2) Delete game + shop state for this group
    //    (tables already used by get_game_state.php and get_shop_state.php) 
    $stmt = $pdo->prepare('DELETE FROM game_state WHERE group_id = ?');
    $stmt->execute([$groupId]);

    $stmt = $pdo->prepare('DELETE FROM shop_state WHERE group_id = ?');
    $stmt->execute([$groupId]);

    // 3) Delete the group itself
    //    group_members rows are removed via ON DELETE CASCADE in your schema
    $stmt = $pdo->prepare('DELETE FROM `groups` WHERE id = ?');
    $stmt->execute([$groupId]);

    $pdo->commit();

    send_json(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete group error: ' . $e->getMessage());
    send_json(['error' => 'Failed to delete group'], 500);
}
