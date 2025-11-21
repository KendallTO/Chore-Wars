-- ===================================================================
-- CHORE WARS DATABASE SETUP
-- ===================================================================
-- This file creates all necessary tables for the group invite system
-- Run this in your cPanel MySQL or phpMyAdmin
--
-- IMPORTANT: Make sure to select your database first!
-- ===================================================================

-- ===================================================================
-- 1. GROUPS TABLE
-- ===================================================================
-- Stores all group information including invite codes
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique group identifier',
    name VARCHAR(100) NOT NULL COMMENT 'Group name (max 100 chars)',
    description TEXT COMMENT 'Optional group description',
    invite_code VARCHAR(8) NOT NULL UNIQUE COMMENT 'Unique 8-character invite code',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the group was created',
    
    -- Indexes for performance
    INDEX idx_invite_code (invite_code) COMMENT 'Fast lookup by invite code'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores group information with invite codes';

-- ===================================================================
-- 2. GROUP_MEMBERS TABLE
-- ===================================================================
-- Links users to groups with their roles (owner or member)
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique membership record',
    group_id INT NOT NULL COMMENT 'Reference to groups table',
    user_id INT NOT NULL COMMENT 'Reference to users table',
    role ENUM('owner', 'member') NOT NULL DEFAULT 'member' COMMENT 'User role in group',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When user joined the group',
    
    -- Foreign keys for data integrity
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE COMMENT 'Delete memberships when group is deleted',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE COMMENT 'Delete memberships when user is deleted',
    
    -- Unique constraint to prevent duplicate memberships
    UNIQUE KEY unique_group_user (group_id, user_id) COMMENT 'One membership per user per group',
    
    -- Indexes for performance
    INDEX idx_user_id (user_id) COMMENT 'Fast lookup of user memberships',
    INDEX idx_group_id (group_id) COMMENT 'Fast lookup of group members'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Links users to groups with ownership roles';

-- ===================================================================
-- VERIFY TABLES WERE CREATED
-- ===================================================================
-- Run these commands to verify the tables exist:
-- SHOW TABLES LIKE 'groups';
-- SHOW TABLES LIKE 'group_members';
-- 
-- To see table structure:
-- DESCRIBE groups;
-- DESCRIBE group_members;

-- ===================================================================
-- SAMPLE DATA (OPTIONAL - FOR TESTING)
-- ===================================================================
-- Uncomment the lines below to add test data
-- NOTE: You need to have a user with id=1 in your users table first

-- INSERT INTO groups (name, description, invite_code) VALUES
-- ('Test Family', 'A test family group', 'TEST1234'),
-- ('Roommates', 'Apartment roommates', 'ROOM5678');

-- INSERT INTO group_members (group_id, user_id, role) VALUES
-- (1, 1, 'owner'),  -- User 1 is owner of group 1
-- (2, 1, 'owner');  -- User 1 is owner of group 2

-- ===================================================================
-- CLEANUP (OPTIONAL - USE CAREFULLY!)
-- ===================================================================
-- To remove the tables and start fresh, uncomment these lines:
-- WARNING: This will delete all group data!

-- DROP TABLE IF EXISTS group_members;
-- DROP TABLE IF EXISTS groups;

-- ===================================================================
-- USEFUL QUERIES FOR TESTING
-- ===================================================================

-- Get all groups for a specific user (replace 1 with actual user_id):
-- SELECT g.*, gm.role 
-- FROM groups g 
-- JOIN group_members gm ON g.id = gm.group_id 
-- WHERE gm.user_id = 1;

-- Get all members of a specific group (replace 1 with actual group_id):
-- SELECT u.*, gm.role, gm.joined_at
-- FROM users u
-- JOIN group_members gm ON u.id = gm.user_id
-- WHERE gm.group_id = 1;

-- Find a group by invite code:
-- SELECT * FROM groups WHERE invite_code = 'TEST1234';

-- Count members in each group:
-- SELECT g.name, COUNT(gm.user_id) as member_count
-- FROM groups g
-- LEFT JOIN group_members gm ON g.id = gm.group_id
-- GROUP BY g.id, g.name;
