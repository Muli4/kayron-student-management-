<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
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
                <a href="./manage-students.php" class="link-card">View Student(s)</a>
                <a href="./view-balances.php" class="link-card">view balance(s)</a>
                <a href="./make-payments.php" class="link-card">Make Payments</a>
                <a href="./purchase-book.php" class="link-card">purchase diary | assessment</a>
                <a href="./process-uniform.php" class="link-card">purchase uniform</a>
                <a href="./weekly-report.php" class="link-card">Weekly Report</a>
                <a href="#" class="link-card">Other</a>
                <a href="#" class="link-card">Clear Balance</a>
                <a href="#" class="link-card">Print Receipt</a>
                <a href="#" class="link-card">School Exams</a>
                <a href="#" class="link-card">Subordinate Staff</a>
            </div>
        </div>
    </div>
    
    <footer class="footer-dash">
        <p>&copy; <?php echo date("Y")?>Kayron Junior School. All Rights Reserved.</p>
    </footer>


    <script src="../js/java-script.js"></script>
</body>
</html>
