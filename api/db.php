<?php
// api/db.php
// Shared PDO connection - do NOT output headers here as it breaks includers

$DB_HOST = 'db5019042997.hosting-data.io';   // e.g. db5019042997.hosting-data.io
$DB_NAME = 'dbs14985870';   // e.g. dbs14985870
$DB_USER = 'dbu5466581';   // e.g. dbu1234567
$DB_PASS = 'passwordfordatabase1';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        $options
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'DB connection failed'
        // For debugging only, you CAN temporarily add:
        // 'details' => $e->getMessage()
    ]);
    exit;
}
