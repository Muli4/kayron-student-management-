<?php
require 'db.php'; // Include database connection

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$total_book_payments = 0;
$total_uniform_payments = 0;
$total_school_fee_payments = 0;
$total_lunch_payments = 0;
$total_others_payments = 0; // Added Others Table
$grand_total = 0;

if (!empty($start_date) && !empty($end_date)) {
    // Fetch payments for book purchases
    $stmt = $conn->prepare("SELECT SUM(amount_paid) AS total FROM book_purchases WHERE purchase_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_book_payments = $result['total'] ?? 0;

    // Fetch payments for uniform purchases
    $stmt = $conn->prepare("SELECT SUM(amount_paid) AS total FROM uniform_purchases WHERE purchase_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_uniform_payments = $result['total'] ?? 0;

    // Fetch payments for school fees
    $stmt = $conn->prepare("SELECT SUM(amount_paid) AS total FROM school_fee_transactions WHERE payment_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_school_fee_payments = $result['total'] ?? 0;

    // Fetch payments for lunch fees
    $stmt = $conn->prepare("SELECT SUM(amount_paid) AS total FROM lunch_fee_transactions WHERE payment_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_lunch_payments = $result['total'] ?? 0;

    // Fetch payments for others
    $stmt = $conn->prepare("SELECT SUM(amount) AS total FROM others WHERE payment_date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_others_payments = $result['total'] ?? 0;

    // Calculate Grand Total
    $grand_total = $total_book_payments + $total_uniform_payments + $total_school_fee_payments + $total_lunch_payments + $total_others_payments;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Payment Report</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
<div class="container">
    <h2>Weekly Payment Report</h2>

    <!-- Date Selection Form -->
    <form method="GET" action="">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date); ?>" required>

        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date); ?>" required>

        <button type="submit">Generate Report</button>
    </form>

    <?php if (!empty($start_date) && !empty($end_date)): ?>
        <h3>Payments from <?= htmlspecialchars($start_date); ?> to <?= htmlspecialchars($end_date); ?></h3>
        <table border="1">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Book Purchases</td>
                    <td><?= number_format($total_book_payments, 2); ?></td>
                </tr>
                <tr>
                    <td>Uniform Purchases</td>
                    <td><?= number_format($total_uniform_payments, 2); ?></td>
                </tr>
                <tr>
                    <td>School Fees</td>
                    <td><?= number_format($total_school_fee_payments, 2); ?></td>
                </tr>
                <tr>
                    <td>Lunch Fees</td>
                    <td><?= number_format($total_lunch_payments, 2); ?></td>
                </tr>
                <tr>
                    <td>Others</td>
                    <td><?= number_format($total_others_payments, 2); ?></td>
                </tr>
                <tr>
                    <th>Total</th>
                    <th><?= number_format($grand_total, 2); ?></th>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="button-container">
    <button type="button"><a href="./dashboard.php">Back to Dashboard</a></button>
</div>

</body>
</html>
