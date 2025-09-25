<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare query for normal user
    $stmt = $conn->prepare("SELECT password, role, login_attempts, is_locked FROM administration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    // Check if user exists
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($db_password, $role, $login_attempts, $is_locked);
        $stmt->fetch();

        $max_attempts = 5;

        // Account locked
        if ($is_locked) {
            $_SESSION['message'] = "<div class='error-message'>Account is locked. Contact administrator.</div>";
            header("Location: ../index.php");
            exit();
        }

        // Compare hashed password
        $hashed_password = hash('sha256', $password);

        if ($hashed_password === $db_password) {
            // ✅ Correct password: reset attempts
            $update = $conn->prepare("UPDATE administration SET login_attempts = 0 WHERE username = ?");
            $update->bind_param("s", $username);
            $update->execute();

            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['message'] = "";

            // Redirect based on role
            if ($role === 'teacher') {
                header("Location: admin_dashboard.php");
            } elseif ($role === 'admin') {
                header("Location: ../admin/master_panel.php");
            } else {
                $_SESSION['message'] = "<div class='error-message'>Invalid role!</div>";
                header("Location: ../index.php");
            }

            exit();
        } else {
            // ❌ Wrong password
            $login_attempts++;
            $is_locked = $login_attempts >= $max_attempts ? 1 : 0;

            $update = $conn->prepare("UPDATE administration SET login_attempts = ?, is_locked = ? WHERE username = ?");
            $update->bind_param("iis", $login_attempts, $is_locked, $username);
            $update->execute();

            if ($is_locked) {
                $_SESSION['message'] = "<div class='error-message'>Account locked after $max_attempts failed attempts.</div>";
            } else {
                $_SESSION['message'] = "<div class='error-message'>Incorrect password! Attempt $login_attempts of $max_attempts.</div>";
            }

            header("Location: ../index.php");
            exit();
        }
    } else {
        // ❌ User not found
        $_SESSION['message'] = "<div class='error-message'>Username not found!</div>";
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
