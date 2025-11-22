<?php
// api/get_game_state.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$groupId = (int)($_GET['groupId'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing groupId']);
    exit;
}

$stmt = $pdo->prepare('SELECT data_json FROM game_state WHERE group_id = ?');
$stmt->execute([$groupId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(null);
    exit;
}

// data_json already contains JSON
echo $row['data_json'];
