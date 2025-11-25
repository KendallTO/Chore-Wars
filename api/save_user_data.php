<?php
/**
 * api/save_user_data.php
 * 
 * Saves per-user game data to the database
 * This allows users to sync their data across multiple devices
 * 
 * Endpoint: POST /api/save_user_data.php
 * Requires: Authenticated session with user_id
 * 
 * Body:
 * {
 *   "data": {
 *     "chores": [...],
 *     "points": {...},
 *     "headers": {...},
 *     "extraState": {...}
 *   }
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

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only POST allowed']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['data'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON or missing data']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$gameData = $input['data'];

// Validate game data structure
if (!is_array($gameData)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'data must be an object']);
    exit;
}

try {
    // Encode as JSON for storage
    $dataJson = json_encode($gameData);

    // Insert or update
    $stmt = $pdo->prepare('
        INSERT INTO user_game_data (user_id, data_json, updated_at)
        VALUES (:user_id, :data_json, NOW())
        ON DUPLICATE KEY UPDATE
            data_json = VALUES(data_json),
            updated_at = VALUES(updated_at)
    ');

    $stmt->execute([
        ':user_id' => $userId,
        ':data_json' => $dataJson
    ]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
