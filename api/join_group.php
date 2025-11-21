<?php
/**
 * API Endpoint: Join Group by Invite Code
 * POST /api/join_group.php
 * 
 * Allows a user to join a group using an invite code
 * Validates that the code exists and user isn't already a member
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

// Validate input
if (!isset($input['userId']) || !isset($input['inviteCode'])) {
    sendError('Missing required fields: userId, inviteCode', 400);
}

$userId = intval($input['userId']);
$inviteCode = strtoupper(trim($input['inviteCode']));

// Validate data
if ($userId <= 0) {
    sendError('Invalid userId', 400);
}

if (strlen($inviteCode) !== 8) {
    sendError('Invalid invite code format', 400);
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed', 500);
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Find the group by invite code
    $findGroupStmt = $conn->prepare("SELECT id, name, description FROM `groups` WHERE invite_code = ?");
    $findGroupStmt->bind_param("s", $inviteCode);
    $findGroupStmt->execute();
    $groupResult = $findGroupStmt->get_result();
    
    if ($groupResult->num_rows === 0) {
        $findGroupStmt->close();
        throw new Exception('Invalid invite code. Please check and try again.');
    }
    
    $group = $groupResult->fetch_assoc();
    $groupId = intval($group['id']);
    $findGroupStmt->close();
    
    // 2. Check if user is already a member of this group
    $checkMemberStmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $checkMemberStmt->bind_param("ii", $groupId, $userId);
    $checkMemberStmt->execute();
    $memberResult = $checkMemberStmt->get_result();
    
    if ($memberResult->num_rows > 0) {
        $checkMemberStmt->close();
        throw new Exception('You are already a member of this group.');
    }
    $checkMemberStmt->close();
    
    // 3. Add user as a member (not owner)
    $insertMemberStmt = $conn->prepare(
        "INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())"
    );
    $insertMemberStmt->bind_param("ii", $groupId, $userId);
    
    if (!$insertMemberStmt->execute()) {
        throw new Exception('Failed to join group: ' . $insertMemberStmt->error);
    }
    $insertMemberStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success with group details
    sendJSON([
        'success' => true,
        'group' => [
            'id' => $groupId,
            'name' => $group['name'],
            'description' => $group['description']
        ]
    ], 200);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Join group error: " . $e->getMessage());
    sendError($e->getMessage(), 400);
    
} finally {
    closeDBConnection($conn);
}
?>
