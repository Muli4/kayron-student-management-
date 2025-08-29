<?php
$servername = "localhost";  // or the IP address of the MySQL server
$username = "root";
$password = "";  // your MySQL root password
$dbname = "school_database";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
