<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide errors from user
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log'); // Log errors to a file

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school_database";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form inputs
    $admission_no = trim($_POST['admission_no'] ?? '');
    $fee_type = strtolower(trim($_POST['fee_type'] ?? ''));
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = trim($_POST['payment_type'] ?? 'Cash');
    $receipt_number = trim($_POST['receipt_number'] ?? '');

    // Validate inputs
    if (empty($admission_no) || empty($fee_type) || $amount <= 0 || empty($receipt_number)) {
        die("Invalid payment data.");
    }

    // Retrieve student name and term
    $stmt = $conn->prepare("SELECT name, term FROM student_records WHERE admission_no = ?");
    if (!$stmt) {
        error_log("MySQL Error (Fetching student): " . $conn->error);
        die("Error retrieving student details.");
    }

    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($name, $term);
    $stmt->fetch();
    $stmt->close();

    if (empty($name)) {
        die("Student record not found.");
    }

    if (!$term) {
        $term = "Unknown";
    }

    // Check if admission fee has already been paid
    if ($fee_type === "admission") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM others WHERE admission_no = ? AND LOWER(fee_type) = 'admission'");
        if (!$stmt) {
            error_log("MySQL Error (Checking admission fee): " . $conn->error);
            die("Error checking admission fee.");
        }

        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            die("Admission fee has already been paid for this student.");
        }
    }

    // Insert the payment
    $stmt = $conn->prepare("INSERT INTO others (receipt_number, admission_no, name, term, fee_type, amount, payment_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("MySQL Error (Inserting payment): " . $conn->error);
        die("Error processing payment.");
    }

    $stmt->bind_param("sssssss", $receipt_number, $admission_no, $name, $term, $fee_type, $amount, $payment_type);

    if ($stmt->execute()) {
        echo "Payment successfully recorded!";
    } else {
        error_log("MySQL Execution Error: " . $stmt->error);
        echo "Error saving payment.";
    }

    $stmt->close();
}

$conn->close();
?>
