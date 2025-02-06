<?php
session_start();
include 'db.php'; // Ensure you have a proper database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT password FROM administration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        // Bind the result to a variable
        $stmt->bind_result($db_password);
        $stmt->fetch();

        // Check if the entered password matches the stored password
        if ($password === $db_password) {
            $_SESSION['username'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: ../index.html?error=incorrect_password");
            exit();
        }
    } else {
        header("Location: ../index.html?error=username_not_found");
        exit();
    }

    $stmt->close();
}
$conn->close();
?>
