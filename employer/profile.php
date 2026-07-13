<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/location_schema.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";
require_once __DIR__ . "/../helpers/employer_sidebar_helpers.php";

ensure_location_schema($conn);
ensure_profile_image_schema($conn);

$current_user_id = jobfind_require_login();
$current_role    = $_SESSION['role'];
$sidebar_pending_apps = $current_role === 'employer' ? get_employer_pending_application_count($conn, $current_user_id) : 0;
$current_user_image = mysqli_fetch_assoc(mysqli_query($conn,"SELECT profile_image FROM Users WHERE user_id='$current_user_id'"));
$current_profile_image = trim($current_user_image['profile_image'] ?? '');

function safe_return_url($url, $fallback = ''){
    $url = trim((string)$url);
    if($url === '' || preg_match('/[\r\n]/', $url)){
        return $fallback;
    }

    $parts = parse_url($url);
    if($parts === false || isset($parts['scheme']) || isset($parts['host']) || strpos($url, '//') === 0){
        return $fallback;
    }

    if(!preg_match('/^[A-Za-z0-9_\/.-]+\.php(\?[A-Za-z0-9_%=&.\-\/]*)?$/', $url)){
        return $fallback;
    }

    return $url;
}

$return_url = safe_return_url($_GET['return_url'] ?? '');
$return_query = $return_url !== '' ? '&return_url=' . urlencode($return_url) : '';

// ตรวจสอบโหมด: ดูสาธารณะ (Freelancer) หรือ แก้ไขตัวเอง (Employer)
$view_emp_id = intval($_GET['emp'] ?? $_GET['employer_id'] ?? 0);
$is_public   = ($view_emp_id > 0 && ($current_role != 'employer' || $view_emp_id != $current_user_id));

$toast = $_GET['toast'] ?? '';
$success = $toast === 'profile_saved';
$image_deleted = $toast === 'profile_image_deleted';
$image_delete_err = $toast === 'profile_image_delete_failed';
$dup_err = false;
$image_err = '';

if(!$is_public && $current_role !== 'employer'){
    header("Location: ../freelancer/profile.php");
    exit();
}

if(!$is_public && isset($_POST['delete_profile_image'])){
    if($current_profile_image !== '' && mysqli_query($conn,"UPDATE Users SET profile_image=NULL WHERE user_id='$current_user_id'")){
        delete_profile_image_file($current_profile_image);
        header("Location: profile.php?toast=profile_image_deleted");
        exit();
    }

    header("Location: profile.php?toast=profile_image_delete_failed");
    exit();
}

// ── UPDATE PROFILE (เฉพาะ Employer แก้ตัวเอง) ──
if(!$is_public && isset($_POST['update'])){
    $new_username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $fullname     = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email        = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone        = mysqli_real_escape_string($conn, jobfind_digits_only($_POST['phone'] ?? ''));
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $address      = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $province     = mysqli_real_escape_string($conn, $_POST['province'] ?? '');
    $district     = mysqli_real_escape_string($conn, $_POST['district'] ?? '');
    $postal_code  = mysqli_real_escape_string($conn, $_POST['postal_code'] ?? '');
    $latitude     = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude    = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $latitude_sql = $latitude !== null ? sprintf('%.8F', $latitude) : "NULL";
    $longitude_sql = $longitude !== null ? sprintf('%.8F', $longitude) : "NULL";

    $dup = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id FROM Users
        WHERE (username='$new_username' OR email='$email')
        AND user_id != '$current_user_id'
    "));

    if($dup){
        $dup_err = true;
    } else {
        $new_profile_image_path = '';
        if(profile_image_file_selected($_FILES['profile_image'] ?? [])){
            $new_profile_image_path = save_uploaded_profile_image($_FILES['profile_image'], $current_user_id, $image_err);
        }

        if($image_err !== ''){
            if($new_profile_image_path !== ''){
                delete_profile_image_file($new_profile_image_path);
            }
        } else {
            $profile_image_set = '';
            if($new_profile_image_path !== ''){
                $new_profile_image_sql = mysqli_real_escape_string($conn, $new_profile_image_path);
                $profile_image_set = ", profile_image='$new_profile_image_sql'";
            }

        mysqli_query($conn,"
            UPDATE Users SET username='$new_username', fullname='$fullname', email='$email', phone='$phone',
                latitude=$latitude_sql, longitude=$longitude_sql
                $profile_image_set
            WHERE user_id='$current_user_id'
        ");
        $_SESSION['username'] = $new_username;

        $check = mysqli_query($conn,"SELECT * FROM Employer_Profile WHERE user_id='$current_user_id'");
        if(mysqli_num_rows($check) > 0){
            mysqli_query($conn,"
                UPDATE Employer_Profile SET
                employer_name='$fullname', employer_description='$description',
                address='$address', province='$province', district='$district', postal_code='$postal_code',
                latitude=$latitude_sql, longitude=$longitude_sql
                WHERE user_id='$current_user_id'
            ");
        } else {
            mysqli_query($conn,"
                INSERT INTO Employer_Profile
                    (user_id, employer_name, employer_description, address, province, district, postal_code, latitude, longitude)
                VALUES
                    ('$current_user_id','$fullname','$description','$address','$province','$district','$postal_code',$latitude_sql,$longitude_sql)
            ");
        }
        if($new_profile_image_path !== ''){
            delete_profile_image_file($current_profile_image);
        }
        header("Location: profile.php?toast=profile_saved");
        exit();
        }
    }
}

// ── FETCH DATA ──
$target_id = $is_public ? $view_emp_id : $current_user_id;
$user    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM Users WHERE user_id='$target_id'"));
$profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM Employer_Profile WHERE user_id='$target_id'"));
$has_profile = (bool)$profile;
$profile_image = trim($user['profile_image'] ?? '');

if(!$user){ header("Location: ../freelancer/browse_jobs.php"); exit(); }
if($profile && empty($profile['latitude']) && !empty($user['latitude'])){
    $profile['latitude'] = $user['latitude'];
}
if($profile && empty($profile['longitude']) && !empty($user['longitude'])){
    $profile['longitude'] = $user['longitude'];
}

$initials = profile_initials($user['fullname'] ?: $user['username']);

// ดึงรีวิว (เฉพาะโหมดสาธารณะ)
$reviews = [];
$avg_rating = 0;
$review_count = 0;
if($is_public){
    $rev_res = mysqli_query($conn,"
        SELECT er.*, u.username as reviewer_name, j.title as job_title
        FROM Employer_Review er
        JOIN Users u ON er.freelancer_id = u.user_id
        LEFT JOIN Job j ON er.job_id = j.job_id
        WHERE er.employer_id = '$target_id'
        ORDER BY er.review_id DESC
    ");
    while($r = mysqli_fetch_assoc($rev_res)){
        $reviews[] = $r;
        $avg_rating += $r['rating'];
        $review_count++;
    }
    $avg_rating = $review_count > 0 ? round($avg_rating / $review_count, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $is_public ? 'โปรไฟล์บริษัท' : 'แก้ไขโปรไฟล์'; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.min.css" />
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root { --navy:#0f172a; --navy2:#1e293b; --navy3:#334155; --accent:#6366f1; --light:#f1f5f9; --white:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --green:#10b981; --yellow:#f59e0b; --radius:14px; }
  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }

  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub  { font-size:11px; color:var(--navy3); }
  .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; }
  .nav-item:hover { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }

  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; justify-content:center; }
  .content-wrap { width:100%; max-width:760px; }
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; margin-bottom:16px; transition:background .15s; }
  .btn-back:hover { background:var(--light); }

  .profile-banner { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; display:grid; grid-template-columns:56px 1fr; gap:16px; align-items:center; margin-bottom:18px; }
  .banner-avatar { width:56px; height:56px; border-radius:16px; background:var(--accent); color:#fff; font-size:18px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
  .banner-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .banner-main { min-width:0; }
  .banner-main h3 { font-size:18px; font-weight:700; margin-bottom:4px; }
  .banner-main p { font-size:13px; color:var(--muted); margin-bottom:8px; }
  .banner-tags { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
  .banner-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
  .company-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--muted); background:var(--light); border-radius:999px; padding:6px 10px; }

  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
  .profile-image-editor {
    display:grid;
    grid-template-columns:78px 1fr;
    gap:14px;
    align-items:center;
    padding:14px;
    border:1px solid var(--border);
    border-radius:12px;
    background:#f8fafc;
    margin-bottom:18px;
  }
  .profile-image-preview {
    width:78px; height:78px; border-radius:16px;
    overflow:hidden; display:flex; align-items:center; justify-content:center;
    background:var(--accent); color:#fff; font-size:22px; font-weight:700;
  }
  .profile-image-preview img { width:100%; height:100%; object-fit:cover; display:block; }
  .profile-image-copy strong { display:block; font-size:14px; margin-bottom:4px; color:var(--text); }
  .profile-image-copy p { margin:0 0 10px; font-size:12.5px; color:var(--muted); line-height:1.6; }
  .profile-file-input { width:100%; max-width:480px; font-size:13px; }
  .image-delete-form { display:flex; justify-content:flex-end; margin:-4px 0 18px; }
  .btn-delete-image {
    display:inline-flex; align-items:center; gap:7px;
    background:#fff1f2; color:#be123c; border:1px solid #fecdd3;
    border-radius:10px; padding:9px 14px; font-size:12.5px; font-weight:700;
    cursor:pointer; transition:background .15s, transform .1s;
  }
  .btn-delete-image:hover { background:#ffe4e6; transform:translateY(-1px); }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
  .info-box { background:var(--light); border-radius:12px; padding:14px; }
  .info-box .lbl { font-size:11px; color:var(--muted); margin-bottom:6px; display:block; }
  .info-box .val { font-size:14px; font-weight:600; color:var(--text); }
  .desc-box { background:var(--light); border-radius:var(--radius); padding:14px; font-size:14px; line-height:1.7; }
  .review-card { background:var(--light); border-radius:var(--radius); padding:14px; margin-bottom:12px; border-left:3px solid var(--accent); }
  .rev-header { display:flex; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:10px; }
  .rev-name { font-size:13px; font-weight:600; }
  .rev-job { font-size:11px; color:var(--muted); }
  .rev-stars { font-size:12px; color:var(--yellow); margin-bottom:8px; }
  .rev-comment { font-size:13px; line-height:1.6; }
  .empty-rev { text-align:center; padding:28px 20px; color:var(--muted); font-size:13px; }

  /* ── Map Modal ── */
  .map-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
  .map-modal.active { display:flex; }
  .map-container { background:white; border-radius:16px; width:90%; max-width:900px; height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .map-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
  .map-header h3 { margin:0; font-size:18px; font-weight:600; }
  .map-close { background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); }
  .map-close:hover { color:var(--text); }
  #employer-map { flex:1; }
  .map-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }
  .map-footer button { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:14px; }
  .btn-map-confirm { background:var(--accent); color:white; }
  .btn-map-confirm:hover { background:#4f46e5; }
  .btn-map-cancel { background:var(--light); color:var(--text); }
  .btn-map-cancel:hover { background:#e2e8f0; }
  .map-info { padding:12px 16px; background:#eef2ff; border-radius:8px; font-size:13px; margin-bottom:12px; }
  .btn-open-map { display:inline-flex; align-items:center; gap:6px; background:#eef2ff; color:var(--accent); border:1px solid #c7d2fe; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer; transition:all .15s; }
  .btn-open-map:hover { background:#c7d2fe; color:var(--accent); }
  .coord-display { font-size:12px; color:var(--muted); margin-top:6px; }

  @media(max-width:968px){ .profile-banner { grid-template-columns:1fr; text-align:center; } .banner-actions { justify-content:center; } .info-grid { grid-template-columns:1fr; } }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .info-grid { grid-template-columns:1fr; } .map-container { width:100%; height:100%; max-width:none; border-radius:0; } }
  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }
  .field-group { margin-bottom:18px; }
  .field-group label { display:block; font-size:13px; font-weight:500; color:var(--text); margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }
  .form-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--white); }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  textarea.form-input { resize:vertical; min-height:100px; line-height:1.7; }
  .input-icon-wrap { position:relative; }
  .input-icon-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--muted); pointer-events:none; }
  .input-icon-wrap .form-input { padding-left:40px; }
  .input-icon-wrap.textarea-wrap i { top:14px; transform:none; }
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }
  .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:12px 28px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s,transform .1s; }
  .btn-save:hover { background:#4f46e5; transform:translateY(-1px); }
  .profile-actions { display:flex; justify-content:space-between; align-items:center; gap:14px; padding-top:8px; flex-wrap:wrap; }
  .profile-status { display:flex; align-items:center; gap:8px; color:var(--muted); font-size:12.5px; line-height:1.5; }
  .profile-status i { color:var(--accent); }

  /* Public View Styles */
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
  .info-box { background:var(--light); border-radius:10px; padding:12px; }
  .info-box .lbl { font-size:11px; color:var(--muted); margin-bottom:3px; }
  .info-box .val { font-size:13px; font-weight:500; }
  .desc-box { background:var(--light); border-radius:10px; padding:14px; font-size:13.5px; line-height:1.7; margin-bottom:20px; }
  .review-card { background:var(--light); border-radius:10px; padding:14px; margin-bottom:10px; border-left:3px solid var(--accent); }
  .rev-header { display:flex; justify-content:space-between; margin-bottom:6px; }
  .rev-name { font-size:13px; font-weight:600; }
  .rev-job { font-size:11px; color:var(--muted); }
  .rev-stars { font-size:12px; color:var(--yellow); margin-bottom:6px; }
  .rev-comment { font-size:13px; line-height:1.6; }
  .empty-rev { text-align:center; padding:30px; color:var(--muted); font-size:13px; }

  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .info-grid { grid-template-columns:1fr; } }
  @media(max-width:640px){ .profile-image-editor { grid-template-columns:1fr; justify-items:start; } .profile-actions { align-items:stretch; flex-direction:column; } .btn-save { justify-content:center; width:100%; } }

  /* ปุ่มรีวิวบริษัท */
  .btn-company-review {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--light);
    color: var(--accent);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    margin-top: 12px;
    transition: all 0.15s;
  }
  .btn-company-review:hover {
    border-color: var(--accent);
    background: #eef2ff;
  }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<?php if($success): ?>
<div class="toast-bar" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> อัปเดตโปรไฟล์สำเร็จแล้ว</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php elseif($image_deleted): ?>
<div class="toast-bar" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> ลบรูปโปรไฟล์แล้ว</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php elseif($dup_err): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;"><i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;"></i> Username หรือ Email นี้ถูกใช้งานแล้ว</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },4000);</script>
<?php elseif($image_delete_err || $image_err !== ''): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;"><i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;"></i> <?php echo htmlspecialchars($image_err ?: 'ลบรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่'); ?></div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },4000);</script>
<?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div><div class="logo-text" style="display:none!important;">Freelance Matching Online</div><div class="logo-sub" style="display:none!important;"><?php echo $current_role == 'employer' ? 'Employer' : 'Freelancer'; ?></div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <?php if($current_role == 'employer'): ?>
      <a href="dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
      <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
      <a href="manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
      <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
      <a href="reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="company_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
      <a href="profile.php"     class="nav-item active"><i class="bi bi-person-circle"></i> My Profile</a>
    <?php else: ?>
      <a href="../freelancer/dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
      <a href="../freelancer/browse_jobs.php"          class="nav-item active"><i class="bi bi-briefcase"></i> Browse Jobs</a>
      <a href="../freelancer/my_applications.php"      class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
      <a href="../freelancer/profile.php"           class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="../freelancer/reviews.php"   class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="../freelancer/upload_resume.php"        class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <?php endif; ?>
    <div class="nav-divider"></div>
    <a href="../support/messages.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
<div class="content-wrap">

  <?php if($is_public): ?>
    <a href="<?php echo htmlspecialchars($return_url ?: 'javascript:history.back()', ENT_QUOTES, 'UTF-8'); ?>" class="btn-back"><i class="bi bi-arrow-left"></i> กลับ</a>
  <?php endif; ?>

  <div class="topbar">
    <h2><?php echo $is_public ? 'โปรไฟล์บริษัท' : 'แก้ไขโปรไฟล์'; ?></h2>
    <p><?php echo $is_public ? 'ข้อมูลบริษัทและประวัติรีวิว' : 'จัดการข้อมูลบริษัทและช่องทางติดต่อ'; ?></p>
  </div>

  <div class="profile-banner">
    <div class="banner-avatar">
      <?php if($profile_image !== ''): ?>
        <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image">
      <?php else: ?>
        <?php echo $initials ?: '?'; ?>
      <?php endif; ?>
    </div>
    <div class="banner-main">
      <h3><?php echo htmlspecialchars($profile['employer_name'] ?? $user['fullname'] ?? '(ยังไม่ระบุ)'); ?></h3>
      <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
      <div class="banner-tags">
        <?php if(!empty($user['phone'])): ?>
        <span class="company-status"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
        <?php endif; ?>
        <?php if($is_public): ?>
        <span class="company-status"><i class="bi bi-star-fill"></i> <?php echo $avg_rating > 0 ? $avg_rating : 'ยังไม่มี'; ?> (<?php echo $review_count; ?> รีวิว)</span>
        <?php endif; ?>
        <span class="company-status"><i class="bi bi-building"></i> นายจ้าง</span>
      </div>
      <div class="banner-actions">
        <?php if($is_public): ?>
        <a href="../freelancer/review_employer.php?employer_id=<?php echo $target_id; ?><?php echo htmlspecialchars($return_query, ENT_QUOTES, 'UTF-8'); ?>" class="btn-company-review">
          <i class="bi bi-building"></i> รีวิวผู้ว่าจ้าง
        </a>
        <?php endif; ?>
        <?php if(!$is_public): ?>
        <a href="manage_jobs.php" class="btn-company-review" style="color:#fff; background:var(--accent); border:none;">
          <i class="bi bi-briefcase"></i> ดูงานของฉัน
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if(!$is_public && $profile_image !== ''): ?>
  <form method="POST" class="image-delete-form"
        onsubmit="return confirm('ยืนยันลบรูปโปรไฟล์?');">
    <button type="submit" name="delete_profile_image" class="btn-delete-image">
      <i class="bi bi-trash"></i> ลบรูปโปรไฟล์
    </button>
  </form>
  <?php endif; ?>

  <?php if(!$is_public): ?>
  <!-- ✅ MODE แก้ไข (Employer) -->
  <form method="POST" enctype="multipart/form-data">
  <div class="form-card">
    <div class="section-title"><i class="bi bi-image"></i> รูปโปรไฟล์บริษัท</div>
    <div class="profile-image-editor">
      <div class="profile-image-preview">
        <?php if($profile_image !== ''): ?>
          <img src="<?php echo profile_image_src($profile_image); ?>" alt="Company profile image preview">
        <?php else: ?>
          <?php echo $initials ?: '?'; ?>
        <?php endif; ?>
      </div>
      <div class="profile-image-copy">
        <strong>อัปโหลดหรือเปลี่ยนรูปโปรไฟล์</strong>
        <p>รองรับ JPG, PNG, WEBP ขนาดไม่เกิน <?php echo profile_image_max_size_label(); ?> รูปใหม่จะแทนที่รูปเดิมเมื่อกดบันทึก</p>
        <input class="form-control profile-file-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>

    <div class="section-title"><i class="bi bi-at"></i> ข้อมูลบัญชี</div>
    <div class="field-group">
      <label>Username</label>
      <div class="input-icon-wrap">
        <i class="bi bi-at"></i>
        <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required maxlength="50">
      </div>
    </div>

    <div class="section-title"><i class="bi bi-building"></i> ข้อมูลบริษัท</div>
    <div class="field-group">
      <label>ชื่อบริษัท / Company Name</label>
      <div class="input-icon-wrap">
        <i class="bi bi-building"></i>
        <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
      </div>
    </div>
    <div class="field-group">
      <label>รายละเอียดบริษัท <span>(แนะนำตัวให้ Freelancer รู้จักบริษัทคุณ)</span></label>
      <div class="input-icon-wrap textarea-wrap">
        <i class="bi bi-card-text"></i>
        <textarea name="description" id="desc-input" class="form-input" style="padding-left:40px;" placeholder="เล่าเกี่ยวกับบริษัท..." maxlength="500" oninput="updateCount()"><?php echo htmlspecialchars($profile['employer_description'] ?? ''); ?></textarea>
      </div>
      <div class="char-count"><span id="desc-count">0</span> / 500</div>
    </div>

    <div class="section-title" style="margin-top:8px;"><i class="bi bi-person"></i> ข้อมูลติดต่อ</div>
    <div class="field-group">
      <label>Email</label>
      <div class="input-icon-wrap">
        <i class="bi bi-envelope"></i>
        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
      </div>
    </div>
    <div class="field-group">
      <label>เบอร์โทรศัพท์ <span>(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-telephone"></i>
        <input type="text" name="phone" class="form-input"
               inputmode="numeric" pattern="[0-9]*" maxlength="20"
               oninput="this.value=this.value.replace(/\D/g,'')"
               value="<?php echo htmlspecialchars($user['phone']); ?>">
      </div>
    </div>

    <div class="section-title" style="margin-top:16px;"><i class="bi bi-map"></i> ที่อยู่บริษัท</div>
    <div class="field-group">
      <label>ที่อยู่</label>
      <div class="input-icon-wrap textarea-wrap">
        <i class="bi bi-geo-alt"></i>
        <textarea name="address" class="form-input" style="padding-left:40px;" placeholder="เช่น 123 ซ.สุขุมวิท ถ.สุขุมวิท..."><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
      </div>
    </div>

    <div class="field-group">
      <label>จังหวัด</label>
      <div class="input-icon-wrap">
        <i class="bi bi-building"></i>
        <select name="province" id="employer-province" class="form-input">
          <option value="">เลือกจังหวัด</option>
        </select>
      </div>
    </div>

    <div class="field-group">
      <label>อำเภอ</label>
      <div class="input-icon-wrap">
        <i class="bi bi-pin-map"></i>
        <select name="district" id="employer-district" class="form-input" disabled>
          <option value="">เลือกจังหวัดก่อน</option>
        </select>
      </div>
    </div>

    <div class="field-group">
      <label>ตำแหน่งบริษัทบนแผนที่ / Company pin</label>
      <button type="button" class="btn-open-map" onclick="openEmployerMapModal()">
        <i class="bi bi-geo"></i> เลือกตำแหน่งจากแผนที่
      </button>
      <div class="coord-display" id="employer-coord-display">
        <?php
          if (!empty($profile['latitude']) && !empty($profile['longitude'])) {
            echo "ปักหมุดบริษัทแล้ว";
          } else {
            echo "ยังไม่ได้ปักหมุดบริษัท";
          }
        ?>
      </div>
      <input type="hidden" name="latitude" id="employer-latitude" value="<?php echo htmlspecialchars($profile['latitude'] ?? ''); ?>">
      <input type="hidden" name="longitude" id="employer-longitude" value="<?php echo htmlspecialchars($profile['longitude'] ?? ''); ?>">
    </div>

    <div class="field-group">
      <label>รหัสไปรษณีย์</label>
      <div class="input-icon-wrap">
        <i class="bi bi-mailbox"></i>
        <input type="text" name="postal_code" id="employer-postal-code" class="form-input" value="<?php echo htmlspecialchars($profile['postal_code'] ?? ''); ?>" placeholder="10110">
      </div>
    </div>

    <div class="profile-actions">
      <div class="profile-status">
        <?php if($has_profile): ?>
          <i class="bi bi-check-circle-fill"></i> โปรไฟล์บริษัทพร้อมใช้งานและสามารถแก้ไขได้
        <?php else: ?>
          <i class="bi bi-info-circle-fill"></i> ยังไม่ได้ตั้งโปรไฟล์บริษัท กดบันทึกเพื่อสร้างโปรไฟล์ใหม่
        <?php endif; ?>
      </div>
      <button type="submit" name="update" class="btn-save">
        <i class="bi bi-check-lg"></i> <?php echo $has_profile ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างโปรไฟล์บริษัท'; ?>
      </button>
    </div>
  </div>
  </form>

  <div class="map-modal" id="employerMapModal">
    <div class="map-container">
      <div class="map-header">
        <h3><i class="bi bi-geo"></i> ปักหมุดบริษัท</h3>
        <button class="map-close" type="button" onclick="closeEmployerMapModal()">&times;</button>
      </div>
      <div style="padding:16px;">
        <div class="map-info">
          คลิกบนแผนที่เพื่อเลือกที่อยู่บริษัทหรือจุดที่ต้องการให้ระบบใช้แมชงานกับ freelancer
        </div>
      </div>
      <div id="employer-map"></div>
      <div class="map-footer">
        <button type="button" class="btn-map-cancel" onclick="closeEmployerMapModal()">ยกเลิก</button>
        <button type="button" class="btn-map-confirm" onclick="confirmEmployerMapLocation()">ยืนยัน</button>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ✅ MODE สาธารณะ (Freelancer อ่าน) -->
  <div class="form-card">
      <div class="section-title"><i class="bi bi-info-circle"></i> ข้อมูลติดต่อ</div>
      <div class="info-grid">
        <div class="info-box"><div class="lbl">Email</div><div class="val"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></div></div>
        <div class="info-box"><div class="lbl">เบอร์โทรศัพท์</div><div class="val"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></div></div>
      </div>
      <div class="section-title"><i class="bi bi-building"></i> รายละเอียดบริษัท</div>
      <div class="desc-box">
        <?php echo nl2br(htmlspecialchars($profile['employer_description'] ?? 'ยังไม่ได้ระบุรายละเอียดเกี่ยวกับบริษัท')); ?>
      </div>
      <div class="section-title"><i class="bi bi-star"></i> รีวิวจาก Freelancer</div>
      <?php if(empty($reviews)): ?>
        <div class="empty-rev"><i class="bi bi-star" style="font-size:28px;display:block;margin-bottom:8px;"></i>ยังไม่มีรีวิวสำหรับบริษัทนี้</div>
      <?php else: ?>
        <?php foreach($reviews as $r): ?>
        <div class="review-card">
          <div class="rev-header">
            <span class="rev-name"><?php echo htmlspecialchars($r['reviewer_name']); ?></span>
            <span class="rev-job"><?php echo htmlspecialchars($r['job_title'] ?? 'งานนี้'); ?></span>
          </div>
          <div class="rev-stars">⭐ <?php echo $r['rating']; ?>/5</div>
          <div class="rev-comment"><?php echo nl2br(htmlspecialchars($r['comment'] ?? 'ไม่มีความคิดเห็นเพิ่มเติม')); ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</main>

<script src="../assets/vendor/leaflet/leaflet.min.js"></script>
<script src="../assets/js/location-map-picker.js?v=longdo-search-20260713" data-longdo-key="<?php echo jobfind_longdo_api_key_attr(); ?>"></script>
<script src="../assets/js/thai-location-selects.js"></script>
<script>
initThaiProvinceDistrictSelects({
  provinceId: 'employer-province',
  districtId: 'employer-district',
  postalCodeId: 'employer-postal-code',
  currentProvince: <?php echo json_encode($profile['province'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
  currentDistrict: <?php echo json_encode($profile['district'] ?? '', JSON_UNESCAPED_UNICODE); ?>
});

function updateCount(){
  const ta = document.getElementById('desc-input');
  if(ta) document.getElementById('desc-count').textContent = ta.value.length;
}
updateCount();

let employerMap = null;
let employerLat = <?php echo !empty($profile['latitude']) ? $profile['latitude'] : '13.7563'; ?>;
let employerLng = <?php echo !empty($profile['longitude']) ? $profile['longitude'] : '100.5018'; ?>;
let employerHasPin = <?php echo (!empty($profile['latitude']) && !empty($profile['longitude'])) ? 'true' : 'false'; ?>;

function setEmployerPosition(lat, lng) {
  employerLat = Number(lat);
  employerLng = Number(lng);
  employerHasPin = true;
}

function openEmployerMapModal() {
  const modal = document.getElementById('employerMapModal');
  if (!modal) return;
  modal.classList.add('active');

  setTimeout(() => {
    if (!employerMap) {
      employerMap = createJobFindMapPicker({
        elementId: 'employer-map',
        lat: employerLat,
        lng: employerLng,
        hasPin: employerHasPin,
        radiusKm: 30,
        showCircle: false,
        onChange: setEmployerPosition
      });
    }

    if (employerMap) {
      employerMap.resize();
    }
    if (employerHasPin) {
      employerMap.setView(employerLat, employerLng);
    }
  }, 100);
}

function closeEmployerMapModal() {
  const modal = document.getElementById('employerMapModal');
  if (modal) modal.classList.remove('active');
}

function confirmEmployerMapLocation() {
  if (!employerHasPin) {
    alert('กรุณาเลือกตำแหน่งบริษัทบนแผนที่');
    return;
  }

  document.getElementById('employer-latitude').value = employerLat.toFixed(6);
  document.getElementById('employer-longitude').value = employerLng.toFixed(6);
  document.getElementById('employer-coord-display').textContent = 'ปักหมุดบริษัทแล้ว';
  closeEmployerMapModal();
}

const employerMapModal = document.getElementById('employerMapModal');
if (employerMapModal) {
  employerMapModal.addEventListener('click', function(e) {
    if (e.target === this) {
      closeEmployerMapModal();
    }
  });
}
</script>
</body>
</html>
