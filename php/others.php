<?php
session_start();

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

// Function to generate a unique receipt number
function generateReceiptNumber($conn) {
    do {
        $receipt_number = "REC" . date("Ymd") . rand(1000, 9999);
        $stmt = $conn->prepare("SELECT id FROM others WHERE receipt_number = ?");
        $stmt->bind_param("s", $receipt_number);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0); // Ensure uniqueness
    $stmt->close();
    return $receipt_number;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['fee_type']) || empty($_POST['fee_type'])) {
        $_SESSION['message'] = "Error: No fee type selected!";
        header("Location: make-payments.php");
        exit;
    }

    if (!isset($_POST['amount_paid']) || empty($_POST['amount_paid'])) {
        $_SESSION['message'] = "Error: Amount field is missing!";
        header("Location: make-payments.php");
        exit;
    }

    $admission_no = $_POST['admission_no'];
    $name = $_POST['name'];
    $fee_type = $_POST['fee_type']; // Treat fee_type as a single value
    $amount = $_POST['amount_paid'];
    $payment_type = $_POST['payment_type'];
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;

    if (!is_numeric($amount) || $amount <= 0) {
        $_SESSION['message'] = "Error: Invalid amount!";
        header("Location: make-payments.php");
        exit;
    }

    // **Retrieve the term from the students table**
    $stmt = $conn->prepare("SELECT term FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($term);
    $stmt->fetch();
    $stmt->close();

    // If no term is found, return an error
    if (empty($term)) {
        $_SESSION['message'] = "Error: Student record not found or term missing!";
        header("Location: make-payments.php");
        exit;
    }

    // **Check if admission fee has already been paid**
    if ($fee_type === "admission") {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM others WHERE admission_no = ? AND fee_type = 'admission'");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $_SESSION['message'] = "Error: Admission fee has already been paid!";
            header("Location: make-payments.php");
            exit;
        }
    }

    // Generate a receipt number
    $receipt_number = generateReceiptNumber($conn);

    // Insert into `others` table
    $sql = "INSERT INTO others (receipt_number, admission_no, name, term, fee_type, amount, payment_type, is_recurring) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssdsi", $receipt_number, $admission_no, $name, $term, $fee_type, $amount, $payment_type, $is_recurring);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Payment recorded successfully! Receipt Number: <b>$receipt_number</b>";
    } else {
        $_SESSION['message'] = "Error processing payment: " . $stmt->error;
    }
    $stmt->close();

    // Redirect back to form
    header("Location: make-payments.php");
    exit;
}

$conn->close();
?>
