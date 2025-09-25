<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

require '../php/db.php';

// Fixed Prize Giving amount
$prize_amount = 500;
$payment_type = 'Cash'; // Can be changed if needed

// --- Fetch active term ---
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT term_number FROM terms WHERE start_date <= ? AND end_date >= ? LIMIT 1");
$stmt->bind_param("ss", $today, $today);
$stmt->execute();
$current_term_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_term_result) {
    die("<script>alert('❌ No active term found.'); window.history.back();</script>");
}

$current_term_enum = "term" . $current_term_result['term_number'];

// --- Only process if form is submitted ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['admissions'])) {
    $admissions = $_POST['admissions'];

    foreach ($admissions as $admission_no) {
        $admission_no = trim($admission_no);
        if ($admission_no === '') continue;

        // Fetch student name
        $stmt = $conn->prepare("SELECT name AS full_name FROM student_records WHERE admission_no=? LIMIT 1");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) continue; // skip if student not found
        $name = $student['full_name'];

        // Check if Prize Giving record exists for current term
        $stmt = $conn->prepare("SELECT id, amount_paid FROM others 
                                WHERE admission_no=? AND fee_type='Prize Giving' AND term=? LIMIT 1");
        $stmt->bind_param("ss", $admission_no, $current_term_enum);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Update existing record (max 500)
            $new_paid = $existing['amount_paid'] + $prize_amount;
            if ($new_paid > $prize_amount) $new_paid = $prize_amount;

            $stmt = $conn->prepare("UPDATE others 
                                    SET amount_paid=?, payment_type=?, payment_date=NOW() 
                                    WHERE id=?");
            $stmt->bind_param("dsi", $new_paid, $payment_type, $existing['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert new record with custom receipt
            $random_str = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4);
            $receipt_number = "KSR-T{$current_term_result['term_number']}-{$random_str}";

            $stmt = $conn->prepare("INSERT INTO others 
                (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, payment_type) 
                VALUES (?, ?, ?, ?, 'Prize Giving', ?, ?, ?)");
            $stmt->bind_param("ssssdss", $receipt_number, $admission_no, $name, $current_term_enum, $prize_amount, $prize_amount, $payment_type);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<script>alert('✅ Prize Giving fee applied to selected children.'); window.location.href='prize_giving.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prize Giving Fee</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    /* ===== HEADER ===== */
.content h1 {
    font-size: 2rem;
    margin-bottom: 15px;
    color: #1cc88a;
    font-weight: bold;
}

/* ===== BUTTONS ===== */
.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    margin: 0 3px;
}

.btn-add {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    float: right;
    margin-top: -35px; /* aligns with header */
    margin-bottom: 15px;
}

.btn:hover {
    opacity: 0.85;
}
/* Highlighted row style */
tr.selected {
    background-color: rgba(28, 200, 138, 0.2); /* light green */
}

/* ===== TABLE STYLING ===== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 0.95rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-radius: 8px;
    overflow: hidden;
}

table thead {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: #fff;
    font-weight: bold;
}

table th, table td {
    padding: 10px 12px;
    text-align: center;
    border-bottom: 1px solid #e0e0e0;
}

table tr:hover {
    background: rgba(30, 100, 150, 0.1);
}

/* ===== FORM ELEMENTS ===== */
form p {
    margin-bottom: 10px;
    font-weight: bold;
}

input[type="checkbox"] {
    transform: scale(1.2);
    cursor: pointer;
}

/* ===== RESPONSIVE ===== */
@media(max-width: 768px){
    .btn-add {
        float: none;
        display: block;
        width: 100%;
        margin: 10px 0;
    }
    table th, table td { font-size: 0.85rem; padding: 6px 8px; }
    .btn { font-size: 0.85rem; padding: 5px 10px; }
}

  </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../includes/admin-sidebar.php'; ?>
    <main class="content">
        <h1>Prize Giving Fee Payment</h1>
        <form method="post" action="">
            <p>Select students to apply the Prize Giving fee (500 each):</p>
            <table>
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $students = $conn->query("SELECT admission_no, name, class FROM student_records ORDER BY name ASC");
                    while ($s = $students->fetch_assoc()):
                    ?>
                    <tr>
                        <td><input type="checkbox" name="admissions[]" value="<?= htmlspecialchars($s['admission_no']) ?>"></td>
                        <td><?= htmlspecialchars($s['admission_no']) ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['class']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <br>
            <button type="submit" class="btn btn-add">Apply Prize Giving Fee</button>
        </form>
    </main>
</div>
<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name="admissions[]"]');

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const row = cb.closest('tr');
            if (cb.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });
    });
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

        /* ===== Auto logout after 5 mins inactivity ===== */
        let logoutTimer;
        function resetLogoutTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                window.location.href = '../php/logout.php';
            }, 300000); // 5 minutes
        }
        ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetLogoutTimer);
        });
        resetLogoutTimer();
    });
</script>
</body>
</html>
