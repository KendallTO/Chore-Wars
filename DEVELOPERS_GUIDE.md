# 👨‍💻 Developer's Quick Guide - Cross-Device Sync

## For Developers Getting Up to Speed

This guide helps developers understand the implementation quickly.

---

## 🏗️ System Architecture (60-second version)

```
Frontend (JavaScript)        Backend (PHP)           Database (MySQL)
─────────────────────        ─────────────           ─────────────────
index.html                   /api/auth.php           users
  ↓                            ↓                         ↓
loadUserData()               save_user_data.php      user_game_data (NEW)
  ↓                            ↓                         ↓
mergeUserDataIfNeeded()       load_user_data.php     game_state
  ↓                            ↓
saveUserDataToAccount()       init_user_data_table.php

Data Sync:
  Login   → load_user_data()  → populate localStorage
  Play    → normal saves (existing system)
  Logout  → saveUserDataToAccount() → persist to database
```

---

## 📝 Code Changes Summary

### Backend Changes (PHP)

#### auth.php - Load user data on login
```php
// Added: ~30 lines after successful password verification
$userData = null;
try {
    $stmt = $pdo->prepare('
        SELECT game_data, updated_at
        FROM user_game_data
        WHERE user_id = :user_id
    ');
    $stmt->execute([':user_id' => (int)$user['id']]);
    $row = $stmt->fetch();
    
    if ($row) {
        $userData = [
            'data' => json_decode($row['game_data'], true),
            'updatedAt' => $row['updated_at']
        ];
    }
} catch (Exception $e) {
    error_log('Error loading user game data: ' . $e->getMessage());
}

send_json([
    'ok' => true,
    'user' => $_SESSION['user'],
    'userData' => $userData
]);
```

### Frontend Changes (JavaScript)

#### index.html - 4 new functions

**1. loadUserData()**
```javascript
async function loadUserData() {
    try {
        const res = await fetch('/api/load_user_data.php');
        if (!res.ok) return null;
        const result = await res.json();
        if (result.ok && result.data) {
            return result.data;
        }
        return null;
    } catch (e) {
        console.error('Error loading user data from server', e);
        return null;
    }
}
```

**2. mergeUserDataIfNeeded()**
```javascript
async function mergeUserDataIfNeeded() {
    const userData = await loadUserData();
    if (!userData) return;
    
    // Check if local storage has any data already
    const hasLocalChores = (loadChores() || []).length > 0;
    const hasLocalPoints = Object.values(loadPoints() || {}).some(v => v);
    
    // If local storage is empty, restore from user account data
    if (!hasLocalChores && !hasLocalPoints) {
        if (userData.chores) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(userData.chores));
        }
        if (userData.points) {
            localStorage.setItem(POINTS_KEY, JSON.stringify(userData.points));
        }
        if (userData.headers) {
            localStorage.setItem(HEADERS_KEY, JSON.stringify(userData.headers));
        }
        if (userData.extraState) {
            localStorage.setItem(EXTRA_STATE_KEY, JSON.stringify(userData.extraState));
        }
        console.log('Restored game data from user account');
    }
}
```

**3. saveUserDataToAccount()**
```javascript
async function saveUserDataToAccount() {
    const fullState = buildFullGameState();
    
    try {
        const response = await fetch('/api/save_user_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              data: fullState
            })
        });
        
        if (!response.ok) {
            console.error('Failed to save user data:', response.status);
            return false;
        }
        
        const result = await response.json();
        return result.ok === true;
    } catch (e) {
        console.error('Error saving user data to account:', e);
        return false;
    }
}
```

**4. Integration in DOMContentLoaded**
```javascript
document.addEventListener('DOMContentLoaded', async () => {
    // ... existing code ...
    
    await loadStateFromServer();
    await mergeUserDataIfNeeded(); // NEW: Load user account data
    
    if (typeof render === 'function') {
        render();
    }
    renderPoints();
});
```

**5. Integration in Logout**
```javascript
document.getElementById('logout-btn').addEventListener('click', async () => {
    askLogoutConfirm().then(async (confirmed) => {
        if (confirmed) {
            // Save user data before logout
            window.dispatchEvent(new CustomEvent('saveUserDataBeforeLogout'));
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Clear session...
            localStorage.removeItem('tcg_session_v1');
            // ... rest of logout code ...
        }
    });
});
```

**6. Event Listener**
```javascript
window.addEventListener('saveUserDataBeforeLogout', async () => {
    await saveUserDataToAccount();
});
```

---

## 🗄️ Database Schema

### New Table: user_game_data
```sql
CREATE TABLE user_game_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    game_data LONGTEXT NOT NULL COMMENT "JSON: {chores, points, headers, extraState}",
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Data Flow
```
User submits data
    ↓
json_encode() → JSON string
    ↓
INSERT INTO user_game_data (user_id, game_data)
    ↓
SELECT game_data FROM user_game_data WHERE user_id = ?
    ↓
json_decode() → PHP array
    ↓
json_encode() → JSON for frontend
    ↓
JSON.parse() → JavaScript object
    ↓
localStorage.setItem() → Browser storage
```

---

## 🔄 Data Flow Sequences

### Login Flow
```
1. User enters credentials
2. POST /api/auth.php
3. Backend verifies password
4. Backend queries user_game_data table
5. Returns user + userData
6. Frontend receives userData
7. If localStorage empty → Restore from userData
8. Render game board with restored data
```

### Gameplay Flow
```
1. User makes change (existing system)
2. Change saved to localStorage
3. queueServerSave() called (500ms debounce)
4. POST /api/save_game_state.php (group data)
5. Data persists in group table
```

### Logout Flow
```
1. User clicks logout
2. Dispatch saveUserDataBeforeLogout event
3. saveUserDataToAccount() called
4. POST /api/save_user_data.php
5. Backend saves to user_game_data table
6. Frontend waits 500ms for completion
7. Clears all localStorage
8. Redirects to login.html
```

### Next Device Login Flow
```
1. User logs in on new device
2. Backend loads from user_game_data
3. Frontend receives userData
4. localStorage on new device is empty
5. mergeUserDataIfNeeded() restores userData
6. User sees their previous game state
```

---

## 🧪 Testing the Implementation

### Manual Testing Checklist

```javascript
// 1. Test loadUserData()
fetch('/api/load_user_data.php')
    .then(r => r.json())
    .then(d => console.log('User data:', d));

// 2. Test saveUserDataToAccount()
const state = buildFullGameState();
fetch('/api/save_user_data.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ data: state })
}).then(r => r.json()).then(d => console.log('Save result:', d));

// 3. Verify database
// SSH into IONOS and run:
// SELECT * FROM user_game_data WHERE user_id = 1;

// 4. Test conflict resolution
// Clear localStorage: localStorage.clear()
// Then call: mergeUserDataIfNeeded()
// Check console for 'Restored game data from user account'
```

---

## 🔒 Security Considerations

### Current Implementation
```javascript
✅ User ID from session
✅ Foreign key constraints
✅ Session-based auth required
✅ No client-side ID manipulation
```

### Recommended Additions
```javascript
⚠️ Input validation on game_data
⚠️ Rate limiting on API endpoints
⚠️ Data size limits (max 50KB)
⚠️ Encryption at rest (optional)
⚠️ Access logging
```

### Implementation Example
```php
// In save_user_data.php
if (strlen($dataJson) > 50000) { // 50KB limit
    http_response_code(400);
    echo json_encode(['error' => 'Data too large']);
    exit;
}

// Validate JSON structure
if (!isset($gameData['chores'], $gameData['points'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data structure']);
    exit;
}
```

---

## 🐛 Debugging Tips

### Enable Debug Logging
```php
// In PHP files, add:
error_log('User ' . $userId . ' loading data at ' . date('Y-m-d H:i:s'));
```

### Browser Console Debugging
```javascript
// Check if functions are defined
console.log(typeof loadUserData);
console.log(typeof mergeUserDataIfNeeded);
console.log(typeof saveUserDataToAccount);

// Check localStorage
console.log(localStorage.getItem('tcg_session_v1'));

// Check API responses
fetch('/api/load_user_data.php').then(r => r.json()).then(console.log);
```

### Database Debugging
```sql
-- Check if table exists
SHOW TABLES LIKE 'user_game_data';

-- View user data
SELECT id, user_id, DATE(created_at), DATE(updated_at) 
FROM user_game_data;

-- Check specific user
SELECT * FROM user_game_data WHERE user_id = 1;

-- View data structure
SELECT user_id, LENGTH(game_data) as data_size
FROM user_game_data;
```

---

## 🚀 Performance Optimization

### Current Optimizations
```
✅ 500ms debounce on saves (prevents DB overload)
✅ Async/await (non-blocking operations)
✅ JSON compression (text format)
✅ UNIQUE index on user_id (fast lookups)
✅ Foreign key (referential integrity)
```

### Future Optimizations
```
⚠️ Gzip compression on large payloads
⚠️ Redis caching for frequently accessed data
⚠️ Batch operations for multiple saves
⚠️ Archive old user_game_data records
```

---

## 📋 Deployment Checklist for Developers

```
BACKEND:
✅ auth.php updated and tested
✅ save_user_data.php uploaded
✅ load_user_data.php uploaded
✅ init_user_data_table.php uploaded
✅ Database table created

FRONTEND:
✅ index.html updated with new functions
✅ Event listeners configured
✅ localStorage keys correct
✅ No console errors

TESTING:
✅ Same device login/logout
✅ Cross-device sync
✅ Error scenarios
✅ Database queries verified

DOCUMENTATION:
✅ README updated
✅ API endpoints documented
✅ Deployment steps documented
✅ Troubleshooting guide available
```

---

## 🔗 API Reference for Developers

### POST /api/save_user_data.php
```
Request:
{
  "data": {
    "chores": [],
    "points": {},
    "headers": {},
    "extraState": {}
  }
}

Response (Success):
{
  "ok": true
}

Response (Error):
{
  "ok": false,
  "error": "Not authenticated"
}

HTTP Status:
- 200: Success
- 400: Bad request
- 401: Not authenticated
- 500: Server error
```

### GET /api/load_user_data.php
```
Response (Has Data):
{
  "ok": true,
  "data": {...},
  "updatedAt": "2025-11-24 10:30:45"
}

Response (No Data):
{
  "ok": true,
  "data": null,
  "updatedAt": null
}

HTTP Status:
- 200: Success
- 401: Not authenticated
- 500: Server error
```

### GET /api/init_user_data_table.php
```
Response (Success):
{
  "ok": true,
  "message": "user_game_data table created successfully"
}

Response (Table Exists):
{
  "ok": true,
  "message": "user_game_data table created successfully"
}

Response (Error):
{
  "ok": false,
  "error": "Failed to create table: ..."
}
```

---

## 🎯 Key Technical Decisions

### Why This Approach?
```
✅ Session-based auth → Already in place, reuse existing
✅ JSON storage → Flexible, easy to extend
✅ Debounced saves → Prevent database overload
✅ Async/await → Non-blocking, better UX
✅ Conflict: Local priority → Safer, prevents data loss
```

### Alternatives Considered
```
❌ Token-based auth → More complex, not needed
❌ Binary format → Less flexible
❌ Real-time sync → Complex, overkill
❌ Synchronous saves → Would block UI
❌ Conflict: Server priority → Could lose local data
```

---

## 📚 Related Functions (Already Exist)

```javascript
buildFullGameState()      // Collect all game data
saveStateToServer()       // Save group data
loadStateFromServer()     // Load group data
queueServerSave()         // Debounced save trigger
loadChores()              // Get chores from storage
loadPoints()              // Get points from storage
loadAllHeaders()          // Get player names from storage
loadExtraState()          // Get extra columns state
```

---

## 🔄 Git Workflow

```bash
# Files created
git add api/save_user_data.php
git add api/load_user_data.php
git add api/init_user_data_table.php

# Files modified
git add api/auth.php
git add index.html

# Documentation
git add *.md

# Commit
git commit -m "feat: implement cross-device synchronization"

# Deploy
git push origin main
```

---

## 📊 Code Metrics

```
Lines of Backend Code:      ~200
Lines of Frontend Code:      ~100
Lines of Documentation:      ~2000
Functions Added:             4
API Endpoints Added:         3
Database Tables Added:       1
Test Scenarios:              3+
```

---

## 🎓 Learning Resources

For developers new to the codebase:

1. **Start Here:** This file (you're reading it!)
2. **Then:** ARCHITECTURE_DIAGRAMS.md (visual understanding)
3. **Then:** CROSS_DEVICE_SYNC.md (full technical details)
4. **Reference:** QUICK_REFERENCE.md (quick lookups)

---

**Version 1.0** | November 24, 2025 | Production Ready
