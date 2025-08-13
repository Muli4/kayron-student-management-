<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

// Add "graduate" to the available classes
$predefined_classes = [
    'babyclass','intermediate','PP1','PP2','grade1','grade2',
    'grade3','grade4','grade5','grade6','graduate'
];

// Filters
$balance_filter = isset($_GET['balance_filter']) && $_GET['balance_filter'] !== '' ? (float)$_GET['balance_filter'] : 0;
$class_filter = isset($_GET['class_filter']) ? $_GET['class_filter'] : '';

// Get current/latest term
$term_row = $conn->query("SELECT id, term_number, start_date FROM terms ORDER BY term_number DESC LIMIT 1")->fetch_assoc();
$current_term_id = $term_row['id'] ?? 0;
$current_term_number = $term_row['term_number'] ?? 1;
$current_term_start = $term_row['start_date'] ?? null;

// Lunch fee per day
$per_day_fee = 70;

// Day name to index mapping
$day_map = [
    'monday' => 0,
    'tuesday' => 1,
    'wednesday' => 2,
    'thursday' => 3,
    'friday' => 4
];

$today = date('Y-m-d');

// --- Get students
if ($class_filter !== '' && in_array($class_filter, $predefined_classes)) {
    if ($class_filter === 'graduate') {
        $query = "SELECT admission_no, name, 'Graduate' AS class, graduation_date FROM graduated_students";
        $stmt_students = $conn->prepare($query);
    } else {
        $query = "SELECT admission_no, name, class, NULL AS graduation_date FROM student_records WHERE class = ?";
        $stmt_students = $conn->prepare($query);
        $stmt_students->bind_param("s", $class_filter);
    }
} else {
    $query = "
        SELECT admission_no, name, class, NULL AS graduation_date FROM student_records
        UNION ALL
        SELECT admission_no, name, 'Graduate' AS class, graduation_date FROM graduated_students
    ";
    $stmt_students = $conn->prepare($query);
}
$stmt_students->execute();
$result_students = $stmt_students->get_result();

$students = [];

while ($student = $result_students->fetch_assoc()) {
    $admission_no = $student['admission_no'];
    $graduation_date = $student['graduation_date'] ?? null;

    // ======== SCHOOL FEES, BOOKS, UNIFORM ========
    $stmt_school = $conn->prepare("SELECT balance FROM school_fees WHERE admission_no = ?");
    $stmt_school->bind_param("s", $admission_no);
    $stmt_school->execute();
    $school_fees_balance = (float)($stmt_school->get_result()->fetch_assoc()['balance'] ?? 0);
    $stmt_school->close();

    $stmt_books = $conn->prepare("SELECT SUM(balance) AS total FROM book_purchases WHERE admission_no = ?");
    $stmt_books->bind_param("s", $admission_no);
    $stmt_books->execute();
    $book_balance = (float)($stmt_books->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_books->close();

    $stmt_uniform = $conn->prepare("SELECT SUM(balance) AS total FROM uniform_purchases WHERE admission_no = ?");
    $stmt_uniform->bind_param("s", $admission_no);
    $stmt_uniform->execute();
    $uniform_balance = (float)($stmt_uniform->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_uniform->close();

    // ======== CURRENT TERM LUNCH BALANCE ========
    $current_term_balance = 0;

    if ($graduation_date && strtotime($graduation_date) < strtotime($current_term_start)) {
        $current_term_balance = 0;
    } else {
        $weeks_res = $conn->prepare("SELECT id, week_number FROM weeks WHERE term_id = ?");
        $weeks_res->bind_param("i", $current_term_id);
        $weeks_res->execute();
        $weeks_result = $weeks_res->get_result();

        while ($week = $weeks_result->fetch_assoc()) {
            $week_id = $week['id'];
            $week_number = $week['week_number'];

            $days_stmt = $conn->prepare("SELECT id, day_name, is_public_holiday FROM days WHERE week_id = ?");
            $days_stmt->bind_param("i", $week_id);
            $days_stmt->execute();
            $days_result = $days_stmt->get_result();

            while ($day = $days_result->fetch_assoc()) {
                $day_id = $day['id'];
                $day_name = $day['day_name'];
                $is_holiday = (int)$day['is_public_holiday'];

                if ($is_holiday) continue;

                $lower_day = strtolower($day_name);
                $week_offset = $week_number - 1;
                $day_offset = $day_map[$lower_day] ?? null;
                if ($day_offset === null) continue;

                $day_date = date('Y-m-d', strtotime("$current_term_start +{$week_offset} weeks +{$day_offset} days"));
                if ($day_date > $today) continue;

                // Attendance
                $att_stmt = $conn->prepare("
                    SELECT status FROM attendance
                    WHERE admission_no = ? AND term_number = ? AND week_number = ? AND day_id = ?
                ");
                $att_stmt->bind_param("siii", $admission_no, $current_term_number, $week_number, $day_id);
                $att_stmt->execute();
                $att_status = $att_stmt->get_result()->fetch_assoc()['status'] ?? 'Present';
                $att_stmt->close();

                if ($att_status === 'Absent') continue;

                // Lunch paid check
                $lunch_stmt = $conn->prepare("
                    SELECT monday, tuesday, wednesday, thursday, friday
                    FROM lunch_fees
                    WHERE admission_no = ? AND term_id = ? AND week_number = ?
                ");
                $lunch_stmt->bind_param("sii", $admission_no, $current_term_id, $week_number);
                $lunch_stmt->execute();
                $lunch_row = $lunch_stmt->get_result()->fetch_assoc();
                $lunch_stmt->close();

                $day_paid = 0;
                if ($lunch_row) {
                    $day_paid = $lunch_row[$lower_day] ?? 0;
                }

                if ($day_paid <= 0) {
                    $current_term_balance += $per_day_fee;
                }
            }
            $days_stmt->close();
        }
        $weeks_res->close();
    }

    // ======== PREVIOUS TERMS LUNCH BALANCE ========
    $prev_terms_res = $conn->prepare("
        SELECT SUM(balance) as prev_balance
        FROM lunch_fees lf
        JOIN terms t ON lf.term_id = t.id
        WHERE lf.admission_no = ? AND t.term_number < ?
    ");
    $prev_terms_res->bind_param("si", $admission_no, $current_term_number);
    $prev_terms_res->execute();
    $prev_balance = (float)($prev_terms_res->get_result()->fetch_assoc()['prev_balance'] ?? 0);
    $prev_terms_res->close();

    // ======== TOTAL LUNCH BALANCE ========
    $lunch_balance = $prev_balance + $current_term_balance;

    // ======== TOTAL BALANCE ========
    $total_balance = max(0, $school_fees_balance) + $book_balance + $uniform_balance + $lunch_balance;

    if ($total_balance >= $balance_filter) {
        $students[] = [
            'admission_no' => $admission_no,
            'name' => $student['name'],
            'school_fees' => $school_fees_balance <= 0 ? "Paid" : number_format($school_fees_balance, 2),
            'lunch' => $lunch_balance <= 0 ? "Paid" : number_format($lunch_balance, 2),
            'book_purchases' => number_format($book_balance, 2),
            'uniform_purchases' => number_format($uniform_balance, 2),
            'total_balance' => $total_balance <= 0 ? "Paid" : number_format($total_balance, 2),
            'class' => $student['class']
        ];
    }
}

$stmt_students->close();
$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Balances</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="icon" type="image/png" href="../images/school-logo.jpg">
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
                <input type="number" id="balance_filter" name="balance_filter" min="0" step="0.01" 
                       value="<?= htmlspecialchars($balance_filter); ?>" oninput="autoSubmit()">

                <label for="class_filter">Class:</label>
                <select name="class_filter" id="class_filter" onchange="autoSubmit()">
                    <option value="">-- All Classes --</option>
                    <?php foreach ($predefined_classes as $class): ?>
                        <option value="<?= htmlspecialchars($class); ?>" <?= ($class_filter === $class) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($class)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" onclick="printBalances()">
                    <i class='bx bx-printer' style="font-size: 20px;"></i>
                </button>
            </form>

            <div id="balancesTableContainer">
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
                            <th>Total Balance</th>
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

