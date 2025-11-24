<?php
/**
 * API Endpoint: Regenerate Invite Code
 * POST /api/regenerate_invite_code.php
 * 
 * Allows a group owner to generate a new invite code
 * Validates ownership and ensures code uniqueness
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
if (!isset($input['userId']) || !isset($input['groupId']) || !isset($input['newInviteCode'])) {
    sendError('Missing required fields: userId, groupId, newInviteCode', 400);
}

$userId = intval($input['userId']);
$groupId = intval($input['groupId']);
$newInviteCode = strtoupper(trim($input['newInviteCode']));

// Validate data
if ($userId <= 0 || $groupId <= 0) {
    sendError('Invalid userId or groupId', 400);
}

if (strlen($newInviteCode) !== 8) {
    sendError('Invite code must be 8 characters', 400);
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed', 500);
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Verify user is the owner of this group
    $checkOwnerStmt = $conn->prepare(
        "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?"
    );
    $checkOwnerStmt->bind_param("ii", $groupId, $userId);
    $checkOwnerStmt->execute();
    $ownerResult = $checkOwnerStmt->get_result();
    
    if ($ownerResult->num_rows === 0) {
        $checkOwnerStmt->close();
        throw new Exception('You are not a member of this group.');
    }
    
    $memberData = $ownerResult->fetch_assoc();
    $checkOwnerStmt->close();
    
    if ($memberData['role'] !== 'owner') {
        throw new Exception('Only the group owner can regenerate the invite code.');
    }
    
    // 2. Check if new invite code is already in use by another group
    $checkCodeStmt = $conn->prepare("SELECT id FROM `groups` WHERE invite_code = ? AND id != ?");
    $checkCodeStmt->bind_param("si", $newInviteCode, $groupId);
    $checkCodeStmt->execute();
    $codeResult = $checkCodeStmt->get_result();
    
    if ($codeResult->num_rows > 0) {
        $checkCodeStmt->close();
        throw new Exception('This invite code is already in use. Please try again.');
    }
    $checkCodeStmt->close();
    
    // 3. Update the group's invite code
    $updateStmt = $conn->prepare("UPDATE `groups` SET invite_code = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newInviteCode, $groupId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update invite code: ' . $updateStmt->error);
    }
    $updateStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success with new code
    sendJSON([
        'success' => true,
        'inviteCode' => $newInviteCode
    ], 200);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Regenerate invite code error: " . $e->getMessage());
    sendError($e->getMessage(), 400);
    
} finally {
    closeDBConnection($conn);
}
?>
