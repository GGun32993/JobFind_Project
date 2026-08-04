<?php
if(session_status() === PHP_SESSION_NONE){
session_start();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">

<div class="container-fluid">

<a class="navbar-brand" href="../index.php">
Freelance Matching Online
</a>

<div class="ms-auto text-white d-flex align-items-center gap-3">

<button class="theme-toggle-btn btn-sm" type="button" aria-label="Toggle theme">
  <i class="bi bi-moon-stars-fill theme-toggle-icon"></i>
  <span class="theme-toggle-text">โหมดมืด</span>
</button>

<?php if(isset($_SESSION['fullname'])){ ?>

Welcome, <?php echo $_SESSION['fullname']; ?>

<a href="../logout.php" class="btn btn-danger btn-sm ms-2">
Logout
</a>

<?php } ?>

</div>

</div>

</nav>