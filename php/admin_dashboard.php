<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php'; // Database connection

// Fetch total students
$totalStudents = 0;
$boys = 0;
$girls = 0;

$result = $conn->query("SELECT COUNT(*) AS total, 
                               SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS boys, 
                               SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS girls 
                        FROM student_records");
if ($result) {
    $row = $result->fetch_assoc();
    $totalStudents = $row['total'];
    $boys = $row['boys'];
    $girls = $row['girls'];
}

// Fetch total teachers
$totalTeachers = 0;
$maleTeachers = 0;
$femaleTeachers = 0;

$result = $conn->query("SELECT COUNT(*) AS total, 
                               SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS maleTeachers, 
                               SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS femaleTeachers 
                        FROM teacher_records");
if ($result) {
    $row = $result->fetch_assoc();
    $totalTeachers = $row['total'];
    $maleTeachers = $row['maleTeachers'];
    $femaleTeachers = $row['femaleTeachers'];
}

// Fetch total unique classes
$totalClasses = 0;
$result = $conn->query("SELECT COUNT(DISTINCT class) AS total FROM student_records");
if ($result) {
    $row = $result->fetch_assoc();
    $totalClasses = $row['total'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
<style>
.dashboard-data {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: stretch;
    margin-top: 20px;
}

.data-container {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    border-radius: 8px;
    padding: 1rem 1.2rem;
    box-shadow: 0 4px 10px rgba(30, 100, 150, 0.3);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
    transition: box-shadow 0.3s ease;
    cursor: default;
}

.data-container:hover {
    box-shadow: 0 6px 20px rgba(30, 100, 150, 0.5);
}

.data-container h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: #e0f7fa; /* lighter accent */
}

.data-container p {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}


/* Make canvas span full width below the cards */
#studentPerformanceChart {
    grid-column: 1 / -1; /* span all columns */
    width: 100% !important; /* ensure full width */
    height: 300px; /* set desired height */
    margin-top: 2rem;
}

/* Responsive tweaks */
@media (max-width: 480px) {
    .dashboard-data {
        grid-template-columns: 1fr;
    }
}

</style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="content">
            <!-- Dashboard Data -->
            <div class="dashboard-data">
                <div class="data-container">
                    <h3>Total Students</h3>
                    <p><?php echo $totalStudents; ?></p>
                </div>
                <div class="data-container">
                    <h3> Boys : Girls</h3>
                    <p><?php echo $boys . " : " . $girls; ?></p>
                </div>
                <div class="data-container">
                    <h3>Total Teachers</h3>
                    <p><?php echo $totalTeachers; ?></p>
                </div>
                <div class="data-container">
                    <h3> Male  : Female </h3>
                    <p><?php echo $maleTeachers . " : " . $femaleTeachers; ?></p>
                </div>
                <div class="data-container">
                    <h3>Total Classes</h3>
                    <p><?php echo $totalClasses; ?></p>
                </div>
                <canvas id="studentPerformanceChart"></canvas>
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
