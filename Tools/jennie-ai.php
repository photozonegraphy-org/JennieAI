<?php
session_start();
include "auth/db.php";
include "auth/security.php";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = '/jennie-ai';
    header('Location: /auth/login'); exit;
}
$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, full_name, username, profile_image, is_verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid); $stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
if (!$me) { header('Location: /auth/login'); exit; }

// Token system
$tok = $conn->prepare("SELECT * FROM jennie_tokens WHERE user_id = ? LIMIT 1");
$tok->bind_param("i", $uid); $tok->execute();
$tok_row = $tok->get_result()->fetch_assoc();
$DEFAULT = 120;
if (!$tok_row) {
    $ra = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $ins = $conn->prepare("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES(?,?,?,?)");
    $ins->bind_param("iiis", $uid, $DEFAULT, $DEFAULT, $ra); $ins->execute();
    $tok_row = ['tokens_left'=>$DEFAULT,'tokens_max'=>$DEFAULT,'reset_at'=>$ra];
}
if (strtotime($tok_row['reset_at']) <= time()) {
    $ra2 = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $conn->prepare("UPDATE jennie_tokens SET tokens_left=tokens_max,reset_at=?,updated_at=NOW() WHERE user_id=?")->bind_param("si",$ra2,$uid);
    $conn->query("UPDATE jennie_tokens SET tokens_left=tokens_max,reset_at='$ra2',updated_at=NOW() WHERE user_id=$uid");
    $tok_row['tokens_left']=$tok_row['tokens_max']; $tok_row['reset_at']=$ra2;
}
$tokens_left=(int)$tok_row['tokens_left']; $tokens_max=(int)$tok_row['tokens_max'];
$reset_ts=strtotime($tok_row['reset_at']);
$tok_pct=$tokens_max>0?round(($tokens_left/$tokens_max)*100):0;

// History
$history=[];
$hs=$conn->prepare("SELECT tool_id,label,created_at FROM jennie_history WHERE user_id=? ORDER BY id DESC LIMIT 6");
if($hs){$hs->bind_param("i",$uid);$hs->execute();$hr=$hs->get_result();while($r=$hr->fetch_assoc())$history[]=$r;}

$first_name = explode(' ', trim($me['full_name']))[0];
$is_pro = (bool)$me['is_verified'];

$welcomes=[
    ["Good to see you, {name}.","Your workspace is ready. What are we working on today?"],
    ["Welcome back, {name}.","Drop an image and I will get started right away."],
    ["Hey {name} — let's create something.","Upload a photo or pick a tool below to begin."],
    ["{name}, ready when you are.","Your session is active. Let's make something great."],
    ["Back again, {name}.","Pick up where you left off or start something new."],
];
$w=$welcomes[crc32($first_name.date('Ymd'))%count($welcomes)];
$wline=str_replace('{name}',$first_name,$w[0]);
$wsub=$w[1];

// CDN base — the domain where all tool JS files are hosted
$CDN = 'https://ai.photozonegraphy.com/Tools';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>JennieAI — Image Intelligence Studio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{
  --bg:#f5f4f1;--surface:#fff;--surface-2:#f9f8f6;--surface-3:#f0efe9;
  --border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.13);
  --ink:#111110;--ink-2:#3a3935;--ink-3:#6b6a65;--ink-4:#9e9d99;
  --accent:#2563eb;--accent-lt:#eff4ff;
  --gold:#b45309;--gold-lt:#fef3c7;
  --green:#16a34a;--green-lt:#f0fdf4;
  --red:#dc2626;--red-lt:#fef2f2;
  --amber:#d97706;--amber-lt:#fffbeb;
  --purple:#7c3aed;--purple-lt:#f5f3ff;
  --r:14px;--rsm:9px;--rxs:6px;
  --sh-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --sh-md:0 4px 16px rgba(0,0,0,.08),0 1px 4px rgba(0,0,0,.04);
  --sh-lg:0 12px 40px rgba(0,0,0,.12),0 2px 8px rgba(0,0,0,.06);
  --sidebar:268px;--topbar:60px;
  --font:'Inter',system-ui,sans-serif;
  --serif:'DM Serif Display',Georgia,serif;
  --tr:.2s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--bg);color:var(--ink);min-height:100vh;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button{font-family:var(--font);cursor:pointer;border:none;background:none}

/* LAYOUT */
.layout{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:200;overflow:hidden;transition:transform var(--tr)}
.sb-top{padding:18px 15px 13px;border-bottom:1px solid var(--border)}
.sb-logo{display:flex;align-items:center;gap:9px;font-weight:700;font-size:1.05rem;color:var(--ink);margin-bottom:15px}
.logo-gem{width:31px;height:31px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.78rem;box-shadow:0 2px 8px rgba(37,99,235,.3)}
.tok-box{background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);border-radius:var(--rsm);padding:11px 12px}
.tok-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.tok-lbl{font-size:.7rem;font-weight:600;color:var(--accent);letter-spacing:.03em}
.tok-count{font-size:.78rem;font-weight:700;color:var(--ink)}
.tok-bg{height:5px;background:rgba(37,99,235,.12);border-radius:99px;overflow:hidden}
.tok-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#2563eb,#7c3aed);transition:width .8s cubic-bezier(.4,0,.2,1)}
.tok-reset{font-size:.65rem;color:var(--ink-4);margin-top:5px;display:flex;align-items:center;gap:3px}

.sb-nav{flex:1;overflow-y:auto;padding:7px 7px;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.nav-sect{font-size:.62rem;font-weight:700;letter-spacing:.12em;color:var(--ink-4);text-transform:uppercase;padding:11px 8px 4px}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 9px;border-radius:var(--rxs);font-size:.83rem;font-weight:500;color:var(--ink-3);cursor:pointer;transition:all var(--tr);margin-bottom:1px}
.nav-item:hover{background:var(--surface-3);color:var(--ink)}
.nav-item.active{background:var(--accent-lt);color:var(--accent)}
.nav-item i{width:15px;text-align:center;font-size:.8rem;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:.58rem;font-weight:700;padding:2px 6px;border-radius:99px}
.nav-badge.gold{background:var(--gold)}
.nav-div{height:1px;background:var(--border);margin:6px 0}

.sb-bottom{padding:9px 7px;border-top:1px solid var(--border)}
.u-pill{display:flex;align-items:center;gap:9px;padding:8px 9px;border-radius:var(--rsm);cursor:pointer;transition:background var(--tr)}
.u-pill:hover{background:var(--surface-3)}
.u-av{width:30px;height:30px;border-radius:50%;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,var(--accent),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700}
.u-av img{width:100%;height:100%;object-fit:cover}
.u-name{font-size:.8rem;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.u-role{font-size:.65rem;color:var(--ink-4)}
.u-role.pro{color:var(--gold);font-weight:600}

/* MAIN */
.main-wrap{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* TOPBAR */
.topbar{height:var(--topbar);background:rgba(255,255,255,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:13px;position:sticky;top:0;z-index:100}
.tb-title{font-size:.86rem;font-weight:600;color:var(--ink-2);flex:1}
.tb-right{display:flex;align-items:center;gap:8px}
.tb-tok{display:flex;align-items:center;gap:5px;background:var(--accent-lt);border:1px solid rgba(37,99,235,.18);border-radius:99px;padding:5px 12px;font-size:.75rem;font-weight:600;color:var(--accent)}
.tb-btn{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--ink-3);font-size:.82rem;transition:all var(--tr);border:1px solid var(--border);background:var(--surface)}
.tb-btn:hover{background:var(--surface-3);color:var(--ink)}

/* CONTENT */
.content{padding:28px 24px;max-width:840px;width:100%}

/* WELCOME */
.welcome{margin-bottom:26px;padding-bottom:20px;border-bottom:1px solid var(--border)}
.w-greeting{font-family:var(--serif);font-size:clamp(1.55rem,3vw,2.1rem);color:var(--ink);margin-bottom:4px;line-height:1.2}
.w-sub{font-size:.88rem;color:var(--ink-3)}
.status-row{display:flex;align-items:center;gap:6px;margin-top:11px;flex-wrap:wrap}
.s-dot{width:7px;height:7px;border-radius:50%;background:var(--green);animation:pDot 2.5s infinite}
@keyframes pDot{0%,100%{box-shadow:0 0 0 0 rgba(22,163,74,.4)}50%{box-shadow:0 0 0 5px rgba(22,163,74,0)}}
.s-text{font-size:.72rem;color:var(--ink-4);font-weight:500}
.s-sep{color:var(--border-md)}
.s-pro{display:inline-flex;align-items:center;gap:3px;background:var(--gold-lt);border:1px solid rgba(180,83,9,.25);color:var(--gold);border-radius:99px;font-size:.62rem;font-weight:700;padding:2px 8px}

/* TOKEN EXHAUSTED */
.tok-ex{display:none;background:var(--red-lt);border:1px solid rgba(220,38,38,.2);border-radius:var(--r);padding:16px 18px;margin-bottom:20px;align-items:flex-start;gap:12px}
.tok-ex.show{display:flex}
.te-icon{color:var(--red);font-size:1.1rem;flex-shrink:0;margin-top:2px}
.te-title{font-size:.88rem;font-weight:700;color:var(--red);margin-bottom:2px}
.te-desc{font-size:.79rem;color:var(--ink-3);line-height:1.6}
.te-timer{font-size:.75rem;font-weight:600;color:var(--ink);margin-top:6px}

/* SECTION HEAD */
.sec-hd{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.sec-ttl{font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-4)}
.sec-hd::after{content:'';flex:1;height:1px;background:var(--border)}

/* QUICK GRID */
.quick-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-bottom:22px}
.qc{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:14px 12px;cursor:pointer;transition:all var(--tr);box-shadow:var(--sh-sm);position:relative;overflow:hidden}
.qc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;transition:opacity .25s}
.qc:hover{border-color:var(--border-md);box-shadow:var(--sh-md);transform:translateY(-2px)}
.qc:hover::before{opacity:1}
.qc.c-blue::before{background:var(--accent)}
.qc.c-green::before{background:var(--green)}
.qc.c-amber::before{background:var(--amber)}
.qc.c-purple::before{background:var(--purple)}
.qc.c-rose::before{background:#e11d48}
.qci{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;margin-bottom:8px}
.qci.c-blue{background:var(--accent-lt);color:var(--accent)}
.qci.c-green{background:var(--green-lt);color:var(--green)}
.qci.c-amber{background:var(--amber-lt);color:var(--amber)}
.qci.c-purple{background:var(--purple-lt);color:var(--purple)}
.qci.c-rose{background:#fff1f2;color:#e11d48}
.qc-name{font-size:.8rem;font-weight:600;color:var(--ink-2);margin-bottom:2px}
.qc-desc{font-size:.68rem;color:var(--ink-4);line-height:1.4}
.qc-cost{font-size:.62rem;color:var(--ink-4);margin-top:6px}
.qc-cost span{color:var(--accent);font-weight:600}

/* UPLOAD */
.upload-zone{border:1.5px dashed var(--border-md);border-radius:var(--r);padding:34px 20px;text-align:center;cursor:pointer;transition:all .25s;background:var(--surface);position:relative;overflow:hidden}
.upload-zone::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(37,99,235,.04) 0%,transparent 65%);pointer-events:none}
.upload-zone:hover,.upload-zone.drag-over{border-color:var(--accent);background:var(--accent-lt)}
.upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.uz-icon{width:48px;height:48px;border-radius:12px;background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.15rem;color:var(--accent)}
.upload-zone h3{font-size:.93rem;font-weight:600;color:var(--ink-2);margin-bottom:4px}
.upload-zone p{font-size:.77rem;color:var(--ink-4)}
.uz-chips{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:9px;flex-wrap:wrap}
.uchip{display:inline-flex;align-items:center;gap:3px;background:var(--surface-3);border:1px solid var(--border);border-radius:99px;padding:2px 8px;font-size:.66rem;color:var(--ink-4)}

/* FILE PREVIEW */
.file-preview{display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:13px 14px;margin-top:11px;gap:13px;align-items:flex-start;animation:slideDown .3s ease}
.file-preview.show{display:flex}
@keyframes slideDown{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.prev-thumb{width:72px;height:72px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--surface-3)}
.prev-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.prev-meta{flex:1;min-width:0}
.prev-name{font-size:.82rem;font-weight:600;color:var(--ink);margin-bottom:2px;word-break:break-all}
.prev-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px}
.pchip{background:var(--surface-3);border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:.67rem;color:var(--ink-3)}
.prev-close{color:var(--ink-4);font-size:.85rem;cursor:pointer;transition:color var(--tr);padding:2px;flex-shrink:0}
.prev-close:hover{color:var(--red)}

/* BRANCH CARDS */
.branch-grid{display:flex;flex-direction:column;gap:8px;margin-bottom:22px}
.branch-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;transition:box-shadow var(--tr),border-color var(--tr);box-shadow:var(--sh-sm)}
.branch-card:hover{border-color:var(--border-md);box-shadow:var(--sh-md)}
.branch-card.expanded{border-color:rgba(37,99,235,.2)}
.branch-hd{display:flex;align-items:center;gap:10px;padding:12px 14px;cursor:pointer;user-select:none;transition:background var(--tr)}
.branch-hd:hover{background:var(--surface-2)}
.bic{width:33px;height:33px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0}
.bic.c-blue{background:var(--accent-lt);color:var(--accent)}
.bic.c-green{background:var(--green-lt);color:var(--green)}
.bic.c-amber{background:var(--amber-lt);color:var(--amber)}
.bic.c-purple{background:var(--purple-lt);color:var(--purple)}
.bic.c-rose{background:#fff1f2;color:#e11d48}
.blbl{flex:1}
.bname{font-size:.87rem;font-weight:600;color:var(--ink)}
.bdesc{font-size:.71rem;color:var(--ink-4);margin-top:1px}
.bcost{display:inline-flex;align-items:center;gap:3px;background:var(--accent-lt);border:1px solid rgba(37,99,235,.15);border-radius:99px;padding:2px 8px;font-size:.63rem;font-weight:600;color:var(--accent);white-space:nowrap}
.bchev{color:var(--ink-4);font-size:.7rem;transition:transform .3s cubic-bezier(.4,0,.2,1)}
.branch-card.expanded .bchev{transform:rotate(180deg)}
.branch-body{max-height:0;overflow:hidden;transition:max-height .38s cubic-bezier(.4,0,.2,1);border-top:0 solid var(--border)}
.branch-card.expanded .branch-body{max-height:480px;border-top-width:1px}
.branch-inner{padding:12px 14px;display:flex;flex-direction:column;gap:9px}

/* LEAVES */
.leaf-row{display:flex;flex-wrap:wrap;gap:5px}
.leaf{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:99px;border:1px solid var(--border);background:var(--surface-2);font-size:.8rem;font-weight:500;color:var(--ink-3);cursor:pointer;transition:all .18s;user-select:none}
.leaf:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}
.leaf.selected{border-color:var(--accent);color:var(--accent);background:var(--accent-lt);font-weight:600}
.leaf i{font-size:.73rem}
.lc{font-size:.6rem;background:rgba(37,99,235,.1);border-radius:99px;padding:1px 5px;color:var(--accent);font-weight:700}

/* QUALITY SLIDER */
.q-row{display:none;align-items:center;gap:10px}
.q-row.show{display:flex}
.q-row label{font-size:.75rem;font-weight:600;color:var(--ink-3);white-space:nowrap}
.q-row input[type="range"]{flex:1;accent-color:var(--accent);cursor:pointer;height:4px}
.q-val{font-size:.78rem;font-weight:700;color:var(--accent);min-width:30px;text-align:right}

/* RUN BTN */
.run-btn{display:none;width:100%;padding:11px 18px;border-radius:99px;font-size:.85rem;font-weight:700;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;letter-spacing:.02em;transition:all .22s;box-shadow:0 2px 12px rgba(37,99,235,.26);align-items:center;justify-content:center;gap:7px}
.run-btn.show{display:flex}
.run-btn:hover{box-shadow:0 4px 20px rgba(37,99,235,.4);transform:translateY(-1px);filter:brightness(1.05)}
.run-btn:active{transform:translateY(0)}

/* RESPONSE */
.response-area{display:none;margin-bottom:22px}
.response-area.show{display:block}
.response-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh-sm);animation:slideUp .35s cubic-bezier(.4,0,.2,1) both}
@keyframes slideUp{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:translateY(0)}}
.res-hd{padding:12px 14px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.res-badge{display:flex;align-items:center;gap:5px;font-size:.68rem;font-weight:700;color:var(--accent)}
.res-title{font-size:.83rem;font-weight:600;color:var(--ink);flex:1}
.res-tok{display:flex;align-items:center;gap:3px;font-size:.67rem;color:var(--ink-4)}
.res-tok i{color:var(--accent);font-size:.62rem}
.res-img-wrap{padding:14px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start}
.res-img-prev{border-radius:8px;overflow:hidden;max-width:240px;max-height:200px;border:1px solid var(--border);background:var(--surface-3);flex-shrink:0}
.res-img-prev img{width:100%;display:block;max-height:200px;object-fit:contain}
.res-stats{flex:1;min-width:160px}
.res-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px}
.res-stat-box{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rsm);padding:7px 10px}
.rsn{font-size:1rem;font-weight:700;color:var(--ink);line-height:1;margin-bottom:2px}
.rsn.green{color:var(--green)}
.rsl{font-size:.65rem;color:var(--ink-4)}
.res-text-wrap{padding:13px;display:flex;flex-direction:column;gap:6px}
.res-line{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:9px 11px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rsm)}
.rlt{font-size:.83rem;color:var(--ink-2);line-height:1.6;flex:1}
.copy-sm{background:none;border:1px solid var(--border-md);color:var(--ink-4);border-radius:5px;padding:2px 7px;font-size:.64rem;cursor:pointer;transition:all var(--tr);white-space:nowrap;flex-shrink:0}
.copy-sm:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}
.post-acts{padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:99px;font-size:.77rem;font-weight:600;transition:all .18s;cursor:pointer;border:1px solid}
.act-btn.primary{background:linear-gradient(135deg,#2563eb,#4f46e5);border-color:transparent;color:#fff;box-shadow:0 2px 8px rgba(37,99,235,.22)}
.act-btn.primary:hover{box-shadow:0 4px 14px rgba(37,99,235,.36);transform:translateY(-1px)}
.act-btn.ghost{background:var(--surface-2);border-color:var(--border);color:var(--ink-3)}
.act-btn.ghost:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-lt)}

/* HISTORY */
.hist-list{display:flex;flex-direction:column;gap:5px}
.hist-item{display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--rsm);transition:all var(--tr)}
.hist-item:hover{border-color:var(--border-md)}
.hist-ic{width:27px;height:27px;border-radius:7px;background:var(--accent-lt);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:.73rem;flex-shrink:0}
.hist-lbl{flex:1;font-size:.8rem;color:var(--ink-2);font-weight:500}
.hist-time{font-size:.67rem;color:var(--ink-4)}
.hist-empty{font-size:.8rem;color:var(--ink-4);text-align:center;padding:18px;background:var(--surface);border:1px dashed var(--border-md);border-radius:var(--rsm)}

/* PROCESSING OVERLAY */
.proc-ov{display:none;position:fixed;inset:0;background:rgba(245,244,241,.94);backdrop-filter:blur(14px);z-index:9000;align-items:center;justify-content:center;flex-direction:column}
.proc-ov.show{display:flex}
.proc-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:36px 42px;text-align:center;box-shadow:var(--sh-lg);max-width:340px;width:90%}
.proc-spin{width:58px;height:58px;margin:0 auto 16px;position:relative}
.pr1{width:58px;height:58px;border-radius:50%;border:3px solid var(--surface-3);border-top-color:var(--accent);animation:spin .85s linear infinite;position:absolute;top:0;left:0}
.pr2{width:44px;height:44px;border-radius:50%;border:2px solid transparent;border-top-color:rgba(37,99,235,.25);animation:spin 1.3s linear infinite reverse;position:absolute;top:7px;left:7px}
@keyframes spin{to{transform:rotate(360deg)}}
.pgem{width:19px;height:19px;border-radius:5px;background:linear-gradient(135deg,#2563eb,#7c3aed);position:absolute;top:20px;left:20px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.56rem;animation:gPulse 1.8s infinite}
@keyframes gPulse{0%,100%{opacity:1}50%{opacity:.5}}
.proc-title{font-size:.95rem;font-weight:700;color:var(--ink);margin-bottom:4px}
.proc-msg{font-size:.8rem;color:var(--ink-3);line-height:1.6;min-height:36px}
.psteps{display:flex;flex-direction:column;gap:6px;margin-top:16px;text-align:left}
.pstep{display:flex;align-items:center;gap:7px;font-size:.73rem;color:var(--ink-4);transition:color .35s}
.pstep.done{color:var(--green)}
.pstep.active{color:var(--ink);font-weight:600}
.psdot{width:6px;height:6px;border-radius:50%;background:var(--border-md);flex-shrink:0;transition:background .35s}
.pstep.done .psdot{background:var(--green)}
.pstep.active .psdot{background:var(--accent);animation:pDot 1s infinite}
.proc-bar-bg{height:3px;background:var(--surface-3);border-radius:99px;margin-top:16px;overflow:hidden}
.proc-bar-fill{height:100%;background:linear-gradient(90deg,#2563eb,#7c3aed);border-radius:99px;width:0%;transition:width .5s ease}

/* TOAST */
#toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px}
.toast{display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:var(--rsm);font-size:.8rem;font-weight:500;box-shadow:var(--sh-md);animation:toastIn .3s cubic-bezier(.4,0,.2,1);max-width:280px}
@keyframes toastIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.toast.success{background:var(--green-lt);border:1px solid rgba(22,163,74,.2);color:var(--green)}
.toast.error{background:var(--red-lt);border:1px solid rgba(220,38,38,.2);color:var(--red)}
.toast.info{background:var(--accent-lt);border:1px solid rgba(37,99,235,.18);color:var(--accent)}

/* RIPPLE */
.ripple{position:absolute;border-radius:50%;background:rgba(37,99,235,.13);transform:scale(0);animation:ripA .5s linear;pointer-events:none}
@keyframes ripA{to{transform:scale(4);opacity:0}}

/* MOB TOGGLE */
.mob-tog{display:none;position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:300;background:var(--ink);color:#fff;border:none;border-radius:99px;padding:8px 18px;font-size:.8rem;font-weight:600;gap:6px;box-shadow:var(--sh-lg);align-items:center;font-family:var(--font);cursor:pointer}

@media(max-width:760px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0);box-shadow:var(--sh-lg)}
  .main-wrap{margin-left:0}
  .content{padding:16px 13px}
  .topbar{padding:0 14px}
  .mob-tog{display:flex}
  .quick-grid{grid-template-columns:1fr 1fr}
  .res-img-wrap{flex-direction:column}
  .res-img-prev{max-width:100%}
  .proc-card{padding:26px 20px}
}
@media(max-width:380px){
  .quick-grid{grid-template-columns:1fr}
  .leaf{font-size:.74rem;padding:6px 9px}
}
</style>
</head>
<body>
<div id="toast-container"></div>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-top">
    <div class="sb-logo">
      <div class="logo-gem"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
      JennieAI
    </div>
    <div class="tok-box">
      <div class="tok-row">
        <span class="tok-lbl"><i class="fa-solid fa-bolt"></i>&nbsp;Tokens</span>
        <span class="tok-count" id="sbTok"><?= $tokens_left ?> / <?= $tokens_max ?></span>
      </div>
      <div class="tok-bg"><div class="tok-fill" id="sbBar" style="width:<?= $tok_pct ?>%"></div></div>
      <div class="tok-reset"><i class="fa-regular fa-clock"></i>&nbsp;Resets in <span id="sbTimer">—</span></div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="nav-sect">Workspace</div>
    <div class="nav-item active" onclick="goTo('tools')"><i class="fa-solid fa-wand-magic-sparkles"></i> Image Studio</div>
    <div class="nav-item" onclick="goTo('history')"><i class="fa-solid fa-clock-rotate-left"></i> Recent sessions</div>
    <div class="nav-sect">Tools</div>
    <div class="nav-item" onclick="triggerBranch('compress')"><i class="fa-solid fa-compress"></i> Compression</div>
    <div class="nav-item" onclick="triggerBranch('convert')"><i class="fa-solid fa-repeat"></i> Format Converter</div>
    <div class="nav-item" onclick="triggerBranch('ai')"><i class="fa-solid fa-microchip"></i> AI Analysis</div>
    <div class="nav-item" onclick="triggerBranch('title')"><i class="fa-solid fa-pen-nib"></i> Title Generator</div>
    <div class="nav-div"></div>
    <div class="nav-sect">Account</div>
    <div class="nav-item" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'"><i class="fa-solid fa-user"></i> My Profile</div>
    <?php if($is_pro): ?>
    <div class="nav-item" style="color:var(--gold)"><i class="fa-solid fa-crown" style="color:var(--gold)"></i> Pro Member <span class="nav-badge gold">PRO</span></div>
    <?php else: ?>
    <div class="nav-item" onclick="location.href='/membership'"><i class="fa-solid fa-crown"></i> Upgrade to Pro <span class="nav-badge">NEW</span></div>
    <?php endif; ?>
    <div class="nav-item" onclick="location.href='/auth/logout'"><i class="fa-solid fa-right-from-bracket"></i> Sign out</div>
  </nav>
  <div class="sb-bottom">
    <div class="u-pill" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'">
      <div class="u-av">
        <?php if(!empty($me['profile_image'])): ?><img src="<?= htmlspecialchars($me['profile_image']) ?>" alt="">
        <?php else: ?><?= strtoupper(substr($first_name,0,1)) ?><?php endif; ?>
      </div>
      <div style="flex:1;min-width:0">
        <div class="u-name"><?= htmlspecialchars($first_name) ?></div>
        <div class="u-role <?= $is_pro?'pro':'' ?>"><?= $is_pro?'✦ Pro member':'Free plan' ?></div>
      </div>
      <i class="fa-solid fa-chevron-right" style="font-size:.6rem;color:var(--ink-4)"></i>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main-wrap">
  <header class="topbar">
    <button class="tb-btn" id="sbTogBtn" onclick="toggleSidebar()" style="display:none"><i class="fa-solid fa-bars"></i></button>
    <span class="tb-title">Image Intelligence Studio</span>
    <div class="tb-right">
      <div class="tb-tok"><i class="fa-solid fa-bolt"></i><span id="tbTok"><?= $tokens_left ?></span>&nbsp;tokens</div>
      <?php if($is_pro): ?><div class="tb-btn" style="color:var(--gold);border-color:rgba(180,83,9,.25);background:var(--gold-lt)" title="Pro"><i class="fa-solid fa-crown"></i></div><?php endif; ?>
      <button class="tb-btn" onclick="location.href='/profile/<?= htmlspecialchars($me['username']) ?>'" title="Profile"><i class="fa-solid fa-user"></i></button>
    </div>
  </header>

  <div class="content">

    <!-- WELCOME -->
    <div class="welcome">
      <div class="w-greeting"><?= htmlspecialchars($wline) ?></div>
      <div class="w-sub"><?= htmlspecialchars($wsub) ?></div>
      <div class="status-row">
        <span class="s-dot"></span>
        <span class="s-text">All systems active</span>
        <span class="s-sep">·</span>
        <span class="s-text">JennieAI v2.1</span>
        <?php if($is_pro): ?><span class="s-sep">·</span><span class="s-pro"><i class="fa-solid fa-crown"></i> Pro</span><?php endif; ?>
      </div>
    </div>

    <!-- TOKEN EXHAUSTED -->
    <div class="tok-ex <?= $tokens_left<=0?'show':'' ?>" id="tokEx">
      <div class="te-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
      <div>
        <div class="te-title">Tokens exhausted</div>
        <div class="te-desc">Your session allowance is used up. It renews automatically every 2 hours.</div>
        <div class="te-timer">Next reset in: <strong id="exTimer">calculating…</strong></div>
      </div>
    </div>

    <!-- QUICK TOOLS -->
    <div id="tools">
      <div class="sec-hd"><span class="sec-ttl">Quick access</span></div>
      <div class="quick-grid">
        <div class="qc c-blue" onclick="triggerBranch('compress');ripple(event,this)"><div class="qci c-blue"><i class="fa-solid fa-compress"></i></div><div class="qc-name">Compress</div><div class="qc-desc">Reduce file size</div><div class="qc-cost">From <span>4 tokens</span></div></div>
        <div class="qc c-green" onclick="triggerBranch('convert');ripple(event,this)"><div class="qci c-green"><i class="fa-solid fa-repeat"></i></div><div class="qc-name">Convert</div><div class="qc-desc">Change format</div><div class="qc-cost">From <span>3 tokens</span></div></div>
        <div class="qc c-purple" onclick="triggerBranch('ai');ripple(event,this)"><div class="qci c-purple"><i class="fa-solid fa-microchip"></i></div><div class="qc-name">AI Analysis</div><div class="qc-desc">Face detect, BG remove</div><div class="qc-cost">From <span>8 tokens</span></div></div>
        <div class="qc c-amber" onclick="triggerBranch('title');ripple(event,this)"><div class="qci c-amber"><i class="fa-solid fa-pen-nib"></i></div><div class="qc-name">Title Gen</div><div class="qc-desc">Creative photo titles</div><div class="qc-cost">From <span>5 tokens</span></div></div>
        <div class="qc c-rose" onclick="triggerBranch('ai');selectLeafById('exif-camera');ripple(event,this)"><div class="qci c-rose"><i class="fa-solid fa-camera-retro"></i></div><div class="qc-name">Camera Info</div><div class="qc-desc">EXIF metadata analysis</div><div class="qc-cost">From <span>6 tokens</span></div></div>
      </div>
    </div>

    <!-- UPLOAD -->
    <div style="margin-bottom:22px">
      <div class="sec-hd"><span class="sec-ttl">Upload image</span></div>
      <div class="upload-zone" id="uploadZone">
        <input type="file" id="fileInput" accept="image/*" <?= $tokens_left<=0?'disabled':'' ?>>
        <div class="uz-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <h3>Drop your image here, or tap to browse</h3>
        <p>JPG, PNG, WebP, HEIC, GIF and more</p>
        <div class="uz-chips">
          <span class="uchip"><i class="fa-solid fa-lock"></i> Private</span>
          <span class="uchip"><i class="fa-solid fa-bolt"></i> AI-powered</span>
          <span class="uchip">Max 50 MB</span>
        </div>
      </div>
      <div class="file-preview" id="filePreview">
        <div class="prev-thumb"><img id="prevThumb" src="" alt=""></div>
        <div class="prev-meta">
          <div class="prev-name" id="prevName">—</div>
          <div class="prev-chips" id="prevChips"></div>
        </div>
        <button class="prev-close" onclick="clearFile()" title="Remove"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>

    <!-- COMMANDS -->
    <div style="margin-bottom:22px">
      <div class="sec-hd"><span class="sec-ttl">Processing mode</span></div>
      <div class="branch-grid">

        <!-- COMPRESSION -->
        <div class="branch-card" id="branch-compress">
          <div class="branch-hd" onclick="toggleBranch('compress')">
            <div class="bic c-blue"><i class="fa-solid fa-compress"></i></div>
            <div class="blbl"><div class="bname">Image Compression</div><div class="bdesc">Reduce file size without losing visible quality</div></div>
            <span class="bcost"><i class="fa-solid fa-bolt"></i> 4–5 tk</span>
            <i class="fa-solid fa-chevron-down bchev"></i>
          </div>
          <div class="branch-body"><div class="branch-inner">
            <div class="leaf-row">
              <div class="leaf" data-tool="compress-jpg" onclick="selectLeaf(this,'compress-jpg','compress')"><i class="fa-solid fa-image"></i> To JPG <span class="lc">4 tk</span></div>
              <div class="leaf" data-tool="compress-webp" onclick="selectLeaf(this,'compress-webp','compress')"><i class="fa-brands fa-chrome"></i> To WebP <span class="lc">4 tk</span></div>
              <div class="leaf" data-tool="compress-png" onclick="selectLeaf(this,'compress-png','compress')"><i class="fa-regular fa-image"></i> To PNG <span class="lc">5 tk</span></div>
            </div>
            <div class="q-row" id="q-compress">
              <label>Quality</label>
              <input type="range" id="qualSlider" min="10" max="100" value="80" oninput="document.getElementById('qualDisplay').textContent=this.value+'%'">
              <span class="q-val" id="qualDisplay">80%</span>
            </div>
            <button class="run-btn" id="run-compress" onclick="runTool()"><i class="fa-solid fa-wand-magic-sparkles"></i> Process image</button>
          </div></div>
        </div>

        <!-- CONVERSION -->
        <div class="branch-card" id="branch-convert">
          <div class="branch-hd" onclick="toggleBranch('convert')">
            <div class="bic c-green"><i class="fa-solid fa-repeat"></i></div>
            <div class="blbl"><div class="bname">Format Conversion</div><div class="bdesc">Convert to a different image format</div></div>
            <span class="bcost"><i class="fa-solid fa-bolt"></i> 3–5 tk</span>
            <i class="fa-solid fa-chevron-down bchev"></i>
          </div>
          <div class="branch-body"><div class="branch-inner">
            <div class="leaf-row">
              <div class="leaf" data-tool="jpg-to-webp" onclick="selectLeaf(this,'jpg-to-webp','convert')"><i class="fa-solid fa-arrow-right-arrow-left"></i> Any → WebP <span class="lc">3 tk</span></div>
              <div class="leaf" data-tool="any-to-jpg" onclick="selectLeaf(this,'any-to-jpg','convert')"><i class="fa-solid fa-arrow-right-arrow-left"></i> Any → JPG <span class="lc">3 tk</span></div>
              <div class="leaf" data-tool="any-to-png" onclick="selectLeaf(this,'any-to-png','convert')"><i class="fa-solid fa-arrow-right-arrow-left"></i> Any → PNG <span class="lc">5 tk</span></div>
            </div>
            <button class="run-btn" id="run-convert" onclick="runTool()"><i class="fa-solid fa-wand-magic-sparkles"></i> Process image</button>
          </div></div>
        </div>

        <!-- AI ANALYSIS -->
        <div class="branch-card" id="branch-ai">
          <div class="branch-hd" onclick="toggleBranch('ai')">
            <div class="bic c-purple"><i class="fa-solid fa-microchip"></i></div>
            <div class="blbl"><div class="bname">AI Analysis</div><div class="bdesc">Deep image intelligence — face detection, background removal, metadata</div></div>
            <span class="bcost"><i class="fa-solid fa-bolt"></i> 6–12 tk</span>
            <i class="fa-solid fa-chevron-down bchev"></i>
          </div>
          <div class="branch-body"><div class="branch-inner">
            <div class="leaf-row">
              <div class="leaf" id="leaf-face-detect" data-tool="face-detect" onclick="selectLeaf(this,'face-detect','ai')"><i class="fa-solid fa-face-smile"></i> Face Count &amp; Analysis <span class="lc">10 tk</span></div>
              <div class="leaf" id="leaf-bg-remove" data-tool="bg-remove" onclick="selectLeaf(this,'bg-remove','ai')"><i class="fa-solid fa-wand-magic-sparkles"></i> Remove Background <span class="lc">12 tk</span></div>
              <div class="leaf" id="leaf-exif-camera" data-tool="exif-camera" onclick="selectLeaf(this,'exif-camera','ai')"><i class="fa-solid fa-camera-retro"></i> Camera &amp; Lens Info <span class="lc">6 tk</span></div>
            </div>
            <button class="run-btn" id="run-ai" onclick="runTool()"><i class="fa-solid fa-wand-magic-sparkles"></i> Run analysis</button>
          </div></div>
        </div>

        <!-- TITLE GENERATOR -->
        <div class="branch-card" id="branch-title">
          <div class="branch-hd" onclick="toggleBranch('title')">
            <div class="bic c-amber"><i class="fa-solid fa-pen-nib"></i></div>
            <div class="blbl"><div class="bname">Title Generator</div><div class="bdesc">AI-generated creative titles for your photographs</div></div>
            <span class="bcost"><i class="fa-solid fa-bolt"></i> 5 tk</span>
            <i class="fa-solid fa-chevron-down bchev"></i>
          </div>
          <div class="branch-body"><div class="branch-inner">
            <div class="leaf-row">
              <div class="leaf" data-tool="title-photo" onclick="selectLeaf(this,'title-photo','title')"><i class="fa-solid fa-camera"></i> Creative Photo Title <span class="lc">5 tk</span></div>
            </div>
            <button class="run-btn" id="run-title" onclick="runTool()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate titles</button>
          </div></div>
        </div>

      </div>
    </div>

    <!-- RESPONSE -->
    <div class="response-area" id="responseArea">
      <div class="sec-hd"><span class="sec-ttl">Response</span></div>
      <div class="response-card" id="responseCard"></div>
    </div>

    <!-- HISTORY -->
    <div id="history" style="margin-bottom:60px">
      <div class="sec-hd"><span class="sec-ttl">Recent sessions</span></div>
      <div class="hist-list" id="histList">
        <?php if(empty($history)): ?>
        <div class="hist-empty">No sessions yet. Run your first tool above.</div>
        <?php else: ?>
        <?php foreach($history as $h): ?>
        <div class="hist-item">
          <div class="hist-ic"><i class="fa-solid fa-clock-rotate-left"></i></div>
          <div class="hist-lbl"><?= htmlspecialchars($h['label']) ?></div>
          <div class="hist-time"><?= date('d M, H:i',strtotime($h['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main-wrap -->
</div><!-- /layout -->

<!-- PROCESSING OVERLAY -->
<div class="proc-ov" id="procOv">
  <div class="proc-card">
    <div class="proc-spin">
      <div class="pr1"></div><div class="pr2"></div>
      <div class="pgem"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
    </div>
    <div class="proc-title">JennieAI is working</div>
    <div class="proc-msg" id="procMsg">Preparing your request…</div>
    <div class="psteps" id="pSteps"></div>
    <div class="proc-bar-bg"><div class="proc-bar-fill" id="procBar"></div></div>
  </div>
</div>

<button class="mob-tog" id="mobTog" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i> Menu</button>

<script>
// ── CONFIG ──────────────────────────────────────────────────
var CDN_BASE='<?= $CDN ?>';
var MANIFEST_URL='manifest.php';
var CFG={tl:<?= $tokens_left ?>,tm:<?= $tokens_max ?>,ra:<?= $reset_ts ?>*1000,uid:<?= $uid ?>,pro:<?= $is_pro?'true':'false' ?>};

// Tool costs (server-authoritative, these just gate UI)
var COSTS={'compress-jpg':4,'compress-webp':4,'compress-png':5,'jpg-to-webp':3,'any-to-jpg':3,'any-to-png':5,'title-photo':5,'face-detect':10,'bg-remove':12,'exif-camera':6};

// Processing steps per tool
var STEPS={
  'compress-jpg':['Analysing image structure','Optimising colour channels','Applying JPEG compression','Finalising output'],
  'compress-webp':['Analysing image structure','Loading WebP encoder','Applying compression pass','Finalising output'],
  'compress-png':['Reading image layers','Running lossless pass','Optimising PNG','Finalising output'],
  'jpg-to-webp':['Reading source format','Initialising converter','Transcoding to WebP','Packaging result'],
  'any-to-jpg':['Reading source format','Flattening transparency','Encoding to JPEG','Packaging result'],
  'any-to-png':['Reading source format','Preserving alpha channel','Encoding to PNG','Packaging result'],
  'title-photo':['Scanning visual composition','Analysing colour distribution','Generating title candidates','Ranking by relevance'],
  'face-detect':['Initialising neural network','Loading facial detection model','Scanning image for faces','Computing age and gender estimates'],
  'bg-remove':['Loading segmentation model','Identifying foreground subject','Removing background pixels','Rendering transparent output'],
  'exif-camera':['Reading image metadata','Decoding EXIF data blocks','Interpreting camera parameters','Composing analysis report'],
};
var MSGS={
  'upload':['Receiving your image…','Scanning file…','Preparing workspace…','Image accepted.'],
  'compress-jpg':['Measuring pixel density…','Applying JPEG engine…','Optimising output…'],
  'compress-webp':['Starting WebP engine…','Compressing streams…','Packaging result…'],
  'compress-png':['Reading colour map…','Running lossless optimiser…','Writing PNG…'],
  'jpg-to-webp':['Transcoding pixels…','Encoding WebP…','Wrapping output…'],
  'any-to-jpg':['Flattening alpha…','Encoding JPEG…','Preparing result…'],
  'any-to-png':['Preserving transparency…','Encoding PNG…','Finalising…'],
  'title-photo':['Parsing composition…','Generating options…','Selecting matches…'],
  'face-detect':['Loading neural network…','Detecting faces…','Computing estimates…'],
  'bg-remove':['Loading AI model (first run may take ~15s)…','Segmenting subject…','Removing background…'],
  'exif-camera':['Reading metadata…','Decoding camera data…','Composing report…'],
};

// ── STATE ───────────────────────────────────────────────────
var loadedFile=null,currentTool=null,currentBranch=null,manifestCache=null;
var tl=CFG.tl,_lines=[];

// ── HELPERS ─────────────────────────────────────────────────
function fmt(b){if(b<1024)return b+' B';if(b<1048576)return(b/1024).toFixed(1)+' KB';return(b/1048576).toFixed(2)+' MB';}
function esc(s){var d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML;}
function toast(msg,type,dur){
  type=type||'info';dur=dur||3200;
  var c=document.getElementById('toast-container');
  var d=document.createElement('div');d.className='toast '+type;
  var ic={success:'fa-circle-check',error:'fa-circle-exclamation',info:'fa-circle-info'};
  d.innerHTML='<i class="fa-solid '+ic[type]+'"></i><span></span>';
  d.querySelector('span').textContent=msg;c.appendChild(d);
  setTimeout(function(){d.style.opacity='0';d.style.transition='opacity .3s';setTimeout(function(){d.remove();},300);},dur);
}
function ripple(e,el){
  var r=el.getBoundingClientRect();
  var s=document.createElement('span');s.className='ripple';
  var sz=Math.max(r.width,r.height)*.8;
  s.style.cssText='width:'+sz+'px;height:'+sz+'px;left:'+(e.clientX-r.left-sz/2)+'px;top:'+(e.clientY-r.top-sz/2)+'px;position:absolute;';
  el.style.position='relative';el.style.overflow='hidden';
  el.appendChild(s);setTimeout(function(){s.remove();},600);
}

// ── TIMER ───────────────────────────────────────────────────
function updateTimer(){
  var diff=Math.max(0,CFG.ra-Date.now());
  var h=Math.floor(diff/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
  var str=pad(h)+':'+pad(m)+':'+pad(s);
  ['sbTimer','exTimer'].forEach(function(id){var el=document.getElementById(id);if(el)el.textContent=str;});
}
function pad(n){return String(n).padStart(2,'0');}
setInterval(updateTimer,1000);updateTimer();

// ── TOKEN UI ─────────────────────────────────────────────────
function updateTokUI(n){
  tl=n;var pct=Math.max(0,Math.round((n/CFG.tm)*100));
  var bar=document.getElementById('sbBar');
  var sc=document.getElementById('sbTok');
  var tc=document.getElementById('tbTok');
  if(bar)bar.style.width=pct+'%';
  if(sc)sc.textContent=n+' / '+CFG.tm;
  if(tc)tc.textContent=n;
  if(bar){
    if(pct<=20)bar.style.background='linear-gradient(90deg,#dc2626,#ef4444)';
    else if(pct<=50)bar.style.background='linear-gradient(90deg,#d97706,#f59e0b)';
    else bar.style.background='linear-gradient(90deg,#2563eb,#7c3aed)';
  }
  document.getElementById('tokEx').classList.toggle('show',n<=0);
}

// ── SIDEBAR ──────────────────────────────────────────────────
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function goTo(id){var el=document.getElementById(id);if(el)el.scrollIntoView({behavior:'smooth',block:'start'});if(window.innerWidth<=760)document.getElementById('sidebar').classList.remove('open');}
function triggerBranch(id){
  var card=document.getElementById('branch-'+id);if(!card)return;
  if(!card.classList.contains('expanded'))toggleBranch(id);
  setTimeout(function(){card.scrollIntoView({behavior:'smooth',block:'center'});},80);
  if(window.innerWidth<=760)document.getElementById('sidebar').classList.remove('open');
}
function selectLeafById(toolId){
  var el=document.querySelector('[data-tool="'+toolId+'"]');
  if(el){var branch=el.closest('.branch-card').id.replace('branch-','');selectLeaf(el,toolId,branch);}
}
(function checkMob(){var btn=document.getElementById('sbTogBtn');if(window.innerWidth<=760&&btn)btn.style.display='flex';})();
window.addEventListener('resize',function(){var btn=document.getElementById('sbTogBtn');if(btn)btn.style.display=window.innerWidth<=760?'flex':'none';});

// ── UPLOAD ───────────────────────────────────────────────────
var uz=document.getElementById('uploadZone');
var fi=document.getElementById('fileInput');
uz.addEventListener('dragover',function(e){e.preventDefault();uz.classList.add('drag-over');});
uz.addEventListener('dragleave',function(){uz.classList.remove('drag-over');});
uz.addEventListener('drop',function(e){e.preventDefault();uz.classList.remove('drag-over');if(e.dataTransfer.files[0])handleFile(e.dataTransfer.files[0]);});
fi.addEventListener('change',function(){if(fi.files[0])handleFile(fi.files[0]);});

function handleFile(file){
  if(!file.type.startsWith('image/')){toast('Please upload an image file.','error');return;}
  if(file.size>50*1024*1024){toast('File too large — max 50 MB.','error');return;}
  if(tl<=0){toast('No tokens remaining. Your allowance resets shortly.','error');return;}
  loadedFile=file;currentTool=null;
  simulateUpload(file);
}

function simulateUpload(file){
  var steps=[{msg:'Sending image to JennieAI…',p:18},{msg:'Verifying file integrity…',p:44},{msg:'Building your workspace…',p:72},{msg:'Image accepted and ready.',p:100}];
  showProc('upload');
  var i=0;
  (function run(){
    if(i>=steps.length){hideProc();revealPreview(file);return;}
    var st=steps[i++];
    document.getElementById('procMsg').textContent=st.msg;
    document.getElementById('procBar').style.width=st.p+'%';
    setTimeout(run,360+Math.random()*480);
  })();
}

function revealPreview(file){
  var reader=new FileReader();
  reader.onload=function(e){
    document.getElementById('prevThumb').src=e.target.result;
    document.getElementById('prevName').textContent=file.name;
    var img=new Image();
    img.onload=function(){
      document.getElementById('prevChips').innerHTML=
        '<span class="pchip">'+fmt(file.size)+'</span>'+
        '<span class="pchip">'+img.naturalWidth+'×'+img.naturalHeight+'px</span>'+
        '<span class="pchip">'+(file.type||'image').replace('image/','').toUpperCase()+'</span>';
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
  document.getElementById('prevThumb').src='';fi.value='';
  clearResponse();
  document.querySelectorAll('.leaf').forEach(function(l){l.classList.remove('selected');});
  document.querySelectorAll('.run-btn').forEach(function(b){b.classList.remove('show');});
  document.querySelectorAll('.q-row').forEach(function(q){q.classList.remove('show');});
}

// ── BRANCH TOGGLE ────────────────────────────────────────────
function toggleBranch(id){
  var card=document.getElementById('branch-'+id);
  var isOpen=card.classList.contains('expanded');
  document.querySelectorAll('.branch-card').forEach(function(c){c.classList.remove('expanded');});
  if(!isOpen)card.classList.add('expanded');
}

// ── LEAF SELECT ──────────────────────────────────────────────
function selectLeaf(el,toolId,branch){
  document.querySelectorAll('.leaf').forEach(function(l){l.classList.remove('selected');});
  el.classList.add('selected');
  currentTool=toolId;currentBranch=branch;clearResponse();
  document.querySelectorAll('.q-row').forEach(function(q){q.classList.remove('show');});
  if(toolId.startsWith('compress'))document.getElementById('q-compress').classList.add('show');
  document.querySelectorAll('.run-btn').forEach(function(b){b.classList.remove('show');});
  document.getElementById('run-'+branch).classList.add('show');
  if(!loadedFile)toast('Upload an image first, then run the tool.','info');
}

// ── PROCESSING OVERLAY ───────────────────────────────────────
function showProc(toolId){
  var ov=document.getElementById('procOv');
  var sl=document.getElementById('pSteps');
  var bar=document.getElementById('procBar');
  ov.classList.add('show');bar.style.width='0%';
  var steps=STEPS[toolId]||['Preparing…','Processing…','Finalising…','Done'];
  sl.innerHTML=steps.map(function(s){return '<div class="pstep"><span class="psdot"></span>'+esc(s)+'</div>';}).join('');
}
function advProc(toolId,idx,pct){
  var steps=document.querySelectorAll('.pstep');
  var msgs=MSGS[toolId]||MSGS['upload'];
  steps.forEach(function(el,i){
    el.classList.remove('done','active');
    if(i<idx)el.classList.add('done');
    else if(i===idx)el.classList.add('active');
  });
  document.getElementById('procBar').style.width=pct+'%';
  var mi=Math.min(idx,msgs.length-1);
  document.getElementById('procMsg').textContent=msgs[mi];
}
function hideProc(){
  document.getElementById('procOv').classList.remove('show');
  document.getElementById('pSteps').innerHTML='';
  document.getElementById('procBar').style.width='0%';
}

// ── TOOL LOADING — FETCH AS TEXT (fixes CORS) ────────────────
// Instead of injecting a <script> tag (blocked by CORS on some CDNs),
// we fetch the JS as text and execute it with Function().
// This works with any CDN that allows CORS GET requests.
var _loadedTools={};
var _manifestCache=null;

async function fetchManifest(){
  if(_manifestCache)return _manifestCache;
  var res=await fetch(MANIFEST_URL,{credentials:'same-origin'});
  if(!res.ok)throw new Error('Manifest unavailable ('+res.status+')');
  _manifestCache=await res.json();
  return _manifestCache;
}

async function loadTool(toolId){
  if(_loadedTools[toolId])return;

  var manifest=await fetchManifest();
  var url=manifest.tools[toolId];
  if(!url)throw new Error('This feature is not available yet.');

  // Fetch tool JS as text from same-origin tool-loader.php (no CORS issue)
  // credentials:'same-origin' sends the session cookie so the auth gate passes
  var res=await fetch(url,{
    method:'GET',
    credentials:'same-origin',
    cache:'default',
    headers:{'Accept':'application/javascript,text/javascript,*/*'}
  });
  if(!res.ok){
    var status=res.status;
    if(status===403)throw new Error('Session expired. Please refresh the page and log in again.');
    if(status===429)throw new Error('Too many requests. Please wait a moment and try again.');
    if(status===502)throw new Error('Analysis module temporarily unavailable. Please try again shortly.');
    throw new Error('Could not load analysis module ('+status+'). Please try again.');
  }
  var code=await res.text();
  if(!code||code.length<20)throw new Error('Received empty response from analysis server.');

  try{
    (new Function(code))();
  }catch(e){
    throw new Error('Module initialisation error: '+e.message);
  }
  _loadedTools[toolId]=true;
}

// ── RUN TOOL ─────────────────────────────────────────────────
async function runTool(){
  if(!loadedFile){toast('Please upload an image first.','error');return;}
  if(!currentTool){toast('Please select a processing mode.','error');return;}
  if(tl<=0){toast('No tokens remaining. Your allowance resets automatically.','error');return;}
  var cost=COSTS[currentTool]||5;
  if(tl<cost){toast('This needs '+cost+' tokens. You have '+tl+' left.','error');return;}
  clearResponse();
  showProc(currentTool);
  var total=(STEPS[currentTool]||['','','','']).length;
  try{
    for(var i=0;i<total;i++){
      advProc(currentTool,i,Math.round(((i+1)/total)*85));
      await sleep(400+Math.random()*600);
    }
    advProc(currentTool,total,100);
    await sleep(280);
    await loadTool(currentTool);
    var fn=(window.JennieTools||{})[currentTool];
    if(typeof fn!=='function')throw new Error('Analysis engine not ready. Please try again.');
    var quality=parseInt(document.getElementById('qualSlider')?document.getElementById('qualSlider').value:'80')/100;
    var result=await fn(loadedFile,{quality:quality});
    hideProc();
    await deductTok(cost);
    renderResponse(result,cost);
    logHistory(currentTool,result.label||currentTool);
  }catch(err){
    hideProc();
    toast(err.message,'error',5000);
  }
}

function sleep(ms){return new Promise(function(r){setTimeout(r,ms);});}

async function deductTok(cost){
  try{
    var res=await fetch('jennie_deduct_tokens',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cost:cost,tool:currentTool})});
    var data=await res.json();
    if(data.tokens_left!==undefined)updateTokUI(data.tokens_left);
  }catch(e){}
}
async function logHistory(toolId,label){
  try{await fetch('jennie_log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tool_id:toolId,label:label})});}
  catch(e){}
}

// ── RENDER RESPONSE ───────────────────────────────────────────
function renderResponse(result,cost){
  var area=document.getElementById('responseArea');
  var card=document.getElementById('responseCard');
  area.classList.add('show');card.innerHTML='';

  var hd=document.createElement('div');hd.className='res-hd';
  hd.innerHTML='<div class="res-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> JennieAI</div>'+
    '<div class="res-title">'+esc(result.label||'Result')+'</div>'+
    '<div class="res-tok"><i class="fa-solid fa-bolt"></i> '+cost+' tokens</div>';
  card.appendChild(hd);

  if(result.type==='image'&&result.blob){
    var url=URL.createObjectURL(result.blob);
    var saved=loadedFile.size-result.blob.size;
    var pct=((saved/loadedFile.size)*100).toFixed(1);
    var body=document.createElement('div');body.className='res-img-wrap';
    body.innerHTML=
      '<div class="res-img-prev"><img src="'+url+'" alt="Output"></div>'+
      '<div class="res-stats"><div class="res-stat-grid">'+
        '<div class="res-stat-box"><div class="rsn">'+fmt(loadedFile.size)+'</div><div class="rsl">Original</div></div>'+
        '<div class="res-stat-box"><div class="rsn">'+fmt(result.blob.size)+'</div><div class="rsl">Result</div></div>'+
        '<div class="res-stat-box"><div class="rsn '+(saved>0?'green':'')+'">'+( saved>0?'-'+pct+'%':'+'+Math.abs(pct)+'%')+'</div><div class="rsl">Size change</div></div>'+
        (result.width?'<div class="res-stat-box"><div class="rsn">'+result.width+'×'+result.height+'</div><div class="rsl">Dimensions</div></div>':'')+
      '</div></div>';
    card.appendChild(body);
    var ext=result.ext||'jpg';
    var acts=document.createElement('div');acts.className='post-acts';
    acts.innerHTML=
      '<button class="act-btn primary" onclick="dlBlob(\''+url+'\',\'jennie-output.'+ext+'\')"><i class="fa-solid fa-download"></i> Download</button>'+
      (currentTool.startsWith('compress')?
        '<button class="act-btn ghost" onclick="nudge(-10)"><i class="fa-solid fa-arrow-down"></i> More compression</button>'+
        '<button class="act-btn ghost" onclick="nudge(+10)"><i class="fa-solid fa-arrow-up"></i> Better quality</button>':'')+
      '<button class="act-btn ghost" onclick="reprocess(\''+url+'\',\''+ext+'\')"><i class="fa-solid fa-rotate"></i> Re-process</button>'+
      '<button class="act-btn ghost" onclick="clearResponse()"><i class="fa-solid fa-xmark"></i> Close</button>';
    card.appendChild(acts);
  }

  if(result.type==='text'&&result.lines){
    _lines=result.lines;
    var body2=document.createElement('div');body2.className='res-text-wrap';
    body2.innerHTML=result.lines.map(function(line,i){
      return '<div class="res-line"><div class="rlt">'+esc(line)+'</div><button class="copy-sm" onclick="cpLine('+i+')"><i class="fa-solid fa-copy"></i> Copy</button></div>';
    }).join('');
    card.appendChild(body2);
    var acts2=document.createElement('div');acts2.className='post-acts';
    acts2.innerHTML='<button class="act-btn primary" onclick="cpAll()"><i class="fa-solid fa-copy"></i> Copy all</button>'+
      '<button class="act-btn ghost" onclick="clearResponse()"><i class="fa-solid fa-xmark"></i> Close</button>';
    card.appendChild(acts2);
  }

  card.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function clearResponse(){
  document.getElementById('responseArea').classList.remove('show');
  document.getElementById('responseCard').innerHTML='';
}

// ── HELPERS ───────────────────────────────────────────────────
function dlBlob(url,name){var a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();document.body.removeChild(a);}
function nudge(d){var s=document.getElementById('qualSlider');if(!s)return;s.value=Math.max(10,Math.min(100,parseInt(s.value)+d));document.getElementById('qualDisplay').textContent=s.value+'%';runTool();}
function reprocess(url,ext){fetch(url).then(function(r){return r.blob();}).then(function(b){var f=new File([b],'reprocess.'+ext,{type:b.type});handleFile(f);});}
function cpLine(i){navigator.clipboard.writeText(_lines[i]).then(function(){toast('Copied!','success');});}
function cpAll(){navigator.clipboard.writeText(_lines.join('\n')).then(function(){toast('All copied!','success');});}
</script>
</body>
</html>
