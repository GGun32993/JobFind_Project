<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

$freelancer_id = jobfind_require_role('freelancer');


$res = mysqli_query($conn,"
SELECT *
FROM Resume
WHERE freelancer_id='$freelancer_id'
ORDER BY resume_id DESC
LIMIT 1
");

$data = mysqli_fetch_assoc($res);

if($data){

    $resume_path = JOBFIND_UPLOADS_PATH . DIRECTORY_SEPARATOR . basename((string)$data['file_name']);
    if(is_file($resume_path)){
        unlink($resume_path);
    }

    mysqli_query($conn,"
    DELETE FROM Resume
    WHERE resume_id='".$data['resume_id']."'
    ");

}

header("Location: ../freelancer/upload_resume.php");
exit();
