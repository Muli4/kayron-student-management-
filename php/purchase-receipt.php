<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php'; // Database connection

$approved_by = $_SESSION['username'] ?? "Admin";

$receipt_number = htmlspecialchars($_GET['receipt_number'] ?? '');
$admission_no = htmlspecialchars($_GET['admission_no'] ?? '');
$payment_type = htmlspecialchars($_GET['payment_type'] ?? '');
$date = date("d-m-Y");

// Fetch student name
$student_name = "Unknown";
$student_query = "SELECT name FROM student_records WHERE admission_no = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("s", $admission_no);
$stmt->execute();
$student_result = $stmt->get_result();
if ($student_result->num_rows > 0) {
    $student_name = htmlspecialchars($student_result->fetch_assoc()['name']);
}
$stmt->close();

// Safely decode fees JSON to array, fallback to empty array if invalid
$fees = json_decode($_GET['fees'] ?? '[]', true);
if (!is_array($fees)) {
    $fees = [];
}

$books = [];
$uniforms = [];

// Fetch book purchase data for this receipt
$book_query = "SELECT book_name, quantity, amount_paid, balance FROM book_purchases WHERE receipt_number = ?";
$stmt = $conn->prepare($book_query);
$stmt->bind_param("s", $receipt_number);
$stmt->execute();
$book_result = $stmt->get_result();
while ($row = $book_result->fetch_assoc()) {
    $books[] = [
        'name' => $row['book_name'],
        'quantity' => $row['quantity'],
        'amount_paid' => $row['amount_paid'],
        'balance' => $row['balance']
    ];
}
$stmt->close();

// Fetch uniform purchase data for this receipt
$uniform_query = "SELECT uniform_type, quantity, amount_paid, balance FROM uniform_purchases WHERE receipt_number = ?";
$stmt = $conn->prepare($uniform_query);
$stmt->bind_param("s", $receipt_number);
$stmt->execute();
$uniform_result = $stmt->get_result();
while ($row = $uniform_result->fetch_assoc()) {
    $uniforms[] = [
        'uniform_type' => $row['uniform_type'],
        'quantity' => $row['quantity'],
        'amount_paid' => $row['amount_paid'],
        'balance' => $row['balance']
    ];
}
$stmt->close();

// Calculate totals
$total_paid = 0;
$total_balance = 0;

foreach ($fees as $amount) {
    $total_paid += (float)$amount;
}

foreach ($books as $book) {
    $total_paid += (float)$book['amount_paid'];
    $total_balance += (float)$book['balance'];
}

foreach ($uniforms as $uniform) {
    $total_paid += (float)$uniform['amount_paid'];
    $total_balance += (float)$uniform['balance'];
}

$barcode_url = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($receipt_number) . "&code=Code128&dpi=96";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Receipt</title>
    <style>
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 14px;
  width: 320px;
  margin: 20px auto;
  color: #333;
  background: #fff;
  -webkit-print-color-adjust: exact; /* ensure colors print */
}

.receipt {
  border: 1px dotted black;
  padding: 15px 20px;
  box-shadow: 0 0 8px rgba(0,0,0,0.1);
  background: #fff;
}

.title {
  font-weight: 700;
  font-size: 1.3em;
  text-transform: uppercase;
  margin-bottom: 5px;
  color: #1a1a1a;
  letter-spacing: 1px;
}

.receipt small {
  display: block;
  margin-bottom: 10px;
  color: #666;
  font-size: 0.85em;
}

.line {
  border-top: 1px dashed #999;
  margin: 10px 0;
}

strong {
  color: #222;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin: 10px 0;
  font-size: 0.9em;
}

th, td {
  border: 1px solid #ccc;
  padding: 6px 8px;
  text-align: left;
}

th {
  background-color: #f7f7f7;
  font-weight: 600;
  color: #555;
}

.amount {
  text-align: right;
  color: #222;
  font-variant-numeric: tabular-nums;
}

.total {
  font-weight: 700;
  font-size: 1.1em;
  margin-top: 15px;
  border-top: 2px solid #444;
  padding-top: 8px;
  color: #000;
}

.approved {
  margin-top: 15px;
  font-style: italic;
  font-size: 0.9em;
  color: #555;
}

.thank-you {
  margin-top: 12px;
  font-weight: 600;
  font-size: 0.95em;
  color: black;
}

.barcode {
  margin-top: 15px;
  display: flex;
  justify-content: center;
}

.barcode img {
  width: 180px;
  height: auto;
}

/* Print-specific tweaks */
@media print {
  body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 14px;
  width: 320px;
  margin: 20px auto;
  color: #333;
  background: #fff;
  -webkit-print-color-adjust: exact; /* ensure colors print */
}

.receipt {
  border: 1px dotted black;
  padding: 15px 20px;
  box-shadow: 0 0 8px rgba(0,0,0,0.1);
  background: #fff;
}
  .barcode img {
    width: 160px;
  }
}

    </style>
    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</head>
<body>
    <div class="receipt">
        <div class="title">Kayron Junior School</div>
        <small>Tel: 0703373151 / 0740047243</small>
        <div class="line"></div>
        <strong>Official Receipt</strong><br />
        Date: <strong><?= $date ?></strong><br />
        Receipt No: <strong><?= $receipt_number ?></strong><br />
        Admission No: <strong><?= $admission_no ?></strong><br />
        Student Name: <strong><?= $student_name ?></strong><br />
        Payment Method: <strong><?= ucfirst($payment_type) ?></strong>
        <div class="line"></div>

        <?php if (!empty($fees)): ?>
            <strong>Fee Payments</strong>
            <table>
                <tr>
                    <th>Fee Type</th>
                    <th>Amount (KES)</th>
                </tr>
                <?php foreach ($fees as $fee_type => $amount): ?>
                    <?php if ((float)$amount > 0): ?>
                    <tr>
                        <td><?= ucfirst(str_replace('_', ' ', $fee_type)) ?></td>
                        <td class="amount"><?= number_format($amount, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <div class="line"></div>
        <?php endif; ?>

        <?php if (!empty($books)): ?>
            <strong>Book Purchases</strong>
            <table>
                <tr>
                    <th>Book</th>
                    <th>Qty</th>
                    <th>Paid Now (KES)</th>
                    <th>Balance (KES)</th>
                </tr>
                <?php foreach ($books as $book): ?>
                    <?php if ((float)$book['amount_paid'] > 0): ?>
                    <tr>
                        <td><?= htmlspecialchars($book['name']) ?></td>
                        <td><?= htmlspecialchars($book['quantity']) ?></td>
                        <td class="amount"><?= number_format($book['amount_paid'], 2) ?></td>
                        <td class="amount"><?= number_format($book['balance'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <div class="line"></div>
        <?php endif; ?>

        <?php if (!empty($uniforms)): ?>
            <strong>Uniform Purchases</strong>
            <table>
                <tr>
                    <th>Uniform</th>
                    <th>Qty</th>
                    <th>Paid Now (KES)</th>
                    <th>Balance (KES)</th>
                </tr>
                <?php foreach ($uniforms as $uniform): ?>
                    <?php if ((float)$uniform['amount_paid'] > 0): ?>
                    <tr>
                        <td><?= htmlspecialchars($uniform['uniform_type']) ?></td>
                        <td><?= htmlspecialchars($uniform['quantity']) ?></td>
                        <td class="amount"><?= number_format($uniform['amount_paid'], 2) ?></td>
                        <td class="amount"><?= number_format($uniform['balance'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <div class="line"></div>
        <?php endif; ?>

        <div class="total">TOTAL PAID NOW: KES <?= number_format($total_paid, 2) ?></div>
        <div class="total">TOTAL BALANCE: KES <?= number_format($total_balance, 2) ?></div>
        <div class="approved">Payment Approved By: <strong><?= $approved_by ?></strong></div>
        <div class="thank-you">Thank you for trusting in our school. Always working to output the best!</div>

        <div class="barcode">
            <img src="<?= $barcode_url ?>" alt="Receipt Barcode" />
        </div>
    </div>

<script>
window.onload = function () {
    window.print();

    window.onafterprint = function () {
        // Instead of closing the window, redirect to purchase.php
        window.location.href = 'purchase.php';
    };
};
</script>

</body>
</html>
<?php exit; ?>
