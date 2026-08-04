<?php
session_start();
require_once __DIR__ . "/../config/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer'){
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

$freelancer_id = $_GET['freelancer_id'];
$job_id = $_GET['job_id'];

if(isset($_POST['submit'])){

    $rating = $_POST['rating'];
    $review = $_POST['review'];

    $sql = "INSERT INTO Freelancer_Review
    (freelancer_id, employer_id, rating, review)
    VALUES
    ('$freelancer_id','$employer_id','$rating','$review')";

    mysqli_query($conn,$sql);

    echo "<script>
    alert('Review submitted successfully');
    window.location='../employer/view_applicants.php?job_id=$job_id';
    </script>";
}
?>

<h2>Review Freelancer</h2>

<form method="POST">

Rating:
<select name="rating" required>
<option value="">Select rating</option>
<option value="5">5 - Excellent</option>
<option value="4">4 - Good</option>
<option value="3">3 - Average</option>
<option value="2">2 - Poor</option>
<option value="1">1 - Bad</option>
</select>

<br><br>

Review:
<br>
<textarea name="review" required></textarea>

<br><br>

<button type="submit" name="submit">Submit Review</button>

</form>
