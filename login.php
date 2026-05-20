<?php
session_start();
include("config.php");

if(isset($_SESSION['role'])){
    if($_SESSION['role']=="admin")      { header("Location: admin_dashboard.php");      exit(); }
    if($_SESSION['role']=="employer")   { header("Location: employer_dashboard.php");   exit(); }
    if($_SESSION['role']=="freelancer") { header("Location: freelancer_dashboard.php"); exit(); }
}

$error = '';

if(isset($_POST['login'])){
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = mysqli_query($conn,"
        SELECT * FROM users
        WHERE email='$email' AND password='$password'
    ");

    if(mysqli_num_rows($query)==1){
        $row = mysqli_fetch_assoc($query);
        $_SESSION['user_id']  = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];

        if($row['role']=="admin")      { header("Location: admin_dashboard.php");      exit(); }
        if($row['role']=="employer")   { header("Location: employer_dashboard.php");   exit(); }
        if($row['role']=="freelancer") { header("Location: freelancer_dashboard.php"); exit(); }
    } else {
        $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — FreelanceHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:   #0f172a;  --navy2: #1e293b;
    --accent: #6366f1;  --accent2: #818cf8;
    --light:  #f1f5f9;  --white: #ffffff;
    --text:   #0f172a;  --muted: #64748b;
    --border: #e2e8f0;  --green: #10b981;
    --red:    #ef4444;  --radius: 14px;
  }

  body { font-family:'Sora',sans-serif; background:var(--light); min-height:100vh; display:flex; align-items:center; justify-content:center; }

  /* ── Card ── */
  .login-wrap {
    display:flex; width:900px; max-width:95vw;
    background:var(--white); border-radius:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.12);
    overflow:hidden; min-height:540px;
  }

  /* ── Left panel ── */
  .left-panel {
    width:420px; flex-shrink:0;
    background:var(--navy);
    padding:52px 44px;
    display:flex; flex-direction:column;
    justify-content:space-between;
    position:relative; overflow:hidden;
  }

  /* decorative circles */
  .left-panel::before {
    content:''; position:absolute;
    width:300px; height:300px; border-radius:50%;
    background:rgba(99,102,241,.15);
    top:-80px; right:-80px;
  }
  .left-panel::after {
    content:''; position:absolute;
    width:200px; height:200px; border-radius:50%;
    background:rgba(99,102,241,.1);
    bottom:-60px; left:-60px;
  }

  .brand { display:flex; align-items:center; gap:12px; z-index:1; }
  .brand-icon {
    width:44px; height:44px; border-radius:12px;
    background:var(--accent); display:flex;
    align-items:center; justify-content:center;
    font-size:22px; color:#fff;
  }
  .brand-name { font-size:20px; font-weight:700; color:#fff; }
  .brand-sub  { font-size:12px; color:#94a3b8; }

  .left-content { z-index:1; }
  .left-content h2 { font-size:28px; font-weight:700; color:#fff; line-height:1.3; margin-bottom:14px; }
  .left-content p  { font-size:14px; color:#94a3b8; line-height:1.8; }

  .feature-list { margin-top:28px; display:flex; flex-direction:column; gap:12px; }
  .feature-item { display:flex; align-items:center; gap:10px; color:#cbd5e1; font-size:13.5px; }
  .feature-item i { width:28px; height:28px; border-radius:8px; background:rgba(99,102,241,.25); color:var(--accent2); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }

  .left-footer { z-index:1; }
  .left-footer p { font-size:12px; color:#475569; }

  /* ── Right panel ── */
  .right-panel { flex:1; padding:52px 44px; display:flex; flex-direction:column; justify-content:center; }

  .right-panel h3 { font-size:22px; font-weight:600; margin-bottom:6px; }
  .right-panel .subtitle { font-size:13.5px; color:var(--muted); margin-bottom:32px; }

  /* Error alert */
  .alert-err {
    background:#fee2e2; border:1px solid #fca5a5;
    border-radius:10px; padding:12px 16px;
    font-size:13.5px; color:#991b1b;
    display:flex; align-items:center; gap:8px;
    margin-bottom:20px;
  }

  /* Fields */
  .field-group { margin-bottom:18px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; color:var(--text); }

  .input-wrap { position:relative; }
  .input-wrap i.prefix {
    position:absolute; left:13px; top:50%;
    transform:translateY(-50%); font-size:16px;
    color:var(--muted); pointer-events:none;
  }
  .form-input {
    width:100%; padding:12px 14px 12px 40px;
    border:1px solid var(--border); border-radius:10px;
    font-family:'Sora',sans-serif; font-size:14px;
    color:var(--text); outline:none;
    transition:border-color .15s, box-shadow .15s;
  }
  .form-input:focus {
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(99,102,241,.12);
  }

  /* Toggle password */
  .input-wrap .toggle-pw {
    position:absolute; right:13px; top:50%;
    transform:translateY(-50%); font-size:17px;
    color:var(--muted); cursor:pointer; border:none;
    background:none; padding:0; line-height:1;
    display:flex; align-items:center; justify-content:center;
    width:24px; height:24px;
  }
  .input-wrap .toggle-pw i { font-size:17px; line-height:1; display:block; }
  .input-wrap .toggle-pw:hover { color:var(--accent); }
  .form-input.has-toggle { padding-right:40px; }

  /* Submit */
  .btn-login {
    width:100%; padding:13px;
    background:var(--accent); color:#fff;
    border:none; border-radius:10px;
    font-family:'Sora',sans-serif;
    font-size:15px; font-weight:600;
    cursor:pointer; display:flex;
    align-items:center; justify-content:center; gap:8px;
    transition:background .15s, transform .1s;
    margin-top:8px;
  }
  .btn-login:hover { background:#4f46e5; transform:translateY(-1px); }
  .btn-login:active { transform:scale(.98); }

  /* Register link */
  .register-link {
    text-align:center; margin-top:24px;
    font-size:13.5px; color:var(--muted);
  }
  .register-link a { color:var(--accent); font-weight:600; text-decoration:none; }
  .register-link a:hover { text-decoration:underline; }

  /* Responsive */
  @media(max-width:700px){
    .left-panel { display:none; }
    .right-panel { padding:36px 28px; }
    .login-wrap { border-radius:16px; }
  }
</style>
</head>
<body>

<?php if(isset($_GET['registered'])): ?>
<div style="position:fixed;top:24px;right:24px;z-index:999;background:#0f172a;color:#fff;padding:14px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:slideIn .3s ease;transition:opacity .4s;" id="reg-toast">
  <i class="bi bi-check-circle-fill" style="color:#10b981;font-size:18px;"></i> สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ
</div>
<script>setTimeout(()=>{ const t=document.getElementById('reg-toast'); if(t) t.style.opacity='0'; },4000);</script>
<?php endif; ?>

<div class="login-wrap">

  <!-- ── Left Panel ── -->
  <div class="left-panel">
    <div class="brand">
      <div class="brand-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="brand-name">FreelanceHub</div>
        <div class="brand-sub">แพลตฟอร์มหางาน Freelance</div>
      </div>
    </div>

    <div class="left-content">
      <h2>ยินดีต้อนรับกลับมา 👋</h2>
      <p>เชื่อมต่อ Freelancer ที่มีความสามารถกับงานที่ใช่ ได้ง่ายๆ บนแพลตฟอร์มของเรา</p>

      <div class="feature-list">
        <div class="feature-item">
          <i class="bi bi-briefcase"></i>
          งานคุณภาพจากหลายหมวดหมู่
        </div>
        <div class="feature-item">
          <i class="bi bi-shield-check"></i>
          ระบบ Review และ Rating ที่น่าเชื่อถือ
        </div>
        <div class="feature-item">
          <i class="bi bi-chat-dots"></i>
          ติดต่อ Support ได้ตลอดเวลา
        </div>
        <div class="feature-item">
          <i class="bi bi-file-earmark-pdf"></i>
          อัปโหลด Resume และ Portfolio ได้เลย
        </div>
      </div>
    </div>

    <div class="left-footer">
      <p>© 2026 FreelanceHub. All rights reserved.</p>
    </div>
  </div>

  <!-- ── Right Panel ── -->
  <div class="right-panel">

    <h3>เข้าสู่ระบบ</h3>
    <p class="subtitle">กรอกอีเมลและรหัสผ่านของคุณ</p>

    <?php if($error): ?>
    <div class="alert-err">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST">

      <div class="field-group">
        <label>อีเมล</label>
        <div class="input-wrap">
          <i class="bi bi-envelope prefix"></i>
          <input type="email" name="email" class="form-input"
                 placeholder="email@example.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 required autocomplete="email">
        </div>
      </div>

      <div class="field-group">
        <label>รหัสผ่าน</label>
        <div class="input-wrap">
          <i class="bi bi-lock prefix"></i>
          <input type="password" name="password" id="pw-input"
                 class="form-input has-toggle"
                 placeholder="รหัสผ่านของคุณ"
                 required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePw()">
            <i class="bi bi-eye" id="pw-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" name="login" class="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
      </button>

    </form>

    <div class="register-link">
      ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a>
    </div>

  </div>
</div>

<script>
  function togglePw(){
    const input = document.getElementById('pw-input');
    const eye   = document.getElementById('pw-eye');
    if(input.type === 'password'){
      input.type = 'text';
      eye.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      eye.className = 'bi bi-eye';
    }
  }
</script>
</body>
</html>