<?php
// Database connection details
$servername = "localhost";
$username = "root"; // Change if different
$password = ""; // Change if set
$dbname = "school_database"; // Change to your database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate a unique receipt number AFTER successful payment
function generateReceiptNumber($conn) {
    do {
        $receipt_number = "REC" . date("Ymd") . rand(1000, 9999);
        $stmt = $conn->prepare("SELECT id FROM others WHERE receipt_number = ?");
        $stmt->bind_param("s", $receipt_number);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0); // Keep generating until it's unique
    $stmt->close();
    return $receipt_number;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['fee_type']) || !is_array($_POST['fee_type'])) {
        echo "Error: No fee type selected!";
        exit;
    }

    if (!isset($_POST['amount']) || !is_array($_POST['amount'])) {
        echo "Error: Amount field is missing!";
        exit;
    }

    $admission_no = $_POST['admission_no']; // Ensure this exists in your form
    $fees_inserted = false; // Track if at least one fee is inserted

    foreach ($_POST['fee_type'] as $index => $fee_type) {
        if (!isset($_POST['amount'][$index]) || empty($_POST['amount'][$index])) {
            echo "Error: Amount missing for fee type: " . htmlspecialchars($fee_type) . "<br>";
            continue; // Skip this fee type
        }

        $amount = $_POST['amount'][$index];

        // Ensure amount is a valid number
        if (!is_numeric($amount) || $amount <= 0) {
            echo "Error: Invalid amount for fee type: " . htmlspecialchars($fee_type) . "<br>";
            continue;
        }

        // Generate a receipt number only if at least one successful insertion occurs
        if (!$fees_inserted) {
            $receipt_number = generateReceiptNumber($conn);
            $fees_inserted = true;
        }

        // Insert into `others` table
        $sql = "INSERT INTO others (receipt_number, admission_no, fee_type, amount) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssd", $receipt_number, $admission_no, $fee_type, $amount);

        if (!$stmt->execute()) {
            echo "Error inserting fee type: " . htmlspecialchars($fee_type) . " - " . $stmt->error . "<br>";
        }
        $stmt->close();
    }

    if ($fees_inserted) {
        echo "Fees recorded successfully!<br>Generated Receipt Number: <b>$receipt_number</b>";
    } else {
        echo "No fees were recorded.";
    }

    $conn->close();
}
?>
