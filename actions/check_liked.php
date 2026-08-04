<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

header('Content-Type: application/json');

$freelancer_id = jobfind_require_json_role('freelancer', ['liked' => false]);
$employer_id = intval($_GET['employer_id'] ?? 0);

if($employer_id <= 0){
    echo json_encode(['liked' => false]);
    exit();
}

$check = mysqli_query($conn, "
    SELECT * FROM Like_Employer
    WHERE freelancer_id=$freelancer_id
    AND employer_id=$employer_id
");

$liked = mysqli_num_rows($check) > 0;
echo json_encode(['liked' => $liked]);
?>
