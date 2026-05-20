<?php
// เชื่อมต่อ database
include(__DIR__ . "/config.php");

// ดึงข้อมูลงาน
$query = mysqli_query($conn,"
SELECT * FROM job 
WHERE status='approved'
ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>JobFind</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f8f9fa;
font-family: Arial;
}

/* Navbar */
.navbar{
background:white;
}

/* Hero */
.hero{
padding:100px 0;
background:white;
text-align:center;
}

.hero h1{
font-size:48px;
font-weight:bold;
}

.hero p{
color:gray;
}

/* Job Card */
.job-card{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 2px 8px rgba(0,0,0,0.08);
margin-bottom:20px;
transition:0.3s;
}

.job-card:hover{
transform:translateY(-5px);
}

.apply-btn{
width:100%;
}

</style>

</head>
<body>


<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg shadow-sm">

<div class="container">

<a class="navbar-brand text-primary fw-bold">
JobFind
</a>

<div class="ms-auto">

<a href="login.php" class="btn btn-outline-primary me-2">
Login
</a>

<a href="register.php" class="btn btn-primary">
Sign Up
</a>

</div>

</div>

</nav>


<!-- HERO -->
<section class="hero">

<div class="container">

<h1>Find Your Dream Job</h1>

<p>
Discover jobs from trusted companies
</p>

</div>

</section>



<!-- JOB LIST -->
<section>

<div class="container">

<h3 class="mb-4">Latest Jobs</h3>

<div class="row">

<?php

// ตรวจสอบว่ามีงานไหม
if(mysqli_num_rows($query) > 0){

while($job = mysqli_fetch_assoc($query)){

?>

<div class="col-md-4">

<div class="job-card">

<h5>
<?php echo $job['title']; ?>
</h5>

<p>
📍 <?php echo $job['location']; ?>
</p>

<p>
💰 <?php echo $job['salary']; ?> บาท
</p>

<a href="apply_job.php?job_id=<?php echo $job['job_id']; ?>" 
class="btn btn-primary apply-btn">

Apply Now

</a>

</div>

</div>

<?php

}

}else{

echo "<p>No jobs found</p>";

}

?>

</div>

</div>

</section>


<!-- FOOTER -->
<footer class="text-center mt-5 mb-3 text-muted">

© 2026 JobFind. All rights reserved.

</footer>


</body>
</html>