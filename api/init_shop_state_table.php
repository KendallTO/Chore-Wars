<?php
/**
 * Database migration script to create the shop_state table
 * Run this once to initialize the table structure for shop items and inventories
 * 
 * Usage: Visit /api/init_shop_state_table.php in your browser
 * (Optionally, restrict this to admin-only or run via command line)
 */

require __DIR__ . '/db.php';

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS shop_state (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            user_id INT,
            items_json LONGTEXT NOT NULL COMMENT "JSON array of shop items",
            inventories_json LONGTEXT NOT NULL COMMENT "JSON object mapping player locations to item ids",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_group_user (group_id, user_id),
            INDEX idx_group (group_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

    echo json_encode([
        'ok' => true,
        'message' => 'shop_state table created successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create table: ' . $e->getMessage()
    ]);
}
?>
