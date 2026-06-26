# JennieAI v2.1 — Deploy Guide
# InfinityFree CORS fix included

## WHY THE OLD VERSION FAILED
InfinityFree deliberately blocks CORS headers on their hosting.
Their bot protection intercepts cross-origin requests even when
.htaccess sets the right headers. This is confirmed on their forum.

## THE FIX
Instead of the browser fetching JS from ai.photozonegraphy.com
(cross-origin = blocked), a PHP file called tool-loader.php now
acts as a same-origin proxy. The browser asks photozonegraphy.com
for the JS, the PHP fetches it from the CDN server-to-server
(no CORS involved), and streams it back. Same origin, no block.

---

## FILES TO UPLOAD — website root (photozonegraphy.com)

```
jennie-ai.php              main front page
manifest.php               tool registry (auth-gated, same-origin URLs)
tool-loader.php            same-origin JS proxy (THE CORS FIX)
jennie_deduct_tokens.php   token deduction API
jennie_log.php             history log API
jennie-tools/              folder with all tool JS files (read by PHP)
  .htaccess                blocks direct web access to this folder
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
```

The jennie-tools/ folder is read by PHP internally. Users cannot
access it directly (blocked by .htaccess). No CDN needed at all
if you upload the JS files here — tool-loader.php reads from disk.

---

## STEP 1 — Database

Run schema.sql in phpMyAdmin SQL tab. One paste, one click.
Verify: SHOW TABLES LIKE 'jennie_%';

---

## STEP 2 — Upload files

Upload ALL files listed above to your website root.
Make sure jennie-tools/ is a folder with all 10 .js files
plus the .htaccess inside it.

---

## STEP 3 — Test the console command correctly

Open your site in Chrome, press F12 → Console.
Paste this ENTIRE block at once (all 3 lines together):

fetch('/tool-loader.php?t=compress-jpg',{credentials:'include'})
  .then(r=>r.text())
  .then(t=>console.log(t.slice(0,80)))

You should see the first 80 characters of compress-jpg.js.
If you see a number like "403" — you are not logged in.
If you see "/* Unknown tool */" — the tool name is wrong.
If you see JS code starting with "(function(){" — it works.

---

## STEP 4 — Console test that was failing before

The previous test failed because you pasted 3 lines separately.
The browser console reads each line as a separate command.
Lines starting with .then( have no context so they crash.

WRONG — pasting line by line:
  fetch('/tool-loader.php?t=compress-jpg')   ← pastes OK
  .then(r=>r.text())                          ← CRASHES (no context)

RIGHT — paste all lines at once as one block (copy from line 1
to line 3, paste all at once, press Enter once):
  fetch('/tool-loader.php?t=compress-jpg',{credentials:'include'})
    .then(r=>r.text())
    .then(t=>console.log(t.slice(0,80)))

---

## AI tools and what they use (all free, unlimited)

  bg-remove     @imgly/background-removal  (WASM, loads from jsDelivr CDN)
  face-detect   face-api.js + vladmandic   (loads from jsDelivr CDN)
  exif-camera   ExifReader                  (loads from jsDelivr CDN)
  title-photo   Canvas API                  (built into browser, no CDN)
  All image     Canvas API                  (built into browser, no CDN)

The external AI libraries (bg-remove, face-detect, exif-camera) are
loaded by the tool JS files themselves at runtime from jsDelivr CDN.
jsDelivr is a public CDN with no CORS restrictions — browsers can
always fetch from it. These are separate from your tool-loader proxy.

The AI model files (neural networks) download on first use and are
cached by the browser. bg-remove downloads ~10MB once then is instant.
face-detect downloads ~3MB once then is instant. Users will not see
raw loading — the processing overlay covers this with "Initialising
neural network…" and similar messages.

---

## Admin panel

Upload admin-jennie-tokens.php to /admin/ on your site.
Your existing admin auth must set $_SESSION['is_admin'] = 1.
