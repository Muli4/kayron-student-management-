<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php'; // Ensure database connection is included

    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $username = $_SESSION['username'];

    // Validate form data
    if ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match!";
    } else {
        // Fetch the user's current hashed password from the database
        $stmt = $conn->prepare("SELECT password FROM administration WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($db_password);
            $stmt->fetch();

            // Hash the entered current password to compare with the stored hashed password
            if (hash('sha256', $current_password) === $db_password) {
                // Hash the new password before storing it
                $hashed_new_password = hash('sha256', $new_password);

                // Update the password in the database
                $update_stmt = $conn->prepare("UPDATE administration SET password = ? WHERE username = ?");
                $update_stmt->bind_param("ss", $hashed_new_password, $username);

                if ($update_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
                $update_stmt->close();
            } else {
                $error_message = "Current password is incorrect.";
            }
        } else {
            $error_message = "User not found in the database.";
        }

        $stmt->close();
    }
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
</head>
<body>
    <div class="change-container">
        <h2>Change Password</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="change-password.php" method="POST">
            <div class="input-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
            </div>
            <div class="input-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <button type="submit">Change Password</button>
        </form>
    </div>

    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard <i class='bx bx-exit'></i></a>
    </div>
</body>
</html>
