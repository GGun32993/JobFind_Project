<?php
require_once __DIR__ . "/../config/config.php";

$id = $_GET['id'];

mysqli_query($conn,"
UPDATE job
SET admin_status='rejected'
WHERE job_id='$id'
");

header("Location: ../admin/manage_jobs.php");
exit();
?>
