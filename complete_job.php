<?php
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

$job_id = $_GET['job_id'];

mysqli_query($conn,"
UPDATE job
SET status='completed'
WHERE job_id='$job_id'
");

header("Location: employer_manage_jobs.php");
exit();
?>