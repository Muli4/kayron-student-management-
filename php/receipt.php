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

// Fetch student name from student_records or graduated_students
$student_name = "Unknown";
$student_query = "
    SELECT name FROM student_records WHERE admission_no = ?
    UNION
    SELECT name FROM graduated_students WHERE admission_no = ?
    LIMIT 1
";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("ss", $admission_no, $admission_no);
$stmt->execute();
$student_result = $stmt->get_result();
if ($student_result->num_rows > 0) {
    $student_name = htmlspecialchars($student_result->fetch_assoc()['name']);
}
$stmt->close();

// Decode fees from query string
$fees = json_decode($_GET['fees'] ?? '[]', true);
if (!is_array($fees)) $fees = [];

$total_paid_now = 0;
$total_balance = 0;

// Fetch updated school fees balances from the database
$fee_details = [];
foreach ($fees as $term => $amount_paid_now) {
    $amount_paid_now = (float)$amount_paid_now;
    $total_paid_now += $amount_paid_now;

    $fee_query = "SELECT balance FROM school_fees WHERE admission_no = ? AND term = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($fee_query);
    $stmt->bind_param("ss", $admission_no, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = 0.00;
    if ($row = $result->fetch_assoc()) {
        $balance = (float)$row['balance'];
    }
    $stmt->close();

    $fee_details[] = [
        'term' => $term,
        'amount_paid' => $amount_paid_now,
        'balance' => $balance
    ];
    $total_balance += $balance;
}

$barcode_url = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($receipt_number) . "&code=Code128&dpi=96";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            width: 320px;
            margin: auto;
            font-size: 14px;
            color: #333;
        }
        .receipt {
            border: 1px dashed black;
            padding: 15px;
            background: #fff;
        }
        .title {
            font-weight: bold;
            font-size: 1.2em;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            padding: 4px 6px;
            text-align: left;
            border: 1px solid #ccc;
        }
        .amount {
            text-align: right;
        }
        .total {
            font-weight: bold;
            margin-top: 10px;
            border-top: 2px solid black;
            padding-top: 5px;
        }
        .approved, .thank-you {
            margin-top: 10px;
            font-style: italic;
            text-align: center;
        }
        .barcode {
            text-align: center;
            margin-top: 15px;
        }
        .barcode img {
            width: 180px;
            height: auto;
        }
    </style>
    <script>
        window.onload = function () {
            window.print();
            window.onafterprint = function () {
                window.location.href = 'purchase.php'; // Or remove if you don't want redirect
            };
        };
    </script>
</head>
<body>
<div class="receipt">
    <div class="title">Kayron Junior School</div>
    <div style="text-align:center;">Tel: 0703373151 / 0740047243</div>
    <div class="line"></div>
    <strong>Official Receipt</strong><br>
    Date: <strong><?= $date ?></strong><br>
    Receipt No: <strong><?= $receipt_number ?></strong><br>
    Admission No: <strong><?= $admission_no ?></strong><br>
    Student Name: <strong><?= $student_name ?></strong><br>
    Payment Type: <strong><?= ucfirst($payment_type) ?></strong>
    <div class="line"></div>

    <?php if (!empty($fee_details)): ?>
        <strong>Fee Payments</strong>
        <table>
            <tr>
                <th>Fee Type</th>
                <th>Paid Now (KES)</th>
                <th>Balance (KES)</th>
            </tr>
            <?php foreach ($fee_details as $fee): ?>
                <tr>
                    <td>School Fees (<?= ucfirst($fee['term']) ?>)</td>
                    <td class="amount"><?= number_format($fee['amount_paid'], 2) ?></td>
                    <td class="amount"><?= number_format($fee['balance'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="total">TOTAL PAID NOW: KES <?= number_format($total_paid_now, 2) ?></div>
    <div class="total">TOTAL BALANCE: KES <?= number_format($total_balance, 2) ?></div>

    <div class="approved">Payment Approved By: <strong><?= htmlspecialchars($approved_by) ?></strong></div>
    <div class="thank-you">Thank you for trusting in our school. Always working to output the best!</div>

    <div class="barcode">
        <img src="<?= $barcode_url ?>" alt="Receipt Barcode">
    </div>
</div>
</body>
</html>
<?php exit; ?>
