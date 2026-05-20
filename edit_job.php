<?php

session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
header("Location: login.php");
exit();
}

$job_id = $_GET['id'];


// UPDATE
if(isset($_POST['update'])){

$title = $_POST['title'];
$description = $_POST['description'];
$location = $_POST['location'];
$salary = $_POST['salary'];

mysqli_query($conn,"
UPDATE job SET
title='$title',
description='$description',
location='$location',
salary='$salary'
WHERE job_id='$job_id'
");

echo "<script>alert('Job updated');</script>";

}


// GET JOB
$query = mysqli_query($conn,"
SELECT * FROM job
WHERE job_id='$job_id'
");

$job = mysqli_fetch_assoc($query);

?>

<!DOCTYPE html>
<html>
<head>

<title>Edit Job</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>

<div class="container mt-5">

<h2>Edit Job</h2>

<form method="POST">

<input type="text"
name="title"
class="form-control mb-3"
value="<?php echo $job['title']; ?>"
required>


<textarea name="description"
class="form-control mb-3"><?php echo $job['description']; ?></textarea>


<input type="text"
name="location"
class="form-control mb-3"
value="<?php echo $job['location']; ?>">


<input type="text"
name="salary"
class="form-control mb-3"
value="<?php echo $job['salary']; ?>">


<button name="update"
class="btn btn-primary">

Update Job

</button>

</form>

</div>

</body>
</html>