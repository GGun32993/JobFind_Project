<?php
session_start();
include "config.php";

$user_id = $_SESSION['user_id'];

$result = mysqli_query($conn,"
SELECT u.username, r.rating, r.review
FROM employer_review r
JOIN users u ON r.employer_id=u.user_id
WHERE r.freelancer_id='$user_id'
");
?>

<h2>My Reviews</h2>

<?php while($row=mysqli_fetch_assoc($result)): ?>

<div>

Employer: <?php echo $row['username']; ?><br>

Rating: <?php echo $row['rating']; ?>/5<br>

Review: <?php echo $row['review']; ?>

</div>

<br>

<?php endwhile; ?>