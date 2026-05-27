<?php

session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
header("Location: login.php");
exit();
}

$user_id = $_SESSION['user_id'];


// =====================
// UPDATE PROFILE
// =====================

if(isset($_POST['update'])){

$fullname = $_POST['fullname'];
$phone = $_POST['phone'];
$email = $_POST['email'];

mysqli_query($conn,"
UPDATE users SET
fullname='$fullname',
phone='$phone',
email='$email'
WHERE user_id='$user_id'
");

echo "<script>alert('Profile updated');</script>";

}


// =====================
// GET USER DATA
// =====================

$query = mysqli_query($conn,"
SELECT * FROM users
WHERE user_id='$user_id'
");

$user = mysqli_fetch_assoc($query);

?>


<!DOCTYPE html>
<html>
<head>

<title>Freelancer Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
</head>
<body>


<div class="container mt-5">


<h2>My Profile</h2>

<a href="freelancer_dashboard.php" class="btn btn-secondary mb-3">
Back Dashboard
</a>


<form method="POST">


<label>Full Name</label>

<input type="text"
name="fullname"
class="form-control mb-3"
value="<?php echo $user['fullname']; ?>"
required>



<label>Email</label>

<input type="email"
name="email"
class="form-control mb-3"
value="<?php echo $user['email']; ?>"
required>



<label>Phone</label>

<input type="text"
name="phone"
class="form-control mb-3"
value="<?php echo $user['phone']; ?>">



<button name="update"
class="btn btn-primary">

Update Profile

</button>


</form>


</div>


</body>
</html>