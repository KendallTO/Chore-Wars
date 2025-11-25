<?php
// api/save_shop_state.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require __DIR__ . '/db.php';

// Must be logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);

// groupId
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

// Upsert on (group_id, user_id)
$stmt = $pdo->prepare('
    INSERT INTO shop_state (group_id, user_id, items_json, inventories_json)
    VALUES (:group_id, :user_id, :items, :inventories)
    ON DUPLICATE KEY UPDATE
        items_json = VALUES(items_json),
        inventories_json = VALUES(inventories_json)
');

$stmt->execute([
    ':group_id'    => $groupId,
    ':user_id'     => $userId,
    ':items'       => $itemsJson,
    ':inventories' => $inventJson,
]);

echo json_encode(['ok' => true]);
