# Database Setup Guide

## Quick Answer
**Yes, you need to run the SQL!** The database tables don't exist yet.

## What You Need to Do

### Step 1: Access Your Database
You're using a hosted MySQL database:
- **Host**: `db5019042997.hosting-data.io`
- **Database**: `dbs14985870`
- **User**: `dbu5466581`

### Step 2: Run the SQL Schema
You need to execute the `database_schema.sql` file on your database. You have several options:

#### Option A: Using phpMyAdmin (Easiest)
1. Log into your hosting provider's control panel
2. Open phpMyAdmin
3. Select your database (`dbs14985870`)
4. Click the "SQL" tab
5. Copy and paste the contents of `database_schema.sql`
6. Click "Go" to execute

#### Option B: Using MySQL Command Line
```bash
mysql -h db5019042997.hosting-data.io -u dbu5466581 -p dbs14985870 < database_schema.sql
```
(Enter your password when prompted)

#### Option C: Using a GUI Tool (like MySQL Workbench)
1. Connect to your database using the credentials above
2. Open `database_schema.sql`
3. Execute the script

### Step 3: Verify Tables Were Created
After running the SQL, verify these 6 tables exist:
- ✅ `users` - User accounts
- ✅ `groups` - Group information
- ✅ `group_members` - User-to-group relationships
- ✅ `game_state` - Chore game data per group
- ✅ `shop_state` - Shop items and inventories per group
- ✅ `user_game_data` - Personal user data for cross-device sync

## What Each Table Does

### `users`
Stores user login credentials
- Created when someone registers at `login.html`

### `groups`
Each row = one household/family/team
- Has unique ID, name, description, and invite code
- Created when you click "Create New Group"

### `group_members`
Links users to groups
- Shows who belongs to which groups
- Tracks if they're the owner or a member

### `game_state`
**This is where your chore data lives!**
- One row per group
- `group_id` = which group (e.g., 42)
- `data_json` = all the chores, points, player names
- This is what keeps groups separated!

### `shop_state`
**This is where your shop data lives!**
- One row per group
- `group_id` = which group
- `items_json` = shop inventory
- `inventories_json` = what each player bought
- Also keeps groups separated!

### `user_game_data`
Personal backup of your data
- Used for cross-device synchronization
- One row per user

## Why This Matters for Group Separation

The **PRIMARY KEY on `group_id`** in both `game_state` and `shop_state` is crucial:

```sql
game_state:
group_id | data_json
---------|------------------
42       | {...Smith data...}
43       | {...Jones data...}

shop_state:
group_id | data_json
---------|------------------
42       | {...Smith shop...}
43       | {...Jones shop...}
```

This enforces:
- ✅ **One row per group** - Can't duplicate data
- ✅ **Complete isolation** - Group 42 can never access Group 43's data
- ✅ **Referential integrity** - If you delete a group, its data is automatically deleted

## Testing After Setup

1. Create a test account at `login.html`
2. Create a group called "Test Group"
3. Add some chores on `index.html`
4. Visit `shop.html` and add shop items
5. Log out and check your database - you should see:
   - Row in `users` table
   - Row in `groups` table
   - Row in `group_members` table
   - Row in `game_state` table (with your chores)
   - Row in `shop_state` table (with your shop items)

## Troubleshooting

### "Table already exists" error
- Safe to ignore - it means the table is already there
- The `IF NOT EXISTS` clause prevents errors

### "Foreign key constraint fails"
- Run the tables in order (they are ordered correctly in the SQL file)
- Make sure parent tables exist before creating child tables

### "Access denied"
- Check your database credentials in `api/db.php`
- Verify you have CREATE TABLE permissions

## Security Note

⚠️ **IMPORTANT**: The file `api/db.php` contains your database password in plain text. Make sure:
- This file is NOT committed to a public repository
- Your `.gitignore` includes `api/db.php` or `api/db_config.php`
- Only the server can access this file (not web-accessible)

## Need Help?

If you run into issues:
1. Check your hosting provider's documentation for database access
2. Look for phpMyAdmin or similar database management tool
3. Contact your hosting support if you can't access the database
4. Make sure your user has CREATE, ALTER, and INSERT permissions
