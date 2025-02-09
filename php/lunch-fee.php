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

// Default lunch fee values
$daily_fee = 70;
$total_weekly_fee = 350;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';

    // Store original amount for transaction recording
    $original_amount_paid = $amount_paid;

    // Validate admission number and fetch student details
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $_SESSION['message'] = "<div class='error-message'>Error: Admission number not found in student records!</div>";
        header("Location: make-payments.php");
        exit();
    }

    $name = $student['name'];
    $class = $student['class'];

    // Generate a unique receipt number (Secure & Unique)
    $receipt_no = "RCPT-" . strtoupper(bin2hex(random_bytes(4)));

    // Fetch current week's record
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? ORDER BY week_number DESC LIMIT 1");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $week_data = $result->fetch_assoc();
    $stmt->close();

    $week_number = $week_data ? ($week_data['balance'] == 0 ? $week_data['week_number'] + 1 : $week_data['week_number']) : 1;

    // If new week, insert record
    if (!$week_data || $week_data['balance'] == 0) {
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, payment_type) VALUES (?, 0, ?, ?, ?)");
        $stmt->bind_param("sdis", $admission_no, $total_weekly_fee, $week_number, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // Get the latest week data
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
    $stmt->bind_param("si", $admission_no, $week_number);
    $stmt->execute();
    $week_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $balance = $week_data['balance'];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    // Distribute payment across the week
    foreach ($days as $day) {
        if ($balance <= 0 || $amount_paid <= 0) break;

        if ($week_data[$day] < $daily_fee) {
            $remaining_fee = $daily_fee - $week_data[$day];
            $pay_today = min($remaining_fee, $amount_paid);

            $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ?, payment_type = ? WHERE admission_no = ? AND week_number = ?");
            $stmt->bind_param("dddssi", $pay_today, $pay_today, $pay_today, $payment_type, $admission_no, $week_number);
            $stmt->execute();
            $stmt->close();

            $amount_paid -= $pay_today;
            $balance -= $pay_today;
        }
    }

    // Handle overpayment by advancing to future weeks
    while ($amount_paid > 0) {
        $week_number++;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, payment_type) VALUES (?, 0, ?, ?, ?)");
        $stmt->bind_param("sdis", $admission_no, $total_weekly_fee, $week_number, $payment_type);
        $stmt->execute();
        $stmt->close();

        foreach ($days as $day) {
            if ($amount_paid <= 0) break;

            $pay_today = min($daily_fee, $amount_paid);
            $stmt = $conn->prepare("UPDATE lunch_fees SET $day = ?, total_paid = total_paid + ?, balance = balance - ?, payment_type = ? WHERE admission_no = ? AND week_number = ?");
            $stmt->bind_param("dddssi", $pay_today, $pay_today, $pay_today, $payment_type, $admission_no, $week_number);
            $stmt->execute();
            $stmt->close();

            $amount_paid -= $pay_today;
        }
    }

    // Insert transaction record into lunch_fee_transactions
    $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_no, $original_amount_paid, $payment_type);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "<div class='success-message'>Payment recorded successfully!</div>";
    header("Location: make-payments.php");
    exit();
}

$conn->close();
?>
