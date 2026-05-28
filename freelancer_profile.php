<?php

session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/location_schema.php";

ensure_location_schema($conn);

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
$gender = jobfind_normalize_gender($_POST['gender'] ?? '');
$gender_sql = $gender !== ''
    ? "'" . mysqli_real_escape_string($conn, $gender) . "'"
    : "NULL";

mysqli_query($conn,"
UPDATE users SET
fullname='$fullname',
phone='$phone',
email='$email',
gender=$gender_sql
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
$selected_gender = jobfind_normalize_gender($user['gender'] ?? '');

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

<label>เพศ</label>

<select name="gender" class="form-control mb-3">
<option value="">เลือกเพศ</option>
<?php foreach(jobfind_gender_options() as $gender_value => $gender_label): ?>
<option value="<?php echo htmlspecialchars($gender_value); ?>" <?php echo $selected_gender === $gender_value ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($gender_label); ?>
</option>
<?php endforeach; ?>
</select>



<button name="update"
class="btn btn-primary">

Update Profile

</button>


</form>


</div>


</body>
</html>
