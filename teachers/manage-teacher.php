<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Africa/Nairobi');
include '../php/db.php'; // DB connection

// ================== Handle Update ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
    $id              = intval($_POST['id']);
    $name            = trim($_POST['name']);
    $gender          = $_POST['gender'];
    $national_id     = trim($_POST['national_id']);
    $phone           = trim($_POST['phone']);
    $email           = trim($_POST['email']);
    $tsc_no          = trim($_POST['tsc_no']);
    $employment_date = !empty($_POST['employment_date']) ? $_POST['employment_date'] : null;
    $status          = $_POST['status'];

    // Handle photo update
    $teacher_photo = null;
    $update_photo  = false;
    if (!empty($_FILES['teacher_photo']['tmp_name']) && $_FILES['teacher_photo']['error'] === 0) {
        $teacher_photo = file_get_contents($_FILES['teacher_photo']['tmp_name']);
        $update_photo  = true;
    }

    if ($update_photo) {
        $stmt = $conn->prepare("UPDATE teacher_records 
            SET name=?, gender=?, tsc_no=?, national_id=?, phone=?, email=?, employment_date=?, status=?, teacher_photo=? 
            WHERE id=?");
        $stmt->bind_param("sssssssbsi", $name, $gender, $tsc_no, $national_id, $phone, $email, $employment_date, $status, $teacher_photo, $id);
    } else {
        $stmt = $conn->prepare("UPDATE teacher_records 
            SET name=?, gender=?, tsc_no=?, national_id=?, phone=?, email=?, employment_date=?, status=? 
            WHERE id=?");
        $stmt->bind_param("ssssssssi", $name, $gender, $tsc_no, $national_id, $phone, $email, $employment_date, $status, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='message-success'>Teacher updated successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='message-error'>Error updating teacher: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();

    header("Location: manage-teacher.php");
    exit();
}

// ================== Handle Delete ==================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM teacher_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['message'] = "<div class='message-success'>Teacher deleted successfully!</div>";
    header("Location: manage-teacher.php");
    exit();
}

// ================== Fetch Data ==================
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? ''; // active / inactive / ''

$whereClauses = [];
$params = [];
$types  = "";

// 🔎 Search filter
if (!empty($search)) {
    $whereClauses[] = "(name LIKE ? OR national_id LIKE ? OR phone LIKE ? OR code LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
    $types  = "ssss";
}

// ✅ Status filter
if (!empty($filter_status)) {
    $whereClauses[] = "status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

$searchSql = "";
if (!empty($whereClauses)) {
    $searchSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Pagination
$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total
if ($searchSql) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_records $searchSql");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total  = $result->fetch_assoc()['total'];
    $stmt->close();
} else {
    $total = $conn->query("SELECT COUNT(*) AS total FROM teacher_records")->fetch_assoc()['total'];
}
$total_pages = max(1, ceil($total / $limit));

// Fetch data
if ($searchSql) {
    $stmt = $conn->prepare("SELECT * FROM teacher_records $searchSql ORDER BY id DESC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $types   .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $teachers = $stmt->get_result();
} else {
    $teachers = $conn->query("SELECT * FROM teacher_records ORDER BY id DESC LIMIT $limit OFFSET $offset");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Teachers</title>
<link rel="stylesheet" href="../style/style-sheet.css">
<link rel="icon" type="image/png" href="../images/school-logo.jpg">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Excel export library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #004b8d;
  margin-bottom: 20px;
  text-align: center;
  border-bottom: 2px solid #004b8d;
  padding-bottom: 10px;
}
/* ===== Teachers Filter Form Styling (Blue Theme) ===== */

/* Filter form */
.filter-form {
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.filter-form label {
  font-weight: 600;
}
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
.filter-form select {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #90caf9;
  font-size: 0.95rem;
  cursor: pointer;
  background: #fff;
}

/* Buttons (Search, Print, Export) */
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
@media (max-width: 1024px) {
  .filter-form {
    gap: 8px;
  }
}
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
}
@media (max-width: 480px) {
  .filter-form {
    gap: 6px;
  }
}

.table-container {
  background: #fff;
  padding: 1rem;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 10px 15px;
  border-bottom: 1px solid #ddd;
  text-align: left;
  font-size: 0.95rem;
}
th {
  background: #004b8d;
  color: #fff;
}
tr:hover {
  background: #f8f9fc;
}
.actions a {
  margin-right: 8px;
  text-decoration: none;
  font-size: 1.2rem;
}
.actions .edit { color: #4e73df; }
.actions .delete { color: #e74c3c; }
.search-bar {
  margin-bottom: 1rem;
  display: flex;
  justify-content: flex-end;
}
.search-bar input {
  padding: 0.5rem;
  border: 1.5px solid #4e73df;
  border-radius: 6px;
  outline: none;
}
.pagination {
  margin-top: 1rem;
  text-align: center;
}
.pagination a {
  padding: 6px 12px;
  margin: 0 3px;
  border: 1px solid #4e73df;
  color: #004b8d;
  text-decoration: none;
  border-radius: 5px;
}
.pagination a.active {
  background: #4e73df;
  color: #fff;
}
/* Modal overlay */
.modal {
  display: none;
  position: fixed;
  top: 0; 
  left: 0;
  width: 100%; 
  height: 100%;
  background: rgba(0,0,0,0.6);
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

/* Modal box */
.modal-content {
  background: #fff;
  padding: 1.5rem;
  border-radius: 10px;
  width: 600px;
  max-width: 90%;

  /* Height reduced with scroll */
  max-height: 70vh;     /* modal limited to 70% of viewport height */
  overflow-y: auto;     /* vertical scroll if content is taller */

  box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

/* Header */
.modal-header {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 1rem;
  color: #004b8d;
}

/* Close button */
.close-btn {
  float: right;
  font-size: 1.5rem;
  cursor: pointer;
  color: #e74c3c;
}

/* Form styles */
.form-group { 
  margin-bottom: 1rem; 
  display: flex; 
  flex-direction: column; 
}
.form-group label { 
  font-weight: 600; 
  margin-bottom: .3rem; 
}
.form-group input, 
.form-group select {
  padding: .5rem;
  border: 1.5px solid #4e73df;
  border-radius: 6px;
}

/* Save button */
.save-btn {
  background: linear-gradient(135deg, #4e73df, #1cc88a);
  color: white;
  padding: 0.6rem 1.2rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}
.save-btn:hover {
  opacity: 0.9;
}
/* ✅ Success Message */
.message-success {
  background-color: #d4edda;
  color: #155724;
  border: 1.5px solid #c3e6cb;
  padding: 12px 18px;
  border-radius: 8px;
  margin: 1rem 0;
  font-weight: 600;
  text-align: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  animation: fadeIn 0.5s ease-in-out;
}

/* ❌ Error Message */
.message-error {
  background-color: #f8d7da;
  color: #721c24;
  border: 1.5px solid #f5c6cb;
  padding: 12px 18px;
  border-radius: 8px;
  margin: 1rem 0;
  font-weight: 600;
  text-align: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  animation: fadeIn 0.5s ease-in-out;
}

/* ℹ️ Info Message */
.message-info {
  background-color: #e7f3fe;
  color: #084298;
  border: 1.5px solid #b6d4fe;
  padding: 12px 18px;
  border-radius: 8px;
  margin: 1rem 0;
  font-weight: 600;
  text-align: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  animation: fadeIn 0.5s ease-in-out;
}

/* ✨ Fade-in Animation */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
/* Page Loader with Colored Dots */
#page-loader {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: #ffffff;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}
#page-loader span{
    font-weight: bold;
}
.loader .dot {
  width: 18px;
  height: 18px;
  margin: 0 6px;
  border-radius: 50%;
  animation: bounce 1.2s infinite ease-in-out;
}

.loader .dot.red { background-color: #e74c3c; animation-delay: 0s; }
.loader .dot.green { background-color: #1cc88a; animation-delay: 0.2s; }
.loader .dot.blue { background-color: #4e73df; animation-delay: 0.4s; }

@keyframes bounce {
  0%, 80%, 100% {
    transform: scale(0);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

</style>
</head>
<body>
<div id="page-loader" class="loader">
  <span>Loading</span>
  <div class="dot red"></div>
  <div class="dot green"></div>
  <div class="dot blue"></div>
</div>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="content">

<?php if (isset($_SESSION['message'])) { echo "<div class='msg-box'>".$_SESSION['message']."</div>"; unset($_SESSION['message']); } ?>

<h2 class="page-title"><i class='bx bxs-user-detail'></i> Manage Teachers</h2>

<!-- Filter Form -->
<form method="get" class="filter-form">
  <input type="text" name="search" placeholder="Search code or Name"
        value="<?= htmlspecialchars($search) ?>" />
  <button type="submit"><i class="bx bx-search"></i> Search</button>     

  <label for="status">Show:</label>
  <select name="status" id="status" onchange="this.form.submit()">
      <option value="">All</option>
      <option value="active"   <?= $filter_status==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
  </select>

    <!-- Action Buttons -->
    <button type="button" onclick="printTeachers()"><i class="bx bx-printer"></i> Print</button>
    <button type="button" onclick="exportTeachers('gradTable', 'Teachers_List')">
    <i class="bx bx-download"></i> Export
    </button>
</form>

<div class="table-container">
<table id="gradTable">
  <thead>
    <tr>
      <th>#</th>
      <th>Photo</th>
      <th>Code</th>
      <th>Name</th>
      <th>Gender</th>
      <th>TSC No</th>
      <th>National ID</th>
      <th>Phone</th>
      <th>Email</th>
      <th>Employment Date</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($teachers->num_rows > 0): $sn=$offset+1; ?>
    <?php while ($row = $teachers->fetch_assoc()): ?>
    <tr>
      <td><?= $sn++ ?></td>
      <td>
        <?php if (!empty($row['teacher_photo'])): ?>
          <img src="data:image/jpeg;base64,<?= base64_encode($row['teacher_photo']) ?>" width="50" height="50" style="border-radius:50%">
        <?php else: ?>
          <i class='bx bxs-user-circle' style="font-size:2rem;color:#ccc"></i>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($row['code']) ?></td>
      <td><?= htmlspecialchars($row['name'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['gender'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['tsc_no'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['national_id'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['employment_date'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['status'] ?? '—') ?></td>
      <td class="actions">
        <a href="#" class="edit" 
           data-id="<?= $row['id'] ?>"
           data-name="<?= htmlspecialchars($row['name']) ?>"
           data-gender="<?= $row['gender'] ?>"
           data-tsc="<?= $row['tsc_no'] ?>"
           data-nid="<?= $row['national_id'] ?>"
           data-phone="<?= $row['phone'] ?>"
           data-email="<?= $row['email'] ?>"
           data-date="<?= $row['employment_date'] ?>"
           data-status="<?= $row['status'] ?>"
           ><i class='bx bx-edit'></i></a>
        <a href="manage-teachers.php?delete=<?= $row['id'] ?>" class="delete" onclick="return confirm('Delete this teacher?');"><i class='bx bx-trash'></i></a>
      </td>
    </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr><td colspan="12" style="text-align:center;color:#999">No teachers found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

</main>
</div>

<!-- Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <div class="page-title"><i class='bx bx-edit'></i> Edit Teacher</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" required></div>
      <div class="form-group"><label>Gender</label>
        <select name="gender" id="edit_gender" required>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group"><label>TSC No</label><input type="text" name="tsc_no" id="edit_tsc"></div>
      <div class="form-group"><label>National ID</label><input type="text" name="national_id" id="edit_nid" required></div>
      <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_phone" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email"></div>
      <div class="form-group"><label>Employment Date</label><input type="date" name="employment_date" id="edit_date"></div>
      <div class="form-group"><label>Status</label>
        <select name="status" id="edit_status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="form-group"><label>Photo</label><input type="file" name="teacher_photo" accept="image/*"></div>
      <button type="submit" name="update_teacher" class="save-btn">Save Changes</button>
    </form>
  </div>
</div>
<script>
window.addEventListener('load', () => {
  setTimeout(() => {
    const loader = document.getElementById('page-loader');
    if (loader) {
      loader.style.display = 'none';
    }
  }, 2000);
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
            window.location.href = '../php/logout.php'; // Change to your logout URL
        }, 300000); // 30 seconds
    }

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    resetLogoutTimer();
});
</script>
<script>
// ===== Modal =====
function closeModal() {
  document.getElementById("editModal").style.display = "none";
}
document.querySelectorAll(".edit").forEach(btn => {
  btn.addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("edit_id").value = this.dataset.id;
    document.getElementById("edit_name").value = this.dataset.name;
    document.getElementById("edit_gender").value = this.dataset.gender;
    document.getElementById("edit_tsc").value = this.dataset.tsc;
    document.getElementById("edit_nid").value = this.dataset.nid;
    document.getElementById("edit_phone").value = this.dataset.phone;
    document.getElementById("edit_email").value = this.dataset.email;
    document.getElementById("edit_date").value = this.dataset.date;
    document.getElementById("edit_status").value = this.dataset.status;
    document.getElementById("editModal").style.display = "flex";
  });
});
</script>
<script>
// ================== EXPORT TO EXCEL ==================
function exportTeachers(tableID = "gradTable", filename = "Teachers_List") {
    var table = document.getElementById(tableID);
    if (!table) {
        console.error("❌ Table not found!");
        return;
    }

    var wb = XLSX.utils.table_to_book(table, {sheet:"Teachers"});
    var ws = wb.Sheets["Teachers"];

    // ✅ Add borders
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

    XLSX.writeFile(wb, filename + ".xlsx");
}

// ================== PRINT FUNCTION ==================
function printTeachers() {
    const table = document.getElementById("gradTable");
    if (!table) {
        console.error("❌ Table not found!");
        return;
    }

    const today = new Date();
    const dateStr = formatDateWithSuffix(today);
    const logoUrl = "../images/school-logo.jpg"; // adjust if needed

    const printWindow = window.open('', '', 'height=800,width=1000');

    printWindow.document.write('<html><head><title>Teachers List</title>');
    printWindow.document.write(`
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                margin: 0;
                padding: 20px;
            }
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                opacity: 0.1;
                z-index: 0;
                pointer-events: none;
            }
            .watermark img { width: 400px; }
            .header { margin-bottom: 20px; position: relative; z-index: 2; }
            .header img { width: 80px; height: 80px; }
            h2 { margin: 5px 0; }
            table {
                width: 100%;
                margin-top: 15px;
                border-collapse: collapse;
                z-index: 2;
                background-color: transparent;
            }
            th, td {
                border: 1px solid black;
                padding: 6px;
                text-align: left;
                background-color: transparent;
            }
            th { font-weight: bold; background: #f0f0f0; }
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
            <h2>Teachers List</h2>
            <p>Date: ${dateStr}</p>
        </div>
    `);

    // ✅ Table
    printWindow.document.write(table.outerHTML);

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => printWindow.print();
}

// ✅ Helper: format date nicely
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

<?php include '../includes/footer.php'; ?>
</body>
</html>
