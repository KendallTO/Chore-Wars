-- Chore Wars Database Schema
-- Run this SQL to create all necessary tables for the application

-- ===================================
-- USERS TABLE
-- ===================================
-- Stores user account information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- GROUPS TABLE
-- ===================================
-- Stores group information (families, households, teams, etc.)
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    invite_code VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invite_code (invite_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- GROUP MEMBERS TABLE
-- ===================================
-- Links users to groups with their role (owner/member)
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'member') NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_group (user_id, group_id),
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_group_id (group_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- GAME STATE TABLE
-- ===================================
-- Stores the game state (chores, points, headers, etc.) for each group
CREATE TABLE IF NOT EXISTS game_state (
    group_id INT PRIMARY KEY,
    data_json JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- SHOP STATE TABLE
-- ===================================
-- Stores shop items and player inventories for each group
CREATE TABLE IF NOT EXISTS shop_state (
    group_id INT PRIMARY KEY,
    user_id INT,
    items_json JSON,
    inventories_json JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- USER GAME DATA TABLE
-- ===================================
-- Stores personal user data for cross-device synchronization
CREATE TABLE IF NOT EXISTS user_game_data (
    user_id INT PRIMARY KEY,
    data_json JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- NOTES
-- ===================================
-- JSON Structure Examples:
--
-- game_state.data_json:
-- {
--   "chores": [...],
--   "points": {"left": 100, "right": 50, "extra1": 75},
--   "headers": {"left": "Player 1", "right": "Player 2"},
--   "extraState": {...}
-- }
--
-- shop_state.items_json:
-- [
--   {"id": "abc123", "name": "Candy Bar", "price": 10, "stock": 5},
--   ...
-- ]
--
-- shop_state.inventories_json:
-- {
--   "left": [{"itemId": "abc123", "purchasedAt": "2025-11-25"}],
--   "right": [...]
-- }
