<?php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school_database";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed!"]));
}

// Function to generate a unique receipt number
function generateReceiptNumber() {
    return "REC" . date("Ymd") . strtoupper(bin2hex(random_bytes(3))); // Unique and secure
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $fee_type = trim($_POST['fee_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0); // Changed from amount_paid
    $payment_type = $_POST['payment_type'] ?? 'Cash';
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;

    if (empty($admission_no) || empty($name) || empty($fee_type) || $amount <= 0) {
        echo json_encode(["error" => "Invalid input data!"]);
        exit();
    }

    // Retrieve the student's term
    $stmt = $conn->prepare("SELECT term FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($term);
    $stmt->fetch();
    $stmt->close();

    if (empty($term)) {
        echo json_encode(["error" => "Student record not found or term missing!"]);
        exit();
    }

    // Check if admission fee has already been paid
    if ($fee_type === "admission") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM others WHERE admission_no = ? AND fee_type = 'admission'");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            echo json_encode(["error" => "Admission fee has already been paid!"]);
            exit();
        }
    }

    // Generate a receipt number
    $receipt_number = generateReceiptNumber();

    // Insert payment into `others` table
    $stmt = $conn->prepare("INSERT INTO others (receipt_number, admission_no, name, term, fee_type, amount, payment_type, is_recurring) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssdsi", $receipt_number, $admission_no, $name, $term, $fee_type, $amount, $payment_type, $is_recurring);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Payment recorded successfully!", "receipt" => $receipt_number]);
    } else {
        echo json_encode(["error" => "Error processing payment: " . $stmt->error]);
    }

    $stmt->close();
    exit();
}

$conn->close();
?>
