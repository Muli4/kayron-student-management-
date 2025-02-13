<?php
// Validate required parameters
if (!isset($_GET['receipt_number'], $_GET['admission_no'], $_GET['payment_type'], $_GET['total'])) {
    die("Invalid request!");
}

// Sanitize inputs
$receipt_number = htmlspecialchars($_GET['receipt_number']);
$admission_no = htmlspecialchars($_GET['admission_no']);
$payment_type = htmlspecialchars($_GET['payment_type']);
$total = isset($_GET['total']) ? (float) $_GET['total'] : 0.00;

// Fetch student name from database
$conn = new mysqli("localhost", "root", "", "school_database");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_query = "SELECT name FROM student_records WHERE admission_no = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("s", $admission_no);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows > 0) {
    $student = $student_result->fetch_assoc();
    $student_name = htmlspecialchars($student['name']);
} else {
    $student_name = "Unknown";
}

$stmt->close();
$conn->close();

// Check if fees data is available
$fees = isset($_GET['fees']) ? json_decode($_GET['fees'], true) : null;
$date = date("d-m-Y");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            width: 300px;
            margin: auto;
        }
        .receipt {
            border: 1px dashed black;
            padding: 10px;
        }
        .title {
            font-weight: bold;
            text-transform: uppercase;
        }
        .line {
            border-top: 1px dashed black;
            margin: 5px 0;
        }
        .amount {
            text-align: right;
        }
        .total {
            font-weight: bold;
        }
        .print-btn {
            margin-top: 15px;
            padding: 8px 15px;
            background: green;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body onload="window.print()">

<div class="receipt">
    <div class="title">Kayron Junior School</div>
    <small>Tel: 971166866 / 971185676</small>
    <div class="line"></div>
    <strong>Official Receipt</strong><br>
    Date: <strong><?php echo $date; ?></strong><br>
    Receipt No: <strong><?php echo $receipt_number; ?></strong><br>
    Admission No: <strong><?php echo $admission_no; ?></strong><br>
    Student Name: <strong><?php echo $student_name; ?></strong><br>
    Payment Method: <strong><?php echo ucfirst($payment_type); ?></strong>
    <div class="line"></div>

    <table width="100%">
        <?php if ($fees && is_array($fees)): ?>
            <!-- Display fees breakdown -->
            <?php foreach ($fees as $feeType => $amount): ?>
                <tr>
                    <td><?php echo ucfirst(str_replace("_", " ", htmlspecialchars($feeType))); ?></td>
                    <td class="amount">KES <?php echo number_format((float)$amount, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Display book purchase total -->
            <tr>
                <td>Books Purchased</td>
                <td class="amount">KES <?php echo number_format($total, 2); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="line"></div>
    <div class="total">TOTAL: KES <?php echo number_format($total, 2); ?></div>

</div>

</body>
</html>
