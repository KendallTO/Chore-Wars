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
$clientTs = isset($input['clientTs']) ? (int)$input['clientTs'] : 0; // milliseconds since epoch

if ($groupId <= 0 || $data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing groupId or data']);
    exit;
}

$dataJson = json_encode($data);

// Optional concurrency guard: if clientTs is provided, reject saves older than current row
if ($clientTs > 0) {
    $check = $pdo->prepare('SELECT updated_at FROM game_state WHERE group_id = ?');
    $check->execute([$groupId]);
    $row = $check->fetch();

    if ($row && isset($row['updated_at'])) {
        // Convert DB updated_at to milliseconds for comparison
        $serverUpdatedAtMs = strtotime($row['updated_at']) * 1000;
        if ($serverUpdatedAtMs > 0 && $clientTs < $serverUpdatedAtMs) {
            // Incoming save is stale; do not overwrite newer server state
            echo json_encode(['ok' => false, 'reason' => 'stale_update', 'serverUpdatedAt' => $serverUpdatedAtMs]);
            exit;
        }
    }
}

$stmt = $pdo->prepare('
    INSERT INTO game_state (group_id, data_json, updated_at)
    VALUES (:group_id, :data_json, NOW())
    ON DUPLICATE KEY UPDATE
        data_json  = VALUES(data_json),
        updated_at = VALUES(updated_at)
');

$stmt->execute([
    ':group_id'  => $groupId,
    ':data_json' => $dataJson,
]);

echo json_encode(['ok' => true]);
