<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // --- Admin override credentials ---
    $override_username = 'masteradmin';
    $override_password = 'jm0011';

    if ($username === $override_username && $password === $override_password) {
        $_SESSION['username'] = $override_username;
        $_SESSION['role'] = 'masteradmin';  // special master admin role
        $_SESSION['message'] = "";

        // Unlock all accounts and reset login attempts
        $conn->query("UPDATE administration SET is_locked = 0, login_attempts = 0");

        header("Location: master_panel.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT password, role, login_attempts, is_locked FROM administration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($db_password, $role, $login_attempts, $is_locked);
        $stmt->fetch();

        if ($is_locked) {
            $_SESSION['message'] = "<div class='error-message'>Account is locked. Contact administrator.</div>";
            header("Location: ../index.php");
            exit();
        }

        $hashed_password = hash('sha256', $password);

        if ($hashed_password === $db_password) {
            // Reset attempts on successful login
            $update = $conn->prepare("UPDATE administration SET login_attempts = 0 WHERE username = ?");
            $update->bind_param("s", $username);
            $update->execute();

            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['message'] = "";

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
            $login_attempts++;
            $is_locked = $login_attempts >= 3 ? 1 : 0;

            $update = $conn->prepare("UPDATE administration SET login_attempts = ?, is_locked = ? WHERE username = ?");
            $update->bind_param("iis", $login_attempts, $is_locked, $username);
            $update->execute();

            if ($is_locked) {
                $_SESSION['message'] = "<div class='error-message'>Account locked after 3 failed attempts.</div>";
            } else {
                $_SESSION['message'] = "<div class='error-message'>Incorrect password! Attempt $login_attempts of 3.</div>";
            }

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
