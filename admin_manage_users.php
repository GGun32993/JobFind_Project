<?php
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/location_schema.php";
require_once __DIR__ . "/profile_image_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin"){
    header("Location: login.php"); exit();
}

ensure_location_schema($conn);
ensure_profile_image_schema($conn);

function admin_profile_location_text($row, $prefix) {
    $district = trim($row[$prefix . '_district'] ?? '');
    $province = trim($row[$prefix . '_province'] ?? '');
    $address = trim($row[$prefix . '_address'] ?? '');
    $legacy_location = trim($row[$prefix . '_location'] ?? '');

    $parts = array_filter([$district, $province]);
    if (!empty($parts)) {
        return implode(', ', $parts);
    }
    if ($legacy_location !== '') {
        return $legacy_location;
    }
    return $address;
}

function admin_profile_preview($row) {
    $role = $row['role'] ?? '';
    if ($role === 'freelancer') {
        return [
            'exists' => !empty($row['freelancer_id']),
            'title' => trim($row['freelancer_skill'] ?? ''),
            'meta' => trim($row['freelancer_experience'] ?? ''),
            'location' => admin_profile_location_text($row, 'freelancer')
        ];
    }
    if ($role === 'employer') {
        return [
            'exists' => !empty($row['employer_id']),
            'title' => trim($row['employer_name'] ?? ''),
            'meta' => trim($row['employer_description'] ?? ''),
            'location' => admin_profile_location_text($row, 'employer')
        ];
    }
    return [
        'exists' => true,
        'title' => 'Admin account',
        'meta' => '',
        'location' => ''
    ];
}

$admin_unread_support = 0;
$admin_id_for_badge = (int)$_SESSION['user_id'];
$col_check = mysqli_query($conn,"SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
if($col_check && mysqli_num_rows($col_check) === 0){
    mysqli_query($conn,"ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
}
$unread_support = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c
    FROM chat_messages cm
    JOIN users u ON u.user_id=cm.sender_id
    WHERE cm.receiver_id='$admin_id_for_badge'
    AND cm.is_read=0
"));
$admin_unread_support = (int)($unread_support['c'] ?? 0);

$toast = '';

// ── DELETE ──
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    if($del_id != $_SESSION['user_id']){
        mysqli_query($conn,"DELETE FROM users WHERE user_id='$del_id'");
        $toast = 'deleted';
    } else { $toast = 'self'; }
    header("Location: admin_manage_users.php?toast=$toast"); exit();
}

// ── ADD USER ──
if(isset($_POST['add_user'])){
    $un   = mysqli_real_escape_string($conn, trim($_POST['username']));
    $em   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $pw   = mysqli_real_escape_string($conn, $_POST['password']);
    $fn   = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $ph   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $dup = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM users WHERE username='$un' OR email='$em'"));
    if($dup){
        $toast = 'dup';
    } else {
        mysqli_query($conn,"
            INSERT INTO users (username,email,password,fullname,phone,role)
            VALUES ('$un','$em','$pw','$fn','$ph','$role')
        ");
        $new_id = mysqli_insert_id($conn);
        if($role === 'freelancer'){
            mysqli_query($conn,"INSERT INTO freelancer_profile (user_id,skill,experience,location) VALUES ('$new_id','','','')");
        } elseif($role === 'employer'){
            mysqli_query($conn,"INSERT INTO employer_profile (user_id,employer_name,employer_description) VALUES ('$new_id','$fn','')");
        }
        $toast = 'added';
    }
    header("Location: admin_manage_users.php?toast=$toast"); exit();
}

// ── GET USERS ──
$result = mysqli_query($conn,"
    SELECT
        u.*,
        fp.freelancer_id,
        fp.skill AS freelancer_skill,
        fp.experience AS freelancer_experience,
        fp.location AS freelancer_location,
        fp.address AS freelancer_address,
        fp.province AS freelancer_province,
        fp.district AS freelancer_district,
        fp.postal_code AS freelancer_postal_code,
        ep.employer_id,
        ep.employer_name,
        ep.employer_description,
        ep.address AS employer_address,
        ep.province AS employer_province,
        ep.district AS employer_district,
        ep.postal_code AS employer_postal_code
    FROM users u
    LEFT JOIN freelancer_profile fp ON fp.user_id = u.user_id
    LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
    ORDER BY u.created_at DESC
");
$rows = []; $count_fl = 0; $count_em = 0; $count_ad = 0;
while($u = mysqli_fetch_assoc($result)){
    $rows[] = $u;
    if($u['role']==='freelancer')  $count_fl++;
    elseif($u['role']==='employer') $count_em++;
    elseif($u['role']==='admin')    $count_ad++;
}
$count_all = count($rows);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=9">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --navy:#0f172a;--navy2:#1e293b;--navy3:#334155;
    --accent:#6366f1;--light:#f1f5f9;--white:#ffffff;
    --text:#0f172a;--muted:#64748b;--border:#e2e8f0;
    --green:#10b981;--yellow:#f59e0b;--red:#ef4444;--blue:#0ea5e9;--radius:14px;
  }
  body{font-family:'Sora',sans-serif;background:var(--light);color:var(--text);display:flex;min-height:100vh;}

  /* Sidebar */
  .sidebar{width:240px;min-height:100vh;background:var(--navy);display:flex;flex-direction:column;padding:28px 0;position:fixed;top:0;left:0;z-index:100;}
  .sidebar-brand{padding:0 24px 28px;border-bottom:1px solid var(--navy3);}
  .logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
  .logo-icon{width:36px;height:36px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;}
  .logo-text{font-size:15px;font-weight:600;color:#fff;line-height:1.2;}
  .logo-sub{font-size:11px;color:var(--navy3);}
  .sidebar-nav{padding:20px 12px;flex:1;display:flex;flex-direction:column;gap:4px;}
  .nav-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s,color .15s;}
  .nav-item:hover{background:var(--navy2);color:#e2e8f0;}
  .nav-item.active{background:var(--accent);color:#fff;}
  .nav-item i{font-size:17px;width:20px;text-align:center;}
  .nav-divider{height:1px;background:var(--navy3);margin:10px 14px;}
  .sidebar-footer{padding:16px 12px 0;}
  .nav-logout{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#f87171;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s;}
  .nav-logout:hover{background:rgba(239,68,68,.12);}

  /* Main */
  .main{margin-left:240px;flex:1;padding:36px 40px;}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
  .topbar h2{font-size:22px;font-weight:600;}
  .topbar p{font-size:13px;color:var(--muted);margin-top:2px;}
  .btn-add{display:inline-flex;align-items:center;gap:7px;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:11px 22px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;transition:background .15s;}
  .btn-add:hover{background:#4f46e5;}

  /* Toast */
  .toast-bar{position:fixed;top:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.18);animation:slideIn .3s ease;transition:opacity .4s;}
  .t-ok{background:var(--navy);color:#fff;}
  .t-err{background:#7f1d1d;color:#fff;}
  @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

  /* Stat */
  .stat-row{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
  .stat-mini{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;display:flex;align-items:center;gap:12px;flex:1;min-width:120px;}
  .sm-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
  .sm-val{font-size:22px;font-weight:600;line-height:1;}
  .sm-lbl{font-size:12px;color:var(--muted);margin-top:3px;}
  .si-purple{background:#eef2ff;color:var(--accent);}
  .si-blue{background:#e0f2fe;color:var(--blue);}
  .si-green{background:#d1fae5;color:var(--green);}
  .si-gray{background:#f1f5f9;color:var(--navy3);}

  /* Toolbar */
  .toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
  .search-wrap{position:relative;flex:1;min-width:200px;}
  .search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--muted);pointer-events:none;}
  .search-wrap input{width:100%;padding:10px 14px 10px 38px;border:1px solid var(--border);border-radius:10px;font-family:'Sora',sans-serif;font-size:13.5px;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;}
  .search-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,.12);}
  .filter-sel{padding:10px 14px;border:1px solid var(--border);border-radius:10px;font-family:'Sora',sans-serif;font-size:13.5px;color:var(--text);outline:none;background:var(--white);}
  .result-info{font-size:13px;color:var(--muted);white-space:nowrap;}

  /* Table */
  .table-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
  .data-table{width:100%;border-collapse:collapse;}
  .data-table thead th{font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:13px 20px;text-align:left;background:var(--light);border-bottom:1px solid var(--border);white-space:nowrap;}
  .data-table tbody td{font-size:13.5px;padding:13px 20px;border-bottom:1px solid var(--border);vertical-align:middle;}
  .data-table tbody tr:last-child td{border-bottom:none;}
  .data-table tbody tr:hover td{background:#f8f9ff;}
  .data-table tbody tr.hidden{display:none;}

  .user-cell{display:flex;align-items:center;gap:10px;}
  .u-av{width:36px;height:36px;border-radius:50%;font-size:13px;font-weight:600;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  .u-av img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;}
  .ua-freelancer{background:var(--accent);}
  .ua-employer{background:var(--blue);}
  .ua-admin{background:var(--navy3);}
  .u-name{font-weight:600;font-size:13.5px;}
  .u-email{font-size:12px;color:var(--muted);margin-top:2px;}
  .you-tag{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;background:#eef2ff;color:var(--accent);margin-left:6px;vertical-align:middle;}

  .profile-cell{min-width:240px;max-width:360px;}
  .profile-main{font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:7px;line-height:1.35;}
  .profile-main i{color:var(--accent);font-size:14px;flex-shrink:0;}
  .profile-sub,.profile-loc{font-size:12px;color:var(--muted);margin-top:4px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .profile-loc{display:flex;-webkit-line-clamp:unset;-webkit-box-orient:initial;align-items:center;gap:5px;}
  .profile-empty{font-size:12px;color:var(--muted);font-style:italic;}

  .role-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:4px 11px;border-radius:20px;}
  .rb-freelancer{background:#eef2ff;color:var(--accent);}
  .rb-employer{background:#e0f2fe;color:#0369a1;}
  .rb-admin{background:#fee2e2;color:#991b1b;}

  .act-wrap{display:flex;gap:6px;}
  .btn-edit{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:8px;font-size:12.5px;font-weight:500;background:#fef9c3;color:#854d0e;text-decoration:none;transition:opacity .15s,transform .1s;}
  .btn-edit:hover{opacity:.85;transform:translateY(-1px);color:#854d0e;}
  .btn-del{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:8px;font-size:12.5px;font-weight:500;background:#fee2e2;color:#991b1b;border:none;cursor:pointer;font-family:'Sora',sans-serif;transition:opacity .15s,transform .1s;}
  .btn-del:hover{opacity:.85;transform:translateY(-1px);}
  .self-lock{font-size:12px;color:var(--muted);display:inline-flex;align-items:center;gap:4px;}

  .empty-row td{text-align:center;padding:52px;color:var(--muted);}
  .empty-row i{font-size:40px;color:#c7d2fe;display:block;margin-bottom:10px;}

  /* Modals */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;}
  .modal-overlay.show{display:flex;}
  .modal-box{background:var(--white);border-radius:var(--radius);padding:30px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.22);}
  .modal-box.wide{max-width:500px;}
  .modal-title{font-size:17px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;}
  .modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--muted);line-height:1;padding:0;}
  .modal-close:hover{color:var(--text);}
  .modal-icon{width:54px;height:54px;border-radius:50%;background:#fee2e2;color:var(--red);font-size:26px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
  .modal-sub{font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:24px;}
  .modal-actions{display:flex;gap:10px;}
  .btn-mc{flex:1;padding:11px;border:1px solid var(--border);border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:500;cursor:pointer;background:var(--white);color:var(--text);}
  .btn-mc:hover{background:var(--light);}
  .btn-md{flex:1;padding:11px;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:600;cursor:pointer;background:var(--red);color:#fff;text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px;}
  .btn-md:hover{opacity:.88;color:#fff;}

  /* Add form inputs */
  .add-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
  .add-field{margin-bottom:12px;}
  .add-field label{display:block;font-size:13px;font-weight:500;margin-bottom:5px;}
  .add-input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:10px;font-family:'Sora',sans-serif;font-size:13.5px;color:var(--text);outline:none;transition:border-color .15s;}
  .add-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,.12);}
  .role-pills{display:flex;gap:8px;}
  .role-pill input{display:none;}
  .role-pill label{display:flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:13px;transition:all .15s;white-space:nowrap;}
  .role-pill input:checked + label{border-color:var(--accent);background:#eef2ff;color:var(--accent);font-weight:600;}
  .btn-submit{width:100%;padding:11px;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:600;cursor:pointer;background:var(--accent);color:#fff;margin-top:8px;}
  .btn-submit:hover{background:#4f46e5;}

  @media(max-width:768px){.sidebar{display:none;}.main{margin-left:0;padding:20px 16px;}.add-grid{grid-template-columns:1fr;}}
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- Toast -->
<?php
$toasts = [
    'deleted'=>['ok','bi-check-circle-fill','var(--green)','ลบผู้ใช้เรียบร้อยแล้ว'],
    'added'  =>['ok','bi-check-circle-fill','var(--green)','เพิ่มผู้ใช้ใหม่เรียบร้อยแล้ว'],
    'self'   =>['err','bi-exclamation-triangle-fill','#fca5a5','ไม่สามารถลบบัญชีของตัวเองได้'],
    'dup'    =>['err','bi-exclamation-triangle-fill','#fca5a5','Username หรือ Email นี้ถูกใช้งานแล้ว'],
];
if(isset($_GET['toast']) && isset($toasts[$_GET['toast']])):
    [$type,$icon,$color,$msg] = $toasts[$_GET['toast']];
?>
<div class="toast-bar t-<?php echo $type; ?>" id="toast">
  <i class="bi <?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:18px;"></i>
  <?php echo $msg; ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.opacity='0';},3000);</script>
<?php endif; ?>

<!-- Delete Modal -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-trash3"></i></div>
    <div style="font-size:17px;font-weight:600;margin-bottom:6px;">ยืนยันการลบผู้ใช้</div>
    <div class="modal-sub">คุณต้องการลบ <strong id="del-name"></strong> ออกจากระบบ?<br>ข้อมูลทั้งหมดจะหายไปและไม่สามารถกู้คืนได้</div>
    <div class="modal-actions">
      <button class="btn-mc" onclick="closeDelModal()">ยกเลิก</button>
      <a href="#" id="del-link" class="btn-md"><i class="bi bi-trash3"></i> ลบเลย</a>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box wide">
    <div class="modal-title">
      <span><i class="bi bi-person-plus" style="color:var(--accent);margin-right:8px;"></i>เพิ่มผู้ใช้ใหม่</span>
      <button class="modal-close" onclick="closeAddModal()">×</button>
    </div>
    <form method="POST">
      <div class="add-grid">
        <div class="add-field" style="margin-bottom:0;">
          <label>Username <span style="color:var(--red);">*</span></label>
          <input type="text" name="username" class="add-input" placeholder="username" required maxlength="50">
        </div>
        <div class="add-field" style="margin-bottom:0;">
          <label>ชื่อ-นามสกุล / บริษัท</label>
          <input type="text" name="fullname" class="add-input" placeholder="ชื่อจริงหรือชื่อบริษัท">
        </div>
      </div>
      <div class="add-field">
        <label>Email <span style="color:var(--red);">*</span></label>
        <input type="email" name="email" class="add-input" placeholder="email@example.com" required>
      </div>
      <div class="add-grid">
        <div class="add-field" style="margin-bottom:0;">
          <label>รหัสผ่าน <span style="color:var(--red);">*</span></label>
          <input type="password" name="password" class="add-input" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
        </div>
        <div class="add-field" style="margin-bottom:0;">
          <label>เบอร์โทรศัพท์</label>
          <input type="text" name="phone" class="add-input" placeholder="0xx-xxx-xxxx">
        </div>
      </div>
      <div class="add-field" style="margin-top:12px;">
        <label>Role <span style="color:var(--red);">*</span></label>
        <div class="role-pills">
          <div class="role-pill"><input type="radio" name="role" id="r-fl" value="freelancer" checked><label for="r-fl">💼 Freelancer</label></div>
          <div class="role-pill"><input type="radio" name="role" id="r-em" value="employer"><label for="r-em">🏢 Employer</label></div>
          <div class="role-pill"><input type="radio" name="role" id="r-ad" value="admin"><label for="r-ad">🛡️ Admin</label></div>
        </div>
      </div>
      <button type="submit" name="add_user" class="btn-submit">
        <i class="bi bi-plus-lg"></i> เพิ่มผู้ใช้
      </button>
    </form>
  </div>
</div>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=9" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div><div class="logo-text">Job_Find</div><div class="logo-sub">Admin Panel</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php"       class="nav-item active"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="admin_manage_categories.php"  class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php"            class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- Main -->
<main class="main">

  <div class="topbar">
    <div>
      <h2>Manage Users</h2>
      <p>จัดการบัญชีผู้ใช้งานทั้งหมดในระบบ</p>
    </div>
    <button class="btn-add" onclick="document.getElementById('add-modal').classList.add('show')">
      <i class="bi bi-person-plus"></i> เพิ่มผู้ใช้ใหม่
    </button>
  </div>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat-mini"><div class="sm-icon si-purple"><i class="bi bi-people"></i></div><div><div class="sm-val"><?php echo $count_all; ?></div><div class="sm-lbl">ทั้งหมด</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-blue"><i class="bi bi-person-badge"></i></div><div><div class="sm-val"><?php echo $count_fl; ?></div><div class="sm-lbl">Freelancer</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-green"><i class="bi bi-building"></i></div><div><div class="sm-val"><?php echo $count_em; ?></div><div class="sm-lbl">Employer</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-gray"><i class="bi bi-shield-check"></i></div><div><div class="sm-val"><?php echo $count_ad; ?></div><div class="sm-lbl">Admin</div></div></div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="search-input" placeholder="ค้นหา username, email, ชื่อ..." oninput="filterTable()">
    </div>
    <select class="filter-sel" id="role-filter" onchange="filterTable()">
      <option value="">ทุก Role</option>
      <option value="freelancer">Freelancer</option>
      <option value="employer">Employer</option>
      <option value="admin">Admin</option>
    </select>
    <span class="result-info">พบ <strong id="result-count"><?php echo $count_all; ?></strong> รายการ</span>
  </div>

  <!-- Table -->
  <div class="table-card">
    <table class="data-table" id="user-table">
      <thead>
        <tr>
          <th>#</th>
          <th>ผู้ใช้งาน</th>
          <th>โปรไฟล์</th>
          <th>เบอร์โทร</th>
          <th>Role</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($rows)): ?>
        <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i>ยังไม่มีผู้ใช้</td></tr>
      <?php else: ?>
        <?php foreach($rows as $row):
          $is_me    = ($row['user_id'] == $_SESSION['user_id']);
          $init     = profile_initials($row['fullname'] ?: ($row['username'] ?? '?'));
          $av_class = 'ua-'.($row['role'] ?? 'admin');
          $rb_class = 'rb-'.($row['role'] ?? 'admin');
          $profile_preview = admin_profile_preview($row);
          $profile_image = trim($row['profile_image'] ?? '');
          $search_str = strtolower(
              ($row['username']??'').' '.
              ($row['fullname']??'').' '.
              ($row['email']??'').' '.
              ($row['phone']??'').' '.
              ($profile_preview['title']??'').' '.
              ($profile_preview['meta']??'').' '.
              ($profile_preview['location']??'')
          );
        ?>
        <tr data-search="<?php echo htmlspecialchars($search_str); ?>"
            data-role="<?php echo $row['role']; ?>">
          <td style="color:var(--muted);font-size:13px;"><?php echo $row['user_id']; ?></td>
          <td>
            <div class="user-cell">
              <div class="u-av <?php echo $av_class; ?>">
                <?php if($profile_image !== ''): ?>
                  <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image">
                <?php else: ?>
                  <?php echo $init; ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="u-name">
                  <?php echo htmlspecialchars($row['fullname'] ?: $row['username']); ?>
                  <?php if($is_me): ?><span class="you-tag">คุณ</span><?php endif; ?>
                </div>
                <div class="u-email"><?php echo htmlspecialchars($row['email']); ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="profile-cell">
              <?php if($profile_preview['exists']): ?>
                <div class="profile-main">
                  <i class="bi <?php echo $row['role'] === 'employer' ? 'bi-building' : ($row['role'] === 'admin' ? 'bi-shield-check' : 'bi-person-lines-fill'); ?>"></i>
                  <span><?php echo htmlspecialchars($profile_preview['title'] !== '' ? $profile_preview['title'] : 'ยังไม่ได้ระบุข้อมูลหลัก'); ?></span>
                </div>
                <?php if($profile_preview['meta'] !== ''): ?>
                  <div class="profile-sub"><?php echo htmlspecialchars($profile_preview['meta']); ?></div>
                <?php endif; ?>
                <?php if($profile_preview['location'] !== ''): ?>
                  <div class="profile-loc"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($profile_preview['location']); ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="profile-empty">ยังไม่มีโปรไฟล์</span>
              <?php endif; ?>
            </div>
          </td>
          <td style="color:var(--muted);"><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
          <td>
            <span class="role-badge <?php echo $rb_class; ?>">
              <?php if($row['role']==='admin'): ?><i class="bi bi-shield-check"></i>
              <?php elseif($row['role']==='employer'): ?><i class="bi bi-building"></i>
              <?php else: ?><i class="bi bi-person-badge"></i><?php endif; ?>
              <?php echo ucfirst($row['role']); ?>
            </span>
          </td>
          <td>
            <div class="act-wrap">
              <a href="admin_edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn-edit">
                <i class="bi bi-pencil"></i> แก้ไข
              </a>
              <?php if(!$is_me): ?>
              <button class="btn-del" onclick="openDelModal(<?php echo $row['user_id']; ?>,'<?php echo htmlspecialchars($row['fullname']?:$row['username'],ENT_QUOTES); ?>')">
                <i class="bi bi-trash3"></i> ลบ
              </button>
              <?php else: ?>
              <span class="self-lock"><i class="bi bi-lock"></i> บัญชีของคุณ</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<script>
  // filter table
  function filterTable(){
    const kw   = document.getElementById('search-input').value.toLowerCase().trim();
    const role = document.getElementById('role-filter').value;
    const rows = document.querySelectorAll('#user-table tbody tr:not(.empty-row)');
    let n = 0;
    rows.forEach(r => {
      const show = (!kw || (r.dataset.search||'').includes(kw)) && (!role || r.dataset.role===role);
      r.classList.toggle('hidden',!show);
      if(show) n++;
    });
    document.getElementById('result-count').textContent = n;
  }

  // delete modal
  function openDelModal(id, name){
    document.getElementById('del-name').textContent = name;
    document.getElementById('del-link').href = 'admin_manage_users.php?delete='+id;
    document.getElementById('del-modal').classList.add('show');
  }
  function closeDelModal(){ document.getElementById('del-modal').classList.remove('show'); }
  document.getElementById('del-modal').addEventListener('click',function(e){ if(e.target===this) closeDelModal(); });

  // add modal
  function closeAddModal(){ document.getElementById('add-modal').classList.remove('show'); }
  document.getElementById('add-modal').addEventListener('click',function(e){ if(e.target===this) closeAddModal(); });
</script>
</body>
</html>
