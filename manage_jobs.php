<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

$result = mysqli_query($conn,"
SELECT * FROM job
WHERE employer_id='$employer_id'
ORDER BY job_id DESC
");

?>

<h2>Manage Jobs</h2>

<a href="employer_dashboard.php">Back Dashboard</a>

<?php while($job=mysqli_fetch_assoc($result)){ ?>

<div style="border:1px solid #ccc;padding:10px;margin:10px;">

<h3><?php echo $job['title']; ?></h3>

<p>Status: <?php echo $job['status']; ?></p>

<p>End Date: <?php echo $job['end_date']; ?></p>

<a href="view_applicants.php?job_id=<?php echo $job['job_id']; ?>">
<button>View Applicants</button>
</a>

</div>

<?php } ?>