<?php
// api/save_game_state.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // provides $pdo

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Must be logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    send_json(['error' => 'Not authenticated'], 401);
}

$userId = (int)$_SESSION['user']['id'];

// Read JSON body: { groupId, data: { chores, points, headers, extraState } }
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(['error' => 'Invalid JSON body'], 400);
}

$groupId = isset($input['groupId']) ? (int)$input['groupId'] : 0;
$data    = $input['data'] ?? null;

if ($groupId <= 0) {
    send_json(['error' => 'Missing or invalid groupId'], 400);
}
if (!is_array($data)) {
    send_json(['error' => 'Missing or invalid data payload'], 400);
}

// Encode the whole game state into one JSON column
$dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);

try {
    // Assumes table:
    // game_state(group_id INT, user_id INT, data_json LONGTEXT, PRIMARY KEY (group_id, user_id))
    $stmt = $pdo->prepare('
        INSERT INTO game_state (group_id, user_id, data_json)
        VALUES (:group_id, :user_id, :data_json)
        ON DUPLICATE KEY UPDATE
            data_json = VALUES(data_json)
    ');

    $stmt->execute([
        ':group_id'  => $groupId,
        ':user_id'   => $userId,
        ':data_json' => $dataJson,
    ]);

    send_json(['ok' => true]);
} catch (Throwable $e) {
    // For debugging you *can* log the error:
    // error_log("save_game_state error: " . $e->getMessage());
    send_json(['error' => 'Failed to save game state'], 500);
}
