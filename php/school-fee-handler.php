<?php
session_start();

if (!isset($_SESSION['username'])) {
    echo "<script>alert('Unauthorized access. Please login.'); window.location.href='login.php';</script>";
    exit();
}

include 'db.php';

// Fetch uniforms
$uniforms = $conn->query("SELECT DISTINCT uniform_type FROM uniform_prices ORDER BY uniform_type ASC");
$uniform_sizes = [];
$res = $conn->query("SELECT uniform_type, size FROM uniform_prices ORDER BY uniform_type, size ASC");
while($row = $res->fetch_assoc()) {
    $uniform_sizes[$row['uniform_type']][] = $row['size'];
}
$uniform_sizes_json = json_encode($uniform_sizes);

// Fetch books
$books = $conn->query("SELECT book_name FROM book_prices ORDER BY book_name ASC");

$daily_fee = 70;
$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $admission_no   = trim($_POST['admission_no'] ?? '');
    $school_amount  = floatval($_POST['school_amount'] ?? 0);
    $lunch_amount   = floatval($_POST['lunch_amount'] ?? 0);
    $payment_type   = $_POST['payment_type'] ?? 'Cash';

    if (empty($admission_no)) {
        echo "<script>alert('Admission number required.'); window.history.back();</script>";
        exit();
    }

    // === Find Student ===
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no=?");
    $stmt->bind_param("s",$admission_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $stmt = $conn->prepare("SELECT name, class_completed as class FROM graduated_students WHERE admission_no=?");
        $stmt->bind_param("s",$admission_no);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$student) {
        echo "<script>alert('Student not found!'); window.history.back();</script>";
        exit();
    }

    $name = $student['name'];
    $class = $student['class'];

    // === Get Current Term ===
    $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
    $terms_arr = $terms_res->fetch_all(MYSQLI_ASSOC);
    $current_term = end($terms_arr);
    $current_term_number = $current_term['term_number'];

    // === Generate Receipt Number ===
    $random_str = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4);
    $receipt_number = "KSR-T{$current_term_number}-{$random_str}";

    $conn->begin_transaction();

    try {
        // ---------------------------
        // SCHOOL FEE
        // ---------------------------
        if ($school_amount > 0) {
            $stmt = $conn->prepare("SELECT total_fee, amount_paid, balance FROM school_fees WHERE admission_no=?");
            $stmt->bind_param("s",$admission_no);
            $stmt->execute();
            $fee_record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fee_record) throw new Exception("School fee record not found!");

            $new_total_paid = $fee_record['amount_paid'] + $school_amount;
            $new_balance = $fee_record['total_fee'] - $new_total_paid;

            $stmt = $conn->prepare("UPDATE school_fees SET amount_paid=?, balance=? WHERE admission_no=?");
            $stmt->bind_param("dds",$new_total_paid,$new_balance,$admission_no);
            $stmt->execute(); $stmt->close();

            $stmt = $conn->prepare("INSERT INTO school_fee_transactions (name,admission_no,class,amount_paid,receipt_number,payment_type) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssdss",$name,$admission_no,$class,$school_amount,$receipt_number,$payment_type);
            $stmt->execute(); $stmt->close();
        }

        // ---------------------------
        // LUNCH FEE
        // ---------------------------
        if ($lunch_amount > 0) {
            $original_amt = $lunch_amount;
            function insertWeek($conn,$admission_no,$term_id,$week_num,$payment_type,$daily_fee){
                $total_weekly = $daily_fee*5;
                $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no,term_id,total_paid,balance,week_number,total_amount,payment_type,carry_forward) VALUES (?,?,0,?,?,?,?,'0')");
                $stmt->bind_param("sidiss",$admission_no,$term_id,$total_weekly,$week_num,$total_weekly,$payment_type);
                $stmt->execute(); $stmt->close();
            }

            foreach([$current_term] as $term){
                $termId = $term['id'];
                $daysInTerm = (strtotime($term['end_date']) - strtotime($term['start_date']))/86400 +1;
                $total_weeks = ceil($daysInTerm/5);

                $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? ORDER BY week_number DESC LIMIT 1");
                $stmt->bind_param("si",$admission_no,$termId);
                $stmt->execute();
                $lastWeek = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $startWeek = $lastWeek ? ($lastWeek['balance']==0?$lastWeek['week_number']+1:$lastWeek['week_number']):1;

                for($wk=$startWeek;$wk<=$total_weeks && $lunch_amount>0;$wk++){
                    $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=?");
                    $stmt->bind_param("sii",$admission_no,$termId,$wk);
                    $stmt->execute();
                    $weekData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if(!$weekData) insertWeek($conn,$admission_no,$termId,$wk,$payment_type,$daily_fee);

                    // distribute per day
                    foreach($valid_days as $dayName){
                        if($lunch_amount<=0) break;
                        $dayCol = strtolower($dayName);
                        $stmt = $conn->prepare("SELECT id,$dayCol FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=?");
                        $stmt->bind_param("sii",$admission_no,$termId,$wk);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $topUp = min($daily_fee - $row[$dayCol], $lunch_amount);
                        if($topUp>0){
                            $stmt = $conn->prepare("UPDATE lunch_fees SET $dayCol=$dayCol+?, total_paid=total_paid+?, balance=balance-? WHERE id=?");
                            $stmt->bind_param("dddi",$topUp,$topUp,$topUp,$row['id']);
                            $stmt->execute(); $stmt->close();
                            $lunch_amount -= $topUp;
                        }
                    }
                }
            }

            $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name,class,admission_no,receipt_number,amount_paid,payment_type) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssds",$name,$class,$admission_no,$receipt_number,$original_amt,$payment_type);
            $stmt->execute(); $stmt->close();
        }

        // ---------------------------
        // OTHER FEES
        // ---------------------------
        $other_fees = [
            'admission_fee'   => 'Admission',
            'activity_fee'    => 'Activity',
            'exam_fee'        => 'Exam',
            'interview_fee'   => 'Interview',
            'graduation_fee'  => 'Graduation',
            'prize_giving_fee'=> 'Prize Giving'
        ];

        // Allowed fee amounts (fixed caps)
        $allowed_fees = [
            "Admission"     => 1000,
            "Activity"      => 100,
            "Exam"          => 100,
            "Interview"     => 200,
            "Prize Giving"  => 500,
            "Graduation"    => 1500
        ];

        foreach ($other_fees as $postname => $fee_type) {
            $amount = floatval($_POST[$postname] ?? 0);
            if ($amount <= 0) continue;

            // Fetch latest record for this fee_type
            $stmt = $conn->prepare("
                SELECT id, amount_paid, total_amount, balance 
                FROM others 
                WHERE admission_no=? AND fee_type=? 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->bind_param("ss", $admission_no, $fee_type);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($old) {
                // Pay against existing balance
                $toClear = min($old['balance'], $amount);

                if ($toClear > 0) {
                    $stmt = $conn->prepare("
                        UPDATE others 
                        SET amount_paid = amount_paid + ?, balance = balance - ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ddi", $toClear, $toClear, $old['id']);
                    $stmt->execute();
                    $stmt->close();
                    $amount -= $toClear;
                }

                // If fully paid already, skip
                if ($old['balance'] <= 0 && $amount <= 0) continue;
            }

            // If still money left, create a new record (fresh fee cycle)
            if ($amount > 0) {
                $total_allowed = $allowed_fees[$fee_type];
                $balance = max($total_allowed - $amount, 0);

                $stmt = $conn->prepare("
                    INSERT INTO others 
                    (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, balance, payment_type) 
                    VALUES (?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param(
                    "ssssdddis",
                    $receipt_number,
                    $admission_no,
                    $name,
                    $current_term_number,
                    $fee_type,
                    $total_allowed,
                    $amount,
                    $balance,
                    $payment_type
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        // ---------------------------
        // UNIFORMS
        // ---------------------------
        $uniform_type     = $_POST['uniform_type'] ?? '';
        $uniform_size     = $_POST['uniform_size'] ?? '';
        $uniform_quantity = intval($_POST['uniform_quantity'] ?? 0);
        $uniform_paid     = floatval($_POST['uniform_amount'] ?? 0);

        if(!empty($uniform_type) && $uniform_quantity > 0 && $uniform_paid > 0){
            // Check for previous balance
            $stmt = $conn->prepare("SELECT id, balance FROM uniform_purchases WHERE admission_no=? AND uniform_type=? AND size=? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("sss",$admission_no,$uniform_type,$uniform_size);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if($old){
                $toClear = min($old['balance'], $uniform_paid);
                $stmt = $conn->prepare("UPDATE uniform_purchases SET amount_paid=amount_paid+?, balance=balance-? WHERE id=?");
                $stmt->bind_param("ddi",$toClear,$toClear,$old['id']);
                $stmt->execute(); $stmt->close();
                $uniform_paid -= $toClear;
            }

            if($uniform_paid>0){
                $stmt = $conn->prepare("SELECT price FROM uniform_prices WHERE uniform_type=? AND size=? LIMIT 1");
                $stmt->bind_param("ss",$uniform_type,$uniform_size);
                $stmt->execute();
                $price_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if(!$price_row) throw new Exception("Uniform price not found.");

                $total_price = $price_row['price'] * $uniform_quantity;
                $balance = $total_price - $uniform_paid;

                $stmt = $conn->prepare("INSERT INTO uniform_purchases (receipt_number,name,admission_no,uniform_type,size,quantity,total_price,amount_paid,balance,payment_type) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssssddds",$receipt_number,$name,$admission_no,$uniform_type,$uniform_size,$uniform_quantity,$total_price,$uniform_paid,$balance,$payment_type);
                $stmt->execute(); $stmt->close();

                $stmt = $conn->prepare("INSERT INTO purchase_transactions (receipt_number,admission_no,student_name,total_amount_paid,payment_type) VALUES (?,?,?,?,?)");
                $stmt->bind_param("ssdss",$receipt_number,$admission_no,$name,$uniform_paid,$payment_type);
                $stmt->execute(); $stmt->close();
            }
        }

        // ---------------------------
        // BOOKS
        // ---------------------------
        $book_name     = $_POST['book_name'] ?? '';
        $book_quantity = intval($_POST['book_quantity'] ?? 0);
        $book_paid     = floatval($_POST['book_amount'] ?? 0);

        if(!empty($book_name) && $book_quantity > 0 && $book_paid > 0){
            // Check previous balance
            $stmt = $conn->prepare("SELECT id, balance FROM book_purchases WHERE admission_no=? AND book_name=? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("ss",$admission_no,$book_name);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if($old){
                $toClear = min($old['balance'],$book_paid);
                $stmt = $conn->prepare("UPDATE book_purchases SET amount_paid=amount_paid+?, balance=balance-? WHERE id=?");
                $stmt->bind_param("ddi",$toClear,$toClear,$old['id']);
                $stmt->execute(); $stmt->close();
                $book_paid -= $toClear;
            }

            if($book_paid>0){
                $stmt = $conn->prepare("SELECT price FROM book_prices WHERE book_name=? LIMIT 1");
                $stmt->bind_param("s",$book_name);
                $stmt->execute();
                $book_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if(!$book_row) throw new Exception("Book price not found.");

                $total_price = $book_row['price'] * $book_quantity;
                $balance = $total_price - $book_paid;

                $stmt = $conn->prepare("INSERT INTO book_purchases (receipt_number,admission_no,name,book_name,quantity,total_price,amount_paid,balance,payment_type) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssdddds",$receipt_number,$admission_no,$name,$book_name,$book_quantity,$total_price,$book_paid,$balance,$payment_type);
                $stmt->execute(); $stmt->close();

                $stmt = $conn->prepare("INSERT INTO purchase_transactions (receipt_number,admission_no,student_name,total_amount_paid,payment_type) VALUES (?,?,?,?,?)");
                $stmt->bind_param("ssdss",$receipt_number,$admission_no,$name,$book_paid,$payment_type);
                $stmt->execute(); $stmt->close();
            }
        }

        $conn->commit();
        echo "<script>alert('Payment successful! Receipt: $receipt_number'); window.location.href='school-fee-handler.php';</script>";
    } catch(Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: ".$e->getMessage()."'); window.history.back();</script>";
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background:#f9f9f9; }
    .payment-container {
      width: 600px; margin: 50px auto; background:#fff; padding:20px;
      border-radius:10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { text-align:center; margin-bottom:20px; }
    h3 { margin-top:25px; }
    label { display:block; margin-top:15px; font-weight:bold; }
    input[type="text"], input[type="number"], select {
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
    
    <!-- Admission Number -->
    <label for="admission_no">Admission Number:</label>
    <input type="text" name="admission_no" id="admission_no" required>

    <!-- School Fee -->
    <label for="school_amount">School Fee:</label>
    <input type="number" name="school_amount" id="school_amount" placeholder="Enter amount" min="0" step="0.01">

    <!-- Lunch Fee -->
    <label for="lunch_amount">Lunch Fee:</label>
    <input type="number" name="lunch_amount" id="lunch_amount" placeholder="Enter amount" min="0" step="0.01">

    <h3>Other Fees</h3>
    <label for="admission_fee">Admission Fee:</label>
    <input type="number" name="admission_fee" id="admission_fee" placeholder="Enter amount" min="0" step="0.01">

    <label for="activity_fee">Activity Fee:</label>
    <input type="number" name="activity_fee" id="activity_fee" placeholder="Enter amount" min="0" step="0.01">

    <label for="exam_fee">Exam Fee:</label>
    <input type="number" name="exam_fee" id="exam_fee" placeholder="Enter amount" min="0" step="0.01">

    <label for="interview_fee">Interview Fee:</label>
    <input type="number" name="interview_fee" id="interview_fee" placeholder="Enter amount" min="0" step="0.01">

    <label for="graduation_fee">Graduation Fee:</label>
    <input type="number" name="graduation_fee" id="graduation_fee" placeholder="Enter amount" min="0" step="0.01">

    <label for="prize_giving_fee">Prize Giving Fee:</label>
    <input type="number" name="prize_giving_fee" id="prize_giving_fee" placeholder="Enter amount" min="0" step="0.01">

    <h3>Uniform Purchases</h3>
    <label for="uniform_type">Uniform Type:</label>
    <select name="uniform_type" id="uniform_type">
    <option value="">--Select--</option>
    <?php while($row = $uniforms->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($row['uniform_type']) ?>"><?= htmlspecialchars($row['uniform_type']) ?></option>
    <?php endwhile; ?>
    </select>

    <label for="uniform_size">Size:</label>
    <select name="uniform_size" id="uniform_size">
    <option value="">--Select Type First--</option>
    </select>

    <label for="uniform_quantity">Quantity:</label>
    <input type="number" name="uniform_quantity" id="uniform_quantity" placeholder="Quantity" min="0">

    <label for="uniform_amount">Amount Paid:</label>
    <input type="number" name="uniform_amount" id="uniform_amount" placeholder="Amount Paid" min="0" step="0.01">

    <h3>Book Purchases</h3>
    <label for="book_name">Book Name:</label>
    <select name="book_name" id="book_name">
    <option value="">--Select--</option>
    <?php while($row = $books->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($row['book_name']) ?>"><?= htmlspecialchars($row['book_name']) ?></option>
    <?php endwhile; ?>
    </select>

    <label for="book_quantity">Quantity:</label>
    <input type="number" name="book_quantity" id="book_quantity" placeholder="Quantity" min="0">

    <label for="book_amount">Amount Paid:</label>
    <input type="number" name="book_amount" id="book_amount" placeholder="Amount Paid" min="0" step="0.01">


    <!-- Payment Method -->
    <label for="payment_type">Payment Method:</label>
    <select name="payment_type" id="payment_type" required>
      <option value="Cash">Cash</option>
      <option value="M-Pesa">M-Pesa</option>
      <option value="Bank Transfer">Bank Transfer</option>
      <option value="Other">Other</option>
    </select>

    <!-- Submit -->
    <button type="submit" class="btn">Make Payment</button>
  </form>
</div>
<script>
const uniformSizes = <?= $uniform_sizes_json ?>;

document.getElementById('uniform_type').addEventListener('change', function() {
    const type = this.value;
    const sizeSelect = document.getElementById('uniform_size');
    sizeSelect.innerHTML = '<option value="">--Select--</option>';

    if (type && uniformSizes[type]) {
        uniformSizes[type].forEach(size => {
            const opt = document.createElement('option');
            opt.value = size;
            opt.textContent = size;
            sizeSelect.appendChild(opt);
        });
    }
});
</script>
</body>
</html>
