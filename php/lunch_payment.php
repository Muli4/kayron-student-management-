<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../php/db.php';

$lunch_full_day = 70;

// === Ajax Tracker Rendering ===
if (isset($_GET['ajax'])) {
    $selected_term = intval($_GET['term'] ?? 0);
    $selected_student = $_GET['admission_no'] ?? '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1; 
    $rows_per_page = 5;

    if (!$selected_student || !$selected_term) {
        echo "<div class='summary unpaid'>Select a student and term to view lunch tracker.</div>";
        exit;
    }

    // Fetch term start and end dates
    $term_stmt = $conn->prepare("SELECT start_date, end_date FROM terms WHERE id = ?");
    $term_stmt->bind_param("i", $selected_term);
    $term_stmt->execute();
    $term_stmt->bind_result($start_date, $end_date);
    $term_stmt->fetch();
    $term_stmt->close();

    $total_weeks = ceil((strtotime($end_date) - strtotime($start_date) + 1) / (60*60*24*7));
    $day_order = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

    // Load attendance data indexed by week and day (lowercase)
    $attendance = [];
    $att_stmt = $conn->prepare("
        SELECT d.day_name, w.week_number, a.status, d.is_public_holiday
        FROM days d
        JOIN weeks w ON d.week_id = w.id
        LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
        WHERE w.term_id = ?
    ");
    $att_stmt->bind_param("si", $selected_student, $selected_term);
    $att_stmt->execute();
    $att_res = $att_stmt->get_result();
    while($row = $att_res->fetch_assoc()){
        $attendance[$row['week_number']][strtolower($row['day_name'])] = [
            'status' => $row['status'] ?? 'Not Marked',
            'holiday' => (int)$row['is_public_holiday']
        ];
    }
    $att_stmt->close();

    // Load lunch payments data indexed by week_number
    $lunch_data = [];
    $payments_stmt = $conn->prepare("
        SELECT week_number, monday, tuesday, wednesday, thursday, friday
        FROM lunch_fees
        WHERE admission_no = ? AND term_id = ?
        ORDER BY week_number ASC
    ");
    $payments_stmt->bind_param("si", $selected_student, $selected_term);
    $payments_stmt->execute();
    $payments_res = $payments_stmt->get_result();
    while($row = $payments_res->fetch_assoc()) {
        $lunch_data[intval($row['week_number'])] = $row;
    }
    $payments_stmt->close();

    // Calculate carry forward credit from previous terms
    $carry_stmt = $conn->prepare("
        SELECT IFNULL(SUM(carry_forward),0) AS carry
        FROM lunch_fees
        WHERE admission_no = ? AND term_id < ?
    ");
    $carry_stmt->bind_param("si", $selected_student, $selected_term);
    $carry_stmt->execute();
    $carry_stmt->bind_result($carry_forward);
    $carry_stmt->fetch();
    $carry_stmt->close();

    // Check unpaid balance from previous term (if any)
    $prev_term_id = 0;
    $prev_term_res = $conn->prepare("SELECT MAX(id) FROM terms WHERE id < ?");
    $prev_term_res->bind_param("i", $selected_term);
    $prev_term_res->execute();
    $prev_term_res->bind_result($prev_term_id);
    $prev_term_res->fetch();
    $prev_term_res->close();

    $unpaid_balance = 0;
    $has_prev_lunch = 0;

    if ($prev_term_id) {
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) FROM lunch_fees 
            WHERE admission_no = ? AND term_id = ?
        ");
        $check_stmt->bind_param("si", $selected_student, $prev_term_id);
        $check_stmt->execute();
        $check_stmt->bind_result($has_prev_lunch);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($has_prev_lunch > 0) {
            $paid_stmt = $conn->prepare("
                SELECT IFNULL(SUM(monday+tuesday+wednesday+thursday+friday),0)
                FROM lunch_fees
                WHERE admission_no = ? AND term_id = ?
            ");
            $paid_stmt->bind_param("si", $selected_student, $prev_term_id);
            $paid_stmt->execute();
            $paid_stmt->bind_result($total_paid);
            $paid_stmt->fetch();
            $paid_stmt->close();

            $days_stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM days d
                JOIN weeks w ON d.week_id = w.id
                WHERE w.term_id = ? AND d.is_public_holiday = 0
            ");
            $days_stmt->bind_param("i", $prev_term_id);
            $days_stmt->execute();
            $days_stmt->bind_result($total_days);
            $days_stmt->fetch();
            $days_stmt->close();

            $abs_stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM days d
                JOIN weeks w ON d.week_id = w.id
                LEFT JOIN attendance a ON a.day_id = d.id AND a.admission_no = ?
                WHERE w.term_id = ? AND a.status = 'Absent'
            ");
            $abs_stmt->bind_param("si", $selected_student, $prev_term_id);
            $abs_stmt->execute();
            $abs_stmt->bind_result($absent_days);
            $abs_stmt->fetch();
            $abs_stmt->close();

            $expected = ($total_days - $absent_days) * $lunch_full_day;
            $unpaid_balance = max(0, $expected - $total_paid);
        }
    }

    $last_term_id = $conn->query("SELECT MAX(id) AS last_term FROM terms")->fetch_assoc()['last_term'] ?? 0;

    $last_week_num = 0;
    $last_day_name = '';

    $today = date('Y-m-d');
    if ($selected_term == $last_term_id && $today >= $start_date && $today <= $end_date) {
        $days_since_start = (strtotime($today) - strtotime($start_date)) / (60 * 60 * 24);
        $last_week_num = floor($days_since_start / 7) + 1;
        $last_day_name = strtolower(date('l', strtotime($today)));
    }

    $output = '';

    if ($prev_term_id && $unpaid_balance > 0 && $has_prev_lunch > 0) {
        $output .= "<div class='summary unpaid'>
                        Previous Term Balance: KES {$unpaid_balance}
                    </div>";
    }

    $credit_carry = $carry_forward;
    $term_total_paid = 0;
    $term_total_balance = 0;

    $output .= "<table class='lunch-table'>
            <tr>
                <th></th>
                <th>Week</th>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
                <th>Thursday</th>
                <th>Friday</th>
                <th>Total Paid</th>
                <th>Balance</th>
            </tr>";

    $start_week = ($page - 1) * $rows_per_page + 1;
    $end_week = min($start_week + $rows_per_page - 1, $total_weeks);

    foreach (range(1, $total_weeks) as $week_no) {
        $week_data = $lunch_data[$week_no] ?? ['monday'=>0,'tuesday'=>0,'wednesday'=>0,'thursday'=>0,'friday'=>0];
        $week_total_paid = 0;
        $week_balance = 0;

        $week_class = ($selected_term == $last_term_id && $week_no == $last_week_num) ? "highlight-week" : "";

        $row_html = "<tr class='{$week_class}'>";
        $row_html .= "<td>{$week_no}</td><td>Week {$week_no}</td>";

        foreach ($day_order as $day) {
            $lower_day = strtolower($day);
            $amount = floatval($week_data[$lower_day] ?? 0);
            $credit_carry += $amount;

            $day_att = $attendance[$week_no][$lower_day] ?? ['status'=>'Not Marked','holiday'=>0];
            $status = $day_att['status'];
            $isHoliday = $day_att['holiday'] == 1;
            $isAbsent = ($status === 'Absent');

            $highlight_day = ($selected_term == $last_term_id && $week_no == $last_week_num && $lower_day == $last_day_name) ? "highlight-day" : "";

            if ($isHoliday) {
                $row_html .= "<td class='unpaid {$highlight_day}'>Holiday</td>";
            } elseif ($isAbsent) {
                $row_html .= "<td class='unpaid {$highlight_day}'>Absent</td>";
            } else {
                if ($credit_carry >= $lunch_full_day) {
                    $row_html .= "<td class='paid {$highlight_day}'>&#10004;</td>";
                    $week_total_paid += $lunch_full_day;
                    $term_total_paid += $lunch_full_day;
                    $credit_carry -= $lunch_full_day;
                } elseif ($credit_carry > 0) {
                    $row_html .= "<td class='partial {$highlight_day}'>" . number_format($credit_carry, 2) . "</td>";
                    $week_total_paid += $credit_carry;
                    $term_total_paid += $credit_carry;
                    $week_balance += $lunch_full_day - $credit_carry;
                    $term_total_balance += $lunch_full_day - $credit_carry;
                    $credit_carry = 0;
                } else {
                    $row_html .= "<td class='unpaid {$highlight_day}'>x</td>";
                    $week_balance += $lunch_full_day;
                    $term_total_balance += $lunch_full_day;
                }
            }
        }

        $row_html .= "<td>KES " . number_format($week_total_paid, 2) . "</td>";
        $row_html .= "<td>KES " . number_format($week_balance, 2) . "</td>";
        $row_html .= "</tr>";

        if ($week_no >= $start_week && $week_no <= $end_week) {
            $output .= $row_html;
        }
    }

    $output .= "</table>";

    // Calculate current term unpaid lunch balance up to today (based on attendance & payments)
    $current_term_balance = 0;
    $per_day_fee = $lunch_full_day;

    $day_map = ['monday'=>0, 'tuesday'=>1, 'wednesday'=>2, 'thursday'=>3, 'friday'=>4];

    for ($week_no = 1; $week_no <= $total_weeks; $week_no++) {
        foreach ($day_map as $day_name => $day_offset) {
            // Calculate date of the day in current week
            $week_start_date = date('Y-m-d', strtotime($start_date . " +".($week_no - 1)." weeks"));
            $day_date = date('Y-m-d', strtotime($week_start_date . " +{$day_offset} days"));

            if ($day_date > $today) {
                // Skip future days
                continue;
            }

            // Skip if holiday or attendance is absent
            $day_att = $attendance[$week_no][$day_name] ?? ['status' => 'Not Marked', 'holiday' => 0];
            if ($day_att['holiday'] == 1 || $day_att['status'] === 'Absent') {
                continue;
            }

            // Check if lunch paid for this day in this week
            $week_payment = $lunch_data[$week_no] ?? null;
            $paid_amount = ($week_payment[$day_name] ?? 0);

            if ($paid_amount <= 0) {
                $current_term_balance += $per_day_fee;
            }
        }
    }

    // Show current term balance as of today for last term
    if ($selected_term == $last_term_id && $today >= $start_date && $today <= $end_date) {
        $output .= "<div class='summary unpaid'>
            <strong>Current Term Balance as of Today:</strong> KES " . number_format($current_term_balance, 2) . "
        </div>";
    } else {
        $output .= "<div class='summary paid'>
            <strong>Total Paid:</strong> KES " . number_format($term_total_paid, 2) . "
            <br><strong>Balance:</strong> KES " . number_format($term_total_balance, 2) . "
        </div>";
    }

    echo $output;

    exit; // <<< Important to stop further output after AJAX response
}

// If not AJAX, here you would output your normal full page HTML & form for selecting students, terms, etc.
// (not shown here)
?>



<!DOCTYPE html>
<html>
<head>
    <title>Lunch Payment Tracker</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
<style>
    /* Lunch Payment Tracker Styles */

.tracker-title {
  font-size: 1.8rem;
  margin-bottom: 1rem;
  text-align: center;
  font-weight: 700;
  color: #004080;
}

.tracker-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 1rem;
  background: #f9faff;
  border-radius: 8px;
  box-shadow: 0 0 8px rgba(0,0,0,0.1);
}

.tracker {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.tracker label {
  font-weight: 600;
  color: #333;
  min-width: 90px;
}

.tracker select,
.tracker input[type="text"] {
  padding: 6px 10px;
  font-size: 1rem;
  border: 1px solid #bbb;
  border-radius: 4px;
  width: 220px;
  transition: border-color 0.3s ease;
}

.tracker select:focus,
.tracker input[type="text"]:focus {
  border-color: #007acc;
  outline: none;
}

.search-wrapper {
  position: relative;
  width: 220px;
}

#suggestions {
  position: absolute;
  top: 38px;
  left: 0;
  width: 100%;
  max-height: 180px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 0 0 4px 4px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  z-index: 1000;
  display: none;
}

.suggestion-item {
  padding: 8px 12px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  font-size: 0.9rem;
  color: #222;
}

.suggestion-item:hover {
  background-color: #007acc;
  color: #fff;
}

#pay-btn {
  background-color: #007acc;
  color: #fff;
  border: none;
  padding: 8px 18px;
  font-size: 1rem;
  border-radius: 5px;
  cursor: pointer;
  transition: background-color 0.3s ease;
  height: 38px;
}

#pay-btn:hover {
  background-color: #005fa3;
}

/* Tracker results table */

.lunch-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
  font-size: 0.95rem;
}

.lunch-table th,
.lunch-table td {
  border: 1px solid #ccc;
  padding: 8px 10px;
  text-align: center;
  vertical-align: middle;
}

.lunch-table th {
  background-color: #007acc;
  color: white;
  font-weight: 600;
}

.lunch-table td.paid {
  color: #155724;
  font-weight: bold;
}

.lunch-table td.partial {
  color: #856404;
  font-weight: bold;
}

.lunch-table td.unpaid {
  color: #721c24;
  font-weight: bold;
}

.lunch-table td.highlight-day,
.lunch-table tr.highlight-week {
  border: 2px solid #004080;
}
.lunch-table td.highlight-day{
    background-color: #007acc;
}
.totals-row td {
  font-weight: 700;
  background-color: #e9ecef;
}

.carry-row td {
  font-style: italic;
  color: #555;
  background-color: #f1f3f5;
}

/* Summary messages */
.summary {
  font-size: 1rem;
  padding: 10px 15px;
  border-radius: 5px;
  margin-bottom: 1rem;
  text-align: center;
  font-weight: 600;
}

.summary.unpaid {
  background-color: #f8d7da;
  color: #721c24;
}

.summary.paid {
  background-color: #d4edda;
  color: #155724;
}

/* Pagination */
.pagination {
  margin-top: 1rem;
  text-align: center;
  user-select: none;
}

.pagination a {
  display: inline-block;
  margin: 0 8px;
  padding: 6px 14px;
  border-radius: 5px;
  background-color: #007acc;
  color: white;
  text-decoration: none;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s ease;
}

.pagination a.disabled,
.pagination a.disabled:hover {
  background-color: #ccc;
  cursor: default;
  color: #666;
}

.pagination a:hover:not(.disabled) {
  background-color: #005fa3;
}

/* Modal styles */
.modal {
  display: none; /* Hidden by default */
  position: fixed; 
  z-index: 1500; 
  left: 0;
  top: 0;
  width: 100%; 
  height: 100%; 
  overflow: auto; 
  background-color: rgba(0,0,0,0.5); /* Black with opacity */
}

.modal-content {
  background-color: #fff;
  margin: 10% auto;
  padding: 20px 30px;
  border-radius: 8px;
  width: 400px;
  max-width: 90%;
  position: relative;
  box-shadow: 0 0 10px rgba(0,0,0,0.25);
}

#close-modal {
  position: absolute;
  top: 10px;
  right: 18px;
  font-size: 24px;
  font-weight: 700;
  color: #444;
  cursor: pointer;
  transition: color 0.2s ease;
}

#close-modal:hover {
  color: #007acc;
}

.modal-header {
  font-size: 1.4rem;
  margin-bottom: 1rem;
  font-weight: 700;
  color: #004080;
  text-align: center;
}

.form-group {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 6px;
  color: #333;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select {
  padding: 8px 12px;
  border: 1px solid #bbb;
  border-radius: 4px;
  font-size: 1rem;
  transition: border-color 0.3s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus,
.form-group select:focus {
  border-color: #007acc;
  outline: none;
}

#submit-payment {
  background-color: #007acc;
  color: white;
  border: none;
  padding: 10px 16px;
  font-size: 1rem;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 700;
  width: 100%;
  transition: background-color 0.3s ease;
}

#submit-payment:hover {
  background-color: #005fa3;
}
/* Responsive Media Queries */

/* Medium devices (tablets, 768px and up) */
@media (max-width: 768px) {
  .tracker {
    flex-direction: column;
    align-items: stretch;
  }

  .tracker label,
  .tracker select,
  .tracker input[type="text"],
  #pay-btn {
    width: 100%;
  }

  #pay-btn {
    margin-top: 10px;
  }

  .search-wrapper {
    width: 100%;
  }

  #suggestions {
    max-height: 150px;
  }

  .lunch-table {
    font-size: 0.85rem;
  }

  .modal-content {
    width: 90%;
    margin: 20% auto;
  }
}

/* Small devices (phones, 480px and below) */
@media (max-width: 480px) {
  .tracker-container {
    padding: 0.5rem;
  }

  .tracker-title {
    font-size: 1.4rem;
  }

  .tracker label {
    font-size: 0.9rem;
    min-width: auto;
    margin-bottom: 4px;
  }

  .tracker select,
  .tracker input[type="text"] {
    font-size: 0.9rem;
  }

  #pay-btn {
    font-size: 0.95rem;
    padding: 8px 12px;
  }

  .lunch-table th,
  .lunch-table td {
    padding: 6px 5px;
  }

  .lunch-table {
    font-size: 0.8rem;
  }

  /* Make the table horizontally scrollable on small screens */
  .lunch-table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }

  /* Modal tweaks */
  .modal-content {
    width: 95%;
    margin: 30% auto;
    padding: 15px 20px;
  }

  #close-modal {
    font-size: 20px;
    top: 6px;
    right: 12px;
  }
}
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="dashboard-container">
<?php include '../includes/sidebar.php'; ?>
<main class="content">
        <h2 class="tracker-title">Lunch Payment Tracker</h2>

    <div class="tracker-container">
        <div class="tracker">
            <label>Select Term:</label>
            <select id="term">
                <option value="">--Choose Term--</option>
                <?php
                include '../includes/db.php';
                $terms = $conn->query("SELECT id, term_number, year FROM terms ORDER BY year DESC, term_number DESC");
                while($t = $terms->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>">Term <?= $t['term_number'] ?> (<?= $t['year'] ?>)</option>
                <?php endwhile; ?>
            </select>

            <label for="student-search">Search Student:</label>
            <div class="search-wrapper">
                <input type="text" id="student-search" placeholder="Enter student name..." autocomplete="off">
                <input type="hidden" id="admission_no" name="admission_no">
                <div id="suggestions"></div>
            </div>

            <button id="pay-btn">Pay</button>
        </div>

        <div id="message-box"></div>
        <div id="tracker-results"></div>
    </div>

</main>
</div>

<!-- Payment Modal -->
<div id="payment-modal" class="modal">
  <div class="modal-content">
    <span id="close-modal">&times;</span>
    <div class="modal-header">Lunch Fee Payment</div>
    <form id="payment-form">
      <div class="form-group">
        <label>Admission Number</label>
        <input type="text" id="modal-admission-no" name="admission_no" readonly>
      </div>
      <div class="form-group">
        <label>Amount (KES)</label>
        <input type="number" name="amount" id="amount" required>
      </div>
      <div class="form-group">
        <label>Payment Method</label>
        <select name="payment_method" required>
          <option value="">-- Select Payment Method --</option>
          <option value="Cash">Cash</option>
          <option value="M-Pesa">M-Pesa</option>
          <option value="Bank">Bank</option>
        </select>
      </div>
      <button type="submit" id="submit-payment">Make Payment</button>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
$(document).ready(function () {

  // === Live student search ===
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
    const $suggestions = $('#suggestions');

    if (!query) {
      $suggestions.hide().empty();
      return;
    }

    $.post('search-students.php', { query }, function (response) {
      $suggestions.empty().show();
      if (Array.isArray(response) && response.length) {
        response.forEach(student => {
          $suggestions.append(`
            <div class="suggestion-item" data-admission="${student.admission_no}" data-name="${student.name}" data-class="${student.class}">
              ${student.name} – ${student.admission_no} – ${student.class}
            </div>`);
        });
      } else {
        $suggestions.append('<div class="suggestion-item">No records found</div>');
      }
    }, 'json').fail((xhr, status, err) => {
      console.error('Search AJAX error:', status, err, xhr.responseText);
      $('#message-box').html("<div class='unpaid'>Search failed; check console for details.</div>").show();
    });
  });

  // === Select student ===
  $('#suggestions').on('click', '.suggestion-item', function () {
    const admission = $(this).data('admission');
    const name = $(this).data('name');
    const studentClass = $(this).data('class');

    $('#student-search').val(`${name} – ${admission} – ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').hide().empty();

    const term = $('#term').val();
    if (!term) {
      $('#message-box').html("<div class='unpaid'>Please select a term first</div>").show();
      return;
    }
    loadTracker(term, admission, 1);
  });

  // === Term change loads tracker ===
  $('#term').on('change', function () {
    const term = $(this).val();
    const admission = $('#admission_no').val();
    if (term && admission) loadTracker(term, admission, 1);
  });

  // === Open payment modal ===
  $('#pay-btn').on('click', function () {
    const term = $('#term').val();
    const admission = $('#admission_no').val();
    if (term && admission) {
      $('#modal-admission-no').val(admission);
      $('#payment-modal').fadeIn();
    } else {
      $('#message-box').html("<div class='unpaid'>Select a term and student first</div>").show();
    }
  });

  // === Close modal ===
  $('#close-modal').on('click', () => $('#payment-modal').fadeOut());

  // === Submit payment form via AJAX ===
  $('#payment-form').on('submit', function (e) {
    e.preventDefault();
    const data = $(this).serialize();

    $.ajax({
      url: './pay-lunch-modal.php',
      method: 'POST',
      data: data,
      dataType: 'json',
      success: function (response) {
        $('#message-box').html(`<div class="${response.success ? 'paid' : 'unpaid'}">${response.message}</div>`).show();
        if (response.success) {
          $('#payment-form')[0].reset();
          $('#payment-modal').fadeOut();
          const term = $('#term').val();
          const admission = $('#admission_no').val();
          if (term && admission) loadTracker(term, admission, 1);
        }
      },
      error: function (xhr, status, error) {
        console.error('Payment AJAX error:', status, error, xhr.responseText);
        let msg = 'Payment request failed. Please try again.';
        if (status === 'parsererror') {
          msg = 'Invalid server response (not valid JSON).';
        }
        $('#message-box').html(`<div class="unpaid">${msg}</div>`).show();
      }
    });
  });

  // === Load Tracker function with pagination ===
  function loadTracker(term, admission, page = 1) {
    $.get(window.location.pathname, { ajax: 1, term, admission_no: admission, page }, function (response) {
      $('#tracker-results').html(response);
      $('#tracker-results').off('click').on('click', '.pagination a', function (e) {
        e.preventDefault();
        const np = $(this).data('page');
        if (!$(this).hasClass('disabled') && np) loadTracker(term, admission, np);
      });
    });
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
        }, 300000); // 5 minutes
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
