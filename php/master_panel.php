<?php
// master_panel.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Master Admin Panel</title>
    <style>
        body.master-panel {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f7fa;
            padding: 30px;
            color: #2c3e50;
        }
        h1.master-panel__title {
            text-align: center;
            color: #2980b9;
            margin-bottom: 40px;
        }
        .master-panel__nav {
            max-width: 900px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .master-panel__link {
            display: block;
            background: white;
            border: 2px solid #3498db;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
            color: #3498db;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .master-panel__link:hover {
            background: #3498db;
            color: white;
        }
        /* Base styles already cover desktop */

/* Tablets and small desktops (up to 1024px) */
@media (max-width: 1024px) {
    .master-panel__nav {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
    }
    .master-panel__link {
        font-size: 1.05em;
        padding: 18px;
    }
}

/* Small tablets and large phones (up to 768px) */
@media (max-width: 768px) {
    .master-panel__nav {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    .master-panel__link {
        font-size: 1em;
        padding: 16px;
    }
}

/* Phones (up to 480px) */
@media (max-width: 480px) {
    .master-panel__nav {
        grid-template-columns: 1fr;  /* Single column */
        max-width: 320px;
        margin: 0 auto;
        gap: 12px;
    }
    .master-panel__link {
        font-size: 0.95em;
        padding: 14px;
    }
}

/* Extra small phones (up to 360px) */
@media (max-width: 360px) {
    body.master-panel {
        padding: 15px;
    }
    .master-panel__title {
        font-size: 1.5em;
        margin-bottom: 30px;
    }
    .master-panel__link {
        font-size: 0.9em;
        padding: 12px;
    }
}

    </style>
</head>
<body class="master-panel">
    <h1 class="master-panel__title">Master Admin Panel</h1>
    <div class="master-panel__nav">
        <a href="../tables/administration.php" class="master-panel__link">Administration</a>
        <a href="../tables/student_records.php" class="master-panel__link">Student Records</a>
        <a href="../tables/graduated_students.php" class="master-panel__link">Graduated Students</a>
        <a href="../tables/edit_fees.php" class="master-panel__link">School Fees</a>
        <a href="../tables/school_fee_transactions.php" class="master-panel__link">School Fee Transactions</a>
        <a href="../tables/lunch_fees.php" class="master-panel__link">Lunch Fees</a>
        <a href="../tables/lunch_fee_transactions.php" class="master-panel__link">Lunch Fee Transactions</a>
        <a href="../tables/others.php" class="master-panel__link">Other Fees</a>
        <a href="../tables/other_transactions.php" class="master-panel__link">Other Transactions</a>
        <a href="../tables/book_prices.php" class="master-panel__link">Book Prices</a>
        <a href="../tables/book_purchases.php" class="master-panel__link">Book Purchases</a>
        <a href="../tables/uniform_prices.php" class="master-panel__link">Uniform Prices</a>
        <a href="../tables/uniform_purchases.php" class="master-panel__link">Uniform Purchases</a>
        <a href="../tables/purchase_transactions.php" class="master-panel__link">Purchase Transactions</a>
        <a href="../tables/teacher_records.php" class="master-panel__link">Teacher Records</a>
        <a href="../tables/terms.php" class="master-panel__link">Terms</a>
        <a href="../tables/weeks.php" class="master-panel__link">Weeks</a>
        <a href="../tables/days.php" class="master-panel__link">Days</a>
        <a href="../tables/edit_attendance.php" class="master-panel__link">Attendance</a>
        <a href="logout.php"class="master-panel__link"><i class='bx bx-log-out'></i> Logout</a>
    </div>
</body>
</html>
