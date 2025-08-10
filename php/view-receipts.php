<?php
require 'db.php';

$student_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$groupedReceipts = [];

if ($student_name !== '') {
    $likeName = "%" . $conn->real_escape_string($student_name) . "%";

    $query = "
        SELECT receipt_number, payment_date AS date, 'Lunch fees' AS category, amount_paid, name, admission_no, payment_type FROM lunch_fee_transactions WHERE name LIKE '$likeName'
        UNION
        SELECT receipt_number, payment_date, 'School fees', amount_paid, name, admission_no, payment_type FROM school_fee_transactions WHERE name LIKE '$likeName'
        UNION
        SELECT 
            ot.receipt_number, 
            ot.transaction_date AS date, 
            o.fee_type AS category, 
            ot.amount, 
            o.name, 
            o.admission_no, 
            ot.payment_type
        FROM other_transactions ot
        JOIN others o ON ot.others_id = o.id
        WHERE o.name LIKE '$likeName'
        UNION
        SELECT receipt_number, purchase_date, 'Book Purchase', amount_paid, name, admission_no, payment_type FROM book_purchases WHERE name LIKE '$likeName'
        UNION
        SELECT receipt_number, purchase_date, 'Uniform Purchase', amount_paid, name, admission_no, payment_type FROM uniform_purchases WHERE name LIKE '$likeName'
        ORDER BY date DESC
    ";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $rn = $row['receipt_number'];
        $row['formatted_date'] = date('d/m/y h:i A', strtotime($row['date']));
        if (!isset($groupedReceipts[$rn])) {
            $groupedReceipts[$rn] = [
                'student_name' => $row['name'],
                'admission_no' => $row['admission_no'],
                'payment_method' => $row['payment_type'],
                'date' => $row['formatted_date'],
                'items' => [],
                'total' => 0
            ];
        }
        // Use 'amount' if set, else fallback to 'amount_paid'
        $amount = isset($row['amount']) ? $row['amount'] : $row['amount_paid'];

        $groupedReceipts[$rn]['items'][] = [
            'category' => $row['category'],
            'amount' => $amount
        ];
        $groupedReceipts[$rn]['total'] += $amount;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Receipts</title>
    <link rel="stylesheet" href="../style/style.css">
    <style>
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .receipt-table th, .receipt-table td {
            border: 1px dotted #000;
            padding: 8px;
            text-align: left;
        }
        .print-btn {
            padding: 5px 10px;
            background-color: #009688;
            color: white;
            border: none;
            cursor: pointer;
        }
        .search-box {
            margin-bottom: 20px;
            position: relative;
        }
        #suggestions {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
        }
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }
        .suggestion-item:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>

<main class="content">
    <div class="container-wrapper">
        <h2>Search Student Receipts</h2>
        <form method="GET" class="search-box" autocomplete="off">
            <input type="text" name="name" id="student-search" placeholder="Enter student name..." value="<?= htmlspecialchars($student_name) ?>" />
            <div id="suggestions"></div>
        </form>

        <?php if (!empty($groupedReceipts)): ?>
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Fee Types</th>
                        <th>Total Paid</th>
                        <th>Payment Date</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedReceipts as $receipt_number => $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($receipt_number) ?></td>
                            <td>
                                <?php foreach ($data['items'] as $item): ?>
                                    <?= htmlspecialchars($item['category']) ?> - KES <?= number_format($item['amount'], 2) ?><br>
                                <?php endforeach; ?>
                            </td>
                            <td><strong>KES <?= number_format($data['total'], 2) ?></strong></td>
                            <td><?= $data['date'] ?></td>
                            <td>
                                <button class="print-btn" onclick='printReceipt(<?= json_encode($receipt_number) ?>, <?= json_encode($data) ?>)'>Print</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($student_name): ?>
            <p>No receipts found for this student.</p>
        <?php endif; ?>
    </div>
</main>
</div>
<?php include '../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function printReceipt(receiptNumber, data) {
    const win = window.open('', '', 'width=600,height=700');
    let itemsHTML = '';
    let total = 0;

    data.items.forEach(item => {
        itemsHTML += `<tr><td>${item.category}</td><td>KES ${parseFloat(item.amount).toFixed(2)}</td></tr>`;
        total += parseFloat(item.amount);
    });

    win.document.write(`
        <html>
        <head>
            <title>Receipt</title>
            <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
            <style>
                body { font-family: Arial, sans-serif; font-size: 14px; text-align: center; }
                .receipt-box {
                    border: 1px dotted black;
                    padding: 15px;
                    width: 350px;
                    margin: 0 auto;
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
                }
                .center { text-align: center; }
                .barcode {
                    margin-top: 10px;
                    font-family: 'Libre Barcode 39', monospace;
                    font-size: 32px;
                    letter-spacing: 2px;
                }
                .total {
                    font-weight: bold;
                    border-top: 1px dotted black;
                    padding-top: 8px;
                    margin-top: 5px;
                }
                .thanks {
                    margin-top: 10px;
                    font-weight: bold;
                }
                .approved {
                    margin-top: 10px;
                    font-style: italic;
                }
            </style>
        </head>
        <body>
            <div class="receipt-box">
                <h2>KAYRON JUNIOR SCHOOL</h2>
                <div>Tel: 0703373151 / 0740047243</div>
                <h3>Official Receipt</h3>
                <table>
                    <tr><th>Date:</th><td>${data.date.split(' ')[0].replace(/\//g, '-')}</td></tr>
                    <tr><th>Receipt No:</th><td>${receiptNumber}</td></tr>
                    <tr><th>Admission No:</th><td>${data.admission_no}</td></tr>
                    <tr><th>Student Name:</th><td>${data.student_name}</td></tr>
                    <tr><th>Payment Method:</th><td>${data.payment_method}</td></tr>
                </table>
                <h4 class="center">Fee Payments</h4>
                <table>
                    <tr><th>Fee Type</th><th>Amount</th></tr>
                    ${itemsHTML}
                </table>
                <div class="total">TOTAL: KES ${total.toFixed(2)}</div>
                <div class="approved">Payment Approved By: <strong>emmaculate</strong></div>
                <div class="thanks">Thank you for trusting in our school.<br>Always working to output the best!</div>
                <div class="barcode">*${receiptNumber}*</div>
            </div>
        </body>
        </html>
    `);
    win.document.close();
    win.print();
}

$('#student-search').on('input', function () {
    const query = $(this).val().trim();
    if (query.length === 0) {
        $('#suggestions').empty();
        return;
    }

    $.ajax({
        url: 'search-students.php',
        method: 'POST',
        dataType: 'json',
        data: { query },
        success: function (response) {
            $('#suggestions').empty();
            if (Array.isArray(response) && response.length > 0) {
                response.forEach(function (student) {
                    $('#suggestions').append(`
                        <div class="suggestion-item" data-name="${student.name}">
                            ${student.name} - ${student.admission_no}
                        </div>
                    `);
                });
            } else {
                $('#suggestions').append('<div class="suggestion-item">No records found</div>');
            }
        }
    });
});

$('#suggestions').on('click', '.suggestion-item', function () {
    const name = $(this).data('name');
    $('#student-search').val(name);
    $('#suggestions').empty();
    $('form').submit();
});

$(document).on('click', function (e) {
    if (!$(e.target).closest('.search-box').length) {
        $('#suggestions').empty();
    }
});
</script>
</body>
</html>
