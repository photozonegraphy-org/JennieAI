# JennieAI — Deployment Guide
================================================

## File Structure

```
YOUR WEBSITE ROOT/
├── jennie-ai.php          ← Front page (UI + orchestrator)
└── manifest.php           ← Tool registry (returns CDN URLs as JSON)

YOUR CLOUDFLARE CDN BUCKET/
└── jennieai/tools/
    ├── compress-jpg.js
    ├── compress-webp.js
    ├── compress-png.js
    ├── jpg-to-webp.js
    ├── any-to-jpg.js
    ├── any-to-png.js
    ├── title-photo.js
    ├── title-seo.js
    └── title-social.js
```

Only 2 files go in your website root.
All tool JS files go on Cloudflare CDN.

---

## Step 1 — Upload tool JS files to Cloudflare

1. Go to your Cloudflare dashboard.
2. Open R2 Storage (or Pages / Workers KV, depending on your setup).
3. Create a bucket (e.g. `jennieai`) if you don't have one.
4. Upload ALL the .js tool files into a folder: `jennieai/tools/`
5. Make the bucket/folder PUBLIC so files are accessible via URL.
6. Your CDN base URL will look like:
   - R2 public URL:  https://pub-XXXX.r2.dev/jennieai/tools
   - Custom domain:  https://cdn.yourdomain.com/jennieai/tools

---

## Step 2 — Set your CDN base URL in manifest.php

Open `manifest.php` and change this line:

```php
$CDN_BASE = 'https://cdn.yourdomain.com/jennieai/tools'; // ← change this
```

Replace with your actual Cloudflare CDN base URL.

Example:
```php
$CDN_BASE = 'https://pub-abc123def456.r2.dev/jennieai/tools';
```

---

## Step 3 — Upload root files to your website

Upload `jennie-ai.php` and `manifest.php` to your website root.

Access at: `https://yourdomain.com/jennie-ai.php`

---

## How it works (flow)

```
User opens jennie-ai.php
         │
         ▼
User uploads image (stays in browser memory — never sent to server)
         │
         ▼
User selects a tool branch + leaf (e.g. Compression → Compress to WebP)
         │
         ▼
jennie-ai.php fetches manifest.php → gets JSON list of CDN URLs
         │
         ▼
Only the ONE needed tool JS is downloaded from Cloudflare CDN
(e.g. compress-webp.js — ~2KB, loads in <100ms)
         │
         ▼
Tool JS registers itself: window.JennieTools['compress-webp'] = async fn
         │
         ▼
Orchestrator calls the tool function with (file, options)
         │
         ▼
Tool processes image using Canvas API — 100% in browser
         │
         ▼
Result blob is shown + download button appears
         │
         ▼
User downloads the output file
         (nothing was ever uploaded to any server)
```

---

## How to add a new tool

### 1. Write the tool JS file

Every tool must follow this exact pattern:

```javascript
(function () {
  'use strict';

  window.JennieTools = window.JennieTools || {};

  window.JennieTools['your-tool-id'] = async function (file, opts) {
    // file  = File object from the user's upload
    // opts  = { quality: 0.0–1.0 }

    // ... do your processing ...

    // Return ONE of these two result shapes:

    // For image output:
    return {
      type   : 'image',
      blob   : resultBlob,       // Blob object
      ext    : 'jpg',            // 'jpg' | 'webp' | 'png'
      width  : 1920,             // output width in px
      height : 1080,             // output height in px
      label  : 'Tool name',      // shown as result heading
    };

    // For text output:
    return {
      type  : 'text',
      lines : ['Line 1', 'Line 2', 'Line 3'],  // each line gets a Copy button
      label : 'Result heading',
    };
  };

})();
```

### 2. Upload the JS file to Cloudflare CDN

Put it in your `jennieai/tools/` folder.

### 3. Add it to manifest.php

```php
'your-tool-id' => $CDN_BASE . '/your-tool-file.js',
```

### 4. Add the button in jennie-ai.php

Inside the `#commandTree` div, add a branch or leaf:

```html
<!-- New leaf inside an existing branch -->
<div class="cmd leaf" data-tool="your-tool-id" onclick="selectLeaf(this, 'your-tool-id')">
  <span class="cmd-icon">🛠️</span> Your Tool Name
</div>
```

Then in the `selectLeaf()` JavaScript function, add a condition for
which run button / sub-options to show for your new tool ID.

---

## Tool IDs reference

| Tool ID        | File              | What it does                          |
|----------------|-------------------|---------------------------------------|
| compress-jpg   | compress-jpg.js   | Compress any image → JPG              |
| compress-webp  | compress-webp.js  | Compress any image → WebP             |
| compress-png   | compress-png.js   | Compress any image → PNG              |
| jpg-to-webp    | jpg-to-webp.js    | Convert any image → WebP (high qual)  |
| any-to-jpg     | any-to-jpg.js     | Convert any image → JPG               |
| any-to-png     | any-to-png.js     | Convert any image → PNG               |
| title-photo    | title-photo.js    | Generate 5 creative photo titles      |
| title-seo      | title-seo.js      | SEO title + alt text + meta desc      |
| title-social   | title-social.js   | Social caption + hashtags + tip       |

---

## Cloudflare CDN — CORS headers

If your tool JS files are on a different domain than your PHP site,
add these CORS headers to your Cloudflare bucket or Worker:

```
Access-Control-Allow-Origin: https://yourdomain.com
Access-Control-Allow-Methods: GET
```

Or use `*` during development:
```
Access-Control-Allow-Origin: *
```

In your Cloudflare R2 bucket settings → CORS Policy:
```json
[
  {
    "AllowedOrigins": ["https://yourdomain.com"],
    "AllowedMethods": ["GET"],
    "AllowedHeaders": ["*"]
  }
]
```

---

## Security notes

- Users' images NEVER leave their browser. No upload happens.
- manifest.php only returns URLs — no sensitive data.
- Tool JS files are public static files — they contain no secrets.
- You can add PHP session checks to manifest.php to restrict access
  to Pro users only (JennieAI Pro gate):

```php
// At top of manifest.php, before the JSON output:
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['is_pro'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Pro subscription required.']);
    exit;
}
```

---

## Browser compatibility

| Feature        | Chrome | Firefox | Safari | Edge |
|----------------|--------|---------|--------|------|
| Canvas API     | ✅     | ✅      | ✅     | ✅   |
| WebP encoding  | ✅     | ✅      | ✅ 14+ | ✅   |
| FileReader     | ✅     | ✅      | ✅     | ✅   |
| Blob + URL     | ✅     | ✅      | ✅     | ✅   |

WebP encoding requires Safari 14+. Older Safari will show an error
message for WebP tools only — all other tools work fine.

---

## Future tool ideas (just add to CDN + manifest)

- resize-image.js     — custom width/height resize
- watermark.js        — text or logo watermark overlay
- grayscale.js        — convert to greyscale / B&W
- exif-reader.js      — read and display EXIF metadata
- crop-image.js       — interactive crop tool
- metadata-strip.js   — remove all EXIF data from image
- blur-background.js  — simple centre-focus blur (no ML)
- color-correct.js    — brightness/contrast/saturation sliders
- collage.js          — combine multiple images into a grid
- batch-rename.js     — rename files by pattern before download

Each one: write the JS, upload to CDN, add to manifest.php,
add button in jennie-ai.php. That's all it takes.
