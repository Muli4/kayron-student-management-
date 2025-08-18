<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit();
}
include 'db.php';

$daily_fee = 70;
$valid_days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_method'] ?? 'Cash';

    if (!$admission_no || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input!']);
        exit();
    }

    $original_amt = $amount;

    // Validate student
    $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }
    $name = $student['name'];
    $class = $student['class'];

    // Get all terms
    $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
    if (!$terms_res || $terms_res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No terms available.']);
        exit();
    }
    $terms_arr = $terms_res->fetch_all(MYSQLI_ASSOC);
    $current_term = end($terms_arr);
    $current_term_number = $current_term['term_number'];

    $randomDigits = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $receipt_no = "LF-T{$current_term_number}-{$randomDigits}";

    // Helper to insert new lunch week
    function insertWeek($conn, $admission_no, $term_id, $week_num, $payment_type, $daily_fee) {
        $total_weekly = $daily_fee * 5;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward) VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly, $week_num, $total_weekly, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // === Find previous term if any
    $prev_term = null;
    foreach ($terms_arr as $term) {
        if ($term['id'] < $current_term['id']) {
            $prev_term = $term;
        }
    }

    if (!$prev_term) {
        // No previous term at all → start from current term
        $terms_to_process = [$current_term];
    } else {
        // Check if student had lunch fees in previous term
        $stmt = $conn->prepare("SELECT 1 FROM lunch_fees WHERE admission_no = ? AND term_id = ? LIMIT 1");
        $stmt->bind_param("si", $admission_no, $prev_term['id']);
        $stmt->execute();
        $has_prev_lunch = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$has_prev_lunch) {
            // Previous term exists but no lunch fees → start from current term
            $terms_to_process = [$current_term];
        } else {
            // Continue paying from previous term up to current term
            $start_collecting = false;
            $terms_to_process = [];
            foreach ($terms_arr as $t) {
                if ($t['id'] == $prev_term['id']) $start_collecting = true;
                if ($start_collecting) $terms_to_process[] = $t;
            }
        }
    }

    // === Payment distribution
    foreach ($terms_to_process as $term) {
        if ($amount <= 0) break;

        $termId = $term['id'];
        $daysInTerm = (strtotime($term['end_date']) - strtotime($term['start_date'])) / 86400 + 1;
        $total_weeks = ceil($daysInTerm / 5);

        // Get last week for this term
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? ORDER BY week_number DESC LIMIT 1");
        $stmt->bind_param("si", $admission_no, $termId);
        $stmt->execute();
        $lastWeek = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $startWeek = $lastWeek ? ($lastWeek['balance'] == 0 ? $lastWeek['week_number'] + 1 : $lastWeek['week_number']) : 1;

        for ($wk = $startWeek; $wk <= $total_weeks && $amount > 0; $wk++) {
            // Ensure week exists
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

                // Attendance check — assume present if no record
                $stmt = $conn->prepare("
                    SELECT d.id, d.is_public_holiday,
                           (a.status IS NULL OR a.status = 'Present') AS is_present
                    FROM days d
                    LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
                    LEFT JOIN weeks w ON w.id = d.week_id
                    WHERE w.term_id = ? AND w.week_number = ? AND d.day_name = ?
                ");
                $stmt->bind_param("siis", $admission_no, $termId, $wk, $dayName);
                $stmt->execute();
                $dd = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$dd || $dd['is_public_holiday'] || !$dd['is_present']) continue;

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

        // Carry forward leftover
        if ($amount > 0 && $wk > $total_weeks) {
            $lastWeekNum = min($total_weeks, $startWeek);
            $stmt = $conn->prepare("UPDATE lunch_fees SET carry_forward = carry_forward + ? WHERE admission_no = ? AND term_id = ? AND week_number = ?");
            $stmt->bind_param("dsii", $amount, $admission_no, $termId, $lastWeekNum);
            $stmt->execute();
            $stmt->close();
            $amount = 0;
        }
    }

    // Store transaction
    $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_no, $original_amt, $payment_type);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => "Payment successful ✅"]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
exit();
$conn->close();
?>
