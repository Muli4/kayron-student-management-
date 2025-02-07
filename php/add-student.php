<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "school_database"; 

$conn = new mysqli($servername, $username, $password, $database);

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $admission_no = $_POST['admission_no'] ?? '';
    $birth_cert = $_POST['birth_cert'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $class = strtolower($_POST['class'] ?? ''); 
    $term = strtolower($_POST['term'] ?? ''); 
    $religion = $_POST['religion'] ?? '';
    $gurdian = $_POST['gurdian'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Handle student photo upload
    $student_photo = null;
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] == 0) {
        $student_photo = file_get_contents($_FILES['student_photo']['tmp_name']);
    }

    // Check if admission number already exists
    $check_query = "SELECT admission_no FROM student_records WHERE admission_no = ?";
    $stmt_check = $conn->prepare($check_query);
    $stmt_check->bind_param("s", $admission_no);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $_SESSION['message'] = "<div class='error-message'>Admission number already exists. Try another one.</div>";
        header("Location: add-student.php");
        exit();
    }
    $stmt_check->close();

    // Fee structure mapping
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
    $total_fee = isset($fee_structure[$class]) ? $fee_structure[$class][$term_index[$term] ?? 0] : 0;
    $amount_paid = 0;
    $balance = $total_fee - $amount_paid;

    // Insert student record
    $stmt = $conn->prepare("INSERT INTO student_records 
        (name, admission_no, birth_cert, dob, gender, class, term, religion, gurdian, phone, student_photo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssssi", $name, $admission_no, $birth_cert, $dob, $gender, $class, $term, $religion, $gurdian, $phone, $student_photo);

    if ($stmt->execute()) {
        // Insert fee record into school_fees
        $stmt_fee = $conn->prepare("INSERT INTO school_fees (admission_no, birth_cert, class, term, total_fee, amount_paid, balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_fee->bind_param("ssssddd", $admission_no, $birth_cert, $class, $term, $total_fee, $amount_paid, $balance);

        if ($stmt_fee->execute()) {
            $_SESSION['message'] = "<div class='success-message'>Student and fee details added successfully!</div>";
        } else {
            $_SESSION['message'] = "<div class='error-message'>Error adding fee record: " . $stmt_fee->error . "</div>";
        }
        $stmt_fee->close();
    } else {
        $_SESSION['message'] = "<div class='error-message'>Error adding student: " . $stmt->error . "</div>";
    }

    $stmt->close();
    header("Location: add-student.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>
    <link rel="stylesheet" href="../style/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="heading-all">
        <h2 class="title">Kayron Junior School</h2>
    </div>
    <div class="add-heading">
        <h2>add student</h2>
    </div>
    <form method="post" enctype="multipart/form-data" class="add-student-form">
    <?php
    if (isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']); // Clear message after displaying
    }
    ?>
        <div class="add-student">
            <div class="form-group">
                <label for="name"><i class='bx bxs-user-circle'></i> Name</label>
                <input type="text" name="name" placeholder="Full Name" required>
            </div>

            <div class="form-group">
                <label for="admission_no"><i class='bx bxs-bookmark-alt-minus'></i> Admission No</label>
                <input type="text" name="admission_no" placeholder="Admission Number" required>
            </div>

            <div class="form-group">
                <label for="birth_cert"><i class='bx bxs-certification'></i> Birth Certificate</label>
                <input type="text" name="birth_cert" placeholder="Birth Certificate Number">
            </div>

            <div class="form-group">
                <label for="dob"><i class='bx bx-calendar'></i> Date Of Birth</label>
                <input type="date" name="dob" required>
            </div>

            <div class="form-group">
                <label for="gender"><i class='bx bx-male-female'></i> Gender</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>

            <div class="form-group">
                <label for="class"><i class='bx bxs-chalkboard'></i> Class</label>
                <select name="class" required>
                    <option value="">Select Class</option>
                    <option value="babyclass">BabyClass</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="PP1">Pre-Primary 1</option>
                    <option value="PP2">Pre-Primary 2</option>
                    <option value="grade1">Grade One</option>
                    <option value="grade2">Grade Two</option>
                    <option value="grade3">Grade Three</option>
                    <option value="grade4">Grade Four</option>
                    <option value="grade5">Grade Five</option>
                    <option value="grade6">Grade Six</option>
                </select>
            </div>

            <div class="form-group">
                <label for="term"><i class='bx bxs-calendar-week'></i> Term</label>
                <select name="term" required>
                    <option value="">Select Term</option>
                    <option value="term1">Term One</option>
                    <option value="term2">Term Two</option>
                    <option value="term3">Term Three</option>
                </select>
            </div>

            <div class="form-group">
                <label for="religion"><i class='bx bxs-church'></i> Religion</label>
                <select name="religion" required>
                    <option value="">Select Religion</option>
                    <option value="christian">Christian</option>
                    <option value="muslim">Muslim</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="gurdian"><i class='bx bxs-shield-alt-2'></i> Guardian</label>
                <input type="text" name="gurdian" placeholder="Guardian Name" required>
            </div>

            <div class="form-group">
                <label for="phone"><i class='bx bxs-phone'></i> Phone Number</label>
                <input type="text" name="phone" placeholder="Phone Number" required>
            </div>

            <div class="form-group">
                <label for="student_photo"><i class='bx bxs-camera'></i> Passport</label>
                <input type="file" name="student_photo">
            </div>
        </div>
        <button type="submit" class="add-student-btn"><i class='bx bx-cart-add'></i> Add Student</button>
    </form>

    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard <i class='bx bx-exit'></i></a>
    </div>
</body>

</html>
