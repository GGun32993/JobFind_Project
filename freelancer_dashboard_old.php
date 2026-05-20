<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
header("Location: login.php");
exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// get location
$profile = mysqli_query($conn,"
SELECT location
FROM freelancer_profile
WHERE user_id='$user_id'
");

$data = mysqli_fetch_assoc($profile);

$user_location = "";

if($data){
$user_location = $data['location'];
}

// recommend jobs
if($user_location != ""){

$recommend = mysqli_query($conn,"
SELECT *
FROM job
WHERE location LIKE '%$user_location%'
AND status='approved'
ORDER BY created_at DESC
LIMIT 5
");

}else{

$recommend = mysqli_query($conn,"
SELECT *
FROM job
WHERE status='approved'
ORDER BY created_at DESC
LIMIT 5
");

}
?>

<!DOCTYPE html>
<html>
<head>

<title>Freelancer Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f8f9fa;
}

.container{
margin-top:40px;
}

.card{
padding:20px;
margin-bottom:15px;
border-radius:10px;
box-shadow:0px 0px 5px rgba(0,0,0,0.1);
}

.menu{
margin-bottom:20px;
}

.menu a{
margin-right:10px;
}

</style>

</head>

<body>

<div class="container">

<h3>Welcome, <?php echo $username; ?></h3>

<!-- MENU -->
<div class="menu">

<a href="browse_jobs.php" class="btn btn-primary">
Browse Jobs
</a>

<a href="my_applications.php" class="btn btn-info">
My Applications
</a>

<a href="my_profile.php" class="btn btn-warning">
My Profile
</a>

<a href="freelancer_reviews.php" class="btn btn-info">
⭐ My Reviews
</a>

<a href="upload_resume.php" class="btn btn-secondary">
Upload Resume
</a>

<a href="logout.php" class="btn btn-danger">
Logout
</a>

<a href="support_chat.php" class="btn btn-dark">
Support Chat
</a>

</div>

<hr>

<h4>Recommended Jobs</h4>

<?php while($job = mysqli_fetch_assoc($recommend)){ ?>

<div class="card">

<h5><?php echo $job['title']; ?></h5>

<p>📍 <?php echo $job['location']; ?></p>

<p>💰 Salary: <?php echo $job['salary']; ?></p>

<a href="apply_job.php?job_id=<?php echo $job['job_id']; ?>"
class="btn btn-success btn-sm">

Apply

</a>

</div>

<?php } ?>

</div>

</body>
</html>