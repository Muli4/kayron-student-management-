<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

require '../php/db.php';

// Find admission numbers & dates with duplicates
$duplicates_sql = "
    SELECT admission_no, DATE(payment_date) AS pay_date, COUNT(*) AS times_paid
    FROM lunch_fee_transactions
    GROUP BY admission_no, DATE(payment_date)
    HAVING COUNT(*) > 1
";

$duplicates = $conn->query($duplicates_sql);

$dup_conditions = [];
if ($duplicates && $duplicates->num_rows > 0) {
    while ($d = $duplicates->fetch_assoc()) {
        $admission = $conn->real_escape_string($d['admission_no']);
        $date      = $conn->real_escape_string($d['pay_date']);
        $dup_conditions[] = "(admission_no = '$admission' AND DATE(payment_date) = '$date')";
    }
}

$where_clause = !empty($dup_conditions) ? implode(" OR ", $dup_conditions) : "1=0";

// Now fetch all the duplicate payment rows
$sql = "
    SELECT id, receipt_number, admission_no, name, class, amount_paid, payment_type, payment_date
    FROM lunch_fee_transactions
    WHERE $where_clause
    ORDER BY admission_no, payment_date ASC
";

$result = $conn->query($sql);

// === Assign DISTINCT colors per admission number ===
$colors = [
    "#FF9999", // red
    "#99CCFF", // blue
    "#99FF99", // green
    "#FFD966", // yellow
    "#FFB366", // orange
    "#C299FF", // purple
    "#FF66B2", // pink
    "#66FFCC", // teal
    "#A6A6A6", // gray
    "#FFCC99"  // peach
];
$student_colors = [];
$color_index = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Duplicate Lunch Fee Payments</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <style>
    h1 {
      font-size: 22px;
      margin-bottom: 20px;
      color: #2c3e50;
      display: inline-block;
    }
    .btn-export {
      float: right;
      background: #27ae60;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
    }
    .btn-export:hover {
      background: #219150;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #2c3e50;
      color: white;
    }
    tr:nth-child(even) {
      background: #f9f9f9;
    }
  </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        <main class="content">
            <h1>Duplicate Lunch Fee Payments (Same Day)</h1>
            <button class="btn-export" onclick="exportTable('duplicatesTable', 'Duplicate_Lunch_Fees')">
                Export to Excel
            </button>

            <table id="duplicatesTable">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Admission No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Amount Paid</th>
                        <th>Payment Type</th>
                        <th>Payment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $admission = $row['admission_no'];
                                if (!isset($student_colors[$admission])) {
                                    $student_colors[$admission] = $colors[$color_index % count($colors)];
                                    $color_index++;
                                }
                                $bg_color = $student_colors[$admission];
                            ?>
                            <tr style="background-color: <?= $bg_color ?>;">
                                <td><?= htmlspecialchars($row['receipt_number']) ?></td>
                                <td><?= htmlspecialchars($row['admission_no']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['class']) ?></td>
                                <td><?= htmlspecialchars($row['amount_paid']) ?></td>
                                <td><?= htmlspecialchars($row['payment_type']) ?></td>
                                <td><?= htmlspecialchars($row['payment_date']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">✅ No duplicate payments found.</td></tr>
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
<script>
function exportTable(tableID, filename = "Duplicate_Lunch_Fees") {
    var table = document.getElementById(tableID);

    if (!table) {
        console.error("❌ Table not found!");
        return;
    }

    // Convert HTML table → Workbook
    var wb = XLSX.utils.table_to_book(table, {sheet:"Duplicate Lunch Fees"});
    var ws = wb.Sheets["Duplicate Lunch Fees"];

    // Get table rows to map colors
    var rows = table.getElementsByTagName("tr");

    // Apply borders + background color row by row
    var range = XLSX.utils.decode_range(ws['!ref']);
    for (var R = range.s.r; R <= range.e.r; ++R) {
        for (var C = range.s.c; C <= range.e.c; ++C) {
            var cellRef = XLSX.utils.encode_cell({r:R, c:C});
            if (!ws[cellRef]) ws[cellRef] = {t:"s", v:""};

            // Default thin border
            ws[cellRef].s = {
                border: {
                    top: {style: "thin"},
                    bottom: {style: "thin"},
                    left: {style: "thin"},
                    right: {style: "thin"}
                }
            };

            // Try to grab the row's background color
            if (rows[R] && rows[R].style && rows[R].style.backgroundColor) {
                let color = rows[R].style.backgroundColor;

                // Convert RGB → HEX if needed
                function rgbToHex(rgb) {
                    let result = /^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/.exec(rgb);
                    return result ? (
                        "#" +
                        ("0" + parseInt(result[1],10).toString(16)).slice(-2) +
                        ("0" + parseInt(result[2],10).toString(16)).slice(-2) +
                        ("0" + parseInt(result[3],10).toString(16)).slice(-2)
                    ) : rgb;
                }

                let hexColor = rgbToHex(color);

                // Add fill color to Excel cell
                ws[cellRef].s.fill = {
                    patternType: "solid",
                    fgColor: { rgb: hexColor.replace("#","").toUpperCase() }
                };
            }
        }
    }

    // Export Excel file
    XLSX.writeFile(wb, filename + ".xlsx");
}
</script>
</body>
</html>
