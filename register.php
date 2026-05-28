<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "location_schema.php";

ensure_location_schema($conn);

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
    $role_raw = $_POST['role'] ?? 'freelancer';
    $role     = in_array($role_raw, ['freelancer', 'employer'], true) ? $role_raw : 'freelancer';
    $gender_raw = $_POST['gender'] ?? '';
    $gender = $role === 'freelancer' ? jobfind_normalize_gender($gender_raw) : '';
    $gender_sql = $gender !== ''
        ? "'" . mysqli_real_escape_string($conn, $gender) . "'"
        : "NULL";
    $age = jobfind_normalize_age($_POST['age'] ?? '');
    $age_sql = $age !== null ? (string)$age : "NULL";
    $address_raw  = trim($_POST['address'] ?? '');
    $province_raw = trim($_POST['province'] ?? '');
    $district_raw = trim($_POST['district'] ?? '');
    $postal_raw   = trim($_POST['postal_code'] ?? '');
    $location_raw = trim(implode(', ', array_filter([$district_raw, $province_raw])));
    if($location_raw === ''){
        $location_raw = $address_raw;
    }
    $address     = mysqli_real_escape_string($conn, $address_raw);
    $province    = mysqli_real_escape_string($conn, $province_raw);
    $district    = mysqli_real_escape_string($conn, $district_raw);
    $postal_code = mysqli_real_escape_string($conn, $postal_raw);
    $location    = mysqli_real_escape_string($conn, $location_raw);
    $latitude    = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude   = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $preferred_radius_km = isset($_POST['preferred_radius_km'])
        ? max(1, min(300, floatval($_POST['preferred_radius_km'])))
        : 30;
    $latitude_sql = $latitude !== null ? sprintf('%.8F', $latitude) : "NULL";
    $longitude_sql = $longitude !== null ? sprintf('%.8F', $longitude) : "NULL";
    $radius_sql = sprintf('%.2F', $preferred_radius_km);

    // check duplicate username / email
    $dup = mysqli_query($conn,"SELECT user_id FROM users WHERE username='$username' OR email='$email'");
    if(mysqli_num_rows($dup) > 0){
        $error = "Username หรือ Email นี้ถูกใช้งานแล้ว";
    } else {
        mysqli_begin_transaction($conn);

        $user_ok = mysqli_query($conn,"
            INSERT INTO users (username,email,password,fullname,phone,gender,role,latitude,longitude)
            VALUES ('$username','$email','$password','$fullname','$phone',$gender_sql,'$role',$latitude_sql,$longitude_sql)
        ");
        $user_id = $user_ok ? mysqli_insert_id($conn) : 0;
        $profile_ok = false;

        if($user_ok && $user_id > 0 && $role == "freelancer"){
            $profile_ok = mysqli_query($conn,"
                INSERT INTO freelancer_profile
                    (user_id,skill,experience,age,location,address,province,district,postal_code,latitude,longitude,preferred_radius_km)
                VALUES
                    ('$user_id','','',$age_sql,'$location','$address','$province','$district','$postal_code',$latitude_sql,$longitude_sql,$radius_sql)
            ");
        }
        if($user_ok && $user_id > 0 && $role == "employer"){
            $profile_ok = mysqli_query($conn,"
                INSERT INTO employer_profile
                    (user_id,employer_name,employer_description,address,province,district,postal_code,latitude,longitude)
                VALUES
                    ('$user_id','$fullname','','$address','$province','$district','$postal_code',$latitude_sql,$longitude_sql)
            ");
        }

        if($user_ok && $profile_ok){
            mysqli_commit($conn);
            header("Location: login.php?registered=1");
            exit();
        }

        mysqli_rollback($conn);
        error_log('Register failed: ' . mysqli_error($conn));
        $error = "สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
    }
}

$selected_role = $_POST['role'] ?? 'freelancer';
$selected_role = in_array($selected_role, ['freelancer', 'employer'], true) ? $selected_role : 'freelancer';
$selected_gender = $selected_role === 'freelancer' ? jobfind_normalize_gender($_POST['gender'] ?? '') : '';
$selected_age = jobfind_normalize_age($_POST['age'] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — FreelanceHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.min.css" />
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

  body { font-family:'Sora',sans-serif; background:var(--light); min-height:100vh; display:flex; align-items:flex-start; justify-content:center; padding:28px 0; }

  /* ── Card ── */
  .register-wrap {
    display:flex; width:920px; max-width:95vw;
    background:var(--white); border-radius:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.12);
    overflow:hidden;
  }

  /* ── Left panel ── */
  .left-panel {
    width:340px; flex-shrink:0;
    background:var(--navy);
    padding:44px 34px;
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
  .right-panel { flex:1; padding:34px 38px; display:flex; flex-direction:column; justify-content:flex-start; overflow-y:auto; }

  .right-panel h3 { font-size:22px; font-weight:600; margin-bottom:4px; }
  .right-panel .subtitle { font-size:13.5px; color:var(--muted); margin-bottom:20px; }

  /* Alert */
  .alert-err { background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:12px 16px; font-size:13.5px; color:#991b1b; display:flex; align-items:center; gap:8px; margin-bottom:20px; }

  /* Fields */
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .field-group { margin-bottom:12px; }
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
  .freelancer-only.is-hidden { display:none; }

  /* Address and map */
  .textarea-wrap i.prefix { top:18px; transform:none; }
  textarea.form-input { min-height:68px; resize:vertical; line-height:1.5; }
  .location-status { margin-top:7px; font-size:12px; color:var(--muted); display:flex; align-items:center; gap:5px; }
  .btn-open-map { display:inline-flex; align-items:center; gap:7px; background:#eef2ff; color:var(--accent); border:1px solid #c7d2fe; border-radius:10px; padding:9px 14px; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s,color .15s; }
  .btn-open-map:hover { background:#c7d2fe; color:var(--accent); }
  .radius-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
  .radius-row span { font-size:12px; color:var(--muted); }
  .radius-value { font-size:13px; font-weight:700; color:var(--accent); white-space:nowrap; }
  .radius-slider { width:100%; accent-color:var(--accent); }
  .radius-scale { display:flex; justify-content:space-between; margin-top:6px; font-size:11px; color:var(--muted); }
  .map-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.62); z-index:1000; align-items:center; justify-content:center; padding:22px; }
  .map-modal.active { display:flex; }
  .map-container { width:min(900px,100%); height:min(760px,92vh); background:#fff; border-radius:18px; display:flex; flex-direction:column; box-shadow:0 24px 70px rgba(15,23,42,.32); overflow:hidden; }
  .map-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .map-header h3 { font-size:17px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
  .map-close { border:0; background:transparent; color:var(--muted); font-size:26px; line-height:1; cursor:pointer; }
  .map-info { margin:16px 16px 0; padding:12px 14px; background:#eef2ff; color:#3730a3; border-radius:10px; font-size:13px; }
  .map-radius-control { display:none; margin:12px 16px 14px; padding:13px 14px; border:1px solid var(--border); border-radius:12px; background:#f8fafc; }
  .map-radius-control.active { display:block; }
  #register-map { flex:1; min-height:360px; margin-top:16px; }
  .map-footer { padding:14px 18px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
  .map-footer button { border:0; border-radius:10px; padding:10px 18px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn-map-cancel { background:var(--light); color:var(--text); }
  .btn-map-confirm { background:var(--accent); color:#fff; }

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

  @media(max-width:900px){
    .left-panel { display:none; }
    .register-wrap { width:min(620px,95vw); }
  }

  @media(max-width:700px){
    .right-panel { padding:32px 24px; }
    .two-col { grid-template-columns:1fr; }
    .role-select-wrap { grid-template-columns:1fr; }
    .map-modal { padding:0; }
    .map-container { width:100%; height:100%; max-height:none; border-radius:0; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
<style>
  .register-wrap > .left-panel .left-content h2 {
    color:#ffffff !important;
    -webkit-text-fill-color:#ffffff !important;
    text-shadow:0 2px 16px rgba(0,0,0,.45);
  }
</style>

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

      <div class="field-group freelancer-only" id="freelancer-gender-field">
        <label>เพศ</label>
        <div class="input-wrap">
          <i class="bi bi-gender-ambiguous prefix"></i>
          <select name="gender" class="form-input">
            <option value="">เลือกเพศ</option>
            <?php foreach(jobfind_gender_options() as $gender_value => $gender_label): ?>
              <option value="<?php echo htmlspecialchars($gender_value); ?>" <?php echo $selected_gender === $gender_value ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($gender_label); ?>
              </option>
            <?php endforeach; ?>
          </select>
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

      <div class="field-group freelancer-only" id="freelancer-age-field">
        <label>อายุ</label>
        <div class="input-wrap">
          <i class="bi bi-calendar3 prefix"></i>
          <input type="number" name="age" class="form-input"
                 min="1" max="120" inputmode="numeric"
                 placeholder="เช่น 25"
                 value="<?php echo htmlspecialchars($selected_age ?? ''); ?>">
        </div>
      </div>

      <!-- Address -->
      <div class="section-label" style="margin-top:4px;" id="address-section-label">ที่อยู่และพื้นที่หางาน</div>

      <div class="field-group">
        <label id="address-label">ที่อยู่</label>
        <div class="input-wrap textarea-wrap">
          <i class="bi bi-geo-alt prefix"></i>
          <textarea name="address" class="form-input" placeholder="เช่น 123 ซ.สุขุมวิท ถ.สุขุมวิท..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        </div>
      </div>

      <div class="two-col">
        <div class="field-group">
          <label>จังหวัด</label>
          <div class="input-wrap">
            <i class="bi bi-building prefix"></i>
            <input type="text" name="province" class="form-input"
                   placeholder="เช่น กรุงเทพมหานคร"
                   value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
          </div>
        </div>
        <div class="field-group">
          <label>อำเภอ / เขต</label>
          <div class="input-wrap">
            <i class="bi bi-pin-map prefix"></i>
            <input type="text" name="district" class="form-input"
                   placeholder="เช่น วัฒนา"
                   value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>">
          </div>
        </div>
      </div>

      <div class="field-group">
        <label>รหัสไปรษณีย์</label>
        <div class="input-wrap">
          <i class="bi bi-mailbox prefix"></i>
          <input type="text" name="postal_code" class="form-input"
                 placeholder="10110"
                 value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
        </div>
      </div>

      <div class="field-group">
        <label id="pin-label">ปักพื้นที่หางานบนแผนที่</label>
        <button type="button" class="btn-open-map" onclick="openRegisterMapModal()">
          <i class="bi bi-geo"></i> เลือกตำแหน่งจากแผนที่
        </button>
        <div class="location-status" id="location-status">
          <i class="bi bi-info-circle"></i> ยังไม่ได้ปักหมุด
        </div>
        <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
        <input type="hidden" name="preferred_radius_km" id="preferred_radius_km" value="<?php echo htmlspecialchars($_POST['preferred_radius_km'] ?? 30); ?>">
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

<div class="map-modal" id="registerMapModal">
  <div class="map-container">
    <div class="map-header">
      <h3><i class="bi bi-geo"></i> <span id="map-title">ปักพื้นที่หางาน</span></h3>
      <button type="button" class="map-close" onclick="closeRegisterMapModal()">&times;</button>
    </div>
    <div class="map-info" id="map-info">
      คลิกบนแผนที่เพื่อเลือกตำแหน่งของคุณ ระบบจะใช้ตำแหน่งนี้แนะนำงานใกล้เคียง
    </div>
    <div class="map-radius-control" id="map-radius-control">
      <div class="radius-row">
        <span>วงค้นหางานบนแผนที่</span>
        <strong class="radius-value"><span id="map-radius-label"><?php echo htmlspecialchars($_POST['preferred_radius_km'] ?? 30); ?></span> กม.</strong>
      </div>
      <input class="radius-slider" type="range" id="map_preferred_radius_slider"
             min="1" max="300" step="1"
             value="<?php echo htmlspecialchars($_POST['preferred_radius_km'] ?? 30); ?>"
             oninput="updateRegisterRadius(this.value)">
      <div class="radius-scale">
        <span>1 กม.</span>
        <span>300 กม.</span>
      </div>
    </div>
    <div id="register-map"></div>
    <div class="map-footer">
      <button type="button" class="btn-map-cancel" onclick="closeRegisterMapModal()">ยกเลิก</button>
      <button type="button" class="btn-map-confirm" onclick="confirmRegisterMapLocation()">ยืนยัน</button>
    </div>
  </div>
</div>

<script src="assets/vendor/leaflet/leaflet.min.js"></script>
<script src="assets/js/location-map-picker.js"></script>
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

  let registerMap = null;
  let registerMapRole = '';
  let selectedLat = Number(document.getElementById('latitude')?.value || 13.7563);
  let selectedLng = Number(document.getElementById('longitude')?.value || 100.5018);
  let hasSelectedPin = Boolean(document.getElementById('latitude')?.value && document.getElementById('longitude')?.value);
  let selectedRadiusKm = Number(document.getElementById('preferred_radius_km')?.value || 30);

  function getSelectedRole(){
    return document.querySelector('input[name="role"]:checked')?.value || 'freelancer';
  }

  function updateRoleLocationCopy(){
    const role = getSelectedRole();
    const isFreelancer = role === 'freelancer';
    document.getElementById('address-section-label').textContent = isFreelancer ? 'ที่อยู่และพื้นที่หางาน' : 'ที่อยู่และพื้นที่บริษัท';
    document.getElementById('address-label').textContent = isFreelancer ? 'ที่อยู่ของคุณ' : 'ที่อยู่บริษัท';
    document.getElementById('pin-label').textContent = isFreelancer ? 'ปักพื้นที่หางานบนแผนที่' : 'ปักพื้นที่บริษัทบนแผนที่';
    document.getElementById('map-title').textContent = isFreelancer ? 'ปักพื้นที่หางาน' : 'ปักพื้นที่บริษัท';
    document.getElementById('map-info').textContent = isFreelancer
      ? 'คลิกบนแผนที่เพื่อเลือกตำแหน่งของคุณ ระบบจะใช้ตำแหน่งนี้แนะนำงานใกล้เคียง'
      : 'คลิกบนแผนที่เพื่อเลือกตำแหน่งบริษัท ระบบจะใช้ตำแหน่งนี้ช่วยแมชงานกับ freelancer';

    document.getElementById('map-radius-control').classList.toggle('active', isFreelancer);
    document.querySelectorAll('.freelancer-only').forEach(el => {
      el.classList.toggle('is-hidden', !isFreelancer);
      el.querySelectorAll('input, select, textarea').forEach(control => {
        control.disabled = !isFreelancer;
      });
    });

    if (registerMap && registerMapRole !== role && typeof registerMap.destroy === 'function') {
      registerMap.destroy();
      registerMap = null;
    }
  }

  function setRegisterPosition(lat, lng) {
    selectedLat = Number(lat);
    selectedLng = Number(lng);
    hasSelectedPin = true;
  }

  function updateRegisterRadius(value) {
    selectedRadiusKm = Math.max(1, Math.min(300, Number(value) || 30));
    document.getElementById('preferred_radius_km').value = selectedRadiusKm;
    document.getElementById('map_preferred_radius_slider').value = selectedRadiusKm;
    document.getElementById('map-radius-label').textContent = selectedRadiusKm;

    if (registerMap) {
      registerMap.setRadius(selectedRadiusKm);
    }
  }

  function openRegisterMapModal() {
    updateRoleLocationCopy();
    const role = getSelectedRole();
    const showCircle = role === 'freelancer';
    document.getElementById('registerMapModal').classList.add('active');

    setTimeout(() => {
      if (!registerMap || registerMapRole !== role) {
        registerMap = createJobFindMapPicker({
          elementId: 'register-map',
          lat: selectedLat,
          lng: selectedLng,
          hasPin: hasSelectedPin,
          radiusKm: selectedRadiusKm,
          showCircle: showCircle,
          onChange: setRegisterPosition
        });
        registerMapRole = role;
      }

      if (registerMap) {
        registerMap.resize();
        if (hasSelectedPin) {
          registerMap.setView(selectedLat, selectedLng);
          registerMap.setRadius(selectedRadiusKm);
        }
      }
    }, 100);
  }

  function closeRegisterMapModal() {
    document.getElementById('registerMapModal').classList.remove('active');
  }

  function confirmRegisterMapLocation() {
    if (!hasSelectedPin) {
      alert('กรุณาเลือกตำแหน่งบนแผนที่');
      return;
    }

    document.getElementById('latitude').value = selectedLat.toFixed(6);
    document.getElementById('longitude').value = selectedLng.toFixed(6);
    document.getElementById('location-status').innerHTML = '<i class="bi bi-check-circle"></i> ปักหมุดแล้ว';
    closeRegisterMapModal();
  }

  document.querySelectorAll('input[name="role"]').forEach(input => {
    input.addEventListener('change', updateRoleLocationCopy);
  });

  document.getElementById('registerMapModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeRegisterMapModal();
    }
  });

  updateRoleLocationCopy();
  updateRegisterRadius(selectedRadiusKm);
  if (hasSelectedPin) {
    document.getElementById('location-status').innerHTML = '<i class="bi bi-check-circle"></i> ปักหมุดแล้ว';
  }
</script>
</body>
</html>
