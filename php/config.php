<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // --- Master Admin Override ---
    $override_username = 'masteradmin';
    $override_password = 'jm0011';

    if ($username === $override_username && $password === $override_password) {
        $_SESSION['username'] = $override_username;
        $_SESSION['role'] = 'masteradmin';  // Special role
        $_SESSION['message'] = "";

        // ✅ No reset or unlock of other accounts

        header("Location: master_panel.php");
        exit();
    }

    // Prepare query for normal user
    $stmt = $conn->prepare("SELECT password, role, login_attempts, is_locked FROM administration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    // User found
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($db_password, $role, $login_attempts, $is_locked);
        $stmt->fetch();

        // Define max allowed attempts
        $max_attempts = 5;

        // Check if account is locked
        if ($is_locked) {
            $_SESSION['message'] = "<div class='error-message'>Account is locked. Contact administrator.</div>";
            header("Location: ../index.php");
            exit();
        }

        // Hash input password to compare
        $hashed_password = hash('sha256', $password);

        // ✅ Correct password
        if ($hashed_password === $db_password) {
            // Reset login attempts on success
            $update = $conn->prepare("UPDATE administration SET login_attempts = 0 WHERE username = ?");
            $update->bind_param("s", $username);
            $update->execute();

            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['message'] = "";

            // Redirect by role
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
            // ❌ Incorrect password
            $login_attempts++;
            $is_locked = $login_attempts >= $max_attempts ? 1 : 0;

            // Update attempts and lock status
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
        // Username not found
        $_SESSION['message'] = "<div class='error-message'>Username not found!</div>";
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
