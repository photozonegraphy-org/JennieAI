<?php
/**
 * admin/jennie-tokens.php
 * Admin panel — manage JennieAI token limits per user.
 * Place inside your existing /admin/ folder.
 * Gate with your existing admin auth check.
 */
session_start();
include "../auth/db.php";
include "../auth/security.php";

// ── Admin auth gate ───────────────────────────────────────────
// Replace with your existing admin check
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('Access denied.');
}
$admin_id = (int)$_SESSION['user_id'];

// ── Handle POST actions ───────────────────────────────────────
$msg = '';
$msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = clean($_POST['action']  ?? '');
    $user_id = (int)($_POST['user_id'] ?? 0);
    $value   = (int)($_POST['value']   ?? 0);
    $note    = mb_substr(clean($_POST['note'] ?? ''), 0, 255);

    if (!$user_id) { $msg = 'No user selected.'; $msg_type = 'err'; goto render; }

    // Fetch current row
    $row_stmt = $conn->prepare("SELECT tokens_left, tokens_max FROM jennie_tokens WHERE user_id = ? LIMIT 1");
    $row_stmt->bind_param("i", $user_id);
    $row_stmt->execute();
    $tok = $row_stmt->get_result()->fetch_assoc();

    switch ($action) {

        case 'add_tokens':
            if (!$tok) {
                // Create row first
                $reset = date('Y-m-d H:i:s', strtotime('+2 hours'));
                $conn->prepare("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES(?,?,120,?)")
                     ->bind_param("iis", $user_id, $value, $reset);
                $conn->prepare("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES(?,?,120,?)")
                     ->execute();
                // simpler:
                $conn->query("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES($user_id, $value, 120, DATE_ADD(NOW(), INTERVAL 2 HOUR))");
            } else {
                $new = min($tok['tokens_max'], $tok['tokens_left'] + $value);
                $conn->query("UPDATE jennie_tokens SET tokens_left=$new, updated_at=NOW() WHERE user_id=$user_id");
            }
            $msg = "Added $value tokens to user #$user_id.";
            break;

        case 'set_max':
            if (!$tok) {
                $conn->query("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES($user_id,$value,$value,DATE_ADD(NOW(),INTERVAL 2 HOUR))");
            } else {
                $conn->query("UPDATE jennie_tokens SET tokens_max=$value, tokens_left=LEAST(tokens_left,$value), updated_at=NOW() WHERE user_id=$user_id");
            }
            $msg = "Set token max to $value for user #$user_id.";
            break;

        case 'instant_reset':
            if (!$tok) {
                $conn->query("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES($user_id,120,120,DATE_ADD(NOW(),INTERVAL 2 HOUR))");
            } else {
                $conn->query("UPDATE jennie_tokens SET tokens_left=tokens_max, reset_at=DATE_ADD(NOW(),INTERVAL 2 HOUR), updated_at=NOW() WHERE user_id=$user_id");
            }
            $msg = "Instantly renewed tokens for user #$user_id.";
            break;

        case 'set_tokens_exact':
            if (!$tok) {
                $conn->query("INSERT INTO jennie_tokens (user_id,tokens_left,tokens_max,reset_at) VALUES($user_id,$value,120,DATE_ADD(NOW(),INTERVAL 2 HOUR))");
            } else {
                $conn->query("UPDATE jennie_tokens SET tokens_left=$value, updated_at=NOW() WHERE user_id=$user_id");
            }
            $msg = "Set token balance to $value for user #$user_id.";
            break;

        default:
            $msg = 'Unknown action.'; $msg_type = 'err';
    }

    // Log to admin overrides table
    if ($msg && $msg_type !== 'err') {
        $log = $conn->prepare("INSERT INTO jennie_admin_overrides (user_id,admin_id,action,value,note) VALUES(?,?,?,?,?)");
        $log->bind_param("iisss", $user_id, $admin_id, $action, $value, $note);
        $log->execute();
    }
}

render:

// ── Fetch all users with token data ───────────────────────────
$users_stmt = $conn->query("
    SELECT u.id, u.username, u.full_name, u.is_verified,
           COALESCE(t.tokens_left, '-') AS tokens_left,
           COALESCE(t.tokens_max, '-')  AS tokens_max,
           t.reset_at,
           t.updated_at
    FROM users u
    LEFT JOIN jennie_tokens t ON t.user_id = u.id
    ORDER BY t.updated_at DESC, u.id ASC
    LIMIT 200
");
$users = $users_stmt->fetch_all(MYSQLI_ASSOC);

// Tool stats
$tool_stats = $conn->query("SELECT * FROM jennie_tool_stats ORDER BY total_runs DESC")->fetch_all(MYSQLI_ASSOC);

// Recent history
$recent_hist = $conn->query("
    SELECT h.user_id, u.username, h.tool_id, h.label, h.created_at
    FROM jennie_history h
    JOIN users u ON u.id = h.user_id
    ORDER BY h.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JennieAI Token Manager — Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f4f4f7;color:#1a1a1a;font-size:14px;-webkit-font-smoothing:antialiased}
a{color:#2563eb;text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:28px 20px}
h1{font-size:1.4rem;font-weight:700;margin-bottom:4px}
.sub{font-size:.82rem;color:#777;margin-bottom:24px}

/* MSG */
.msg{padding:11px 16px;border-radius:8px;margin-bottom:18px;font-size:.85rem;font-weight:500}
.msg.ok {background:#f0fdf4;border:1px solid rgba(22,163,74,.25);color:#15803d}
.msg.err{background:#fef2f2;border:1px solid rgba(220,38,38,.25);color:#b91c1c}

/* STATS GRID */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:16px 14px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-num{font-size:1.6rem;font-weight:700;color:#2563eb;line-height:1;margin-bottom:4px}
.stat-lbl{font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.06em}

/* SECTION */
.section{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:12px;padding:20px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.section-title{font-size:.88rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#555;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0}

/* ACTION FORM */
.action-form{background:#f9f9fb;border:1px solid rgba(0,0,0,.07);border-radius:8px;padding:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:20px}
.form-group{display:flex;flex-direction:column;gap:4px}
.form-group label{font-size:.72rem;font-weight:600;color:#666;letter-spacing:.04em;text-transform:uppercase}
select,input[type="number"],input[type="text"]{
  border:1px solid rgba(0,0,0,.12);border-radius:6px;
  padding:7px 10px;font-size:.84rem;background:#fff;
  font-family:inherit;transition:border-color .15s;min-width:120px;
}
select:focus,input:focus{outline:none;border-color:#2563eb}
.btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:8px 16px;border-radius:6px;font-size:.83rem;
  font-weight:600;cursor:pointer;border:none;font-family:inherit;
  transition:all .18s;
}
.btn-primary{background:#2563eb;color:#fff}
.btn-primary:hover{background:#1d4ed8}
.btn-green{background:#16a34a;color:#fff}
.btn-green:hover{background:#15803d}
.btn-amber{background:#d97706;color:#fff}
.btn-amber:hover{background:#b45309}

/* TABLE */
table{width:100%;border-collapse:collapse}
thead th{
  background:#f5f5f8;padding:9px 12px;text-align:left;
  font-size:.7rem;font-weight:700;letter-spacing:.07em;
  text-transform:uppercase;color:#666;
  border-bottom:2px solid #ebebeb;white-space:nowrap;
}
tbody td{padding:8px 12px;border-bottom:1px solid #f0f0f2;font-size:.83rem;color:#333;vertical-align:middle}
tbody tr:hover td{background:#fafafe}
tbody tr:last-child td{border-bottom:none}

.badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:99px;font-size:.66rem;font-weight:700}
.badge.pro{background:#fef3c7;border:1px solid rgba(180,83,9,.2);color:#b45309}
.badge.free{background:#f3f4f6;border:1px solid rgba(0,0,0,.1);color:#6b7280}
.badge.low{background:#fef2f2;border:1px solid rgba(220,38,38,.2);color:#dc2626}
.badge.ok{background:#f0fdf4;border:1px solid rgba(22,163,74,.2);color:#16a34a}

.tok-bar-wrap{width:80px;height:5px;background:#eee;border-radius:99px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:6px}
.tok-bar{height:100%;border-radius:99px;background:#2563eb;transition:width .4s}

/* Quick action buttons in table */
.tbl-btn{
  padding:3px 8px;border-radius:5px;font-size:.7rem;font-weight:600;
  cursor:pointer;border:1px solid;font-family:inherit;transition:all .15s;
}
.tbl-btn.renew{border-color:rgba(22,163,74,.3);color:#16a34a;background:#f0fdf4}
.tbl-btn.renew:hover{background:#16a34a;color:#fff}
.tbl-btn.add{border-color:rgba(37,99,235,.3);color:#2563eb;background:#eff4ff}
.tbl-btn.add:hover{background:#2563eb;color:#fff}

@media(max-width:600px){
  .action-form{flex-direction:column}
  table{font-size:.75rem}
  thead th,tbody td{padding:7px 8px}
}
</style>
</head>
<body>
<div class="wrap">

  <h1><i class="fa-solid fa-wand-magic-sparkles" style="color:#2563eb"></i> JennieAI Token Manager</h1>
  <div class="sub">Manage token limits, renewals, and usage across all users.</div>

  <?php if ($msg): ?>
  <div class="msg <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <?php
  $total_users_with_tokens = count(array_filter($users, fn($u) => $u['tokens_left'] !== '-'));
  $total_runs = array_sum(array_column($tool_stats, 'total_runs'));
  $total_tokens_consumed = array_sum(array_column($tool_stats, 'total_tokens_consumed'));
  $low_token_users = count(array_filter($users, fn($u) => is_numeric($u['tokens_left']) && (int)$u['tokens_left'] <= 20));
  ?>
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num"><?= count($users) ?></div>
      <div class="stat-lbl">Total users</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $total_users_with_tokens ?></div>
      <div class="stat-lbl">JennieAI users</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= number_format($total_runs) ?></div>
      <div class="stat-lbl">Total tool runs</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= number_format($total_tokens_consumed) ?></div>
      <div class="stat-lbl">Tokens consumed</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="color:#dc2626"><?= $low_token_users ?></div>
      <div class="stat-lbl">Low token users</div>
    </div>
  </div>

  <!-- Action form -->
  <div class="section">
    <div class="section-title"><i class="fa-solid fa-bolt"></i> Token Actions</div>
    <form method="post" class="action-form">
      <div class="form-group">
        <label>User</label>
        <select name="user_id" required>
          <option value="">— Select user —</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= $u['tokens_left'] ?>/<?= $u['tokens_max'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Action</label>
        <select name="action" required>
          <option value="instant_reset">Instant full reset</option>
          <option value="add_tokens">Add tokens</option>
          <option value="set_max">Change max limit</option>
          <option value="set_tokens_exact">Set exact balance</option>
        </select>
      </div>
      <div class="form-group">
        <label>Value (tokens)</label>
        <input type="number" name="value" min="0" max="9999" value="60" placeholder="60">
      </div>
      <div class="form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="255" placeholder="Reason…" style="min-width:200px">
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-bolt"></i> Apply
      </button>
    </form>
  </div>

  <!-- Users table -->
  <div class="section">
    <div class="section-title"><i class="fa-solid fa-users"></i> All Users — Token Status</div>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Plan</th>
          <th>Tokens left</th>
          <th>Max</th>
          <th>Resets at</th>
          <th>Last used</th>
          <th>Quick actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $left = $u['tokens_left'];
          $max  = $u['tokens_max'];
          $pct  = (is_numeric($left) && is_numeric($max) && $max > 0) ? round(($left/$max)*100) : 100;
          $low  = is_numeric($left) && (int)$left <= 20;
        ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td>
            <a href="/profile/<?= htmlspecialchars($u['username']) ?>" target="_blank">
              <?= htmlspecialchars($u['username']) ?>
            </a>
          </td>
          <td>
            <span class="badge <?= $u['is_verified'] ? 'pro' : 'free' ?>">
              <?= $u['is_verified'] ? '✦ Pro' : 'Free' ?>
            </span>
          </td>
          <td>
            <?php if (is_numeric($left)): ?>
            <span class="badge <?= $low ? 'low' : 'ok' ?>"><?= $left ?></span>
            <span class="tok-bar-wrap"><span class="tok-bar" style="width:<?= $pct ?>%;background:<?= $low ? '#dc2626' : '#2563eb' ?>"></span></span>
            <?php else: ?>
            <span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td><?= is_numeric($max) ? $max : '—' ?></td>
          <td style="font-size:.75rem;color:#888">
            <?= !empty($u['reset_at']) ? date('d M, H:i', strtotime($u['reset_at'])) : '—' ?>
          </td>
          <td style="font-size:.75rem;color:#888">
            <?= !empty($u['updated_at']) ? date('d M, H:i', strtotime($u['updated_at'])) : '—' ?>
          </td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="instant_reset">
              <input type="hidden" name="value" value="0">
              <button type="submit" class="tbl-btn renew" title="Instantly reset to full">
                <i class="fa-solid fa-rotate"></i> Reset
              </button>
            </form>
            <form method="post" style="display:inline;margin-left:5px">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="add_tokens">
              <input type="hidden" name="value" value="30">
              <button type="submit" class="tbl-btn add" title="Add 30 tokens">
                <i class="fa-solid fa-plus"></i> +30
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Tool stats -->
  <div class="section">
    <div class="section-title"><i class="fa-solid fa-chart-simple"></i> Tool Usage</div>
    <table>
      <thead>
        <tr>
          <th>Tool</th>
          <th>Total runs</th>
          <th>Tokens consumed</th>
          <th>Last run</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tool_stats as $ts): ?>
        <tr>
          <td><code style="font-size:.78rem;background:#f4f4f7;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($ts['tool_id']) ?></code></td>
          <td><?= number_format($ts['total_runs']) ?></td>
          <td><?= number_format($ts['total_tokens_consumed']) ?></td>
          <td style="font-size:.76rem;color:#888"><?= !empty($ts['last_run_at']) ? date('d M Y, H:i', strtotime($ts['last_run_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent history -->
  <div class="section">
    <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Sessions (last 50)</div>
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Tool</th>
          <th>Label</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_hist as $h): ?>
        <tr>
          <td><a href="/profile/<?= htmlspecialchars($h['username']) ?>" target="_blank"><?= htmlspecialchars($h['username']) ?></a></td>
          <td><code style="font-size:.75rem"><?= htmlspecialchars($h['tool_id']) ?></code></td>
          <td><?= htmlspecialchars($h['label']) ?></td>
          <td style="font-size:.75rem;color:#888"><?= date('d M, H:i', strtotime($h['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
