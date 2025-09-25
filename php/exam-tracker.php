<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

// Define fee rules
$FEE_RULES = [
    "Admission"    => 1000,
    "Activity"     => 100,
    "Interview"    => 200,
    "Prize Giving" => 500,
    "Graduation"   => 1500,
    "Exam"         => [
        "babyclass"    => 100,
        "intermediate" => 100,
        "pp1"          => 100,
        "pp2"          => 100,
        "grade1"       => 100,
        "grade2"       => 100,
        "grade3"       => 150,
        "grade4"       => 150,
        "grade5"       => 150,
        "grade6"       => 150,
    ]
];

// Handle Exam Payment POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admission_no'])) {
    $admission_no = trim($_POST['admission_no']);
    $amount_paid  = floatval($_POST['amount_paid'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';
    $payment_date = date('Y-m-d');
    $fee_type     = 'Exam';

    if ($admission_no === '' || $amount_paid <= 0) {
        $_SESSION['error'] = "❌ Invalid data submitted.";
        header("Location: exam-tracker.php");
        exit();
    }

    // Get student class and name
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $_SESSION['error'] = "❌ Student not found.";
        header("Location: exam-tracker.php");
        exit();
    }

    $name = $student['name'];
    $class = strtolower(trim($student['class']));
    $year = date('Y');

    // Get fee limit
    $fee_limit = $FEE_RULES['Exam'][$class] ?? 0;
    if ($fee_limit <= 0) {
        $_SESSION['error'] = "❌ Exam fee not set for this class.";
        header("Location: exam-tracker.php");
        exit();
    }

    // Get total paid so far
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_paid 
                            FROM others 
                            WHERE admission_no = ? AND fee_type = 'Exam' AND YEAR(payment_date) = ?");
    $stmt->bind_param("si", $admission_no, $year);
    $stmt->execute();
    $total_paid = floatval($stmt->get_result()->fetch_assoc()['total_paid']);
    $stmt->close();

    $balance = $fee_limit - $total_paid;

    if ($balance <= 0) {
        $_SESSION['error'] = "✅ Exam fee already fully paid.";
        header("Location: exam-tracker.php");
        exit();
    }

    if ($amount_paid > $balance) {
        $_SESSION['error'] = "⚠️ Cannot pay more than the remaining balance (KES " . number_format($balance, 2) . ").";
        header("Location: exam-tracker.php");
        exit();
    }

    // Get current term (for logging)
    $term = 'term1';
    $termRes = $conn->query("SELECT term_number FROM terms ORDER BY start_date DESC LIMIT 1");
    if ($termRes && $row = $termRes->fetch_assoc()) {
        $term = 'term' . $row['term_number'];
    }

    // Generate new receipt format: EF-T{termNumber}-{random4}
    $termNumber = 1;
    $termRes = $conn->query("SELECT term_number FROM terms ORDER BY start_date DESC LIMIT 1");
    if ($termRes && $row = $termRes->fetch_assoc()) {
        $termNumber = (int)$row['term_number'];
    }

    // Generate 4-character random string (alphanumeric)
    $randomPart = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));

    $receipt = 'EF-T' . $termNumber . '-' . $randomPart;


    // Begin transaction
    $conn->begin_transaction();
    try {
        // Check if there’s already an unpaid exam record for this year
        $stmt = $conn->prepare("SELECT id, amount_paid, total_amount 
                                FROM others 
                                WHERE admission_no = ? 
                                AND fee_type = 'Exam' 
                                AND YEAR(payment_date) = ? 
                                ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("si", $admission_no, $year);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Update the existing record instead of inserting a new one
            $newPaid = $existing['amount_paid'] + $amount_paid;
            if ($newPaid > $existing['total_amount']) {
                throw new Exception("Overpayment detected.");
            }

            $stmt = $conn->prepare("UPDATE others 
                                    SET amount_paid = ?, payment_date = ? 
                                    WHERE id = ?");
            $stmt->bind_param("dsi", $newPaid, $payment_date, $existing['id']);
            $stmt->execute();
            $stmt->close();

            $others_id = $existing['id'];
        } else {
            // Insert new record if none exists
            $stmt = $conn->prepare("INSERT INTO others 
                (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, payment_type, payment_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdsss", $receipt, $admission_no, $name, $term, $fee_type, $fee_limit, $amount_paid, $payment_type, $payment_date);
            $stmt->execute();
            $others_id = $stmt->insert_id;
            $stmt->close();
        }

        // Always log into other_transactions
        $stmt = $conn->prepare("INSERT INTO other_transactions 
            (others_id, fee_type, amount_paid, payment_type, receipt_number, status) 
            VALUES (?, ?, ?, ?, ?, 'Completed')");
        $stmt->bind_param("isdss", $others_id, $fee_type, $amount_paid, $payment_type, $receipt);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success'] = "✅ Exam payment recorded successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "❌ Failed to record payment. " . $e->getMessage();
    }


    header("Location: exam-tracker.php");
    exit();
}


// Setup Filters and Year
$currentYear   = date("Y");
$status_filter = $_GET['status_filter'] ?? "all";
$class_filter  = $_GET['class_filter'] ?? "all";
$search        = trim($_GET['search'] ?? "");

// Classes without opener exam
$noOpener = ["babyclass", "intermediate", "pp1", "pp2", "grade1", "grade2"];

// Pagination setup
$perPage = 30;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;

// Count total students
$count_sql = "SELECT COUNT(*) as total FROM student_records s WHERE 1=1";
$count_params = [];
$count_types  = "";

// Filters
if ($class_filter !== "all") {
    $count_sql .= " AND s.class = ?";
    $count_params[] = $class_filter;
    $count_types   .= "s";
}
if ($search !== "") {
    $count_sql .= " AND (s.admission_no LIKE ? OR s.name LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_types   .= "ss";
}

$stmt = $conn->prepare($count_sql);
if ($count_types) $stmt->bind_param($count_types, ...$count_params);
$stmt->execute();
$totalStudents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$totalPages = max(1, ceil($totalStudents / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch student records with exam payment summary
$sql = "SELECT s.admission_no, s.name, s.class,
               COALESCE(SUM(o.amount_paid), 0) as totalPaid
        FROM student_records s
        LEFT JOIN others o 
            ON s.admission_no = o.admission_no
           AND o.fee_type = 'Exam'
           AND YEAR(o.payment_date) = ?
        WHERE 1=1";
$params = [$currentYear];
$types  = "i";

if ($class_filter !== "all") {
    $sql .= " AND s.class = ?";
    $params[] = $class_filter;
    $types   .= "s";
}
if ($search !== "") {
    $sql .= " AND (s.admission_no LIKE ? OR s.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= "ss";
}

$sql .= " GROUP BY s.admission_no, s.name, s.class
          ORDER BY s.class, s.name
          LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $admission    = $row['admission_no'];
    $studentClass = strtolower($row['class']);
    $totalPaid    = (float)$row['totalPaid'];

    // Determine total fee for exam based on class
    $totalFee = $FEE_RULES['Exam'][$studentClass] ?? 0;

    // Calculate fee segments for payment status checks
    $segmentAmount = $totalFee / 3;

    // Determine payment status icons
    if (isset($FEE_RULES['Exam'][$studentClass])) {
        if (in_array($studentClass, $noOpener)) {
            $opener  = "—";
            $midterm = ($totalPaid >= $segmentAmount) ? "✅" : "❌";
            $endterm = ($totalPaid >= $totalFee) ? "✅" : "❌";
        } else {
            $opener  = ($totalPaid >= $segmentAmount) ? "✅" : "❌";
            $midterm = ($totalPaid >= $segmentAmount * 2) ? "✅" : "❌";
            $endterm = ($totalPaid >= $totalFee) ? "✅" : "❌";
        }
    } else {
        $opener = $midterm = $endterm = "❌"; // If no fee rule found
    }

    // Calculate balance
    $balance = max(0, $totalFee - $totalPaid);

    // Determine status
    if ($totalPaid >= $totalFee) {
        $status = "Paid";
    } elseif ($totalPaid > 0) {
        $status = "Partial";
    } else {
        $status = "Unpaid";
    }

    // Apply status filter
    $matchesStatus = ($status_filter === "all" || strtolower($status_filter) === strtolower($status));

    if ($matchesStatus) {
        $students[] = [
            "admission_no" => $admission,
            "name"         => $row['name'],
            "class"        => $row['class'],
            "opener"       => $opener,
            "midterm"      => $midterm,
            "endterm"      => $endterm,
            "balance"      => $balance,
            "status"       => $status
        ];
    }
}
$stmt->close();

// Fetch distinct class list
$classList = [];
$classResult = $conn->query("SELECT DISTINCT class FROM student_records ORDER BY class ASC");
if ($classResult && $classResult->num_rows > 0) {
    while ($row = $classResult->fetch_assoc()) {
        $classList[] = $row['class'];
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>:: Kayron | Exam-Tracker</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://cdn.boxicons.com/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <style>
.content h2 {
  font-size: 1.6rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0d47a1; /* Deep blue */
  padding: 10px 15px;
  background: #e3f2fd; /* Light blue background */
  border-left: 5px solid #1976d2; /* Mid blue */
  border-radius: 6px;
}
.content h2 i {
  color: #1976d2;
}

/* Filters */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.filter-form input[type="text"] {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    min-width: 220px;
}

.filter-form select {
    padding: 6px 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
}

.filter-form button {
    background: #0073e6;
    color: #fff;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    transition: background 0.2s;
}

.filter-form button:hover {
    background: #005bb5;
}

/* Table */
#examTable {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

#examTable th, 
#examTable td {
    border: 1px solid #ddd;
    padding: 8px 10px;
    text-align: center;
    font-size: 14px;
}

#examTable th {
    background: #f4f6f9;
    font-weight: bold;
    color: #333;
}

#examTable tbody tr:nth-child(even) {
    background: #fafafa;
}

#examTable tbody tr:hover {
    background: #f1f7ff;
}

/* Status colors */
td.paid {
    color: green;
    font-weight: bold;
}
td.partial {
    color: #d58512;
    font-weight: bold;
}
td.unpaid {
    color: red;
    font-weight: bold;
}

/* Tick & Cross styling */
#examTable td {
    font-size: 16px;
}

#examTable td:contains("✔") {
    color: green;
    font-weight: bold;
}
#examTable td:contains("x") {
    color: red;
    font-weight: bold;
}
/* ===== ACTION BUTTONS ===== */
.action-btn {
    background: #1976d2;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background 0.2s ease-in-out;
}
.action-btn:hover {
    background: #0d47a1;
}
.action-btn:disabled {
    background: #ccc;
    color: #666;
    cursor: not-allowed;
}

/* Green submit button */
.btn-success {
    background: #28a745;
}
.btn-success:hover {
    background: #218838;
}

/* Red cancel button */
.btn-cancel {
    background: #dc3545;
}
.btn-cancel:hover {
    background: #c82333;
}

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: #fff;
    padding: 20px 25px;
    border-radius: 8px;
    width: 400px;
    max-width: 95%;
    position: relative;
    animation: fadeIn 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.modal-content h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.3rem;
    color: #0d47a1;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}
.modal-content label {
    font-weight: bold;
    display: block;
    margin-top: 10px;
    margin-bottom: 5px;
}
.modal-content input[type="number"] {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.modal-actions {
    margin-top: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Modal animation */
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.9);}
    to   { opacity: 1; transform: scale(1);}
}

/* ===================== Responsive Design ===================== */

/* For tablets and smaller laptops */
@media (max-width: 1024px) {
  .filter-form {
    gap: 8px;
  }
  #examTable th, #examTable td {
    font-size: 13px;
    padding: 6px;
  }
  .content h2 {
    font-size: 1.4rem;
  }
}

/* For mobile devices */
@media (max-width: 768px) {
  .filter-form {
    flex-direction: column;
    align-items: stretch;
  }
  .filter-form input[type="text"], 
  .filter-form select, 
  .filter-form button {
    width: 100%;
  }
  #examTable th, #examTable td {
    font-size: 12px;
    padding: 5px;
  }
  .content h2 {
    font-size: 1.2rem;
    text-align: center;
  }
  #examTable {
    display: block;
    overflow-x: auto; /* horizontal scroll if needed */
    white-space: nowrap;
  }
}

/* For very small phones */
@media (max-width: 480px) {
  .filter-form {
    gap: 6px;
  }
  #examTable th, #examTable td {
    font-size: 11px;
    padding: 4px;
  }
  .content h2 {
    font-size: 1rem;
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
    <h2><i class="bx bx-pen"></i> Exam Tracker (<?= $currentYear ?>)</h2>

    <!-- Filters -->
    <form method="get" class="filter-form">
        <input type="text" name="search" placeholder="Search Admission No or Name"
               value="<?= htmlspecialchars($search) ?>" />
        <button type="submit"><i class="bx bx-search"></i> Search</button>
        

        <label>Status:</label>
        <select name="status_filter" onchange="this.form.submit()">
            <option value="all" <?= $status_filter=="all"?"selected":"" ?>>All</option>
            <option value="Paid" <?= $status_filter=="Paid"?"selected":"" ?>>Paid</option>
            <option value="Partial" <?= $status_filter=="Partial"?"selected":"" ?>>Partial</option>
            <option value="Unpaid" <?= $status_filter=="Unpaid"?"selected":"" ?>>Unpaid</option>
        </select>

        <label>Class:</label>
        <select name="class_filter" onchange="this.form.submit()">
            <option value="all" <?= $class_filter=="all"?"selected":"" ?>>All</option>
            <?php foreach($classList as $class): ?>
                <option value="<?= $class ?>" <?= $class_filter==$class?"selected":"" ?>>
                    <?= $class ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" onclick="printTable()"><i class="bx bx-printer"></i> Print</button>
        <button type="button" onclick="exportTable()"><i class="bx bx-download"></i> Export</button>
    </form>

    <!-- Table -->
    <table id="examTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Opener Exam</th>
                <th>Mid Term</th>
                <th>End Term</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($students as $s): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($s['admission_no']) ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['class']) ?></td>
                <td style="font-size:18px;"><?= $s['opener'] ?></td>
                <td style="font-size:18px;"><?= $s['midterm'] ?></td>
                <td style="font-size:18px;"><?= $s['endterm'] ?></td>
                <td><?= number_format($s['balance'],2) ?></td>
                <td class="<?= strtolower($s['status']) ?>"><?= $s['status'] ?></td>
                <td>
                <?php if ($s['status'] === 'Paid'): ?>
                    <button class="action-btn" disabled>Paid</button>
                <?php else: ?>
                    <button class="action-btn" 
                    onclick="openPaymentModal('<?= $s['admission_no'] ?>', '<?= $s['name'] ?>', <?= $s['balance'] ?>)">
                    <i class="bx bx-money"></i> Pay
                    </button>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Pagination -->
    <div style="margin-top:15px; text-align:center;">
        <?php if ($totalPages > 1): ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&status_filter=<?= urlencode($status_filter) ?>&class_filter=<?= urlencode($class_filter) ?>&search=<?= urlencode($search) ?>"
                style="margin:0 5px; padding:6px 12px; border:1px solid #ccc; border-radius:4px;
                        text-decoration:none; <?= $p == $page ? 'background:#0073e6;color:#fff;' : 'background:#f9f9f9;color:#333;' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3><i class="bx bx-money"></i> Pay Exam Fee</h3>
        <form id="paymentForm" method="post" action="exam-tracker.php">
            <input type="hidden" name="admission_no" id="modalAdmissionNo">
            <p><strong>Name:</strong> <span id="modalName"></span></p>
            <p><strong>Balance:</strong> KES <span id="modalBalance"></span></p>
            
            <label>Amount to Pay:</label>
            <input type="number" name="amount_paid" required min="1" max="150" step="0.01">

            <div class="modal-actions">
                <button type="submit" class="action-btn btn-success">Submit</button>
                <button type="button" class="action-btn btn-cancel" onclick="closePaymentModal()">Cancel</button>
            </div>
        </form>
    </div>
    </div>
</main>

    </div>
<script>
function openPaymentModal(admissionNo, name, balance) {
    document.getElementById('modalAdmissionNo').value = admissionNo;
    document.getElementById('modalName').textContent = name;
    document.getElementById('modalBalance').textContent = balance.toFixed(2);
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}
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
<script>
// ✅ Helper: format date like "13th Sept 2025"
function formatDateWithSuffix(date) {
    const day = date.getDate();
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                        "Jul", "Aug", "Sept", "Oct", "Nov", "Dec"];
    const month = monthNames[date.getMonth()];
    const year = date.getFullYear();

    function getOrdinal(n) {
        if (n > 3 && n < 21) return 'th';
        switch (n % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }
    return `${day}${getOrdinal(day)} ${month} ${year}`;
}

// ✅ Print ANY table
function printTable(tableId = null, title = "Data Report") {
    const logoUrl = "http://localhost/kayron-student-management-/images/school-logo.jpg";
    const today = formatDateWithSuffix(new Date());

    const table = tableId ? document.getElementById(tableId) : document.querySelector("table");
    if (!table) {
        alert("⚠️ No table found to print!");
        return;
    }

    const printContents = `
        <div style="text-align:center;">
            <img src="${logoUrl}" alt="School Logo" 
                 style="width:80px; height:80px; margin-bottom:8px;">
            <h2 style="margin:5px 0;">${title}</h2>
            <p style="margin:0; font-size:14px;">Date: ${today}</p>
        </div>
        ${table.outerHTML}
    `;

    const printWindow = window.open('', '', 'height=650,width=950');
    printWindow.document.write('<html><head><title>' + title + '</title>');
    printWindow.document.write(`
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                position: relative;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
                font-size: 0.9rem;
            }
            th {
                background: #f2f2f2;
            }
            body::after {
                content: "";
                background: url('${logoUrl}') no-repeat center;
                background-size: 200px;
                opacity: 0.08;
                top: 0; left: 0; bottom: 0; right: 0;
                position: fixed;
                z-index: -1;
            }
        </style>
    `);
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// ✅ Export ANY table
function exportTable(tableId = null, filename = "Exported_Data") {
    const table = tableId ? document.getElementById(tableId) : document.querySelector("table");
    if (!table) {
        alert("⚠️ No table found to export!");
        return;
    }

    var wb = XLSX.utils.table_to_book(table, {sheet:"Sheet1"});
    XLSX.writeFile(wb, filename + ".xlsx");
}
</script>

</body>
</html>