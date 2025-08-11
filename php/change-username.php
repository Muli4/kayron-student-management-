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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Change Username</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .change-username-container {
  max-width: 480px;
  margin: 60px auto 80px;
  padding: 30px 25px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #333;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #004b8d;
  margin-bottom: 25px;
  text-align: center;
  border-bottom: 2px solid #004b8d;
  padding-bottom: 10px;
}

.change-message {
  background-color: #e1f0d8;
  color: #3c763d;
  border: 1px solid #d6e9c6;
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 20px;
  text-align: center;
  font-weight: 600;
}

.change-username-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  font-weight: 600;
  font-size: 14px;
  color: #222;
}

.form-group label {
  margin-bottom: 6px;
}

.form-group input[type="text"],
.form-group input[type="password"] {
  padding: 10px 12px;
  font-size: 15px;
  border-radius: 6px;
  border: 1px solid #ccc;
  transition: border-color 0.3s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="password"]:focus {
  border-color: #004b8d;
  outline: none;
}

.btn-primary {
  background-color: #004b8d;
  color: white;
  font-weight: 700;
  font-size: 16px;
  padding: 12px 0;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.btn-primary:hover {
  background-color: #00345f;
}

/* Responsive for small devices */
@media (max-width: 600px) {
  .change-username-container {
    margin: 40px 15px 60px;
    padding: 25px 20px;
  }
}
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>

  <main class="content">
    <div class="change-username-container">
      <h2 class="page-title">Change Username</h2>

      <?php if (!empty($message)): ?>
        <div class="change-message"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form class="change-username-form" method="POST" action="">
        <div class="form-group">
          <label for="new_username">New Username:</label>
          <input
            type="text"
            id="new_username"
            name="new_username"
            placeholder="Enter new username"
            required
          />
        </div>
        <button type="submit" class="btn-primary">Update Username</button>
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