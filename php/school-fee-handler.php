<?php
session_start();

// ============================
// ACCESS CONTROL
// ============================
if (!isset($_SESSION['username'])) {
    echo "<script>alert('Unauthorized access. Please login.'); window.location.href='login.php';</script>";
    exit();
}

require 'db.php';

$DAILY_FEE   = 70;
$VALID_DAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$FEE_RULES   = [
    "Admission"    => 1000,
    "Activity"     => 100,
    "Exam"         => 100,
    "Interview"    => 200,
    "Prize Giving" => 500,
    "Graduation"   => 1500
];

// ============================
// HANDLE PAYMENT (POST request)
// ============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $admission_no  = trim($_POST['admission_no'] ?? '');
    $school_amount = floatval($_POST['school_amount'] ?? 0);
    $lunch_amount  = floatval($_POST['lunch_amount'] ?? 0);
    $payment_type  = $_POST['payment_type'] ?? 'Cash';

    $others_fees   = $_POST['others'] ?? [];
    $books         = $_POST['books'] ?? [];
    $uniforms      = $_POST['uniforms'] ?? [];

    // Check if there are any non-empty 'others' fees
    $hasOtherFees = !empty(array_filter($others_fees));

    // Check if any book purchase is valid (quantity > 0 and amount > 0)
    $hasBooks = false;
    foreach ($books as $book_data) {
        $qty    = intval($book_data['quantity'] ?? 0);
        $amount = floatval($book_data['amount'] ?? 0);
        if ($qty > 0 && $amount > 0) {
            $hasBooks = true;
            break;
        }
    }

    // Check if any uniform purchase is valid (quantity > 0 and amount > 0)
    $hasUniforms = false;
    foreach ($uniforms as $uniform_data) {
        $qty    = intval($uniform_data['quantity'] ?? 0);
        $amount = floatval($uniform_data['amount'] ?? 0);
        if ($qty > 0 && $amount > 0) {
            $hasUniforms = true;
            break;
        }
    }

    // Validate required fields and at least one positive payment amount
    if (
        empty($admission_no) ||
        (
            $school_amount <= 0 &&
            $lunch_amount <= 0 &&
            !$hasOtherFees &&
            !$hasBooks &&
            !$hasUniforms
        )
    ) {
        echo "<script>alert('Error: Invalid input data! Please check your entries.'); window.history.back();</script>";
        exit();
    }




    // === Fetch Student (current or graduated) ===
    $student = null;
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $stmt = $conn->prepare("SELECT name, class_completed FROM graduated_students WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$student) {
        echo "<script>alert('Error: Admission number not found!'); window.history.back();</script>";
        exit();
    }

    $name  = $student['name'];
    $class = $student['class'] ?? $student['class_completed'];

    // === Current Term ===
    $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
    if (!$terms_res || $terms_res->num_rows === 0) {
        echo "<script>alert('No terms available.'); window.history.back();</script>";
        exit();
    }
    $terms_arr           = $terms_res->fetch_all(MYSQLI_ASSOC);
    $current_term        = end($terms_arr);
    $current_term_number = $current_term['term_number'];

    // === Receipt Number ===
    $random_str     = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4);
    $receipt_number = "KSR-T{$current_term_number}-{$random_str}";

    // ============================
    // BEGIN TRANSACTION
    // ============================
    $conn->begin_transaction();
    try {
        // ----------------------------------------------------
        // SCHOOL FEES
        // ----------------------------------------------------
        if ($school_amount > 0) {
            $stmt = $conn->prepare("SELECT total_fee, amount_paid FROM school_fees WHERE admission_no = ?");
            $stmt->bind_param("s", $admission_no);
            $stmt->execute();
            $fee_record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fee_record) throw new Exception("No school fee record found!");

            $new_total_paid = $fee_record['amount_paid'] + $school_amount;
            $new_balance    = $fee_record['total_fee'] - $new_total_paid;

            $stmt = $conn->prepare("UPDATE school_fees SET amount_paid = ?, balance = ? WHERE admission_no = ?");
            $stmt->bind_param("dds", $new_total_paid, $new_balance, $admission_no);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO school_fee_transactions 
                (name, admission_no, class, amount_paid, receipt_number, payment_type) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdss", $name, $admission_no, $class, $school_amount, $receipt_number, $payment_type);
            $stmt->execute();
            $stmt->close();
        }

        // ----------------------------------------------------
        // LUNCH FEES
        // ----------------------------------------------------
        if ($lunch_amount > 0) {
            $original_amt = $lunch_amount;

            // Helper to insert missing week
            function insertWeek($conn, $admission_no, $term_id, $week_num, $payment_type, $daily_fee) {
                $total_weekly = $daily_fee * 5;
                $stmt = $conn->prepare("INSERT INTO lunch_fees 
                    (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward) 
                    VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
                $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly, $week_num, $total_weekly, $payment_type);
                $stmt->execute();
                $stmt->close();
            }

            // Terms to process (prev + current if applicable)
            $terms_to_process = [$current_term];
            foreach ($terms_arr as $t) {
                if ($t['id'] < $current_term['id']) {
                    $stmt = $conn->prepare("SELECT 1 FROM lunch_fees WHERE admission_no=? AND term_id=? LIMIT 1");
                    $stmt->bind_param("si", $admission_no, $t['id']);
                    $stmt->execute();
                    $has_prev = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($has_prev) {
                        $terms_to_process = array_slice($terms_arr, array_search($t, $terms_arr));
                        break;
                    }
                }
            }

            // Distribute payment
            foreach ($terms_to_process as $term) {
                if ($lunch_amount <= 0) break;

                $termId     = $term['id'];
                $daysInTerm = (strtotime($term['end_date']) - strtotime($term['start_date'])) / 86400 + 1;
                $total_weeks = ceil($daysInTerm / 5);

                // Last paid week
                $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? ORDER BY week_number DESC LIMIT 1");
                $stmt->bind_param("si", $admission_no, $termId);
                $stmt->execute();
                $lastWeek = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $startWeek = $lastWeek ? ($lastWeek['balance'] == 0 ? $lastWeek['week_number'] + 1 : $lastWeek['week_number']) : 1;

                for ($wk = $startWeek; $wk <= $total_weeks && $lunch_amount > 0; $wk++) {
                    // Fetch or insert week
                    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=?");
                    $stmt->bind_param("sii", $admission_no, $termId, $wk);
                    $stmt->execute();
                    $weekData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$weekData) {
                        insertWeek($conn, $admission_no, $termId, $wk, $payment_type, $DAILY_FEE);
                        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=?");
                        $stmt->bind_param("sii", $admission_no, $termId, $wk);
                        $stmt->execute();
                        $weekData = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }

                    // Day-by-day distribution
                    foreach ($VALID_DAYS as $dayName) {
                        if ($lunch_amount <= 0) break;

                        $stmt = $conn->prepare("
                            SELECT d.id, d.is_public_holiday,
                                   (a.status IS NULL OR a.status='Present') AS is_present
                            FROM days d
                            LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no=?
                            LEFT JOIN weeks w ON w.id=d.week_id
                            WHERE w.term_id=? AND w.week_number=? AND d.day_name=?");
                        $stmt->bind_param("siis", $admission_no, $termId, $wk, $dayName);
                        $stmt->execute();
                        $dd = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if (!$dd || $dd['is_public_holiday'] || !$dd['is_present']) continue;

                        $dayCol = strtolower($dayName);
                        $paidToday = floatval($weekData[$dayCol]);
                        if ($paidToday >= $DAILY_FEE) continue;

                        $topUp = min($DAILY_FEE - $paidToday, $lunch_amount);

                        $stmt = $conn->prepare("
                            UPDATE lunch_fees
                            SET $dayCol = $dayCol + ?, total_paid=total_paid+?, balance=balance-?
                            WHERE id=?");
                        $stmt->bind_param("dddi", $topUp, $topUp, $topUp, $weekData['id']);
                        $stmt->execute();
                        $stmt->close();

                        $lunch_amount -= $topUp;
                        $weekData[$dayCol] += $topUp;
                        $weekData['balance'] -= $topUp;
                    }
                }
            }

            // Log lunch transaction
            $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions 
                (name, class, admission_no, receipt_number, amount_paid, payment_type) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_number, $original_amt, $payment_type);
            $stmt->execute();
            $stmt->close();
        }

        // ----------------------------------------------------
        // OTHER FEES 
        // ----------------------------------------------------
        $current_term_enum = "term" . $current_term['term_number']; // Convert to ENUM-compatible value

        foreach ($others_fees as $fee_type => $amount) {
            $amount = floatval($amount);
            if ($amount <= 0 || !isset($FEE_RULES[$fee_type])) continue;

            $max_allowed = $FEE_RULES[$fee_type];

            // === ADMISSION / INTERVIEW: One-time payment checks with alerts ===
            if (in_array($fee_type, ["Admission", "Interview"])) {
                $stmt = $conn->prepare("
                    SELECT SUM(ot.amount_paid) as total 
                    FROM other_transactions ot
                    JOIN others o ON ot.others_id = o.id
                    WHERE o.admission_no=? AND ot.fee_type=? AND ot.status='Completed'
                ");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("ss", $admission_no, $fee_type);
                $stmt->execute();
                $paid_before = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
                $stmt->close();

                if ($paid_before >= $max_allowed) {
                    echo "<script>alert('❌ {$fee_type} fee already fully paid.'); window.history.back();</script>";
                    exit();
                }
            }

            // === GRADUATION: Only for PP2 & check for prior payment ===
            if ($fee_type === "Graduation") {
                // Rule: Only PP2 class is allowed
                if (stripos($class, "PP2") === false) {
                    echo "<script>alert('❌ Graduation fee is only applicable to PP2 students.'); window.history.back();</script>";
                    exit();
                }

                // Check if already paid
                $stmt = $conn->prepare("
                    SELECT SUM(ot.amount_paid) as total 
                    FROM other_transactions ot
                    JOIN others o ON ot.others_id = o.id
                    WHERE o.admission_no=? AND ot.fee_type='Graduation' AND ot.status='Completed'
                ");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("s", $admission_no);
                $stmt->execute();
                $paid_before = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
                $stmt->close();

                if ($paid_before >= $max_allowed) {
                    echo "<script>alert('❌ Graduation fee already fully paid.'); window.history.back();</script>";
                    exit();
                }
            }

            // === Step 1: Check if record exists in `others` ===
            $stmt = $conn->prepare("SELECT id, amount_paid, total_amount FROM others 
                                    WHERE admission_no=? AND fee_type=? AND term=? LIMIT 1");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("sss", $admission_no, $fee_type, $current_term_enum);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing) {
                // === Step 2: Update existing record ===
                $others_id = $existing['id'];
                $new_paid = $existing['amount_paid'] + $amount;
                if ($new_paid > $max_allowed) $new_paid = $max_allowed;

                $stmt = $conn->prepare("UPDATE others 
                                        SET amount_paid=?, payment_type=?, payment_date=NOW() 
                                        WHERE id=?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("dsi", $new_paid, $payment_type, $others_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // === Step 3: Insert new record ===
                $stmt = $conn->prepare("INSERT INTO others 
                    (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, payment_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("sssssdds", $receipt_number, $admission_no, $name, $current_term_enum,
                                $fee_type, $max_allowed, $amount, $payment_type);
                $stmt->execute();
                $others_id = $stmt->insert_id;
                $stmt->close();
            }

            // === Step 4: Log transaction ===
            $stmt = $conn->prepare("INSERT INTO other_transactions
                (others_id, fee_type, amount_paid, payment_type, receipt_number, status)
                VALUES (?, ?, ?, ?, ?, 'Completed')");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("isdss", $others_id, $fee_type, $amount, $payment_type, $receipt_number);
            $stmt->execute();
            $stmt->close();
        }

        // ----------------------------------------------------
        // BOOKS
        // ----------------------------------------------------

        if (!empty($_POST['books'])) {
            foreach ($_POST['books'] as $book_id => $book_data) {
                $quantity = intval($book_data['quantity'] ?? 0);
                $amount_paid = floatval($book_data['amount'] ?? 0);
                if ($quantity <= 0 || $amount_paid <= 0) continue;

                // Fetch book price & name
                $stmt = $conn->prepare("SELECT book_name, price FROM book_prices WHERE book_id=?");
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $book_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$book_info) continue;

                $book_name  = $book_info['book_name'];
                $unit_price = $book_info['price'];
                $total_price = $unit_price * $quantity;

                // Step 1: Check old balance for this book for this child
                $stmt = $conn->prepare("SELECT id, balance FROM book_purchases WHERE admission_no=? AND book_name=? AND balance > 0 ORDER BY id ASC");
                $stmt->bind_param("ss", $admission_no, $book_name);
                $stmt->execute();
                $old_balance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $remaining_payment = $amount_paid;

                // Step 2: Deduct old balances first
                foreach ($old_balance_records as $record) {
                    if ($remaining_payment <= 0) break;

                    $old_balance_id = $record['id'];
                    $old_balance_amount = floatval($record['balance']);

                    if ($remaining_payment >= $old_balance_amount) {
                        // Fully pay old balance
                        $payment_to_old = $old_balance_amount;
                        $remaining_payment -= $payment_to_old;

                        // Update old record balance to zero
                        $stmt = $conn->prepare("UPDATE book_purchases SET balance = 0, amount_paid = amount_paid + ? WHERE id = ?");
                        $stmt->bind_param("di", $payment_to_old, $old_balance_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Partially pay old balance
                        $payment_to_old = $remaining_payment;
                        $remaining_payment = 0;

                        // Reduce old balance accordingly and increase amount_paid
                        $stmt = $conn->prepare("UPDATE book_purchases SET balance = balance - ?, amount_paid = amount_paid + ? WHERE id = ?");
                        $stmt->bind_param("ddi", $payment_to_old, $payment_to_old, $old_balance_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Step 3: Apply remaining payment to new purchase
                $new_balance = $total_price - $remaining_payment;
                if ($new_balance < 0) {
                    // Overpayment on new purchase (handle if needed)
                    $remaining_payment = $total_price; // pay full price
                    $new_balance = 0;
                }

                // Insert new book purchase record with the payment after deducting old balances
                $stmt = $conn->prepare("INSERT INTO book_purchases 
                    (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiidds", $receipt_number, $admission_no, $name, $book_name, $quantity, 
                                $total_price, $remaining_payment, $new_balance, $payment_type);
                $stmt->execute();
                $stmt->close();

                // Log in purchase_transactions
                $stmt = $conn->prepare("INSERT INTO purchase_transactions
                    (receipt_number, admission_no, name, total_amount_paid, payment_type)
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssds", $receipt_number, $admission_no, $name, $amount_paid, $payment_type);
                $stmt->execute();
                $stmt->close();
            }
        }


        // ----------------------------------------------------
        // UNIFORM
        // ----------------------------------------------------
        if (!empty($_POST['uniforms'])) {
            foreach ($_POST['uniforms'] as $uniform_id => $uniform_data) {
                $quantity = intval($uniform_data['quantity'] ?? 0);
                $amount_paid = floatval($uniform_data['amount'] ?? 0);
                if ($quantity < 1 || $amount_paid <= 0) continue;

                // Fetch uniform details from uniform_prices table using ID
                $stmt = $conn->prepare("SELECT uniform_type, size, price FROM uniform_prices WHERE id = ?");
                $stmt->bind_param("i", $uniform_id);
                $stmt->execute();
                $price_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$price_info) continue; // No matching uniform found

                $uniform_type = $price_info['uniform_type'];
                $size = $price_info['size'];
                $unit_price = floatval($price_info['price']);
                $total_price = $unit_price * $quantity;

                // Step 1: Get all old balances for this uniform type and size, admission_no
                $stmt = $conn->prepare("SELECT id, balance FROM uniform_purchases WHERE admission_no=? AND uniform_type=? AND size=? AND balance > 0 ORDER BY id ASC");
                $stmt->bind_param("sss", $admission_no, $uniform_type, $size);
                $stmt->execute();
                $old_balance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $remaining_payment = $amount_paid;

                // Step 2: Deduct old balances first
                foreach ($old_balance_records as $record) {
                    if ($remaining_payment <= 0) break;

                    $old_id = $record['id'];
                    $old_balance = floatval($record['balance']);

                    if ($remaining_payment >= $old_balance) {
                        // Fully pay old balance
                        $payment_to_old = $old_balance;
                        $remaining_payment -= $payment_to_old;

                        $stmt = $conn->prepare("UPDATE uniform_purchases SET balance = 0, amount_paid = amount_paid + ? WHERE id = ?");
                        $stmt->bind_param("di", $payment_to_old, $old_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Partially pay old balance
                        $payment_to_old = $remaining_payment;
                        $remaining_payment = 0;

                        $stmt = $conn->prepare("UPDATE uniform_purchases SET balance = balance - ?, amount_paid = amount_paid + ? WHERE id = ?");
                        $stmt->bind_param("ddi", $payment_to_old, $payment_to_old, $old_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Step 3: Calculate new balance after applying remaining payment
                $new_balance = $total_price - $remaining_payment;
                if ($new_balance < 0) {
                    // Overpayment scenario, adjust payment and balance accordingly
                    $remaining_payment = $total_price;
                    $new_balance = 0;
                }

                // Step 4: Insert new uniform purchase record
                $stmt = $conn->prepare("INSERT INTO uniform_purchases 
                    (receipt_number, admission_no, name, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssiidds", $receipt_number, $admission_no, $name, $uniform_type, $size, $quantity, 
                                $total_price, $remaining_payment, $new_balance, $payment_type);
                $stmt->execute();
                $stmt->close();

                // Step 5: Log transaction (optional: aggregate total outside loop)
                $stmt = $conn->prepare("INSERT INTO purchase_transactions 
                    (receipt_number, admission_no, name, total_amount_paid, payment_type)
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssds", $receipt_number, $admission_no, $name, $amount_paid, $payment_type);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // ----------------------------------------------------
        // COMMIT ALL
        // ----------------------------------------------------
        $conn->commit();
        echo "<script>alert('✅ Payment Successful! Receipt: {$receipt_number}'); window.location.href='school-fee-handler.php';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: Transaction failed - " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
}
?>

<!-- ============================
     SHOW FORM (GET request)
============================ -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Make Payment</title>
  <link rel="stylesheet" href="../style/style.css">
  <style>
    body { font-family: Arial, sans-serif; background:#f9f9f9; }
    .payment-container {
      width: 600px; margin: 50px auto; background:#fff; padding:20px;
      border-radius:10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { text-align:center; margin-bottom:20px; }
    label { display:block; margin-top:15px; font-weight:bold; }
    input, select {
      width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:5px;
    }
    .btn {
      margin-top:20px; padding:10px; width:100%; background:#2c7be5;
      color:#fff; border:none; border-radius:5px; font-size:16px; cursor:pointer;
    }
    .btn:hover { background:#1a5dc9; }
  </style>
</head>
<body>
<div class="payment-container">
  <h2>Student Payment</h2>
  <form method="POST" action="school-fee-handler.php">

    <label>Admission Number:</label>
    <input type="text" name="admission_no" required>

    <label>School Fee Amount:</label>
    <input type="number" name="school_amount" placeholder="Enter school fee amount" min="0" step="0.01">

    <label>Lunch Fee Amount:</label>
    <input type="number" name="lunch_amount" placeholder="Enter lunch fee amount" min="0" step="0.01">

    <h3>Other Fees</h3>
    <label>Admission Fee :</label>
    <input type="number" name="others[Admission]" placeholder="Enter amount" min="0" step="0.01">

    <label>Activity Fee :</label>
    <input type="number" name="others[Activity]" placeholder="Enter amount" min="0" step="0.01">

    <label>Exam Fee :</label>
    <input type="number" name="others[Exam]" placeholder="Enter amount" min="0" step="0.01">

    <label>Interview Fee :</label>
    <input type="number" name="others[Interview]" placeholder="Enter amount" min="0" step="0.01">

    <label>Prize Giving Fee :</label>
    <input type="number" name="others[Prize Giving]" placeholder="Enter amount" min="0" step="0.01">

    <label>Graduation Fee :</label>
    <input type="number" name="others[Graduation]" placeholder="Enter amount" min="0" step="0.01">

    <h3>Book Purchases</h3>
    <?php
    $books_res = $conn->query("SELECT * FROM book_prices ORDER BY category ASC");
    while ($book = $books_res->fetch_assoc()): ?>
      <div style="margin-bottom:15px;">
          <label><?= htmlspecialchars($book['book_name']) ?> (KES <?= number_format($book['price'], 2) ?> each)</label>
          <div style="display:flex; gap:10px; margin-top:5px;">
            <input type="number" name="books[<?= $book['book_id'] ?>][quantity]" 
                   placeholder="Qty" min="0" value="1" style="flex:1;">
            <input type="number" name="books[<?= $book['book_id'] ?>][amount]" 
                   placeholder="Amount Paid" min="0" step="0.01"  style="flex:1;">
          </div>
      </div>
    <?php endwhile; ?>

    <h3>Uniform Purchases</h3>
    <?php
    $uniforms_res = $conn->query("SELECT * FROM uniform_prices ORDER BY id ASC");
    while ($uniform = $uniforms_res->fetch_assoc()): ?>
      <div style="margin-bottom:15px;">
        <label><?= htmlspecialchars($uniform['uniform_type']) ?> - <?= htmlspecialchars($uniform['size']) ?> (KES <?= number_format($uniform['price'], 2) ?>)</label>
        <div style="display:flex; gap:10px; margin-top:5px;">
          <input type="number" name="uniforms[<?= $uniform['id'] ?>][quantity]" 
                 placeholder="Qty" min="0" value="1" style="flex:1;">
          <input type="number" name="uniforms[<?= $uniform['id'] ?>][amount]" 
                 placeholder="Amount Paid" min="0" step="0.01" style="flex:1;">
        </div>
      </div>
    <?php endwhile; ?>

    <label>Payment Method:</label>
    <select name="payment_type" required>
      <option value="Cash">Cash</option>
      <option value="Mpesa">Mpesa</option>
      <option value="Bank">Bank</option>
      <option value="Other">Other</option>
    </select>

    <button type="submit" class="btn">Make Payment</button>
  </form>
</div>

</body>
</html>
