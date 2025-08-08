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
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            clockElement.textContent = timeString;
        }
    }
    updateClock(); // Initial call
    setInterval(updateClock, 1000); // Update every second
});
</script>
</body>
</html>
