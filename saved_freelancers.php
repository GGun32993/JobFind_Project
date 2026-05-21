<?php
session_start();
include '../includes/db_connection.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'employer') {
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

// ดึงรายการ freelancer ที่บันทึกไว้
$query = "
    SELECT sf.*, u.username, u.email, u.location, uf.skills, uf.experience, uf.rating, uf.review_count
    FROM saved_freelancers sf
    JOIN users u ON sf.freelancer_id = u.id
    LEFT JOIN freelancer_profiles uf ON u.id = uf.user_id
    WHERE sf.employer_id = ?
    ORDER BY sf.saved_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$saved_freelancers = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Freelancers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ใช้ CSS เดียวกับ view_applicants.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            padding: 20px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .sidebar .logo-text {
            color: white;
            font-size: 20px;
            font-weight: 600;
        }

        .sidebar .logo-text span {
            display: block;
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .nav-link i {
            font-size: 18px;
            width: 24px;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 14px;
        }

        .freelancers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .freelancer-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .freelancer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .freelancer-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .freelancer-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .freelancer-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .freelancer-name h4 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .freelancer-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #64748b;
        }

        .rating {
            color: #fbbf24;
        }

        .unsave-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .unsave-btn:hover {
            background: #ef4444;
            color: white;
        }

        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
        }

        .skill-badge {
            background: linear-gradient(135deg, #e0e7ff 0%, #ddd6fe 100%);
            color: #667eea;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .saved-date {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #1e293b;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 20px;
        }

        .browse-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .browse-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <div class="logo-icon">⚡</div>
            <div class="logo-text">
                FreelanceHub
                <span>Employer</span>
            </div>
        </div>
        
        <nav>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="post_job.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Post Job</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_jobs.php" class="nav-link">
                    <i class="fas fa-briefcase"></i>
                    <span>Manage Jobs</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="saved_freelancers.php" class="nav-link active">
                    <i class="fas fa-bookmark"></i>
                    <span>Saved Freelancers</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="my_reviews.php" class="nav-link">
                    <i class="fas fa-star"></i>
                    <span>My Reviews</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="my_profile.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="support_chat.php" class="nav-link">
                    <i class="fas fa-comments"></i>
                    <span>Support Chat</span>
                </a>
            </div>
            <div class="nav-item" style="margin-top: auto; padding-top: 20px;">
                <a href="../logout.php" class="nav-link" style="color: #ef4444;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">ผู้สมัครที่บันทึกไว้</h1>
                <p class="page-subtitle">รายชื่อ Freelancer ที่คุณบันทึกไว้เพื่อดูภายหลัง</p>
            </div>
        </div>

        <?php if ($saved_freelancers->num_rows > 0): ?>
            <div class="freelancers-grid">
                <?php while ($freelancer = $saved_freelancers->fetch_assoc()): ?>
                    <?php $initials = strtoupper(substr($freelancer['username'], 0, 2)); ?>
                    <div class="freelancer-card">
                        <div class="freelancer-header">
                            <div class="freelancer-info">
                                <div class="freelancer-avatar"><?php echo $initials; ?></div>
                                <div class="freelancer-name">
                                    <h4><?php echo htmlspecialchars($freelancer['username']); ?></h4>
                                    <div class="freelancer-meta">
                                        <span>📍 <?php echo htmlspecialchars($freelancer['location'] ?? 'ไม่ระบุ'); ?></span>
                                        <?php if ($freelancer['rating']): ?>
                                            <span class="rating">⭐ <?php echo number_format($freelancer['rating'], 1); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <button class="unsave-btn" onclick="unsaveFreelancer(<?php echo $freelancer['freelancer_id']; ?>)">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
                        
                        <?php if ($freelancer['skills']): ?>
                            <div class="skills-container">
                                <?php 
                                $skills = explode(',', $freelancer['skills']);
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-badge"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin: 10px 0;">
                            <small style="color: #64748b;">
                                <i class="fas fa-briefcase"></i> <?php echo ($freelancer['experience'] ?? 0); ?> years experience
                            </small>
                        </div>
                        
                        <div class="saved-date">
                            <i class="fas fa-clock"></i> บันทึกเมื่อ <?php echo date('d/m/Y H:i', strtotime($freelancer['saved_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bookmark"></i>
                <h3>ยังไม่มีผู้สมัครที่บันทึกไว้</h3>
                <p>คุณยังไม่ได้บันทึก Freelancer คนใดๆ ไว้เลย</p>
                <a href="manage_jobs.php" class="browse-btn">ดูผู้สมัครงาน</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function unsaveFreelancer(freelancerId) {
            if (confirm('คุณต้องการยกเลิกการบันทึก Freelancer คนนี้ใช่ไหม?')) {
                fetch('save_freelancer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `freelancer_id=${freelancerId}&action=unsave`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                });
            }
        }
    </script>
</body>
</html>