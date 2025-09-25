<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Africa/Nairobi');
include '../php/db.php'; // DB connection

// ================== Generate Next Teacher Code ==================
$year_suffix = '0' . date('y'); // e.g. "025"
$next_num = 1;

$last_code_result = $conn->query("SELECT code FROM teacher_records ORDER BY id DESC LIMIT 1");
if ($last_code_result && $last_code_result->num_rows > 0) {
    $row = $last_code_result->fetch_assoc();
    if (preg_match('/TCH-(\d+)-0\d{2}/', $row['code'], $matches)) {
        $last_num = (int)$matches[1];
        $next_num = $last_num + 1;
    }
}

$next_teacher_code = 'TCH-' . str_pad($next_num, 4, '0', STR_PAD_LEFT) . '-' . $year_suffix;

// ================== Handle Form Submission ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $gender      = $_POST['gender'] ?? '';
    $national_id = trim($_POST['national_id'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? ''); // optional
    $status      = $_POST['status'] ?? 'active';
    $employment_date = !empty($_POST['employment_date']) ? $_POST['employment_date'] : date('Y-m-d');
    $code = $next_teacher_code;

    // Validate required inputs
    if (empty($name) || empty($gender) || empty($national_id) || empty($phone)) {
        $_SESSION['message'] = "<div class='add-error-message'>⚠️ Please fill in Name, Gender, National ID, and Phone.</div>";
        header("Location: add-teacher.php");
        exit();
    }

    // Handle teacher photo
    $teacher_photo = null;
    if (isset($_FILES['teacher_photo']) && $_FILES['teacher_photo']['error'] === 0) {
        $teacher_photo = file_get_contents($_FILES['teacher_photo']['tmp_name']);
    }

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO teacher_records 
        (name, gender, national_id, phone, email, code, employment_date, status, teacher_photo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $null = null;
    $stmt->bind_param(
        "sssssssss",
        $name,
        $gender,
        $national_id,
        $phone,
        $email,
        $code,
        $employment_date,
        $status,
        $null
    );

    if ($teacher_photo !== null) {
        $stmt->send_long_data(8, $teacher_photo);
    }

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='add-success-message'>Teacher added successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='add-error-message'>Error adding teacher: " . htmlspecialchars($stmt->error) . "</div>";
    }

    $stmt->close();
    header("Location: add-teacher.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Teacher</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style/style-sheet.css">
<link rel="icon" type="image/png" href="../images/school-logo.jpg">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
/* ===== Add Teacher Form ===== */
.add-teacher {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
    background: #fff;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.page-title{
  font-size: 28px;
  font-weight: 700;
  color: #004b8d;
  margin-bottom: 25px;
  text-align: center;
  border-bottom: 2px solid #004b8d;
  padding-bottom: 10px;
}
.page-title i {
    color: #004b8d;
    font-size: 2rem;
    vertical-align: middle;
    margin-right: 0.5rem;
}
.form-group { flex: 1 1 300px; display: flex; flex-direction: column; }
.form-group label { font-weight: 600; color: #1cc88a; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem; font-size: 0.95rem; }
.form-group input[type="text"],
.form-group input[type="date"],
.form-group input[type="email"],
.form-group input[type="file"],
.form-group select { padding: 0.5rem 0.8rem; border: 1.8px solid #4e73df; border-radius: 6px; font-size: 1rem; outline: none; transition: border-color 0.3s ease; }
.form-group input:focus, .form-group select:focus { border-color: #1cc88a; }
.button-container { width: 100%; margin-top: 1.5rem; display: flex; justify-content: center; }
.add-teacher-btn { background: linear-gradient(135deg, #4e73df, #1cc88a); color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background 0.3s ease; }
.add-teacher-btn:hover { background: linear-gradient(135deg, #1cc88a, #4e73df); }
.add-success-message { background-color: #d4edda; color: #155724; border: 1.5px solid #c3e6cb; padding: 10px 15px; border-radius: 6px; margin-bottom: 1rem; font-weight: 600; text-align: center; }
.add-error-message { background-color: #f8d7da; color: #721c24; border: 1.5px solid #f5c6cb; padding: 10px 15px; border-radius: 6px; margin-bottom: 1rem; font-weight: 600; text-align: center; }
@media (max-width: 600px) { .add-teacher { flex-direction: column; } .form-group { flex: 1 1 100%; } }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="content">

<?php
if (isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<h2 class="page-title"><i class='bx bxs-user-plus'></i> Add Teacher</h2>

<form method="POST" enctype="multipart/form-data">
    <div class="add-teacher">
        <div class="form-group">
            <label><i class='bx bxs-user-circle'></i> Name *</label>
            <input type="text" name="name" placeholder="Full Name" required>
        </div>

        <div class="form-group">
            <label><i class='bx bxs-id-card'></i> National ID / Passport *</label>
            <input type="text" name="national_id" placeholder="Enter National ID" required>
        </div>

        <div class="form-group">
            <label><i class='bx bxs-phone'></i> Phone Number *</label>
            <input type="text" name="phone" placeholder="07xx xxx xxx or +2547..." required>
        </div>

        <div class="form-group">
            <label><i class='bx bxs-envelope'></i> Email Address</label>
            <input type="email" name="email" placeholder="Enter Email (Optional)">
        </div>

        <div class="form-group">
            <label><i class='bx bxs-id-card'></i> Teacher Code *</label>
            <input type="text" name="code_display" value="<?= htmlspecialchars($next_teacher_code) ?>" readonly>
        </div>

        <div class="form-group">
            <label><i class='bx bx-calendar'></i> Employment Date</label>
            <input type="date" name="employment_date">
        </div>

        <div class="form-group">
            <label><i class='bx bx-male-female'></i> Gender *</label>
            <select name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class='bx bxs-user-circle'></i> Status *</label>
            <select name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class='bx bxs-camera'></i> Upload Photo</label>
            <input type="file" name="teacher_photo" accept="image/*">
        </div>
    </div>

    <div class="button-container">
        <button type="submit" class="add-teacher-btn">
            <i class='bx bx-cart-add'></i> Add Teacher
        </button>
    </div>
</form>
</main>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>

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