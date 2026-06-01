<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit();
}

if(!isset($_GET['job_id']))
{
    echo "No Job Selected";
    exit();
}

$job_id = $_GET['job_id'];


$query = mysqli_query($conn,"
SELECT job.*, users.username AS employer
FROM job
JOIN users ON job.employer_id = users.user_id
WHERE job.job_id='$job_id'
");

$job = mysqli_fetch_assoc($query);

if(!$job)
{
    echo "Job not found";
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=6">

<title>Job Details</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:20px;
}

.container{
    background:white;
    padding:20px;
}

.btn{
    padding:10px 15px;
    color:white;
    text-decoration:none;
    border-radius:5px;
}

.btn-success{
    background:#28a745;
}

.btn-secondary{
    background:#6c757d;
}

</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">


</head>
<body>

<div class="container">

<h2><?php echo $job['title']; ?></h2>

<p>
<b>Employer:</b>
<?php echo $job['employer']; ?>
</p>


<p>
<b>Location:</b>
<?php echo $job['location']; ?>
</p>


<p>
<b>Salary:</b>
<?php echo $job['salary']; ?>
</p>


<p>
<b>Description:</b>
</p>

<p>

<?php
echo nl2br($job['description'] ?? "No description");
?>

</p>


<br>


<a href="apply_job.php?job_id=<?php echo $job['job_id']; ?>"
class="btn btn-success">

Apply Job

</a>


<a href="browse_jobs.php"
class="btn btn-secondary">

Back

</a>

</div>

</body>
</html>