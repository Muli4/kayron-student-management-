<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

$message = $_SESSION['message'] ?? '';
$start_date = $_SESSION['start_date'] ?? '';
$end_date = $_SESSION['end_date'] ?? '';
unset($_SESSION['message']); // Clear message so it shows once

$results = [
    'school fees'         => 0,
    'lunch fees'          => 0,
    'graduation & prize'  => 0,
    'others'              => 0,
    'books and uniform'   => 0
];

$totalPaid = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST form submission
    $start_date_post = $_POST['start_date'] ?? '';
    $end_date_post = $_POST['end_date'] ?? '';

    if (!$start_date_post || !$end_date_post) {
        $_SESSION['message'] = "Please select both start and end dates.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['start_date'] = $start_date_post;
        $_SESSION['end_date'] = $end_date_post;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// If start and end date are set in session, fetch the results
if ($start_date && $end_date) {
    $db_start = date('Y-m-d', strtotime($start_date));
    $db_end = date('Y-m-d', strtotime($end_date));

    function safePrepare($conn, $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("SQL prepare failed: " . $conn->error);
        }
        return $stmt;
    }

    // 1. School Fee
    $stmt = safePrepare($conn, "
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM school_fee_transactions 
        WHERE DATE(payment_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $db_start, $db_end);
    $stmt->execute();
    $stmt->bind_result($results['school fees']);
    $stmt->fetch();
    $stmt->close();

    // 2. Lunch Fee
    $stmt = safePrepare($conn, "
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM lunch_fee_transactions 
        WHERE DATE(payment_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $db_start, $db_end);
    $stmt->execute();
    $stmt->bind_result($results['lunch fees']);
    $stmt->fetch();
    $stmt->close();

    // 3a. Graduation + Prize Giving
    $stmt = safePrepare($conn, "
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM other_transactions 
        WHERE DATE(transaction_date) BETWEEN ? AND ? 
        AND status = 'Completed' 
        AND fee_type IN ('Graduation', 'Prize Giving')
    ");
    $stmt->bind_param("ss", $db_start, $db_end);
    $stmt->execute();
    $stmt->bind_result($results['graduation & prize']);
    $stmt->fetch();
    $stmt->close();

    // 3b. Other fee types excluding Graduation + Prize Giving
    $stmt = safePrepare($conn, "
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM other_transactions 
        WHERE DATE(transaction_date) BETWEEN ? AND ? 
        AND status = 'Completed' 
        AND fee_type NOT IN ('Graduation', 'Prize Giving')
    ");
    $stmt->bind_param("ss", $db_start, $db_end);
    $stmt->execute();
    $stmt->bind_result($results['others']);
    $stmt->fetch();
    $stmt->close();

    // 4. Books + Uniform Purchases
    $stmt = safePrepare($conn, "
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM (
            SELECT amount_paid, purchase_date AS date 
            FROM book_purchases
            UNION ALL
            SELECT amount_paid, purchase_date AS date 
            FROM uniform_purchases
        ) AS purchases
        WHERE DATE(date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $db_start, $db_end);
    $stmt->execute();
    $stmt->bind_result($results['books and uniform']);
    $stmt->fetch();
    $stmt->close();

    // Final total
    $totalPaid = array_sum($results);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Weekly Financial Report</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    .weekly-report {
      font-family: 'Segoe UI', sans-serif;
      color: #333;
    }

    .weekly-report__container {
      max-width: 960px;
      margin: 60px auto;
      padding: 35px 40px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    .weekly-report__title {
      text-align: center;
      font-size: 26px;
      margin-bottom: 30px;
      color: #004b8d;
      border-bottom: 2px solid #004b8d;
      padding-bottom: 10px;
    }
    .weekly-report__title i {
      margin-right: 10px;
      vertical-align: middle;
      font-size: 26px;
      color: #004b8d;
    }

    .weekly-report__form {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-bottom: 30px;
    }

    .weekly-report__form label {
      font-weight: 600;
      font-size: 15px;
    }

    .weekly-report__form input[type="date"] {
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 14px;
      min-width: 150px;
    }

    .weekly-report__form button {
      padding: 10px 18px;
      background-color: #004b8d;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .weekly-report__form button:hover {
      background-color: #00345f;
    }

    .weekly-report__table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    .weekly-report__table th,
    .weekly-report__table td {
      border: 1px solid #ddd;
      padding: 14px;
      text-align: center;
      font-size: 15px;
    }

    .weekly-report__table th {
      background-color: #004b8d;
      color: white;
      text-transform: capitalize;
    }

    .weekly-report__table tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .weekly-report__table tr:last-child {
      font-weight: bold;
      background-color: #e6f0fa;
    }

    .weekly-report__message {
      color: red;
      text-align: center;
      font-size: 15px;
      margin-top: 10px;
    }

    .weekly-report__summary {
      text-align: center;
      color: #004b8d;
      margin-top: 20px;
      font-size: 18px;
    }

@media (max-width: 600px) {
  .weekly-report__container {
    padding-left: 15px;
    padding-right: 15px;
  }

  .weekly-report__table {
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    overflow-x: auto;
    display: block;
  }

  .weekly-report__table th,
  .weekly-report__table td {
    padding: 10px 8px;
    font-size: 14px;
  }
}


    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }

      .printable-area,
      .printable-area * {
        visibility: visible;
      }

      .printable-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 90%;
        font-family: 'Segoe UI', sans-serif;
        color: black;
        background: white;
        padding: 20px;
      }

      .printable-area table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
      }

      .printable-area table,
      .printable-area th,
      .printable-area td {
        border: 1px solid black !important;
        color: black !important;
        background: white !important;
      }

      .printable-area th {
        background: #eee !important;
        font-weight: bold;
      }

      .printable-area td,
      .printable-area th {
        padding: 8px;
        text-align: center;
      }
    }
  </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
  <?php include '../includes/sidebar.php'; ?>

  <main class="content weekly-report">
    <div class="weekly-report__container">
      <h1 class="weekly-report__title"><i class='bx bx-bar-chart-alt-2'></i>Weekly Financial Report</h1>

      <form method="post" class="weekly-report__form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <label>Start Date:
          <input type="date" name="start_date" required value="<?= htmlspecialchars($start_date) ?>">
        </label>
        <label>End Date:
          <input type="date" name="end_date" required value="<?= htmlspecialchars($end_date) ?>">
        </label>
        <button type="submit">Generate Report</button>
        <button type="button" onclick="printReport()">Print Report</button>
      </form>

      <?php if ($message): ?>
        <div class="weekly-report__message"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if ($start_date && $end_date && !$message): ?>
        <?php
          $formatted_start = date('d/m/Y', strtotime($start_date));
          $formatted_end = date('d/m/Y', strtotime($end_date));
        ?>

        <h3 class="weekly-report__summary">Payments from <?= $formatted_start ?> to <?= $formatted_end ?>:</h3>

        <table class="weekly-report__table">
          <thead>
            <tr>
              <th>School Fees</th>
              <th>Lunch Fees</th>
              <th>Prize Giving & Graduation</th>
              <th>Others</th>
              <th>Books and Uniform</th>
              <th>Total Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= number_format($results['school fees'], 2) ?></td>
              <td><?= number_format($results['lunch fees'], 2) ?></td>
              <td><?= number_format($results['graduation & prize'], 2) ?></td>
              <td><?= number_format($results['others'], 2) ?></td>
              <td><?= number_format($results['books and uniform'], 2) ?></td>
              <td><?= number_format($totalPaid, 2) ?></td>
            </tr>
            <tr style="font-weight: bold; background-color: #e6f0fa;">
              <td><?= number_format($results['school fees'], 2) ?></td>
              <td><?= number_format($results['lunch fees'], 2) ?></td>
              <td><?= number_format($results['graduation & prize'], 2) ?></td>
              <td><?= number_format($results['others'], 2) ?></td>
              <td><?= number_format($results['books and uniform'], 2) ?></td>
              <td><?= number_format($totalPaid, 2) ?></td>
            </tr>
          </tbody>
        </table>


      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
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
        }, 300000); // 30 seconds
    }

    // Reset timer on user activity
    ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetLogoutTimer);
    });

    // Start the timer when page loads
    resetLogoutTimer();
});
</script>
  <script>
    function printReport() {
      const summary = document.querySelector('.weekly-report__summary');
      const table = document.querySelector('.weekly-report__table');

      if (!summary || !table) {
        alert("No report available to print.");
        return;
      }

      const printContainer = document.createElement('div');
      printContainer.classList.add('printable-area');
      printContainer.appendChild(summary.cloneNode(true));
      printContainer.appendChild(table.cloneNode(true));

      const originalBody = document.body.innerHTML;
      document.body.innerHTML = '';
      document.body.appendChild(printContainer);

      window.print();

      document.body.innerHTML = originalBody;
      window.location.reload();
    }
  </script>
</body>
</html>
