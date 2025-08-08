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
    <link rel="website icon" type="png" href="photos/Logo.jpg">
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
/* === Tracker Container & Layout === */
.tracker-container { 
  display: flex; 
  flex-direction: column; 
  align-items: center; 
  width: 100%;
}

/* === Tracker Section === */
.tracker {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: #f0f4f8;
  border-radius: 8px;
  margin-top: 20px;
  width: 70%;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.tracker label {
  font-weight: bold;
}
.tracker select, 
.tracker input {
  padding: 6px 10px;
  border: 1px solid #ccc;
  border-radius: 5px;
  font-size: 14px;
  width: 200px;
}
.tracker button {
  padding: 7px 15px;
  background: #28a745;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
}
.tracker button:hover {
  background: #218838;
}

/* === Search Wrapper & Suggestions === */
.search-wrapper {
  position: relative;
  display: inline-block;
  width: 200px; /* match input width */
  overflow: visible; /* allow dropdown to escape */
}

#suggestions {  
  position: absolute;
  top: 100%; /* directly below input */
  left: 0;
  width: 100%; /* match input width */
  background: #fff;
  border: 1px solid #ccc;
  border-top: none;
  border-bottom-left-radius: 5px;
  border-bottom-right-radius: 5px;
  max-height: 200px;
  overflow-y: auto;
  display: none;
  z-index: 99999; /* always on top */
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.suggestion-item { 
  padding: 6px 10px; 
  cursor: pointer; 
  font-size: 14px;
  color: #333;
}
.suggestion-item:hover { 
  background-color: #f0f0f0; 
}

/* === Message Box === */
#message-box {
  width: 70%;
  text-align: center;
  font-size: 1.1em;
  font-weight: bold;
  padding: 12px;
  border-radius: 6px;
  display: none;
  margin-top: 15px;
}
#message-box .paid {
  color: #27ae60;
  background: #e8f8f2;
  border: 1px solid #27ae60;
}
#message-box .unpaid {
  color: #e74c3c;
  background: #fdecea;
  border: 1px solid #e74c3c;
}

/* === Tracker Results Table === */
#tracker-results table {
  border-collapse: collapse; 
  width: 100%; /* wide table */
  margin-top: 20px; 
}
#tracker-results th, 
#tracker-results td {
  border: 1px solid #ccc; 
  padding: 8px; 
  text-align: center;
}
#tracker-results th {
  background: #f0f4f8;
  font-weight: bold;
}

/* === Payment Status Colors === */
.paid {
  color: green;
  font-weight: bold;
} 
.partial {
  color: orange;
  font-weight: bold;
}
.unpaid {
  color: red;
  font-weight: bold;
}

/* === Current Week/Day Highlight === */
.current-week {
  background-color: lightblue;
}
.current-day {
  background-color: #ffeb99;
  font-weight: bold;
}

/* === Summary Section === */
.summary {
  text-align: center;
  font-weight: bold;
  margin-top: 20px;
  font-size: 1.1em;
}

/* === Pagination === */
.pagination {
  text-align: center;
  margin-top: 15px;
}
.pagination a {
  margin: 0 10px;
  padding: 6px 12px;
  border: 1px solid #ccc;
  text-decoration: none;
  cursor: pointer;
}
.pagination a.disabled {
  color: #999;
  pointer-events: none;
}
</style>