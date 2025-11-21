# Chore Wars API Documentation

This directory contains the PHP API endpoints for the Chore Wars group functionality.

## Setup Instructions

### 1. Database Setup

Before using these APIs, create the necessary database tables using the SQL below:

```sql
-- Create groups table
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    invite_code VARCHAR(8) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invite_code (invite_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create group_members table
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'member') NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** Make sure you have a `users` table with at least an `id` column before creating the `group_members` table.

### 2. Configure Database Credentials

Edit `db_config.php` and update the following constants with your database information:

```php
define('DB_HOST', 'localhost');              // Your database host
define('DB_NAME', 'your_database_name');     // Your database name
define('DB_USER', 'your_database_user');     // Your database username
define('DB_PASS', 'your_database_pass');     // Your database password
```

### 3. Upload Files via SFTP

Upload the following files to your server:

```
/htdocs/
  └── api/
      ├── db_config.php
      ├── create_group.php
      ├── get_groups.php
      ├── join_group.php
      └── regenerate_invite_code.php
```

### 4. Set File Permissions

Ensure proper permissions (typically 644 for PHP files, 755 for directories):

```bash
chmod 644 api/*.php
chmod 755 api/
```

### 5. Protect Sensitive Files (Optional but Recommended)

Create a `.htaccess` file in the `/api/` directory to prevent direct access to `db_config.php`:

```apache
<Files "db_config.php">
    Order allow,deny
    Deny from all
</Files>
```

## API Endpoints

### 1. Create Group
**Endpoint:** `POST /api/create_group.php`

**Request Body:**
```json
{
  "userId": 123,
  "name": "Smith Family",
  "description": "Our family chore group",
  "inviteCode": "ABC12XYZ"
}
```

**Response (201 Created):**
```json
{
  "id": 1,
  "name": "Smith Family",
  "description": "Our family chore group",
  "inviteCode": "ABC12XYZ",
  "created_at": "2025-11-21 10:30:00"
}
```

### 2. Get Groups for User
**Endpoint:** `GET /api/get_groups.php?userId={userId}`

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Smith Family",
    "description": "Our family chore group",
    "created_at": "2025-11-21 10:30:00",
    "role": "owner",
    "inviteCode": "ABC12XYZ"
  },
  {
    "id": 2,
    "name": "Roommates",
    "description": null,
    "created_at": "2025-11-20 15:00:00",
    "role": "member",
    "inviteCode": null
  }
]
```

**Note:** `inviteCode` is only included if the user is the group owner.

### 3. Join Group by Invite Code
**Endpoint:** `POST /api/join_group.php`

**Request Body:**
```json
{
  "userId": 456,
  "inviteCode": "ABC12XYZ"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "group": {
    "id": 1,
    "name": "Smith Family",
    "description": "Our family chore group"
  }
}
```

**Error Response (400 Bad Request):**
```json
{
  "error": "Invalid invite code. Please check and try again."
}
```

### 4. Regenerate Invite Code
**Endpoint:** `POST /api/regenerate_invite_code.php`

**Request Body:**
```json
{
  "userId": 123,
  "groupId": 1,
  "newInviteCode": "XYZ98ABC"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "inviteCode": "XYZ98ABC"
}
```

## Error Handling

All endpoints return errors in the following format:

```json
{
  "error": "Error message description"
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created (for create_group.php)
- `400` - Bad Request (validation error)
- `401` - Unauthorized
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## Security Considerations

1. **SQL Injection Protection:** All queries use prepared statements with parameter binding
2. **Input Validation:** All inputs are validated and sanitized
3. **Transaction Safety:** Database operations use transactions for data integrity
4. **Error Logging:** Errors are logged server-side without exposing sensitive info to clients
5. **CORS:** Configure CORS headers appropriately for your domain in production

## Testing

You can test the endpoints using tools like:
- Postman
- cURL
- Browser DevTools (for GET requests)

Example cURL command:
```bash
curl -X POST https://your-domain.com/api/create_group.php \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"name":"Test Group","description":"Testing","inviteCode":"TEST1234"}'
```

## Troubleshooting

**Connection failed:**
- Verify database credentials in `db_config.php`
- Ensure database server is running
- Check firewall settings

**Permission denied:**
- Check file permissions (644 for PHP files)
- Verify web server user has read access

**Database errors:**
- Ensure tables are created with correct schema
- Check foreign key constraints (users table must exist)
- Verify character encoding (UTF-8)

## Production Deployment

Before deploying to production:

1. Set `error_reporting(0)` in `db_config.php`
2. Remove or restrict CORS headers to your domain only
3. Enable HTTPS for all API endpoints
4. Set up database backups
5. Monitor error logs regularly
6. Consider rate limiting for API endpoints
