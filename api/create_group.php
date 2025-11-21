<?php
/**
 * API Endpoint: Create Group
 * POST /api/create_group.php
 * 
 * Creates a new group with the authenticated user as owner.
 * NOTE: No invite code is generated at creation time. Owners can
 * later generate an invite code via regenerate_invite_code.php.
 */

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input (inviteCode intentionally NOT required)
if (!isset($input['userId']) || !isset($input['name'])) {
    sendError('Missing required fields: userId, name', 400);
}

$userId = intval($input['userId']);
$groupName = trim($input['name']);
$description = isset($input['description']) ? trim($input['description']) : '';
// Invite code is optional (null until owner generates one). Some legacy
// deployments may still have NOT NULL constraint; we will fallback.
$inviteCode = null;

// Validate data
if (empty($groupName)) {
    sendError('Group name cannot be empty', 400);
}
if ($userId <= 0) {
    sendError('Invalid userId', 400);
}

// No invite code length validation here (none provided at creation)

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed', 500);
}

// Start transaction for data integrity
// Verify user exists (defensive; legacy ensure_user.php deprecated; unified auth sets session)
$userCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$userCheckStmt->bind_param("i", $userId);
if (!$userCheckStmt->execute()) {
    $err = $userCheckStmt->error;
    $userCheckStmt->close();
    sendError('User validation failed: ' . $err, 500);
}
$resUser = $userCheckStmt->get_result();
if ($resUser->num_rows === 0) {
    $userCheckStmt->close();
    sendError('User does not exist', 400);
}
$userCheckStmt->close();

$conn->begin_transaction();

try {
    // Helper to generate an invite code if required by old schema
    function generateInviteCode() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    // Attempt 1: insert with NULL invite_code (modern schema)
    $insertGroupStmt = $conn->prepare(
        "INSERT INTO `groups` (name, description, invite_code, created_at) VALUES (?, ?, NULL, NOW())"
    );
    $insertGroupStmt->bind_param("ss", $groupName, $description);
    $execOk = $insertGroupStmt->execute();
    $errorMsg = $insertGroupStmt->error;

    if (!$execOk) {
        // Check if failure likely due to NOT NULL constraint or unknown column
        // Error codes: 1048 (Column cannot be null), 1364 (Field doesn't have a default value)
        $errno = $insertGroupStmt->errno;
        $insertGroupStmt->close();
        if (in_array($errno, [1048, 1364])) {
            // Fallback: generate unique invite code and retry
            $attempts = 0;
            do {
                $inviteCode = generateInviteCode();
                $check = $conn->prepare("SELECT id FROM `groups` WHERE invite_code = ? LIMIT 1");
                $check->bind_param("s", $inviteCode);
                $check->execute();
                $resCheck = $check->get_result();
                $exists = $resCheck->num_rows > 0;
                $check->close();
                $attempts++;
            } while ($exists && $attempts < 5);
            if ($exists) {
                throw new Exception('Failed to generate unique invite code after multiple attempts.');
            }
            $fallbackStmt = $conn->prepare(
                "INSERT INTO `groups` (name, description, invite_code, created_at) VALUES (?, ?, ?, NOW())"
            );
            $fallbackStmt->bind_param("sss", $groupName, $description, $inviteCode);
            if (!$fallbackStmt->execute()) {
                $errDetail = $fallbackStmt->error;
                $fallbackStmt->close();
                throw new Exception('Failed to create group (legacy schema fallback): ' . $errDetail);
            }
            $groupId = $conn->insert_id;
            $fallbackStmt->close();
        } else {
            throw new Exception('Failed to create group: ' . $errorMsg);
        }
    } else {
        $groupId = $conn->insert_id;
        $insertGroupStmt->close();
    }
    
    // Insert user as owner in group_members table
    $insertMemberStmt = $conn->prepare(
        "INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'owner', NOW())"
    );
    $insertMemberStmt->bind_param("ii", $groupId, $userId);
    
    if (!$insertMemberStmt->execute()) {
        throw new Exception('Failed to add user as owner: ' . $insertMemberStmt->error);
    }
    $insertMemberStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    sendJSON([
        'id' => $groupId,
        'name' => $groupName,
        'description' => $description,
        'inviteCode' => $inviteCode, // null on modern schema, code on legacy fallback
        'created_at' => date('Y-m-d H:i:s'),
        'fallbackUsed' => $inviteCode !== null
    ], 201);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Create group error: " . $e->getMessage());
    sendError($e->getMessage(), 500);
    
} finally {
    closeDBConnection($conn);
}
?>
