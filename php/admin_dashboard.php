<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php'; // Database connection

// ======== Dashboard Stats ========
// Total students
$totalStudents = $boys = $girls = 0;
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

// Total teachers
$totalTeachers = $maleTeachers = $femaleTeachers = 0;
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

// Total classes
$totalClasses = 0;
$result = $conn->query("SELECT COUNT(DISTINCT class) AS total FROM student_records");
if ($result) {
    $row = $result->fetch_assoc();
    $totalClasses = $row['total'];
}

// ======== Current Term & Week Calculation ========
$current_date = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT id, term_number, year, start_date, end_date 
    FROM terms 
    WHERE ? BETWEEN start_date AND end_date 
    LIMIT 1
");
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();
$currentTerm = $result->fetch_assoc();
$stmt->close();

if (!$currentTerm) {
    die("No active term found for today ($current_date).");
}

$termStartDate = $currentTerm['start_date'];
$termEndDate   = $currentTerm['end_date'];
$termNumber    = $currentTerm['term_number'];

// ===== Function to get week start/end skipping weekends =====
function getWeekStartEndPartial($termStartDate, $weekNumber) {
    $weekDays = [];
    $currentDate = strtotime($termStartDate);

    while ($currentDate <= strtotime(date('Y-m-d'))) {
        $dayOfWeek = date('N', $currentDate); // 1=Mon, 7=Sun
        if ($dayOfWeek <= 5) {
            $weekDays[] = date('Y-m-d', $currentDate);
        }
        $currentDate = strtotime("+1 day", $currentDate);
    }

    $weeks = array_chunk($weekDays, 5); // 5 weekdays per week
    if (isset($weeks[$weekNumber - 1])) {
        $weekStart = $weeks[$weekNumber - 1][0];
        $weekEnd = end($weeks[$weekNumber - 1]);
        return [$weekStart, $weekEnd];
    } else {
        return [null, null];
    }
}

// ===== Calculate current week number (includes partial week) =====
$weekdayCount = 0;
$dayIter = strtotime($termStartDate);
while ($dayIter <= strtotime($current_date)) {
    if (date('N', $dayIter) <= 5) $weekdayCount++;
    $dayIter = strtotime("+1 day", $dayIter);
}
$weekNumber = ceil($weekdayCount / 5);

// ===== Weekly Totals =====
$schoolFeesWeeks = $lunchFeesWeeks = $graduationWeeks = $otherWeeks = $booksUniformWeeks = $totalGrossWeeks = [];

for ($i = 1; $i <= $weekNumber; $i++) {
    list($weekStart, $weekEnd) = getWeekStartEndPartial($termStartDate, $i);
    if (!$weekStart || !$weekEnd) {
        $schoolFeesWeeks[] = $lunchFeesWeeks[] = $graduationWeeks[] = $otherWeeks[] = $booksUniformWeeks[] = $totalGrossWeeks[] = 0;
        continue;
    }

    // School Fees
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM school_fee_transactions WHERE DATE(payment_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute(); $stmt->bind_result($amount); $stmt->fetch(); $schoolFeesWeeks[] = $amount; $stmt->close();

    // Lunch Fees
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM lunch_fee_transactions WHERE DATE(payment_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute(); $stmt->bind_result($amount); $stmt->fetch(); $lunchFeesWeeks[] = $amount; $stmt->close();

    // Graduation & Prize
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM other_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND status='Completed' AND fee_type IN ('Graduation','Prize Giving')");
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute(); $stmt->bind_result($amount); $stmt->fetch(); $graduationWeeks[] = $amount; $stmt->close();

    // Other Fees
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM other_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND status='Completed' AND fee_type NOT IN ('Graduation','Prize Giving')");
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute(); $stmt->bind_result($amount); $stmt->fetch(); $otherWeeks[] = $amount; $stmt->close();

    // Books & Uniform
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM (
        SELECT amount_paid, purchase_date AS date FROM book_purchases
        UNION ALL
        SELECT amount_paid, purchase_date AS date FROM uniform_purchases
    ) AS purchases WHERE DATE(date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute(); $stmt->bind_result($amount); $stmt->fetch(); $booksUniformWeeks[] = $amount; $stmt->close();

    // Total Gross
    $totalGrossWeeks[] = $schoolFeesWeeks[$i-1] + $lunchFeesWeeks[$i-1] + $graduationWeeks[$i-1] + $otherWeeks[$i-1] + $booksUniformWeeks[$i-1];
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
/* ===== DASHBOARD CARDS ===== */
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
    color: #e0f7fa;
}
.data-container p {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}

/* ===== CHARTS ===== */
.charts-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-top: 2rem;
}
canvas.chart {
    width: 100% !important;
    height: 300px !important; /* Taller for better readability */
    background: #fff;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .charts-container {
        grid-template-columns: 1fr 1fr; /* 2 per row on tablets */
    }
    canvas.chart {
        height: 280px !important;
    }
}

@media (max-width: 600px) {
    .dashboard-data {
        grid-template-columns: 1fr; /* stack cards on mobile */
    }
    .charts-container {
        grid-template-columns: 1fr; /* one chart per row */
    }
    canvas.chart {
        height: 320px !important; /* extra height for labels */
        padding: 14px;
    }
}
.message-container {
    position: relative;
    display: inline-block;
    padding: 16px 20px;
    background: #e3f2fd; /* Light blue background */
    color: #0d47a1;       /* Deep blue text */
    font-size: 18px;
    font-weight: 500;
    border-left: 6px solid #2196f3; /* Info blue */
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    animation: slide-right 30s linear infinite;
    white-space: nowrap;
}

/* Left-to-right slide */
@keyframes slide-right {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(100vw);
    }
    100% {
        transform: translateX(-100%);
    }
}
</style>

</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="content">
    
<div class="message-container">
    <h2>📢 You can now pay exam through the tracker</h2>
</div>
<div class="dashboard-data">
    <div class="data-container"><h3>Total Students</h3><p><?= $totalStudents ?></p></div>
    <div class="data-container"><h3>Boys : Girls</h3><p><?= $boys . " : " . $girls ?></p></div>
    <div class="data-container"><h3>Total Teachers</h3><p><?= $totalTeachers ?></p></div>
    <div class="data-container"><h3>Male : Female</h3><p><?= $maleTeachers . " : " . $femaleTeachers ?></p></div>
    <div class="data-container"><h3>Total Classes</h3><p><?= $totalClasses ?></p></div>
</div>

<div class="charts-container">
    <canvas id="schoolFeesChart" class="chart"></canvas>
    <canvas id="lunchFeesChart" class="chart"></canvas>
    <canvas id="graduationChart" class="chart"></canvas>
    <canvas id="otherFeesChart" class="chart"></canvas>
    <canvas id="booksUniformChart" class="chart"></canvas>
    <canvas id="totalGrossChart" class="chart"></canvas>
</div>
</main>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const weekLabels = [<?php for($i=1;$i<=$weekNumber;$i++){ echo "'Week $i'"; if($i!=$weekNumber) echo ",";} ?>];

    const schoolFees = <?= json_encode($schoolFeesWeeks) ?>;
    const lunchFees = <?= json_encode($lunchFeesWeeks) ?>;
    const graduationFees = <?= json_encode($graduationWeeks) ?>;
    const otherFees = <?= json_encode($otherWeeks) ?>;
    const booksUniform = <?= json_encode($booksUniformWeeks) ?>;
    const totalGross = <?= json_encode($totalGrossWeeks) ?>; // Use PHP-calculated total

    function renderChart(ctx, label, data, bgColor) {
        return new Chart(ctx, {
            type: 'bar',
            data: { 
                labels: weekLabels, 
                datasets: [{
                    label,
                    data,
                    backgroundColor: bgColor,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7
                }] 
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { 
                        display: true, 
                        text: label, 
                        font: { size: 24, weight: 'bold' },
                        color: '#000'
                    }
                },
                scales: {
                    x: { 
                        title: { display: true, text: 'Week Number', font: { size: 22, weight: 'bold' }, color: '#000' }, 
                        ticks: { font: { size: 20, weight: 'bold' }, color: '#000' },
                        grid: { color: '#cccccc', lineWidth: 1.2 },
                        border: { color: '#000', width: 2 }
                    },
                    y: { 
                        title: { display: true, text: 'Amount (KES)', font: { size: 22, weight: 'bold' }, color: '#000' }, 
                        ticks: { font: { size: 20, weight: 'bold' }, color: '#000', beginAtZero: true },
                        grid: { color: '#cccccc', lineWidth: 1.2 },
                        border: { color: '#000', width: 2 }
                    }
                }
            }
        });
    }

    // Render all charts
    renderChart(document.getElementById('schoolFeesChart'), 'School Fees', schoolFees, '#4e73df');
    renderChart(document.getElementById('lunchFeesChart'), 'Lunch Fees', lunchFees, '#1cc88a');
    renderChart(document.getElementById('graduationChart'), 'Graduation & Prize Giving', graduationFees, '#f6c23e');
    renderChart(document.getElementById('otherFeesChart'), 'Other Fees', otherFees, '#e74a3b');
    renderChart(document.getElementById('booksUniformChart'), 'Books & Uniform', booksUniform, '#36b9cc');
    renderChart(document.getElementById('totalGrossChart'), 'Total Gross (All Fees)', totalGross, '#8e44ad'); // PHP total
});
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) {
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

    /* ===== Keep dropdown open if current page matches a child link ===== */
    const currentUrl = window.location.pathname.split("/").pop();
    document.querySelectorAll(".dropdown").forEach(drop => {
        const links = drop.querySelectorAll("a");
        links.forEach(link => {
            const linkUrl = link.getAttribute("href");
            if (linkUrl && linkUrl.includes(currentUrl)) {
                drop.classList.add("open");
            }
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
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 30 seconds
    }

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    resetLogoutTimer();
});
</script>
</body>
</html>