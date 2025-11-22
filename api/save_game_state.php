<?php
// api/save_game_state.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$groupId = (int)($input['groupId'] ?? 0);
$data    = $input['data'] ?? null;

if ($groupId <= 0 || $data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing groupId or data']);
    exit;
}

$json = json_encode($data);

$stmt = $pdo->prepare('SELECT id FROM game_state WHERE group_id = ?');
$stmt->execute([$groupId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('UPDATE game_state SET data_json = ? WHERE group_id = ?');
    $stmt->execute([$json, $groupId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO game_state (group_id, data_json) VALUES (?, ?)');
    $stmt->execute([$groupId, $json]);
}

echo json_encode(['ok' => true]);
