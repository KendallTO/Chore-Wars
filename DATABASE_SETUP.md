# SQL Commands - Cross-Device Sync Setup

## 📋 Database Setup Instructions

This document contains all SQL commands needed to set up cross-device synchronization on your IONOS database.

---

## ✅ Step 1: Check Existing Tables

Before making any changes, check what tables currently exist in your database:

```sql
SHOW TABLES;
```

**Expected output:**
```
users
groups
group_members
game_state
shop_state
user_groups
```

---

## 🆕 Step 2: Create the New `user_game_data` Table

✅ **Your `users.id` is `int unsigned`**, so use this exact SQL:

```sql
CREATE TABLE IF NOT EXISTS user_game_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

This will work with one command - no need for the two-step process!

**What this does:**
- Creates a table with one record per user
- Stores game data as JSON
- Automatically tracks when data was created and updated
- Deletes the record if the user is deleted (cascade)

**Note:** The `IF NOT EXISTS` clause means this is safe to run multiple times - it will only create the table if it doesn't already exist.

---

## ✔️ Step 3: Verify the Table Was Created

After running the CREATE TABLE command, verify it worked:

```sql
DESCRIBE user_game_data;
```

**Expected output:**
```
Field        | Type                 | Null | Key | Default              | Extra
─────────────────────────────────────────────────────────────────────────────
id           | int                  | NO   | PRI | NULL                 | auto_increment
user_id      | int                  | NO   | UNI | NULL                 |
game_data    | longtext             | NO   |     | NULL                 |
created_at   | timestamp            | NO   |     | CURRENT_TIMESTAMP    |
updated_at   | timestamp            | NO   |     | CURRENT_TIMESTAMP ON  | DEFAULT_GENERATED
                                                   | UPDATE CURRENT_...   |
```

---

## 📊 Step 4: Verify Foreign Key Constraint

Check that the foreign key was created correctly:

```sql
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME = 'user_game_data' AND COLUMN_NAME = 'user_id';
```

**Expected output:**
```
CONSTRAINT_NAME       | TABLE_NAME      | COLUMN_NAME | REFERENCED_TABLE_NAME | REFERENCED_COLUMN_NAME
──────────────────────────────────────────────────────────────────────────────────────────────────────
user_game_data_ibfk_1 | user_game_data  | user_id     | users                 | id
```

---

## 🗑️ Step 5: Optional - Delete Old Table (If Exists)

**⚠️ ONLY run this if you had a previous implementation and need to start fresh!**

If you previously created a `user_game_data` table and need to delete it:

```sql
DROP TABLE IF EXISTS user_game_data;
```

**⚠️ WARNING:** This will delete all stored user game data. Only use this if you're resetting.

After deleting, run Step 2 again to create the fresh table.

---

## 📝 Step 6: Test the Table (Optional)

Once the table is created, you can insert test data:

```sql
-- Insert test data (replace user_id with an actual user ID from your database)
INSERT INTO user_game_data (user_id, game_data) 
VALUES (1, '{"chores":[],"points":{"left":0,"right":0},"headers":{"left":"Player 1","right":"Player 2"},"extraState":{}}');
```

Then verify it:

```sql
SELECT * FROM user_game_data WHERE user_id = 1;
```

---

## 🔄 Step 7: Check Database Size (Optional)

See how much space the new table is using:

```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
ORDER BY (data_length + index_length) DESC;
```

---

## 🛠️ Complete Setup Command (All at Once)

Copy and paste this ONE command (it includes the foreign key):

```sql
CREATE TABLE IF NOT EXISTS user_game_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Then verify it worked:

```sql
SHOW TABLES LIKE 'user_game_data';
DESCRIBE user_game_data;
```

---

## ❌ Do I Need to Delete Old Tables?

### Current Existing Tables (Safe to Keep)

These tables should **NOT be deleted** - they're used by the existing application:

```sql
users              -- User accounts
groups             -- Group management
group_members      -- Group member relationships
game_state         -- Per-group game state (existing)
shop_state         -- Shop data
user_groups        -- User-group relationships
```

### New Table

The new table being added:
```sql
user_game_data     -- Per-user personal game data (KEEP THIS)
```

**No deletion needed!** The new system works alongside the existing tables.

---

## 📊 Complete Database Schema After Setup

Your database will have these tables:

```
users (existing)
├─ id
├─ username
├─ password_hash
├─ email
└─ ...

groups (existing)
├─ id
├─ name
└─ ...

group_members (existing)
├─ group_id → groups.id
├─ user_id → users.id
└─ ...

game_state (existing)
├─ group_id → groups.id
├─ data_json
└─ ...

shop_state (existing)
└─ ...

user_groups (existing)
└─ ...

user_game_data (NEW)
├─ id
├─ user_id → users.id (UNIQUE)
├─ game_data (JSON)
├─ created_at
└─ updated_at
```

---

## 🔍 How to Execute These Commands

### Option 1: phpMyAdmin (Recommended for IONOS)

1. Log into your IONOS control panel
2. Open phpMyAdmin
3. Select your database (dbs14985870)
4. Click "SQL" tab
5. Paste the CREATE TABLE command
6. Click "Go" to execute

### Option 2: Command Line (If you have SSH access)

```bash
mysql -h db5019042997.hosting-data.io -u dbu5466581 -p dbs14985870 << EOF
CREATE TABLE IF NOT EXISTS user_game_data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
```

### Option 3: Using PHP Script

Alternatively, visit this URL in your browser to auto-create the table:
```
https://your-domain.com/api/init_user_data_table.php
```

---

## ✅ Verification Checklist

After running the SQL commands, verify:

- [ ] Table `user_game_data` exists: `SHOW TABLES LIKE 'user_game_data';`
- [ ] Table has correct structure: `DESCRIBE user_game_data;`
- [ ] Foreign key created: Query INFORMATION_SCHEMA
- [ ] All existing tables still intact: `SHOW TABLES;`
- [ ] No error messages in phpMyAdmin

---

## 🚨 Troubleshooting

### Problem: "Referencing column and referenced column are incompatible" (Error #3780)
```
Error: Referencing column 'user_id' and referenced column 'id' in foreign key constraint are incompatible.
```

**Solution:** The column types don't match. Use the **two-step approach**:
1. Create table WITHOUT foreign key (just use `INT NOT NULL UNIQUE`)
2. Then run the ALTER TABLE to add the foreign key

If ALTER TABLE still fails:
```sql
-- First, check your users table structure
DESCRIBE users;

-- Look at the 'id' column type and share it with me
-- Then I can give you the exact SQL that matches
```

### Problem: "Table already exists"
```
Error: Table 'user_game_data' already exists
```
**Solution:** Use `CREATE TABLE IF NOT EXISTS` (already in the command above)

### Problem: "Foreign key constraint fails"
```
Error: Cannot add or update a foreign key constraint
```
**Solution:** This usually means:
1. The referenced user doesn't exist in the `users` table
2. The column types don't match (see first solution above)
3. Try creating without foreign key first, then adding it separately

### Problem: "Unknown character set"
```
Error: Unknown character set: 'utf8mb4'
```
**Solution:** Use `utf8` instead of `utf8mb4` (older MySQL):
```sql
CREATE TABLE user_game_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Problem: "Syntax error"
**Solution:** Make sure:
- All semicolons (`;`) are included at the end
- Parentheses are balanced
- No extra commas at the end

---

## 🔄 Rolling Back (If Needed)

If you need to completely remove the cross-device sync:

```sql
-- Drop the user_game_data table
DROP TABLE IF EXISTS user_game_data;

-- Verify it's gone
SHOW TABLES LIKE 'user_game_data';
```

This will return no results if successful.

---

## 📈 Monitoring the Table

### Check table size:
```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_name = 'user_game_data' AND table_schema = DATABASE();
```

### Check number of records:
```sql
SELECT COUNT(*) as record_count FROM user_game_data;
```

### Check most recently updated:
```sql
SELECT user_id, updated_at FROM user_game_data ORDER BY updated_at DESC LIMIT 5;
```

### Check oldest records:
```sql
SELECT user_id, created_at FROM user_game_data ORDER BY created_at ASC LIMIT 5;
```

---

## 🗄️ Data Maintenance (Optional)

### Delete user data older than 1 year:
```sql
DELETE FROM user_game_data 
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Delete data for specific user:
```sql
DELETE FROM user_game_data WHERE user_id = 123;
```

### Update user data (if needed):
```sql
UPDATE user_game_data 
SET game_data = '{"chores":[],"points":{},"headers":{},"extraState":{}}'
WHERE user_id = 123;
```

---

## 📝 Summary

### What Changed
- ✅ Added 1 new table: `user_game_data`
- ✅ No existing tables deleted
- ✅ No existing tables modified
- ✅ Fully backwards compatible

### What to Run

```sql
CREATE TABLE IF NOT EXISTS user_game_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Do You Need to Delete Old Tables?
**NO** - All existing tables should be kept. They are not affected by the new implementation.

---

## 🎯 Next Steps

1. ✅ Run the CREATE TABLE command
2. ✅ Verify the table was created
3. ✅ Upload the 3 new PHP files
4. ✅ Replace auth.php and index.html
5. ✅ Test the cross-device sync

---

**Database Setup Date:** November 24, 2025
**Status:** ✅ Ready to Execute
**Compatibility:** IONOS MySQL 5.7+
