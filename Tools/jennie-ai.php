<?php
session_start();
include "auth/db.php";
include "auth/security.php";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = '/jennie-ai';
    header('Location: /auth/login');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, full_name, username, profile_image, is_verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
if (!$me) { header('Location: /auth/login'); exit; }

// Token system
$tok_stmt = $conn->prepare("SELECT * FROM jennie_tokens WHERE user_id = ? LIMIT 1");
$tok_stmt->bind_param("i", $uid);
$tok_stmt->execute();
$tok_row = $tok_stmt->get_result()->fetch_assoc();

$DEFAULT_TOKENS = 120;
$DEFAULT_MAX    = 120;

if (!$tok_row) {
    $reset_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $ins = $conn->prepare("INSERT INTO jennie_tokens (user_id, tokens_left, tokens_max, reset_at) VALUES (?,?,?,?)");
    $ins->bind_param("iiis", $uid, $DEFAULT_TOKENS, $DEFAULT_MAX, $reset_at);
    $ins->execute();
    $tok_row = ['tokens_left' => $DEFAULT_TOKENS, 'tokens_max' => $DEFAULT_MAX, 'reset_at' => $reset_at];
}

if (isset($tok_row['reset_at']) && strtotime($tok_row['reset_at']) <= time()) {
    $new_reset = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $upd = $conn->prepare("UPDATE jennie_tokens SET tokens_left = tokens_max, reset_at = ?, updated_at = NOW() WHERE user_id = ?");
    $upd->bind_param("si", $new_reset, $uid);
    $upd->execute();
    $tok_row['tokens_left'] = $tok_row['tokens_max'];
    $tok_row['reset_at']    = $new_reset;
}

$tokens_left  = (int)$tok_row['tokens_left'];
$tokens_max   = (int)$tok_row['tokens_max'];
$reset_at_ts  = strtotime($tok_row['reset_at']);
$token_pct    = $tokens_max > 0 ? round(($tokens_left / $tokens_max) * 100) : 0;

// Recent history
$hist_stmt = $conn->prepare("SELECT tool_id, label, created_at FROM jennie_history WHERE user_id = ? ORDER BY id DESC LIMIT 6");
$history = [];
if ($hist_stmt) {
    $hist_stmt->bind_param("i", $uid);
    $hist_stmt->execute();
    $hr = $hist_stmt->get_result();
    while ($row = $hr->fetch_assoc()) $history[] = $row;
}

$first_name = explode(' ', trim($me['full_name']))[0];
$is_pro     = (bool)$me['is_verified'];

$welcomes = [
    ["line" => "Good to see you, {name}.", "sub" => "Your workspace is ready. What are we working on today?"],
    ["line" => "Welcome back, {name}.", "sub" => "Drop an image and I will get started right away."],
    ["line" => "Hey {name} — let's create something.", "sub" => "Upload a photo or pick a tool below to begin."],
    ["line" => "{name}, ready when you are.", "sub" => "Your tokens are loaded. Let's make something great."],
    ["line" => "Back again, {name}.", "sub" => "Pick up where you left off or start something new."],
];
$wl = $welcomes[crc32($first_name . date('Ymd')) % count($welcomes)];
$welcome_line = str_replace('{name}', $first_name, $wl['line']);
$welcome_sub  = $wl['sub'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JennieAI — Image Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root {
  --bg:          #f5f4f1;
  --surface:     #ffffff;
  --surface-2:   #f9f8f6;
  --surface-3:   #f0efe9;
  --border:      rgba(0,0,0,.08);
  --border-md:   rgba(0,0,0,.13);
  --ink:         #111110;
  --ink-2:       #3a3935;
  --ink-3:       #6b6a65;
  --ink-4:       #9e9d99;
  --accent:      #2563eb;
  --accent-lt:   #eff4ff;
  --accent-mid:  #93b4f7;
  --gold:        #b45309;
  --gold-lt:     #fef3c7;
  --green:       #16a34a;
  --green-lt:    #f0fdf4;
  --red:         #dc2626;
  --red-lt:      #fef2f2;
  --amber:       #d97706;
  --amber-lt:    #fffbeb;
  --purple:      #7c3aed;
  --purple-lt:   #f5f3ff;
  --radius:      14px;
  --radius-sm:   9px;
  --radius-xs:   6px;
  --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:   0 4px 16px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.04);
  --shadow-lg:   0 12px 40px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
  --sidebar-w:   268px;
  --topbar-h:    60px;
  --font:        'Inter', system-ui, sans-serif;
  --font-serif:  'DM Serif Display', Georgia, serif;
  --tr:          .2s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--bg);color:var(--ink);min-height:100vh;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button{font-family:var(--font);cursor:pointer;border:none;background:none}

/* LAYOUT */
.layout{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{
  width:var(--sidebar-w);background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;height:100vh;
  z-index:200;overflow:hidden;
  transition:transform var(--tr);
}
.sidebar-top{padding:20px 16px 14px;border-bottom:1px solid var(--border)}
.sidebar-logo{display:flex;align-items:center;gap:9px;font-weight:700;font-size:1.05rem;color:var(--ink);margin-bottom:16px}
.logo-gem{
  width:32px;height:32px;border-radius:9px;flex-shrink:0;
  background:linear-gradient(135deg,#2563eb,#7c3aed);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:.8rem;
  box-shadow:0 2px 8px rgba(37,99,235,.3);
}
.token-sidebar{
  background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);
  border-radius:var(--radius-sm);padding:11px 12px;
}
.tok-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
.tok-label{font-size:.7rem;font-weight:600;color:var(--accent);letter-spacing:.03em;display:flex;align-items:center;gap:4px}
.tok-count{font-size:.78rem;font-weight:700;color:var(--ink)}
.tok-bar-bg{height:5px;background:rgba(37,99,235,.12);border-radius:99px;overflow:hidden}
.tok-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#2563eb,#7c3aed);transition:width .8s cubic-bezier(.4,0,.2,1)}
.tok-reset{font-size:.66rem;color:var(--ink-4);margin-top:5px;display:flex;align-items:center;gap:3px}

.sidebar-nav{flex:1;overflow-y:auto;padding:8px 8px;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.nav-sect{font-size:.63rem;font-weight:700;letter-spacing:.12em;color:var(--ink-4);text-transform:uppercase;padding:12px 8px 4px}
.nav-item{
  display:flex;align-items:center;gap:9px;
  padding:8px 9px;border-radius:var(--radius-xs);
  font-size:.84rem;font-weight:500;color:var(--ink-3);
  cursor:pointer;transition:all var(--tr);margin-bottom:1px;
}
.nav-item:hover{background:var(--surface-3);color:var(--ink)}
.nav-item.active{background:var(--accent-lt);color:var(--accent)}
.nav-item i{width:15px;text-align:center;font-size:.82rem;flex-shrink:0}
.nav-badge{
  margin-left:auto;background:var(--accent);color:#fff;
  font-size:.58rem;font-weight:700;padding:2px 6px;border-radius:99px;
}
.nav-badge.gold{background:var(--gold)}
.nav-div{height:1px;background:var(--border);margin:6px 0}

.sidebar-bottom{padding:10px 8px;border-top:1px solid var(--border)}
.user-pill{display:flex;align-items:center;gap:9px;padding:8px 9px;border-radius:var(--radius-sm);cursor:pointer;transition:background var(--tr)}
.user-pill:hover{background:var(--surface-3)}
.u-avatar{
  width:30px;height:30px;border-radius:50%;flex-shrink:0;overflow:hidden;
  background:linear-gradient(135deg,var(--accent),var(--purple));
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:.75rem;font-weight:700;
}
.u-avatar img{width:100%;height:100%;object-fit:cover}
.u-name{font-size:.8rem;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.u-role{font-size:.66rem;color:var(--ink-4)}
.u-role.pro{color:var(--gold);font-weight:600}

/* MAIN */
.main-wrap{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* TOPBAR */
.topbar{
  height:var(--topbar-h);
  background:rgba(255,255,255,.88);
  backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 26px;gap:14px;
  position:sticky;top:0;z-index:100;
}
.topbar-title{font-size:.86rem;font-weight:600;color:var(--ink-2);flex:1}
.topbar-right{display:flex;align-items:center;gap:8px}
.tok-pill{
  display:flex;align-items:center;gap:5px;
  background:var(--accent-lt);border:1px solid rgba(37,99,235,.18);
  border-radius:99px;padding:5px 12px;
  font-size:.76rem;font-weight:600;color:var(--accent);
}
.tb-btn{
  width:34px;height:34px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  color:var(--ink-3);font-size:.82rem;
  transition:all var(--tr);border:1px solid var(--border);
  background:var(--surface);
}
.tb-btn:hover{background:var(--surface-3);color:var(--ink)}

/* CONTENT */
.content{padding:30px 26px;max-width:860px;width:100%}

/* WELCOME */
.welcome-banner{margin-bottom:28px;padding-bottom:22px;border-bottom:1px solid var(--border)}
.welcome-greeting{font-family:var(--font-serif);font-size:clamp(1.6rem,3vw,2.2rem);color:var(--ink);margin-bottom:5px;line-height:1.2}
.welcome-sub{font-size:.9rem;color:var(--ink-3)}
.status-row{display:flex;align-items:center;gap:6px;margin-top:12px;flex-wrap:wrap}
.s-dot{width:7px;height:7px;border-radius:50%;background:var(--green);animation:pulseDot 2.5s infinite}
@keyframes pulseDot{0%,100%{box-shadow:0 0 0 0 rgba(22,163,74,.4)}50%{box-shadow:0 0 0 5px rgba(22,163,74,0)}}
.s-text{font-size:.73rem;color:var(--ink-4);font-weight:500}
.s-sep{color:var(--border-md)}
.s-pro{
  display:inline-flex;align-items:center;gap:3px;
  background:var(--gold-lt);border:1px solid rgba(180,83,9,.25);
  color:var(--gold);border-radius:99px;font-size:.63rem;font-weight:700;padding:2px 8px;
}

/* TOKEN EXHAUSTED */
.tok-exhausted{
  display:none;background:var(--red-lt);
  border:1px solid rgba(220,38,38,.2);border-radius:var(--radius);
  padding:18px 20px;margin-bottom:22px;
  align-items:flex-start;gap:13px;
}
.tok-exhausted.show{display:flex}
.te-icon{color:var(--red);font-size:1.2rem;flex-shrink:0;margin-top:2px}
.te-title{font-size:.9rem;font-weight:700;color:var(--red);margin-bottom:3px}
.te-desc{font-size:.8rem;color:var(--ink-3);line-height:1.6}
.te-timer{font-size:.76rem;font-weight:600;color:var(--ink);margin-top:7px}

/* SECTION HEADER */
.sec-head{display:flex;align-items:center;gap:8px;margin-bottom:13px}
.sec-title{font-size:.73rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-4)}
.sec-head::after{content:'';flex:1;height:1px;background:var(--border)}

/* QUICK GRID */
.quick-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:9px;margin-bottom:24px}
.quick-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:15px 13px;
  cursor:pointer;transition:all var(--tr);box-shadow:var(--shadow-sm);
  position:relative;overflow:hidden;
}
.quick-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;transition:opacity .25s}
.quick-card:hover{border-color:var(--border-md);box-shadow:var(--shadow-md);transform:translateY(-2px)}
.quick-card:hover::before{opacity:1}
.quick-card.c-blue::before  {background:var(--accent)}
.quick-card.c-green::before {background:var(--green)}
.quick-card.c-amber::before {background:var(--amber)}
.quick-card.c-purple::before{background:var(--purple)}

.qc-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.88rem;margin-bottom:9px}
.qc-icon.c-blue  {background:var(--accent-lt);color:var(--accent)}
.qc-icon.c-green {background:var(--green-lt);color:var(--green)}
.qc-icon.c-amber {background:var(--amber-lt);color:var(--amber)}
.qc-icon.c-purple{background:var(--purple-lt);color:var(--purple)}
.qc-name{font-size:.82rem;font-weight:600;color:var(--ink-2);margin-bottom:2px}
.qc-desc{font-size:.7rem;color:var(--ink-4);line-height:1.4}
.qc-cost{font-size:.64rem;color:var(--ink-4);margin-top:7px}
.qc-cost span{color:var(--accent);font-weight:600}

/* UPLOAD */
.upload-zone{
  border:1.5px dashed var(--border-md);border-radius:var(--radius);
  padding:36px 22px;text-align:center;cursor:pointer;
  transition:all .25s;background:var(--surface);
  position:relative;overflow:hidden;
}
.upload-zone::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at 50% 0%,rgba(37,99,235,.04) 0%,transparent 65%);
  pointer-events:none;
}
.upload-zone:hover,.upload-zone.drag-over{border-color:var(--accent);background:var(--accent-lt)}
.upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-icon-wrap{
  width:50px;height:50px;border-radius:13px;
  background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 13px;font-size:1.2rem;color:var(--accent);
}
.upload-zone h3{font-size:.96rem;font-weight:600;color:var(--ink-2);margin-bottom:4px}
.upload-zone p{font-size:.78rem;color:var(--ink-4)}
.upload-chips{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;flex-wrap:wrap}
.uchip{
  display:inline-flex;align-items:center;gap:3px;
  background:var(--surface-3);border:1px solid var(--border);
  border-radius:99px;padding:3px 9px;font-size:.68rem;color:var(--ink-4);
}

/* FILE PREVIEW */
.file-preview{
  display:none;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:14px 16px;
  margin-top:12px;gap:14px;align-items:flex-start;
  animation:slideDown .3s ease;
}
.file-preview.show{display:flex}
@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.prev-thumb{width:76px;height:76px;border-radius:9px;overflow:hidden;flex-shrink:0;background:var(--surface-3)}
.prev-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.prev-meta{flex:1;min-width:0}
.prev-name{font-size:.84rem;font-weight:600;color:var(--ink);margin-bottom:2px;word-break:break-all}
.prev-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.pchip{
  background:var(--surface-3);border:1px solid var(--border);
  border-radius:5px;padding:2px 8px;font-size:.69rem;color:var(--ink-3);
}
.prev-close{color:var(--ink-4);font-size:.88rem;cursor:pointer;transition:color var(--tr);padding:2px;flex-shrink:0}
.prev-close:hover{color:var(--red)}

/* BRANCH CARDS */
.branch-grid{display:flex;flex-direction:column;gap:9px;margin-bottom:24px}
.branch-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  transition:box-shadow var(--tr),border-color var(--tr);
  box-shadow:var(--shadow-sm);
}
.branch-card:hover{border-color:var(--border-md);box-shadow:var(--shadow-md)}
.branch-card.expanded{border-color:rgba(37,99,235,.22)}

.branch-hd{
  display:flex;align-items:center;gap:11px;
  padding:13px 15px;cursor:pointer;user-select:none;
  transition:background var(--tr);
}
.branch-hd:hover{background:var(--surface-2)}
.branch-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.branch-ic.c-blue  {background:var(--accent-lt);color:var(--accent)}
.branch-ic.c-green {background:var(--green-lt);color:var(--green)}
.branch-ic.c-amber {background:var(--amber-lt);color:var(--amber)}
.branch-lbl{flex:1}
.branch-name{font-size:.88rem;font-weight:600;color:var(--ink)}
.branch-desc{font-size:.72rem;color:var(--ink-4);margin-top:1px}
.branch-cost{
  display:inline-flex;align-items:center;gap:3px;
  background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);
  border-radius:99px;padding:3px 8px;
  font-size:.65rem;font-weight:600;color:var(--accent);white-space:nowrap;
}
.branch-chev{color:var(--ink-4);font-size:.72rem;transition:transform .3s cubic-bezier(.4,0,.2,1)}
.branch-card.expanded .branch-chev{transform:rotate(180deg)}

.branch-body{max-height:0;overflow:hidden;transition:max-height .4s cubic-bezier(.4,0,.2,1);border-top:0 solid var(--border)}
.branch-card.expanded .branch-body{max-height:500px;border-top-width:1px}
.branch-inner{padding:13px 15px;display:flex;flex-direction:column;gap:10px}

/* LEAVES */
.leaf-row{display:flex;flex-wrap:wrap;gap:6px}
.leaf{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 13px;border-radius:99px;
  border:1px solid var(--border);background:var(--surface-2);
  font-size:.81rem;font-weight:500;color:var(--ink-3);
  cursor:pointer;transition:all .18s;user-select:none;
}
.leaf:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}
.leaf.selected{border-color:var(--accent);color:var(--accent);background:var(--accent-lt);font-weight:600}
.leaf i{font-size:.75rem}
.leaf-cost{font-size:.62rem;background:rgba(37,99,235,.1);border-radius:99px;padding:1px 6px;color:var(--accent);font-weight:700}

/* QUALITY SLIDER */
.quality-row{display:none;align-items:center;gap:11px}
.quality-row.show{display:flex}
.quality-row label{font-size:.76rem;font-weight:600;color:var(--ink-3);white-space:nowrap}
.quality-row input[type="range"]{flex:1;accent-color:var(--accent);cursor:pointer;height:4px}
.quality-val{font-size:.8rem;font-weight:700;color:var(--accent);min-width:32px;text-align:right}

/* RUN BUTTON */
.run-btn{
  display:none;width:100%;padding:11px 18px;
  border-radius:99px;font-size:.86rem;font-weight:700;
  background:linear-gradient(135deg,#2563eb,#4f46e5);
  color:#fff;letter-spacing:.02em;
  transition:all .22s;box-shadow:0 2px 12px rgba(37,99,235,.28);
  align-items:center;justify-content:center;gap:7px;
}
.run-btn.show{display:flex}
.run-btn:hover{box-shadow:0 4px 20px rgba(37,99,235,.42);transform:translateY(-1px);filter:brightness(1.05)}
.run-btn:active{transform:translateY(0)}

/* RESPONSE */
.response-area{display:none;margin-bottom:24px}
.response-area.show{display:block}
.response-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
  box-shadow:var(--shadow-sm);
  animation:slideUp .35s cubic-bezier(.4,0,.2,1) both;
}
@keyframes slideUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.res-hd{
  padding:13px 15px 11px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:9px;
}
.res-ai-badge{display:flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;color:var(--accent)}
.res-title{font-size:.84rem;font-weight:600;color:var(--ink);flex:1}
.res-tok-used{display:flex;align-items:center;gap:3px;font-size:.68rem;color:var(--ink-4)}
.res-tok-used i{color:var(--accent);font-size:.63rem}

/* image result */
.res-img-wrap{padding:15px;display:flex;gap:15px;flex-wrap:wrap;align-items:flex-start}
.res-img-prev{
  border-radius:9px;overflow:hidden;max-width:260px;max-height:210px;
  border:1px solid var(--border);background:var(--surface-3);flex-shrink:0;
}
.res-img-prev img{width:100%;display:block;max-height:210px;object-fit:contain}
.res-stats{flex:1;min-width:170px}
.res-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:13px}
.res-stat-box{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 11px}
.res-stat-num{font-size:1.05rem;font-weight:700;color:var(--ink);line-height:1;margin-bottom:2px}
.res-stat-num.green{color:var(--green)}
.res-stat-lbl{font-size:.66rem;color:var(--ink-4)}

/* text result */
.res-text-wrap{padding:14px;display:flex;flex-direction:column;gap:7px}
.res-line{
  display:flex;align-items:flex-start;justify-content:space-between;gap:8px;
  padding:9px 12px;background:var(--surface-2);border:1px solid var(--border);
  border-radius:var(--radius-sm);
}
.res-line-text{font-size:.84rem;color:var(--ink-2);line-height:1.55;flex:1}
.copy-sm{
  background:none;border:1px solid var(--border-md);color:var(--ink-4);
  border-radius:5px;padding:2px 8px;font-size:.66rem;cursor:pointer;
  transition:all var(--tr);white-space:nowrap;flex-shrink:0;
}
.copy-sm:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}

/* post actions */
.post-acts{padding:11px 15px;border-top:1px solid var(--border);display:flex;gap:7px;flex-wrap:wrap}
.act-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 14px;border-radius:99px;
  font-size:.78rem;font-weight:600;
  transition:all .18s;cursor:pointer;border:1px solid;
}
.act-btn.primary{background:linear-gradient(135deg,#2563eb,#4f46e5);border-color:transparent;color:#fff;box-shadow:0 2px 8px rgba(37,99,235,.22)}
.act-btn.primary:hover{box-shadow:0 4px 16px rgba(37,99,235,.38);transform:translateY(-1px)}
.act-btn.ghost{background:var(--surface-2);border-color:var(--border);color:var(--ink-3)}
.act-btn.ghost:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}

/* HISTORY */
.hist-list{display:flex;flex-direction:column;gap:5px}
.hist-item{
  display:flex;align-items:center;gap:11px;
  padding:9px 13px;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius-sm);transition:all var(--tr);
}
.hist-item:hover{border-color:var(--border-md)}
.hist-ic{width:28px;height:28px;border-radius:7px;background:var(--accent-lt);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.hist-lbl{flex:1;font-size:.81rem;color:var(--ink-2);font-weight:500}
.hist-time{font-size:.68rem;color:var(--ink-4)}
.hist-empty{font-size:.82rem;color:var(--ink-4);text-align:center;padding:20px;background:var(--surface);border:1px dashed var(--border-md);border-radius:var(--radius-sm)}

/* PROCESSING OVERLAY */
.proc-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(245,244,241,.93);
  backdrop-filter:blur(14px);
  z-index:9000;align-items:center;justify-content:center;flex-direction:column;
}
.proc-overlay.show{display:flex}
.proc-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:20px;padding:38px 44px;
  text-align:center;box-shadow:var(--shadow-lg);
  max-width:360px;width:90%;
}
.proc-spinner{width:60px;height:60px;margin:0 auto 18px;position:relative}
.proc-ring{
  width:60px;height:60px;border-radius:50%;
  border:3px solid var(--surface-3);border-top-color:var(--accent);
  animation:spin .85s linear infinite;
  position:absolute;top:0;left:0;
}
.proc-ring2{
  width:46px;height:46px;border-radius:50%;
  border:2px solid transparent;border-top-color:rgba(37,99,235,.28);
  animation:spin 1.35s linear infinite reverse;
  position:absolute;top:7px;left:7px;
}
@keyframes spin{to{transform:rotate(360deg)}}
.proc-gem{
  width:20px;height:20px;border-radius:5px;
  background:linear-gradient(135deg,#2563eb,#7c3aed);
  position:absolute;top:20px;left:20px;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:.58rem;
  animation:gemPulse 1.8s infinite;
}
@keyframes gemPulse{0%,100%{opacity:1}50%{opacity:.55}}
.proc-title{font-size:.97rem;font-weight:700;color:var(--ink);margin-bottom:5px}
.proc-msg{font-size:.82rem;color:var(--ink-3);line-height:1.6;min-height:38px}
.proc-steps-list{display:flex;flex-direction:column;gap:6px;margin-top:18px;text-align:left}
.pstep{display:flex;align-items:center;gap:8px;font-size:.75rem;color:var(--ink-4);transition:color .35s}
.pstep.done{color:var(--green)}
.pstep.active{color:var(--ink);font-weight:600}
.pstep-dot{width:7px;height:7px;border-radius:50%;background:var(--border-md);flex-shrink:0;transition:background .35s}
.pstep.done .pstep-dot{background:var(--green)}
.pstep.active .pstep-dot{background:var(--accent);animation:pulseDot 1s infinite}
.proc-bar-bg{height:3px;background:var(--surface-3);border-radius:99px;margin-top:18px;overflow:hidden}
.proc-bar-fill{height:100%;background:linear-gradient(90deg,#2563eb,#7c3aed);border-radius:99px;width:0%;transition:width .55s ease}

/* TOAST */
#toast-container{position:fixed;bottom:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:7px}
.toast{
  display:flex;align-items:center;gap:8px;
  padding:10px 15px;border-radius:var(--radius-sm);
  font-size:.82rem;font-weight:500;
  box-shadow:var(--shadow-md);
  animation:toastIn .3s cubic-bezier(.4,0,.2,1);max-width:290px;
}
@keyframes toastIn{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)}}
.toast.success{background:var(--green-lt);border:1px solid rgba(22,163,74,.22);color:var(--green)}
.toast.error  {background:var(--red-lt);border:1px solid rgba(220,38,38,.22);color:var(--red)}
.toast.info   {background:var(--accent-lt);border:1px solid rgba(37,99,235,.18);color:var(--accent)}

/* RIPPLE */
.ripple{position:absolute;border-radius:50%;background:rgba(37,99,235,.14);transform:scale(0);animation:rippleA .5s linear;pointer-events:none}
@keyframes rippleA{to{transform:scale(4);opacity:0}}

/* MOBILE SIDEBAR TOGGLE */
.mob-sidebar-toggle{
  display:none;position:fixed;bottom:18px;left:50%;transform:translateX(-50%);
  z-index:300;background:var(--ink);color:#fff;
  border:none;border-radius:99px;padding:9px 20px;
  font-size:.82rem;font-weight:600;gap:7px;
  box-shadow:var(--shadow-lg);align-items:center;font-family:var(--font);cursor:pointer;
}

/* RESPONSIVE */
@media(max-width:760px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0);box-shadow:var(--shadow-lg)}
  .main-wrap{margin-left:0}
  .content{padding:18px 15px}
  .topbar{padding:0 15px}
  .mob-sidebar-toggle{display:flex}
  .quick-grid{grid-template-columns:1fr 1fr}
  .res-img-wrap{flex-direction:column}
  .res-img-prev{max-width:100%}
  .proc-card{padding:28px 22px}
}
@media(max-width:400px){
  .quick-grid{grid-template-columns:1fr}
  .leaf{font-size:.76rem;padding:6px 10px}
  .proc-card{padding:24px 18px}
}
</style>
</head>
<body>
<div id="toast-container"></div>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <div class="sidebar-logo">
        <div class="logo-gem"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        JennieAI
      </div>
      <div class="token-sidebar">
        <div class="tok-row">
          <span class="tok-label"><i class="fa-solid fa-bolt"></i> Tokens</span>
          <span class="tok-count" id="sbTokCount"><?= $tokens_left ?> / <?= $tokens_max ?></span>
        </div>
        <div class="tok-bar-bg">
          <div class="tok-bar-fill" id="sbTokBar" style="width:<?= $token_pct ?>%"></div>
        </div>
        <div class="tok-reset"><i class="fa-regular fa-clock"></i>&nbsp;Resets in <span id="sbResetTimer">—</span></div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-sect">Workspace</div>
      <div class="nav-item active" onclick="scrollTo('tools')">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Image Studio
      </div>
      <div class="nav-item" onclick="scrollTo('history')">
        <i class="fa-solid fa-clock-rotate-left"></i> Recent sessions
      </div>

      <div class="nav-sect">Tools</div>
      <div class="nav-item" onclick="triggerBranch('compress')">
        <i class="fa-solid fa-compress"></i> Compression
      </div>
      <div class="nav-item" onclick="triggerBranch('convert')">
        <i class="fa-solid fa-repeat"></i> Format Converter
      </div>
      <div class="nav-item" onclick="triggerBranch('title')">
        <i class="fa-solid fa-pen-nib"></i> Title Generator
      </div>

      <div class="nav-div"></div>
      <div class="nav-sect">Account</div>
      <div class="nav-item" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'">
        <i class="fa-solid fa-user"></i> My Profile
      </div>
      <?php if ($is_pro): ?>
      <div class="nav-item" style="color:var(--gold)">
        <i class="fa-solid fa-crown" style="color:var(--gold)"></i> Pro Member
        <span class="nav-badge gold">PRO</span>
      </div>
      <?php else: ?>
      <div class="nav-item" onclick="location.href='/membership'">
        <i class="fa-solid fa-crown"></i> Upgrade to Pro
        <span class="nav-badge">NEW</span>
      </div>
      <?php endif; ?>
      <div class="nav-item" onclick="location.href='/auth/logout'">
        <i class="fa-solid fa-right-from-bracket"></i> Sign out
      </div>
    </nav>

    <div class="sidebar-bottom">
      <div class="user-pill" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'">
        <div class="u-avatar">
          <?php if (!empty($me['profile_image'])): ?>
          <img src="<?= htmlspecialchars($me['profile_image']) ?>" alt="">
          <?php else: ?>
          <?= strtoupper(substr($first_name, 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div class="u-name"><?= htmlspecialchars($first_name) ?></div>
          <div class="u-role <?= $is_pro ? 'pro' : '' ?>"><?= $is_pro ? '✦ Pro member' : 'Free plan' ?></div>
        </div>
        <i class="fa-solid fa-chevron-right" style="font-size:.62rem;color:var(--ink-4)"></i>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-wrap">
    <header class="topbar">
      <button class="tb-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" style="display:none">
        <i class="fa-solid fa-bars"></i>
      </button>
      <span class="topbar-title">Image Studio</span>
      <div class="topbar-right">
        <div class="tok-pill">
          <i class="fa-solid fa-bolt"></i>
          <span id="tbTokCount"><?= $tokens_left ?></span>&nbsp;tokens
        </div>
        <?php if ($is_pro): ?>
        <div class="tb-btn" style="color:var(--gold);border-color:rgba(180,83,9,.25);background:var(--gold-lt)" title="Pro">
          <i class="fa-solid fa-crown"></i>
        </div>
        <?php endif; ?>
        <button class="tb-btn" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'" title="Profile">
          <i class="fa-solid fa-user"></i>
        </button>
      </div>
    </header>

    <div class="content">

      <!-- WELCOME -->
      <div class="welcome-banner">
        <div class="welcome-greeting"><?= htmlspecialchars($welcome_line) ?></div>
        <div class="welcome-sub"><?= htmlspecialchars($welcome_sub) ?></div>
        <div class="status-row">
          <span class="s-dot"></span>
          <span class="s-text">All systems active</span>
          <span class="s-sep">·</span>
          <span class="s-text">JennieAI v2.1</span>
          <?php if ($is_pro): ?>
          <span class="s-sep">·</span>
          <span class="s-pro"><i class="fa-solid fa-crown"></i> Pro</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- TOKEN EXHAUSTED -->
      <div class="tok-exhausted <?= $tokens_left <= 0 ? 'show' : '' ?>" id="tokExhausted">
        <div class="te-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div>
          <div class="te-title">Tokens exhausted</div>
          <div class="te-desc">You have used all your available tokens for this session. Your allowance renews automatically every 2 hours — no action needed.</div>
          <div class="te-timer">Next reset in: <strong id="exhaustedTimer">calculating…</strong></div>
        </div>
      </div>

      <!-- QUICK TOOLS -->
      <div id="tools">
        <div class="sec-head"><span class="sec-title">Quick tools</span></div>
        <div class="quick-grid">
          <div class="quick-card c-blue" onclick="triggerBranch('compress');addRipple(event,this)">
            <div class="qc-icon c-blue"><i class="fa-solid fa-compress"></i></div>
            <div class="qc-name">Compress Image</div>
            <div class="qc-desc">Reduce file size, keep quality</div>
            <div class="qc-cost">From <span>4 tokens</span></div>
          </div>
          <div class="quick-card c-green" onclick="triggerBranch('convert');addRipple(event,this)">
            <div class="qc-icon c-green"><i class="fa-solid fa-repeat"></i></div>
            <div class="qc-name">Convert Format</div>
            <div class="qc-desc">JPG, PNG, WebP — convert freely</div>
            <div class="qc-cost">From <span>3 tokens</span></div>
          </div>
          <div class="quick-card c-amber" onclick="triggerBranch('title');addRipple(event,this)">
            <div class="qc-icon c-amber"><i class="fa-solid fa-pen-nib"></i></div>
            <div class="qc-name">Generate Title</div>
            <div class="qc-desc">Titles, SEO copy, social captions</div>
            <div class="qc-cost">From <span>6 tokens</span></div>
          </div>
          <div class="quick-card c-purple" onclick="toast('Auto Enhance is coming soon.','info')">
            <div class="qc-icon c-purple"><i class="fa-solid fa-sliders"></i></div>
            <div class="qc-name">Auto Enhance</div>
            <div class="qc-desc">Brightness, contrast &amp; colour grade</div>
            <div class="qc-cost">From <span>10 tokens</span> · <span style="color:var(--ink-4)">soon</span></div>
          </div>
        </div>
      </div>

      <!-- UPLOAD -->
      <div style="margin-bottom:24px">
        <div class="sec-head"><span class="sec-title">Upload image</span></div>
        <div class="upload-zone" id="uploadZone">
          <input type="file" id="fileInput" accept="image/*" <?= $tokens_left <= 0 ? 'disabled' : '' ?>>
          <div class="upload-icon-wrap"><i class="fa-solid fa-cloud-arrow-up"></i></div>
          <h3>Drop your image here, or tap to browse</h3>
          <p>Supports JPG, PNG, WebP, HEIC &amp; GIF</p>
          <div class="upload-chips">
            <span class="uchip"><i class="fa-solid fa-lock"></i> Private</span>
            <span class="uchip"><i class="fa-solid fa-bolt"></i> Instant processing</span>
            <span class="uchip">Max 50 MB</span>
          </div>
        </div>
        <div class="file-preview" id="filePreview">
          <div class="prev-thumb"><img id="prevThumb" src="" alt=""></div>
          <div class="prev-meta">
            <div class="prev-name" id="prevName">—</div>
            <div class="prev-chips" id="prevChips"></div>
          </div>
          <button class="prev-close" onclick="clearFile()" title="Remove file">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <!-- COMMANDS -->
      <div style="margin-bottom:24px">
        <div class="sec-head"><span class="sec-title">Processing mode</span></div>
        <div class="branch-grid">

          <!-- COMPRESSION -->
          <div class="branch-card" id="branch-compress">
            <div class="branch-hd" onclick="toggleBranch('compress')">
              <div class="branch-ic c-blue"><i class="fa-solid fa-compress"></i></div>
              <div class="branch-lbl">
                <div class="branch-name">Image Compression</div>
                <div class="branch-desc">Reduce file size without losing visible quality</div>
              </div>
              <span class="branch-cost"><i class="fa-solid fa-bolt"></i> 4–5 tokens</span>
              <i class="fa-solid fa-chevron-down branch-chev"></i>
            </div>
            <div class="branch-body">
              <div class="branch-inner">
                <div class="leaf-row">
                  <div class="leaf" data-tool="compress-jpg" onclick="selectLeaf(this,'compress-jpg','compress')">
                    <i class="fa-solid fa-image"></i> To JPG <span class="leaf-cost">4 tk</span>
                  </div>
                  <div class="leaf" data-tool="compress-webp" onclick="selectLeaf(this,'compress-webp','compress')">
                    <i class="fa-brands fa-chrome"></i> To WebP <span class="leaf-cost">4 tk</span>
                  </div>
                  <div class="leaf" data-tool="compress-png" onclick="selectLeaf(this,'compress-png','compress')">
                    <i class="fa-regular fa-image"></i> To PNG <span class="leaf-cost">5 tk</span>
                  </div>
                </div>
                <div class="quality-row" id="quality-compress">
                  <label>Quality</label>
                  <input type="range" id="qualitySlider" min="10" max="100" value="80"
                    oninput="document.getElementById('qualityDisplay').textContent=this.value+'%'">
                  <span class="quality-val" id="qualityDisplay">80%</span>
                </div>
                <button class="run-btn" id="run-compress" onclick="runTool()">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> Process image
                </button>
              </div>
            </div>
          </div>

          <!-- CONVERSION -->
          <div class="branch-card" id="branch-convert">
            <div class="branch-hd" onclick="toggleBranch('convert')">
              <div class="branch-ic c-green"><i class="fa-solid fa-repeat"></i></div>
              <div class="branch-lbl">
                <div class="branch-name">Format Conversion</div>
                <div class="branch-desc">Convert to a different image format</div>
              </div>
              <span class="branch-cost"><i class="fa-solid fa-bolt"></i> 3–5 tokens</span>
              <i class="fa-solid fa-chevron-down branch-chev"></i>
            </div>
            <div class="branch-body">
              <div class="branch-inner">
                <div class="leaf-row">
                  <div class="leaf" data-tool="jpg-to-webp" onclick="selectLeaf(this,'jpg-to-webp','convert')">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Any → WebP <span class="leaf-cost">3 tk</span>
                  </div>
                  <div class="leaf" data-tool="any-to-jpg" onclick="selectLeaf(this,'any-to-jpg','convert')">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Any → JPG <span class="leaf-cost">3 tk</span>
                  </div>
                  <div class="leaf" data-tool="any-to-png" onclick="selectLeaf(this,'any-to-png','convert')">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Any → PNG <span class="leaf-cost">5 tk</span>
                  </div>
                </div>
                <button class="run-btn" id="run-convert" onclick="runTool()">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> Process image
                </button>
              </div>
            </div>
          </div>

          <!-- TITLE GENERATOR -->
          <div class="branch-card" id="branch-title">
            <div class="branch-hd" onclick="toggleBranch('title')">
              <div class="branch-ic c-amber"><i class="fa-solid fa-pen-nib"></i></div>
              <div class="branch-lbl">
                <div class="branch-name">Title &amp; Caption Generator</div>
                <div class="branch-desc">Creative titles, SEO metadata, and social captions</div>
              </div>
              <span class="branch-cost"><i class="fa-solid fa-bolt"></i> 6–8 tokens</span>
              <i class="fa-solid fa-chevron-down branch-chev"></i>
            </div>
            <div class="branch-body">
              <div class="branch-inner">
                <div class="leaf-row">
                  <div class="leaf" data-tool="title-photo" onclick="selectLeaf(this,'title-photo','title')">
                    <i class="fa-solid fa-camera"></i> Photo Title <span class="leaf-cost">6 tk</span>
                  </div>
                  <div class="leaf" data-tool="title-seo" onclick="selectLeaf(this,'title-seo','title')">
                    <i class="fa-solid fa-magnifying-glass"></i> SEO + Alt Text <span class="leaf-cost">8 tk</span>
                  </div>
                  <div class="leaf" data-tool="title-social" onclick="selectLeaf(this,'title-social','title')">
                    <i class="fa-brands fa-instagram"></i> Social Caption <span class="leaf-cost">7 tk</span>
                  </div>
                </div>
                <button class="run-btn" id="run-title" onclick="runTool()">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> Generate response
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- RESPONSE -->
      <div class="response-area" id="responseArea">
        <div class="sec-head"><span class="sec-title">Response</span></div>
        <div class="response-card" id="responseCard"></div>
      </div>

      <!-- HISTORY -->
      <div id="history" style="margin-bottom:60px">
        <div class="sec-head"><span class="sec-title">Recent sessions</span></div>
        <div class="hist-list" id="historyList">
          <?php if (empty($history)): ?>
          <div class="hist-empty">No sessions yet. Run your first tool above.</div>
          <?php else: ?>
          <?php foreach ($history as $h): ?>
          <div class="hist-item">
            <div class="hist-ic"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="hist-lbl"><?= htmlspecialchars($h['label']) ?></div>
            <div class="hist-time"><?= date('d M, H:i', strtotime($h['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main-wrap -->
</div><!-- /layout -->

<!-- PROCESSING OVERLAY -->
<div class="proc-overlay" id="procOverlay">
  <div class="proc-card">
    <div class="proc-spinner">
      <div class="proc-ring"></div>
      <div class="proc-ring2"></div>
      <div class="proc-gem"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
    </div>
    <div class="proc-title">JennieAI is working</div>
    <div class="proc-msg" id="procMsg">Preparing your request…</div>
    <div class="proc-steps-list" id="procStepsList"></div>
    <div class="proc-bar-bg">
      <div class="proc-bar-fill" id="procBar"></div>
    </div>
  </div>
</div>

<!-- MOBILE TOGGLE -->
<button class="mob-sidebar-toggle" id="mobToggle" onclick="toggleSidebar()">
  <i class="fa-solid fa-bars"></i> Menu
</button>

<script>
const MANIFEST_URL = 'manifest.php';
const CFG = {
  tokensLeft: <?= $tokens_left ?>,
  tokensMax:  <?= $tokens_max ?>,
  resetAt:    <?= $reset_at_ts ?> * 1000,
  userId:     <?= $uid ?>,
  isPro:      <?= $is_pro ? 'true' : 'false' ?>,
};

const TOOL_COSTS = {
  'compress-jpg':4,'compress-webp':4,'compress-png':5,
  'jpg-to-webp':3,'any-to-jpg':3,'any-to-png':5,
  'title-photo':6,'title-seo':8,'title-social':7
};

const PROC_STEPS = {
  'compress-jpg'  :['Analysing image structure','Optimising colour channels','Applying compression','Finalising output'],
  'compress-webp' :['Analysing image structure','Preparing WebP encoder','Compression pass','Finalising output'],
  'compress-png'  :['Reading image layers','Running lossless pass','Optimising PNG','Finalising output'],
  'jpg-to-webp'   :['Reading source format','Initialising converter','Transcoding to WebP','Packaging result'],
  'any-to-jpg'    :['Reading source format','Flattening transparency','Encoding to JPEG','Packaging result'],
  'any-to-png'    :['Reading source format','Preserving alpha channel','Encoding to PNG','Packaging result'],
  'title-photo'   :['Analysing visual composition','Reading colour palette','Generating candidates','Ranking results'],
  'title-seo'     :['Scanning image metadata','Building keyword model','Composing SEO copy','Writing alt text'],
  'title-social'  :['Detecting mood and tone','Matching caption style','Adding hashtags','Finalising caption'],
};
const PROC_MSGS = {
  upload:         ['Receiving your image…','Scanning file…','Preparing workspace…'],
  'compress-jpg': ['Measuring pixel density…','Applying JPEG compression…','Optimising output…'],
  'compress-webp':['Starting WebP engine…','Compressing data streams…','Packaging result…'],
  'compress-png': ['Reading colour map…','Running lossless optimiser…','Writing PNG…'],
  'jpg-to-webp':  ['Transcoding pixel data…','Applying WebP encoding…','Wrapping output…'],
  'any-to-jpg':   ['Flattening alpha layer…','Encoding to JPEG…','Preparing result…'],
  'any-to-png':   ['Preserving transparency…','Encoding to PNG…','Finalising…'],
  'title-photo':  ['Parsing composition…','Generating title options…','Selecting best matches…'],
  'title-seo':    ['Extracting image features…','Building metadata model…','Writing SEO copy…'],
  'title-social': ['Reading image mood…','Crafting caption…','Adding hashtag set…'],
};

let loadedFile=null, currentTool=null, currentBranch=null, manifestCache=null;
let tokensLeft=CFG.tokensLeft, _resultLines=[];

/* HELPERS */
function fmt(b){if(b<1024)return b+' B';if(b<1048576)return(b/1024).toFixed(1)+' KB';return(b/1048576).toFixed(2)+' MB'}
function esc(s){const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML}
function toast(msg,type='info',dur=3200){
  const c=document.getElementById('toast-container');
  const d=document.createElement('div');
  d.className='toast '+type;
  const ic={success:'fa-circle-check',error:'fa-circle-exclamation',info:'fa-circle-info'};
  d.innerHTML=`<i class="fa-solid ${ic[type]||ic.info}"></i><span></span>`;
  d.querySelector('span').textContent=msg;
  c.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='opacity .3s';setTimeout(()=>d.remove(),300)},dur);
}
function addRipple(e,el){
  const r=el.getBoundingClientRect();
  const s=document.createElement('span');s.className='ripple';
  const sz=Math.max(r.width,r.height)*.8;
  s.style.cssText=`width:${sz}px;height:${sz}px;left:${e.clientX-r.left-sz/2}px;top:${e.clientY-r.top-sz/2}px;position:absolute;`;
  el.style.position='relative';el.style.overflow='hidden';
  el.appendChild(s);setTimeout(()=>s.remove(),600);
}

/* TIMER */
function updateTimers(){
  const diff=Math.max(0,CFG.resetAt-Date.now());
  const h=Math.floor(diff/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
  const str=`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  ['sbResetTimer','exhaustedTimer'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=str});
}
setInterval(updateTimers,1000);updateTimers();

/* TOKEN UI */
function updateTokenUI(n){
  tokensLeft=n;
  const pct=Math.max(0,Math.round((n/CFG.tokensMax)*100));
  const bar=document.getElementById('sbTokBar');
  const sbC=document.getElementById('sbTokCount');
  const tbC=document.getElementById('tbTokCount');
  if(bar)bar.style.width=pct+'%';
  if(sbC)sbC.textContent=n+' / '+CFG.tokensMax;
  if(tbC)tbC.textContent=n;
  if(bar){
    if(pct<=20)bar.style.background='linear-gradient(90deg,#dc2626,#ef4444)';
    else if(pct<=50)bar.style.background='linear-gradient(90deg,#d97706,#f59e0b)';
    else bar.style.background='linear-gradient(90deg,#2563eb,#7c3aed)';
  }
  document.getElementById('tokExhausted').classList.toggle('show',n<=0);
}

/* SIDEBAR */
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open')}
function scrollTo(id){const el=document.getElementById(id);if(el)el.scrollIntoView({behavior:'smooth',block:'start'})}
function triggerBranch(id){
  const card=document.getElementById('branch-'+id);if(!card)return;
  if(!card.classList.contains('expanded'))toggleBranch(id);
  card.scrollIntoView({behavior:'smooth',block:'center'});
  if(window.innerWidth<=760)document.getElementById('sidebar').classList.remove('open');
}

/* MOBILE TOGGLE SHOW */
(function checkMobile(){
  const btn=document.getElementById('sidebarToggleBtn');
  if(window.innerWidth<=760&&btn)btn.style.display='flex';
})();
window.addEventListener('resize',function(){
  const btn=document.getElementById('sidebarToggleBtn');
  if(btn)btn.style.display=window.innerWidth<=760?'flex':'none';
});

/* UPLOAD */
const uploadZone=document.getElementById('uploadZone');
const fileInput=document.getElementById('fileInput');
uploadZone.addEventListener('dragover',e=>{e.preventDefault();uploadZone.classList.add('drag-over')});
uploadZone.addEventListener('dragleave',()=>uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop',e=>{e.preventDefault();uploadZone.classList.remove('drag-over');if(e.dataTransfer.files[0])handleFile(e.dataTransfer.files[0])});
fileInput.addEventListener('change',()=>{if(fileInput.files[0])handleFile(fileInput.files[0])});

function handleFile(file){
  if(!file.type.startsWith('image/')){toast('Please upload an image file.','error');return}
  if(file.size>50*1024*1024){toast('File too large — max 50 MB.','error');return}
  if(tokensLeft<=0){toast('No tokens remaining. Your allowance resets shortly.','error');return}
  loadedFile=file;currentTool=null;
  simulateUpload(file);
}

function simulateUpload(file){
  const steps=[
    {msg:'Sending image to JennieAI…',pct:18},
    {msg:'Verifying file integrity…',pct:42},
    {msg:'Building your workspace…',pct:70},
    {msg:'Image accepted and ready.', pct:100},
  ];
  showProcessing('upload');
  let i=0;
  (function run(){
    if(i>=steps.length){hideProcessing();revealPreview(file);return}
    const st=steps[i++];
    document.getElementById('procMsg').textContent=st.msg;
    document.getElementById('procBar').style.width=st.pct+'%';
    setTimeout(run,380+Math.random()*520);
  })();
}

function revealPreview(file){
  const reader=new FileReader();
  reader.onload=e=>{
    document.getElementById('prevThumb').src=e.target.result;
    document.getElementById('prevName').textContent=file.name;
    const img=new Image();
    img.onload=()=>{
      document.getElementById('prevChips').innerHTML=`
        <span class="pchip">${fmt(file.size)}</span>
        <span class="pchip">${img.naturalWidth}×${img.naturalHeight}px</span>
        <span class="pchip">${(file.type||'image').replace('image/','').toUpperCase()}</span>`;
    };
    img.src=e.target.result;
    document.getElementById('filePreview').classList.add('show');
    toast('Image received. Select a processing mode below.','success');
  };
  reader.readAsDataURL(file);
  clearResponse();
}

function clearFile(){
  loadedFile=null;currentTool=null;
  document.getElementById('filePreview').classList.remove('show');
  document.getElementById('prevThumb').src='';fileInput.value='';
  clearResponse();
  document.querySelectorAll('.leaf').forEach(l=>l.classList.remove('selected'));
  document.querySelectorAll('.run-btn').forEach(b=>b.classList.remove('show'));
  document.querySelectorAll('.quality-row').forEach(q=>q.classList.remove('show'));
}

/* BRANCH */
function toggleBranch(id){
  const card=document.getElementById('branch-'+id);
  const isOpen=card.classList.contains('expanded');
  document.querySelectorAll('.branch-card').forEach(c=>c.classList.remove('expanded'));
  if(!isOpen)card.classList.add('expanded');
}

/* LEAF */
function selectLeaf(el,toolId,branch){
  document.querySelectorAll('.leaf').forEach(l=>l.classList.remove('selected'));
  el.classList.add('selected');
  currentTool=toolId;currentBranch=branch;clearResponse();
  document.querySelectorAll('.quality-row').forEach(q=>q.classList.remove('show'));
  if(toolId.startsWith('compress'))document.getElementById('quality-compress').classList.add('show');
  document.querySelectorAll('.run-btn').forEach(b=>b.classList.remove('show'));
  document.getElementById('run-'+branch).classList.add('show');
  if(!loadedFile)toast('Upload an image first, then run the tool.','info');
}

/* PROCESSING */
function showProcessing(toolId){
  const ov=document.getElementById('procOverlay');
  const sl=document.getElementById('procStepsList');
  const bar=document.getElementById('procBar');
  ov.classList.add('show');bar.style.width='0%';
  const steps=PROC_STEPS[toolId]||['Preparing…','Processing…','Finalising…','Done'];
  sl.innerHTML=steps.map(s=>`<div class="pstep"><span class="pstep-dot"></span>${esc(s)}</div>`).join('');
}
function advanceProc(stepIdx,progress){
  const steps=document.querySelectorAll('.pstep');
  const msgs=PROC_MSGS[currentTool]||PROC_MSGS['upload'];
  steps.forEach((el,i)=>{
    el.classList.remove('done','active');
    if(i<stepIdx)el.classList.add('done');
    else if(i===stepIdx)el.classList.add('active');
  });
  document.getElementById('procBar').style.width=progress+'%';
  const mi=Math.min(stepIdx,msgs.length-1);
  document.getElementById('procMsg').textContent=msgs[mi];
}
function hideProcessing(){
  document.getElementById('procOverlay').classList.remove('show');
  document.getElementById('procStepsList').innerHTML='';
  document.getElementById('procBar').style.width='0%';
}

/* MANIFEST + TOOL LOAD */
async function fetchManifest(){
  if(manifestCache)return manifestCache;
  const res=await fetch(MANIFEST_URL);
  if(!res.ok)throw new Error('Service unavailable. Please try again.');
  manifestCache=await res.json();return manifestCache;
}
async function loadTool(toolId){
  if((window.JennieTools||{})[toolId])return;
  const manifest=await fetchManifest();
  const url=manifest.tools[toolId];
  if(!url)throw new Error('This tool is temporarily unavailable.');
  await new Promise((res,rej)=>{
    const s=document.createElement('script');
    s.src=url;s.onload=res;
    s.onerror=()=>rej(new Error('Could not reach JennieAI. Check your connection.'));
    document.head.appendChild(s);
  });
}

/* RUN */
async function runTool(){
  if(!loadedFile){toast('Please upload an image first.','error');return}
  if(!currentTool){toast('Please select a processing mode.','error');return}
  if(tokensLeft<=0){toast('No tokens remaining. Your allowance resets automatically.','error');return}
  const cost=TOOL_COSTS[currentTool]||5;
  if(tokensLeft<cost){toast(`This needs ${cost} tokens. You have ${tokensLeft} left.`,'error');return}
  clearResponse();

  showProcessing(currentTool);
  const total=(PROC_STEPS[currentTool]||['','','','']).length;

  try{
    // Animate steps
    for(let i=0;i<total;i++){
      advanceProc(i,Math.round(((i+1)/total)*85));
      await new Promise(r=>setTimeout(r,400+Math.random()*650));
    }
    advanceProc(total,100);
    await new Promise(r=>setTimeout(r,320));

    await loadTool(currentTool);
    const fn=(window.JennieTools||{})[currentTool];
    if(typeof fn!=='function')throw new Error('Processing engine unavailable. Please try again.');

    const quality=parseInt(document.getElementById('qualitySlider')?.value||'80')/100;
    const result=await fn(loadedFile,{quality});

    hideProcessing();
    await deductTokens(cost);
    renderResponse(result,cost);
    logHistory(currentTool,result.label||currentTool);

  }catch(err){
    hideProcessing();
    toast(err.message,'error');
  }
}

async function deductTokens(cost){
  try{
    const res=await fetch('jennie_deduct_tokens',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cost,tool:currentTool})});
    const data=await res.json();
    if(data.tokens_left!==undefined)updateTokenUI(data.tokens_left);
  }catch{/* silent */}
}
async function logHistory(toolId,label){
  try{await fetch('jennie_log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tool_id:toolId,label})})}
  catch{/* silent */}
}

/* RENDER RESPONSE */
function renderResponse(result,tokensUsed){
  const area=document.getElementById('responseArea');
  const card=document.getElementById('responseCard');
  area.classList.add('show');card.innerHTML='';

  const hd=document.createElement('div');hd.className='res-hd';
  hd.innerHTML=`
    <div class="res-ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> JennieAI</div>
    <div class="res-title">${esc(result.label||'Result')}</div>
    <div class="res-tok-used"><i class="fa-solid fa-bolt"></i> ${tokensUsed} tokens</div>`;
  card.appendChild(hd);

  if(result.type==='image'&&result.blob){
    const url=URL.createObjectURL(result.blob);
    const saved=loadedFile.size-result.blob.size;
    const pct=((saved/loadedFile.size)*100).toFixed(1);
    const body=document.createElement('div');body.className='res-img-wrap';
    body.innerHTML=`
      <div class="res-img-prev"><img src="${url}" alt="Output"></div>
      <div class="res-stats">
        <div class="res-stat-grid">
          <div class="res-stat-box">
            <div class="res-stat-num">${fmt(loadedFile.size)}</div>
            <div class="res-stat-lbl">Original</div>
          </div>
          <div class="res-stat-box">
            <div class="res-stat-num">${fmt(result.blob.size)}</div>
            <div class="res-stat-lbl">Result</div>
          </div>
          <div class="res-stat-box">
            <div class="res-stat-num ${saved>0?'green':''}">${saved>0?'-'+pct+'%':'+'+Math.abs(pct)+'%'}</div>
            <div class="res-stat-lbl">Size change</div>
          </div>
          ${result.width?`<div class="res-stat-box"><div class="res-stat-num">${result.width}×${result.height}</div><div class="res-stat-lbl">Dimensions</div></div>`:''}
        </div>
      </div>`;
    card.appendChild(body);
    const ext=result.ext||'jpg';
    const acts=document.createElement('div');acts.className='post-acts';
    acts.innerHTML=`
      <button class="act-btn primary" onclick="downloadBlob('${url}','jennie-output.${ext}')">
        <i class="fa-solid fa-download"></i> Download
      </button>
      ${currentTool.startsWith('compress')?`
        <button class="act-btn ghost" onclick="nudgeQuality(-10)"><i class="fa-solid fa-arrow-down"></i> More compression</button>
        <button class="act-btn ghost" onclick="nudgeQuality(+10)"><i class="fa-solid fa-arrow-up"></i> Better quality</button>
      `:''}
      <button class="act-btn ghost" onclick="reprocessResult('${url}','${ext}',${result.blob.size})">
        <i class="fa-solid fa-rotate"></i> Re-process
      </button>
      <button class="act-btn ghost" onclick="clearResponse()"><i class="fa-solid fa-xmark"></i> Close</button>`;
    card.appendChild(acts);
  }

  if(result.type==='text'&&result.lines){
    _resultLines=result.lines;
    const body=document.createElement('div');body.className='res-text-wrap';
    body.innerHTML=result.lines.map((line,i)=>`
      <div class="res-line">
        <div class="res-line-text">${esc(line)}</div>
        <button class="copy-sm" onclick="copyLine(${i})"><i class="fa-solid fa-copy"></i> Copy</button>
      </div>`).join('');
    card.appendChild(body);
    const acts=document.createElement('div');acts.className='post-acts';
    acts.innerHTML=`
      <button class="act-btn primary" onclick="copyAll()"><i class="fa-solid fa-copy"></i> Copy all</button>
      <button class="act-btn ghost" onclick="clearResponse()"><i class="fa-solid fa-xmark"></i> Close</button>`;
    card.appendChild(acts);
  }

  card.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function clearResponse(){
  document.getElementById('responseArea').classList.remove('show');
  document.getElementById('responseCard').innerHTML='';
}

/* HELPERS */
function downloadBlob(url,name){const a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();document.body.removeChild(a)}
function nudgeQuality(d){const s=document.getElementById('qualitySlider');if(!s)return;s.value=Math.max(10,Math.min(100,parseInt(s.value)+d));document.getElementById('qualityDisplay').textContent=s.value+'%';runTool()}
function reprocessResult(url,ext,size){fetch(url).then(r=>r.blob()).then(b=>{const f=new File([b],'reprocess.'+ext,{type:b.type});handleFile(f)})}
function copyLine(i){navigator.clipboard.writeText(_resultLines[i]).then(()=>toast('Copied!','success'))}
function copyAll(){navigator.clipboard.writeText(_resultLines.join('\n')).then(()=>toast('All copied!','success'))}
</script>
</body>
</html>
