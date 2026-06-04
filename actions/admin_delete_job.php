<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/job_image_helpers.php";

$id = intval($_GET['id'] ?? 0);
ensure_job_image_schema($conn);

$image_paths = [];
$img_res = mysqli_query($conn,"SELECT image_path FROM job_images WHERE job_id='$id'");
if($img_res){
    while($img = mysqli_fetch_assoc($img_res)){
        $image_paths[] = $img['image_path'];
    }
}

mysqli_query($conn,"DELETE FROM job_images WHERE job_id='$id'");
mysqli_query($conn,"
DELETE FROM job
WHERE job_id='$id'
");

foreach($image_paths as $path){
    delete_job_image_file($path);
}

header("Location: ../admin/manage_jobs.php");
exit();
?>
