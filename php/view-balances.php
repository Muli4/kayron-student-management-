<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$predefined_classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];

// Filters
$balance_filter = isset($_GET['balance_filter']) && $_GET['balance_filter'] !== '' ? (float)$_GET['balance_filter'] : 0;
$class_filter = isset($_GET['class_filter']) ? $_GET['class_filter'] : '';

// Get students
$query = "SELECT admission_no, name, class FROM student_records";
if ($class_filter !== '' && in_array($class_filter, $predefined_classes)) {
    $query .= " WHERE class = ?";
    $stmt_students = $conn->prepare($query);
    $stmt_students->bind_param("s", $class_filter);
} else {
    $stmt_students = $conn->prepare($query);
}
$stmt_students->execute();
$result_students = $stmt_students->get_result();

$students = [];

// Fee constants
$admission_fee = 1000;
$activity_fee = 100;
$exam_fee = 50 * 2; // 2 exams per term = 100
$interview_fee = 200;

$term_map = ['term1' => 1, 'term2' => 2, 'term3' => 3];

// Get current term info for fee calculations
$term_res = $conn->query("SELECT id, term_number FROM terms ORDER BY term_number DESC LIMIT 1");
$current_term = $term_res->fetch_assoc();
$current_term_id = $current_term['id'] ?? 0;
$current_term_number = $current_term['term_number'] ?? 'term1';
$current_term_num = $term_map[$current_term_number] ?? 1;

while ($student = $result_students->fetch_assoc()) {
    $admission_no = $student['admission_no'];

    // School fees balance
    $stmt_school = $conn->prepare("SELECT balance FROM school_fees WHERE admission_no = ?");
    $stmt_school->bind_param("s", $admission_no);
    $stmt_school->execute();
    $school_fees_balance = (float)($stmt_school->get_result()->fetch_assoc()['balance'] ?? 0);
    $stmt_school->close();

    // Book purchases balance
    $stmt_books = $conn->prepare("SELECT SUM(balance) AS total FROM book_purchases WHERE admission_no = ?");
    $stmt_books->bind_param("s", $admission_no);
    $stmt_books->execute();
    $book_balance = (float)($stmt_books->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_books->close();

    // Uniform purchases balance
    $stmt_uniform = $conn->prepare("SELECT SUM(balance) AS total FROM uniform_purchases WHERE admission_no = ?");
    $stmt_uniform->bind_param("s", $admission_no);
    $stmt_uniform->execute();
    $uniform_balance = (float)($stmt_uniform->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_uniform->close();

    // -----------------------
    // Lunch fees calculation
    // -----------------------

    // 1. Get latest term by term_number descending
    $term_res2 = $conn->query("SELECT id, term_number FROM terms ORDER BY term_number DESC LIMIT 1");
    $current_term2 = $term_res2->fetch_assoc();
    $current_term_id2 = $current_term2['id'] ?? 0;
    $current_term_number2 = $current_term2['term_number'] ?? 'term1';

    // 2. Get latest week number for current term
    $week_res = $conn->prepare("SELECT MAX(week_number) AS max_week FROM weeks WHERE term_id = ?");
    $week_res->bind_param("i", $current_term_id2);
    $week_res->execute();
    $max_week = $week_res->get_result()->fetch_assoc()['max_week'] ?? 0;
    $week_res->close();

    // 3. Get week id for current term and latest week number
    $stmt_week = $conn->prepare("SELECT id FROM weeks WHERE term_id = ? AND week_number = ?");
    $stmt_week->bind_param("ii", $current_term_id2, $max_week);
    $stmt_week->execute();
    $week_row = $stmt_week->get_result()->fetch_assoc();
    $week_id = $week_row['id'] ?? 0;
    $stmt_week->close();

    // 4. Get days of latest week (ordered)
    $days_res = [];
    if ($week_id) {
        $days_stmt = $conn->prepare("SELECT day_name FROM days WHERE week_id = ? ORDER BY id");
        $days_stmt->bind_param("i", $week_id);
        $days_stmt->execute();
        $days_result = $days_stmt->get_result();
        while ($d = $days_result->fetch_assoc()) {
            $days_res[] = $d['day_name'];
        }
        $days_stmt->close();
    }

    // 5. Calculate number of days passed this week (assume all days counted)
    $days_passed = count($days_res);

    // 6. Fee per day
    $per_day_fee = 70;

    // 7. Calculate expected lunch fee up to current term/week/day:
    // weeks before current * 5 days/week * fee + days_passed * fee
    $expected_current_term = ($max_week - 1) * 5 * $per_day_fee + $days_passed * $per_day_fee;

    // 8. Sum balance from previous terms lunch fees
    $prev_terms_res = $conn->prepare("
        SELECT SUM(balance) as prev_balance
        FROM lunch_fees lf
        JOIN terms t ON lf.term_id = t.id
        WHERE lf.admission_no = ? AND t.term_number < ?
    ");
    $prev_terms_res->bind_param("ss", $admission_no, $current_term_number2);
    $prev_terms_res->execute();
    $prev_balance = (float)($prev_terms_res->get_result()->fetch_assoc()['prev_balance'] ?? 0);
    $prev_terms_res->close();

    // 9. Get lunch fees record for current term
    $current_term_lunch_res = $conn->prepare("
        SELECT total_paid, balance FROM lunch_fees WHERE admission_no = ? AND term_id = ?
    ");
    $current_term_lunch_res->bind_param("si", $admission_no, $current_term_id2);
    $current_term_lunch_res->execute();
    $lunch_row = $current_term_lunch_res->get_result()->fetch_assoc();
    $current_term_lunch_res->close();

    // 10. Calculate current term lunch balance
    if ($lunch_row) {
        $current_term_balance = max(0, (float)$lunch_row['balance']);
    } else {
        // no payment record => assume full expected lunch fee owed
        $current_term_balance = $expected_current_term;
    }

    // 11. Total lunch balance
    $lunch_balance = max(0, $prev_balance) + $current_term_balance;

    // -----------------------
    // End lunch fees calculation
    // -----------------------

    // Get current term name for fee filtering (e.g., 'term1', 'term2', etc.)
    $term_query = $conn->query("SELECT term_number FROM terms ORDER BY term_number DESC LIMIT 1");
    $current_term_data = $term_query->fetch_assoc();
    $current_term_number = $current_term_data['term_number'] ?? 1;

    // Term enum mapping
    $term_enum = "term{$current_term_number}";

    // === Admission Fee ===
    $admission_balance = 1000;
    $admission_stmt = $conn->prepare("SELECT SUM(amount) as total FROM others WHERE admission_no = ? AND fee_type = 'Admission'");
    $admission_stmt->bind_param("s", $admission_no);
    $admission_stmt->execute();
    $admission_paid = (float)($admission_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $admission_stmt->close();
    $admission_display = $admission_paid >= 1000 ? "Paid" : number_format(1000 - $admission_paid, 2);

    // === Interview Fee ===
    $interview_balance = 200;
    $interview_stmt = $conn->prepare("SELECT SUM(amount) as total FROM others WHERE admission_no = ? AND fee_type = 'Interview'");
    $interview_stmt->bind_param("s", $admission_no);
    $interview_stmt->execute();
    $interview_paid = (float)($interview_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $interview_stmt->close();
    $interview_display = $interview_paid >= 200 ? "Paid" : number_format(200 - $interview_paid, 2);

    // === Activity Fee (only if current term is term2) ===
    if ($term_enum === 'term2') {
        $activity_stmt = $conn->prepare("SELECT SUM(amount) as total FROM others WHERE admission_no = ? AND fee_type = 'Activity' AND term = ?");
        $activity_stmt->bind_param("ss", $admission_no, $term_enum);
        $activity_stmt->execute();
        $activity_paid = (float)($activity_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $activity_stmt->close();
        $activity_display = $activity_paid >= 100 ? "Paid" : number_format(100 - $activity_paid, 2);
    } else {
        $activity_display = "N/A";
    }

    // === Exam Fee (2 exams per term @ 50 each = 100 per term) ===
    $exam_stmt = $conn->prepare("SELECT SUM(amount) as total FROM others WHERE admission_no = ? AND fee_type = 'Exam' AND term = ?");
    $exam_stmt->bind_param("ss", $admission_no, $term_enum);
    $exam_stmt->execute();
    $exam_paid = (float)($exam_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $exam_stmt->close();
    $exam_display = $exam_paid >= 100 ? "Paid" : number_format(100 - $exam_paid, 2);

    // === Add to total balance ===
    $admission_balance = $admission_display === "Paid" ? 0 : (float)str_replace(',', '', $admission_display);
    $interview_balance = $interview_display === "Paid" ? 0 : (float)str_replace(',', '', $interview_display);
    $activity_balance = $activity_display === "Paid" || $activity_display === "N/A" ? 0 : (float)str_replace(',', '', $activity_display);
    $exam_balance = $exam_display === "Paid" ? 0 : (float)str_replace(',', '', $exam_display);

    // Format individual balances for display
    $school_fees_display = $school_fees_balance <= 0 ? "Paid" : number_format($school_fees_balance, 2);
    $lunch_display = $lunch_balance <= 0 ? "Paid" : number_format($lunch_balance, 2);
    $admission_display = $admission_balance <= 0 ? "Paid" : number_format($admission_balance, 2);
    $interview_display = $interview_balance <= 0 ? "Paid" : number_format($interview_balance, 2);
    $activity_display = $activity_balance <= 0 ? "Paid" : number_format($activity_balance, 2);
    $exam_display = $exam_balance <= 0 ? "Paid" : number_format($exam_balance, 2);

    // Calculate total balance
    $total_balance = max(0, $school_fees_balance) + $book_balance + $uniform_balance + $lunch_balance
                + $admission_balance + $interview_balance + $activity_balance + $exam_balance;
    $total_display = $total_balance <= 0 ? "Paid" : number_format($total_balance, 2);

    // Add student to the list if balance matches filter
    if ($total_balance >= $balance_filter) {
        $students[] = [
            'admission_no' => $admission_no,
            'name' => $student['name'],
            'school_fees' => $school_fees_display,
            'lunch' => $lunch_display,
            'book_purchases' => number_format($book_balance, 2),
            'uniform_purchases' => number_format($uniform_balance, 2),
            'admission' => $admission_display,
            'activity' => $activity_display,
            'exam' => $exam_display,
            'interview' => $interview_display,
            'total_balance' => $total_display,
            'class' => $student['class']
        ];
    }


}

$stmt_students->close();
$conn->close();

// Now $students contains all data including fees balances ready for your output display.

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Balances</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
<style>
    /* Container for the whole view */
.view-container {
    background: #fff;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 1rem auto;
}

.view-container h2 {
    color: #1cc88a;
    font-weight: 700;
    font-size: 1.8rem;
    margin-bottom: 2rem;
    text-align: center; /* center content inside h2 */
    display: block;     /* block element to take full width */
}

.view-container h2 i {
    color: #1cc88a;
    font-size: 2rem;
    vertical-align: middle; /* align icon with text vertically */
    margin-right: 0.5rem;   /* space between icon and text */
}


/* Filter form styles */
#filterForm {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 1.5rem;
}

#filterForm label {
    font-weight: 600;
    color: #4e73df;
    font-size: 1rem;
    margin-right: 0.3rem;
}

#filterForm input[type="number"],
#filterForm select {
    padding: 0.4rem 0.8rem;
    border: 1.8px solid #4e73df;
    border-radius: 6px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s ease;
    min-width: 120px;
}

#filterForm input[type="number"]:focus,
#filterForm select:focus {
    border-color: #1cc88a;
}

/* Print button with icon */
#filterForm button {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    border: none;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    color: white;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
}

#filterForm button:hover {
    background: linear-gradient(135deg, #1cc88a, #4e73df);
}

#filterForm button i {
    font-size: 1.5rem;
}

/* Table styling */
#balancesTable {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

#balancesTable th,
#balancesTable td {
    border: 1px solid #ddd;
    padding: 0.6rem 0.8rem;
    text-align: center;
}

#balancesTable thead {
    background-color: #4e73df;
    color: white;
}

#balancesTable tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

#balancesTable tbody tr:hover {
    background-color: #e6f7f1;
    cursor: default;
}
.table-responsive {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch; /* for smooth scrolling on iOS */
  border: 1px solid #ccc; /* optional: border to show the container */
  box-sizing: border-box;
}


/* Responsive for smaller screens */
/* General page styles (your existing styles would be here) */

/* Responsive tweaks */
@media (max-width: 768px) {
  /* Hide sidebar on small screens */
  #sidebar {
    display: none !important;
  }

  /* Make main content full width */
  main.content {
    margin-left: 0 !important;
    width: 100% !important;
    padding: 10px;
  }

  /* Table: allow horizontal scroll */
  #balancesTable {
    display: block;
    width: 100%;
    overflow-x: auto;
    white-space: nowrap;
  }

  /* Form controls: stack vertically */
  form#filterForm {
    flex-direction: column !important;
    gap: 15px !important;
  }

  form#filterForm label,
  form#filterForm input,
  form#filterForm select,
  form#filterForm button {
    width: 100% !important;
    max-width: 100% !important;
  }

  /* Center the h2 and icon */
  h2 {
    text-align: center;
  }

  /* Optional: adjust print button size */
  form#filterForm button {
    font-size: 1.5rem;
    padding: 10px;
  }
    .table-responsive {
    width: 100%;
  }
}

@media (max-width: 480px) {
  /* Smaller font sizes on very small screens */
  h2 {
    font-size: 1.4rem;
  }

  form#filterForm button {
    font-size: 1.3rem;
    padding: 8px;
  }
}


</style>
</head>

<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">
        <div class="view-container">
            <h2><i class='bx bxs-wallet'></i> Student Balances</h2>

            <form method="GET" action="" id="filterForm" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <label for="balance_filter">Balance</label>
                <input type="number" id="balance_filter" name="balance_filter" min="0" step="0.01" value="<?= htmlspecialchars($balance_filter); ?>" oninput="autoSubmit()">

                <label for="class_filter">Class:</label>
                <select name="class_filter" id="class_filter" onchange="autoSubmit()">
                    <option value="">-- All Classes --</option>
                    <?php foreach ($predefined_classes as $class): ?>
                        <option value="<?= htmlspecialchars($class); ?>" <?= ($class_filter === $class) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($class)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" onclick="printBalances()"><i class='bx bx-printer' style="font-size: 20px;"></i></button>
            </form>
            <div id="balancesTable">
                            <table id="balancesTable" border="1">
                <thead>
                <tr>
                    <th>Adm</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Fees</th>
                    <th>Lunch</th>
                    <th>Books</th>
                    <th>Uniform</th>
                    <th>Admission</th>
                    <th>Activity</th>
                    <th>Exam</th>
                    <th>Interview</th>
                    <th>Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['admission_no']); ?></td>
                            <td><?= htmlspecialchars($student['name']); ?></td>
                            <td><?= htmlspecialchars($student['class']); ?></td>
                            <td><?= htmlspecialchars($student['school_fees']); ?></td>
                            <td><?= htmlspecialchars($student['lunch'] ?? 'Paid'); ?></td>
                            <td><?= htmlspecialchars($student['book_purchases']); ?></td>
                            <td><?= htmlspecialchars($student['uniform_purchases']); ?></td>
                            <td><?= htmlspecialchars($student['admission']) ?></td>
                            <td><?= htmlspecialchars($student['activity']) ?></td>
                            <td><?= htmlspecialchars($student['exam']) ?></td>
                            <td><?= htmlspecialchars($student['interview']) ?></td>

                            <td><?= htmlspecialchars($student['total_balance']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
</div>
<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // ===== Real-time clock =====
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

    // ===== Dropdowns: only one open =====
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

    // ===== Sidebar toggle for mobile =====
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.toggle-btn');
    const overlay = document.createElement('div');
    overlay.classList.add('sidebar-overlay');
    document.body.appendChild(overlay);

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    // ===== Close sidebar on outside click =====
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    // ===== Auto logout after 30 seconds inactivity (no alert) =====
    let logoutTimer;
    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            window.location.href = 'logout.php'; // Adjust your logout URL here
        }, 300000); // 5 minutes
    }

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    resetLogoutTimer();
});

// ===== Auto-submit debounce and print function =====
let typingTimer;
const debounceDelay = 1500;

function autoSubmit() {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, debounceDelay);
}

function printBalances() {
    const printContent = document.getElementById('balancesTable').outerHTML;
    const newWin = window.open("", "_blank");
    newWin.document.write("<html><head><title>Print Balances</title></head><body>");
    newWin.document.write(printContent);
    newWin.document.write("</body></html>");
    newWin.document.close();
    newWin.print();
}
</script>

</body>
</html>
