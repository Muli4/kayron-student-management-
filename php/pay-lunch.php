pay    <?php
    session_start();
    if (!isset($_SESSION['username'])) {
        header("Location: ../index.php");
        exit();
    }
    include 'db.php';

    $daily_fee = 70;

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $admission_no = trim($_POST['admission_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_type = $_POST['payment_method'] ?? 'Cash';

        if (empty($admission_no) || $amount <= 0) {
            echo "<script>alert('Invalid input data!'); window.location.href='pay-lunch.php';</script>";
            exit();
        }

        $receipt_no = 'RCPT-' . strtoupper(uniqid());
        $original_amount = $amount;

        // === Validate student
        $stmt = $conn->prepare("SELECT name, class FROM student_records WHERE admission_no = ?");
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            echo "<script>alert('Admission number not found.'); window.location.href='pay-lunch.php';</script>";
            exit();
        }
        $name = $student['name'];
        $class = $student['class'];

        // === Get Active Term
        $stmt = $conn->prepare("SELECT id, start_date, end_date FROM terms ORDER BY start_date DESC LIMIT 1");
        $stmt->execute();
        $term = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$term) {
            echo "<script>alert('No active term found!'); window.location.href='pay-lunch.php';</script>";
            exit();
        }
        $term_id = $term['id'];
        $start_date = $term['start_date'];
        $end_date = $term['end_date'];

        // === Calculate total weeks
        $total_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
        $total_weeks = ceil($total_days / 5); // Only Mon-Fri counted as school week

        // === Prepare weekly fee amounts
        $total_weekly_fee = $daily_fee * 5;

        // === Fetch last week's data
        $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? ORDER BY week_number DESC LIMIT 1");
        $stmt->bind_param("si", $admission_no, $term_id);
        $stmt->execute();
        $week_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $week_number = $week_data ? ($week_data['balance'] == 0 ? $week_data['week_number'] + 1 : $week_data['week_number']) : 1;
        $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        // === Insert first week if needed
        if (!$week_data || $week_data['balance'] == 0) {
            $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward)
                VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly_fee, $week_number, $total_weekly_fee, $payment_type);
            $stmt->execute();
            $stmt->close();
        }

        // === Start distributing payment
        while ($amount > 0 && $week_number <= $total_weeks) {
            // Fetch current week record
            $stmt = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no = ? AND term_id = ? AND week_number = ?");
            $stmt->bind_param("sii", $admission_no, $term_id, $week_number);
            $stmt->execute();
            $week_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $balance = $week_data['balance'];

            foreach ($valid_days as $day) {
                if ($balance <= 0 || $amount <= 0) break;

                if ($week_data[$day] < $daily_fee) {
                    $remaining_fee = $daily_fee - $week_data[$day];
                    $pay_today = min($remaining_fee, $amount);

                    $stmt = $conn->prepare("
                        UPDATE lunch_fees
                        SET $day = $day + ?, total_paid = total_paid + ?, balance = balance - ?
                        WHERE admission_no = ? AND term_id = ? AND week_number = ?
                    ");
                    $stmt->bind_param("dddssi", $pay_today, $pay_today, $pay_today, $admission_no, $term_id, $week_number);
                    $stmt->execute();
                    $stmt->close();

                    $amount -= $pay_today;
                    $balance -= $pay_today;
                }
            }

            // Move to next week if current week fully paid
            if ($balance <= 0 && $week_number < $total_weeks) {
                $week_number++;
                // Insert next week
                $stmt = $conn->prepare("INSERT INTO lunch_fees (admission_no, term_id, total_paid, balance, week_number, total_amount, payment_type, carry_forward)
                    VALUES (?, ?, 0, ?, ?, ?, ?, 0)");
                $stmt->bind_param("sidiss", $admission_no, $term_id, $total_weekly_fee, $week_number, $total_weekly_fee, $payment_type);
                $stmt->execute();
                $stmt->close();
            } else {
                break;
            }
        }

        // === Carry Forward if extra remains after last week
        if ($amount > 0 && $week_number >= $total_weeks) {
            $carry_forward = $amount;
            $stmt = $conn->prepare("
                UPDATE lunch_fees 
                SET carry_forward = ? 
                WHERE admission_no = ? AND term_id = ? AND week_number = ?
            ");
            $last_week_number = min($week_number, $total_weeks);
            $stmt->bind_param("dsii", $carry_forward, $admission_no, $term_id, $last_week_number);
            $stmt->execute();
            $stmt->close();
            $amount = 0;
        }

        // === Record transaction
        $stmt = $conn->prepare("INSERT INTO lunch_fee_transactions (name, class, admission_no, receipt_number, amount_paid, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssds", $name, $class, $admission_no, $receipt_no, $original_amount, $payment_type);
        $stmt->execute();
        $stmt->close();

        echo "<script>alert('Payment successful. Receipt #: $receipt_no'); window.location.href='pay-lunch.php';</script>";
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