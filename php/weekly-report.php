<?php
require 'db.php'; // Include database connection

// Get start and end date from user input
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : '';

$total_book_payments = 0;
$total_uniform_payments = 0;
$total_school_fee_payments = 0;
$total_lunch_payments = 0;
$total_others_payments = 0;
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
    <title>Financial Report</title>
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>
<div class="heading-all">
    <h2 class="title">Kayron Junior School</h2>
</div>

<div class="container">
    <h2>Financial Report</h2>

    <!-- Date selection form -->
    <form method="GET" action="">
        <label for="start_date">Start Date:</label>
        <input type="datetime-local" id="start_date" name="start_date" value="<?= htmlspecialchars(substr($start_date, 0, 16)); ?>" required>

        <label for="end_date">End Date:</label>
        <input type="datetime-local" id="end_date" name="end_date" value="<?= htmlspecialchars(substr($end_date, 0, 16)); ?>" required>

        <button type="submit">Filter</button>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>Category</th>
                <th>Amount (KES)</th>
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
</div>

<div class="button-container">
    <button type="button" class="back-btn"><a href="./dashboard.php">Back to Dashboard</a></button>
</div>
<footer class="footer-dash">
    <p>&copy; <?= date("Y") ?> Kayron Junior School. All Rights Reserved.</p>
</footer>

<style>
/* Basic Styles */
.container {
    flex: 1;
    width: 80%;
    margin: 20px auto;
    font-family: Arial, sans-serif;
}
.container h2{
    text-align: center;
}
form {
    text-align: center;
    margin-bottom: 20px;
}
input[type="datetime-local"] {
    padding: 8px;
    margin: 5px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}
th {
    background: #007bff;
    color: white;
}
button {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    cursor: pointer;
}
button a {
    color: white;
    text-decoration: none;
}
button:hover {
    background: #0056b3;
}
</style>

</body>
</html>
