<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$limit = 30; // number of students per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Add "graduate" to the available classes
$predefined_classes = [
    'babyclass','intermediate','PP1','PP2','grade1','grade2',
    'grade3','grade4','grade5','grade6','graduate'
];

// Filters
$balance_filter = isset($_GET['balance_filter']) && $_GET['balance_filter'] !== '' ? (float)$_GET['balance_filter'] : 0;
$class_filter = isset($_GET['class_filter']) ? $_GET['class_filter'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

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

// Totals
$totals = [
    'school_fees' => 0,
    'lunch' => 0,
    'book_purchases' => 0,
    'uniform_purchases' => 0,
    'total_balance' => 0
];

// --- Get students
if ($class_filter !== '' && in_array($class_filter, $predefined_classes)) {
    if ($class_filter === 'graduate') {
        $query = "SELECT admission_no, name, 'Graduate' AS class, graduation_date FROM graduated_students";
        if ($search_query !== '') {
            $query .= " WHERE admission_no LIKE ? OR name LIKE ?";
        }
        $stmt_students = $conn->prepare($query);
        if ($search_query !== '') {
            $like = "%$search_query%";
            $stmt_students->bind_param("ss", $like, $like);
        }
    } else {
        $query = "SELECT admission_no, name, class, NULL AS graduation_date FROM student_records WHERE class = ?";
        if ($search_query !== '') {
            $query .= " AND (admission_no LIKE ? OR name LIKE ?)";
        }
        $stmt_students = $conn->prepare($query);
        if ($search_query !== '') {
            $like = "%$search_query%";
            $stmt_students->bind_param("sss", $class_filter, $like, $like);
        } else {
            $stmt_students->bind_param("s", $class_filter);
        }
    }
} else {
    $query = "
        SELECT admission_no, name, class, NULL AS graduation_date FROM student_records
        UNION ALL
        SELECT admission_no, name, 'Graduate' AS class, graduation_date FROM graduated_students
    ";
    if ($search_query !== '') {
        $query = "SELECT * FROM ($query) AS students WHERE admission_no LIKE ? OR name LIKE ?";
        $stmt_students = $conn->prepare($query);
        $like = "%$search_query%";
        $stmt_students->bind_param("ss", $like, $like);
    } else {
        $stmt_students = $conn->prepare($query);
    }
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

                $balance_for_day = $per_day_fee - $day_paid;
                if ($balance_for_day > 0) {
                    $current_term_balance += $balance_for_day;
                }
            }
            $days_stmt->close();
        }
        $weeks_res->close();

        // ======== SUBTRACT CARRY FORWARD ========
        $carry_stmt = $conn->prepare("
            SELECT IFNULL(SUM(carry_forward),0) AS carry
            FROM lunch_fees
            WHERE admission_no = ? AND term_id < ?
        ");
        $carry_stmt->bind_param("si", $admission_no, $current_term_id);
        $carry_stmt->execute();
        $carry_forward = 0;
        $carry_stmt->bind_result($carry_forward);
        $carry_stmt->fetch();
        $carry_stmt->close();

        $current_term_balance = max(0, $current_term_balance - $carry_forward);
    }

    // ======== PREVIOUS TERMS LUNCH BALANCE ========
    $prev_balance = 0;

    $terms_stmt = $conn->prepare("SELECT id FROM terms WHERE term_number < ?");
    $terms_stmt->bind_param("i", $current_term_number);
    $terms_stmt->execute();
    $terms_res = $terms_stmt->get_result();

    while ($term_row = $terms_res->fetch_assoc()) {
        $prev_term_id = (int)$term_row['id'];

        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM lunch_fees WHERE admission_no = ? AND term_id = ?");
        $check_stmt->bind_param("si", $admission_no, $prev_term_id);
        $check_stmt->execute();
        $check_stmt->bind_result($has_lunch);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($has_lunch > 0) {
            $pay_stmt = $conn->prepare("
                SELECT week_number, monday, tuesday, wednesday, thursday, friday
                FROM lunch_fees
                WHERE admission_no = ? AND term_id = ?
                ORDER BY week_number ASC
            ");
            $pay_stmt->bind_param("si", $admission_no, $prev_term_id);
            $pay_stmt->execute();
            $pay_result = $pay_stmt->get_result();

            $last_paid_week = 0;
            $last_paid_day_index = -1;
            $last_paid_amount = 0;
            $day_list = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

            while ($row = $pay_result->fetch_assoc()) {
                foreach ($day_list as $i => $day) {
                    $amt = floatval($row[$day]);
                    if ($amt > 0) {
                        $last_paid_week = (int)$row['week_number'];
                        $last_paid_day_index = $i;
                        $last_paid_amount = $amt;
                    }
                }
            }
            $pay_stmt->close();

            $day_stmt = $conn->prepare("
                SELECT d.day_name, w.week_number, d.is_public_holiday, a.status
                FROM days d
                JOIN weeks w ON d.week_id = w.id
                LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
                WHERE w.term_id = ?
                ORDER BY w.week_number ASC, FIELD(d.day_name, 'Monday','Tuesday','Wednesday','Thursday','Friday')
            ");
            $day_stmt->bind_param("si", $admission_no, $prev_term_id);
            $day_stmt->execute();
            $days_result = $day_stmt->get_result();

            $day_index_map = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4];
            $partial_day_checked = false;

            while ($row = $days_result->fetch_assoc()) {
                $week = (int)$row['week_number'];
                $day_name = $row['day_name'];
                $day_index = $day_index_map[$day_name] ?? -1;
                $is_holiday = (int)$row['is_public_holiday'];
                $status = $row['status'] ?? 'Present';

                if ($day_index === -1 || $is_holiday === 1 || $status === 'Absent') continue;

                if (!$partial_day_checked && $week === $last_paid_week && $day_index === $last_paid_day_index) {
                    if ($last_paid_amount < $per_day_fee) {
                        $prev_balance += $per_day_fee - $last_paid_amount;
                    }
                    $partial_day_checked = true;
                    continue;
                }

                if ($week < $last_paid_week || ($week === $last_paid_week && $day_index <= $last_paid_day_index)) {
                    continue;
                }

                $prev_balance += $per_day_fee;
            }

            $day_stmt->close();
        }
    }
    $terms_stmt->close();

    // ======== TOTAL LUNCH BALANCE ========
    $lunch_balance = $prev_balance + $current_term_balance;

    // ======== TOTAL BALANCE ========
    $school_fees_display = $school_fees_balance < 0 ? 0 : $school_fees_balance;
    $total_balance = $school_fees_display + $book_balance + $uniform_balance + $lunch_balance;

    if ($total_balance >= $balance_filter) {
        $students[] = [
            'admission_no' => $admission_no,
            'name' => $student['name'],
            'school_fees' => $school_fees_balance < 0 ? 'Paid' : $school_fees_balance,
            'lunch' => $lunch_balance,
            'book_purchases' => $book_balance,
            'uniform_purchases' => $uniform_balance,
            'total_balance' => $total_balance,
            'class' => $student['class']
        ];

        // Totals: ignore negative school fee balances
        $totals['school_fees'] += $school_fees_display;
        $totals['lunch'] += $lunch_balance;
        $totals['book_purchases'] += $book_balance;
        $totals['uniform_purchases'] += $uniform_balance;
        $totals['total_balance'] += $total_balance;
    }

}

$stmt_students->close();
$conn->close();

// --- PAGINATION ---
$total_students = count($students);
$total_pages = ceil($total_students / $limit);
$students = array_slice($students, $offset, $limit); // apply pagination
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>:: Kayron | Student Balances</title>
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
    text-align: center;
    display: block;
}

.view-container h2 i {
    color: #1cc88a;
    font-size: 2rem;
    vertical-align: middle;
    margin-right: 0.5rem;
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
#filterForm input[type="text"],
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
#filterForm input[type="text"]:focus,
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

/* Pay button styles */
.pay-btn {
    background-color: #1cc88a;
    color: #fff;
    border: none;
    padding: 0.4rem 0.8rem;
    border-radius: 5px;
    cursor: pointer;
    transition: transform 0.2s, background 0.3s;
    font-size: 0.9rem;
}

.pay-btn:hover {
    background-color: #17a673;
    transform: scale(1.05);
}

.pay-btn:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

/* Table styling - only bottom borders */
#balancesTable {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    border: none; /* remove table border */
}

#balancesTable th,
#balancesTable td {
    border: none; /* remove all borders */
    border-bottom: 1px solid #ddd; /* only bottom border */
    padding: 3px 5px;
    text-align: center;
    transition: transform 0.2s;
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
    transform: scale(1.01);
}

/* Responsive table wrapper */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-sizing: border-box;
}
.pagination a {
    margin: 0 4px;
    padding: 6px 12px;
    border: 1px solid #4e73df;
    border-radius: 4px;
    color: #4e73df;
    text-decoration: none;
    transition: background 0.3s;
}
.pagination a:hover {
    background: #4e73df;
    color: white;
}
.pagination a.active {
    background: #1cc88a;
    color: white;
}

/* Responsive tweaks */
@media (max-width: 768px) {
    #sidebar { display: none !important; }
    main.content { margin-left: 0 !important; width: 100% !important; padding: 10px; }
    #balancesTable { display: block; width: 100%; overflow-x: auto; white-space: nowrap; }
    form#filterForm { flex-direction: column !important; gap: 15px !important; }
    form#filterForm label,
    form#filterForm input,
    form#filterForm select,
    form#filterForm button { width: 100% !important; max-width: 100% !important; }
    h2 { text-align: center; }
    form#filterForm button { font-size: 1.5rem; padding: 10px; }
}

@media (max-width: 480px) {
    h2 { font-size: 1.4rem; }
    form#filterForm button { font-size: 1.3rem; padding: 8px; }
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
                <label for="search">Search:</label>
                <input type="text" id="search" name="search" placeholder="Admission No or Name"
                       value="<?= htmlspecialchars($search_query ?? '') ?>" oninput="autoSubmit()">

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
                            <th>#</th> <!-- Row number -->
                            <th>Adm</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Fees</th>
                            <th>Lunch</th>
                            <th>Books</th>
                            <th>Uniform</th>
                            <th>Total Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="10">No records found.</td></tr>
                        <?php else: ?>
                            <?php 
                                $rowNumber = $offset + 1; // Start number based on pagination
                                foreach ($students as $student): 
                            ?>
                                <tr>
                                    <td><?= $rowNumber++; ?></td>
                                    <td><?= htmlspecialchars($student['admission_no']); ?></td>
                                    <td><?= htmlspecialchars($student['name']); ?></td>
                                    <td><?= htmlspecialchars($student['class']); ?></td>
                                    <td><?= $student['school_fees'] === 'Paid' ? 'Paid' : number_format($student['school_fees'], 2); ?></td>
                                    <td><?= $student['lunch'] <= 0 ? 'Paid' : number_format($student['lunch'], 2); ?></td>
                                    <td><?= number_format($student['book_purchases'], 2); ?></td>
                                    <td><?= number_format($student['uniform_purchases'], 2); ?></td>
                                    <td><?= $student['total_balance'] <= 0 ? 'Paid' : number_format($student['total_balance'], 2); ?></td>
                                    <td>
                                        <?php if ($student['total_balance'] > 0): ?>
                                            <form method="GET" action="school-fee-handler.php" class="pay-form">
                                                <input type="hidden" name="admission_no" value="<?= htmlspecialchars($student['admission_no']); ?>">
                                                <input type="hidden" name="student_name" value="<?= htmlspecialchars($student['name']); ?>">
                                                <button type="submit" class="pay-btn">Pay</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="pay-btn" disabled>Paid</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Totals row -->
                            <tr style="font-weight:bold; background:#f0f0f0;">
                                <td colspan="4">Totals</td>
                                <td><?= number_format($totals['school_fees'], 2); ?></td>
                                <td><?= number_format($totals['lunch'], 2); ?></td>
                                <td><?= number_format($totals['book_purchases'], 2); ?></td>
                                <td><?= number_format($totals['uniform_purchases'], 2); ?></td>
                                <td><?= number_format($totals['total_balance'], 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 1rem; text-align:center;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i; ?>&class_filter=<?= urlencode($class_filter); ?>&search=<?= urlencode($search_query); ?>&balance_filter=<?= urlencode($balance_filter); ?>"
                            style="margin: 0 5px; padding: 5px 10px; border: 1px solid #4e73df; border-radius: 5px; text-decoration: none; 
                                    <?= ($i == $page) ? 'background:#4e73df; color:white;' : 'color:#4e73df;' ?>">
                                <?= $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
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
            clockElement.textContent = new Date().toLocaleTimeString();
        }
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ===== Dropdowns: only one open on click =====
    document.querySelectorAll(".dropdown-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const parent = btn.parentElement;
            document.querySelectorAll(".dropdown").forEach(drop => {
                if (drop !== parent) drop.classList.remove("open");
            });
            parent.classList.toggle("open");
        });
    });

    // ===== Keep Dropdown Open Based on Current Page =====
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

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    // ===== Auto logout after 5 minutes inactivity =====
    let logoutTimer;
    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            window.location.href = 'logout.php';
        }, 300000); // 5 minutes
    }
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });
    resetLogoutTimer();
});

// ===== Auto-submit debounce =====
let typingTimer;
const debounceDelay = 1500;
function autoSubmit() {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, debounceDelay);
}

// ===== Print function with heading, full borders, and popup =====
function printBalances() {
    // Clone table
    const table = document.getElementById('balancesTable').cloneNode(true);

    // Remove last column (actions/pay) from header
    const ths = table.querySelectorAll('thead th');
    if (ths.length > 8) ths[ths.length - 1].remove();

    // Remove last column from each row
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.children.length > 8) row.removeChild(row.lastElementChild);
    });

    const heading = `
        <h2 style="text-align:center; color:#1cc88a; font-size:24px; margin-bottom:20px;">
            <i class="bx bxs-wallet"></i> Student Balances
        </h2>`;

    // Calculate centered popup position
    const width = 1000, height = 700;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;

    const newWin = window.open(
        "",
        "PrintWindow",
        `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
    );

    newWin.document.write(`
        <html>
        <head>
            <title>Student Balances</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
                th, td { border: 1px solid #333; padding: 0.6rem 0.8rem; text-align: center; }
                thead { background-color: #4e73df; color: black; }
                tbody tr:nth-child(even) { background-color: #f9f9f9; }
                tbody tr:hover { background-color: #e6f7f1; }
            </style>
        </head>
        <body>
            ${heading}
            ${table.outerHTML}
        </body>
        </html>
    `);

    newWin.document.close();
    newWin.focus();
    newWin.print();

    newWin.onafterprint = function () {
        window.location.href = 'view-balances.php';
    };
}
</script>

</body>
</html>


