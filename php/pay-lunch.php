<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
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
        echo "<script>alert('Invalid input!'); window.location.href='pay-lunch.php';</script>";
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

    // 2. Get ALL terms and also determine the current/latest term
    $terms_res = $conn->query("SELECT id, term_number, year, start_date, end_date FROM terms ORDER BY start_date ASC");
    if (!$terms_res || $terms_res->num_rows === 0) {
        echo "<script>alert('No terms available.'); window.location.href='pay-lunch.php';</script>";
        exit();
    }

    // Get the last row for current term
    $terms_arr = $terms_res->fetch_all(MYSQLI_ASSOC);
    $current_term = end($terms_arr);  // Last (latest) term
    $current_term_number = $current_term['term_number'];

    // Generate receipt number using current term
    $randomDigits = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $receipt_no = "LF-T{$current_term_number}-{$randomDigits}";

    // Reset pointer for looping through terms
    reset($terms_arr);

    // 3. Helper to insert a week row
    function insertWeek($conn, $admission_no, $term_id, $week_num, $payment_type, $daily_fee) {
        $total_weekly = $daily_fee * 5;
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward) VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly, $week_num, $total_weekly, $payment_type);
        $stmt->execute();
        $stmt->close();
    }

    // 4. Process payments per term, oldest-first
    foreach ($terms_arr as $term) {
        if ($amount <= 0) break;

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
            if ($amount <= 0) break;

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

                // Grab day entry for holiday/attendance check
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
                    continue; // skip holidays or absence
                }

                $paidToday = floatval($weekData[strtolower($dayName)]);
                if ($paidToday >= $daily_fee) continue;

                $topUp = min($daily_fee - $paidToday, $amount);

                $stmt = $conn->prepare("
                    UPDATE lunch_fees
                    SET " . strtolower($dayName) . " = " . strtolower($dayName) . " + ?, total_paid = total_paid + ?, balance = balance - ?
                    WHERE id = ?
                ");
                $stmt->bind_param("dddi", $topUp, $topUp, $topUp, $weekData['id']);
                $stmt->execute();
                $stmt->close();

                $amount -= $topUp;
                $weekData[strtolower($dayName)] += $topUp;
                $weekData['balance'] -= $topUp;
            }
        }

        // Carry forward if still amount leftover after all weeks
        if ($amount > 0 && $wk > $total_weeks) {
            $lastWeekNum = min($total_weeks, $startWeek);
            $stmt = $conn->prepare("UPDATE lunch_fees SET carry_forward = carry_forward + ? WHERE admission_no = ? AND term_id = ? AND week_number = ?");
            $stmt->bind_param("dsii", $amount, $admission_no, $termId, $lastWeekNum);
            $stmt->execute();
            $stmt->close();
            $amount = 0;
        }
    }

    // 5. Log transaction with generated receipt number
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



    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
      <link rel="stylesheet" href="../style/style-sheet.css"/>
      <link rel="website icon" type="png" href="../images/school-logo.jpg">
      <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <title>Lunch Fee Payment</title>
<style>
  /* Container */
.payment-container {
  max-width: 480px;
  margin: 30px auto;
  padding: 25px 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 3px 8px rgb(0 0 0 / 0.1);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Heading */
.payment-container h2 {
  text-align: center;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 25px;
  font-size: 1.9rem;
}

/* Form groups */
.form-group {
  margin-bottom: 18px;
  position: relative;
}

label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #34495e;
}

/* Inputs and selects */
input[type="text"],
input[type="number"],
select {
  width: 100%;
  padding: 10px 12px;
  font-size: 1rem;
  border: 2px solid #2980b9;
  border-radius: 6px;
  box-sizing: border-box;
  transition: border-color 0.3s ease;
}

input[type="text"]:focus,
input[type="number"]:focus,
select:focus {
  border-color: #1b6699;
  outline: none;
}

/* Suggestions box */
#suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1.8px solid #2980b9;
  border-top: none;
  max-height: 150px;
  overflow-y: auto;
  z-index: 1000;
  border-radius: 0 0 6px 6px;
  font-size: 0.95rem;
  box-shadow: 0 3px 6px rgb(0 0 0 / 0.1);
}

.suggestion-item {
  padding: 9px 12px;
  cursor: pointer;
  color: #34495e;
  transition: background-color 0.2s ease;
}

.suggestion-item:hover {
  background-color: #2980b9;
  color: white;
}

/* Submit button */
.btn-submit {
  width: 100%;
  padding: 14px 0;
  font-weight: 700;
  font-size: 1.15rem;
  background-color: #2980b9;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.btn-submit:hover {
  background-color: #1b6699;
}

/* Responsive adjustments */
@media (max-width: 550px) {
  .payment-container {
    margin: 20px 10px;
    padding: 20px 15px;
  }
}

</style>
    </head>
    <body>
    <?php include '../includes/header.php'; ?>
    <div class="dashboard-container">
      <?php include '../includes/sidebar.php'; ?>
      <main class="content">
        <div class="container-wrapper">
          <div class="payment-container">
            <h2>Lunch Fee Payment</h2>
            <form action="pay-lunch.php" method="POST" id="paymentForm">
              <div class="form-group">
                <label for="student-search">Search Student</label>
                <input type="text" id="student-search" placeholder="Enter student name..." autocomplete="off" />
                <input type="hidden" id="admission_no" name="admission_no" />
                <div id="suggestions"></div>
              </div>

              <div class="form-group">
                <label for="amount">Amount</label>
                <input type="number" id="amount" name="amount" required />
              </div>

              <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" required>
                  <option value="">-- Select Payment Method --</option>
                  <option value="MPESA">MPESA</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank</option>
                </select>
              </div>

              <div class="form-group">
                <button type="submit" class="btn-submit">Make Payment</button>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>
    <?php include '../includes/footer.php'; ?>

    <script>
$(document).ready(function () {
  // Student search AJAX live suggestions
  $('#student-search').on('input', function () {
    const query = $(this).val().trim();
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

  // When user clicks a suggestion item
  $('#suggestions').on('click', '.suggestion-item', function () {
    const name = $(this).data('name');
    const admission = $(this).data('admission');
    const studentClass = $(this).data('class');

    $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
    $('#admission_no').val(admission);
    $('#suggestions').empty();
  });

  // Hide suggestions if clicking outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.form-group').length) {
      $('#suggestions').empty();
    }
  });
});

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

  /* ===== Auto logout after 5 minutes inactivity (no alert) ===== */
  let logoutTimer;

  function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutTimer = setTimeout(() => {
      window.location.href = 'logout.php'; // Adjust your logout URL here
    }, 300000); // 300,000ms = 5 minutes
  }

  // Reset timer on user activity
  ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, resetLogoutTimer);
  });

  // Start the timer on page load
  resetLogoutTimer();
});
</script>

    </body>
    </html>