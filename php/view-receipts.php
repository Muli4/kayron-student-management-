<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
require 'db.php';

$admission_no = isset($_GET['admission_no']) ? trim($_GET['admission_no']) : '';
$groupedReceipts = [];

if ($admission_no !== '') {
    $likeAdm = "%" . $conn->real_escape_string($admission_no) . "%";

    $query = "
        SELECT receipt_number, payment_date AS date, 'Lunch fees' AS category, amount_paid, name, admission_no, payment_type 
        FROM lunch_fee_transactions 
        WHERE admission_no LIKE '$likeAdm'
        
        UNION
        
        SELECT receipt_number, payment_date, 'School fees', amount_paid, name, admission_no, payment_type 
        FROM school_fee_transactions 
        WHERE admission_no LIKE '$likeAdm'
        
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
        WHERE o.admission_no LIKE '$likeAdm'
        
        UNION
        
        SELECT receipt_number, purchase_date, 'Book Purchase', amount_paid, name, admission_no, payment_type 
        FROM book_purchases 
        WHERE admission_no LIKE '$likeAdm'
        
        UNION
        
        SELECT receipt_number, purchase_date, 'Uniform Purchase', amount_paid, name, admission_no, payment_type 
        FROM uniform_purchases 
        WHERE admission_no LIKE '$likeAdm'
        
        ORDER BY date DESC
    ";

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
.container-wrapper h2 {
  text-align: center;
  margin-bottom: 20px;
  font-weight: 700;
  font-size: 1.8rem;
  color: #222;
}
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
@media (max-width: 640px) {
  .container-wrapper { padding: 0 10px; }
  .receipt-table th, .receipt-table td { font-size: 0.9rem; padding: 8px 6px; }
  .print-btn { padding: 6px 10px; font-size: 0.9rem; }
  .search-box { max-width: 100%; }
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
            <input type="text" name="admission_no" id="student-search" placeholder="Enter admission number..." value="<?= htmlspecialchars($admission_no) ?>" />
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
        <?php elseif ($admission_no): ?>
            <p>No receipts found for this admission number.</p>
        <?php endif; ?>
    </div>
</main>
</div>
<?php include '../includes/footer.php'; ?>

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
<head>
<meta charset="UTF-8">
<title>Receipt</title>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
<style>
 body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; background: #f8f8f8; margin: 0; padding: 20px; }
 .receipt-box { background: #fff; border: 1px solid #ccc; padding: 25px; max-width: 400px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
 .receipt-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
 .header-left { text-align: left; flex: 1; }
 .header-left h2 { margin: 0; font-size: 20px; color: #333; }
 .header-left .phone { font-size: 13px; color: #666; }
 .header-left h3 { margin-top: 10px; font-size: 16px; text-transform: uppercase; color: #444; }
 .logo { width: 70px; height: auto; margin-left: 10px; }
 table { width: 100%; margin-top: 10px; border-collapse: collapse; }
 th, td { padding: 6px 4px; border-bottom: 1px solid #ddd; text-align: left; }
 th { color: #555; width: 40%; }
 h4.center { text-align: center; margin: 20px 0 10px; font-size: 15px; border-top: 1px dashed #aaa; padding-top: 10px; color: #333; }
 .total { font-weight: bold; text-align: right; margin-top: 10px; border-top: 1px solid #333; padding-top: 8px; font-size: 15px; }
 .thanks { text-align: center; margin-top: 20px; font-weight: 500; color: #444; font-size: 13px; }
 @media print { body { background: none; padding: 0; } .receipt-box { box-shadow: none; border: none; padding: 10px; } .total { border-top: 1px solid #000; } }
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
         <tr><th>Date:</th><td>${data.date.split(' ')[0].replace(/\//g, '-')}</td></tr>
         <tr><th>Receipt No:</th><td>${receiptNumber}</td></tr>
         <tr><th>Admission No:</th><td>${data.admission_no}</td></tr>
         <tr><th>Student Name:</th><td>${data.student_name}</td></tr>
         <tr><th>Payment Method:</th><td>${data.payment_method}</td></tr>
     </table>

     <h4 class="center">Fee Payments</h4>
     <table>
         <tr><th>Fee Type</th><th>Amount (KES)</th></tr>
         ${itemsHTML}
     </table>

     <div class="total">TOTAL: KES ${total.toFixed(2)}</div>

     <div class="thanks">
         Thank you for trusting in our school.<br>
         Always working to output the best!
     </div>
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
                        <div class="suggestion-item" data-adm="${student.admission_no}">
                            ${student.admission_no} - ${student.name} - ${student.class}
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
    const adm = $(this).data('adm');
    $('#student-search').val(adm);
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
        if (clockElement) {
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

    /* ===== Keep dropdown open if current page matches a child link ===== */
    const currentUrl = window.location.pathname.split("/").pop();
    document.querySelectorAll(".dropdown").forEach(drop => {
        const links = drop.querySelectorAll("a");
        links.forEach(link => {
            const linkUrl = link.getAttribute("href");
            if (linkUrl && linkUrl.includes(currentUrl)) {
                drop.classList.add("open");
            }
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
            window.location.href = 'logout.php'; // Change to your logout URL
        }, 300000); // 30 seconds
    }

    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    resetLogoutTimer();
});
</script>

</body>
</html>
