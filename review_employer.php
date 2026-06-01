<?php
session_start();
require_once __DIR__ . "/config.php";

// check login
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
header("Location: login.php");
exit();
}

$freelancer_id = $_SESSION['user_id'];
$employer_id = $_GET['employer_id'];

// submit review
if(isset($_POST['submit'])){

$comment = $_POST['comment'];

mysqli_query($conn,"
INSERT INTO freelancer_review
(freelancer_id, employer_id, comment)
VALUES
('$freelancer_id','$employer_id','$comment')
");

echo "<script>alert('Review submitted successfully');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=7">

<title>Review Employer</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f8f9fa;
}

.container{
margin-top:50px;
max-width:600px;
background:white;
padding:30px;
border-radius:10px;
box-shadow:0px 0px 10px rgba(0,0,0,0.1);
}

</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">


</head>

<body>

<div class="container">

<h2>Review Employer</h2>

<form method="POST">

<textarea
name="comment"
class="form-control mb-3"
placeholder="Write your review here"
required></textarea>

<button
name="submit"
class="btn btn-primary">

Submit Review

</button>

</form>

<br>

<a href="my_applications.php" class="btn btn-secondary">

Back

</a>

</div>

</body>
</html>