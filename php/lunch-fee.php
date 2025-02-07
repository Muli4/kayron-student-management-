<?php
// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "school_database";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default lunch fee per week
$daily_fee = 70;
$total_weekly_fee = 350;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = $_POST['admission_no'] ?? '';
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash'; // Default to Cash if not selected

    // Step 1: Check if admission number exists in student records
    $stmt = $conn->prepare("SELECT * FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo "<script>alert('Error: Admission number not found in student records!'); window.location.href='lunch-fee.php';</script>";
        exit();
    }

    // Get current week record
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? ORDER BY week_number DESC LIMIT 1");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $week_data = $result->fetch_assoc();
    $stmt->close();

    if ($week_data) {
        // If the current week is fully paid, start a new week
        if ($week_data['balance'] == 0) {
            $week_number = $week_data['week_number'] + 1;
            $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, payment_type) VALUES (?, 0, ?, ?, ?)");
            $stmt->bind_param("sdis", $admission_no, $total_weekly_fee, $week_number, $payment_type);
            $stmt->execute();
            $stmt->close();
        } else {
            // If the current week is not fully paid, continue distributing the payment in the current week
            $week_number = $week_data['week_number'];
        }
    } else {
        // No record exists, create the first week's record
        $week_number = 1;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, payment_type) VALUES (?, 0, ?, ?, ?)");
        $stmt->bind_param("sdis", $admission_no, $total_weekly_fee, $week_number, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // Process payment day by day for the current week
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
    $stmt->bind_param("si", $admission_no, $week_number);
    $stmt->execute();
    $week_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $balance = $week_data['balance'];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    // Distribute payment to the current week first
    while ($amount_paid > 0 && $balance > 0) {
        foreach ($days as $day) {
            if ($balance <= 0 || $amount_paid <= 0) break;

            if ($week_data[$day] < $daily_fee) {
                $remaining_fee_for_day = $daily_fee - $week_data[$day];
                $pay_today = min($remaining_fee_for_day, $amount_paid);
                
                // Update the day-wise payment
                $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ?, payment_type = ? WHERE admission_no = ? AND week_number = ?");
                $stmt->bind_param("dddssi", $pay_today, $pay_today, $pay_today, $payment_type, $admission_no, $week_number);
                $stmt->execute();
                $stmt->close();
                
                $amount_paid -= $pay_today;
                $balance -= $pay_today;
            }
        }
    }

    // Handle overpayment (amount paid more than the current week's balance)
    while ($amount_paid > 0) {
        $week_number += 1;
        $next_week_balance = $total_weekly_fee;
        
        // Insert a new week record
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, payment_type) VALUES (?, 0, ?, ?, ?)");
        $stmt->bind_param("sdis", $admission_no, $next_week_balance, $week_number, $payment_type);
        $stmt->execute();
        $stmt->close();

        // Distribute the extra payment to the new week's weekdays
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
        $stmt->bind_param("si", $admission_no, $week_number);
        $stmt->execute();
        $next_week_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $balance = $total_weekly_fee;

        foreach ($days as $day) {
            if ($amount_paid <= 0 || $balance <= 0) break;

            $remaining_fee_for_day = $daily_fee - $next_week_data[$day];
            $pay_today = min($remaining_fee_for_day, $amount_paid);

            $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ?, payment_type = ? WHERE admission_no = ? AND week_number = ?");
            $stmt->bind_param("dddssi", $pay_today, $pay_today, $pay_today, $payment_type, $admission_no, $week_number);
            $stmt->execute();
            $stmt->close();

            $amount_paid -= $pay_today;
            $balance -= $pay_today;
        }
    }

    echo "<script>alert('Payment recorded successfully!'); window.location.href='lunch-fee.php';</script>";
}
$conn->close();
?>


<!DOCTYPE html>
<html>
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
            <div class="form-group">
                <label>Enter Admission Number:</label>
                <input type="text" name="admission_no" required>
            </div>

            <div class="form-group">
                <label>Enter Amount Paid:</label>
                <input type="number" name="amount_paid" required>
            </div>
            <br>
            <div class="form-group">
                <label>Payment Type:</label>
            <select name="payment_type" required>
                <option value="">select payment</option>
                <option value="Cash">Cash</option>
                <option value="Liquid Money">Liquid Money</option>
            </select>
            </div>
            <br>
            <button type="submit" class="add-student-btn"><i class='bx bx-cart-add'></i>Pay Now</button>
        </form>
    </div>

    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard <i class='bx bx-exit'></i></a>
    </div>
</body>
</html>
