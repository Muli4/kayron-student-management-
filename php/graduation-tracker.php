<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

// Current year
$currentYear = date("Y");

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? ''); // search input

$query = "
    SELECT 
        sr.admission_no,
        sr.name,
        sr.class,
        COALESCE(o.amount_paid, 0) AS amount_paid,
        CASE 
            WHEN o.id IS NULL OR o.amount_paid = 0 THEN 'Unpaid'
            ELSE 'Paid'
        END AS status
    FROM student_records sr
    LEFT JOIN others o 
        ON sr.admission_no = o.admission_no 
       AND o.fee_type = 'Graduation'
       AND YEAR(o.payment_date) = YEAR(CURDATE())
    WHERE sr.class = 'pp2'
";

// 🔹 Add search filter
if ($search !== '') {
    $searchEscaped = $conn->real_escape_string($search);
    $query .= " AND (sr.admission_no LIKE '%$searchEscaped%' OR sr.name LIKE '%$searchEscaped%')";
}

// 🔹 Apply status filter
if ($status_filter === 'Paid') {
    $query .= " HAVING status = 'Paid'";
} elseif ($status_filter === 'Unpaid') {
    $query .= " HAVING status = 'Unpaid'";
}

$query .= " ORDER BY status = 'Paid' DESC, sr.name ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>:: Kayron | Graduation-Tracker</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href='https://cdn.boxicons.com/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <style>
/* ===== Graduation Tracker Styling (Blue Theme) ===== */

/* Page title */
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

/* Filter dropdown */
.filter-form {
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.filter-form label {
  font-weight: 600;
}
.filter-form select {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #90caf9;
  font-size: 0.95rem;
  cursor: pointer;
  background: #fff;
}

/* Table */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
table th, table td {
  padding: 10px;
  border: 1px solid #ddd;
  text-align: center;
  font-size: 0.95rem;
}
table th {
  background: #1976d2; /* Blue header */
  color: #fff;
  font-weight: 600;
}
table tbody tr:nth-child(even) {
  background: #f4f9ff; /* Subtle blue row */
}
table tbody tr:hover {
  background: #e3f2fd;
}

/* Status */
.status-paid {
  color: #2e7d32; /* Green for Paid */
  font-weight: 600;
}
.status-unpaid {
  color: #c62828; /* Red for Unpaid */
  font-weight: 600;
}
/* Search input */
.filter-form input[type="text"] {
  padding: 6px 12px;
  border: 1px solid #90caf9;
  border-radius: 6px;
  font-size: 0.95rem;
  outline: none;
  transition: all 0.3s ease;
}
.filter-form input[type="text"]:focus {
  border-color: #1976d2;
  box-shadow: 0 0 4px rgba(25, 118, 210, 0.4);
}

/* Search button */
.filter-form button {
  padding: 6px 14px;
  border: none;
  border-radius: 6px;
  background: #1976d2;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}
.filter-form button:hover {
  background: #0d47a1;
}
.filter-form button i {
  font-size: 1rem;
}
/* ===================== Responsive Design ===================== */

/* For tablets and smaller laptops */
@media (max-width: 1024px) {
  .filter-form {
    gap: 8px;
  }
  #gradTable th, #gradTable td {
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
  #gradTable th, #gradTable td {
    font-size: 12px;
    padding: 5px;
  }
  .content h2 {
    font-size: 1.2rem;
    text-align: center;
  }
  #gradTable {
    display: block;
    overflow-x: auto; /* ✅ horizontal scroll if table too wide */
    white-space: nowrap;
  }
}

/* For very small phones */
@media (max-width: 480px) {
  .filter-form {
    gap: 6px;
  }
  #gradTable th, #gradTable td {
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
  <div class="dashboard-conitanier">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">
      <h2><i class="bx bx-graduation"></i> Graduation Tracker (<?= $currentYear ?>)</h2>

      <!-- Filter Form -->
      <form method="get" class="filter-form">
        <!-- Search input + button -->
        <input type="text" name="search" placeholder="Search Admission No or Name"
              value="<?= htmlspecialchars($search) ?>" />
        <button type="submit"><i class="bx bx-search"></i> Search</button>     
        <label for="status">Show:</label>
        <select name="status" id="status" onchange="this.form.submit()">
          <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
          <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
          <option value="Unpaid" <?= $status_filter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        </select>
        <!-- Action Buttons -->
          <button type="submit" onclick="printGraduation()"><i class="bx bx-printer"></i> Print</button>
        <button type="button" onclick="exportTable('gradTable', 'Graduation_Tracker')">
          <i class="bx bx-download"></i> Export
        </button>
 
      </form>


      <!-- Table -->
      <table id="gradTable">
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
        <?php 
        if ($result && $result->num_rows > 0): 
          $sn = 1;
          while ($row = $result->fetch_assoc()): 
        ?>
          <tr>
            <td><?= $sn++ ?></td>
            <td><?= htmlspecialchars($row['admission_no']) ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['class']) ?></td>
            <td><?= number_format($row['amount_paid'], 2) ?></td>
            <td class="<?= $row['status'] === 'Paid' ? 'status-paid' : 'status-unpaid' ?>">
              <?= $row['status'] ?>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="6" style="text-align:center;">No students found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </main>
  </div>
<?php include '../includes/footer.php'; ?>
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
function exportTable(tableID, filename = "Graduation_Tracker") {
    var table = document.getElementById(tableID);

    if (!table) {
        console.error("❌ Table not found!");
        return;
    }

    // Convert HTML table to workbook
    var wb = XLSX.utils.table_to_book(table, {sheet:"Graduation Tracker"});
    var ws = wb.Sheets["Graduation Tracker"];

    // Apply thin borders to every cell
    var range = XLSX.utils.decode_range(ws['!ref']);
    for (var R = range.s.r; R <= range.e.r; ++R) {
        for (var C = range.s.c; C <= range.e.c; ++C) {
            var cellRef = XLSX.utils.encode_cell({r:R, c:C});
            if(!ws[cellRef]) ws[cellRef] = {t:"s", v:""};
            ws[cellRef].s = {
                border: {
                    top: {style: "thin"},
                    bottom: {style: "thin"},
                    left: {style: "thin"},
                    right: {style: "thin"}
                }
            };
        }
    }

    // Save file as .xlsx
    XLSX.writeFile(wb, filename + ".xlsx");
}
</script>
<script>
function printGraduation() {
    const table = document.getElementById("gradTable");

    if (!table) {
        console.error("❌ Table not found!");
        return;
    }

    const today = new Date();
    const dateStr = formatDateWithSuffix(today);

    const logoUrl = "http://localhost/kayron-student-management-/images/school-logo.jpg";

    // Debug image
    const testImg = new Image();
    testImg.onload = () => console.log("✅ Logo loaded:", logoUrl);
    testImg.onerror = () => console.error("❌ Logo failed to load:", logoUrl);
    testImg.src = logoUrl;

    const printWindow = window.open('', '', 'height=800,width=1000');

    printWindow.document.write('<html><head><title>Graduation Tracker</title>');
    printWindow.document.write(`
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                position: relative;
                margin: 0;
                padding: 20px;
            }

            /* ✅ Watermark: visible behind everything */
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                opacity: 0.1;
                z-index: 0;
                pointer-events: none;
                user-select: none;
            }

            .watermark img {
                width: 400px;
                height: auto;
            }

            /* ✅ Header content */
            .header {
                margin-bottom: 20px;
                position: relative;
                z-index: 2;
            }

            .header img {
                width: 80px;
                height: 80px;
            }

            h2 {
                margin: 5px 0;
            }

            /* ✅ Table */
            table {
                width: 100%;
                margin-top: 15px;
                border-collapse: collapse;
                position: relative;
                z-index: 2;
                background-color: transparent; /* ✅ Transparent background */
            }

            th, td {
                border: 1px solid black;
                padding: 6px;
                text-align: left;
                background-color: transparent; /* ✅ Transparent cell backgrounds */
            }

            th {
                font-weight: bold;
            }
        </style>
    `);
    printWindow.document.write('</head><body>');

    // ✅ Watermark
    printWindow.document.write(`
        <div class="watermark">
            <img src="${logoUrl}" alt="Watermark">
        </div>
    `);

    // ✅ Header
    printWindow.document.write(`
        <div class="header">
            <img src="${logoUrl}" alt="School Logo"><br>
            <h2>Graduation Tracker</h2>
            <p>Date: ${dateStr}</p>
        </div>
    `);

    // ✅ Table
    printWindow.document.write(table.outerHTML);

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => {
        console.log("🖨️ Print window loaded. Printing...");
        printWindow.print();
    };
}

// ✅ Helper: format "6th Sept 2025"
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
</script>

</body>
</html>