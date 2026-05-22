<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'];
$username = $_SESSION['username'];

// ── ดึง admin_id จริงจาก DB (แทนการ hardcode = 1) ──
$admin_query = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' LIMIT 1");
$admin_row   = mysqli_fetch_assoc($admin_query);
$admin_id    = $admin_row['user_id'] ?? 1;

// ── send message ──
if(isset($_POST['send'])){
    $msg = mysqli_real_escape_string($conn, $_POST['message']);
    mysqli_query($conn,"
        INSERT INTO chat_messages (sender_id, receiver_id, message)
        VALUES ('$user_id','$admin_id','$msg')
    ");
    header("Location: support_chat.php");
    exit();
}

// ── get messages ──
$messages = mysqli_query($conn,"
    SELECT * FROM chat_messages
    WHERE (sender_id='$user_id' AND receiver_id='$admin_id')
       OR (sender_id='$admin_id' AND receiver_id='$user_id')
    ORDER BY sent_at ASC
");

$rows = [];
while($r = mysqli_fetch_assoc($messages)) $rows[] = $r;

$dashboard = $role === "employer" ? "employer_dashboard.php" : "freelancer_dashboard.php";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Chat</title>
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
    --radius: 14px;
  }

  body {
    font-family:'Sora',sans-serif;
    background:var(--light);
    color:var(--text);
    display:flex;
    min-height:100vh;
  }

  /* ── Sidebar ── */
  .sidebar {
    width:240px; min-height:100vh;
    background:var(--navy);
    display:flex; flex-direction:column;
    padding:28px 0;
    position:fixed; top:0; left:0; z-index:100;
  }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon {
    width:36px; height:36px; background:var(--accent);
    border-radius:10px; display:flex; align-items:center;
    justify-content:center; color:#fff; font-size:18px;
  }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub  { font-size:11px; color:var(--navy3); }

  .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
  .nav-item {
    display:flex; align-items:center; gap:10px;
    padding:10px 14px; border-radius:10px;
    color:#94a3b8; text-decoration:none;
    font-size:13.5px; font-weight:500;
    transition:background .15s, color .15s;
  }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }

  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout {
    display:flex; align-items:center; gap:10px;
    padding:10px 14px; border-radius:10px;
    color:#f87171; text-decoration:none;
    font-size:13.5px; font-weight:500; transition:background .15s;
  }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main {
    margin-left:240px; flex:1;
    padding:36px 40px;
    min-height:100vh;
    display:flex; flex-direction:column;
  }

  .topbar { margin-bottom:24px; flex-shrink:0; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Chat card ── */
  .chat-wrap {
    background:var(--white);
    border:1px solid var(--border);
    border-radius:var(--radius);
    display:flex; flex-direction:column;
    flex:1; min-height:0;
    max-height:calc(100vh - 180px);
    overflow:hidden;
  }

  /* Chat header */
  .chat-header {
    padding:18px 22px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:14px;
    flex-shrink:0;
  }
  .admin-avatar {
    width:44px; height:44px; border-radius:50%;
    background:var(--navy); color:#fff;
    font-size:16px; font-weight:600;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  .admin-info .name   { font-size:15px; font-weight:600; }
  .admin-info .status {
    font-size:12px; color:var(--green);
    display:flex; align-items:center; gap:4px; margin-top:2px;
  }
  .status-dot { width:7px; height:7px; border-radius:50%; background:var(--green); flex-shrink:0; }

  /* Messages */
  .chat-body {
    flex:1; overflow-y:auto;
    padding:20px 22px;
    display:flex; flex-direction:column; gap:14px;
    scroll-behavior:smooth;
  }

  .date-sep {
    text-align:center; font-size:11.5px;
    color:var(--muted); position:relative; margin:4px 0;
  }
  .date-sep::before, .date-sep::after {
    content:''; position:absolute; top:50%;
    width:calc(50% - 70px); height:1px; background:var(--border);
  }
  .date-sep::before { left:0; }
  .date-sep::after  { right:0; }

  .bubble-row { display:flex; align-items:flex-end; gap:8px; }
  .bubble-row.me { flex-direction:row-reverse; }

  .bubble-avatar {
    width:30px; height:30px; border-radius:50%;
    font-size:11px; font-weight:600; color:#fff;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  .bubble-avatar.them { background:var(--navy); }
  .bubble-avatar.me   { background:var(--accent); }

  .bubble {
    max-width:62%; padding:11px 15px;
    border-radius:18px; font-size:14px; line-height:1.6;
  }
  .bubble.them { background:var(--light); color:var(--text); border-bottom-left-radius:4px; }
  .bubble.me   { background:var(--accent); color:#fff; border-bottom-right-radius:4px; }
  .bubble-time { font-size:10.5px; margin-top:4px; text-align:right; }
  .bubble.them .bubble-time { color:var(--muted); }
  .bubble.me   .bubble-time { color:rgba(255,255,255,.65); }

  .chat-empty {
    flex:1; display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    color:var(--muted); padding:40px; text-align:center;
  }
  .chat-empty i { font-size:44px; color:#c7d2fe; margin-bottom:12px; display:block; }
  .chat-empty p { font-size:13.5px; }

  /* Input */
  .chat-footer {
    padding:16px 20px;
    border-top:1px solid var(--border);
    flex-shrink:0;
  }
  .input-row { display:flex; gap:10px; align-items:center; }
  .msg-input {
    flex:1; padding:12px 16px;
    border:1px solid var(--border); border-radius:30px;
    font-family:'Sora',sans-serif; font-size:14px;
    color:var(--text); outline:none;
    transition:border-color .15s, box-shadow .15s;
  }
  .msg-input:focus {
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(99,102,241,.12);
  }
  .btn-send {
    width:44px; height:44px; border-radius:50%;
    background:var(--accent); color:#fff;
    border:none; cursor:pointer; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; transition:background .15s, transform .1s;
  }
  .btn-send:hover  { background:#4f46e5; transform:scale(1.05); }
  .btn-send:active { transform:scale(.95); }

  @media(max-width:768px){
    .sidebar { display:none; }
    .main    { margin-left:0; padding:16px; }
    .bubble  { max-width:80%; }
  }
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub"><?php echo $role === 'employer' ? 'Employer' : ($role === 'admin' ? 'Admin' : 'Dashboard'); ?></div>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <a href="<?php echo $dashboard; ?>" class="nav-item">
      <i class="bi bi-grid"></i> Dashboard
    </a>

    <?php if($role === 'freelancer'): ?>
      <a href="browse_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Browse Jobs</a>
      <a href="my_applications.php"    class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
      <a href="my_profile.php"         class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="freelancer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="upload_resume.php"      class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <?php elseif($role === 'employer'): ?>
      <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
      <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
      <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
      <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="employer_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
      <a href="employer_profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <?php endif; ?>

    <div class="nav-divider"></div>
    <a href="support_chat.php" class="nav-item active">
      <i class="bi bi-chat-dots"></i> Support Chat
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout">
      <i class="bi bi-box-arrow-left"></i> Logout
    </a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <div class="topbar">
    <h2>Support Chat</h2>
    <p>ติดต่อ Admin เพื่อขอความช่วยเหลือ</p>
  </div>

  <div class="chat-wrap">

    <!-- Header -->
    <div class="chat-header">
      <div class="admin-avatar">AD</div>
      <div class="admin-info">
        <div class="name">Admin Support</div>
        <div class="status"><div class="status-dot"></div> Online</div>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-body" id="chat-body">

      <?php if(empty($rows)): ?>
      <div class="chat-empty">
        <i class="bi bi-chat-dots"></i>
        <p>ยังไม่มีข้อความ<br>ส่งข้อความหา Admin เพื่อขอความช่วยเหลือได้เลยครับ</p>
      </div>

      <?php else:
        $last_date = '';
        $user_init = strtoupper(substr($username, 0, 1));
        foreach($rows as $row):
          $is_me    = ($row['sender_id'] == $user_id);
          $time_str = !empty($row['sent_at']) ? date('H:i', strtotime($row['sent_at'])) : '';
          $date_str = !empty($row['sent_at']) ? date('d M Y', strtotime($row['sent_at'])) : '';
      ?>

        <?php if($date_str && $date_str !== $last_date): $last_date = $date_str; ?>
        <div class="date-sep"><?php echo $date_str; ?></div>
        <?php endif; ?>

        <div class="bubble-row <?php echo $is_me ? 'me' : ''; ?>">
          <div class="bubble-avatar <?php echo $is_me ? 'me' : 'them'; ?>">
            <?php echo $is_me ? $user_init : 'AD'; ?>
          </div>
          <div class="bubble <?php echo $is_me ? 'me' : 'them'; ?>">
            <?php echo htmlspecialchars($row['message']); ?>
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
      <form method="POST" action="support_chat.php">
        <div class="input-row">
          <input type="text" name="message" id="msg-input" class="msg-input"
                 placeholder="พิมพ์ข้อความ..." required autocomplete="off">
          <button type="submit" name="send" class="btn-send" aria-label="ส่งข้อความ">
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
      </form>
    </div>

  </div>

</main>

<script>
  // auto scroll to bottom
  const chatBody = document.getElementById('chat-body');
  if(chatBody) chatBody.scrollTop = chatBody.scrollHeight;

  // Enter to send
  document.getElementById('msg-input').addEventListener('keydown', function(e){
    if(e.key === 'Enter' && !e.shiftKey){
      e.preventDefault();
      this.closest('form').submit();
    }
  });
</script>
</body>
</html>
