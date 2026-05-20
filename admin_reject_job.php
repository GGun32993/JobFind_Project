<?php
include "config.php";

$id = $_GET['id'];

mysqli_query($conn,"
UPDATE job
SET job_status='rejected'
WHERE job_id='$id'
");

header("Location: admin_manage_jobs.php");
exit();
?>