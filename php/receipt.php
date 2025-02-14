<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Database connection failed.");
}

// Fetch logged-in user (Approver)
$approved_by = $_SESSION['username'] ?? "Admin";

$receipt_number = htmlspecialchars($_GET['receipt_number'] ?? '');
$admission_no = htmlspecialchars($_GET['admission_no'] ?? '');
$payment_type = htmlspecialchars($_GET['payment_type'] ?? '');
$date = date("d-m-Y");

// Fetch student details
$student_query = "SELECT name FROM student_records WHERE admission_no = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("s", $admission_no);
$stmt->execute();
$student_result = $stmt->get_result();
$student_name = $student_result->num_rows > 0 ? htmlspecialchars($student_result->fetch_assoc()['name']) : "Unknown";
$stmt->close();

// Fetch payments (fees or books)
$fees = isset($_GET['fees']) ? json_decode($_GET['fees'], true) : [];
$books = [];

if ($payment_type === "books") { // Only fetch books if the payment is for books
    $books = $_SESSION['receipt_books'] ?? [];
}

// Calculate the total dynamically
$total = 0;
foreach ($fees as $amount) {
    $total += (float) $amount;
}

if ($payment_type === "books") {
    foreach ($books as $book) {
        $total += (float) $book['amount_paid'];
    }
}

// Generate barcode URL (resized)
$barcode_url = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($receipt_number) . "&code=Code128&dpi=96";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; text-align: center; width: 300px; margin: auto; }
        .receipt { border: 1px dashed black; padding: 10px; }
        .title { font-weight: bold; text-transform: uppercase; }
        .line { border-top: 1px dashed black; margin: 5px 0; }
        .amount { text-align: right; }
        .total { font-weight: bold; }
        .approved { margin-top: 10px; font-style: italic; }
        .thank-you { margin-top: 10px; font-weight: bold; }
        .barcode { margin-top: 10px; display: flex; justify-content: center; }
        .barcode img { width: 200px; height: auto; }
    </style>
</head>
<body onload="window.print()">

<div class="receipt">
    <div class="title">Kayron Junior School</div>
    <small>Tel: 0703373151 / 0740047243</small>
    <div class="line"></div>
    <strong>Official Receipt</strong><br>
    Date: <strong><?php echo $date; ?></strong><br>
    Receipt No: <strong><?php echo $receipt_number; ?></strong><br>
    Admission No: <strong><?php echo $admission_no; ?></strong><br>
    Student Name: <strong><?php echo $student_name; ?></strong><br>
    Payment Method: <strong><?php echo ucfirst($payment_type); ?></strong>
    <div class="line"></div>

    <?php if (!empty($fees)): ?>
        <strong>Fee Payments</strong>
        <table width="100%">
            <tr>
                <th>Fee Type</th>
                <th>Amount</th>
            </tr>
            <?php foreach ($fees as $fee_type => $amount): ?>
                <tr>
                    <td><?php echo ucfirst(str_replace('_', ' ', $fee_type)); ?></td>
                    <td class="amount">KES <?php echo number_format($amount, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (!empty($books) && $payment_type === "books"): ?>
        <strong>Book Purchases</strong>
        <table width="100%">
            <tr>
                <th>Book</th>
                <th>Qty</th>
                <th>Amount Paid</th>
            </tr>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?php echo htmlspecialchars($book['name']); ?></td>
                    <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                    <td class="amount">KES <?php echo number_format($book['amount_paid'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="line"></div>
    <div class="total">TOTAL: KES <?php echo number_format($total, 2); ?></div>
    <div class="approved">Payment Approved By: <strong><?php echo $approved_by; ?></strong></div>
    <div class="thank-you">Thank you for trusting in our school. Always working to output the best!</div>

    <!-- Barcode (Resized to fit within the receipt) -->
    <div class="barcode">
        <img src="<?php echo $barcode_url; ?>" alt="Receipt Barcode">
    </div>
</div>

</body>
</html>

<?php exit; ?>
