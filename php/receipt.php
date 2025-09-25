<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
require 'db.php';

$receipt_no = $_GET['receipt_no'] ?? '';
if (!$receipt_no) { echo "No receipt specified."; exit(); }

// === Fetch all transactions for this receipt ===
$school_fees = $conn->query("SELECT * FROM school_fee_transactions WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$lunch_fees = $conn->query("SELECT * FROM lunch_fee_transactions WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$others = $conn->query("SELECT * FROM others WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$uniforms = $conn->query("SELECT * FROM uniform_purchases WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);
$books = $conn->query("SELECT * FROM book_purchases WHERE receipt_number='$receipt_no'")->fetch_all(MYSQLI_ASSOC);

// === Get student info from first available record ===
$student = $school_fees[0] ?? $lunch_fees[0] ?? $others[0] ?? $uniforms[0] ?? $books[0] ?? null;
if (!$student) { echo "No transactions found for this receipt."; exit(); }

$name = $student['name'];
$admission_no = $student['admission_no'];
$class = $student['class'] ?? '';
$payment_type = $student['payment_type'] ?? 'Cash';

$total_paid = 0;
$total_balance = 0; // For accumulating balances from other fees

// === Fetch total school fee balance ===
$balance_result = $conn->query("
    SELECT SUM(IFNULL(balance, 0)) AS total_balance 
    FROM school_fees 
    WHERE admission_no = '$admission_no'
");
$data = $balance_result->fetch_assoc();
$school_fee_balance = $data['total_balance'] ?? 0;

// === LUNCH BALANCE LOGIC (detailed per-day calculation) ===
$per_day_fee = 70; // Lunch fee per day
$today = date('Y-m-d');

// Get current term info
$term_res = $conn->query("SELECT id, term_number, start_date AS current_term_start FROM terms ORDER BY id DESC LIMIT 1");
$current_term = $term_res->fetch_assoc();
$current_term_id = (int)$current_term['id'];
$current_term_number = (int)$current_term['term_number'];
$current_term_start = $current_term['current_term_start'];

// Map day names to offsets (Monday=0, etc.)
$day_map = ['monday'=>0,'tuesday'=>1,'wednesday'=>2,'thursday'=>3,'friday'=>4];

// ======== CURRENT TERM LUNCH BALANCE ========
$current_term_balance = 0;
$graduation_date = $student['graduation_date'] ?? null;

if ($graduation_date && strtotime($graduation_date) < strtotime($current_term_start)) {
    $current_term_balance = 0;
} else {
    $weeks_res = $conn->prepare("SELECT id, week_number FROM weeks WHERE term_id = ?");
    $weeks_res->bind_param("i", $current_term_id);
    $weeks_res->execute();
    $weeks_result = $weeks_res->get_result();

    while ($week = $weeks_result->fetch_assoc()) {
        $week_id = $week['id'];
        $week_number = $week['week_number'];

        $days_stmt = $conn->prepare("SELECT id, day_name, is_public_holiday FROM days WHERE week_id = ?");
        $days_stmt->bind_param("i", $week_id);
        $days_stmt->execute();
        $days_result = $days_stmt->get_result();

        while ($day = $days_result->fetch_assoc()) {
            $day_id = $day['id'];
            $day_name = $day['day_name'];
            $is_holiday = (int)$day['is_public_holiday'];

            if ($is_holiday) continue;

            $lower_day = strtolower($day_name);
            $week_offset = $week_number - 1;
            $day_offset = $day_map[$lower_day] ?? null;
            if ($day_offset === null) continue;

            $day_date = date('Y-m-d', strtotime("$current_term_start +{$week_offset} weeks +{$day_offset} days"));
            if ($day_date > $today) continue;

            // Attendance
            $att_stmt = $conn->prepare("
                SELECT status FROM attendance
                WHERE admission_no = ? AND term_number = ? AND week_number = ? AND day_id = ?
            ");
            $att_stmt->bind_param("siii", $admission_no, $current_term_number, $week_number, $day_id);
            $att_stmt->execute();
            $att_status = $att_stmt->get_result()->fetch_assoc()['status'] ?? 'Present';
            $att_stmt->close();

            if ($att_status === 'Absent') continue;

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

            $day_paid = 0;
            if ($lunch_row) {
                $day_paid = $lunch_row[$lower_day] ?? 0;
            }

            $balance_for_day = $per_day_fee - $day_paid;
            if ($balance_for_day > 0) {
                $current_term_balance += $balance_for_day;
            }
        }
        $days_stmt->close();
    }
    $weeks_res->close();

    // Subtract carry forward from previous terms
    $carry_stmt = $conn->prepare("
        SELECT IFNULL(SUM(carry_forward),0) AS carry
        FROM lunch_fees
        WHERE admission_no = ? AND term_id < ?
    ");
    $carry_stmt->bind_param("si", $admission_no, $current_term_id);
    $carry_stmt->execute();
    $carry_forward = 0;
    $carry_stmt->bind_result($carry_forward);
    $carry_stmt->fetch();
    $carry_stmt->close();

    $current_term_balance = max(0, $current_term_balance - $carry_forward);
}

// ======== PREVIOUS TERMS LUNCH BALANCE ========
$prev_balance = 0;
$terms_stmt = $conn->prepare("SELECT id FROM terms WHERE term_number < ?");
$terms_stmt->bind_param("i", $current_term_number);
$terms_stmt->execute();
$terms_res = $terms_stmt->get_result();

while ($term_row = $terms_res->fetch_assoc()) {
    $prev_term_id = (int)$term_row['id'];

    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM lunch_fees WHERE admission_no = ? AND term_id = ?");
    $check_stmt->bind_param("si", $admission_no, $prev_term_id);
    $check_stmt->execute();
    $check_stmt->bind_result($has_lunch);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($has_lunch > 0) {
        $pay_stmt = $conn->prepare("
            SELECT week_number, monday, tuesday, wednesday, thursday, friday
            FROM lunch_fees
            WHERE admission_no = ? AND term_id = ?
            ORDER BY week_number ASC
        ");
        $pay_stmt->bind_param("si", $admission_no, $prev_term_id);
        $pay_stmt->execute();
        $pay_result = $pay_stmt->get_result();

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
}
$terms_stmt->close();

// ======== TOTAL LUNCH BALANCE ========
$lunch_balance = $prev_balance + $current_term_balance;

// ========== HELPER ==========
function formatAmount($amt){ return number_format($amt, 2); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Receipt <?= htmlspecialchars($receipt_no) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet" />
<link rel="website icon" type="png" href="../images/school-logo.jpg">
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; text-align: center; background: #fff; margin: 20px auto; }
  .receipt-box { border: 1px dotted black; padding: 15px; width: 350px; margin: 0 auto; text-align: left; }
  .receipt-header { display: flex; justify-content: space-between; align-items: center; }
  .header-left { text-align: left; }
  .header-right .logo { max-width: 60px; height: auto; }
  .logo { display: block; margin: 0 auto 10px auto; max-width: 100px; }
  h2, h3 { margin: 5px 0; text-align: center; }
  table { width: 100%; margin-top: 10px; border-collapse: collapse; }
  th, td { padding: 5px; border-bottom: 1px dotted black; text-align: left; font-size: 13px; }
  th { font-weight: bold; }
  .phone { text-align: center; font-size: 14px; }
  .total { font-weight: bold; border-top: 1px dotted black; padding-top: 8px; margin-top: 5px; text-align: center; font-size: 14px; }
  .approve { margin-top: 20px; font-weight: bold; text-align: center; font-size: 13px; }
  .thanks { margin-top: 15px; font-weight: bold; text-align: center; font-size: 14px; }
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
    <tr><th>Date:</th><td><?= date('d-m-Y') ?></td></tr>
    <tr><th>Receipt No:</th><td><?= htmlspecialchars($receipt_no) ?></td></tr>
    <tr><th>Admission No:</th><td><?= htmlspecialchars($admission_no) ?></td></tr>
    <tr><th>Student Name:</th><td><?= htmlspecialchars($name) ?></td></tr>
  </table>

  <table>
    <thead>
      <tr>
        <th>Fee Type</th>
        <th>Amount (KES)</th>
        <th>Balance (KES)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($school_fees as $sf): 
          $total_paid += $sf['amount_paid'];
          if ($school_fee_balance < 0) {
              $balance_display = '0.00';
          } elseif ($school_fee_balance == 0) {
              $balance_display = '0.00';
          } else {
              $balance_display = formatAmount($school_fee_balance);
          }
      ?>
      <tr>
        <td>School Fee</td>
        <td><?= formatAmount($sf['amount_paid']) ?></td>
        <td><?= $balance_display ?></td>
      </tr>
      <?php endforeach; ?>

      <?php foreach ($lunch_fees as $lf): 
          $total_paid += $lf['amount_paid'];
      ?>
      <tr>
        <td>Lunch Fee</td>
        <td><?= formatAmount($lf['amount_paid']) ?></td>
        <td><?= $lunch_balance <= 0 ? '0.00' : 'KES ' . formatAmount($lunch_balance); ?></td>
      </tr>
      <?php endforeach; ?>

      <?php foreach ($others as $o): 
          $total_paid += $o['amount_paid'];
          $balance = $o['balance'] ?? 0;
          $total_balance += $balance;
      ?>
      <tr>
        <td><?= htmlspecialchars($o['fee_type']) ?></td>
        <td><?= formatAmount($o['amount_paid']) ?></td>
        <td><?= formatAmount($balance) ?></td>
      </tr>
      <?php endforeach; ?>

      <?php foreach ($uniforms as $u): 
          $total_paid += $u['amount_paid'];
          $balance = $u['balance'] ?? 0;
          $total_balance += $balance;
      ?>
      <tr>
        <td>Uniform (<?= htmlspecialchars($u['uniform_type'] . ' - ' . $u['size']) ?>)</td>
        <td><?= formatAmount($u['amount_paid']) ?></td>
        <td><?= formatAmount($balance) ?></td>
      </tr>
      <?php endforeach; ?>

      <?php foreach ($books as $b): 
          $total_paid += $b['amount_paid'];
          $balance = $b['balance'] ?? 0;
          $total_balance += $balance;
      ?>
      <tr>
        <td>Book (<?= htmlspecialchars($b['book_name']) ?>)</td>
        <td><?= formatAmount($b['amount_paid']) ?></td>
        <td><?= formatAmount($balance) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="total">Total Paid: KES <?= formatAmount($total_paid) ?></div>

 <div class="total">Total Balance: 
<?php 
    $final_balance = 0;

    if (!empty($school_fees)) {
        $final_balance += max(0, $school_fee_balance);
    }

    if (!empty($lunch_fees)) {
        $final_balance += max(0, $lunch_balance);
    }

    if (!empty($others)) {
        $final_balance += $total_balance; // total_balance already accumulates balances from others, uniforms, books
    }

    echo $final_balance == 0 ? 'Cleared' : 'KES ' . formatAmount($final_balance);
?>
</div>

  <div class="approve">Approved By: <?= htmlspecialchars($_SESSION['username']) ?></div>
  <div class="thanks">Thank you for trusting in our school.<br>Always working to output the best!</div>
</div>

<script>window.print();</script>
</body>
</html>
