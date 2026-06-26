<?php
/**
 * jennie_log.php
 * POST endpoint — writes one row to jennie_history.
 * Called after each successful tool run.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

include "auth/db.php";
include "auth/security.php";

$uid  = (int)$_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true);

$tool_id = clean($body['tool_id'] ?? '');
$label   = clean($body['label']   ?? 'Unknown tool');

// Sanitise label length
$label = mb_substr($label, 0, 120);

if (!$tool_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing tool_id']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO jennie_history (user_id, tool_id, label, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $uid, $tool_id, $label);
$stmt->execute();

echo json_encode(['ok' => true]);
