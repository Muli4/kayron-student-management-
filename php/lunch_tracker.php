<?php
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

    // === Term Info ===
    $term_stmt = $conn->prepare("SELECT start_date, end_date FROM terms WHERE id = ?");
    $term_stmt->bind_param("i", $selected_term);
    $term_stmt->execute();
    $term_stmt->bind_result($start_date, $end_date);
    $term_stmt->fetch();
    $term_stmt->close();

    $total_weeks = ceil((strtotime($end_date) - strtotime($start_date) + 1) / (60*60*24*7));
    $day_order = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

    // === Attendance ===
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

    // === Current Term Payments ===
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

    // === Carry Forward (Credit Only) ===
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

    // === Previous Term Underpayment Detection ===
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
        // Check if student had any lunch fees recorded in the previous term
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
            // calculate unpaid balance logic
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

    // === Determine Last Recorded Term, Week, and Day ===
    $last_term_id = $conn->query("SELECT MAX(id) AS last_term FROM terms")->fetch_assoc()['last_term'] ?? 0;
    $last_week_num = 0;
    $last_day_name = '';
    if ($selected_term == $last_term_id) {
        $row_week = $conn->query("SELECT MAX(week_number) AS last_week FROM weeks WHERE term_id = $last_term_id")->fetch_assoc();
        $last_week_num = intval($row_week['last_week'] ?? 0);

        $row_day = $conn->query("
            SELECT day_name FROM days 
            WHERE week_id = (SELECT id FROM weeks WHERE term_id = $last_term_id AND week_number = $last_week_num) 
            ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();
        $last_day_name = strtolower($row_day['day_name'] ?? '');
    }

    // === Output Buffer ===
    $output = '';

    // ✅ Show previous balance ONLY if previous term had lunch records
    if ($prev_term_id && $unpaid_balance > 0 && $has_prev_lunch > 0) {
        $output .= "<div class='summary unpaid'>
                        Previous Term Balance: KES {$unpaid_balance}
                    </div>";
    }

    // === Render Table ===
    $credit_carry = $carry_forward;
    $term_total_paid = 0;
    $term_total_balance = 0;

    $output .= "<table class='lunch-table'>
            <tr>
                <th>#</th>
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
                    $row_html .= "<td class='partial {$highlight_day}'>{$credit_carry}</td>";
                    $week_total_paid += $credit_carry;
                    $term_total_paid += $credit_carry;
                    $week_balance += $lunch_full_day - $credit_carry;
                    $term_total_balance += $lunch_full_day - $credit_carry;
                    $credit_carry = 0;
                } else {
                    $row_html .= "<td class='unpaid {$highlight_day}'>X</td>";
                    $week_balance += $lunch_full_day;
                    $term_total_balance += $lunch_full_day;
                }
            }
        }

        $row_html .= "<td>{$week_total_paid}</td>";
        $row_html .= "<td class='".($week_balance>0?'unpaid':'paid')."'>{$week_balance}</td>";
        $row_html .= "</tr>";

        if ($week_no >= $start_week && $week_no <= $end_week) {
            $output .= $row_html;
        }
    }

    // Totals
    $output .= "<tr class='totals-row'>
            <td colspan='7'>Term Totals (Including Carry-Over)</td>
            <td>{$term_total_paid}</td>
            <td>{$term_total_balance}</td>
          </tr>";

    $carry_text = ($carry_forward > 0) 
        ? "Previous Term Credit: {$carry_forward}" 
        : "No Carry Forward from Previous Terms";

    $output .= "<tr class='carry-row'>
            <td colspan='9'>{$carry_text}</td>
          </tr>";

    $output .= "</table>";

    // Pagination
    $total_pages = ceil($total_weeks / $rows_per_page);
    $output .= "<div class='pagination'>";
    $prev_page = $page - 1;
    $next_page = $page + 1;
    $output .= "<a class='".($page <= 1 ? 'disabled' : '')."' data-page='{$prev_page}'>&laquo; Previous</a>";
    $output .= "<a class='".($page >= $total_pages ? 'disabled' : '')."' data-page='{$next_page}'>Next &raquo;</a>";
    $output .= "</div>";

    echo $output;
    exit;
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Lunch Payment Tracker</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
<style>
/* === Tracker Container & Layout === */
.tracker-container { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    width: 100%;
}

/* === Tracker Section === */
.tracker {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 20px;
    width: 70%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.tracker label { font-weight: bold; }
.tracker select, 
.tracker input {
    padding: 7px 12px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    font-size: 14px;
    width: 200px;
}
.tracker button {
    padding: 8px 16px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.2s;
}
.tracker button:hover { background: #0069d9; }

/* === Search Wrapper & Suggestions === */
.search-wrapper {
    position: relative;
    display: inline-block;
    width: 200px;
}
#suggestions {  
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    border-radius: 0 0 5px 5px;
    max-height: 200px;
    overflow-y: auto;
    display: none;
    z-index: 99999;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.suggestion-item { 
    padding: 8px 12px; 
    cursor: pointer; 
    font-size: 14px;
    color: #333;
}
.suggestion-item:hover { background-color: #f1f1f1; }

/* === Message Box === */
#message-box {
    width: 70%;
    text-align: center;
    font-size: 1.1em;
    font-weight: bold;
    padding: 12px;
    border-radius: 6px;
    display: none;
    margin-top: 15px;
}
#message-box .paid {
    color: #28a745;
    background: #eafaf1;
    border: 1px solid #28a745;
}
#message-box .unpaid {
    color: #dc3545;
    background: #fdecea;
    border: 1px solid #dc3545;
}

/* === Tracker Results Table === */
#tracker-results table, 
.lunch-table {
    border-collapse: collapse; 
    width: 100%;
    margin-top: 20px;
    font-size: 14px;
}
#tracker-results th, #tracker-results td,
.lunch-table th, .lunch-table td {
    border: 1px solid #dee2e6; 
    padding: 10px; 
    text-align: center;
}
#tracker-results th,
.lunch-table th {
    background: #f1f3f5;
    font-weight: bold;
}

/* === Payment Status Colors === */
.paid { 
    color: #155724; 
    font-weight: bold; 
}
.partial { 
    color: #856404; 
    font-weight: bold; 
}
.unpaid { 
    color: #721c24; 
    font-weight: bold; 
}

/* === Highlight for Current/Last Recorded Week & Day === */
.highlight-week {
    background: #d0e6ff !important; /* More saturated blue tint */
    font-weight: 600;
    border-left: 4px solid #007bff;
}

.highlight-day {
    background: #007bff !important;
    color: white !important;
    font-weight: bold;
    box-shadow: inset 0 0 0 2px #0056b3;
}

/* === Summary & Totals === */
.summary { 
    text-align: center; 
    font-weight: bold; 
    margin-top: 20px; 
    font-size: 1.1em;
}
.totals-row { 
    font-weight: bold; 
    background: #f1f3f5; 
}
.carry-row { 
    font-weight: bold; 
    background: #f9f9f9; 
}

/* === Pagination === */
.pagination { 
    text-align: center; 
    margin-top: 15px; 
}
.pagination a { 
    margin: 0 10px; 
    padding: 6px 12px; 
    border: 1px solid #ccc; 
    text-decoration: none; 
    cursor: pointer;
    color: #007bff;
    font-weight: bold;
    border-radius: 4px;
}
.pagination a:hover {
    background-color: #e9ecef;
}
.pagination a.disabled { 
    color: #999; 
    pointer-events: none; 
    background-color: #f8f9fa; 
}

/* === Modal Styling === */
.modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0; top: 0;
    width: 100%; height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}
.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 400px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
}
.modal-header {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 15px;
    text-align: center;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    font-weight: 500;
}
.form-group input, .form-group select {
    width: 100%;
    padding: 8px;
}
#close-modal {
    float: right;
    cursor: pointer;
    font-size: 18px;
    color: red;
}
</style>

</head>
<body>

<?php include '../includes/header.php'; ?>
<div class="dashboard-container" style="position: relative; overflow: visible;">
<?php include '../includes/sidebar.php'; ?>

    <h2>Lunch Payment Tracker</h2>

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
  // Live search
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
    if (!query) { $('#suggestions').hide().empty(); return; }
    $.post('search-students.php', { query }, function(response){
      $('#suggestions').empty().show();
      if(Array.isArray(response) && response.length > 0){
        response.forEach(function(student){
          $('#suggestions').append(`
            <div class="suggestion-item" data-admission="${student.admission_no}" data-name="${student.name}" data-class="${student.class}">
              ${student.name} - ${student.admission_no} - ${student.class}
            </div>
          `);
        });
      } else {
        $('#suggestions').append('<div class="suggestion-item">No records found</div>');
      }
    }, 'json');
  });

  // Select student
  $('#suggestions').on('click', '.suggestion-item', function(){
    const admission = $(this).data('admission');
    const name = $(this).data('name');
    const studentClass = $(this).data('class');
    $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').hide().empty();

    const term = $('#term').val();
    if(!term){ 
      $('#message-box').html("<div class='unpaid'>Please select a term first</div>").show(); 
      return; 
    }
    loadTracker(term, admission, 1);
  });

  // Auto-update on term change
  $('#term').on('change', function(){
    const term = $(this).val();
    const admission = $('#admission_no').val();
    if(term && admission){
      loadTracker(term, admission, 1);
    }
  });

  // Show popup form on Pay click
  $('#pay-btn').on('click', function(){
    const term = $('#term').val();
    const admission = $('#admission_no').val();
    if(term && admission){
      $('#modal-admission-no').val(admission);
      $('#payment-modal').fadeIn();
    } else {
      $('#message-box').html("<div class='unpaid'>Please select a term and student first</div>").show();
    }
  });

  // Close modal
  $('#close-modal').on('click', function(){
    $('#payment-modal').fadeOut();
  });

  // Submit payment form
  $('#payment-form').on('submit', function(e){
    e.preventDefault();
    const data = $(this).serialize();
    $.post('./pay-lunch.php', data, function(response){
      alert(response.message);
      $('#payment-modal').fadeOut();
      const term = $('#term').val();
      const admission = $('#admission_no').val();
      loadTracker(term, admission, 1);
    }, 'json');
  });

  // Load tracker results
  function loadTracker(term, admission, page){
    $.get(window.location.pathname, {ajax:1, term:term, admission_no:admission, page:page}, function(response){
      $('#tracker-results').html(response);

      // Pagination
      $('#tracker-results').off('click').on('click', '.pagination a', function(e){
        e.preventDefault();
        const newPage = $(this).data('page');
        if(!$(this).hasClass('disabled') && newPage) loadTracker(term, admission, newPage);
      });
    });
  }


});
</script>
</body>
</html>
