<?php
/**
 * jennie_deduct_tokens.php
 * POST endpoint — deducts tokens from the user's jennie_tokens row.
 * Called by jennie-ai.php JavaScript after a tool finishes.
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
$cost = max(1, (int)($body['cost'] ?? 1));
$tool = clean($body['tool'] ?? '');

// Allowed tool IDs — whitelist so nobody can send arbitrary cost
$ALLOWED_TOOLS = [
    'compress-jpg'  => 4,
    'compress-webp' => 4,
    'compress-png'  => 5,
    'jpg-to-webp'   => 3,
    'any-to-jpg'    => 3,
    'any-to-png'    => 5,
    'title-photo'   => 6,
    'title-seo'     => 8,
    'title-social'  => 7,
];

// Always use the server-side cost — never trust the client's claimed cost
if (!isset($ALLOWED_TOOLS[$tool])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown tool']);
    exit;
}
$server_cost = $ALLOWED_TOOLS[$tool];

// Get current token row
$stmt = $conn->prepare("SELECT tokens_left, tokens_max, reset_at FROM jennie_tokens WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Token record not found']);
    exit;
}

// Auto-reset if expired
if (strtotime($row['reset_at']) <= time()) {
    $new_reset = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $upd = $conn->prepare("UPDATE jennie_tokens SET tokens_left = tokens_max, reset_at = ?, updated_at = NOW() WHERE user_id = ?");
    $upd->bind_param("si", $new_reset, $uid);
    $upd->execute();
    $row['tokens_left'] = $row['tokens_max'];
    $row['reset_at']    = $new_reset;
}

$current = (int)$row['tokens_left'];

if ($current < $server_cost) {
    http_response_code(402);
    echo json_encode(['error' => 'Insufficient tokens', 'tokens_left' => $current]);
    exit;
}

$new_left = $current - $server_cost;
$upd = $conn->prepare("UPDATE jennie_tokens SET tokens_left = ?, updated_at = NOW() WHERE user_id = ?");
$upd->bind_param("ii", $new_left, $uid);
$upd->execute();

echo json_encode([
    'ok'          => true,
    'tokens_left' => $new_left,
    'cost'        => $server_cost,
    'tool'        => $tool,
]);
