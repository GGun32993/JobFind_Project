<?php
include "config.php";

$id = $_GET['id'];

mysqli_query($conn,"
DELETE FROM job
WHERE job_id='$id'
");

header("Location: admin_manage_jobs.php");
exit();
?>