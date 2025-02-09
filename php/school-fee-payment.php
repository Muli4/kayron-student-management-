<?php
session_start();

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "school_database";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';

    // Store original amount for transaction record
    $original_amount_paid = $amount_paid;

    // Fetch student details
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $_SESSION['message'] = "<div class='error-message'>Error: Admission number not found!</div>";
        header("Location: make-payments.php");
        exit();
    }

    $name = $student['name'];
    $class = $student['class'];

    // Fetch school fees record
    $stmt = $conn->prepare("SELECT total_fee, amount_paid, balance FROM school_fees WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $fee_record = $result->fetch_assoc();
    $stmt->close();

    if (!$fee_record) {
        $_SESSION['message'] = "<div class='error-message'>Error: No school fee record found!</div>";
        header("Location: make-payments.php");
        exit();
    }

    $total_fee = $fee_record['total_fee'];
    $previous_paid = $fee_record['amount_paid'];
    $previous_balance = $fee_record['balance'];

    // Calculate new payment and balance
    $new_total_paid = $previous_paid + $amount_paid;
    $new_balance = max(0, $total_fee - $new_total_paid); // Ensure balance doesn't go negative

    // Update school_fees table
    $stmt = $conn->prepare("UPDATE school_fees SET amount_paid = ?, balance = ? WHERE admission_no = ?");
    $stmt->bind_param("dds", $new_total_paid, $new_balance, $admission_no);
    $stmt->execute();
    $stmt->close();

    // Generate a truly unique receipt number
    do {
        $receipt_number = "REC-" . strtoupper(bin2hex(random_bytes(4))); // Secure 8-char unique ID
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM school_fee_transactions WHERE receipt_number = ?");
        $check_stmt->bind_param("s", $receipt_number);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
    } while ($count > 0);

    // Insert transaction record
    $stmt = $conn->prepare("INSERT INTO school_fee_transactions (name, admission_no, class, amount_paid, receipt_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdss", $name, $admission_no, $class, $original_amount_paid, $receipt_number, $payment_type);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "<div class='success-message'>School Fee Payment Successful!</div>";
    header("Location: make-payments.php");
    exit();
}

$conn->close();
?>
