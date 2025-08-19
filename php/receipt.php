<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "Unauthorized access"; exit();
}
include 'db.php';

$receipt_no = $_GET['receipt_no'] ?? '';
if (!$receipt_no) { echo "No receipt specified."; exit(); }

// === Fetch all transactions for this receipt ===
$school_fees = $conn->query("SELECT * FROM school_fee_transactions WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$lunch_fees = $conn->query("SELECT * FROM lunch_fee_transactions WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$others = $conn->query("SELECT * FROM others WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$uniforms = $conn->query("SELECT * FROM uniform_purchases WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$books = $conn->query("SELECT * FROM book_purchases WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);

// === Get student info from first available record ===
$student = $school_fees[0] ?? $lunch_fees[0] ?? $others[0] ?? $uniforms[0] ?? $books[0] ?? null;
if (!$student) { echo "No transactions found for this receipt."; exit(); }

$name = $student['name'];
$admission_no = $student['admission_no'];
$class = $student['class'] ?? '';
$payment_type = $student['payment_type'] ?? 'Cash';

$total_paid = 0;

function formatAmount($amt){ return number_format($amt,2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= htmlspecialchars($receipt_no) ?></title>
<style>
body { font-family: Arial; max-width:600px; margin:auto; }
.header { text-align:center; margin-bottom:20px; }
h2,h3 { margin:0; }
table { width:100%; border-collapse: collapse; margin-top:10px;}
th,td { border:1px solid #000; padding:5px; text-align:left; }
.total { font-weight:bold; }
.logo {
    max-width: 120px;
    margin-bottom: 10px;
}
</style>
</head>
<body>
<div class="header">
    <img src="../images/school-logo.jpg" alt="School Logo" class="logo" />
    <h2>Kayron Junior School</h2>
    <p>Tel: 0703373151 / 0740047243</p>
    <h3>Official Receipt</h3>
    <p>Date: <?= date('d/m/Y') ?> | Receipt No: <?= htmlspecialchars($receipt_no) ?></p>
</div>

<p><strong>Student:</strong> <?= htmlspecialchars($name) ?> | <strong>Admission No:</strong> <?= htmlspecialchars($admission_no) ?> | <strong>Class:</strong> <?= htmlspecialchars($class) ?></p>
<p><strong>Payment Method:</strong> <?= htmlspecialchars($payment_type) ?></p>

<table>
<tr><th>Fee Type</th><th>Amount</th><th>Balance</th></tr>

<?php foreach($school_fees as $sf): 
    $total_paid += $sf['amount_paid'];
    $balance = isset($sf['balance']) ? $sf['balance'] : 0; // adjust if your table has balance field
?>
<tr>
    <td>School Fee</td>
    <td><?= formatAmount($sf['amount_paid']) ?></td>
    <td><?= formatAmount($balance) ?></td>
</tr>
<?php endforeach; ?>

<?php foreach($lunch_fees as $lf): 
    $total_paid += $lf['amount_paid'];
    $balance = isset($lf['balance']) ? $lf['balance'] : 0;
?>
<tr>
    <td>Lunch Fee</td>
    <td><?= formatAmount($lf['amount_paid']) ?></td>
    <td><?= formatAmount($balance) ?></td>
</tr>
<?php endforeach; ?>

<?php foreach($others as $o): 
    $total_paid += $o['amount_paid'];
    $balance = isset($o['balance']) ? $o['balance'] : 0;
?>
<tr>
    <td><?= htmlspecialchars($o['fee_type']) ?></td>
    <td><?= formatAmount($o['amount_paid']) ?></td>
    <td><?= formatAmount($balance) ?></td>
</tr>
<?php endforeach; ?>

<?php foreach($uniforms as $u): 
    $total_paid += $u['amount_paid'];
    $balance = isset($u['balance']) ? $u['balance'] : 0;
?>
<tr>
    <td>Uniform (<?= htmlspecialchars($u['uniform_type'].'-'.$u['size']) ?>)</td>
    <td><?= formatAmount($u['amount_paid']) ?></td>
    <td><?= formatAmount($balance) ?></td>
</tr>
<?php endforeach; ?>

<?php foreach($books as $b): 
    $total_paid += $b['amount_paid'];
    $balance = isset($b['balance']) ? $b['balance'] : 0;
?>
<tr>
    <td>Book (<?= htmlspecialchars($b['book_name']) ?>)</td>
    <td><?= formatAmount($b['amount_paid']) ?></td>
    <td><?= formatAmount($balance) ?></td>
</tr>
<?php endforeach; ?>

<tr class="total"><td>Total Paid</td><td><?= formatAmount($total_paid) ?></td><td></td></tr>
</table>


<p>Thank you for your payment!</p>
<script>window.print();</script>
</body>
</html>
