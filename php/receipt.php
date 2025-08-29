<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
require 'db.php';

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
$total_balance = 0; // For accumulating balances from other fees

// === Fetch total school fee balance from school_fees table ===
$balance_result = $conn->query("
    SELECT SUM(IFNULL(balance, 0)) AS total_balance 
    FROM school_fees 
    WHERE admission_no = '$admission_no'
");
$data = $balance_result->fetch_assoc();
$school_fee_balance = $data['total_balance'] ?? 0;

function formatAmount($amt){ return number_format($amt, 2); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Receipt <?= htmlspecialchars($receipt_no) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet" />
<link rel="website icon" type="png" href="../images/school-logo.jpg">
<style>
  body {
    font-family: Arial, sans-serif;
    font-size: 14px;
    text-align: center;
    background: #fff;
    margin: 20px auto;
  }
  .receipt-box {
    border: 1px dotted black;
    padding: 15px;
    width: 350px;
    margin: 0 auto;
    text-align: left;
  }
  .receipt-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-left {
  text-align: left;
}

.header-right .logo {
  max-width: 60px; /* adjust as needed */
  height: auto;
}

  .logo {
    display: block;
    margin: 0 auto 10px auto;
    max-width: 100px;
  }
  h2, h3 {
    margin: 5px 0;
    text-align: center;
  }
  table {
    width: 100%;
    margin-top: 10px;
    border-collapse: collapse;
  }
  th, td {
    padding: 5px;
    border-bottom: 1px dotted black;
    text-align: left;
    font-size: 13px;
  }
  th {
    font-weight: bold;
  }
  .center {
    text-align: center;
  }
  .phone {
    text-align: center;
    font-size: 14px;
  }
  .total {
    font-weight: bold;
    border-top: 1px dotted black;
    padding-top: 8px;
    margin-top: 5px;
    text-align: center;
    font-size: 14px;
  }
  .approve {
  margin-top: 20px;
  font-weight: bold;
  text-align: center;
  font-size: 13px;
}

  .thanks {
    margin-top: 15px;
    font-weight: bold;
    text-align: center;
    font-size: 14px;
  }
</style>
</head>
<body>
    <div class="receipt-box">
      <div class="receipt-header">
        <div class="header-left">
          <h2>KAYRON JUNIOR SCHOOL</h2>
          <div class="phone">Tel: 0711686866 / 0731156576</div>
          <h3>Official Receipt</h3>
        </div>
        <div class="header-right">
          <img src="../images/school-logo.jpg" alt="School Logo" class="logo" />
        </div>
      </div>

    <table>
      <tr><th>Date:</th><td><?= date('d-m-Y') ?></td></tr>
      <tr><th>Receipt No:</th><td><?= htmlspecialchars($receipt_no) ?></td></tr>
      <tr><th>Admission No:</th><td><?= htmlspecialchars($admission_no) ?></td></tr>
      <tr><th>Student Name:</th><td><?= htmlspecialchars($name) ?></td></tr>
    </table>

    <table>
      <thead>
        <tr>
          <th>Fee Type</th>
          <th>Amount (KES)</th>
          <th>Balance (KES)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($school_fees as $sf): 
            $total_paid += $sf['amount_paid'];

            // === Only show Carry Forward in this section
            if ($school_fee_balance < 0) {
                $balance_display = 'Carry Forward (KES ' . formatAmount(abs($school_fee_balance)) . ')';
            } elseif ($school_fee_balance == 0) {
                $balance_display = 'Cleared';
            } else {
                $balance_display = formatAmount($school_fee_balance);
            }
        ?>
        <tr>
          <td>School Fee</td>
          <td><?= formatAmount($sf['amount_paid']) ?></td>
          <td><?= $balance_display ?></td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($lunch_fees as $lf): 
            $total_paid += $lf['amount_paid'];
        ?>
        <tr>
          <td>Lunch Fee</td>
          <td><?= formatAmount($lf['amount_paid']) ?></td>
          <td>N/A</td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($others as $o): 
            $total_paid += $o['amount_paid'];
            $balance = isset($o['balance']) ? $o['balance'] : 0;
            $total_balance += $balance;
        ?>
        <tr>
          <td><?= htmlspecialchars($o['fee_type']) ?></td>
          <td><?= formatAmount($o['amount_paid']) ?></td>
          <td><?= formatAmount($balance) ?></td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($uniforms as $u): 
            $total_paid += $u['amount_paid'];
            $balance = isset($u['balance']) ? $u['balance'] : 0;
            $total_balance += $balance;
        ?>
        <tr>
          <td>Uniform (<?= htmlspecialchars($u['uniform_type'] . ' - ' . $u['size']) ?>)</td>
          <td><?= formatAmount($u['amount_paid']) ?></td>
          <td><?= formatAmount($balance) ?></td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($books as $b): 
            $total_paid += $b['amount_paid'];
            $balance = isset($b['balance']) ? $b['balance'] : 0;
            $total_balance += $balance;
        ?>
        <tr>
          <td>Book (<?= htmlspecialchars($b['book_name']) ?>)</td>
          <td><?= formatAmount($b['amount_paid']) ?></td>
          <td><?= formatAmount($balance) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="total">Total Paid: KES <?= formatAmount($total_paid) ?></div>

    <div class="total">Total Balance: 
      <?php 
        // Only add positive school fee balance
        $adjusted_school_fee_balance = $school_fee_balance > 0 ? $school_fee_balance : 0;
        $final_balance = $adjusted_school_fee_balance + $total_balance;

        if ($final_balance == 0) {
            echo 'Cleared';
        } else {
            echo 'KES ' . formatAmount($final_balance);
        }
      ?>
    </div>
    <div class="approve">Approved By: <?= htmlspecialchars($_SESSION['username']) ?></div>
    <div class="thanks">Thank you for trusting in our school.<br>Always working to output the best!</div>
  </div>

<script>window.print();</script>
</body>
</html>
