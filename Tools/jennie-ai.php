<?php
/**
 * JennieAI — Front Page
 * Place this file in your website root.
 * This page talks to manifest.php to get CDN tool links,
 * then runs everything 100% in the user's browser.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JennieAI — Smart Photo Tools</title>
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Segoe UI', system-ui, sans-serif;
  background: #0e0e12;
  color: #e8e6e1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 16px 60px;
}

/* ── Header ── */
.header {
  width: 100%;
  max-width: 700px;
  text-align: center;
  padding: 48px 0 32px;
}
.logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 1.8rem;
  font-weight: 700;
  letter-spacing: -0.5px;
  color: #fff;
}
.logo-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: #c49a2a;
  display: inline-block;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .5; transform: scale(.7); }
}
.header p {
  margin-top: 10px;
  color: #888;
  font-size: .93rem;
}

/* ── Main card ── */
.card {
  width: 100%;
  max-width: 700px;
  background: #17171e;
  border: 1px solid #2a2a35;
  border-radius: 18px;
  padding: 32px;
}

/* ── Upload zone ── */
.upload-zone {
  border: 2px dashed #2e2e3d;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color .25s, background .25s;
  position: relative;
}
.upload-zone:hover,
.upload-zone.drag-over {
  border-color: #c49a2a;
  background: rgba(196,154,42,.05);
}
.upload-zone input[type="file"] {
  position: absolute; inset: 0;
  opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-icon { font-size: 2.4rem; margin-bottom: 10px; }
.upload-zone h3 { font-size: 1rem; color: #ccc; font-weight: 500; }
.upload-zone p  { font-size: .8rem; color: #555; margin-top: 5px; }

/* Image preview */
.preview-wrap {
  display: none;
  margin-top: 18px;
  border-radius: 10px;
  overflow: hidden;
  position: relative;
  max-height: 240px;
}
.preview-wrap img {
  width: 100%; max-height: 240px;
  object-fit: contain;
  background: #111;
}
.preview-info {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  flex-wrap: wrap;
}
.badge {
  background: #1f1f2a;
  border: 1px solid #2e2e3d;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: .75rem;
  color: #888;
}
.badge span { color: #ccc; font-weight: 600; }

/* ── Command tree ── */
.commands-title {
  font-size: .72rem;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: #555;
  margin: 26px 0 12px;
}
.branch { display: flex; flex-direction: column; gap: 6px; }

.branch-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.branch-indent {
  padding-left: 22px;
  border-left: 1px solid #2a2a35;
  margin-left: 10px;
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cmd {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #2e2e3d;
  background: #1e1e28;
  color: #ccc;
  font-size: .85rem;
  cursor: pointer;
  transition: all .2s;
  user-select: none;
  white-space: nowrap;
}
.cmd:hover { border-color: #c49a2a; color: #fff; background: rgba(196,154,42,.08); }
.cmd.active { border-color: #c49a2a; color: #c49a2a; background: rgba(196,154,42,.1); }
.cmd.disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
.cmd-icon { font-size: .9rem; }
.cmd.leaf { border-color: #2a3a2a; color: #88bb88; }
.cmd.leaf:hover { border-color: #4caf7a; color: #4caf7a; background: rgba(76,175,122,.07); }
.cmd.leaf.active { border-color: #4caf7a; color: #4caf7a; background: rgba(76,175,122,.1); }

/* ── Status / log ── */
.status-box {
  margin-top: 22px;
  background: #111117;
  border: 1px solid #22222d;
  border-radius: 10px;
  padding: 16px 18px;
  font-size: .82rem;
  color: #aaa;
  line-height: 1.7;
  display: none;
}
.status-box.show { display: block; }
.status-line { display: flex; align-items: center; gap: 8px; }
.status-line.ok   { color: #4caf7a; }
.status-line.err  { color: #e05a5a; }
.status-line.info { color: #c49a2a; }
.spin {
  width: 14px; height: 14px;
  border: 2px solid #333;
  border-top-color: #c49a2a;
  border-radius: 50%;
  animation: spin .7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Result area ── */
.result-area {
  display: none;
  margin-top: 22px;
  border: 1px solid #2e3d2a;
  border-radius: 12px;
  background: #111a10;
  padding: 22px;
}
.result-area.show { display: block; }
.result-area h4 { font-size: .85rem; color: #4caf7a; margin-bottom: 12px; }
.result-preview { border-radius: 8px; overflow: hidden; max-height: 220px; margin-bottom: 14px; }
.result-preview img { width: 100%; max-height: 220px; object-fit: contain; background: #111; }
.result-stats {
  display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;
}
.stat-box {
  background: #1a2418;
  border: 1px solid #2a3d24;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: .78rem;
  color: #88bb88;
}
.stat-box .num { font-size: 1.1rem; font-weight: 700; color: #4caf7a; }

/* Result text (for title generator) */
.result-text {
  background: #0f1a0e;
  border: 1px solid #2a3d24;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 14px;
}
.result-text p {
  font-size: .9rem;
  color: #bde0ba;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.copy-btn {
  background: none; border: 1px solid #3a5a34;
  color: #4caf7a; border-radius: 5px; padding: 2px 9px;
  font-size: .7rem; cursor: pointer;
}
.copy-btn:hover { background: rgba(76,175,122,.1); }

/* Action buttons after result */
.post-actions {
  display: flex; gap: 8px; flex-wrap: wrap;
}
.action-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px; border-radius: 8px;
  font-size: .84rem; cursor: pointer; border: 1px solid;
  transition: all .2s; font-family: inherit;
}
.action-btn.primary {
  background: #c49a2a; border-color: #c49a2a; color: #000; font-weight: 700;
}
.action-btn.primary:hover { background: #d4aa3a; }
.action-btn.secondary {
  background: transparent; border-color: #2e2e3d; color: #ccc;
}
.action-btn.secondary:hover { border-color: #555; color: #fff; }
.action-btn.ghost {
  background: transparent; border-color: #2a3d24; color: #88bb88;
}
.action-btn.ghost:hover { border-color: #4caf7a; color: #4caf7a; }

/* Quality slider */
.slider-wrap {
  margin: 10px 0 4px;
  display: none;
}
.slider-wrap.show { display: block; }
.slider-wrap label {
  font-size: .78rem; color: #888; display: flex;
  justify-content: space-between; margin-bottom: 6px;
}
.slider-wrap label span { color: #c49a2a; font-weight: 600; }
input[type="range"] {
  width: 100%; accent-color: #c49a2a; cursor: pointer;
}

/* ── Loader overlay for tool fetching ── */
.fetch-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.65);
  z-index: 999;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 14px;
}
.fetch-overlay.show { display: flex; }
.fetch-overlay p { color: #aaa; font-size: .88rem; }
.big-spin {
  width: 44px; height: 44px;
  border: 4px solid #222;
  border-top-color: #c49a2a;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

/* ── Responsive ── */
@media (max-width: 500px) {
  .card { padding: 20px 16px; }
  .cmd  { font-size: .78rem; padding: 7px 12px; }
  .action-btn { font-size: .78rem; padding: 8px 14px; }
}
</style>
</head>
<body>

<div class="header">
  <div class="logo">
    Jennie<span style="color:#c49a2a">AI</span>
    <span class="logo-dot"></span>
  </div>
  <p>Smart photo tools — everything runs in your browser. Nothing is uploaded to any server.</p>
</div>

<div class="card">

  <!-- Upload Zone -->
  <div class="upload-zone" id="uploadZone">
    <input type="file" id="fileInput" accept="image/*">
    <div class="upload-icon">📷</div>
    <h3>Drop your image here, or click to browse</h3>
    <p>JPG, PNG, WebP, GIF — max 20 MB</p>
  </div>

  <!-- Preview -->
  <div class="preview-wrap" id="previewWrap">
    <img id="previewImg" src="" alt="Preview">
  </div>
  <div class="preview-info" id="previewInfo" style="display:none"></div>

  <!-- Commands -->
  <div id="commandArea" style="display:none">
    <div class="commands-title">Choose a tool</div>

    <div class="branch" id="commandTree">

      <!-- Branch: Image Compression -->
      <div class="branch-row">
        <div class="cmd" data-branch="compress" onclick="toggleBranch(this)">
          <span class="cmd-icon">🗜️</span> Image Compression
          <span style="font-size:.7rem;color:#555;margin-left:4px">▾</span>
        </div>
      </div>
      <div class="branch-indent" id="branch-compress" style="display:none">
        <div class="branch-row">
          <div class="cmd leaf" data-tool="compress-jpg" onclick="selectLeaf(this, 'compress-jpg')">
            <span class="cmd-icon">📸</span> Compress to JPG
          </div>
          <div class="cmd leaf" data-tool="compress-webp" onclick="selectLeaf(this, 'compress-webp')">
            <span class="cmd-icon">⚡</span> Compress to WebP
          </div>
          <div class="cmd leaf" data-tool="compress-png" onclick="selectLeaf(this, 'compress-png')">
            <span class="cmd-icon">🖼️</span> Compress to PNG
          </div>
        </div>
        <!-- Quality sub-branch (shown after leaf selected) -->
        <div id="qualityBranch" style="display:none; margin-top:6px;">
          <div class="slider-wrap show">
            <label>Quality: <span id="qualityVal">80</span>%</label>
            <input type="range" id="qualitySlider" min="10" max="100" value="80"
              oninput="document.getElementById('qualityVal').textContent = this.value">
          </div>
          <div class="branch-row" style="margin-top:8px">
            <div class="cmd leaf active" onclick="runTool()">
              <span class="cmd-icon">▶️</span> Run Compression
            </div>
          </div>
        </div>
      </div>

      <!-- Branch: Format Conversion -->
      <div class="branch-row" style="margin-top:6px">
        <div class="cmd" data-branch="convert" onclick="toggleBranch(this)">
          <span class="cmd-icon">🔄</span> Format Conversion
          <span style="font-size:.7rem;color:#555;margin-left:4px">▾</span>
        </div>
      </div>
      <div class="branch-indent" id="branch-convert" style="display:none">
        <div class="branch-row">
          <div class="cmd leaf" data-tool="jpg-to-webp" onclick="selectLeaf(this, 'jpg-to-webp')">
            <span class="cmd-icon">🌐</span> Any → WebP
          </div>
          <div class="cmd leaf" data-tool="any-to-jpg" onclick="selectLeaf(this, 'any-to-jpg')">
            <span class="cmd-icon">📷</span> Any → JPG
          </div>
          <div class="cmd leaf" data-tool="any-to-png" onclick="selectLeaf(this, 'any-to-png')">
            <span class="cmd-icon">🖼️</span> Any → PNG
          </div>
        </div>
        <div id="convertRunBranch" style="display:none; margin-top:8px">
          <div class="branch-row">
            <div class="cmd leaf active" onclick="runTool()">
              <span class="cmd-icon">▶️</span> Run Conversion
            </div>
          </div>
        </div>
      </div>

      <!-- Branch: Title Generator -->
      <div class="branch-row" style="margin-top:6px">
        <div class="cmd" data-branch="title" onclick="toggleBranch(this)">
          <span class="cmd-icon">✏️</span> Title Generator
          <span style="font-size:.7rem;color:#555;margin-left:4px">▾</span>
        </div>
      </div>
      <div class="branch-indent" id="branch-title" style="display:none">
        <div class="branch-row">
          <div class="cmd leaf" data-tool="title-photo" onclick="selectLeaf(this, 'title-photo')">
            <span class="cmd-icon">🌅</span> Photo Title
          </div>
          <div class="cmd leaf" data-tool="title-seo" onclick="selectLeaf(this, 'title-seo')">
            <span class="cmd-icon">🔍</span> SEO Title + Alt Text
          </div>
          <div class="cmd leaf" data-tool="title-social" onclick="selectLeaf(this, 'title-social')">
            <span class="cmd-icon">📲</span> Social Caption
          </div>
        </div>
        <div id="titleRunBranch" style="display:none; margin-top:8px">
          <div class="branch-row">
            <div class="cmd leaf active" onclick="runTool()">
              <span class="cmd-icon">▶️</span> Generate Titles
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Status box -->
  <div class="status-box" id="statusBox"></div>

  <!-- Result area -->
  <div class="result-area" id="resultArea">
    <h4 id="resultTitle">✅ Done</h4>
    <div class="result-preview" id="resultPreview" style="display:none">
      <img id="resultImg" src="" alt="Result">
    </div>
    <div class="result-text" id="resultText" style="display:none"></div>
    <div class="result-stats" id="resultStats"></div>
    <div class="post-actions" id="postActions"></div>
  </div>

</div><!-- /card -->

<!-- Fetch overlay -->
<div class="fetch-overlay" id="fetchOverlay">
  <div class="big-spin"></div>
  <p id="fetchMsg">Loading tool…</p>
</div>

<script>
/* ============================================================
   JennieAI — Frontend Orchestrator
   ============================================================ */

const MANIFEST_URL = 'manifest.php'; // sibling file in root

/* ─── State ─── */
let loadedFile    = null;   // File object from input
let currentTool   = null;   // e.g. 'compress-jpg'
let toolFunctions = {};     // { toolId: fn } — populated after CDN load
let manifestCache = null;

/* ─── Utility ─── */
function fmt(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(2) + ' MB';
}
function log(msg, type='info') {
  const box = document.getElementById('statusBox');
  box.classList.add('show');
  const line = document.createElement('div');
  line.className = 'status-line ' + type;
  const icons = { ok:'✅', err:'❌', info:'⚙️' };
  line.innerHTML = `<span>${icons[type]||'•'}</span><span>${msg}</span>`;
  box.appendChild(line);
  box.scrollTop = box.scrollHeight;
}
function clearLog() {
  const box = document.getElementById('statusBox');
  box.innerHTML = '';
  box.classList.remove('show');
}
function showOverlay(msg) {
  document.getElementById('fetchMsg').textContent = msg;
  document.getElementById('fetchOverlay').classList.add('show');
}
function hideOverlay() {
  document.getElementById('fetchOverlay').classList.remove('show');
}
function hideResult() {
  document.getElementById('resultArea').classList.remove('show');
  document.getElementById('resultPreview').style.display = 'none';
  document.getElementById('resultText').style.display   = 'none';
  document.getElementById('resultStats').innerHTML = '';
  document.getElementById('postActions').innerHTML = '';
  document.getElementById('resultImg').src = '';
}

/* ─── Upload handling ─── */
const uploadZone = document.getElementById('uploadZone');
const fileInput  = document.getElementById('fileInput');

uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
  e.preventDefault();
  uploadZone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f) handleFile(f);
});
fileInput.addEventListener('change', () => {
  if (fileInput.files[0]) handleFile(fileInput.files[0]);
});

function handleFile(file) {
  if (!file.type.startsWith('image/')) { alert('Please upload an image file.'); return; }
  if (file.size > 20 * 1024 * 1024)   { alert('File must be under 20 MB.'); return; }

  loadedFile  = file;
  currentTool = null;
  clearLog();
  hideResult();

  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('previewImg');
    img.src = e.target.result;
    document.getElementById('previewWrap').style.display = 'block';

    const tmp = new Image();
    tmp.onload = () => {
      document.getElementById('previewInfo').style.display = 'flex';
      document.getElementById('previewInfo').innerHTML = `
        <div class="badge">File: <span>${file.name}</span></div>
        <div class="badge">Size: <span>${fmt(file.size)}</span></div>
        <div class="badge">Dimensions: <span>${tmp.naturalWidth}×${tmp.naturalHeight}</span></div>
        <div class="badge">Type: <span>${file.type}</span></div>
      `;
    };
    tmp.src = e.target.result;
  };
  reader.readAsDataURL(file);

  document.getElementById('commandArea').style.display = 'block';
  // reset all sub-branches
  document.querySelectorAll('.branch-indent').forEach(b => b.style.display = 'none');
  document.querySelectorAll('.cmd').forEach(c => c.classList.remove('active'));
  document.getElementById('qualityBranch').style.display    = 'none';
  document.getElementById('convertRunBranch').style.display = 'none';
  document.getElementById('titleRunBranch').style.display   = 'none';
}

/* ─── Branch toggle ─── */
function toggleBranch(el) {
  const id = 'branch-' + el.dataset.branch;
  const sub = document.getElementById(id);
  const isOpen = sub.style.display !== 'none';

  // close all branches
  document.querySelectorAll('.branch-indent').forEach(b => b.style.display = 'none');
  document.querySelectorAll('.cmd[data-branch]').forEach(c => c.classList.remove('active'));

  if (!isOpen) {
    sub.style.display = 'flex';
    sub.style.flexDirection = 'column';
    el.classList.add('active');
  }
  currentTool = null;
  clearLog();
  hideResult();
  // hide sub-sub branches
  document.getElementById('qualityBranch').style.display    = 'none';
  document.getElementById('convertRunBranch').style.display = 'none';
  document.getElementById('titleRunBranch').style.display   = 'none';
}

/* ─── Leaf selection ─── */
function selectLeaf(el, toolId) {
  document.querySelectorAll('.cmd.leaf').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  currentTool = toolId;
  clearLog();
  hideResult();

  // Show relevant sub-options
  if (toolId.startsWith('compress')) {
    document.getElementById('qualityBranch').style.display    = 'block';
    document.getElementById('convertRunBranch').style.display = 'none';
    document.getElementById('titleRunBranch').style.display   = 'none';
  } else if (toolId.startsWith('any-') || toolId === 'jpg-to-webp') {
    document.getElementById('convertRunBranch').style.display = 'block';
    document.getElementById('qualityBranch').style.display    = 'none';
    document.getElementById('titleRunBranch').style.display   = 'none';
  } else if (toolId.startsWith('title')) {
    document.getElementById('titleRunBranch').style.display   = 'block';
    document.getElementById('qualityBranch').style.display    = 'none';
    document.getElementById('convertRunBranch').style.display = 'none';
  }
}

/* ─── Fetch manifest then tool ─── */
async function fetchManifest() {
  if (manifestCache) return manifestCache;
  const res = await fetch(MANIFEST_URL);
  if (!res.ok) throw new Error('Manifest unavailable (' + res.status + ')');
  manifestCache = await res.json();
  return manifestCache;
}

async function loadTool(toolId) {
  if (toolFunctions[toolId]) return; // already loaded

  const manifest = await fetchManifest();
  const url = manifest.tools[toolId];
  if (!url) throw new Error('Tool "' + toolId + '" not found in manifest.');

  showOverlay('Loading ' + toolId + ' tool from CDN…');
  log('Fetching tool from CDN…', 'info');

  await new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = url;
    script.onload  = resolve;
    script.onerror = () => reject(new Error('Failed to load tool script: ' + url));
    document.head.appendChild(script);
  });

  hideOverlay();
  log('Tool loaded ✓', 'ok');
}

/* ─── Run tool ─── */
async function runTool() {
  if (!loadedFile)   { alert('Please upload an image first.'); return; }
  if (!currentTool)  { alert('Please select a tool.'); return; }

  clearLog();
  hideResult();

  try {
    await loadTool(currentTool);

    const quality = parseInt(document.getElementById('qualitySlider').value) / 100;

    // Each tool script registers itself via: window.JennieTools['tool-id'] = async fn(file, opts) => result
    const toolFn = (window.JennieTools || {})[currentTool];
    if (typeof toolFn !== 'function') throw new Error('Tool function not registered for: ' + currentTool);

    log('Processing image…', 'info');
    showOverlay('Processing…');

    const result = await toolFn(loadedFile, { quality });
    hideOverlay();

    renderResult(result);

  } catch (err) {
    hideOverlay();
    log('Error: ' + err.message, 'err');
    console.error(err);
  }
}

/* ─── Render result ─── */
function renderResult(result) {
  const area  = document.getElementById('resultArea');
  const stats = document.getElementById('resultStats');
  const acts  = document.getElementById('postActions');

  area.classList.add('show');
  document.getElementById('resultTitle').textContent = '✅ ' + (result.label || 'Done');

  /* Image result */
  if (result.type === 'image' && result.blob) {
    const url = URL.createObjectURL(result.blob);
    document.getElementById('resultImg').src = url;
    document.getElementById('resultPreview').style.display = 'block';

    const saved = loadedFile.size - result.blob.size;
    const pct   = ((saved / loadedFile.size) * 100).toFixed(1);

    stats.innerHTML = `
      <div class="stat-box"><div class="num">${fmt(loadedFile.size)}</div>Original</div>
      <div class="stat-box"><div class="num">${fmt(result.blob.size)}</div>Result</div>
      <div class="stat-box"><div class="num">${saved > 0 ? '-'+pct+'%' : '+'+Math.abs(pct)+'%'}</div>Size change</div>
      ${result.width ? `<div class="stat-box"><div class="num">${result.width}×${result.height}</div>Dimensions</div>` : ''}
    `;

    /* Post-actions */
    const ext = result.ext || 'jpg';

    // Download
    acts.innerHTML += `<button class="action-btn primary" onclick="downloadBlob('${url}','jennieai-output.${ext}')">⬇️ Download</button>`;

    // Compress more (only for compression tools)
    if (currentTool.startsWith('compress')) {
      acts.innerHTML += `<button class="action-btn ghost" onclick="adjustQuality(-10)">📉 Compress more</button>`;
      acts.innerHTML += `<button class="action-btn ghost" onclick="adjustQuality(+10)">📈 Better quality</button>`;
    }

    // Try another tool
    acts.innerHTML += `<button class="action-btn secondary" onclick="resetToCommands()">🔄 Try another tool</button>`;

    // New image
    acts.innerHTML += `<button class="action-btn secondary" onclick="resetAll()">📁 New image</button>`;

    // Use result as new input
    acts.innerHTML += `<button class="action-btn ghost" onclick="useResultAsInput('${url}', '${ext}', ${result.blob.size})">🔁 Re-process result</button>`;
  }

  /* Text result */
  if (result.type === 'text' && result.lines) {
    const box = document.getElementById('resultText');
    box.style.display = 'block';
    box.innerHTML = result.lines.map((line, i) =>
      `<p>${line} <button class="copy-btn" onclick="copyText(${i})">Copy</button></p>`
    ).join('');
    window._resultLines = result.lines;

    acts.innerHTML = `
      <button class="action-btn primary" onclick="copyAll()">📋 Copy all</button>
      <button class="action-btn secondary" onclick="resetToCommands()">🔄 Try another tool</button>
      <button class="action-btn secondary" onclick="resetAll()">📁 New image</button>
    `;
  }

  log('Finished!', 'ok');
}

/* ─── Post-result actions ─── */
function downloadBlob(url, name) {
  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
}

function adjustQuality(delta) {
  const slider = document.getElementById('qualitySlider');
  slider.value = Math.max(10, Math.min(100, parseInt(slider.value) + delta));
  document.getElementById('qualityVal').textContent = slider.value;
  runTool();
}

function resetToCommands() {
  hideResult();
  clearLog();
  currentTool = null;
  document.querySelectorAll('.cmd').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.branch-indent').forEach(b => b.style.display = 'none');
  document.getElementById('qualityBranch').style.display    = 'none';
  document.getElementById('convertRunBranch').style.display = 'none';
  document.getElementById('titleRunBranch').style.display   = 'none';
}

function resetAll() {
  resetToCommands();
  loadedFile = null;
  document.getElementById('previewWrap').style.display  = 'none';
  document.getElementById('previewInfo').style.display  = 'none';
  document.getElementById('commandArea').style.display  = 'none';
  document.getElementById('fileInput').value            = '';
  document.getElementById('previewImg').src             = '';
}

function useResultAsInput(url, ext, size) {
  fetch(url).then(r => r.blob()).then(blob => {
    const file = new File([blob], 'result.' + ext, { type: blob.type });
    handleFile(file);
  });
}

function copyText(i) {
  navigator.clipboard.writeText(window._resultLines[i]).then(() => log('Copied!', 'ok'));
}
function copyAll() {
  navigator.clipboard.writeText((window._resultLines||[]).join('\n')).then(() => log('All copied!', 'ok'));
}
</script>
</body>
</html>
