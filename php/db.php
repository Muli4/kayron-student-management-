<?php
date_default_timezone_set('Africa/Nairobi'); // Set PHP timezone to EAT

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school_database";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to EAT
$conn->query("SET time_zone = '+03:00'");
?>
