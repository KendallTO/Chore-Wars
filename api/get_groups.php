<?php
/**
 * API Endpoint: Get Groups for User
 * GET /api/get_groups.php?userId={userId}
 * 
 * Retrieves all groups that a user belongs to
 * Returns invite code only if user is the owner
 */

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

// Get and validate userId parameter
if (!isset($_GET['userId']) || empty($_GET['userId'])) {
    sendError('Missing required parameter: userId', 400);
}

$userId = intval($_GET['userId']);

if ($userId <= 0) {
    sendError('Invalid userId', 400);
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed', 500);
}

try {
    // Query to get all groups for this user
    // Join groups with group_members to get user's role
    // Only include invite_code if user is owner
    $stmt = $conn->prepare("
        SELECT 
            g.id,
            g.name,
            g.description,
            g.created_at,
            gm.role,
            CASE 
                WHEN gm.role = 'owner' THEN g.invite_code
                ELSE NULL
            END as inviteCode
        FROM `groups` g
        INNER JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.user_id = ?
        ORDER BY g.created_at DESC
    ");
    
    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch groups: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $groups = [];
    
    while ($row = $result->fetch_assoc()) {
        $groups[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'role' => $row['role'],
            'inviteCode' => $row['inviteCode'] // Will be null for non-owners
        ];
    }
    
    $stmt->close();
    
    // Return groups array (empty array if user has no groups)
    sendJSON($groups, 200);
    
} catch (Exception $e) {
    error_log("Get groups error: " . $e->getMessage());
    sendError($e->getMessage(), 500);
    
} finally {
    closeDBConnection($conn);
}
?>
