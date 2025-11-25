<?php
/**
 * api/load_user_data.php
 * 
 * Retrieves per-user game data from the database
 * Called when a user logs in on a new device to restore their game state
 * 
 * Endpoint: GET /api/load_user_data.php
 * Requires: Authenticated session with user_id
 * 
 * Returns:
 * {
 *   "ok": true,
 *   "data": { // null if no previous data exists
 *     "chores": [...],
 *     "points": {...},
 *     "headers": {...},
 *     "extraState": {...}
 *   },
 *   "updatedAt": "2025-11-24 10:30:45"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
session_start();
require __DIR__ . '/db.php';

// Authentication check
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only GET allowed']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

try {
    $stmt = $pdo->prepare('
        SELECT data_json, updated_at
        FROM user_game_data
        WHERE user_id = :user_id
    ');

    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        // No previous data for this user
        echo json_encode([
            'ok' => true,
            'data' => null,
            'updatedAt' => null
        ]);
        exit;
    }

    // Decode and return the game data
    $gameData = json_decode($row['data_json'], true);

    echo json_encode([
        'ok' => true,
        'data' => $gameData,
        'updatedAt' => $row['updated_at']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
