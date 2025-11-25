# Cross-Device Sync - Architecture Diagrams

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         IONOS Server                             │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              MySQL Database                             │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │ users                                            │   │   │
│  │  │ ├─ id, username, password_hash, email          │   │   │
│  │  │ └─ ...                                          │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │ user_game_data (NEW)                            │   │   │
│  │  │ ├─ id                                            │   │   │
│  │  │ ├─ user_id (FK to users.id)                     │   │   │
│  │  │ ├─ game_data (JSON blob)                        │   │   │
│  │  │ ├─ created_at, updated_at                       │   │   │
│  │  │ └─ ...                                          │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │ game_state                                      │   │   │
│  │  │ ├─ group_id, data_json                         │   │   │
│  │  │ └─ (existing group-specific data)              │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              PHP API Endpoints                          │   │
│  │  ├─ /api/auth.php (UPDATED)                           │   │
│  │  ├─ /api/save_user_data.php (NEW)                     │   │
│  │  ├─ /api/load_user_data.php (NEW)                     │   │
│  │  ├─ /api/init_user_data_table.php (NEW)               │   │
│  │  └─ /api/save_game_state.php (existing)               │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
         ▲                              ▲
         │                              │
         │  HTTP/HTTPS                  │  HTTP/HTTPS
         │                              │
    ┌────┴─────────────────────────────┴────┐
    │                                        │
    │                                        │
DEVICE 1 (Browser)                  DEVICE 2 (Browser)
┌─────────────────┐                 ┌─────────────────┐
│  index.html     │                 │  index.html     │
│  JavaScript     │                 │  JavaScript     │
│  localStorage   │                 │  localStorage   │
└─────────────────┘                 └─────────────────┘
```

---

## Data Flow - Login to Logout

```
┌────────────────────────────────────────────────────────────────────┐
│                    USER LOGIN FLOW                                 │
└────────────────────────────────────────────────────────────────────┘

DEVICE (Browser)                    SERVER
     │                                │
     ├─ User enters username/password─┤
     │                                │
     └────────────────────────────────> POST /api/auth.php
                                        │
                                        ├─ Verify credentials
                                        │
                                        ├─ Load from user_game_data
                                        │
                            <──────────┤ Return user + userData
     │                                │
     ├─ Save session to localStorage  │
     │                                │
     ├─ loadUserData() called          │
     │                                │
     └────────────────────────────────> GET /api/load_user_data.php
                                        │
                            <──────────┤ Return game data or null
     │                                │
     ├─ If local storage empty:        │
     │  └─ Restore from server data   │
     │                                │
     ├─ render() - Show game board    │
     │                                │
     └─ Ready to play!                │


┌────────────────────────────────────────────────────────────────────┐
│                 USER GAMEPLAY FLOW (existing)                      │
└────────────────────────────────────────────────────────────────────┘

DEVICE (Browser)                    SERVER
     │                                │
     ├─ User plays game               │
     │  └─ Makes changes              │
     │                                │
     ├─ Changes saved to localStorage │
     │                                │
     ├─ queueServerSave (500ms)       │
     │                                │
     └────────────────────────────────> POST /api/save_game_state.php
                                        │
                                        ├─ Save to group data
                                        │
     │                            <───┤ Success response
     │                                │
     └─ Continue playing              │


┌────────────────────────────────────────────────────────────────────┐
│                   USER LOGOUT FLOW                                 │
└────────────────────────────────────────────────────────────────────┘

DEVICE (Browser)                    SERVER
     │                                │
     ├─ User clicks Logout            │
     │                                │
     ├─ Dispatch saveUserDataBeforeLogout event
     │                                │
     ├─ saveUserDataToAccount()       │
     │                                │
     └────────────────────────────────> POST /api/save_user_data.php
                                        │
                                        ├─ Save to user_game_data
                                        │
     │                            <───┤ Success response
     │                                │
     ├─ Wait 500ms for save to complete
     │                                │
     ├─ Clear all localStorage        │
     │                                │
     ├─ Destroy session               │
     │                                │
     └─ Redirect to login.html        │
```

---

## Multi-Device Sync Sequence

```
Timeline of Cross-Device Usage:

DEVICE 1                        SERVER                      DEVICE 2
─────────                       ──────                      ─────────

Day 1:
User logs in ────────────────────────────>
             <─────────── userData: null (first time)
             
Plays game, earns 50 pts
(data in localStorage)

Clicks logout ──────────────────────────>
             <─ Save: {points: 50, ...}
             
user_game_data table:
user_id: 123, data: {points: 50}


Day 2, Different Device:
                                        User logs in ────────>
                                        <─ userData: {points: 50}
                                        
                                        localStorage restored
                                        Shows 50 points ✓
                                        
                                        Plays more, earns 30 pts
                                        Now has 80 points
                                        
                                        Clicks logout ────────>
                                        <─ Save: {points: 80, ...}
                                        
                                        user_game_data table:
                                        user_id: 123, data: {points: 80}

Day 3, Back on Device 1:
User logs in ────────────────────────────>
             <─────────── userData: {points: 80}
             
Points restored to 80 ✓
(Device 2's progress is now on Device 1)

Both devices stay in sync!
```

---

## Data Conflict Resolution

```
┌──────────────────────────────────────────────────────────┐
│            CONFLICT RESOLUTION LOGIC                    │
└──────────────────────────────────────────────────────────┘

User logs in on new device
        │
        ├─ Check: Does local storage have data?
        │
        ├─ IF YES (local storage has data)
        │  │
        │  └─→ KEEP LOCAL DATA
        │       Reason: Don't overwrite user's local changes
        │
        ├─ IF NO (local storage is empty)
        │  │
        │  └─→ RESTORE FROM SERVER
        │       Reason: User has no local data, restore their account data
        │
        └─ Start playing!


Example Scenarios:

┌─ Scenario 1: Fresh browser on new device
│  │
│  ├─ Local storage: EMPTY
│  ├─ Server data: {points: 50, ...}
│  │
│  └─→ ACTION: Restore server data
│       Result: User sees 50 points ✓

├─ Scenario 2: Browser with local unsaved data
│  │
│  ├─ Local storage: {points: 30, ...} (local only)
│  ├─ Server data: {points: 50, ...} (from another device)
│  │
│  └─→ ACTION: Keep local data (30 points)
│       Reason: Don't lose local changes
│       Note: Local data will sync to server on logout

└─ Scenario 3: Same device, same browser
    │
    ├─ Local storage: {points: 50, ...} (persisted)
    ├─ Server data: {points: 50, ...} (matching)
    │
    └─→ ACTION: Keep local data
         Result: No change, user continues where they left off ✓
```

---

## API Call Sequence Diagram

```
GET /api/load_user_data.php
│
├─ Check session (user authenticated?)
│  ├─ NO ──> Return 401 Unauthorized
│  └─ YES
│
├─ Query: SELECT game_data FROM user_game_data WHERE user_id = :id
│
├─ Row found?
│  ├─ NO ──> Return { ok: true, data: null }
│  └─ YES
│
├─ Parse JSON from database
│
└─> Return { ok: true, data: {...}, updatedAt: "..." }


POST /api/save_user_data.php
│
├─ Check session (user authenticated?)
│  ├─ NO ──> Return 401 Unauthorized
│  └─ YES
│
├─ Parse request body
│  ├─ Invalid ──> Return 400 Bad Request
│  └─ Valid
│
├─ Encode data to JSON
│
├─ INSERT INTO user_game_data
│  ON DUPLICATE KEY UPDATE game_data, updated_at
│
└─> Return { ok: true }
```

---

## localStorage Structure (Frontend)

```
Browser localStorage
│
├─ tcg_session_v1
│  └─ {id: 123, username: "john", email: "john@..."}
│
├─ tcg_current_group_v1
│  └─ "group_123"
│
├─ tcg_chores_v1_group_123
│  └─ [{id: 1, title: "Dishes", ...}, ...]
│
├─ tcg_points_v1_group_123
│  └─ {left: 50, right: 30, extra1: 0, ...}
│
├─ tcg_column_headers_v1_group_123
│  └─ {left: "John", right: "Sarah"}
│
└─ tcg_extra_cols_v1_group_123
   └─ {extra1: false, extra2: false, ...}

Note: All data for each group is isolated in localStorage
      User account data (user_game_data) stored on server
```

---

## State Machine - User Session

```
                    ┌─────────────────┐
                    │   NOT LOGGED IN  │
                    └────────┬─────────┘
                             │
                             │ Login (POST /api/auth.php)
                             │
                    ┌────────▼──────────┐
                    │  LOADING DATA     │
                    │  from server      │
                    └────────┬──────────┘
                             │
                             │ User data loaded (or null)
                             │
                    ┌────────▼──────────┐
                    │  MERGING DATA     │
                    │  (if needed)      │
                    └────────┬──────────┘
                             │
                             │ Done merging
                             │
        ┌────────────────────▼──────────────────────┐
        │                                           │
        │      LOGGED IN & PLAYING                 │
        │      (localStorage active)               │
        │                                           │
        └────────────┬────────────────────┬────────┘
                     │                    │
                     │ Make changes       │ Logout
                     │ (save every 500ms) │
                     │                    │
                     │              ┌─────▼──────────┐
                     │              │ SAVING DATA    │
                     │              │ to account     │
                     │              └─────┬──────────┘
                     │                    │
                     │              ┌─────▼──────────┐
                     │              │ CLEARING DATA  │
                     │              │ (localStorage) │
                     │              └─────┬──────────┘
                     │                    │
                     └────────────────────┼──────────┐
                                         │         │
                                         │    ┌────▼─────────┐
                                         │    │ NOT LOGGED IN │
                                         │    └──────────────┘
                                         │
                                    ┌────▼──────────┐
                                    │ REDIRECT TO   │
                                    │ login.html    │
                                    └───────────────┘
```

---

## Database Schema

```sql
┌─ users (existing)
│  ├─ id (PK)
│  ├─ username
│  ├─ password_hash
│  └─ email
│
└─ user_game_data (NEW)
   ├─ id (PK)
   ├─ user_id (FK → users.id, UNIQUE)
   ├─ game_data (LONGTEXT JSON)
   │  └─ contains: {chores, points, headers, extraState}
   ├─ created_at
   └─ updated_at

Relationship: 1 user → 1 user_game_data record
Index: UNIQUE on user_id for fast lookups
```

---

**Last Updated:** November 24, 2025
