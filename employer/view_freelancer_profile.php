<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";

ensure_profile_image_schema($conn);

jobfind_require_any_role(['employer', 'admin']);

$freelancer_id = intval($_GET['id'] ?? 0);

// ดึงข้อมูล User
$user = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT fullname, email, phone, username, profile_image
    FROM Users
    WHERE user_id='$freelancer_id' AND role='freelancer'
"));

if(!$user){ 
    echo "<script>alert('ไม่พบผู้ใช้งานนี้'); window.parent.closeProfileModal();</script>";
    exit();
}

// ดึงข้อมูล Freelancer Profile
$profile = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM Freelancer_Profile
    WHERE user_id='$freelancer_id'
"));

// ดึง Rating - แก้เป็น score
$rating_data = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT AVG(score) as avg_rating, COUNT(*) as count
    FROM Freelancer_Rating
    WHERE freelancer_id='$freelancer_id'
"));

$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$rating_count = $rating_data['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Freelancer Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family:'Sora',sans-serif; background:#f8fafc; margin:0; padding:20px; }
  .profile-container { max-width:700px; margin:0 auto; background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; }
  .profile-header { background:linear-gradient(135deg, #6366f1, #8b5cf6); padding:30px; color:#fff; display:flex; align-items:center; gap:20px; }
  .avatar-circle { width:70px; height:70px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:600; overflow:hidden; flex-shrink:0; }
  .avatar-circle img { width:100%; height:100%; object-fit:cover; display:block; }
  .header-info h2 { margin:0; font-size:24px; font-weight:600; }
  .header-info .subtitle { margin:4px 0 0; opacity:0.9; font-size:14px; }
  .profile-body { padding:30px; }
  .info-section { margin-bottom:24px; }
  .section-title { font-size:13px; font-weight:600; color:#64748b; text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  .info-item { background:#f1f5f9; padding:14px; border-radius:10px; }
  .info-label { font-size:12px; color:#64748b; margin-bottom:4px; }
  .info-value { font-size:14px; font-weight:500; color:#0f172a; }
  .badge-tag { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:#eef2ff; color:#4f46e5; border-radius:20px; font-size:13px; font-weight:500; margin:4px; }
  .star-rating { color:#f59e0b; }
  @media(max-width:600px) { .info-grid { grid-template-columns:1fr; } }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<div class="profile-container">
  <div class="profile-header">
    <div class="avatar-circle">
      <?php if(!empty($user['profile_image'])): ?>
        <img src="<?php echo profile_image_src($user['profile_image']); ?>" alt="Profile image">
      <?php else: ?>
        <?php echo profile_initials($user['username']); ?>
      <?php endif; ?>
    </div>
    <div class="header-info">
      <h2><?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?></h2>
      <div class="subtitle">
        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($profile['location'] ?? 'ไม่ระบุ'); ?>
        <span style="margin-left:12px;" class="star-rating">
          <i class="bi bi-star-fill"></i> <?php echo $avg_rating; ?> (<?php echo $rating_count; ?> รีวิว)
        </span>
      </div>
    </div>
  </div>

  <div class="profile-body">
    <div class="info-section">
      <div class="section-title"><i class="bi bi-info-circle"></i> ข้อมูลติดต่อ</div>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Email</div>
          <div class="info-value"><i class="bi bi-envelope" style="color:#64748b; margin-right:6px;"></i><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Phone</div>
          <div class="info-value"><i class="bi bi-telephone" style="color:#64748b; margin-right:6px;"></i><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></div>
        </div>
      </div>
    </div>

    <div class="info-section">
      <div class="section-title"><i class="bi bi-tools"></i> ข้อมูล Freelancer</div>
      
      <div style="margin-bottom:16px;">
        <div class="info-label" style="margin-bottom:8px;">Skills</div>
        <div>
          <?php
          $skills = explode(',', $profile['skill'] ?? '');
          foreach($skills as $skill):
            if(trim($skill) !== ''):
          ?>
          <span class="badge-tag"><i class="bi bi-lightning"></i> <?php echo htmlspecialchars(trim($skill)); ?></span>
          <?php endif; endforeach; ?>
        </div>
      </div>

      <div class="info-item" style="margin-bottom:16px;">
        <div class="info-label">Experience</div>
        <div class="info-value"><?php echo htmlspecialchars($profile['experience'] ?? '-'); ?></div>
      </div>

      <div class="info-item">
        <div class="info-label">Location</div>
        <div class="info-value"><i class="bi bi-geo-alt" style="color:#64748b; margin-right:6px;"></i><?php echo htmlspecialchars($profile['location'] ?? '-'); ?></div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
