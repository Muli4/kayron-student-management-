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
                <input type="password" id="password" name="password" placeholder="Please enter password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <script src="./js/java-script.js"></script>
</body>
</html>
