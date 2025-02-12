<?php
// Validate required parameters
if (!isset($_GET['receipt_number'], $_GET['admission_no'], $_GET['payment_type'], $_GET['fees'], $_GET['total'])) {
    die("Invalid request!");
}

// Sanitize input
$receipt_number = htmlspecialchars($_GET['receipt_number']);
$admission_no = htmlspecialchars($_GET['admission_no']);
$payment_type = htmlspecialchars($_GET['payment_type']);
$total = isset($_GET['total']) ? (float) $_GET['total'] : 0.00;

// Decode and validate fees
$fees = json_decode($_GET['fees'], true);
if (!is_array($fees) || empty($fees)) {
    die("Invalid fees data!");
}

$date = date("Y-m-d");
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
            font-size: 12px;
            text-align: center;
            width: 250px;
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
    </style>
</head>
<body onload="window.print()">

<div class="receipt">
    <div class="title">Kayron Junior School</div>
    <small>Tel: 971166866 / 971185676</small>
    <div class="line"></div>
    <div class="line"></div>
    <strong>Official Receipt</strong><br>
    Date: <strong><?php echo $date = date("d-m-y"); ?></strong><br>
    Receipt No: <strong><?php echo $receipt_number; ?></strong><br>
    Admission No: <strong><?php echo $admission_no; ?></strong><br>
    Payment Method: <strong><?php echo ucfirst($payment_type); ?></strong>
    <div class="line"></div>
    <div class="line"></div>

    <table width="100%">
        <?php 
        foreach ($fees as $feeType => $amount): 
            if (is_numeric($feeType)) continue; // Ignore numeric indexes
        ?>
            <tr>
                <td><?php echo ucfirst(str_replace("_", " ", htmlspecialchars($feeType))); ?></td>
                <td class="amount">KES <?php echo number_format((float)$amount, 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="line"></div>
    <div class="line"></div>
    <div class="total">TOTAL: KES <?php echo number_format($total, 2); ?></div>
</div>

</body>
</html>
