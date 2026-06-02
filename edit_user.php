<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
header("Location: login.php");
exit();
}

$id = $_GET['id'];

$user = mysqli_query($conn,"
SELECT *
FROM users
WHERE user_id='$id'
");

$data = mysqli_fetch_assoc($user);

if(isset($_POST['update'])){

$username = $_POST['username'];
$email = $_POST['email'];
$fullname = $_POST['fullname'];
$phone = $_POST['phone'];
$role = $_POST['role'];

mysqli_query($conn,"
UPDATE users
SET username='$username',
email='$email',
fullname='$fullname',
phone='$phone',
role='$role'
WHERE user_id='$id'
");

echo "<script>alert('User updated');</script>";
echo "<script>window.location='manage_users.php';</script>";

}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=13">

<title>Edit User</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
</head>

<body>

<div class="container mt-5">

<h3>Edit User</h3>

<a href="manage_users.php" class="btn btn-secondary mb-3">
Back
</a>

<form method="POST">

<label>Username</label>
<input type="text"
name="username"
class="form-control mb-2"
value="<?php echo $data['username']; ?>"
required>

<label>Email</label>
<input type="email"
name="email"
class="form-control mb-2"
value="<?php echo $data['email']; ?>"
required>

<label>Fullname</label>
<input type="text"
name="fullname"
class="form-control mb-2"
value="<?php echo $data['fullname']; ?>">

<label>Phone</label>
<input type="text"
name="phone"
class="form-control mb-2"
value="<?php echo $data['phone']; ?>">

<label>Role</label>

<select name="role" class="form-control mb-3">

<option value="admin"
<?php if($data['role']=="admin") echo "selected"; ?>>
Admin
</option>

<option value="employer"
<?php if($data['role']=="employer") echo "selected"; ?>>
Employer
</option>

<option value="freelancer"
<?php if($data['role']=="freelancer") echo "selected"; ?>>
Freelancer
</option>

</select>

<button type="submit"
name="update"
class="btn btn-primary">

Update User

</button>

</form>

</div>

</body>
</html>