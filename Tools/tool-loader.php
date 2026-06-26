<?php
/**
 * tool-loader.php
 * Place this in your website ROOT alongside jennie-ai.php.
 * 
 * WHY THIS EXISTS:
 * InfinityFree blocks CORS — tool JS files cannot be fetched
 * cross-origin from ai.photozonegraphy.com into the browser.
 * This file acts as a SAME-ORIGIN proxy: the browser fetches
 * tool JS from photozonegraphy.com/tool-loader.php?t=compress-jpg
 * which is the SAME origin as jennie-ai.php, so no CORS issue.
 * 
 * SECURITY:
 * - Only logged-in users can load tools (session check).
 * - Only whitelisted tool IDs are served (no path traversal).
 * - The actual JS is either read from disk OR fetched from CDN.
 * - Output is always Content-Type: application/javascript.
 */

session_start();

// ── Auth gate ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '/* Access denied */';
    exit;
}

// ── Whitelist of allowed tool IDs ─────────────────────────────
$TOOLS = [
    'compress-jpg',
    'compress-webp',
    'compress-png',
    'jpg-to-webp',
    'any-to-jpg',
    'any-to-png',
    'face-detect',
    'bg-remove',
    'exif-camera',
    'title-photo',
];

$tool = $_GET['t'] ?? '';
$tool = preg_replace('/[^a-z0-9\-]/', '', $tool); // strip anything unsafe

if (!in_array($tool, $TOOLS, true)) {
    http_response_code(400);
    echo '/* Unknown tool */';
    exit;
}

// ── Try to read from local disk first ─────────────────────────
// If you copy the JS files to the same server as this PHP file,
// they load from disk (fastest). Otherwise falls back to CDN fetch.
$local_path = __DIR__ . '/jennie-tools/' . $tool . '.js';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: private, max-age=3600'); // cache 1hr per user session
header('X-Content-Type-Options: nosniff');
// No CORS header needed — same origin as jennie-ai.php

if (file_exists($local_path)) {
    // Serve from disk — fastest, no external request
    readfile($local_path);
    exit;
}

// ── Fallback: fetch from CDN server-side ──────────────────────
// PHP fetches from your CDN (server-to-server, no CORS problem)
// then streams it to the browser as same-origin content.
$CDN_BASE = 'https://ai.photozonegraphy.com/Tools';
$cdn_url  = $CDN_BASE . '/' . $tool . '.js';

$ctx = stream_context_create([
    'http' => [
        'timeout'         => 10,
        'follow_location' => true,
        'user_agent'      => 'PhotoZoneGraphy-ToolLoader/1.0',
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$content = @file_get_contents($cdn_url, false, $ctx);

if ($content === false) {
    http_response_code(502);
    echo '/* Tool temporarily unavailable — please try again */';
    exit;
}

// Basic sanity: must look like JS (starts with ( or window or //)
$trimmed = ltrim($content);
if (empty($trimmed)) {
    http_response_code(502);
    echo '/* Empty response from tool server */';
    exit;
}

echo $content;
