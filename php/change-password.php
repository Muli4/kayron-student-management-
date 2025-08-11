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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Change Password</title>
  <link rel="stylesheet" href="../style/style-sheet.css" />
  <link rel="website icon" type="png" href="../images/school-logo.jpg" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <style>
    /* Container wrapper */
.change-password-container {
  max-width: 480px;
  margin: 40px auto;
  padding: 30px 25px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #333;
}

/* Page Title */
.change-password-container .page-title {
  font-size: 28px;
  font-weight: 700;
  color: #004b8d;
  margin-bottom: 25px;
  text-align: center;
  border-bottom: 2px solid #004b8d;
  padding-bottom: 10px;
}

/* Alerts */
.alert {
  margin-bottom: 20px;
  padding: 12px 15px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
}

.success-message {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.error-message {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Form Styles */
.change-password-form .form-group {
  margin-bottom: 18px;
  display: flex;
  flex-direction: column;
}

.change-password-form label {
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 15px;
  color: #222;
}

.change-password-form input[type="password"] {
  padding: 10px 12px;
  border: 1.5px solid #ccc;
  border-radius: 6px;
  font-size: 15px;
  transition: border-color 0.3s ease;
}

.change-password-form input[type="password"]:focus {
  outline: none;
  border-color: #004b8d;
  box-shadow: 0 0 5px rgba(0, 75, 141, 0.5);
}

/* Submit Button */
.change-password-form button.btn-primary {
  width: 100%;
  padding: 12px 0;
  background-color: #004b8d;
  color: #fff;
  font-weight: 700;
  font-size: 16px;
  border: none;
  border-radius: 7px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.change-password-form button.btn-primary:hover {
  background-color: #00345f;
}


/* Responsive */
@media (max-width: 480px) {
  .change-password-container {
    margin: 20px 15px;
    padding: 20px 15px;
  }

  .change-password-form button.btn-primary {
    font-size: 14px;
  }

  .change-password-container .page-title {
    font-size: 24px;
  }
}

  </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>

  <main class="content">
    <div class="container-wrapper change-password-container">
      <h2 class="page-title">Change Password</h2>

      <?php if (!empty($success_message)): ?>
        <div class="alert success-message"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="alert error-message"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <form action="change-password.php" method="POST" class="change-password-form" autocomplete="off">
        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input 
            type="password" 
            id="current_password" 
            name="current_password" 
            placeholder="Enter current password" 
            required
          />
        </div>

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input 
            type="password" 
            id="new_password" 
            name="new_password" 
            placeholder="Enter new password" 
            required
          />
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input 
            type="password" 
            id="confirm_password" 
            name="confirm_password" 
            placeholder="Confirm new password" 
            required
          />
        </div>

        <button type="submit" class="btn-primary">Change Password</button>
      </form>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) { // removed window.innerWidth check to show clock on all devices
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            clockElement.textContent = timeString;
        }
    }
    updateClock(); 
    setInterval(updateClock, 1000);

    /* ===== Dropdowns: only one open ===== */
    document.querySelectorAll(".dropdown-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const parent = btn.parentElement;

            document.querySelectorAll(".dropdown").forEach(drop => {
                if (drop !== parent) {
                    drop.classList.remove("open");
                }
            });

            parent.classList.toggle("open");
        });
    });

    /* ===== Sidebar toggle for mobile ===== */
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.toggle-btn');
    const overlay = document.createElement('div');
    overlay.classList.add('sidebar-overlay');
    document.body.appendChild(overlay);

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    /* ===== Close sidebar on outside click ===== */
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    /* ===== Auto logout after 30 seconds inactivity (no alert) ===== */
    let logoutTimer;

    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            // Silent logout - redirect to logout page
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 30 seconds
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    // Start the timer when page loads
    resetLogoutTimer();
});
</script>
</body>
</html>
