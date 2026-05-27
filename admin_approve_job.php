<?php
require_once __DIR__ . "/config.php";

$id = $_GET['id'];

mysqli_query($conn,"
UPDATE job
SET admin_status='approved', status='open'
WHERE job_id='$id'
");

header("Location: admin_manage_jobs.php");
exit();
?>
