<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "jobfind";

$conn = mysqli_connect($host,$user,$pass,$db);

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

// start session safely
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

?>