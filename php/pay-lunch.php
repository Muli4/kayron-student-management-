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

    $receipt_no = 'RCPT-' . strtoupper(uniqid());
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
        $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward) VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
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


    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
      <link rel="stylesheet" href="../style/style.css"/>
      <link rel="website icon" type="png" href="photos/Logo.jpg">
      <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <title>Lunch Fee Payment</title>
            <style>
        .container-wrapper {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: flex-start;
          min-height: 100vh;
          padding: 20px;
          background-color: #f2f2f2;
          overflow-y: auto;
        }

        .payment-container {
          width: 100%;
          max-width: 600px;
          background: #fff;
          border-radius: 12px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
          padding: 25px 20px;
          margin-bottom: 30px;
        }

        .payment-container h2 {
          text-align: center;
          margin-bottom: 20px;
          color: #333;
        }

        .form-group {
          position: relative;
          width: 100%;
          max-width: 400px;
          margin: 0 auto 20px;
        }

        .form-group label {
          display: block;
          margin-bottom: 6px;
          font-weight: 600;
          color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
          width: 100%;
          padding: 10px 12px;
          border: 1px solid #ccc;
          border-radius: 6px;
          font-size: 16px;
          box-sizing: border-box;
        }

        #suggestions {
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background-color: #007bff;
          max-height: 250px;
          overflow-y: auto;
          z-index: 1000;
          border-radius: 0 0 6px 6px;
        }

        .suggestion-item {
          padding: 10px 12px;
          cursor: pointer;
          font-size: 15px;
          color: #fff;
        }

        .suggestion-item:hover {
          background-color: #fff;
          color: #007bff;
        }

        .btn-submit {
          width: 100%;
          max-width: 400px;
          padding: 12px;
          background-color: #007bff;
          border: none;
          border-radius: 6px;
          color: white;
          font-size: 16px;
          cursor: pointer;
        }

        .btn-submit:hover {
          background-color: #0056b3;
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

      $('#suggestions').on('click', '.suggestion-item', function () {
        const name = $(this).data('name');
        const admission = $(this).data('admission');
        const studentClass = $(this).data('class');

        $('#student-search').val(`${name} - ${admission} - ${studentClass}`);
        $('#admission_no').val(admission);
        $('#suggestions').empty();
      });

      $(document).on('click', function (e) {
        if (!$(e.target).closest('.form-group').length) {
          $('#suggestions').empty();
        }
      });
    });
    </script>
    <script>
            document.addEventListener("DOMContentLoaded", function () {
        function updateClock() {
            const clockElement = document.getElementById('realTimeClock');
            if (clockElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                clockElement.textContent = timeString;
            }
        }
        updateClock(); // Initial call
        setInterval(updateClock, 1000); // Update every second
    });
    </script>
    </body>
    </html>