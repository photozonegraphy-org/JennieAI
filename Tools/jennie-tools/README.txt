OPTIONAL — Disk-based tool serving (faster than CDN fetch)
============================================================

If you copy all the .js tool files into this folder
(jennie-tools/ next to tool-loader.php), then tool-loader.php
will serve them from disk instead of fetching from the CDN.

Disk serving is instant. CDN fallback adds ~200-500ms.

Files to copy here:
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

This folder must NOT be web-accessible directly.
Add a .htaccess to deny direct HTTP access:

  Deny from all

tool-loader.php reads these files via PHP (file_get_contents),
not via a URL, so they never need CORS headers when served this way.
