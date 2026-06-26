<?php
/**
 * manifest.php — JennieAI Tool Registry (Secured)
 *
 * SECURITY: Only authenticated users receive this.
 * The CDN URLs themselves are not secrets — they point to
 * public JS files. The security is in the token system
 * on jennie_deduct_tokens.php, which never runs without a session.
 */
session_start();
include "auth/db.php";
include "auth/security.php";

// Auth gate — no session = no manifest
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Rate limit: 60 calls per hour per user
$rl = $conn->prepare("SELECT hits, window_start FROM jennie_rate_limit WHERE user_id = ? LIMIT 1");
$rl->bind_param("i", $uid);
$rl->execute();
$rl_row = $rl->get_result()->fetch_assoc();
if ($rl_row) {
    if ((time() - strtotime($rl_row['window_start'])) > 3600) {
        $conn->prepare("UPDATE jennie_rate_limit SET hits=1, window_start=NOW() WHERE user_id=?")->bind_param("i",$uid);
        $conn->query("UPDATE jennie_rate_limit SET hits=1, window_start=NOW() WHERE user_id=$uid");
    } elseif ((int)$rl_row['hits'] >= 60) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests.']);
        exit;
    } else {
        $conn->query("UPDATE jennie_rate_limit SET hits=hits+1 WHERE user_id=$uid");
    }
} else {
    $conn->query("INSERT INTO jennie_rate_limit (user_id,hits,window_start) VALUES($uid,1,NOW())");
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
// Replace with your actual domain
header('Access-Control-Allow-Origin: https://photozonegraphy.com');
header('Access-Control-Allow-Credentials: true');

$CDN_BASE = 'https://cdn.photozonegraphy.com/jennieai/tools';

$tools = [
    'compress-jpg'  => $CDN_BASE . '/compress-jpg.js',
    'compress-webp' => $CDN_BASE . '/compress-webp.js',
    'compress-png'  => $CDN_BASE . '/compress-png.js',
    'jpg-to-webp'   => $CDN_BASE . '/jpg-to-webp.js',
    'any-to-jpg'    => $CDN_BASE . '/any-to-jpg.js',
    'any-to-png'    => $CDN_BASE . '/any-to-png.js',
    'title-photo'   => $CDN_BASE . '/title-photo.js',
    'title-seo'     => $CDN_BASE . '/title-seo.js',
    'title-social'  => $CDN_BASE . '/title-social.js',
];

echo json_encode(['version' => '1.1.0', 'tools' => $tools], JSON_UNESCAPED_SLASHES);
