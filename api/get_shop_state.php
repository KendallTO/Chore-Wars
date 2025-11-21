<?php
// api/get_shop_state.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$groupId = isset($_GET['groupId']) ? (int)$_GET['groupId'] : 0;
if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid groupId']);
    exit;
}

$stmt = $pdo->prepare('SELECT items_json, inventories_json FROM shop_state WHERE group_id = ?');
$stmt->execute([$groupId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'items'       => json_decode($row['items_json'], true),
        'inventories' => json_decode($row['inventories_json'], true),
    ]);
} else {
    // When nothing yet saved for this group
    echo json_encode([
        'items'       => [],
        'inventories' => [],
    ]);
}
