<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

// Current year
$currentYear = date("Y");

// Get selected filters
$status_filter = $_GET['status_filter'] ?? "all";
$class_filter  = $_GET['class_filter'] ?? "all";   // ✅ New class filter
$search = trim($_GET['search'] ?? "");

// Fetch all students
$sql = "SELECT admission_no, name, class FROM student_records ORDER BY class, name";
$result = $conn->query($sql);

$students = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admission = $row['admission_no'];
        $status = "Unpaid";
        $amount = 0;

        if (strtolower($row['class']) === "pp2") {
            // ✅ PP2 students → just check if a Graduation record exists
            $check = $conn->prepare("SELECT 1 
                                     FROM others 
                                     WHERE admission_no = ? 
                                       AND fee_type = 'Graduation' 
                                       AND YEAR(payment_date) = ? 
                                     LIMIT 1");
            $check->bind_param("si", $admission, $currentYear);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                $status = "Paid";
                $amount = 0; // ✅ Always 0 for PP2
            }
        } else {
            // ✅ Other classes → check Prize Giving fee
            $check = $conn->prepare("SELECT amount_paid 
                                     FROM others 
                                     WHERE admission_no = ? 
                                       AND fee_type = 'Prize Giving' 
                                       AND YEAR(payment_date) = ?");
            $check->bind_param("si", $admission, $currentYear);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                $payRow = $checkResult->fetch_assoc();
                $status = "Paid";
                $amount = $payRow['amount_paid'];
            }
        }

        // Apply filters (status + search + class)
        $matchesStatus = ($status_filter === "all" || strtolower($status_filter) === strtolower($status));
        $matchesSearch = ($search === "" 
                          || stripos($row['admission_no'], $search) !== false 
                          || stripos($row['name'], $search) !== false);
        $matchesClass  = ($class_filter === "all" || $row['class'] === $class_filter);

        if ($matchesStatus && $matchesSearch && $matchesClass) {
            $students[] = [
                "admission_no" => $admission,
                "name" => $row['name'],
                "class" => $row['class'],
                "amount" => $amount, // ✅ PP2 always 0
                "status" => $status
            ];
        }
    }
}

// Sort: Paid first, then Unpaid
usort($students, function($a, $b) {
    if ($a['status'] == $b['status']) return 0;
    return ($a['status'] == 'Paid') ? -1 : 1;
});

// ✅ Fetch distinct classes for dropdown
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
    <title>:: Kayron | Prize Giving</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">

  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href='https://cdn.boxicons.com/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<style>
    /* ===== Prize Giving Tracker Styling (Blue Theme) ===== */
main.content h2 {
    font-size: 1.6rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #004085;
    padding: 10px 15px;
    background: #e9f2fb;
    border-left: 5px solid #007bff;
    border-radius: 6px;
}
main.content h2 i {
    color: #007bff;
}

/* Filter Form */
.filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}
.filter-form label {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #004085;
}
.filter-form select {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid #007bff;
    font-size: 0.95rem;
    cursor: pointer;
    background: #f8fbff;
    color: #004085;
}
.filter-form input[type="text"] {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid #007bff;
    font-size: 0.95rem;
    outline: none;
    background: #f8fbff;
    color: #004085;
    transition: all 0.3s ease;
}
.filter-form input[type="text"]:focus {
    border-color: #0056b3;
    box-shadow: 0 0 5px rgba(0, 91, 187, 0.4);
}

.filter-form button {
    padding: 6px 14px;
    border: none;
    border-radius: 6px;
    background: #007bff;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}
.filter-form button:hover {
    background: #0056b3;
}


/* Table Styling */
#prizeTable {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
#prizeTable th, #prizeTable td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
    font-size: 0.95rem;
}
#prizeTable th {
    background: #007bff;
    color: #fff;
    font-weight: 600;
}
#prizeTable tr:nth-child(even) {
    background: #f9fcff;
}

/* Status Colors */
.status-paid {
    color: #007bff;
    font-weight: 600;
}
.status-unpaid {
    color: #dc3545;
    font-weight: 600;
}
/* ===== Responsive Design ===== */

/* Tablets (≤ 992px) */
@media (max-width: 992px) {
    .filter-form {
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 8px;
    }
    .filter-form input[type="text"],
    .filter-form select,
    .filter-form button {
        flex: 1 1 auto;
        min-width: 140px;
    }
    main.content h2 {
        font-size: 1.3rem;
        padding: 8px 12px;
    }
}

/* Mobile (≤ 768px) */
@media (max-width: 768px) {
    .dashboard-container {
        flex-direction: column;
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form input[type="text"],
    .filter-form select,
    .filter-form button {
        width: 100%;
    }
    #prizeTable th, #prizeTable td {
        font-size: 0.85rem;
        padding: 6px;
    }
    main.content h2 {
        font-size: 1.2rem;
    }
}

/* Extra Small Mobile (≤ 480px) */
@media (max-width: 480px) {
    main.content h2 {
        font-size: 1rem;
        text-align: center;
        flex-direction: column;
        gap: 5px;
    }
    .filter-form {
        gap: 6px;
    }
    .filter-form label {
        font-size: 0.85rem;
    }
    #prizeTable th, #prizeTable td {
        font-size: 0.8rem;
        padding: 4px;
    }
    #prizeTable {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

</style>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>
<main class="content">
    <h2><i class="fas fa-award"></i> Prize Giving Tracker (<?= $currentYear ?>)</h2>

    <!-- Filter -->
<form method="get" class="filter-form">
    <!-- 🔹 Search box -->
    <input type="text" name="search" placeholder="Search Admission No or Name"
           value="<?= htmlspecialchars($search) ?>" />
    <button type="submit"><i class="bx bx-search"></i> Search</button>

    <!-- 🔹 Status filter -->
    <label for="status_filter"><i class="fas fa-filter"></i> Status:</label>
    <select name="status_filter" id="status_filter" onchange="this.form.submit()">
        <option value="all" <?= $status_filter=="all"?"selected":"" ?>>All</option>
        <option value="Paid" <?= $status_filter=="Paid"?"selected":"" ?>>Paid</option>
        <option value="Unpaid" <?= $status_filter=="Unpaid"?"selected":"" ?>>Unpaid</option>
    </select>

    <!-- 🔹 Class filter -->
    <label for="class_filter"><i class="fas fa-school"></i> Class:</label>
    <select name="class_filter" id="class_filter" onchange="this.form.submit()">
        <option value="all" <?= $class_filter=="all"?"selected":"" ?>>All Classes</option>
        <?php foreach ($classList as $class): ?>
            <option value="<?= htmlspecialchars($class) ?>" 
                <?= $class_filter==$class ? "selected" : "" ?>>
                <?= htmlspecialchars($class) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="button" onclick="printTable()"><i class="bx bx-printer"></i> Print</button>
    <button type="button" onclick="exportTable()"><i class="bx bx-download"></i> Export</button>
</form>



    <!-- Table -->
    <table id="prizeTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Amount Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($students as $s): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($s['admission_no']) ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['class']) ?></td>
                <td><?= number_format($s['amount'],2) ?></td>
                <td class="<?= $s['status']=='Paid' ? 'status-paid' : 'status-unpaid' ?>">
                    <?= $s['status'] ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>

</div>
<?php include '../includes/footer.php'; ?>

<script>
// ✅ Format date like "13th Sept 2025"
function formatDateWithSuffix() {
    const now = new Date();
    const day = now.getDate();
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                        "Jul", "Aug", "Sept", "Oct", "Nov", "Dec"];
    const month = monthNames[now.getMonth()];
    const year = now.getFullYear();

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

// ✅ Print with logo, watermark, date & styled table
function printTable() {
    const logoUrl = "http://localhost/kayron-student-management-/images/school-logo.jpg";
    const today = formatDateWithSuffix();

    const printContents = `
        <div style="text-align:center;">
            <img id="headerLogo" src="${logoUrl}" alt="School Logo" 
                 style="width:80px; height:80px; margin-bottom:8px;">
            <h2 style="margin:5px 0;">Prize Giving Tracker (<?= $currentYear ?>)</h2>
            <p style="margin:0; font-size:14px;">Date: ${today}</p>
        </div>
        ${document.getElementById("prizeTable").outerHTML}
    `;

    const printWindow = window.open('', '', 'height=600,width=900');
    printWindow.document.write('<html><head><title>Prize Giving Tracker</title>');

    // ✅ Print CSS
    printWindow.document.write(`
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                position: relative;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            table th, table td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
                font-size: 0.95rem;
            }
            table th {
                background: #f2f2f2;
            }
            /* ✅ Watermark */
            body::after {
                content: "";
                background: url('${logoUrl}') no-repeat center;
                background-size: 200px;
                opacity: 0.08;
                top: 0;
                left: 0;
                bottom: 0;
                right: 0;
                position: fixed;
                z-index: -1;
            }
        </style>
    `);

    printWindow.document.write('</head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');

    printWindow.document.close();

    // ✅ Ensure logo loads before printing
    printWindow.onload = function() {
        const img = printWindow.document.getElementById("headerLogo");
        if (img) {
            img.onload = () => console.log("Header logo loaded successfully.");
            img.onerror = () => console.error("Header logo failed to load:", logoUrl);
        }
        printWindow.print();
    };
}

// ✅ Export to Excel with full sheet borders + formatted filename
function exportTable() {
    const today = formatDateWithSuffix().replace(/\s+/g, "_"); // e.g. "13th_Sept_2025"
    var table = document.getElementById("prizeTable");
    var wb = XLSX.utils.table_to_book(table, {sheet:"Prize Giving"});
    var ws = wb.Sheets["Prize Giving"];

    // Force borders on every cell
    var range = XLSX.utils.decode_range(ws['!ref']);
    for (var R = range.s.r; R <= range.e.r; ++R) {
        for (var C = range.s.c; C <= range.e.c; ++C) {
            var cell_ref = XLSX.utils.encode_cell({r:R, c:C});
            if(!ws[cell_ref]) ws[cell_ref] = {t:"s", v:""};
            ws[cell_ref].s = { border: {
                top: {style: "thin"},
                bottom: {style: "thin"},
                left: {style: "thin"},
                right: {style: "thin"}
            }};
        }
    }

    // ✅ Save with date in filename
    XLSX.writeFile(wb, `Prize_Giving_Tracker_${today}.xlsx`);
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
</body>
</html>