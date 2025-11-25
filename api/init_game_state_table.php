<?php
/**
 * Database migration script to create the game_state table
 * Run this once to initialize the table structure for group-scoped chore data persistence
 * 
 * Usage: Visit /api/init_game_state_table.php in your browser
 * (Optionally, restrict this to admin-only or run via command line)
 */

require __DIR__ . '/db.php';

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS game_state (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL UNIQUE,
            data_json LONGTEXT NOT NULL COMMENT "JSON blob with chores, points, headers, extraState",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_group (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

    echo json_encode([
        'ok' => true,
        'message' => 'game_state table created successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create table: ' . $e->getMessage()
    ]);
}
?>
