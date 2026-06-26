# JennieAI — Deploy Checklist

## Files overview

### Your website root (photozonegraphy.com)
```
jennie-ai.php              ← main front page
manifest.php               ← tool registry (auth-gated)
jennie_deduct_tokens.php   ← token deduction API
jennie_log.php             ← history log API
```

### Your CDN root (ai.photozonegraphy.com/Tools/)
```
compress-jpg.js
compress-webp.js
compress-png.js
jpg-to-webp.js
any-to-jpg.js
any-to-png.js
face-detect.js
bg-remove.js
exif-camera.js
title-photo.js
.htaccess   ← rename tools-htaccess.txt to .htaccess
```

---

## Step 1 — Database

Run `schema.sql` in phpMyAdmin (SQL tab).
Verify with: `SHOW TABLES LIKE 'jennie_%';`
You should see 5 tables: jennie_tokens, jennie_history,
jennie_rate_limit, jennie_admin_overrides, jennie_tool_stats.

---

## Step 2 — CDN (ai.photozonegraphy.com)

1. Upload all 10 `.js` files to `/Tools/` folder on your CDN.
2. Rename `tools-htaccess.txt` to `.htaccess` and upload it
   to the `/Tools/` folder too.
3. Test CORS: open browser console on photozonegraphy.com and run:
   fetch('https://ai.photozonegraphy.com/Tools/compress-jpg.js')
     .then(r => r.text()).then(t => console.log(t.slice(0,80)))
   Should print the JS code without a CORS error.

---

## Step 3 — Website root files

Upload to your website root:
- jennie-ai.php
- manifest.php
- jennie_deduct_tokens.php
- jennie_log.php

---

## Step 4 — Verify the fix for "Could not reach JennieAI"

The old bug was: tools were loaded via <script src="..."> which
browsers block cross-origin. The new code uses fetch() as text
then executes with new Function(). This bypasses the issue as
long as your CDN returns Access-Control-Allow-Origin: * on the
/Tools/ folder (which the .htaccess above does).

If you still see errors, check:
  1. Is .htaccess in the /Tools/ folder on the CDN?
  2. Does your CDN server have mod_headers enabled?
  3. Try opening https://ai.photozonegraphy.com/Tools/compress-jpg.js
     in a browser — you should see the JS code.

---

## AI tools — what they use (all free, all browser-based)

| Tool          | Library                              | Size   |
|---------------|--------------------------------------|--------|
| bg-remove     | @imgly/background-removal (WASM)     | ~10MB (one-time download, cached) |
| face-detect   | face-api.js + vladmandic models      | ~3MB (one-time, cached) |
| exif-camera   | ExifReader (pure JS)                 | ~80KB  |
| title-photo   | Canvas API (built-in)                | 0 KB   |
| All image     | Canvas API (built-in)                | 0 KB   |

The AI model downloads happen on first use only — the browser
caches them so subsequent uses are instant.

The bg-remove first run note ("~15s") is already shown in the
processing overlay so users are not confused.

---

## Admin panel

Upload `admin-jennie-tokens.php` to your `/admin/` folder.
Access at: yourdomain.com/admin/admin-jennie-tokens.php

Make sure your existing admin auth sets:
  $_SESSION['is_admin'] = 1;
for admin users, since the panel checks that.
