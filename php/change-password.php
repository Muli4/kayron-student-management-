<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php'; // Ensure database connection is included
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $username = $_SESSION['username'];

    // Validate form data
    if ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match!";
    } else {
        // Fetch the user's current password from the database
        $stmt = $conn->prepare("SELECT password FROM administration WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($db_password);
            $stmt->fetch();

            // Compare plain text current password
            if ($current_password === $db_password) {
                // Update the password in the database (plain text)
                $update_stmt = $conn->prepare("UPDATE administration SET password = ? WHERE username = ?");
                $update_stmt->bind_param("ss", $new_password, $username);
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
</head>
<body>
    <div class="change-container">
        <h2>Change Password</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="change-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="change-error"><?php echo $error_message; ?></div>
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
