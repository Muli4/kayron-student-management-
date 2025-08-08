<?php
session_start();
include 'db.php'; // Ensure database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Hash the entered password using SHA-256
    $hashed_password = hash('sha256', $password);

    // Prepare statement to fetch password and role
    $stmt = $conn->prepare("SELECT password, role FROM administration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        // Bind the results to variables
        $stmt->bind_result($db_password, $role);
        $stmt->fetch();

        // Compare hashed passwords (entered vs stored)
        if ($hashed_password === $db_password) {
            // Set session variables
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['message'] = "";

            // Redirect based on role
            if ($role === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($role === 'teacher') {
                header("Location: teacher_dashboard.php");
            } else {
                $_SESSION['message'] = "<div class='error-message'>Invalid role!</div>";
                header("Location: ../index.php");
            }
            exit();
        } else {
            $_SESSION['message'] = "<div class='error-message'>Incorrect Password!</div>";
            header("Location: ../index.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "<div class='error-message'>Username not found!</div>";
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
}
$conn->close();
?>
