<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Get current term from terms table
$current_term = '';
$term_result = $conn->query("SELECT term_number FROM terms ORDER BY id DESC LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $row = $term_result->fetch_assoc();
    $current_term = strtolower($row['term_number']); // 'term1', 'term2', 'term3'
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $admission_no = trim($_POST['admission_no'] ?? '');
    $birth_cert = !empty($_POST['birth_cert']) ? trim($_POST['birth_cert']) : null;
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $class = strtolower($_POST['class'] ?? '');
    $term = $current_term;
    $religion = !empty($_POST['religion']) ? $_POST['religion'] : null;
    $guardian = trim($_POST['guardian'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Handle student photo
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

    // Fee structure and logic
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
    $total_fee = $fee_structure[$class][$term_index[$term]] ?? 0;
    $amount_paid = 0;
    $balance = $total_fee;

    // Insert into student_records
    $stmt = $conn->prepare("INSERT INTO student_records 
        (admission_no, birth_cert, name, dob, gender, student_photo, class, term, religion, guardian, phone) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssssbssssi",
        $admission_no,
        $birth_cert,
        $name,
        $dob,
        $gender,
        $student_photo,
        $class,
        $term,
        $religion,
        $guardian,
        $phone
    );

    if ($stmt->execute()) {
        // Insert into school_fees table
        $stmt_fee = $conn->prepare("INSERT INTO school_fees (admission_no, birth_cert, class, term, total_fee, amount_paid, balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_fee->bind_param("sssdddd", $admission_no, $birth_cert, $class, $term, $total_fee, $amount_paid, $balance);
        $stmt_fee->execute();
        $stmt_fee->close();

        $_SESSION['message'] = "<div class='success-message'>Student and fee details added successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='error-message'>Error adding student: " . $stmt->error . "</div>";
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
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
    // Set default DOB to today
    document.addEventListener("DOMContentLoaded", () => {
        const dobInput = document.getElementById('dob');
        if (dobInput) {
            const today = new Date().toISOString().split('T')[0];
            dobInput.value = today;
        }
    });
</script>

<script src="../js/java-script.js" defer></script>
</body>
</html>
