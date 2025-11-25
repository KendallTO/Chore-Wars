# Cross-Device Sync - Setup Checklist

## ✅ Deployment Steps

Follow these steps to deploy the cross-device sync feature:

### Step 1: Database Setup
- [ ] Connect to your IONOS database
- [ ] Run the migration script: Visit `https://your-domain.com/api/init_user_data_table.php`
- [ ] OR manually run the SQL from `CROSS_DEVICE_SYNC.md`
- [ ] Verify `user_game_data` table exists in your database

### Step 2: Upload New Files
- [ ] Upload `api/init_user_data_table.php`
- [ ] Upload `api/save_user_data.php`
- [ ] Upload `api/load_user_data.php`

### Step 3: Update Existing Files
- [ ] Replace `api/auth.php` with updated version
- [ ] Replace `index.html` with updated version

### Step 4: Verify Setup
- [ ] Test login on one device
- [ ] Create/edit some game data
- [ ] Logout
- [ ] Login again on the same device
- [ ] Verify data is restored ✓

### Step 5: Test Cross-Device Sync
- [ ] Login on Device 1, add data, logout
- [ ] Login on Device 2 with same account
- [ ] Verify Device 1's data appears ✓

## 📋 File Changes Summary

### New Files Created (3)
1. `api/init_user_data_table.php` - Database table initialization
2. `api/save_user_data.php` - Saves user account data
3. `api/load_user_data.php` - Loads user account data

### Files Modified (2)
1. `api/auth.php` - Returns userData on successful login
2. `index.html` - Added cross-device sync logic:
   - `loadUserData()` - Fetches user data from server
   - `mergeUserDataIfNeeded()` - Restores data if local storage empty
   - `saveUserDataToAccount()` - Saves user data before logout
   - Event listener for `saveUserDataBeforeLogout`

### Documentation Added (2)
1. `CROSS_DEVICE_SYNC.md` - Complete implementation guide
2. `SETUP_CHECKLIST.md` - This file

## 🔧 How It Works (Quick Overview)

1. **Login** → Server loads user's saved data → If local storage empty, restore from server
2. **Gameplay** → Changes saved to localStorage and group state (existing system)
3. **Logout** → User data saved to account before clearing local storage
4. **New Device Login** → Repeat step 1 with restored data

## ⚙️ Configuration

**No configuration needed!** The feature works out-of-the-box with your existing setup.

Optional: Adjust debounce timing in `index.html`:
```javascript
// Line ~350: Change the 500ms timeout if needed
serverSaveTimeoutId = setTimeout(() => {
  saveStateToServer(state);
}, 500);  // Adjust this value (milliseconds)
```

## 🧪 Testing

See `CROSS_DEVICE_SYNC.md` for detailed testing procedures:
- Single device logout/login test
- Cross-device sync test
- New user first login test

## 🚀 Performance Impact

✓ Minimal - All operations are asynchronous and non-blocking
✓ Debounced saves prevent database overload
✓ No impact on regular gameplay

## 🔐 Security Notes

- Only authenticated users can access their data
- Data is tied to user session
- Foreign key constraint prevents orphaned records
- Recommend adding rate limiting for production

## ❓ Common Issues & Solutions

**Issue:** "Not authenticated" error
- Solution: Check that user session is active

**Issue:** Data not restoring on new device
- Solution: Verify `user_game_data` table exists; check browser console for errors

**Issue:** Database errors
- Solution: Check IONOS database credentials in `db.php`

## 📞 Support

Refer to `CROSS_DEVICE_SYNC.md` for troubleshooting and FAQ.

---

**Last Updated:** November 24, 2025
**Deployment Status:** ⏳ Ready for deployment

To complete deployment:
1. Follow steps 1-5 above
2. Test thoroughly on multiple devices
3. Monitor logs for any issues
