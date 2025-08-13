<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

include 'db.php';

$message = "";

// Validate week_id in query string
if (!isset($_GET['week_id']) || !is_numeric($_GET['week_id'])) {
    die("Invalid week ID.");
}

$week_id = intval($_GET['week_id']);

// Get week info including term_id and week_number
$stmt = $conn->prepare("SELECT w.*, t.term_number, t.year FROM weeks w JOIN terms t ON w.term_id = t.id WHERE w.id = ?");
$stmt->bind_param("i", $week_id);
$stmt->execute();
$week = $stmt->get_result()->fetch_assoc();

if (!$week) {
    die("Week not found.");
}

$week_number = $week['week_number'];
$term_number = $week['term_number'];
$year = $week['year'];

// Days of week we care about
$week_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_days'])) {
    $checked_days = $_POST['public_holidays'] ?? [];

    // Load existing days for this week
    $stmt_days = $conn->prepare("SELECT * FROM days WHERE week_id = ?");
    $stmt_days->bind_param("i", $week_id);
    $stmt_days->execute();
    $days_result = $stmt_days->get_result();

    while ($day = $days_result->fetch_assoc()) {
        $day_name = $day['day_name'];
        $day_id = $day['id'];
        $should_be_holiday = in_array($day_name, $checked_days) ? 1 : 0;

        if ($day['is_public_holiday'] != $should_be_holiday) {
            $stmt_update = $conn->prepare("UPDATE days SET is_public_holiday = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $should_be_holiday, $day_id);
            $stmt_update->execute();

            if ($should_be_holiday === 1) {
                // Auto-insert 'Absent' for all students if not already recorded
                $stmt_students = $conn->query("SELECT admission_no FROM student_records");
                while ($student = $stmt_students->fetch_assoc()) {
                    $admission_no = $student['admission_no'];

                    // Check if attendance already exists
                    $stmt_check_att = $conn->prepare("SELECT admission_no FROM attendance WHERE admission_no = ? AND term_number = ? AND week_number = ? AND day_id = ?");
                    $stmt_check_att->bind_param("siii", $admission_no, $term_number, $week_number, $day_id);
                    $stmt_check_att->execute();
                    $att_res = $stmt_check_att->get_result();

                    if ($att_res->num_rows === 0) {
                        $stmt_insert_att = $conn->prepare("INSERT INTO attendance (admission_no, term_number, week_number, day_id, day_name, status) VALUES (?, ?, ?, ?, ?, 'Absent')");
                        $stmt_insert_att->bind_param("siiis", $admission_no, $term_number, $week_number, $day_id, $day_name);
                        $stmt_insert_att->execute();
                    }
                }
            } else {
                // Remove holiday 'Absent' records
                $stmt_delete_att = $conn->prepare("DELETE FROM attendance WHERE term_number = ? AND week_number = ? AND day_id = ? AND status = 'Absent'");
                $stmt_delete_att->bind_param("iii", $term_number, $week_number, $day_id);
                $stmt_delete_att->execute();
            }
        }
    }

    $_SESSION['message'] = "Week $week_number updated successfully.";
    header("Location: edit-week.php?week_id=$week_id");
    exit();
}

// Load days for display with their holiday status
$stmt_days = $conn->prepare("SELECT * FROM days WHERE week_id = ? ORDER BY FIELD(day_name, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')");
$stmt_days->bind_param("i", $week_id);
$stmt_days->execute();
$days = $stmt_days->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Week <?= htmlspecialchars($week_number) ?></title>
    <link rel="stylesheet" href="../style/style-sheet.css" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      /* Simple table styling consistent with your theme */
      .edit-week-container {
        max-width: 700px;
        margin: 30px auto 60px;
        padding: 20px 30px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #2c3e50;
      }
      .edit-week-container h1 {
        text-align: center;
        margin-bottom: 25px;
        color: #1a4d8f;
        font-weight: 700;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
      }
      th, td {
        border: 1px solid #2980b9;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 1rem;
      }
      th {
        background-color: #2980b9;
        color: white;
      }
      td input[type="checkbox"] {
        transform: scale(1.2);
        cursor: pointer;
      }
      .message {
        text-align: center;
        font-weight: 700;
        color: #27ae60;
        margin-bottom: 20px;
      }
      .btn-group {
        text-align: center;
      }
      button, a.back-link {
        padding: 10px 25px;
        font-size: 1rem;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
      }
      button {
        background-color: #2980b9;
        color: white;
        border: none;
        transition: background-color 0.3s ease;
      }
      button:hover {
        background-color: #1b6699;
      }
      a.back-link {
        background-color: #7f8c8d;
        color: white;
        margin-left: 20px;
      }
      a.back-link:hover {
        background-color: #565f60;
      }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="content">
            <div class="edit-week-container">
                <h1><i class="bx bx-calendar-edit"></i> Edit Week <?= htmlspecialchars($week_number) ?> (Term <?= htmlspecialchars($term_number) ?>, <?= htmlspecialchars($year) ?>)</h1>

                <?php if (!empty($_SESSION['message'])): ?>
                    <div class="message">
                        <?= htmlspecialchars($_SESSION['message']) ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <form method="POST" action="">
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th style="text-align:center;">Public Holiday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                                <tr>
                                    <td><?= htmlspecialchars($day['day_name']) ?></td>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="public_holidays[]" value="<?= htmlspecialchars($day['day_name']) ?>" 
                                            <?= ($day['is_public_holiday'] == 1) ? 'checked' : '' ?> />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="btn-group">
                        <button type="submit" name="save_days"><i class="bx bx-save"></i> Save Changes</button>
                        <a href="week.php" class="back-link"><i class="bx bx-arrow-back"></i> Back to Weeks</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }

  function showEditPopup() {
    document.getElementById('termEditPopup').style.display = 'block';
  }

  function closeEditPopup() {
    document.getElementById('termEditPopup').style.display = 'none';
  }

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