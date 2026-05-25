<?php
session_start();
include("config.php");

if(isset($_SESSION['role'])){
    if($_SESSION['role']=="admin")      { header("Location: admin_dashboard.php");      exit(); }
    if($_SESSION['role']=="employer")   { header("Location: employer_dashboard.php");   exit(); }
    if($_SESSION['role']=="freelancer") { header("Location: freelancer_dashboard.php"); exit(); }
}

$error   = '';
$success = false;

if(isset($_POST['register'])){
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role     = mysqli_real_escape_string($conn, $_POST['role']);

    // check duplicate username / email
    $dup = mysqli_query($conn,"SELECT user_id FROM users WHERE username='$username' OR email='$email'");
    if(mysqli_num_rows($dup) > 0){
        $error = "Username หรือ Email นี้ถูกใช้งานแล้ว";
    } else {
        mysqli_query($conn,"
            INSERT INTO users (username,email,password,fullname,phone,role)
            VALUES ('$username','$email','$password','$fullname','$phone','$role')
        ");
        $user_id = mysqli_insert_id($conn);

        if($role == "freelancer"){
            mysqli_query($conn,"
                INSERT INTO freelancer_profile (user_id,skill,experience,location)
                VALUES ('$user_id','','','')
            ");
        }
        if($role == "employer"){
            mysqli_query($conn,"
                INSERT INTO employer_profile (user_id,employer_name,employer_description)
                VALUES ('$user_id','$fullname','')
            ");
        }

        header("Location: login.php?registered=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — FreelanceHub</title>
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

  body { font-family:'Sora',sans-serif; background:var(--light); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 0; }

  /* ── Card ── */
  .register-wrap {
    display:flex; width:960px; max-width:95vw;
    background:var(--white); border-radius:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.12);
    overflow:hidden; min-height:600px;
  }

  /* ── Left panel ── */
  .left-panel {
    width:380px; flex-shrink:0;
    background:var(--navy);
    padding:52px 40px;
    display:flex; flex-direction:column;
    justify-content:space-between;
    position:relative; overflow:hidden;
  }
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
  .brand-icon { width:44px; height:44px; border-radius:12px; background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; }
  .brand-name { font-size:20px; font-weight:700; color:#fff; }
  .brand-sub  { font-size:12px; color:#94a3b8; }

  .left-content { z-index:1; }
  .left-content h2 { font-size:26px; font-weight:700; color:#fff; line-height:1.3; margin-bottom:14px; }
  .left-content p  { font-size:14px; color:#94a3b8; line-height:1.8; }

  /* Role cards */
  .role-cards { margin-top:28px; display:flex; flex-direction:column; gap:12px; }
  .role-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:14px 16px; }
  .role-card .rc-title { font-size:13.5px; font-weight:600; color:#e2e8f0; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
  .role-card .rc-title span { font-size:18px; }
  .role-card .rc-desc  { font-size:12px; color:#64748b; line-height:1.6; }

  .left-footer { z-index:1; }
  .left-footer p { font-size:12px; color:#475569; }

  /* ── Right panel ── */
  .right-panel { flex:1; padding:48px 44px; display:flex; flex-direction:column; justify-content:center; overflow-y:auto; }

  .right-panel h3 { font-size:22px; font-weight:600; margin-bottom:4px; }
  .right-panel .subtitle { font-size:13.5px; color:var(--muted); margin-bottom:28px; }

  /* Alert */
  .alert-err { background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:12px 16px; font-size:13.5px; color:#991b1b; display:flex; align-items:center; gap:8px; margin-bottom:20px; }

  /* Fields */
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .field-group { margin-bottom:14px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:5px; color:var(--text); }
  .field-group label .req { color:var(--red); margin-left:2px; }

  .input-wrap { position:relative; }
  .input-wrap i.prefix { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:15px; color:var(--muted); pointer-events:none; }
  .form-input { width:100%; padding:11px 14px 11px 38px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13.5px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  .toggle-pw { position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--muted); cursor:pointer; border:none; background:none; padding:0; }
  .toggle-pw:hover { color:var(--accent); }
  .has-toggle { padding-right:38px; }

  /* Role select styled */
  .role-select-wrap { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
  .role-opt { position:relative; }
  .role-opt input[type="radio"] { display:none; }
  .role-opt label {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; border:1.5px solid var(--border);
    border-radius:10px; cursor:pointer;
    transition:border-color .15s, background .15s;
    font-size:13.5px; font-weight:500; color:var(--muted);
  }
  .role-opt label .role-icon { font-size:20px; }
  .role-opt input:checked + label { border-color:var(--accent); background:#eef2ff; color:var(--accent); }
  .role-opt label .checkmark { margin-left:auto; width:18px; height:18px; border-radius:50%; border:2px solid var(--border); transition:all .15s; flex-shrink:0; }
  .role-opt input:checked + label .checkmark { border-color:var(--accent); background:var(--accent); box-shadow:inset 0 0 0 3px #fff; }

  /* Submit */
  .btn-register { width:100%; padding:13px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:15px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:background .15s,transform .1s; margin-top:6px; }
  .btn-register:hover { background:#4f46e5; transform:translateY(-1px); }

  /* Section label */
  .section-label { font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
  .section-label::after { content:''; flex:1; height:1px; background:var(--border); }

  /* Login link */
  .login-link { text-align:center; margin-top:20px; font-size:13.5px; color:var(--muted); }
  .login-link a { color:var(--accent); font-weight:600; text-decoration:none; }
  .login-link a:hover { text-decoration:underline; }

  @media(max-width:700px){
    .left-panel { display:none; }
    .right-panel { padding:32px 24px; }
    .two-col { grid-template-columns:1fr; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<div class="register-wrap">

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
      <h2>เริ่มต้นกับเรา วันนี้ 🚀</h2>
      <p>สมัครฟรี เริ่มหางานหรือโพสต์งานได้ทันที ไม่มีค่าใช้จ่ายแอบแฝง</p>

      <div class="role-cards">
        <div class="role-card">
          <div class="rc-title"><span>💼</span> Freelancer</div>
          <div class="rc-desc">หางานที่ใช่ อัปโหลด Resume รับรีวิวจาก Employer</div>
        </div>
        <div class="role-card">
          <div class="rc-title"><span>🏢</span> Employer</div>
          <div class="rc-desc">โพสต์งาน คัดเลือก Freelancer รีวิวคนที่ทำงานด้วย</div>
        </div>
      </div>
    </div>

    <div class="left-footer">
      <p>© 2026 FreelanceHub. All rights reserved.</p>
    </div>
  </div>

  <!-- ── Right Panel ── -->
  <div class="right-panel">

    <h3>สมัครสมาชิก</h3>
    <p class="subtitle">กรอกข้อมูลเพื่อสร้างบัญชีใหม่</p>

    <?php if($error): ?>
    <div class="alert-err">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST">

      <!-- บัญชี -->
      <div class="section-label">ข้อมูลบัญชี</div>

      <div class="two-col">
        <div class="field-group">
          <label>Username <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-at prefix"></i>
            <input type="text" name="username" class="form-input"
                   placeholder="username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   required maxlength="50">
          </div>
        </div>
        <div class="field-group">
          <label>Email <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-envelope prefix"></i>
            <input type="email" name="email" class="form-input"
                   placeholder="email@example.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   required>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label>รหัสผ่าน <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="bi bi-lock prefix"></i>
          <input type="password" name="password" id="pw-input"
                 class="form-input has-toggle"
                 placeholder="อย่างน้อย 6 ตัวอักษร"
                 required minlength="6">
          <button type="button" class="toggle-pw" onclick="togglePw()">
            <i class="bi bi-eye" id="pw-eye"></i>
          </button>
        </div>
      </div>

      <!-- ข้อมูลส่วนตัว -->
      <div class="section-label" style="margin-top:4px;">ข้อมูลส่วนตัว</div>

      <div class="two-col">
        <div class="field-group">
          <label>ชื่อ-นามสกุล / บริษัท <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-person prefix"></i>
            <input type="text" name="fullname" class="form-input"
                   placeholder="ชื่อจริงหรือชื่อบริษัท"
                   value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>"
                   required>
          </div>
        </div>
        <div class="field-group">
          <label>เบอร์โทรศัพท์</label>
          <div class="input-wrap">
            <i class="bi bi-telephone prefix"></i>
            <input type="text" name="phone" class="form-input"
                   placeholder="0xx-xxx-xxxx"
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <!-- Role -->
      <div class="section-label" style="margin-top:4px;">สมัครในฐานะ</div>
      <div class="role-select-wrap">
        <div class="role-opt">
          <input type="radio" name="role" id="role-fl" value="freelancer"
                 <?php echo (($_POST['role'] ?? 'freelancer')==='freelancer')?'checked':''; ?>>
          <label for="role-fl">
            <span class="role-icon">💼</span>
            Freelancer
            <span class="checkmark"></span>
          </label>
        </div>
        <div class="role-opt">
          <input type="radio" name="role" id="role-em" value="employer"
                 <?php echo (($_POST['role'] ?? '')==='employer')?'checked':''; ?>>
          <label for="role-em">
            <span class="role-icon">🏢</span>
            Employer
            <span class="checkmark"></span>
          </label>
        </div>
      </div>

      <button type="submit" name="register" class="btn-register">
        <i class="bi bi-person-plus"></i> สมัครสมาชิก
      </button>

    </form>

    <div class="login-link">
      มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
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