<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';  // Your DB connection

// Get current term from terms table
$current_term = '';
$term_result = $conn->query("SELECT term_number FROM terms ORDER BY id DESC LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $row = $term_result->fetch_assoc();
    $current_term = strtolower($row['term_number']); // e.g. 'term1', 'term2', 'term3'
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $admission_no = trim($_POST['admission_no'] ?? '');
    $birth_cert = !empty($_POST['birth_cert']) ? trim($_POST['birth_cert']) : null;
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $class = strtolower($_POST['class'] ?? '');
    $term = $current_term;
    $religion = !empty($_POST['religion']) ? $_POST['religion'] : null;
    $guardian = trim($_POST['guardian'] ?? $_POST['gurdian'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Handle student photo blob
    $student_photo = null;
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === 0) {
        $student_photo = file_get_contents($_FILES['student_photo']['tmp_name']);
    }

    // Check for duplicate admission number
    $stmt_check = $conn->prepare("SELECT admission_no FROM student_records WHERE admission_no = ?");
    $stmt_check->bind_param("s", $admission_no);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $_SESSION['message'] = "<div class='error-message'>Admission number already exists.</div>";
        $stmt_check->close();
        header("Location: add-student.php");
        exit();
    }
    $stmt_check->close();

    // Fee structure
    $fee_structure = [
        "babyclass" => [4700, 4700, 4700],
        "intermediate" => [4700, 4700, 4700],
        "pp1" => [4700, 4700, 4700],
        "pp2" => [4700, 4700, 4700],
        "grade1" => [5700, 5700, 5700],
        "grade2" => [5700, 5700, 5700],
        "grade3" => [5700, 5700, 5700],
        "grade4" => [6700, 6700, 6700],
        "grade5" => [6700, 6700, 6700],
        "grade6" => [6700, 6700, 6700]
    ];
    $term_index = ["term1" => 0, "term2" => 1, "term3" => 2];
    $total_fee = $fee_structure[$class][$term_index[$term] ?? 0] ?? 0;
    $amount_paid = 0;
    $balance = $total_fee;

    // Prepare insert statement for student_records
    $stmt = $conn->prepare("INSERT INTO student_records 
        (admission_no, birth_cert, name, dob, gender, student_photo, class, term, religion, guardian, phone) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Bind parameters including blob with correct types and count
    $null = NULL; // placeholder for blob
    $stmt->bind_param(
        "sssssbsssss",
        $admission_no,
        $birth_cert,
        $name,
        $dob,
        $gender,
        $null,      // for blob
        $class,
        $term,
        $religion,
        $guardian,
        $phone
    );

    // Send blob data if available
    if ($student_photo !== null) {
        $stmt->send_long_data(5, $student_photo);
    }

    if ($stmt->execute()) {
        // Insert fee details
        $stmt_fee = $conn->prepare("INSERT INTO school_fees (admission_no, birth_cert, class, term, total_fee, amount_paid, balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_fee->bind_param("ssssddd", $admission_no, $birth_cert, $class, $term, $total_fee, $amount_paid, $balance);
        $stmt_fee->execute();
        $stmt_fee->close();

        $_SESSION['message'] = "<div class='add-success-message'>Student and fee details added successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='add-error-message'>Error adding student: " . $stmt->error . "</div>";
    }

    $stmt->close();
    header("Location: add-student.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
    /* ===== Add Student Form Container ===== */
.add-student {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
    background: #fff;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Each form group */
.form-group {
    flex: 1 1 300px;
    display: flex;
    flex-direction: column;
}

/* Labels with icon */
.form-group label {
    font-weight: 600;
    color: #1cc88a;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.95rem;
}

/* Inputs and selects */
.form-group input[type="text"],
.form-group input[type="date"],
.form-group input[type="file"],
.form-group select {
    padding: 0.5rem 0.8rem;
    border: 1.8px solid #4e73df;
    border-radius: 6px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="date"]:focus,
.form-group input[type="file"]:focus,
.form-group select:focus {
    border-color: #1cc88a;
}

/* Button container */
.button-container {
    width: 100%;
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;  /* Center the button */
}

/* Submit button */
.add-student-btn {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.3s ease;
}

.add-student-btn:hover {
    background: linear-gradient(135deg, #1cc88a, #4e73df);
}

/* Center page title with icon */
.page-title {
    display: flex;
    justify-content: center; /* center horizontally */
    align-items: center;
    gap: 0.5rem;
    font-size: 1.8rem;
    font-weight: 600;
    color: #1cc88a;
    margin-bottom: 1.5rem;
    text-align: center;
}

.page-title i {
    font-size: 2rem;
    color: #4e73df;
}
.add-success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1.5px solid #c3e6cb;
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-weight: 600;
    text-align: center;
}

.add-error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1.5px solid #f5c6cb;
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-weight: 600;
    text-align: center;
}

@media (max-width: 600px) {
    .add-student {
        flex-direction: column;
        max-width: 100%;       /* Ensure container doesn't overflow */
        box-sizing: border-box; /* Include padding in width */
    }
    .form-group {
        flex: 1 1 100%;
        width: 100%;           /* Make sure form groups take full width */
        box-sizing: border-box;
    }
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="file"],
    .form-group select {
        width: 100%;           /* Inputs fill their container */
        box-sizing: border-box;
    }
}
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
            <h2 class="page-title"><i class='bx bxs-user-plus'></i> Add Student</h2>

        <form method="POST" enctype="multipart/form-data">
            <div class="add-student">
                <div class="form-group">
                    <label><i class='bx bxs-user-circle'></i> Name *</label>
                    <input type="text" name="name" placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-bookmark-alt-minus'></i> Admission No *</label>
                    <input type="text" name="admission_no" placeholder="Admission Number" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-certification'></i> Birth Certificate</label>
                    <input type="text" name="birth_cert" placeholder="Birth Certificate Number">
                </div>

                <div class="form-group">
                    <label><i class='bx bx-calendar'></i> Date Of Birth</label>
                    <input type="date" name="dob" id="dob">
                </div>

                <div class="form-group">
                    <label><i class='bx bx-male-female'></i> Gender *</label>
                    <select name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-chalkboard'></i> Class *</label>
                    <select name="class" required>
                        <option value="">-- Select Class --</option>
                        <option value="babyclass">BabyClass</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="pp1">Pre-Primary 1</option>
                        <option value="pp2">Pre-Primary 2</option>
                        <option value="grade1">Grade 1</option>
                        <option value="grade2">Grade 2</option>
                        <option value="grade3">Grade 3</option>
                        <option value="grade4">Grade 4</option>
                        <option value="grade5">Grade 5</option>
                        <option value="grade6">Grade 6</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-calendar-week'></i> Current Term</label>
                    <input type="text" name="term" value="<?php echo htmlspecialchars($current_term); ?>" readonly>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-church'></i> Religion</label>
                    <select name="religion">
                        <option value="">-- Select Religion --</option>
                        <option value="christian">Christian</option>
                        <option value="muslim">Muslim</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-shield-alt-2'></i> Guardian *</label>
                    <input type="text" name="guardian" placeholder="Guardian Name" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-phone'></i> Phone Number *</label>
                    <input type="text" name="phone" placeholder="Phone Number" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bxs-camera'></i> Passport</label>
                    <input type="file" name="student_photo">
                </div>
            </div>

            <div class="button-container">
                <button type="submit" class="add-student-btn">
                    <i class='bx bx-cart-add'></i> Add Student
                </button>
            </div>
        </form>
    </main>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) { // removed window.innerWidth check to show clock on all devices
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
            // Silent logout - redirect to logout page
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 5 minutes
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    // Start the timer when page loads
    resetLogoutTimer();
});
</script>

</body>
</html>
