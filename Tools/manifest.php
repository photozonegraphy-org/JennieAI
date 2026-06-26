<?php
/**
 * manifest.php — JennieAI Tool Registry
 *
 * KEY CHANGE: Tools are served via tool-loader.php (same-origin proxy)
 * instead of directly from the CDN. This solves InfinityFree's CORS block.
 * The browser fetches from photozonegraphy.com/tool-loader.php?t=TOOLID
 * which is the same origin as jennie-ai.php — no CORS issue at all.
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

// Rate limit: 120 calls per hour
$rl = $conn->query("SELECT hits, window_start FROM jennie_rate_limit WHERE user_id=$uid LIMIT 1");
$rl_row = $rl ? $rl->fetch_assoc() : null;
if ($rl_row) {
    $age = time() - strtotime($rl_row['window_start']);
    if ($age > 3600) {
        $conn->query("UPDATE jennie_rate_limit SET hits=1, window_start=NOW() WHERE user_id=$uid");
    } elseif ((int)$rl_row['hits'] >= 120) {
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

// Build same-origin tool URLs via tool-loader.php
// This is the fix for InfinityFree CORS block.
$base = 'https://photozonegraphy.com/tool-loader.php?t=';

$tools = [
    'compress-jpg'  => $base . 'compress-jpg',
    'compress-webp' => $base . 'compress-webp',
    'compress-png'  => $base . 'compress-png',
    'jpg-to-webp'   => $base . 'jpg-to-webp',
    'any-to-jpg'    => $base . 'any-to-jpg',
    'any-to-png'    => $base . 'any-to-png',
    'face-detect'   => $base . 'face-detect',
    'bg-remove'     => $base . 'bg-remove',
    'exif-camera'   => $base . 'exif-camera',
    'title-photo'   => $base . 'title-photo',
];

echo json_encode(['version' => '2.1.0', 'tools' => $tools], JSON_UNESCAPED_SLASHES);
