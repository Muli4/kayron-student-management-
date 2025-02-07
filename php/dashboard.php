<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../style/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <h2 class="logo">Kayron</h2>
            <ul>
                <li class="dropdown">
                    <a href="#" onclick="toggleDropdown(event)"><i class='bx bxs-cog'></i>Settings <i class='bx bx-chevron-down'></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="./change-username.php">Change Username</a></li>
                        <li><a href="./change-password.php">Change Password</a></li>
                    </ul>
                </li>
                <li class="logout-link">
                    <a href="logout.php"><i class='bx bx-log-out'> Logout</i></a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class='bx bx-menu'></i>
            </button>
            <h2 class="dashboard-title">Dashboard</h2>
            
            <div class="links-grid">
                <a href="./add-student.php" class="link-card">Add Student</a>
                <a href="" class="link-card">View Student | Teacher</a>
                <a href="#" class="link-card">Remove Student | Teacher</a>
                <a href="./lunch-fee.php" class="link-card">Lunch Fees</a>
                <a href="./make-payments.php" class="link-card">Make Payments</a>
                <a href="./school-fee-payment.php" class="link-card">school fees</a>
                <a href="#" class="link-card">Class Update</a>
                <a href="#" class="link-card">Update Details</a>
                <a href="#" class="link-card">Search Receipt</a>
                <a href="#" class="link-card">Print Receipt</a>
                <a href="#" class="link-card">School Exams</a>
                <a href="#" class="link-card">Subordinate Staff</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
    let sidebar = document.getElementById("sidebar");
    sidebar.classList.toggle("show-sidebar");
}

// Close sidebar when clicking outside (for better UX)
document.addEventListener("click", function (event) {
    let sidebar = document.getElementById("sidebar");
    let toggleBtn = document.querySelector(".toggle-btn");

    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
        sidebar.classList.remove("show-sidebar");
    }
});


        function toggleDropdown(event) {
            event.preventDefault();
            let dropdown = event.currentTarget.parentElement;
            dropdown.classList.toggle("active");
        }

        // Prevent back navigation after logout
        window.history.pushState(null, "", window.location.href);
        window.addEventListener("popstate", function() {
            window.history.pushState(null, "", window.location.href);
        });

        function preventBack() {
            window.history.forward();
        }
        setTimeout(preventBack, 0);
        window.onunload = function() { null };
    </script>
</body>
</html>
