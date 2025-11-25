<?php
// api/save_game_state.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require __DIR__ . '/db.php';

// Must be logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// We don't actually need userId in the table for now, but we can read it:
$userId = (int)$_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$groupId = (int)($input['groupId'] ?? 0);
$data    = $input['data'] ?? null;   // { chores, points, headers, extraState }

if ($groupId <= 0 || $data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing groupId or data']);
    exit;
}

$dataJson = json_encode($data);

$stmt = $pdo->prepare('
    INSERT INTO game_state (group_id, data_json, updated_at)
    VALUES (:group_id, :data_json, NOW())
    ON DUPLICATE KEY UPDATE
        data_json  = VALUES(data_json),
        updated_at = VALUES(updated_at)
');

try {
    $stmt->execute([
        ':group_id'  => $groupId,
        ':data_json' => $dataJson,
    ]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
