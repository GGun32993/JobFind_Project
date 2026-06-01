<?php
if(session_status() === PHP_SESSION_NONE){
session_start();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">

<div class="container-fluid">

<a class="navbar-brand" href="index.php">
Job_Find
</a>

<div class="ms-auto text-white">

<?php if(isset($_SESSION['fullname'])){ ?>

Welcome, <?php echo $_SESSION['fullname']; ?>

<a href="logout.php" class="btn btn-danger btn-sm ms-3">
Logout
</a>

<?php } ?>

</div>

</div>

</nav>