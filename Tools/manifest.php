<?php
/**
 * manifest.php — JennieAI Tool Registry (Secured)
 * Only authenticated users receive this. Cache-Control: no-store.
 * CDN base: https://ai.photozonegraphy.com/Tools/
 */
session_start();
include "auth/db.php";
include "auth/security.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Simple rate limit — max 120 manifest calls per hour per user
$rl_result = $conn->query("SELECT hits, window_start FROM jennie_rate_limit WHERE user_id=$uid LIMIT 1");
$rl_row = $rl_result ? $rl_result->fetch_assoc() : null;
if ($rl_row) {
    $age = time() - strtotime($rl_row['window_start']);
    if ($age > 3600) {
        $conn->query("UPDATE jennie_rate_limit SET hits=1, window_start=NOW() WHERE user_id=$uid");
    } elseif ((int)$rl_row['hits'] >= 120) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait.']);
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
header('Access-Control-Allow-Origin: https://photozonegraphy.com');
header('Access-Control-Allow-Credentials: true');

// Your CDN base — all tool JS files live here
$CDN = 'https://ai.photozonegraphy.com/Tools';

$tools = [
    // Compression
    'compress-jpg'   => $CDN . '/compress-jpg.js',
    'compress-webp'  => $CDN . '/compress-webp.js',
    'compress-png'   => $CDN . '/compress-png.js',
    // Format conversion
    'jpg-to-webp'    => $CDN . '/jpg-to-webp.js',
    'any-to-jpg'     => $CDN . '/any-to-jpg.js',
    'any-to-png'     => $CDN . '/any-to-png.js',
    // AI analysis
    'face-detect'    => $CDN . '/face-detect.js',
    'bg-remove'      => $CDN . '/bg-remove.js',
    'exif-camera'    => $CDN . '/exif-camera.js',
    // Title generator
    'title-photo'    => $CDN . '/title-photo.js',
];

echo json_encode(['version' => '2.0.0', 'tools' => $tools], JSON_UNESCAPED_SLASHES);
