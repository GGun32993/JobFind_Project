<?php
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/profile_image_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: login.php");
    exit();
}

$admin_id      = $_SESSION['user_id'];
$selected_user = isset($_GET['user']) ? intval($_GET['user']) : 0;

// ── auto-add is_read column ถ้ายังไม่มี ──
$col_check = mysqli_query($conn,"SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
if(mysqli_num_rows($col_check) === 0){
    mysqli_query($conn,"ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
}

if(!$selected_user){
    $latest_conversation = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT u.user_id
        FROM chat_messages cm
        JOIN users u ON (
            (cm.sender_id='$admin_id' AND u.user_id=cm.receiver_id)
            OR (cm.receiver_id='$admin_id' AND u.user_id=cm.sender_id)
        )
        WHERE (cm.sender_id='$admin_id' OR cm.receiver_id='$admin_id')
        AND u.user_id != '$admin_id'
        ORDER BY
            CASE WHEN cm.receiver_id='$admin_id' AND cm.is_read=0 THEN 0 ELSE 1 END,
            cm.sent_at DESC
        LIMIT 1
    "));
    if(!empty($latest_conversation['user_id'])){
        $selected_user = (int)$latest_conversation['user_id'];
    }
}

// ── send message (process BEFORE any output) ──
if(isset($_POST['message']) && $selected_user){
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    mysqli_query($conn,"
        INSERT INTO chat_messages (sender_id, receiver_id, message)
        VALUES ('$admin_id','$selected_user','$message')
    ");
    header("Location: admin_support.php?user=".$selected_user);
    exit();
}

// ── get users who have chatted with admin (fixed query) ──
$users = mysqli_query($conn,"
    SELECT DISTINCT
        u.user_id,
        u.username,
        u.role,
        (
            SELECT message FROM chat_messages
            WHERE (sender_id=u.user_id AND receiver_id='$admin_id')
               OR (sender_id='$admin_id' AND receiver_id=u.user_id)
            ORDER BY sent_at DESC LIMIT 1
        ) AS last_message,
        (
            SELECT sent_at FROM chat_messages
            WHERE (sender_id=u.user_id AND receiver_id='$admin_id')
               OR (sender_id='$admin_id' AND receiver_id=u.user_id)
            ORDER BY sent_at DESC LIMIT 1
        ) AS last_at,
        (
            SELECT COUNT(*) FROM chat_messages
            WHERE sender_id=u.user_id
            AND receiver_id='$admin_id'
            AND is_read=0
        ) AS unread_count
    FROM users u
    WHERE u.user_id != '$admin_id'
      AND EXISTS (
          SELECT 1 FROM chat_messages
          WHERE (sender_id=u.user_id AND receiver_id='$admin_id')
             OR (sender_id='$admin_id' AND receiver_id=u.user_id)
      )
    ORDER BY last_at DESC
");

// ── get selected user info ──
$selected_info = null;
if($selected_user){
    $si = mysqli_query($conn,"SELECT username, role FROM users WHERE user_id='$selected_user'");
    $selected_info = mysqli_fetch_assoc($si);
}

// ── get messages ──
$msg_rows = [];
if($selected_user){
    // ── mark as read ทันทีที่ admin เปิดดู ──
    mysqli_query($conn,"
        UPDATE chat_messages
        SET is_read = 1
        WHERE sender_id = '$selected_user'
        AND receiver_id = '$admin_id'
        AND is_read = 0
    ");

    $msgs = mysqli_query($conn,"
        SELECT * FROM chat_messages
        WHERE (sender_id='$selected_user' AND receiver_id='$admin_id')
           OR (sender_id='$admin_id'      AND receiver_id='$selected_user')
        ORDER BY sent_at ASC
    ");
    while($m = mysqli_fetch_assoc($msgs)) $msg_rows[] = $m;
}

$admin_unread_support = 0;
$unread_support = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c
    FROM chat_messages cm
    JOIN users u ON u.user_id=cm.sender_id
    WHERE cm.receiver_id='$admin_id'
    AND cm.is_read=0
"));
$admin_unread_support = (int)($unread_support['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Support Chat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:   #0f172a;
    --navy2:  #1e293b;
    --navy3:  #334155;
    --accent: #6366f1;
    --light:  #f1f5f9;
    --white:  #ffffff;
    --text:   #0f172a;
    --muted:  #64748b;
    --border: #e2e8f0;
    --green:  #10b981;
    --yellow: #f59e0b;
    --radius: 14px;
  }

  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; overflow:hidden; }

  /* ── Left sidebar (nav) ── */
  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub  { font-size:11px; color:var(--navy3); }
  .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }

  /* ── Main area ── */
  .main { margin-left:240px; flex:1; display:flex; height:100vh; overflow:hidden; }

  /* ── User list panel ── */
  .user-panel {
    width:260px; flex-shrink:0;
    background:var(--white); border-right:1px solid var(--border);
    display:flex; flex-direction:column; height:100vh; overflow:hidden;
  }
  .panel-header {
    padding:20px 18px 14px;
    border-bottom:1px solid var(--border); flex-shrink:0;
  }
  .panel-header h3 { font-size:16px; font-weight:600; margin-bottom:12px; }

  .search-wrap { position:relative; }
  .search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:14px; color:var(--muted); }
  .search-wrap input { width:100%; padding:8px 12px 8px 32px; border:1px solid var(--border); border-radius:9px; font-family:'Sora',sans-serif; font-size:13px; outline:none; transition:border-color .15s; }
  .search-wrap input:focus { border-color:var(--accent); }

  .user-list { flex:1; overflow-y:auto; }
  .user-row {
    display:flex; align-items:center; gap:12px;
    padding:14px 18px; cursor:pointer;
    border-bottom:1px solid var(--border);
    text-decoration:none; color:var(--text);
    transition:background .15s;
  }
  .user-row:hover   { background:var(--light); }
  .user-row.active  { background:#eef2ff; border-left:3px solid var(--accent); }
  .u-avatar {
    width:40px; height:40px; border-radius:50%;
    font-size:14px; font-weight:600; color:#fff;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  .u-avatar.freelancer { background:#6366f1; }
  .u-avatar.employer   { background:#0ea5e9; }
  .u-avatar.other      { background:#64748b; }
  .u-info { flex:1; min-width:0; }
  .u-name { font-size:13.5px; font-weight:600; }
  .u-preview { font-size:12px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
  .u-time { font-size:11px; color:var(--muted); flex-shrink:0; }
  .role-badge { font-size:10px; font-weight:600; padding:2px 7px; border-radius:10px; margin-left:4px; }
  .rb-freelancer { background:#eef2ff; color:var(--accent); }
  .rb-employer   { background:#e0f2fe; color:#0369a1; }

  .no-users { padding:40px 20px; text-align:center; color:var(--muted); }
  .no-users i { font-size:36px; color:#c7d2fe; margin-bottom:10px; display:block; }

  /* ── Chat panel ── */
  .chat-panel { flex:1; display:flex; flex-direction:column; height:100vh; overflow:hidden; }

  .chat-header {
    padding:16px 22px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:14px; flex-shrink:0;
    background:var(--white);
  }
  .ch-avatar { width:42px; height:42px; border-radius:50%; font-size:15px; font-weight:600; color:#fff; display:flex; align-items:center; justify-content:center; }
  .ch-name { font-size:15px; font-weight:600; }
  .ch-role { font-size:12px; color:var(--muted); margin-top:1px; }

  .chat-body { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:12px; background:var(--light); }

  .date-sep { text-align:center; font-size:11.5px; color:var(--muted); margin:4px 0; position:relative; }
  .date-sep::before, .date-sep::after { content:''; position:absolute; top:50%; width:calc(50% - 70px); height:1px; background:var(--border); }
  .date-sep::before { left:0; } .date-sep::after { right:0; }

  .bubble-row { display:flex; align-items:flex-end; gap:8px; }
  .bubble-row.me { flex-direction:row-reverse; }
  .b-avatar { width:30px; height:30px; border-radius:50%; font-size:11px; font-weight:600; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .b-avatar.them { background:#6366f1; }
  .b-avatar.me   { background:#0f172a; }
  .bubble { max-width:60%; padding:10px 14px; border-radius:18px; font-size:13.5px; line-height:1.6; }
  .bubble.them { background:var(--white); color:var(--text); border-bottom-left-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  .bubble.me   { background:var(--accent); color:#fff; border-bottom-right-radius:4px; }
  .bubble-time { font-size:10.5px; margin-top:4px; }
  .bubble.them .bubble-time { color:var(--muted); }
  .bubble.me   .bubble-time { color:rgba(255,255,255,.65); }

  .chat-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--muted); padding:40px; }
  .chat-empty i { font-size:44px; color:#c7d2fe; margin-bottom:12px; }
  .chat-empty p { font-size:13.5px; text-align:center; }

  .chat-footer { padding:14px 20px; border-top:1px solid var(--border); background:var(--white); flex-shrink:0; }
  .input-row { display:flex; gap:10px; align-items:center; }
  .msg-input { flex:1; padding:11px 16px; border:1px solid var(--border); border-radius:30px; font-family:'Sora',sans-serif; font-size:13.5px; color:var(--text); outline:none; transition:border-color .15s, box-shadow .15s; }
  .msg-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  .btn-send { width:42px; height:42px; border-radius:50%; background:var(--accent); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:17px; transition:background .15s, transform .1s; flex-shrink:0; }
  .btn-send:hover { background:#4f46e5; transform:scale(1.05); }

  /* no selection state */
  .no-select { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--muted); background:var(--light); }
  .no-select i { font-size:52px; color:#c7d2fe; margin-bottom:16px; }
  .no-select p { font-size:14px; }

  @media(max-width:768px){
    .sidebar { display:none; }
    .main { margin-left:0; }
    .user-panel { width:200px; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Nav Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=13" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php"       class="nav-item"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="admin_manage_categories.php"  class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php"            class="nav-item active">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main">

  <!-- User list panel -->
  <div class="user-panel">
    <div class="panel-header">
      <h3><i class="bi bi-chat-left-dots"></i> Conversations</h3>
      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input type="text" id="user-search" placeholder="ค้นหาชื่อ user..." oninput="searchUsers()" />
      </div>
    </div>

    <div class="user-list" id="user-list">
    <?php
    $user_rows = [];
    while($u = mysqli_fetch_assoc($users)) $user_rows[] = $u;

    if(empty($user_rows)):
    ?>
      <div class="no-users">
        <i class="bi bi-inbox"></i>
        <p>ยังไม่มีการสนทนา</p>
      </div>
    <?php else: ?>
      <?php foreach($user_rows as $u):
        $is_active  = ($u['user_id'] == $selected_user);
        $init       = profile_initials($u['username']);
        $role_class = in_array($u['role'], ['freelancer','employer']) ? $u['role'] : 'other';
        $preview    = $u['last_message'] ? mb_substr($u['last_message'], 0, 30).(mb_strlen($u['last_message'])>30?'...':'') : 'ยังไม่มีข้อความ';
        $time_str   = $u['last_at'] ? date('H:i', strtotime($u['last_at'])) : '';
      ?>
      <a href="admin_support.php?user=<?php echo $u['user_id']; ?>"
         class="user-row <?php echo $is_active?'active':''; ?>"
         data-name="<?php echo strtolower(htmlspecialchars($u['username'])); ?>">
        <div class="u-avatar <?php echo $role_class; ?>"><?php echo $init; ?></div>
        <div class="u-info">
          <div class="u-name">
            <?php echo htmlspecialchars($u['username']); ?>
            <span class="role-badge rb-<?php echo $role_class; ?>"><?php echo $u['role'] ?? ''; ?></span>
          </div>
          <div class="u-preview"><?php echo htmlspecialchars($preview); ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
          <?php if($time_str): ?>
          <div class="u-time"><?php echo $time_str; ?></div>
          <?php endif; ?>
          <?php if(!empty($u['unread_count']) && $u['unread_count'] > 0 && !$is_active): ?>
          <div style="width:18px;height:18px;border-radius:50%;background:#ef4444;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?php echo $u['unread_count']; ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </div>

  <!-- Chat panel -->
  <div class="chat-panel">
    <?php if($selected_user && $selected_info): ?>

    <!-- Chat header -->
    <div class="chat-header">
      <?php
        $ci = profile_initials($selected_info['username']);
        $cr = $selected_info['role'] ?? 'other';
        $cr_class = in_array($cr, ['freelancer','employer']) ? $cr : 'other';
        $cr_bg = $cr === 'freelancer' ? '#6366f1' : ($cr === 'employer' ? '#0ea5e9' : '#64748b');
      ?>
      <div class="ch-avatar" style="background:<?php echo $cr_bg; ?>"><?php echo $ci; ?></div>
      <div>
        <div class="ch-name"><?php echo htmlspecialchars($selected_info['username']); ?></div>
        <div class="ch-role"><?php echo ucfirst($cr); ?></div>
      </div>
      <div class="chat-header-actions">
        <span class="chat-chip"><i class="bi bi-person-check"></i> <?php echo ucfirst($cr); ?></span>
        <span class="chat-chip"><i class="bi bi-chat-square-text"></i> <?php echo count($msg_rows); ?> messages</span>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-body" id="chat-body">
      <?php if(empty($msg_rows)): ?>
      <div class="chat-empty">
        <i class="bi bi-chat"></i>
        <p>ยังไม่มีข้อความ<br>เริ่มบทสนทนาได้เลย</p>
      </div>
      <?php else:
        $last_date = '';
        foreach($msg_rows as $m):
          $is_me    = ($m['sender_id'] == $admin_id);
          $time_str = $m['sent_at'] ? date('H:i', strtotime($m['sent_at'])) : '';
          $date_str = $m['sent_at'] ? date('d M Y', strtotime($m['sent_at'])) : '';
          $b_init   = $is_me ? 'AD' : $ci;
      ?>
        <?php if($date_str && $date_str !== $last_date): $last_date = $date_str; ?>
        <div class="date-sep"><?php echo $date_str; ?></div>
        <?php endif; ?>

        <div class="bubble-row <?php echo $is_me?'me':''; ?>">
          <div class="b-avatar <?php echo $is_me?'me':'them'; ?>"><?php echo $b_init; ?></div>
          <div class="bubble <?php echo $is_me?'me':'them'; ?>">
            <?php echo htmlspecialchars($m['message']); ?>
            <?php if($time_str): ?>
            <div class="bubble-time"><?php echo $time_str; ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Input -->
    <div class="chat-footer">
      <form method="POST" action="admin_support.php?user=<?php echo $selected_user; ?>">
        <div class="input-row">
          <input type="text" name="message" class="msg-input"
                 placeholder="พิมพ์ข้อความถึง <?php echo htmlspecialchars($selected_info['username']); ?>..."
                 required autocomplete="off" id="msg-input">
          <button type="submit" class="btn-send" aria-label="Send">
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
      </form>
    </div>

    <?php else: ?>
    <div class="no-select">
      <i class="bi bi-chat-square-dots"></i>
      <p>เลือก User จากรายการทางซ้าย<br>เพื่อเริ่มบทสนทนา</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
  // auto scroll
  const cb = document.getElementById('chat-body');
  if(cb) cb.scrollTop = cb.scrollHeight;

  // enter to send
  const mi = document.getElementById('msg-input');
  if(mi){
    mi.addEventListener('keydown', e => {
      if(e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); mi.closest('form').submit(); }
    });
  }

  // search users
  function searchUsers(){
    const q = document.getElementById('user-search').value.toLowerCase();
    document.querySelectorAll('.user-row').forEach(row => {
      const name = row.dataset.name || '';
      row.style.display = name.includes(q) ? '' : 'none';
    });
  }
</script>
</body>
</html>
