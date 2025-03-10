<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link rel="stylesheet" href="./style/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>KAYRON JUNIOR SCHOOL</h2>
        <?php
    session_start();
    if (isset($_SESSION['message'])){
        echo"<div class='message'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
    }
    ?>
        <form action="./php/config.php" method="POST">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Please enter username" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Please enter password">
                    <span id="togglePassword">👁️</span>
                </div>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    if (togglePassword) {
        togglePassword.addEventListener("click", function () {
            if (passwordInput.type === "password") {
                passwordInput.type = "text"; // Show password
                this.textContent = "🙈"; // Change icon
            } else {
                passwordInput.type = "password"; // Hide password
                this.textContent = "👁️"; // Change icon
            }
        });
    }
});

    </script>
    <script src="./j/java-script.js"></script>
</body>
</html>
