<?php
require_once __DIR__ . "/../config/config.php";

$query = mysqli_query($conn,"
SELECT users.fullname, COUNT(like_employer.like_id) as total_likes
FROM like_employer
JOIN users ON like_employer.employer_id = users.user_id
GROUP BY employer_id
ORDER BY total_likes DESC
LIMIT 5
");
?>

<h2>Popular Employers</h2>

<?php while($row = mysqli_fetch_assoc($query)){ ?>

<p>

<?php echo $row['fullname']; ?>

(❤ <?php echo $row['total_likes']; ?>)

</p>

<?php } ?>