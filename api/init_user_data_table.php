<?php
/**
 * Database migration script to create the user_game_data table
 * Run this once to initialize the table structure for cross-device sync
 * 
 * Usage: Visit /api/init_user_data_table.php in your browser
 * (Optionally, restrict this to admin-only or run via command line)
 */

require __DIR__ . '/db.php';

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS user_game_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            game_data LONGTEXT NOT NULL COMMENT "JSON blob with chores, points, headers, extraState",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

    echo json_encode([
        'ok' => true,
        'message' => 'user_game_data table created successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create table: ' . $e->getMessage()
    ]);
}
