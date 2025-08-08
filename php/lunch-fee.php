<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$daily_fee = 70;
$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_method'] ?? 'Cash';
    $receipt_no = trim($_POST['receipt_number'] ?? '');

    if (!$admission_no || $amount <= 0 || !$receipt_no) {
        echo "<script>alert('Invalid input! Missing admission number, amount, or receipt.'); window.location.href='pay-lunch.php';</script>";
        exit();
    }

    $original_amt = $amount;

    // 1. Validate student
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) {
        echo "<script>alert('Student not found.'); window.location.href='pay-lunch.php';</script>";
        exit();
    }
    $name = $student['name'];
    $class = $student['class'];

    // 2. Get terms ordered by start ascending
    $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
    if (!$terms_res || $terms_res->num_rows === 0) {
        echo "<script>alert('No terms available.'); window.location.href='pay-lunch.php';</script>";
        exit();
    }

    // 3. Helper to insert a week row
    function insertWeek($conn, $admission_no, $term_id, $week_num, $payment_type, $daily_fee) {
        $total_weekly = $daily_fee * 5;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward)
            VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly, $week_num, $total_weekly, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // 4. Process payments per term, oldest-first
    while ($amount > 0 && ($term = $terms_res->fetch_assoc())) {
        $termId = $term['id'];

        // Calculate total term weeks
        $daysInTerm = (strtotime($term['end_date']) - strtotime($term['start_date'])) / 86400 + 1;
        $total_weeks = ceil($daysInTerm / 5);

        // Find last paid day by week from lunch_fees
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? ORDER BY week_number DESC LIMIT 1");
        $stmt->bind_param("si", $admission_no, $termId);
        $stmt->execute();
        $lastWeek = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $startWeek = $lastWeek ? ($lastWeek['balance'] == 0 ? $lastWeek['week_number'] + 1 : $lastWeek['week_number']) : 1;

        for ($wk = $startWeek; $wk <= $total_weeks && $amount > 0; $wk++) {
            // Ensure week record exists
            $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? AND week_number = ?");
            $stmt->bind_param("sii", $admission_no, $termId, $wk);
            $stmt->execute();
            $weekData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$weekData) {
                insertWeek($conn, $admission_no, $termId, $wk, $payment_type, $daily_fee);
                $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? AND week_number = ?");
                $stmt->bind_param("sii", $admission_no, $termId, $wk);
                $stmt->execute();
                $weekData = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            foreach ($valid_days as $dayName) {
                if ($amount <= 0) break;

                // Get day record, check attendance & holiday
                $stmt = $conn->prepare("
                    SELECT d.id, d.is_public_holiday,
                           a.status IS NULL OR a.status = 'Present' AS is_present
                    FROM days d
                    LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
                    LEFT JOIN weeks w ON w.id = d.week_id
                    WHERE w.term_id = ? AND w.week_number = ? AND d.day_name = ?
                ");
                $stmt->bind_param("siis", $admission_no, $termId, $wk, $dayName);
                $stmt->execute();
                $dd = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$dd || $dd['is_public_holiday'] || !$dd['is_present']) {
                    continue;
                }

                $dayCol = strtolower($dayName);
                $paidToday = floatval($weekData[$dayCol]);
                if ($paidToday >= $daily_fee) continue;

                $topUp = min($daily_fee - $paidToday, $amount);

                $stmt = $conn->prepare("
                    UPDATE lunch_fees
                    SET $dayCol = $dayCol + ?, total_paid = total_paid + ?, balance = balance - ?
                    WHERE id = ?
                ");
                $stmt->bind_param("dddi", $topUp, $topUp, $topUp, $weekData['id']);
                $stmt->execute();
                $stmt->close();

                $amount -= $topUp;
                $weekData[$dayCol] += $topUp;
                $weekData['balance'] -= $topUp;
            }
        }

        // Carry forward if payment still has leftover
        if ($amount > 0 && $wk > $total_weeks) {
            $lastWeekNum = min($total_weeks, $startWeek);
            $stmt = $conn->prepare("UPDATE lunch_fees SET carry_forward = carry_forward + ? WHERE admission_no = ? AND term_id = ? AND week_number = ?");
            $stmt->bind_param("dsii", $amount, $admission_no, $termId, $lastWeekNum);
            $stmt->execute();
            $stmt->close();
            $amount = 0;
        }
    }

    // 5. Log transaction
    $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_no, $original_amt, $payment_type);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Payment successful! Receipt #: {$receipt_no}'); window.location.href='pay-lunch.php';</script>";
    exit();
}

$conn->close();
?>
