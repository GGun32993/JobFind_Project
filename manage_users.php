<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
header("Location: login.php");
exit();
}

// delete user
if(isset($_GET['delete'])){

$id = $_GET['delete'];

mysqli_query($conn,"
DELETE FROM users
WHERE user_id='$id'
");

header("Location: manage_users.php");

}

$users = mysqli_query($conn,"
SELECT *
FROM users
ORDER BY user_id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=6">

<title>Manage Users</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
</head>

<body>

<div class="container mt-5">

<h3>Manage Users</h3>

<a href="admin_dashboard.php" class="btn btn-secondary mb-3">
Back Dashboard
</a>

<table class="table table-bordered">

<tr>

<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Fullname</th>
<th>Phone</th>
<th>Role</th>
<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($users)){ ?>

<tr>

<td><?php echo $row['user_id']; ?></td>

<td><?php echo $row['username']; ?></td>

<td><?php echo $row['email']; ?></td>

<td><?php echo $row['fullname']; ?></td>

<td><?php echo $row['phone']; ?></td>

<td><?php echo $row['role']; ?></td>

<td>

<a href="edit_user.php?id=<?php echo $row['user_id']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<a href="manage_users.php?delete=<?php echo $row['user_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete user?')">

Delete

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

</body>
</html>