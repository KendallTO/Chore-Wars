<?php
// api/get_shop_state.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require __DIR__ . '/db.php';

// Must be logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId  = (int)$_SESSION['user']['id'];
$groupId = isset($_GET['groupId']) ? (int)$_GET['groupId'] : 0;

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid groupId']);
    exit;
}

$stmt = $pdo->prepare('
    SELECT items_json, inventories_json
    FROM shop_state
    WHERE group_id = :group_id AND user_id = :user_id
    LIMIT 1
');
$stmt->execute([
    ':group_id' => $groupId,
    ':user_id'  => $userId,
]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'items'       => json_decode($row['items_json'], true),
        'inventories' => json_decode($row['inventories_json'], true),
    ]);
} else {
    // Fallback: try any existing record for this group (shared baseline)
    $stmt2 = $pdo->prepare('
        SELECT items_json, inventories_json
        FROM shop_state
        WHERE group_id = :group_id
        LIMIT 1
    ');
    $stmt2->execute([':group_id' => $groupId]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($row2) {
        echo json_encode([
            'items'       => json_decode($row2['items_json'], true),
            'inventories' => json_decode($row2['inventories_json'], true),
        ]);
    } else {
        // No saved state yet for this group at all
        echo json_encode([
            'items'       => [],
            'inventories' => [],
        ]);
    }
}
