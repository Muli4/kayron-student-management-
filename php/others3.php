<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/errors.log');

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

$prefill_adm = $_GET['admission_no'] ?? '';
$prefill_name = $_GET['student_name'] ?? '';

$daily_fee = 70;
$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admission_no = trim($_POST['admission_no'] ?? '');
    $payment_type = ucfirst(strtolower($_POST['payment_type'] ?? 'Cash'));
    $fees = $_POST['fees'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    $school_fee_amount = floatval($_POST['amounts']['School Fee'] ?? 0);
    $lunch_amount = floatval($_POST['amounts']['Lunch Fee'] ?? 0);

    if (!$admission_no || (empty($fees) && $school_fee_amount <= 0 && $lunch_amount <= 0)) {
        echo json_encode(['success' => false, 'message' => 'Select at least one fee or enter an amount.']);
        exit();
    }

    // --- Fetch student ---
    $stmt = $conn->prepare("SELECT name, term, class FROM student_records WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($name, $term, $student_class);
    $found = $stmt->fetch();
    $stmt->close();

    // After fetching student from student_records or graduated_students, add:
    $stmt = $conn->prepare("SELECT graduation_date FROM graduated_students WHERE admission_no = ?");
    $stmt->bind_param("s", $admission_no);
    $stmt->execute();
    $stmt->bind_result($graduation_date);
    $stmt->fetch();
    $stmt->close();

// If student not graduated, $graduation_date will be null or empty


    if (!$found) {
        $stmt2 = $conn->prepare("SELECT name, term, class FROM graduated_students WHERE admission_no = ?");
        $stmt2->bind_param("s", $admission_no);
        $stmt2->execute();
        $stmt2->bind_result($name, $term, $student_class);
        $found2 = $stmt2->fetch();
        $stmt2->close();

        if (!$found2) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit();
        }
    }

    if (!$term) $term = 'Unknown';

    // Get current term
    $term_sql = "SELECT term_number, year FROM terms ORDER BY id DESC LIMIT 1";
    $term_result = $conn->query($term_sql);
    if ($term_result && $term_result->num_rows > 0) {
        $term_data = $term_result->fetch_assoc();
        $term_number = $term_data['term_number'];
        $year_last_three = substr($term_data['year'], -3); // Last three digits of the year
    } else {
        $term_number = 1; // default
        $year_last_three = substr(date('Y'), -3);
    }

    // Generate random 4-character alphanumeric string (letters and numbers)
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random_str = '';
    for ($i = 0; $i < 4; $i++) {
        $random_str .= $characters[random_int(0, strlen($characters) - 1)];
    }

    // Format receipt number
    $receipt_number = "SCF-T{$term_number}-{$random_str}";

    $fee_totals = [
        "Admission" => 1000,
        "Activity" => 100,
        "Exam" => 100,
        "Interview" => 200,
        "Prize Giving" => 500,
        "Graduation" => 1500
    ];

    $conn->begin_transaction();
    try {
        // --- Process Other Fees ---
        foreach ($fees as $fee_type) {
            $amount_paid = floatval($amounts[$fee_type] ?? 0);
            if ($amount_paid <= 0 || in_array($fee_type, ['School Fee', 'Lunch Fee'])) continue;

            if ($fee_type === "Graduation" && strcasecmp($student_class, "PP2") !== 0) {
                throw new Exception("Graduation fee can only be paid by PP2 students.");
            }

            $total_amount = $fee_totals[$fee_type] ?? $amount_paid;

            $stmt = $conn->prepare("SELECT id, amount_paid FROM others WHERE admission_no=? AND fee_type=? AND term=? LIMIT 1");
            $stmt->bind_param("sss", $admission_no, $fee_type, $term);
            $stmt->execute();
            $stmt->bind_result($others_id, $existing_paid);
            $found_fee = $stmt->fetch();
            $stmt->close();

            if ($found_fee) {
                $new_paid = $existing_paid + $amount_paid;
                if ($new_paid > $total_amount) {
                    throw new Exception("Payment for $fee_type exceeds total amount.");
                }
                $upd = $conn->prepare("UPDATE others SET amount_paid=? WHERE id=?");
                $upd->bind_param("di", $new_paid, $others_id);
                $upd->execute();
                $upd->close();
            } else {
                $ins = $conn->prepare("INSERT INTO others (receipt_number, admission_no, name, term, fee_type, total_amount, amount_paid, payment_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("sssssdss", $receipt_number, $admission_no, $name, $term, $fee_type, $total_amount, $amount_paid, $payment_type);
                $ins->execute();
                $others_id = $ins->insert_id;
                $ins->close();
            }

            $tr = $conn->prepare("INSERT INTO other_transactions (others_id, amount_paid, fee_type, payment_type, receipt_number) VALUES (?, ?, ?, ?, ?)");
            $tr->bind_param("idsss", $others_id, $amount_paid, $fee_type, $payment_type, $receipt_number);
            $tr->execute();
            $tr->close();
        }
        
        $term_number = 0;
        $year = 0;
        $default_year = date('Y');

        if (preg_match('/term\s*(\d+)/i', $term, $matches)) {
            $term_number = (int)$matches[1];
            $year = $default_year;
            error_log("Parsed term_number=$term_number, defaulting year=$year from term='$term'");
        } else {
            error_log("Failed to parse term_number from term='$term'");
        }

        if (!isset($current_term_id) || !isset($current_term_number)) {
            if ($term_number > 0 && $year > 0) {
                $term_stmt = $conn->prepare("SELECT id, term_number, start_date FROM terms WHERE term_number = ? AND year = ? LIMIT 1");
                if ($term_stmt === false) {
                    error_log("Failed to prepare statement for terms query: " . $conn->error);
                } else {
                    $term_stmt->bind_param("ii", $term_number, $year);
                    $term_stmt->execute();
                    $term_data = $term_stmt->get_result()->fetch_assoc();
                    $term_stmt->close();

                    if ($term_data) {
                        $current_term_id = (int)$term_data['id'];
                        $current_term_number = (int)$term_data['term_number'];
                        $current_term_start = $term_data['start_date'];
                        error_log("Fetched term data: id=$current_term_id, number=$current_term_number, start=$current_term_start");
                    } else {
                        error_log("ERROR: Failed to fetch term data for term_number=$term_number, year=$year");
                        $current_term_id = 0;
                        $current_term_number = 0;
                        $current_term_start = date('Y-m-d');
                    }
                }
            } else {
                error_log("ERROR: term_number or year not set before fetching term data");
                $current_term_id = 0;
                $current_term_number = 0;
                $current_term_start = date('Y-m-d');
            }
        }


        // --- Process Lunch Fee ---
        if ($lunch_amount > 0) {
            $amount = $lunch_amount;
            $original_amt = $amount;

            // ✅ Check if the student has any lunch fee records (i.e., new or returning)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM lunch_fees WHERE admission_no = ?");
            $stmt->bind_param("s", $admission_no);
            $stmt->execute();
            $stmt->bind_result($lunch_count);
            $stmt->fetch();
            $stmt->close();

            $is_new_student = ($lunch_count === 0);

            // Fetch all terms
            $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
            if (!$terms_res || $terms_res->num_rows === 0) throw new Exception('No terms available for lunch payment.');

            function insertWeek($conn, $admission_no, $term_id, $week_num, $payment_type, $daily_fee) {
                $total_weekly = $daily_fee * 5;
                $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward)
                    VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
                $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly, $week_num, $total_weekly, $payment_type);
                $stmt->execute();
                $stmt->close();
            }

            while ($amount > 0 && ($termRow = $terms_res->fetch_assoc())) {
                $termId = $termRow['id'];

                // ✅ If new student, only allow processing the current term
                if ($is_new_student && $termId != $current_term_id) {
                    continue;
                }

                $daysInTerm = (strtotime($termRow['end_date']) - strtotime($termRow['start_date'])) / 86400 + 1;
                $total_weeks = ceil($daysInTerm / 5);

                $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? ORDER BY week_number DESC LIMIT 1");
                $stmt->bind_param("si", $admission_no, $termId);
                $stmt->execute();
                $lastWeek = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $startWeek = $lastWeek ? ($lastWeek['balance'] == 0 ? $lastWeek['week_number'] + 1 : $lastWeek['week_number']) : 1;

                for ($wk = $startWeek; $wk <= $total_weeks && $amount > 0; $wk++) {
                    // Fetch or create the week's lunch record
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

                        // Skip if public holiday or not present
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

                // If payment still remains after the last week → store as carry_forward
                if ($amount > 0 && $wk > $total_weeks) {
                    $lastWeekNum = min($total_weeks, $startWeek);
                    $stmt = $conn->prepare("UPDATE lunch_fees SET carry_forward = carry_forward + ? WHERE admission_no = ? AND term_id = ? AND week_number = ?");
                    $stmt->bind_param("dsii", $amount, $admission_no, $termId, $lastWeekNum);
                    $stmt->execute();
                    $stmt->close();

                    $amount = 0;
                }
            }

            // Log transaction
            $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssds", $name, $student_class, $admission_no, $receipt_number, $original_amt, $payment_type);
            $stmt->execute();
            $stmt->close();
        }


        // --- Process School Fee ---
        if ($school_fee_amount > 0) {
            $stmt = $conn->prepare("SELECT total_fee, amount_paid, balance FROM school_fees WHERE admission_no=? LIMIT 1");
            $stmt->bind_param("s", $admission_no);
            $stmt->execute();
            $fee_record = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fee_record) throw new Exception("No school fee record found.");

            $new_total = $fee_record['amount_paid'] + $school_fee_amount;
            if ($new_total > $fee_record['total_fee']) {
                throw new Exception("Payment for School Fee exceeds total amount.");
            }

            $new_balance = $fee_record['total_fee'] - $new_total;

            $upd = $conn->prepare("UPDATE school_fees SET amount_paid=?, balance=? WHERE admission_no=?");
            $upd->bind_param("dds", $new_total, $new_balance, $admission_no);
            $upd->execute();
            $upd->close();

            $ins_tr = $conn->prepare("INSERT INTO school_fee_transactions (name, admission_no, class, amount_paid, receipt_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
            $ins_tr->bind_param("sssdss", $name, $admission_no, $student_class, $school_fee_amount, $receipt_number, $payment_type);
            $ins_tr->execute();
            $ins_tr->close();
        }

        $conn->commit();

        // --- Prepare balances for response ---
        $paid_details = [];

        // ======== School Fee ========
        if ($school_fee_amount > 0) {
            $stmt = $conn->prepare("SELECT amount_paid, total_fee, balance FROM school_fees WHERE admission_no=? LIMIT 1");
            $stmt->bind_param("s", $admission_no);
            $stmt->execute();
            $school_fee = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($school_fee) {
                $paid_details[] = [
                    'fee_type' => 'School Fee',
                    'paid' => floatval($school_fee_amount),
                    'balance' => floatval($school_fee['balance'])
                ];
            }
        }

        // ======== Other Fees ========
        foreach ($fees as $fee_type) {
            $amount_paid = floatval($amounts[$fee_type]);
            if ($amount_paid <= 0 || in_array($fee_type, ['School Fee', 'Lunch Fee'])) continue;

            $stmt = $conn->prepare("
                SELECT SUM(amount_paid) AS total_paid, SUM(total_amount) AS total_amount
                FROM others
                WHERE admission_no=? AND fee_type=? AND term=?
            ");
            $stmt->bind_param("sss", $admission_no, $fee_type, $term);
            $stmt->execute();
            $fee = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $balance = ($fee && $fee['total_amount'] !== null)
                ? max(0, floatval($fee['total_amount']) - floatval($fee['total_paid']))
                : 0;

            $paid_details[] = [
                'fee_type' => $fee_type,
                'paid' => $amount_paid,
                'balance' => $balance
            ];
        }

        // ======== Helper function: Calculate Previous Terms Lunch Balance ========
        function calculatePreviousLunchBalance($conn, $admission_no, $current_term_number, $per_day_fee) {
            $prev_balance = 0;

            $terms_stmt = $conn->prepare("SELECT id FROM terms WHERE term_number < ?");
            $terms_stmt->bind_param("i", $current_term_number);
            $terms_stmt->execute();
            $terms_res = $terms_stmt->get_result();

            while ($term_row = $terms_res->fetch_assoc()) {
                $prev_term_id = (int)$term_row['id'];

                $pay_stmt = $conn->prepare("
                    SELECT week_number, monday, tuesday, wednesday, thursday, friday
                    FROM lunch_fees
                    WHERE admission_no = ? AND term_id = ?
                    ORDER BY week_number ASC
                ");
                $pay_stmt->bind_param("si", $admission_no, $prev_term_id);
                $pay_stmt->execute();
                $pay_result = $pay_stmt->get_result();

                if ($pay_result->num_rows === 0) {
                    $pay_stmt->close();
                    continue;
                }

                $last_paid_week = 0;
                $last_paid_day_index = -1;
                $last_paid_amount = 0;
                $day_list = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

                while ($row = $pay_result->fetch_assoc()) {
                    foreach ($day_list as $i => $day) {
                        $amt = floatval($row[$day]);
                        if ($amt > 0) {
                            $last_paid_week = (int)$row['week_number'];
                            $last_paid_day_index = $i;
                            $last_paid_amount = $amt;
                        }
                    }
                }
                $pay_stmt->close();

                $day_stmt = $conn->prepare("
                    SELECT d.day_name, w.week_number, d.is_public_holiday, a.status
                    FROM days d
                    JOIN weeks w ON d.week_id = w.id
                    LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
                    WHERE w.term_id = ?
                    ORDER BY w.week_number ASC, FIELD(d.day_name, 'Monday','Tuesday','Wednesday','Thursday','Friday')
                ");
                $day_stmt->bind_param("si", $admission_no, $prev_term_id);
                $day_stmt->execute();
                $days_result = $day_stmt->get_result();

                $day_index_map = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4];
                $partial_day_checked = false;

                while ($row = $days_result->fetch_assoc()) {
                    $week = (int)$row['week_number'];
                    $day_name = $row['day_name'];
                    $day_index = $day_index_map[$day_name] ?? -1;
                    $is_holiday = (int)$row['is_public_holiday'];
                    $status = $row['status'] ?? 'Present';

                    if ($day_index === -1 || $is_holiday === 1 || $status === 'Absent') continue;

                    if (!$partial_day_checked && $week === $last_paid_week && $day_index === $last_paid_day_index) {
                        if ($last_paid_amount < $per_day_fee) {
                            $prev_balance += $per_day_fee - $last_paid_amount;
                        }
                        $partial_day_checked = true;
                        continue;
                    }

                    if ($week < $last_paid_week || ($week === $last_paid_week && $day_index <= $last_paid_day_index)) {
                        continue;
                    }

                    $prev_balance += $per_day_fee;
                }

                $day_stmt->close();
            }

            $terms_stmt->close();
            return $prev_balance;
        }


        // ======== Lunch Fee Balance ======== 
        if ($lunch_amount > 0) {
            $per_day_fee = 70;
            $day_map = ['monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3, 'friday' => 4];
            $today = date('Y-m-d');
            $current_term_balance = 0;
            $graduation_date = $graduation_date ?? null;
            $current_term_start = $current_term_start ?? date('Y-m-d', strtotime('-1 month'));

            error_log("Starting lunch balance calc for admission_no=$admission_no");
            error_log("DEBUG: current_term_id=$current_term_id, current_term_number=$current_term_number");

            // Skip if graduated before current term
            if ($graduation_date && strtotime($graduation_date) < strtotime($current_term_start)) {
                error_log("Skipping lunch balance calc: graduated before current term.");
                $current_term_balance = 0;
            } else {
                $weeks_res = $conn->prepare("SELECT id, week_number FROM weeks WHERE term_id = ?");
                $weeks_res->bind_param("i", $current_term_id);
                $weeks_res->execute();
                $weeks_result = $weeks_res->get_result();

                while ($week = $weeks_result->fetch_assoc()) {
                    $week_id = $week['id'];
                    $week_number = $week['week_number'];
                    error_log("Processing week: $week_number (week_id=$week_id)");

                    $days_stmt = $conn->prepare("SELECT id, day_name, is_public_holiday FROM days WHERE week_id = ?");
                    $days_stmt->bind_param("i", $week_id);
                    $days_stmt->execute();
                    $days_result = $days_stmt->get_result();

                    while ($day = $days_result->fetch_assoc()) {
                        $day_id = $day['id'];
                        $day_name_raw = $day['day_name'];
                        $day_name = strtolower($day_name_raw);
                        $is_holiday = (int)$day['is_public_holiday'];

                        if (!isset($day_map[$day_name])) {
                            error_log("Skipping $day_name_raw: not a valid weekday.");
                            continue;
                        }
                        if ($is_holiday) {
                            error_log("Skipping $day_name_raw: public holiday.");
                            continue;
                        }

                        $week_offset = $week_number - 1;
                        $day_offset = $day_map[$day_name];
                        $day_date = date('Y-m-d', strtotime("$current_term_start +{$week_offset} weeks +{$day_offset} days"));

                        if ($day_date > $today) {
                            error_log("Skipping $day_name_raw on $day_date: date is in the future.");
                            continue;
                        }

                        // Attendance
                        $att_stmt = $conn->prepare("
                            SELECT status FROM attendance
                            WHERE admission_no = ? AND term_number = ? AND week_number = ? AND day_id = ?
                        ");
                        $att_stmt->bind_param("siii", $admission_no, $current_term_number, $week_number, $day_id);
                        $att_stmt->execute();
                        $att_row = $att_stmt->get_result()->fetch_assoc();
                        $att_stmt->close();

                        $att_status = $att_row['status'] ?? 'Present';
                        error_log("Attendance on $day_name_raw ($day_date): $att_status");

                        if (strcasecmp($att_status, 'Absent') === 0) {
                            error_log("Skipping $day_name_raw ($day_date): student was absent.");
                            continue;
                        }

                        // Lunch paid check
                        $lunch_stmt = $conn->prepare("
                            SELECT monday, tuesday, wednesday, thursday, friday
                            FROM lunch_fees
                            WHERE admission_no = ? AND term_id = ? AND week_number = ?
                        ");
                        $lunch_stmt->bind_param("sii", $admission_no, $current_term_id, $week_number);
                        $lunch_stmt->execute();
                        $lunch_row = $lunch_stmt->get_result()->fetch_assoc();
                        $lunch_stmt->close();

                        $day_paid = $lunch_row[$day_name] ?? 0;
                        $unpaid = max(0, $per_day_fee - $day_paid);
                        $current_term_balance += $unpaid;

                        error_log("Lunch check: Week $week_number, $day_name_raw ($day_date) - Paid: $day_paid, Unpaid: $unpaid");
                    }
                    $days_stmt->close();
                }
                $weeks_res->close();
            }

            // ======== PREVIOUS TERMS LUNCH BALANCE ========
            $prev_balance = calculatePreviousLunchBalance($conn, $admission_no, $current_term_number, $per_day_fee);

            // ======== SUBTRACT CARRY FORWARD FROM PREVIOUS LUNCH FEES ========
            $carry_stmt = $conn->prepare("
                SELECT IFNULL(SUM(carry_forward),0) AS carry
                FROM lunch_fees
                WHERE admission_no = ? AND term_id < ?
            ");
            $carry_stmt->bind_param("si", $admission_no, $current_term_id);
            $carry_stmt->execute();
            $carry_stmt->bind_result($carry_forward);
            $carry_stmt->fetch();
            $carry_stmt->close();

            // ======== FINAL TOTAL LUNCH BALANCE ========
            $total_balance_before_carry = $prev_balance + $current_term_balance;
            $lunch_balance = max(0, $total_balance_before_carry - $carry_forward);

            error_log("Final lunch balance: current=$current_term_balance, prev=$prev_balance, carry=$carry_forward, total=$lunch_balance");

            $paid_details[] = [
                'fee_type' => 'Lunch Fee',
                'paid' => floatval($lunch_amount),
                'balance' => floatval($lunch_balance)
            ];
        }


        if (ob_get_length()) {
            ob_clean();
        }

        echo json_encode([
            'success' => true,
            'message' => "Payment processed successfully. Receipt #: $receipt_number",
            'receipt_number' => $receipt_number,
            'paid_details' => $paid_details
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false,'message' => $e->getMessage() ]);
        exit();
    }
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Fee Payment</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"><style>
/* Container for the payment form */
.payment-container {
  max-width: 450px;          /* reduce overall width */
  margin: 20px auto;         /* center horizontally with vertical margin */
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  font-family: Arial, sans-serif;
}

/* Heading */
.payment-container h2 {
  text-align: center;
  margin-bottom: 20px;
  font-weight: 700;
  color: #2c3e50;
}
.payment-container h2 i {
  margin-right: 8px;
  vertical-align: middle;
  color: #2980b9;
  font-size: 1.3em;
}

/* Form groups */
.form-group {
  margin-bottom: 15px;
  position: relative;
}

/* Student search input */
#student-search {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
}

/* Suggestions dropdown */
#suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1.5px solid #2980b9;
  border-top: none;
  max-height: 150px;
  overflow-y: auto;
  z-index: 1000;
  font-size: 0.95rem;
}

.suggestion-item {
  padding: 8px 10px;
  cursor: pointer;
}

.suggestion-item:hover {
  background-color: #2980b9;
  color: white;
}

/* Fees list container */
.fees-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

/* Each fee item horizontally aligned */
.fee-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Checkbox style */
.fee-item input[type="checkbox"] {
  transform: scale(1.15);
  cursor: pointer;
}

/* Label for fee */
.fee-item label {
  flex: 1;
  user-select: none;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

/* Amount input: bigger and more padding */
.fee-amount {
  width: 200px;
  padding: 8px 12px;
  font-size: 1.1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
  transition: background-color 0.3s ease;
}

/* Disabled amount input */
.fee-amount:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

/* Total container */
.total-container {
  margin-top: 20px;
  font-weight: 700;
  font-size: 1.25rem;
  color: #2980b9;
  text-align: right;
}

/* Payment type select */
#payment_type {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 1.8px solid #2980b9;
  border-radius: 5px;
}

/* Submit button container */
.button-container {
  margin-top: 25px;
  text-align: center;
}

/* Submit button */
.make-payments-btn {
  background-color: #2980b9;
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.make-payments-btn:hover {
  background-color: #1b6699;
}

/* Messages box */
#messageBox {
  font-size: 0.95rem;
  margin-top: 15px;
}

.error-message {
  color: #c0392b;
  font-weight: 600;
}

.success-message {
  color: #27ae60;
  font-weight: 600;
}

.warning-message {
  color: #f39c12;
  font-weight: 600;
}

/* ===== MEDIA QUERIES KEEPING SAME LAYOUT ===== */
@media (max-width: 600px) {
  .payment-container {
    max-width: 90%;
    padding: 15px;
  }

  .fee-amount {
    width: 140px;  /* smaller but still inline */
    font-size: 1rem;
  }

  .make-payments-btn {
    font-size: 1rem;
    padding: 10px 16px;
  }

  .fee-item {
    gap: 8px;  /* slightly less gap */
  }
}

@media (max-width: 400px) {
  .payment-container {
    max-width: 100%;
    margin: 10px 5px;
    padding: 10px;
  }

  .fee-amount {
    width: 120px; /* keep input smaller but inline */
    font-size: 0.95rem;
  }

  .make-payments-btn {
    width: 100%;
    justify-content: center;
    font-size: 0.95rem;
    padding: 10px;
  }

  #student-search, #payment_type {
    font-size: 0.95rem;
  }

  /* Keep fee items row layout */
  .fee-item {
    flex-direction: row;
    align-items: center;
    gap: 6px;
  }
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>

  <main class="content">
    <div class="payment-container">
      <h2><i class="bx bx-wallet"></i> Student Fee Payment</h2>

      <div id="messageBox"></div>

      <form id="feeForm">
        <!-- Student Search -->
        <div class="form-group">
            <input type="text" id="student-search" placeholder="Search student..." autocomplete="off" 
                  value="<?= htmlspecialchars($prefill_name); ?>">
            <input type="hidden" id="admission_no" name="admission_no" 
                  value="<?= htmlspecialchars($prefill_adm); ?>">
            <input type="hidden" id="student_name" name="student_name" 
                  value="<?= htmlspecialchars($prefill_name); ?>">
          <div id="suggestions"></div>
        </div>

        <!-- Fees Selection -->
        <div class="fees-list">
          <?php 
          $feeItems = [
            "School Fee", "Lunch Fee", "Admission",
            "Activity", "Exam", "Interview",
            "Prize Giving", "Graduation"
          ];
          foreach ($feeItems as $fee) {
            echo "<div class='fee-item'>
                    <input type='checkbox' name='fees[]' value='$fee' id='".str_replace(' ', '_', strtolower($fee))."'>
                    <label for='".str_replace(' ', '_', strtolower($fee))."'>$fee</label>
                    <input type='number' class='fee-amount' name='amounts[$fee]' placeholder='Amount' disabled>
                  </div>";
          }
          ?>
        </div>

        <!-- Total -->
        <div class="total-container">
          Total: <span id="totalAmount">0</span>
        </div>

        <!-- Payment Type -->
        <div class="form-group">
          <select id="payment_type" name="payment_type">
            <option value="Cash">Cash</option>
            <option value="Mpesa">Mpesa</option>
            <option value="Bank_Transfer">Bank Transfer</option>
          </select>
        </div>

        <!-- Submit -->
        <div class="button-container">
          <button type="submit" class="make-payments-btn">
            <i class="bx bx-money"></i> Make Payment
          </button>
        </div>
      </form>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
  /* ===== Real-time Clock ===== */
  function updateClock() {
    const clockElement = document.getElementById('realTimeClock');
    if (clockElement) {
      const now = new Date();
      clockElement.textContent = now.toLocaleTimeString();
    }
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ===== Dropdowns: only one open ===== */
  document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const parent = btn.parentElement;
      document.querySelectorAll(".dropdown").forEach(drop => {
        if (drop !== parent) drop.classList.remove("open");
      });
      parent.classList.toggle("open");
    });
  });

  /* ===== Keep dropdown open based on current page ===== */
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

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
  });

  /* ===== Auto logout after 5 minutes ===== */
  let logoutTimer;
  function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutTimer = setTimeout(() => {
      window.location.href = 'logout.php';
    }, 300000); // 5 minutes
  }
  ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, resetLogoutTimer);
  });
  resetLogoutTimer();

  /* ===== Enable/Disable amount inputs based on checkbox ===== */
  document.querySelectorAll('.fee-item input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      const amountInput = this.closest('.fee-item').querySelector('.fee-amount');
      amountInput.disabled = !this.checked;
      if (!this.checked) amountInput.value = '';
      updateTotal();
    });
  });

  /* ===== Update Total Amount ===== */
  function updateTotal() {
    let total = 0;
    document.querySelectorAll('.fee-amount:not(:disabled)').forEach(input => {
      total += parseFloat(input.value) || 0;
    });
    document.getElementById('totalAmount').textContent = total.toFixed(2);
  }

  document.querySelectorAll('.fee-amount').forEach(input => {
    input.addEventListener('input', updateTotal);
  });

  /* ===== jQuery Section ===== */
  $(document).ready(function () {
    // Live search
    $('#student-search').on('input', function () {
      const query = $(this).val().trim();
      $('#student_name').val(query); // keep hidden updated with typed name
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
        error: function () {
          $('#suggestions').html('<div class="suggestion-item">Error fetching data</div>');
        }
      });
    });

    // Select student
    $('#suggestions').on('click', '.suggestion-item', function () {
      const name = $(this).data('name');
      const admission = $(this).data('admission');
      const studentClass = $(this).data('class');

      $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
      $('#admission_no').val(admission);
      $('#student_name').val(name);
      $('#suggestions').empty();
    });

    // Close suggestions if clicking outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.form-group').length) {
        $('#suggestions').empty();
      }
    });

    // ===== Cancel button reload =====
    $(document).on('click', '#cancelBtn', function () {
      window.location.reload();
    });

    // ===== AJAX form submission =====
    $('#feeForm').on('submit', function (e) {
      e.preventDefault();
      $('#message').text('');

      const admissionNo = $('#admission_no').val();
      const studentName = $('#student_name').val();
      const paymentType = $('select[name="payment_type"]').val();

      $.ajax({
        url: 'others.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (res) {
          if (res.success) {
            $('#feeForm')[0].reset();
            $('#admission_no').val('');
            $('#student_name').val('');

            if (res.paid_details && res.paid_details.length > 0) {
              const date = new Date().toLocaleDateString();
              const total_paid_now = res.paid_details.reduce((sum, f) => sum + parseFloat(f.paid), 0);
              const total_balance = res.paid_details.reduce((sum, f) => {
                let bal = parseFloat(f.balance);
                return sum + (isNaN(bal) ? 0 : bal);
              }, 0);

              let receiptHtml = `
                <div class="receipt" style="font-family:Arial;padding:20px;width:450px;">
                  <div class="title" style="font-size:18px;font-weight:bold;">Kayron Junior School</div>
                  <div style="text-align:center;">Tel: 0711686866 / 0731156576</div>
                  <hr>
                  <strong>Official Receipt</strong><br>
                  Date: ${date}<br>
                  Receipt No: ${res.receipt_number}<br>
                  Admission No: ${admissionNo}<br>
                  Student Name: ${studentName}<br>
                  Payment Type: ${paymentType}
                  <hr>
                  <strong>Fee Payments</strong>
                  <table border="1" cellspacing="0" cellpadding="5" style="width:100%;margin-top:10px;">
                    <tr>
                      <th>Fee Type</th>
                      <th>Amount Paid (KES)</th>
                      <th>Balance (KES)</th>
                    </tr>`;

              res.paid_details.forEach(fee => {
                receiptHtml += `
                  <tr>
                    <td>${fee.fee_type}</td>
                    <td style="text-align:right;">${parseFloat(fee.paid).toFixed(2)}</td>
                    <td style="text-align:right;">${(fee.balance === '' || fee.balance == null) ? '' : parseFloat(fee.balance).toFixed(2)}</td>
                  </tr>`;
              });

              receiptHtml += `
                  </table>
                  <div style="margin-top:10px;font-weight:bold;">Amount Paid: KES ${total_paid_now.toFixed(2)}</div>
                  <div style="margin-top:5px;font-weight:bold;">Balance: KES ${total_balance.toFixed(2)}</div>
                  <div style="margin-top:10px;">Thank you for trusting in our school. Always working to output the best!</div>
                </div>
              `;

              const popup = window.open('', 'Receipt', 'width=500,height=600,scrollbars=yes');
              popup.document.write(`
                <html>
                  <head>
                    <title>Receipt</title>
                  </head>
                  <body>
                    ${receiptHtml}
                    <script>
                      window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                          window.close();
                          window.opener.location.reload(); // refresh main page
                        };
                      };
                    <\/script>
                  </body>
                </html>
              `);
              popup.document.close();
            }
          } else {
            $('#message').text(res.message || 'An error occurred.');
          }
        },
        error: function () {
          $('#message').text('An unexpected error occurred.');
        }
      });
    });
  });
});
</script>
</body>
</html>
