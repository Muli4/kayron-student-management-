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
    
    // Get current week record or create new
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? ORDER BY week_number DESC LIMIT 1");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $week_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$week_data || $week_data['balance'] == 0) {
        // No record or last week fully paid - start new week
        $week_number = $week_data ? $week_data['week_number'] + 1 : 1;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number) VALUES (?, 0, ?, ?)");
        $stmt->bind_param("sdi", $admission_no, $total_weekly_fee, $week_number);
        $stmt->execute();
        $stmt->close();
    } else {
        $week_number = $week_data['week_number'];
    }

    // Process payment day by day
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
    $stmt->bind_param("si", $admission_no, $week_number);
    $stmt->execute();
    $week_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $balance = $week_data['balance'];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    
    while ($amount_paid > 0 && $balance > 0) {
        foreach ($days as $day) {
            if ($balance <= 0 || $amount_paid <= 0) break;

            if ($week_data[$day] < $daily_fee) {
                $remaining_fee_for_day = $daily_fee - $week_data[$day];
                $pay_today = min($remaining_fee_for_day, $amount_paid);
                
                // Update the day-wise payment
                $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ? WHERE admission_no = ? AND week_number = ?");
                $stmt->bind_param("dddsd", $pay_today, $pay_today, $pay_today, $admission_no, $week_number);
                $stmt->execute();
                $stmt->close();
                
                $amount_paid -= $pay_today;
                $balance -= $pay_today;
            }
        }
    }

    // Handle overpayment (amount paid more than 350)
    if ($amount_paid > 0) {
        // Insert the overpayment amount as a normal payment for the next week
        $next_week_number = $week_number + 1;
        $next_week_balance = $total_weekly_fee - $amount_paid;

        // Insert the overpayment into the next week's balance
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdid", $admission_no, $amount_paid, $next_week_balance, $next_week_number);
        $stmt->execute();
        $stmt->close();

        // Now distribute the overpayment across the weekdays for the next week
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $remaining_amount = $amount_paid;

        // Get the next week's record to apply the overpayment
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
        $stmt->bind_param("si", $admission_no, $next_week_number);
        $stmt->execute();
        $next_week_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Distribute the overpayment across each day
        foreach ($days as $day) {
            if ($remaining_amount > 0) {
                $remaining_fee_for_day = $daily_fee - $next_week_data[$day]; // Calculate remaining fee for the day
                $pay_today = min($remaining_fee_for_day, $remaining_amount); // Calculate what we can pay today

                // Update the day-wise payment for next week
                $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ? WHERE admission_no = ? AND week_number = ?");
                $stmt->bind_param("dddsd", $pay_today, $pay_today, $pay_today, $admission_no, $next_week_number);
                $stmt->execute();
                $stmt->close();

                // Update remaining overpayment amount
                $remaining_amount -= $pay_today;
            }
        }

        echo "<script>alert('Payment recorded successfully!'); window.location.href='view-details.php';</script>";
    } else {
        echo "<script>alert('Payment recorded successfully!'); window.location.href='view-details.php';</script>";
    }
}
$conn->close();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Lunch Fee Payment</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
    <div class="heading-all">
        <h2 class="title">Kayron Junior School</h2>
    </div>
    <div class="add-heading">
        <h2>Lunch Fees</h2>
    </div>
    <div class="lunch-form" >
    <form method="POST">
        <label >Enter Admission Number:</label>
        <input type="text" name="admission_no" required>
        <br>
        <label>Enter Amount Paid:</label>
        <input type="number" name="amount_paid" required>
        <br>
        <button type="submit" class="add-student-btn"><i class='bx bx-cart-add'></i>Pay Now</button>
    </form>
    </div>

    <div class="back-dash">
        <a href="./dashboard.php">Back to dashboard <i class='bx bx-exit'></i></a>
    </div>
</body>
</html>
