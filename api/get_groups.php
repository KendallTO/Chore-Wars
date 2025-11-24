<?php
// api/get_groups.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['error' => 'Method not allowed'], 405);
}

if (!isset($_SESSION['user']['id'])) {
    send_json(['error' => 'Not authenticated'], 401);
}

$userId = (int)$_SESSION['user']['id'];

try {
    // IMPORTANT: backticks around `groups`
    $stmt = $pdo->prepare(
        'SELECT 
             g.id,
             g.name,
             g.description,
             g.created_at,
             gm.role,
             CASE WHEN gm.role = "owner" THEN g.invite_code ELSE NULL END AS inviteCode
         FROM `groups` g
         INNER JOIN group_members gm ON gm.group_id = g.id
         WHERE gm.user_id = ?
         ORDER BY g.created_at DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    send_json($rows ?: []);

} catch (Throwable $e) {
    error_log('Get groups error: ' . $e->getMessage());

    // Again, for debugging you *could* expose more detail:
    // send_json(['error' => 'Failed to fetch groups: ' . $e->getMessage()], 500);
    send_json(['error' => 'Failed to fetch groups'], 500);
}
