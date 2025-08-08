<?php
session_start();
include 'db.php';

$daily_fee = 70;
$_SESSION['statusMessage'] = '';

// Get first registered day of the term
$stmt = $conn->prepare("SELECT day_name FROM days ORDER BY id ASC LIMIT 1");
$stmt->execute();
$active_day_result = $stmt->get_result();
$active_day_data = $active_day_result->fetch_assoc();
$stmt->close();

if (!$active_day_data) {
    $_SESSION['statusMessage'] = "<div class='error'>No days registered yet!</div>";
    header("Location: pay-lunch.php");
    exit();
}

$start_day = strtolower($active_day_data['day_name']);
$start_day_index = array_search($start_day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);

if ($start_day_index === false) {
    $_SESSION['statusMessage'] = "<div class='error'>Invalid starting day in days table!</div>";
    header("Location: pay-lunch.php");
    exit();
}

$valid_days_first_week = array_slice(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $start_day_index);
$total_weekly_fee_first_week = count($valid_days_first_week) * $daily_fee;
$valid_days_full_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
$total_weekly_fee_full_week = 5 * $daily_fee;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'Cash';
    $name = $_POST['name'] ?? '';
    $class = $_POST['class'] ?? '';
    $receipt_no = $_POST['receipt_no'] ?? '';

    if (empty($admission_no) || $amount <= 0 || empty($receipt_no)) {
        $_SESSION['statusMessage'] = "<div class='error'>Missing required payment information!</div>";
        header("Location: pay-lunch.php");
        exit();
    }

    $original_amount = $amount;

    // Get latest week
    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? ORDER BY week_number DESC LIMIT 1");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $week_data = $result->fetch_assoc();
    $stmt->close();

    $week_number = $week_data ? ($week_data['balance'] == 0 ? $week_data['week_number'] + 1 : $week_data['week_number']) : 1;
    $valid_days = $week_number == 1 ? $valid_days_first_week : $valid_days_full_week;
    $total_weekly_fee = $week_number == 1 ? $total_weekly_fee_first_week : $total_weekly_fee_full_week;

    // Insert new week if needed
    if (!$week_data || $week_data['balance'] == 0) {
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, total_amount, payment_type) VALUES (?, 0, ?, ?, ?, ?)");
        $stmt->bind_param("sdiss", $admission_no, $total_weekly_fee, $week_number, $total_weekly_fee, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // Distribute payment
    while ($amount > 0) {
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND week_number = ?");
        $stmt->bind_param("si", $admission_no, $week_number);
        $stmt->execute();
        $week_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $balance = $week_data['balance'];

        foreach ($valid_days as $day) {
            if ($balance <= 0 || $amount <= 0) break;

            if ($week_data[$day] < $daily_fee) {
                $remaining_fee = $daily_fee - $week_data[$day];
                $pay_today = min($remaining_fee, $amount);

                $stmt = $conn->prepare("UPDATE lunch_fees SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ? WHERE admission_no = ? AND week_number = ?");
                $stmt->bind_param("dddsi", $pay_today, $pay_today, $pay_today, $admission_no, $week_number);
                $stmt->execute();
                $stmt->close();

                $amount -= $pay_today;
                $balance -= $pay_today;
            }
        }

        if ($amount > 0) {
            $week_number++;
            $valid_days = $valid_days_full_week;
            $total_weekly_fee = $total_weekly_fee_full_week;

            $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, total_paid, balance, week_number, total_amount, payment_type) VALUES (?, 0, ?, ?, ?, ?)");
            $stmt->bind_param("sdiss", $admission_no, $total_weekly_fee, $week_number, $total_weekly_fee, $payment_type);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Save transaction
    $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_no, $original_amount, $payment_type);
    $stmt->execute();
    $stmt->close();

    $_SESSION['statusMessage'] = "<div class='success'>Lunch payment recorded! Receipt: <strong>$receipt_no</strong></div>";
    header("Location: pay-lunch.php");
    exit();
}

$conn->close();
?>
