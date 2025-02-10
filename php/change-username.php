<?php
session_start();
include 'db.php'; // Ensure database connection

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['username'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['new_username']);

    // Prevent empty or same username
    if (empty($new_username)) {
        $message = "Username cannot be empty.";
    } elseif ($new_username === $username) {
        $message = "New username cannot be the same as the current one.";
    } else {
        // Check if the new username is already taken
        $stmt = $conn->prepare("SELECT username FROM administration WHERE username = ?");
        $stmt->bind_param("s", $new_username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Username already exists. Choose another.";
        } else {
            // Update the username securely
            $update_stmt = $conn->prepare("UPDATE administration SET username = ? WHERE username = ?");
            $update_stmt->bind_param("ss", $new_username, $username);

            if ($update_stmt->execute()) {
                $_SESSION['username'] = $new_username; // Update session
                $message = "Username updated successfully.";
            } else {
                $message = "Error updating username.";
            }

            $update_stmt->close();
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Username</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
    <div class="change-container">
        <h2>Change Username</h2>
        <?php if ($message) echo "<p class='change-message'>$message</p>"; ?>
        
        <form method="POST">
            <label>New Username:</label>
            <input type="text" name="new_username" placeholder="Enter new username" required>
            <button type="submit">Update Username</button>
        </form>
    </div>
    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard <i class='bx bx-exit'></i></a>
    </div>
</body>
</html>
