# Cross-Device Sync Implementation Guide

## Overview

Your Chore-Wars application now supports cross-device synchronization. Users can sign in on different devices and have their game data automatically transferred to and from your IONOS server.

## How It Works

### 1. **User Account Data Storage**
- When a user logs in, their personal game data (chores, points, headers, extra state) is automatically loaded from the server
- When a user logs out or makes changes, their data is saved to the server
- This data is independent of group-specific data, so it follows the user across devices

### 2. **Data Sync Flow**

#### On Login
```
User Login → Backend loads user account data → Frontend receives userData
  ↓
If local storage is empty → Restore from server data
  ↓
User can continue with their saved game state on new device
```

#### During Gameplay
```
User makes changes → Data is queued for save (500ms debounce)
  ↓
Data is saved to group's game state (existing system)
  ↓
User continues playing without interruption
```

#### On Logout
```
User clicks logout → User account data is saved to server
  ↓
Local storage is wiped → Session cleared
  ↓
User redirected to login
```

#### On Next Device Login
```
User logs in on new device → Server fetches their account data
  ↓
Local storage is empty → Server data is restored
  ↓
User's previous game state is available
```

## Setup Instructions

### Step 1: Initialize Database Table

Visit your server and run the initialization endpoint:
```
GET /api/init_user_data_table.php
```

Or manually run this SQL query on your IONOS database:
```sql
CREATE TABLE IF NOT EXISTS user_game_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL COMMENT "JSON blob with chores, points, headers, extraState",
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Upload Files to Server

Upload these new files to your IONOS server's `/api/` directory:
- `init_user_data_table.php` - Database initialization
- `save_user_data.php` - Save user account data endpoint
- `load_user_data.php` - Load user account data endpoint

Replace these files:
- `api/auth.php` - Updated to return userData on login

Replace these files:
- `index.html` - Updated to handle user data sync

## API Endpoints

### POST `/api/save_user_data.php`
Saves the user's game data to their account

**Request:**
```json
{
  "data": {
    "chores": [...],
    "points": {...},
    "headers": {...},
    "extraState": {...}
  }
}
```

**Response:**
```json
{
  "ok": true
}
```

**Requirements:** Authenticated session (user_id in session)

---

### GET `/api/load_user_data.php`
Retrieves the user's previously saved game data

**Response:**
```json
{
  "ok": true,
  "data": {
    "chores": [...],
    "points": {...},
    "headers": {...},
    "extraState": {...}
  },
  "updatedAt": "2025-11-24 10:30:45"
}
```

If no data exists:
```json
{
  "ok": true,
  "data": null,
  "updatedAt": null
}
```

**Requirements:** Authenticated session (user_id in session)

---

### POST `/api/auth.php`
Updated login endpoint that now includes userData

**Login Request:**
```json
{
  "action": "login",
  "username": "john",
  "password": "password123"
}
```

**Response:**
```json
{
  "ok": true,
  "user": {
    "id": 1,
    "username": "john",
    "email": "john@example.com"
  },
  "userData": {
    "data": {...},
    "updatedAt": "2025-11-24 10:30:45"
  }
}
```

If no previous data exists, `userData` will be null.

## Data Flow Diagram

```
DEVICE 1                          SERVER                      DEVICE 2
─────────                         ──────                      ─────────

User plays game
   │
   ├─→ Changes saved locally
   │
   ├─→ Chore state → /api/save_game_state.php
   │                 (group-specific data)
   │
   └─→ Logout
       │
       └─→ Dispatch event
           │
           └─→ /api/save_user_data.php
               (personal user account data)
               │
               └─→ user_game_data table
                   stored in IONOS DB
                   
                                           ┌─→ User clicks login
                                           │
                                           ├─→ /api/auth.php
                                           │   │
                                           │   ├─→ Verify credentials
                                           │   │
                                           │   └─→ Load from user_game_data
                                           │
                                           ├─→ Frontend receives userData
                                           │
                                           └─→ Restore to localStorage
                                               if local storage empty
```

## Testing the Feature

### Test 1: Single Device Logout/Login
1. Sign in on Device 1
2. Play and make changes (add chores, earn points)
3. Make a note of the data
4. Logout
5. Login again
6. Verify your data is restored ✓

### Test 2: Cross-Device Sync
1. Sign in on Device 1
2. Play and make changes
3. Make a note of the data
4. Logout
5. **On a different device (Device 2):**
6. Sign in with the same account
7. Verify your data from Device 1 appears ✓
8. Make new changes on Device 2
9. Logout
10. **Back on Device 1:**
11. Sign in again
12. Verify Device 2's changes are present ✓

### Test 3: New User First Login
1. Create new account
2. Sign in
3. Should start with empty/fresh game state ✓
4. Add some data and logout
5. Login again on same or different device
6. Data should persist ✓

## Conflict Resolution Strategy

**Current Strategy: Local Data Takes Priority**

When a user logs in on a new device:
- If local storage is **empty** → Restore from server
- If local storage has **any data** → Keep local data, don't overwrite

This prevents accidental data loss if a user has local changes that haven't been saved yet.

**Future Enhancement:** You could implement more sophisticated conflict resolution (timestamps, merge strategies, etc.)

## Storage Structure

### user_game_data table
```
id              INT           - Primary key
user_id         INT           - Foreign key to users table
game_data       LONGTEXT      - JSON blob containing:
                                {
                                  "chores": [...],
                                  "points": {...},
                                  "headers": {...},
                                  "extraState": {...}
                                }
created_at      TIMESTAMP     - Account data creation time
updated_at      TIMESTAMP     - Last update time
```

## Troubleshooting

### Issue: "Not authenticated" error when saving
**Solution:** Ensure the user is logged in (session exists). Check that PHP sessions are properly configured.

### Issue: Data not restoring on new device
**Solution:** 
1. Check that the `user_game_data` table exists
2. Verify the user ID is correct in the database
3. Check browser console for JavaScript errors
4. Check server error logs for database errors

### Issue: Database connection errors
**Solution:**
1. Verify IONOS database credentials in `db.php`
2. Ensure your IP is whitelisted in IONOS (if applicable)
3. Test the connection with a simple query script

## Security Considerations

✓ **Currently Implemented:**
- User data is tied to session authentication
- Only authenticated users can save/load their data
- Foreign key constraint ensures data can't be orphaned

⚠️ **Recommendations:**
- Add rate limiting to prevent abuse
- Validate JSON structure on save
- Consider encryption for sensitive game data
- Add data validation before saving to database
- Log all data access for audit trails

## Performance Notes

- User data load is async and non-blocking
- Data save uses 500ms debounce to avoid excessive DB writes
- Logout waits 500ms for pending saves to complete
- No performance impact on regular gameplay

## Future Enhancements

1. **Data Versioning** - Keep history of previous saves
2. **Selective Sync** - Allow users to choose what data to sync
3. **Conflict Resolution** - Show conflicts when data differs between devices
4. **Backup/Restore** - Let users manually backup/restore game data
5. **Data Export** - Allow users to export their game history as CSV/JSON
6. **Multi-Account Sync** - Sync data across multiple user accounts

## Questions & Support

For issues or questions, check:
1. Browser console (F12) for JavaScript errors
2. Network tab (F12) to see API calls
3. Server error logs on IONOS
4. Database logs for SQL errors

---

**Last Updated:** November 24, 2025
**Version:** 1.0
