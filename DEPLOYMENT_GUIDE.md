# Deployment Guide for Chore Wars Group Invite System

## Files to Upload via SFTP

Upload these files to your web server:

### 1. API Files (upload to `/htdocs/api/` directory)
```
api/
├── db_config.php              # Database configuration
├── create_group.php           # Create new groups
├── get_groups.php             # Get user's groups
├── join_group.php             # Join group by invite code
├── regenerate_invite_code.php # Regenerate group invite codes
├── database_setup.sql         # Database table creation script
└── README.md                  # API documentation
```

### 2. Updated HTML File (upload to `/htdocs/` directory)
```
chore_wars/groups.html         # Updated groups page with invite functionality
```

## Step-by-Step Deployment

### Step 1: Database Setup

1. **Login to cPanel**
   - Navigate to MySQL Databases or phpMyAdmin

2. **Create Database** (if not already created)
   - Create a new database (e.g., `chorewars_db`)
   - Create a database user
   - Grant all privileges to the user

3. **Run SQL Setup**
   - Open phpMyAdmin
   - Select your database
   - Click "SQL" tab
   - Copy and paste contents of `api/database_setup.sql`
   - Click "Go" to execute

4. **Verify Tables Created**
   ```sql
   SHOW TABLES;
   -- Should show: groups, group_members
   ```

### Step 2: Configure Database Connection

1. **Edit `api/db_config.php`**
   
   Replace these values with your actual credentials:
   ```php
   define('DB_HOST', 'localhost');           // Usually localhost
   define('DB_NAME', 'your_database_name');  // Your DB name from cPanel
   define('DB_USER', 'your_database_user');  // Your DB username
   define('DB_PASS', 'your_database_pass');  // Your DB password
   ```

2. **Find Your Database Credentials**
   - In cPanel → MySQL Databases
   - Database name is shown in "Current Databases"
   - Username is shown in "Current Users"
   - Password: Use the one you created (or create a new user)

### Step 3: Upload Files via SFTP

**Using FileZilla or similar SFTP client:**

1. **Connect to your server**
   - Host: `access-XXXXXXXXXX.webspace-host.com`
   - Port: `22`
   - Protocol: SFTP
   - Use your cPanel credentials

2. **Create API directory**
   ```
   Navigate to: /htdocs/
   Create new folder: api
   ```

3. **Upload API files**
   - Upload all files from local `api/` folder
   - To remote `/htdocs/api/` folder

4. **Upload groups.html**
   - Upload `chore_wars/groups.html`
   - To remote `/htdocs/chore_wars/groups.html`

### Step 4: Set File Permissions

**In your SFTP client or cPanel File Manager:**

```
api/ directory          → 755 (rwxr-xr-x)
api/db_config.php       → 644 (rw-r--r--)
api/create_group.php    → 644 (rw-r--r--)
api/get_groups.php      → 644 (rw-r--r--)
api/join_group.php      → 644 (rw-r--r--)
api/regenerate_invite_code.php → 644 (rw-r--r--)
```

### Step 5: Security (Optional but Recommended)

**Create `.htaccess` in `/htdocs/api/` directory:**

```apache
# Prevent direct access to config file
<Files "db_config.php">
    Order allow,deny
    Deny from all
</Files>

# Enable error logging
php_flag display_errors Off
php_flag log_errors On

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
```

### Step 6: Testing

1. **Test Database Connection**
   - Create a test file: `/htdocs/api/test_connection.php`
   ```php
   <?php
   require_once 'db_config.php';
   $conn = getDBConnection();
   if ($conn) {
       echo "✓ Database connection successful!";
       closeDBConnection($conn);
   } else {
       echo "✗ Database connection failed!";
   }
   ?>
   ```
   - Visit: `https://your-domain.com/api/test_connection.php`
   - Should see: "✓ Database connection successful!"
   - **DELETE this file after testing!**

2. **Test Group Creation**
   - Open your website
   - Navigate to groups.html
   - Try creating a new group
   - Check if invite code appears

3. **Test Group Joining**
   - Copy an invite code from a group you own
   - Click "Join Group"
   - Enter the code
   - Verify you can join

4. **Check Browser Console**
   - Press F12 to open DevTools
   - Check Console tab for any errors
   - Check Network tab to see API requests

## Troubleshooting

### Problem: "Database connection failed"
**Solution:**
- Verify credentials in `db_config.php`
- Check if database exists in cPanel
- Ensure user has privileges on the database

### Problem: "404 Not Found" on API calls
**Solution:**
- Verify `/htdocs/api/` directory exists
- Check file names are exactly: `create_group.php`, etc.
- Ensure files have read permissions (644)

### Problem: "Invalid invite code already exists"
**Solution:**
- This is normal if code collision occurs
- JavaScript will regenerate automatically
- Just click create again

### Problem: No errors but groups don't appear
**Solution:**
- Check browser console for JavaScript errors
- Verify userId is stored in localStorage
- Check Network tab for API response
- Verify `get_groups.php` is returning data

### Problem: SQL foreign key errors
**Solution:**
- Ensure `users` table exists before creating `group_members`
- Add a simple users table:
  ```sql
  CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE
  );
  ```

## File Structure on Server

After deployment, your server should look like this:

```
/htdocs/
├── api/
│   ├── db_config.php
│   ├── create_group.php
│   ├── get_groups.php
│   ├── join_group.php
│   ├── regenerate_invite_code.php
│   ├── database_setup.sql
│   ├── README.md
│   └── .htaccess (optional)
├── chore_wars/
│   ├── groups.html (UPDATED)
│   ├── index.html
│   ├── login.html
│   ├── styles/
│   └── images/
└── ... (other files)
```

## Production Checklist

Before going live:

- [ ] Database tables created
- [ ] Database credentials configured in `db_config.php`
- [ ] All PHP files uploaded to `/htdocs/api/`
- [ ] File permissions set correctly (755 for dirs, 644 for files)
- [ ] Test database connection
- [ ] Test creating a group
- [ ] Test joining a group with invite code
- [ ] Test regenerating invite code
- [ ] Delete any test files (like `test_connection.php`)
- [ ] Set `error_reporting(0)` in `db_config.php` for production
- [ ] Configure CORS properly (restrict to your domain)
- [ ] Enable HTTPS (SSL certificate)
- [ ] Set up regular database backups

## Support

If you encounter issues:

1. Check PHP error logs (usually in cPanel → Errors)
2. Check browser console for JavaScript errors
3. Verify all API endpoints return proper JSON
4. Test database queries directly in phpMyAdmin
5. Ensure users table exists with proper structure

## Next Steps

After successful deployment:

1. Test all functionality thoroughly
2. Create a few test groups
3. Invite other test users
4. Verify invite codes work correctly
5. Test on mobile devices
6. Monitor error logs for issues

Good luck with your deployment! 🚀
