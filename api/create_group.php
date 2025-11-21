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
// Invite code is optional (null until owner generates one)
$inviteCode = null;

// Validate data
if (empty($groupName)) {
    sendError('Group name cannot be empty', 400);
}

// No invite code length validation here (none provided at creation)

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed', 500);
}

// Start transaction for data integrity
$conn->begin_transaction();

try {
        // Insert into groups table WITHOUT invite code (nullable field)
        $insertGroupStmt = $conn->prepare(
            "INSERT INTO groups (name, description, invite_code, created_at) VALUES (?, ?, NULL, NOW())"
        );
        $insertGroupStmt->bind_param("ss", $groupName, $description);
    
    if (!$insertGroupStmt->execute()) {
        throw new Exception('Failed to create group: ' . $insertGroupStmt->error);
    }
    
    $groupId = $conn->insert_id;
    $insertGroupStmt->close();
    
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
        'inviteCode' => null,
        'created_at' => date('Y-m-d H:i:s')
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
