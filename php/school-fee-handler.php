<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$prefill_adm = $_GET['admission_no'] ?? '';
$prefill_name = $_GET['student_name'] ?? '';

$DAILY_FEE   = 70;
$VALID_DAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$FEE_RULES = [
    "Admission"    => 1000,
    "Activity"     => 100,
    "Interview"    => 200,
    "Prize Giving" => 500,
    "Graduation"   => 1500,
    "Exam"         => [
        "babyclass" => 100,
        "intermediate" => 100,
        "pp1"       => 100,
        "pp2"       => 100,
        "grade1"   => 100,
        "grade2"   => 100,
        "grade3"   => 150,
        "grade4"   => 150,
        "grade5"   => 150,
        "grade6"   => 150,

    ]
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

        // === Helper: Prepare & check SQL ===
        function safe_prepare($conn, $query) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            return $stmt;
        }

        // === Process Other Fees ===
        foreach ($others_fees as $fee_type => $amount) {
            $amount = floatval($amount);
            if ($amount <= 0 || !isset($FEE_RULES[$fee_type])) continue;

            $max_allowed = $fee_type === 'Exam' && is_array($FEE_RULES['Exam'])
                ? ($FEE_RULES['Exam'][$class] ?? 150)
                : $FEE_RULES[$fee_type];

            // === One-time fee checks (Admission, Interview, Graduation) ===
            if (in_array($fee_type, ['Admission', 'Interview', 'Graduation'])) {
                // Graduation only for PP2
                if ($fee_type === 'Graduation' && stripos($class, 'PP2') === false) {
                    echo "<script>alert('❌ Graduation fee is only applicable to PP2 students.'); window.history.back();</script>";
                    exit();
                }

                $stmt = safe_prepare($conn, "
                    SELECT SUM(ot.amount_paid) AS total
                    FROM other_transactions ot
                    JOIN others o ON ot.others_id = o.id
                    WHERE o.admission_no=? AND ot.fee_type=? AND ot.status='Completed'
                ");
                $stmt->bind_param("ss", $admission_no, $fee_type);
                $stmt->execute();
                $paid_before = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
                $stmt->close();

                if ($paid_before >= $max_allowed) {
                    echo "<script>alert('❌ {$fee_type} fee already fully paid.'); window.history.back();</script>";
                    exit();
                }
            }

            // === Fetch or Create `others` record ===
            $stmt = safe_prepare($conn, "SELECT id, amount_paid, total_amount FROM others WHERE admission_no=? AND fee_type=? AND term=? LIMIT 1");
            $stmt->bind_param("sss", $admission_no, $fee_type, $current_term_enum);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                // Update existing record
                $others_id = $existing['id'];
                $new_paid = min($existing['amount_paid'] + $amount, $max_allowed);

                $stmt = safe_prepare($conn, "UPDATE others SET amount_paid=?, payment_type=?, payment_date=NOW() WHERE id=?");
                $stmt->bind_param("dsi", $new_paid, $payment_type, $others_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insert new record
                $stmt = safe_prepare($conn, "INSERT INTO others 
                    (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, payment_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssdds", $receipt_number, $admission_no, $name, $current_term_enum,
                    $fee_type, $max_allowed, $amount, $payment_type);
                $stmt->execute();
                $others_id = $stmt->insert_id;
                $stmt->close();
            }

            // === Log transaction ===
            $stmt = safe_prepare($conn, "INSERT INTO other_transactions 
                (others_id, fee_type, amount_paid, payment_type, receipt_number, status) 
                VALUES (?, ?, ?, ?, ?, 'Completed')");
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

                // Fetch book details
                $stmt = safe_prepare($conn, "SELECT book_name, price FROM book_prices WHERE book_id=?");
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $book_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$book_info) continue;

                $book_name  = $book_info['book_name'];
                $unit_price = $book_info['price'];
                $total_price = $unit_price * $quantity;

                // Fetch previous unpaid balances
                $stmt = safe_prepare($conn, "SELECT purchase_id, balance FROM book_purchases 
                                            WHERE admission_no=? AND book_name=? AND balance > 0 
                                            ORDER BY purchase_id ASC");
                $stmt->bind_param("ss", $admission_no, $book_name);
                $stmt->execute();
                $old_balance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $remaining_payment = $amount_paid;

                foreach ($old_balance_records as $record) {
                    if ($remaining_payment <= 0) break;

                    $purchase_id = $record['purchase_id'];
                    $balance_due = floatval($record['balance']);
                    $payment_to_old = min($remaining_payment, $balance_due);
                    $remaining_payment -= $payment_to_old;

                    $stmt = safe_prepare($conn, "UPDATE book_purchases 
                                                SET balance = balance - ?, amount_paid = amount_paid + ? 
                                                WHERE purchase_id = ?");
                    $stmt->bind_param("ddi", $payment_to_old, $payment_to_old, $purchase_id);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($remaining_payment > 0) {
                    $new_balance = max(0, $total_price - $remaining_payment);
                    $applied_amount = min($total_price, $remaining_payment);

                    $stmt = safe_prepare($conn, "INSERT INTO book_purchases 
                        (receipt_number, admission_no, name, book_name, quantity, total_price, amount_paid, balance, payment_type)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssiidds", $receipt_number, $admission_no, $name, $book_name, $quantity, 
                                    $total_price, $applied_amount, $new_balance, $payment_type);
                    $stmt->execute();
                    $stmt->close();
                }

                // Log transaction
                $stmt = safe_prepare($conn, "INSERT INTO purchase_transactions 
                    (receipt_number, admission_no, name, total_amount_paid, payment_type) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssds", $receipt_number, $admission_no, $name, $amount_paid, $payment_type);
                $stmt->execute();
                $stmt->close();
            }
        }




        // ----------------------------------------------------
        // UNIFORMS
        // ----------------------------------------------------
        if (!empty($_POST['uniforms'])) {
            foreach ($_POST['uniforms'] as $uniform_id => $uniform_data) {
                $quantity = intval($uniform_data['quantity'] ?? 0);
                $amount_paid = floatval($uniform_data['amount'] ?? 0);
                if ($quantity < 1 || $amount_paid <= 0) continue;

                // Fetch uniform details
                $stmt = $conn->prepare("SELECT uniform_type, size, price FROM uniform_prices WHERE id = ?");
                $stmt->bind_param("i", $uniform_id);
                $stmt->execute();
                $price_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$price_info) continue;

                $uniform_type = $price_info['uniform_type'];
                $size = $price_info['size'];
                $unit_price = floatval($price_info['price']);
                $total_price = $unit_price * $quantity;

                // Step 1: Get any old uniform balances
                $stmt = $conn->prepare("SELECT purchase_id, balance FROM uniform_purchases 
                                        WHERE admission_no = ? AND uniform_type = ? AND size = ? AND balance > 0 
                                        ORDER BY purchase_id ASC");
                $stmt->bind_param("sss", $admission_no, $uniform_type, $size);
                $stmt->execute();
                $old_balance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $remaining_payment = $amount_paid;
                $paid_to_old_balances = 0;

                // Step 2: Settle old balances first
                foreach ($old_balance_records as $record) {
                    if ($remaining_payment <= 0) break;

                    $old_id = $record['id'];
                    $old_balance = floatval($record['balance']);

                    if ($remaining_payment >= $old_balance) {
                        $payment_to_old = $old_balance;
                        $remaining_payment -= $payment_to_old;
                    } else {
                        $payment_to_old = $remaining_payment;
                        $remaining_payment = 0;
                    }

                    $paid_to_old_balances += $payment_to_old;

                    // Update existing record
                    $stmt = $conn->prepare("UPDATE uniform_purchases 
                                            SET balance = balance - ?, amount_paid = amount_paid + ? 
                                            WHERE id = ?");
                    $stmt->bind_param("ddi", $payment_to_old, $payment_to_old, $old_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Step 3: Only insert new record if payment is left after old debts
                if ($remaining_payment > 0) {
                    $new_balance = $total_price - $remaining_payment;
                    if ($new_balance < 0) {
                        $remaining_payment = $total_price;
                        $new_balance = 0;
                    }

                    // Insert new uniform purchase
                    $stmt = $conn->prepare("INSERT INTO uniform_purchases 
                        (receipt_number, admission_no, name, uniform_type, size, quantity, total_price, amount_paid, balance, payment_type)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssiidds", $receipt_number, $admission_no, $name, $uniform_type, $size, $quantity, 
                                    $total_price, $remaining_payment, $new_balance, $payment_type);
                    $stmt->execute();
                    $stmt->close();
                }

                // Step 4: Log full payment into purchase_transactions
                if ($amount_paid > 0) {
                    $stmt = $conn->prepare("INSERT INTO purchase_transactions 
                        (receipt_number, admission_no, name, total_amount_paid, payment_type)
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssds", $receipt_number, $admission_no, $name, $amount_paid, $payment_type);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        
        // ----------------------------------------------------
        // COMMIT ALL
        // ----------------------------------------------------
        $conn->commit();
        echo "<script>
            var win = window.open('receipt.php?receipt_no={$receipt_number}', '_blank');
            if (!win) {
                alert('⚠️ Pop-up blocked! Please allow pop-ups for this site.');
            }
            setTimeout(function() {
                window.location.href = 'school-fee-handler.php';
            }, 500);
        </script>";

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
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
/* ===== Payment Container ===== */
.payment-container {
    max-width: 700px;
    margin: 20px auto;
    padding: 20px;
    background-color: #fdfdfd;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    font-family: Arial, sans-serif;
}

/* Form Heading */
.payment-container h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #333;
}

/* Form Groups */
.payment-container .form-group {
    margin-bottom: 15px;
    position: relative;
}

.payment-container label {
    font-weight: bold;
    margin-bottom: 5px;
    display: inline-block;
    font-size: 14px;
}

.payment-container input[type="text"],
.payment-container input[type="number"],
.payment-container select {
    padding: 6px 10px;
    font-size: 14px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

/* Form Group */
.form-group {
    position: relative;
    margin-bottom: 20px;
    width: 100%;
    max-width: 500px; /* optional: limit width */
}

/* Label styling */
.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
    font-size: 14px;
}

/* Input field styling */
.form-group input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    font-size: 14px;
    border-radius: 5px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

/* Suggestions dropdown */
#suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid #ccc;
    border-top: none;
    background-color: #fff;
    z-index: 100;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 0 0 5px 5px;
}

/* Individual suggestion items */
.suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
}

.suggestion-item:hover {
    background-color: #f0f0f0;
}


/* ===== School Fees ===== */
.school-fees {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.school-fees label {
    display: block;
    margin-bottom: 3px;
}

.school-fees input {
    width: 100%;
}

/* ===== Other Fees arranged like School Fees ===== */
.others {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.others > div {
    flex: 1 1 45%; /* two inputs per row, adjust spacing */
    display: flex;
    flex-direction: column;
}

.others label {
    margin-bottom: 5px;
    font-weight: bold;
}

.others input {
    padding: 6px 10px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
}


/* ===== Books & Uniforms ===== */
.books, .uniform {
    margin-bottom: 20px;
}

.books h3, .uniform h3 {
    font-size: 16px;
    margin-bottom: 10px;
    text-align: center;
}

.books div, .uniform div {
    margin-bottom: 10px;
}

.books div > div, .uniform div > div {
    display: flex;
    gap: 10px;
}

.books div > div input, .uniform div > div input {
    flex: 1;
}
/* Total amount display */
div[style="total"] {
    margin-bottom: 20px;
    font-weight: bold;
    font-size: 16px;
}
/* ===== Payment Method ===== */
.payments {
    margin-bottom: 20px;
}

.payments label {
    margin-bottom: 5px;
}

.payments select {
    padding: 6px 10px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
    width: 100%;
}

/* ===== Submit Button ===== */
.btn {
    display: block;
    width: 100%;
    padding: 10px 0;
    font-size: 15px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}

.btn:hover {
    background-color: #0056b3;
}
/* Chrome, Safari, Edge, Opera */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
}

</style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include'../includes/sidebar.php'; ?>
    <main class="content">
        <div class="payment-container">
        <h2>Student Payment</h2>
        <form method="POST" action="school-fee-handler.php">

                    <div class="form-group">
                        <label for="student-search">Admission No Or Name:</label>
                        <input type="text" id="student-search" placeholder="Search student..." autocomplete="off" 
                            value="<?= htmlspecialchars($prefill_name); ?>">
                        <input type="hidden" id="admission_no" name="admission_no" value="<?= htmlspecialchars($prefill_adm); ?>">
                        <input type="hidden" id="student_name" name="student_name" value="<?= htmlspecialchars($prefill_name); ?>">
                        <div id="suggestions"></div>
                    </div>


                    <div class="school-fees">
                        <div style="flex:1">
                            <label>School Fee Amount:</label>
                            <input type="number" name="school_amount" placeholder="Enter school fee amount" min="0" step="0.01">
                        </div>
                        <div style="flex:1">
                            <label>Lunch Fee Amount:</label>
                            <input type="number" name="lunch_amount" placeholder="Enter lunch fee amount" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="others">
                        <div>
                            <label>Admission Fee:</label>
                            <input type="number" name="others[Admission]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div>
                            <label>Activity Fee:</label>
                            <input type="number" name="others[Activity]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div>
                            <label>Exam Fee:</label>
                            <input type="number" name="others[Exam]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div>
                            <label>Interview Fee:</label>
                            <input type="number" name="others[Interview]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div>
                            <label>Prize Giving Fee:</label>
                            <input type="number" name="others[Prize Giving]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div>
                            <label>Graduation Fee:</label>
                            <input type="number" name="others[Graduation]" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                    </div>



                <div class="books">
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
                </div>

                <div class="uniform">
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
                </div>
                <div style="total">
                    Total Amount Entered: KES <span id="total-amount">0.00</span>
                </div>

                <div class="payments">
                    <label>Payment Method:</label>
                    <select name="payment_type" required>
                    <option value="Cash">Cash</option>
                    </select>
                </div>

            <button type="submit" class="btn">Make Payment</button>
        </form>
        </div>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
  // Check if jQuery loaded
  if (typeof $ === 'undefined') {
    console.error('jQuery not loaded!');
    return;
  } else {
    console.log('jQuery version:', $.fn.jquery);
  }

  // Student search AJAX live suggestions
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
    
    // Clear suggestions if input is empty
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
              <div class="suggestion-item" 
                  data-admission="${student.admission_no}" 
                  data-name="${student.name}" 
                  data-class="${student.class}">
                ${student.name} - ${student.admission_no} - ${student.class}
              </div>
            `);
          });
        } else {
          $('#suggestions').append('<div class="suggestion-item">No records found</div>');
        }
      },
      error: function (xhr, status, error) {
        console.error('Error fetching students:', status, error);
        $('#suggestions').html('<div class="suggestion-item">Error fetching data</div>');
      }
    });
  });

  // When user clicks a suggestion item
  $('#suggestions').on('click', '.suggestion-item', function () {
    const name = $(this).data('name');
    const admission = $(this).data('admission');
    const studentClass = $(this).data('class');

    $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').empty();
  });

  // Hide suggestions if clicking outside the form group
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.form-group').length) {
      $('#suggestions').empty();
    }
  });
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
<script>
function calculateTotalAmount() {
  let total = 0;

  // Add school and lunch fee
  const schoolAmount = parseFloat($('input[name="school_amount"]').val()) || 0;
  const lunchAmount = parseFloat($('input[name="lunch_amount"]').val()) || 0;
  total += schoolAmount + lunchAmount;

  // Add 'other' fees
  $('input[name^="others"]').each(function () {
    const val = parseFloat($(this).val());
    if (!isNaN(val)) total += val;
  });

  // Add book payments
  $('input[name^="books"][name$="[amount]"]').each(function () {
    const val = parseFloat($(this).val());
    if (!isNaN(val)) total += val;
  });

  // Add uniform payments
  $('input[name^="uniforms"][name$="[amount]"]').each(function () {
    const val = parseFloat($(this).val());
    if (!isNaN(val)) total += val;
  });

  // Update display
  $('#total-amount').text(total.toFixed(2));
}

$(document).ready(function () {
  // Recalculate total whenever any relevant input changes
  $('input[type="number"]').on('input', calculateTotalAmount);
});
</script>

</body>
</html>
