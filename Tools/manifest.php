<?php
/**
 * JennieAI — Tool Manifest
 * ─────────────────────────────────────────────────────────────
 * This file lives in your website root alongside jennie-ai.php.
 * It returns a JSON map of tool IDs → Cloudflare CDN URLs.
 *
 * The frontend fetches this once, caches it in memory,
 * then only downloads the specific tool file the user needs.
 *
 * HOW TO USE:
 *  1. Upload each tool JS file to your Cloudflare CDN / R2 bucket.
 *  2. Replace the placeholder CDN URLs below with your real URLs.
 *  3. Done. Frontend handles everything automatically.
 *
 * ADDING A NEW TOOL:
 *  - Upload the tool JS to Cloudflare.
 *  - Add its ID and URL here.
 *  - Add its button in jennie-ai.php.
 *  - The tool JS must call: window.JennieTools['tool-id'] = async (file, opts) => result;
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // cache manifest 5 min in browser
header('Access-Control-Allow-Origin: *');

/* ─────────────────────────────────────────────────────────────
   REPLACE these URLs with your actual Cloudflare CDN URLs.
   Format: https://yourzone.r2.dev/jennieai/tools/FILENAME.js
           or https://cdn.yourdomain.com/jennieai/FILENAME.js
   ───────────────────────────────────────────────────────────── */
$CDN_BASE = 'https://cdn.yourdomain.com/jennieai/tools'; // ← change this

$tools = [

  /* ── Image Compression ── */
  'compress-jpg'  => $CDN_BASE . '/compress-jpg.js',
  'compress-webp' => $CDN_BASE . '/compress-webp.js',
  'compress-png'  => $CDN_BASE . '/compress-png.js',

  /* ── Format Conversion ── */
  'jpg-to-webp'   => $CDN_BASE . '/jpg-to-webp.js',
  'any-to-jpg'    => $CDN_BASE . '/any-to-jpg.js',
  'any-to-png'    => $CDN_BASE . '/any-to-png.js',

  /* ── Title / Text Tools ── */
  'title-photo'   => $CDN_BASE . '/title-photo.js',
  'title-seo'     => $CDN_BASE . '/title-seo.js',
  'title-social'  => $CDN_BASE . '/title-social.js',

  /* ── (Reserved slots for future tools) ── */
  // 'resize-image'     => $CDN_BASE . '/resize-image.js',
  // 'remove-bg'        => $CDN_BASE . '/remove-bg.js',
  // 'exif-reader'      => $CDN_BASE . '/exif-reader.js',
  // 'watermark'        => $CDN_BASE . '/watermark.js',
  // 'grayscale'        => $CDN_BASE . '/grayscale.js',
  // 'blur-background'  => $CDN_BASE . '/blur-background.js',
  // 'image-crop'       => $CDN_BASE . '/image-crop.js',
  // 'metadata-strip'   => $CDN_BASE . '/metadata-strip.js',
  // 'color-correct'    => $CDN_BASE . '/color-correct.js',
  // 'batch-rename'     => $CDN_BASE . '/batch-rename.js',
];

echo json_encode([
  'version' => '1.0.0',
  'updated' => '2025-06-01',
  'tools'   => $tools,
], JSON_PRETTY_PRINT);
?>
