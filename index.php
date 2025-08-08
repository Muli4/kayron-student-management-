<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kayron Junior School</title>
    <link rel="stylesheet" href="./style/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>KAYRON JUNIOR SCHOOL</h2>
        <?php
        session_start();
        if (isset($_SESSION['message'])) {
            echo "<div class='message'>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']);
        }
        ?>
        <form action="./php/config.php" method="POST">
            <div class="input-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Please enter username" required>
            </div>
            <div class="input-group">
                <label for="password">Password:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Please enter password" required>
                    <span id="togglePassword">👁️</span>
                </div>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <!-- Password Toggle Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const togglePassword = document.getElementById("togglePassword");
            const passwordInput = document.getElementById("password");

            togglePassword.addEventListener("click", function () {
                const isPassword = passwordInput.type === "password";
                passwordInput.type = isPassword ? "text" : "password";
                this.textContent = isPassword ? "🙈" : "👁️";
            });
        });
    </script>
</body>
</html>
