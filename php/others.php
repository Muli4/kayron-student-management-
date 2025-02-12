<?php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school_database";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Ensure we received payments data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payments'])) {
    $payments = $_POST['payments']; // No need for json_decode

    if (!is_array($payments) || empty($payments)) {
        echo json_encode(["error" => "Invalid or empty payment data received."]);
        exit();
    }

    $errors = [];
    $successCount = 0;

    foreach ($payments as $payment) {
        $admission_no = trim($payment['admission_no'] ?? '');
        $fee_type = trim($payment['fee_type'] ?? '');
        $amount = floatval($payment['amount'] ?? 0);
        $payment_type = trim($payment['payment_type'] ?? 'Cash');
        $receipt_number = trim($payment['receipt_number'] ?? '');

        // Validate inputs
        if (empty($admission_no) || empty($fee_type) || $amount <= 0 || empty($receipt_number)) {
            $errors[] = "Invalid data for student $admission_no.";
            continue;
        }

        // Retrieve student name and term
        $stmt = $conn->prepare("SELECT name, term FROM student_records WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $stmt->bind_result($name, $term);
        $stmt->fetch();
        $stmt->close();

        if (empty($name)) {
            $errors[] = "Student record not found for $admission_no.";
            continue;
        }

        if (!$term) {
            $term = "Unknown"; // Default if term is missing
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
                $errors[] = "Admission fee already paid for $admission_no.";
                continue;
            }
        }

        // Insert the payment
        $stmt = $conn->prepare("INSERT INTO others (receipt_number, admission_no, name, term, fee_type, amount, payment_type) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $receipt_number, $admission_no, $name, $term, $fee_type, $amount, $payment_type);

        if ($stmt->execute()) {
            $successCount++;
        } else {
            $errors[] = "Error processing payment for $admission_no: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();

    if (!empty($errors)) {
        echo json_encode(["error" => implode("\n", $errors)]);
    } else {
        echo json_encode(["success" => true, "message" => "$successCount payments processed successfully!"]);
    }
    exit();
}

echo json_encode(["error" => "Invalid request."]);
$conn->close();
?>
