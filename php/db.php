<?php
$servername = "localhost";  // or the IP address of the MySQL server
$username = "root";
$password = "1428";  // your MySQL root password
$dbname = "school_database";
$port = 3307;  // ensure this matches the port MySQL is using

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
