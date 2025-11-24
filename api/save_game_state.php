<?php
// api/save_game_state.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require __DIR__ . '/db.php';

// Require login
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// groupId is just the numeric group ID (e.g. 1, 2, 3...)
$groupId = isset($input['groupId']) ? (int)$input['groupId'] : 0;
$data    = $input['data'] ?? null;   // { chores, points, headers, extraState }

if ($groupId <= 0 || $data === null || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid groupId/data']);
    exit;
}

$dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);

$stmt = $pdo->prepare('
    INSERT INTO game_state (group_id, data_json)
    VALUES (:group_id, :data_json)
    ON DUPLICATE KEY UPDATE
      data_json = VALUES(data_json),
      updated_at = CURRENT_TIMESTAMP
');

$stmt->execute([
    ':group_id'  => $groupId,
    ':data_json' => $dataJson,
]);

echo json_encode(['ok' => true]);
