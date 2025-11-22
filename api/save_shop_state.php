<?php
// api/save_shop_state.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);

// Pull groupId normally
$groupId = (int)($input['groupId'] ?? 0);

// Support both formats:
// A) { groupId, items, inventories }
// B) { groupId, data: { items, inventories } }
$items  = $input['items'] 
          ?? ($input['data']['items'] ?? null);

$invent = $input['inventories'] 
          ?? ($input['data']['inventories'] ?? null);

if ($groupId <= 0 || $items === null || $invent === null) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing groupId, items, or inventories'
    ]);
    exit;
}

$itemsJson  = json_encode($items);
$inventJson = json_encode($invent);

// Check for existing record
$stmt = $pdo->prepare('SELECT id FROM shop_state WHERE group_id = ?');
$stmt->execute([$groupId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $pdo->prepare(
        'UPDATE shop_state
         SET items_json = ?, inventories_json = ?
         WHERE group_id = ?'
    );
    $stmt->execute([$itemsJson, $inventJson, $groupId]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO shop_state (group_id, items_json, inventories_json)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$groupId, $itemsJson, $inventJson]);
}

echo json_encode(['ok' => true]);
