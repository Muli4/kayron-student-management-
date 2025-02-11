<?php
session_start();

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "school_database";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed!"]));
}

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';

    if ($amount <= 0) {
        echo json_encode(["error" => "Invalid payment amount."]);
        exit();
    }

    // Store original amount for transaction record
    $original_amount = $amount;

    // Fetch student details
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(["error" => "Admission number not found!"]);
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
        echo json_encode(["error" => "No school fee record found!"]);
        exit();
    }

    $total_fee = $fee_record['total_fee'];
    $previous_paid = $fee_record['amount_paid'];
    $previous_balance = $fee_record['balance'];

    // Calculate new payment and balance (Allows negative balance for overpayment)
    $new_total_paid = $previous_paid + $amount;
    $new_balance = $total_fee - $new_total_paid; // Negative if overpaid

    // Update school_fees table
    $stmt = $conn->prepare("UPDATE school_fees SET amount_paid = ?, balance = ? WHERE admission_no = ?");
    $stmt->bind_param("dds", $new_total_paid, $new_balance, $admission_no);
    $stmt->execute();
    $stmt->close();

    // Generate a unique receipt number
    $receipt_number = "REC-" . strtoupper(bin2hex(random_bytes(4)));

    // Insert transaction record
    $stmt = $conn->prepare("INSERT INTO school_fee_transactions (name, admission_no, class, amount_paid, receipt_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdss", $name, $admission_no, $class, $original_amount, $receipt_number, $payment_type);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success" => true, 
        "message" => "School Fee Payment Successful!", 
        "new_balance" => $new_balance // Include new balance in response
    ]);
    exit();
}

$conn->close();
?>
