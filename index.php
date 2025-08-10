<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kayron Junior School</title>
    <link rel="website icon" type="png" href="./images/school-logo.jpg">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    body.login-body {
        font-family: 'Segoe UI', sans-serif;
        background: linear-gradient(135deg, #4e73df, #1cc88a);
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        padding: 15px;
    }

    .login-container {
        background: #fff;
        padding: 2rem 2rem;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 350px;
        animation: fadeIn 0.6s ease-in-out;
        box-sizing: border-box;
    }

    /* Bigger, centered logo */
    .login-container img {
        display: block;
        margin: 0 auto 1rem auto;
        width: 120px; /* Increased from 70px */
        height: auto;
    }

    .login-container h2 {
        text-align: center;
        margin-bottom: 1.5rem;
        color: #48CAE4;
        font-size: 22px; /* Slightly bigger title */
    }

    .message {
        background: #ffdddd;
        color: #b30000;
        padding: 0.5rem;
        text-align: center;
        border-radius: 5px;
        margin-bottom: 1rem;
        font-size: 14px;
    }

    .input-group {
        margin-bottom: 1.2rem;
    }

    label {
        display: block;
        font-weight: bold;
        margin-bottom: 0.4rem;
        color: #333;
        font-size: 14px;
    }

    input[type="text"],
    input[type="password"] {
        width: 100%;
        padding: 10px 40px 10px 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        outline: none;
        font-size: 14px;
        box-sizing: border-box; /* Ensures inputs never exceed form */
    }

    input:focus {
        border-color: #4e73df;
        box-shadow: 0 0 5px rgba(78, 115, 223, 0.5);
    }

    .password-wrapper {
        position: relative;
    }

    .password-wrapper i {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 14px;
    }

    button {
        width: 100%;
        padding: 10px;
        background: #48CAE4;
        border: none;
        border-radius: 8px;
        color: #fff;
        font-size: 15px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }

    button:hover {
        background: #48CAE4;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Mobile adjustments */
    @media (max-width: 480px) {
        .login-container {
            padding: 1.5rem;
        }
        .login-container img {
            width: 90px; /* Smaller on mobile */
        }
        .login-container h2 {
            font-size: 18px;
        }
        label, input, button {
            font-size: 13px;
        }
    }
</style>

</head>
<body class="login-body">

    <div class="login-container">
        <!-- Logo -->
        <img src="./images/school-logo.jpg" alt="Kayron Junior School Logo">
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
                    <i id="togglePassword" class="fa-solid fa-eye"></i>
                </div>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const togglePassword = document.getElementById("togglePassword");
            const passwordInput = document.getElementById("password");

            togglePassword.addEventListener("click", function () {
                const isPassword = passwordInput.type === "password";
                passwordInput.type = isPassword ? "text" : "password";
                this.classList.toggle("fa-eye");
                this.classList.toggle("fa-eye-slash");
            });
        });
    </script>
</body>
</html>
