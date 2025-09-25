<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$selected_class = $_GET['class'] ?? '';
$students = [];

// Fetch students from active and graduated tables
if ($selected_class) {
    if ($selected_class === 'graduate') {
        $stmt = $conn->prepare("SELECT name, admission_no FROM graduated_students ORDER BY name ASC");
    } else {
        $stmt = $conn->prepare("SELECT name, admission_no FROM student_records WHERE class=? ORDER BY name ASC");
        $stmt->bind_param("s", $selected_class);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// All classes + graduate for dropdown
$classes = ['babyclass','intermediate','pp1','pp2','grade1','grade2','grade3','grade4','grade5','grade6','graduate'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Students by Class</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="icon" type="image/png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <style>
        /* ===== View Students by Class (Blue Theme) ===== */

/* Page heading */
main.content h2 {
  font-size: 1.6rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0d47a1; /* deep blue */
  padding: 10px 15px;
  background: #e3f2fd; /* light blue bg */
  border-left: 5px solid #1976d2; /* mid blue */
  border-radius: 6px;
}
main.content h2 i {
  color: #1976d2;
}

/* Form (class selection + buttons) */
#classForm {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
#classForm label {
  font-weight: 600;
  color: #0d47a1;
}
#classForm select {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #90caf9;
  font-size: 0.95rem;
  cursor: pointer;
  background: #fff;
}
#classForm button {
  padding: 8px 15px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s ease;
}
#classForm button i {
  font-size: 1rem;
}
#classForm button:first-of-type {
  background: #1565c0; /* dark blue */
  color: #fff;
}
#classForm button:first-of-type:hover {
  background: #0d47a1;
}
#classForm button:last-of-type {
  background: #1976d2; /* medium blue */
  color: #fff;
}
#classForm button:last-of-type:hover {
  background: #0d47a1;
}

/* Sub-heading */
main.content h3 {
  margin-top: 10px;
  font-size: 1.3rem;
  color: #0d47a1;
}

/* Table */
.table-responsive {
  overflow-x: auto;
}
#studentsTable {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  border-radius: 6px;
  overflow: hidden;
}
#studentsTable th,
#studentsTable td {
  padding: 10px;
  border: 1px solid #ddd;
  text-align: center;
  font-size: 0.95rem;
}
#studentsTable th {
  background: #1976d2;
  color: #fff;
  font-weight: 600;
}
#studentsTable tr:nth-child(even) {
  background: #f4f9ff; /* subtle light blue */
}
#studentsTable tr:hover {
  background: #e3f2fd;
}

/* Message for empty class */
main.content p {
  margin-top: 15px;
  font-style: italic;
  color: #777;
}
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">
        <h2><i class="bx bx-group"></i> View Students by Class</h2>

        <!-- Class Selection + Print/Export always visible -->
        <form method="get" action="" id="classForm" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
            <label for="class">Select Class:</label>
            <select name="class" id="class" onchange="this.form.submit()">
                <option value="">--Select Class--</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?= htmlspecialchars($cls) ?>" <?= $cls==$selected_class?'selected':'' ?>>
                        <?= ucfirst($cls) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Buttons always visible -->
            <button type="button" onclick="printStudents()">
                <i class='bx bx-printer'></i> Print
            </button>
            <button type="button" onclick="exportToExcel()">
                <i class='bx bx-file'></i> Export Excel
            </button>
        </form>

        <!-- Students Table -->
        <?php if ($selected_class): ?>
            <h3>Students in <?= ucfirst($selected_class) ?></h3>
            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table id="studentsTable" border="1">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Admission No</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $stu): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($stu['name']) ?></td>
                                    <td><?= htmlspecialchars($stu['admission_no']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No students found in this class.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<?php include '../includes/footer.php'; ?>

<script>
function printStudents() {
    const table = document.getElementById('studentsTable');
    if (!table) return alert('No table to print.');

    const today = new Date();
    const dateStr = formatDateWithSuffix(today);

    const logoUrl = "../images/school-logo.jpg"; // Adjust path if needed

    const printWindow = window.open('', '', 'height=900,width=1200');

    printWindow.document.write('<html><head><title>Students</title>');
    printWindow.document.write(`
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                position: relative;
                text-align: center;
            }

            /* Watermark */
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                opacity: 0.08;
                z-index: 0;
                pointer-events: none;
                user-select: none;
            }
            .watermark img { width: 400px; height: auto; }

            /* Header */
            .header {
                margin-bottom: 20px;
                position: relative;
                z-index: 2;
            }
            .header img { width: 80px; height: 80px; }
            h2 { margin: 5px 0; }
            p { margin: 2px 0; font-size: 0.95rem; }

            /* Table */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                position: relative;
                z-index: 2;
            }
            th, td {
                border: 1px solid #333;
                padding: 8px;
                text-align: center;
                background-color: transparent;
            }
            th { font-weight: bold; }
        </style>
    `);
    printWindow.document.write('</head><body>');

    // Watermark
    printWindow.document.write(`
        <div class="watermark">
            <img src="${logoUrl}" alt="Watermark">
        </div>
    `);

    // Header
    printWindow.document.write(`
        <div class="header">
            <img src="${logoUrl}" alt="School Logo"><br>
            <h2>Students in <?= ucfirst($selected_class) ?></h2>
            <p>Date: ${dateStr}</p>
        </div>
    `);

    // Table
    printWindow.document.write(table.outerHTML);

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => printWindow.print();
}

// Format date like "6th Sept 2025"
function formatDateWithSuffix(date) {
    const day = date.getDate();
    const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sept","Oct","Nov","Dec"];
    const month = monthNames[date.getMonth()];
    const year = date.getFullYear();

    function getOrdinal(n) {
        if (n > 3 && n < 21) return 'th';
        switch(n % 10){
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }

    return `${day}${getOrdinal(day)} ${month} ${year}`;
}

function exportToExcel() {
    const table = document.getElementById('studentsTable');
    if (!table) return alert('No table to export.');

    const wb = XLSX.utils.table_to_book(table, {sheet:"Students"});
    const className = "<?= $selected_class ? ucfirst($selected_class) : 'Students' ?>";
    const filename = className + " students.xlsx";
    XLSX.writeFile(wb, filename);
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