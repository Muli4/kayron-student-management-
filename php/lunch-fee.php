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
        header("Location: lunch-fee.php");
        exit();
    }

    $name = $student['name'];
    $class = $student['class'];

    // Generate a unique receipt number
    $receipt_number = uniqid("REC-");

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

    // Distribute payment
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

    // Handle overpayment
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
    $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_number, $original_amount_paid, $payment_type);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "<div class='success-message'>Payment recorded successfully!</div>";
    header("Location: make-payments.php");
    exit();
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Lunch Fee Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
    <div class="heading-all">
        <h2 class="title">Kayron Junior School</h2>
    </div>
    <div class="add-heading">
        <h2>Lunch Fees</h2>
    </div>

    <div class="lunch-form">
        <form method="POST">
            <!-- Display session message above the form -->
            <?php
            if (isset($_SESSION['message'])) {
                echo $_SESSION['message'];
                unset($_SESSION['message']);
            }
            ?>

            <div class="form-group">
                <label>Enter Admission Number:</label>
                <input type="text" name="admission_no" required>
            </div>

            <div class="form-group">
                <label>Enter Amount:</label>
                <input type="number" name="amount_paid" required>
            </div>

            <div class="form-group">
                <label>Payment Type:</label>
                <select name="payment_type" required>
                    <option value="">Select Payment</option>
                    <option value="Cash">Cash</option>
                    <option value="Liquid Money">Liquid Money</option>
                </select>
            </div>

            <button type="submit" class="add-student-btn">Pay Now</button>
        </form>
    </div>

    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard</a>
    </div>
</body>
</html>
