# Group Separation System

## Overview
Your Chore Wars application implements **complete data isolation** between different groups. Each group operates independently with its own game data, shop items, and player information.

## How Group Separation Works

### 1. **Unique Group IDs**
- Each group gets a unique numeric ID when created (stored in the `groups` database table)
- This ID is used to namespace all data for that group
- Example: Group "Smith Family" might have ID `42`

### 2. **LocalStorage Separation**
All data stored in the browser uses the group ID as part of the storage key:

```javascript
// Chore data for group 42
tcg_chores_v1_42

// Points data for group 42
tcg_points_v1_42

// Shop items for group 42
tcg_shop_items_v1_42

// Player inventories for group 42
tcg_player_inventories_v1_42

// And more...
```

This means:
- ✅ Different groups never share data
- ✅ Switching groups loads completely different data
- ✅ Each group maintains its own state independently

### 3. **Server-Side Database Separation**
The backend database also enforces separation:

**game_state table:**
```sql
group_id | data_json          | updated_at
---------|-------------------|------------
42       | {...chore data...} | 2025-11-25
43       | {...chore data...} | 2025-11-25
```

**shop_state table:**
```sql
group_id | data_json          | updated_at
---------|-------------------|------------
42       | {...shop data...}  | 2025-11-25
43       | {...shop data...}  | 2025-11-25
```

### 4. **Visual Group Indicator**
Both the index.html and shop.html pages now display a visual indicator showing:
- **Group Name**: The friendly name you gave the group
- **Group ID**: The unique numeric identifier

This appears in the header below the logo, making it crystal clear which group you're currently working in.

## Data Flow Example

**When you enter "Smith Family" (ID: 42):**
1. System sets `tcg_current_group_v1 = "42"` in localStorage
2. All subsequent reads/writes use group ID 42
3. Index page loads: `tcg_chores_v1_42`, `tcg_points_v1_42`, etc.
4. Shop page loads: `tcg_shop_items_v1_42`, `tcg_player_inventories_v1_42`, etc.
5. Auto-save sends data to server tagged with `group_id: 42`

**When you switch to "Johnson Family" (ID: 43):**
1. System updates `tcg_current_group_v1 = "43"`
2. All data now uses group ID 43 keys
3. Completely different chores, points, shop items load
4. Smith Family data remains untouched

## Auto-Save System

Your game data automatically saves in THREE ways:

1. **Immediate (Debounced)**: Changes save 500ms after you make them
2. **Periodic**: Every 30 seconds, all data syncs to server
3. **On Navigation**: When you refresh or close the page

This means:
- ✅ You never need to log out to save data
- ✅ You can refresh anytime without losing data
- ✅ Data persists across devices (when logged in)
- ✅ All saves are group-specific and isolated

## Security & Isolation

### What's Separated:
- ✅ Chores and assignments
- ✅ Player points/allowances
- ✅ Player names and positions
- ✅ Shop items and prices
- ✅ Player inventories (purchased items)
- ✅ All game settings and state

### What's Shared:
- User accounts (you can be in multiple groups)
- Group membership (you can switch between your groups)

## Technical Details

### Storage Keys by Feature:

**Index Page (Chores & Game):**
- `tcg_chores_v1_${groupId}` - All chore data
- `tcg_points_v1_${groupId}` - Player points
- `tcg_column_headers_v1_${groupId}` - Player names
- `tcg_extra_cols_v1_${groupId}` - Extra players data
- `tcg_player_positions_v1_${groupId}` - Player order

**Shop Page:**
- `tcg_shop_items_v1_${groupId}` - Shop inventory
- `tcg_player_inventories_v1_${groupId}` - What players bought

**Global (Not Group-Specific):**
- `tcg_session_v1` - Your login session
- `tcg_current_group_v1` - Which group you're currently in

### API Endpoints:
- `save_game_state.php` - Saves game data for a specific group
- `get_game_state.php` - Loads game data for a specific group
- `save_shop_state.php` - Saves shop data for a specific group
- `get_shop_state.php` - Loads shop data for a specific group

All endpoints require `groupId` parameter to ensure data goes to the right place.

## Summary

Your group separation is **comprehensive and secure**. Each group operates in complete isolation from others, both in the browser and on the server. The visual indicator in the header makes it impossible to lose track of which group you're working in.

**No cross-contamination is possible** - each group's data is kept separate at every level of the application.
