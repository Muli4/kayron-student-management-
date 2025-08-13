<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
require 'db.php';

$student_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$groupedReceipts = [];

if ($student_name !== '') {
    $likeName = "%" . $conn->real_escape_string($student_name) . "%";

    $query = "
        SELECT receipt_number, payment_date AS date, 'Lunch fees' AS category, amount_paid, name, admission_no, payment_type 
        FROM lunch_fee_transactions 
        WHERE name LIKE '$likeName'
        
        UNION
        
        SELECT receipt_number, payment_date, 'School fees', amount_paid, name, admission_no, payment_type 
        FROM school_fee_transactions 
        WHERE name LIKE '$likeName'
        
        UNION
        
        SELECT 
            ot.receipt_number, 
            ot.transaction_date AS date, 
            o.fee_type AS category, 
            ot.amount_paid, 
            o.name, 
            o.admission_no, 
            ot.payment_type
        FROM other_transactions ot
        JOIN others o ON ot.others_id = o.id
        WHERE o.name LIKE '$likeName'
        
        UNION
        
        SELECT receipt_number, purchase_date, 'Book Purchase', amount_paid, name, admission_no, payment_type 
        FROM book_purchases 
        WHERE name LIKE '$likeName'
        
        UNION
        
        SELECT receipt_number, purchase_date, 'Uniform Purchase', amount_paid, name, admission_no, payment_type 
        FROM uniform_purchases 
        WHERE name LIKE '$likeName'
        
        ORDER BY date DESC
    ";

    // Run query and show error if it fails
    $result = $conn->query($query);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $rn = $row['receipt_number'];
        $row['formatted_date'] = date('d/m/y h:i A', strtotime($row['date']));

        if (!isset($groupedReceipts[$rn])) {
            $groupedReceipts[$rn] = [
                'student_name'   => $row['name'],
                'admission_no'   => $row['admission_no'],
                'payment_method' => $row['payment_type'],
                'date'           => $row['formatted_date'],
                'items'          => [],
                'total'          => 0
            ];
        }

        // Always prefer amount_paid (since all tables use it now)
        $amount = $row['amount_paid'];

        $groupedReceipts[$rn]['items'][] = [
            'category' => $row['category'],
            'amount'   => $amount
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
<style>
    /* Container */
.container-wrapper {
  max-width: 900px;
  margin: 30px auto;
  padding: 0 20px;
}

/* Headings */
.container-wrapper h2 {
  text-align: center;
  margin-bottom: 20px;
  font-weight: 700;
  font-size: 1.8rem;
  color: #222;
}

/* Search box */
.search-box {
  position: relative;
  max-width: 500px;
  margin: 0 auto 25px auto;
}

.search-box input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 1.5px solid #aaa;
  border-radius: 6px;
  outline: none;
  transition: border-color 0.3s ease;
}

.search-box input[type="text"]:focus {
  border-color: #0056b3;
  box-shadow: 0 0 5px rgba(0,86,179,0.5);
}

/* Suggestions dropdown */
#suggestions {
  position: absolute;
  width: 100%;
  background: white;
  border: 1px solid #aaa;
  border-top: none;
  max-height: 180px;
  overflow-y: auto;
  z-index: 1000;
  border-radius: 0 0 6px 6px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.suggestion-item {
  padding: 8px 12px;
  cursor: pointer;
  font-size: 0.95rem;
  color: #333;
}

.suggestion-item:hover {
  background-color: #007bff;
  color: white;
}

/* Receipt table */
.receipt-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  font-size: 0.95rem;
  box-shadow: 0 0 8px rgba(0,0,0,0.05);
}

.receipt-table th,
.receipt-table td {
  padding: 10px 12px;
  border: 1px solid #ddd;
  vertical-align: top;
  text-align: left;
}

.receipt-table th {
  background-color: #f5f5f5;
  font-weight: 600;
  color: #222;
}

/* Print button */
.print-btn {
  background-color: #007bff;
  border: none;
  color: white;
  padding: 7px 14px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s ease;
}

.print-btn:hover {
  background-color: #0056b3;
}

/* Responsive */
@media (max-width: 640px) {
  .container-wrapper {
    padding: 0 10px;
  }

  .receipt-table th,
  .receipt-table td {
    font-size: 0.9rem;
    padding: 8px 6px;
  }

  .print-btn {
    padding: 6px 10px;
    font-size: 0.9rem;
  }

  .search-box {
    max-width: 100%;
  }
}

</style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>

<main class="content">
    <div class="container-wrapper">
        <h2>Student Receipts</h2>
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
<script>
document.addEventListener("DOMContentLoaded", function () {
    /* ===== Real-time clock ===== */
    function updateClock() {
        const clockElement = document.getElementById('realTimeClock');
        if (clockElement) { // removed window.innerWidth check to show clock on all devices
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            clockElement.textContent = timeString;
        }
    }
    updateClock(); 
    setInterval(updateClock, 1000);

    /* ===== Dropdowns: only one open ===== */
    document.querySelectorAll(".dropdown-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const parent = btn.parentElement;

            document.querySelectorAll(".dropdown").forEach(drop => {
                if (drop !== parent) {
                    drop.classList.remove("open");
                }
            });

            parent.classList.toggle("open");
        });
    });

    /* ===== Sidebar toggle for mobile ===== */
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.toggle-btn');
    const overlay = document.createElement('div');
    overlay.classList.add('sidebar-overlay');
    document.body.appendChild(overlay);

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    /* ===== Close sidebar on outside click ===== */
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });

    /* ===== Auto logout after 30 seconds inactivity (no alert) ===== */
    let logoutTimer;

    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            // Silent logout - redirect to logout page
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 30 seconds
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    // Start the timer when page loads
    resetLogoutTimer();
});
</script>
</body>
</html>
