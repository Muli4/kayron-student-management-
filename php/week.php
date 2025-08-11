<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include 'db.php';

$message = "";
$current_date = date('Y-m-d');

// === Fee structure and class order
$class_order = ['babyclass', 'intermediate', 'pp1', 'pp2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];
$fee_structure = [
    "babyclass" => [4700, 4700, 4700],
    "intermediate" => [4700, 4700, 4700],
    "pp1" => [4700, 4700, 4700],
    "pp2" => [4700, 4700, 4700],
    "grade1" => [5700, 5700, 5700],
    "grade2" => [5700, 5700, 5700],
    "grade3" => [5700, 5700, 5700],
    "grade4" => [6700, 6700, 6700],
    "grade5" => [6700, 6700, 6700],
    "grade6" => [6700, 6700, 6700]
];

function calculateWeeks($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    return ceil(($end->diff($start)->days + 1) / 7);
}

function weekExists($conn, $term_id, $week_number) {
    $stmt = $conn->prepare("SELECT id FROM weeks WHERE term_id = ? AND week_number = ?");
    $stmt->bind_param("ii", $term_id, $week_number);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}


// === Get current term
$stmt = $conn->prepare("SELECT * FROM terms ORDER BY year DESC, term_number DESC LIMIT 1");
$stmt->execute();
$current_term = $stmt->get_result()->fetch_assoc();
$current_term_id = $current_term['id'] ?? null;

$term_required = !$current_term;
$term_expired_notice = false;

if ($current_term && strtotime($current_term['end_date']) < strtotime($current_date)) {
    $term_expired_notice = true;
    $message = "⚠ Warning: Term {$current_term['term_number']} ({$current_term['year']}) ended on {$current_term['end_date']}. 
    <a href='week.php?register_new_term=1' style='color:red;font-weight:bold;'>Register New Term</a> to continue.";
}

// === Auto-suggest Week & Day
$week_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
$suggested_week = 1;
$suggested_day = "Monday";

if ($current_term) {
    $current_term_id = $current_term['id'];
    $max_weeks = calculateWeeks($current_term['start_date'], $current_term['end_date']);

    $days_res = $conn->prepare("
        SELECT d.day_name 
        FROM days d 
        JOIN weeks w ON d.week_id = w.id 
        WHERE w.term_id = ? 
        ORDER BY w.week_number ASC, d.id ASC
    ");
    $days_res->bind_param("i", $current_term_id);
    $days_res->execute();
    $res = $days_res->get_result();
    $filled_days = [];
    while ($row = $res->fetch_assoc()) {
        $filled_days[] = $row['day_name'];
    }

    $filled_count = count($filled_days);
    $suggested_week = floor($filled_count / 5) + 1;
    $suggested_day = $week_days[$filled_count % 5];

    if ($suggested_week > $max_weeks && $filled_count % 5 == 0) {
        $message = "⚠ All weeks for Term {$current_term['term_number']} are filled. 
        <a href='week.php?register_new_term=1' style='color:red;font-weight:bold;'>Register New Term</a>.";
    }
}

// === Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // === Register New Term
    if (isset($_POST['register_term'])) {
        $term_number = intval($_POST['term_number']);
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $year = date('Y', strtotime($start));

        $last_term_res = $conn->query("SELECT * FROM terms ORDER BY year DESC, term_number DESC LIMIT 1");
        $last_term = $last_term_res->fetch_assoc();

        $promotion_mode = false;
        if ($last_term) {
            $last_term_number = intval($last_term['term_number']);
            $last_term_year = intval($last_term['year']);

            if ($last_term_number === 3 && $term_number !== 1 && $year > $last_term_year) {
                echo "<script>alert('Unable to register this term. Only Term 1 can be registered after Term 3 for promotion.'); window.location.href='week.php';</script>";
                exit();
            }

            if ($last_term_number === 3 && $term_number === 1 && $year > $last_term_year) {
                $promotion_mode = true;
            }
        }

        $stmt_check_terms = $conn->prepare("SELECT COUNT(*) AS total FROM terms WHERE year = ?");
        $stmt_check_terms->bind_param("i", $year);
        $stmt_check_terms->execute();
        $res_count = $stmt_check_terms->get_result()->fetch_assoc();
        if ($res_count['total'] >= 3) {
            $message = "Cannot register more than 3 terms in $year.";
        } else {
            $stmt_check = $conn->prepare("SELECT * FROM terms WHERE term_number = ? AND year = ?");
            $stmt_check->bind_param("ii", $term_number, $year);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $message = "This term is already registered for $year.";
            } else {
                $stmt = $conn->prepare("INSERT INTO terms (term_number, year, start_date, end_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $term_number, $year, $start, $end);
                if ($stmt->execute()) {
                    $new_term_id = $stmt->insert_id;
                    $term_string = "term" . $term_number;

                    $students = $conn->query("SELECT admission_no, class, term FROM student_records");
                    while ($student = $students->fetch_assoc()) {
                        $admission_no = $student['admission_no'];
                        $class = strtolower($student['class']);
                        $term_string = "term" . $term_number;

                        if ($promotion_mode) {
                            if ($class === 'grade6') {
                                $stmt_get_student = $conn->prepare("SELECT * FROM student_records WHERE admission_no = ?");
                                $stmt_get_student->bind_param("s", $admission_no);
                                $stmt_get_student->execute();
                                $student_data = $stmt_get_student->get_result()->fetch_assoc();

                                if ($student_data) {
                                    $graduation_date = date('Y-m-d');

                                    $stmt_insert_grad = $conn->prepare("
                                        INSERT INTO graduated_students (
                                            admission_no, name, birth_certificate, dob, gender, 
                                            class_completed, term, religion, guardian_name, phone, 
                                            student_photo, year_completed, graduation_date
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt_insert_grad->bind_param(
                                        "sssssssssssis",
                                        $student_data['admission_no'],
                                        $student_data['name'],
                                        $student_data['birth_certificate'],
                                        $student_data['dob'],
                                        $student_data['gender'],
                                        $class,
                                        $student_data['term'],
                                        $student_data['religion'],
                                        $student_data['guardian_name'],
                                        $student_data['phone'],
                                        $student_data['student_photo'],
                                        $last_term['year'],
                                        $graduation_date
                                    );
                                    $stmt_insert_grad->execute();
                                }

                                $stmt_delete_records = $conn->prepare("DELETE FROM student_records WHERE admission_no = ?");
                                $stmt_delete_records->bind_param("s", $admission_no);
                                $stmt_delete_records->execute();

                                continue;
                            } else {
                                $current_index = array_search($class, $class_order);
                                if ($current_index !== false && $current_index < count($class_order) - 1) {
                                    $promoted_class = $class_order[$current_index + 1];
                                    $stmt_update = $conn->prepare("UPDATE student_records SET class = ? WHERE admission_no = ?");
                                    $stmt_update->bind_param("ss", $promoted_class, $admission_no);
                                    $stmt_update->execute();
                                    $class = $promoted_class;
                                }
                            }
                        }

                        $is_same_term = ($student['term'] === $term_string);
                        if (!$is_same_term) {
                            $update_term_stmt = $conn->prepare("UPDATE student_records SET term = ? WHERE admission_no = ?");
                            $update_term_stmt->bind_param("ss", $term_string, $admission_no);
                            $update_term_stmt->execute();
                        }

                        if (isset($fee_structure[$class])) {
                            $term_fee = $fee_structure[$class][$term_number - 1];

                            if ($is_same_term) {
                                $stmt_update_fee = $conn->prepare("
                                    UPDATE school_fees
                                    SET total_fee = total_fee + 0, balance = balance + 0
                                    WHERE admission_no = ?
                                ");
                                $stmt_update_fee->bind_param("s", $admission_no);
                                $stmt_update_fee->execute();
                            } else {
                                $stmt_update_fee = $conn->prepare("
                                    UPDATE school_fees
                                    SET total_fee = total_fee + ?, balance = balance + ?
                                    WHERE admission_no = ?
                                ");
                                $stmt_update_fee->bind_param("iis", $term_fee, $term_fee, $admission_no);
                                $stmt_update_fee->execute();

                                if ($stmt_update_fee->affected_rows === 0) {
                                    $stmt_insert_fee = $conn->prepare("
                                        INSERT INTO school_fees (admission_no, class, term, total_fee, amount_paid, balance)
                                        VALUES (?, ?, ?, ?, 0, ?)
                                    ");
                                    $stmt_insert_fee->bind_param("sssii", $admission_no, $class, $term_string, $term_fee, $term_fee);
                                    $stmt_insert_fee->execute();
                                }
                            }
                        }
                    }

                    header("Location: week.php");
                    exit;
                } else {
                    $message = "Failed to register new term: " . $conn->error;
                }
            }
        }
    }

    // === Edit Term Dates
    if (isset($_POST['edit_term']) && $current_term_id) {
        $new_start = $_POST['edit_start_date'];
        $new_end = $_POST['edit_end_date'];
        $stmt_edit = $conn->prepare("UPDATE terms SET start_date = ?, end_date = ? WHERE id = ?");
        $stmt_edit->bind_param("ssi", $new_start, $new_end, $current_term_id);
        if ($stmt_edit->execute()) {
            header("Location: week.php");
            exit;
        } else {
            $message = "Failed to update term dates.";
        }
    }

    // === Register Day
    if (isset($_POST['register_day']) && $current_term_id) {
        $expected_weeks = calculateWeeks($current_term['start_date'], $current_term['end_date']);
        $week_number = intval($_POST['week_number']);
        $selected_day = $_POST['selected_day'];
        $is_public_holiday = isset($_POST['is_holiday']) ? 1 : 0;

        // Validate week number within term weeks
        if ($week_number < 1 || $week_number > $expected_weeks) {
            $message = "Week number $week_number is outside the term duration.
            <a href='week.php?register_new_term=1' style='color:red;font-weight:bold;'>Register New Term</a> to continue.";
        } else {
            // Additional check: calculate week start date and ensure it's within term dates
            $term_start = new DateTime($current_term['start_date']);
            $term_end = new DateTime($current_term['end_date']);
            $week_start = clone $term_start;
            $week_start->modify('+'.(($week_number - 1) * 7).' days');

            if ($week_start > $term_end) {
                $message = "Week $week_number falls outside the term dates.";
            } else {
                // Count existing weeks
                $stmt_count = $conn->prepare("SELECT COUNT(*) AS week_count FROM weeks WHERE term_id = ?");
                $stmt_count->bind_param("i", $current_term_id);
                $stmt_count->execute();
                $week_count_result = $stmt_count->get_result()->fetch_assoc();
                $current_week_count = $week_count_result['week_count'] ?? 0;

                // Check if max weeks reached and week doesn't exist yet
                if ($current_week_count >= $expected_weeks && !weekExists($conn, $current_term_id, $week_number)) {
                    $message = "Cannot register more than $expected_weeks weeks. Term limit reached.
                    <a href='week.php?register_new_term=1' style='color:red;font-weight:bold;'>Register New Term</a> to continue.";
                } else {
                    // Check if the given week exists
                    $stmt_week = $conn->prepare("SELECT id FROM weeks WHERE term_id = ? AND week_number = ?");
                    $stmt_week->bind_param("ii", $current_term_id, $week_number);
                    $stmt_week->execute();
                    $res_week = $stmt_week->get_result();

                    if ($res_week->num_rows > 0) {
                        $week_id = $res_week->fetch_assoc()['id'];
                    } else {
                        // Insert new week
                        $stmt_insert_week = $conn->prepare("INSERT INTO weeks (term_id, week_number) VALUES (?, ?)");
                        $stmt_insert_week->bind_param("ii", $current_term_id, $week_number);
                        $stmt_insert_week->execute();
                        $week_id = $stmt_insert_week->insert_id;
                    }

                    // Insert day
                    $stmt_insert = $conn->prepare("INSERT INTO days (week_id, day_name, is_public_holiday) VALUES (?, ?, ?)");
                    $stmt_insert->bind_param("isi", $week_id, $selected_day, $is_public_holiday);
                    if ($stmt_insert->execute()) {
                        $day_id = $stmt_insert->insert_id;

                        // If it's a public holiday, mark all students as Absent
                        if ($is_public_holiday == 1) {
                            $students = $conn->query("SELECT admission_no FROM student_records");
                            while ($student = $students->fetch_assoc()) {
                                $admission_no = $student['admission_no'];
                                $stmt_att = $conn->prepare("INSERT INTO attendance (admission_no, day_id, status) VALUES (?, ?, 'Absent')");
                                $stmt_att->bind_param("si", $admission_no, $day_id);
                                $stmt_att->execute();
                            }
                        }

                        header("Location: week.php");
                        exit;
                    } else {
                        $message = "Failed to register day: " . $conn->error;
                    }
                }
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Term & Week Registration</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    /* Container for the whole term/week management */
.term-week-container {
  max-width: 460px;
  margin: 25px auto 60px;
  padding: 25px 30px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.15);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #2c3e50;
}

/* Main heading */
.term-week-container h1 {
  text-align: center;
  font-weight: 700;
  font-size: 2rem;
  margin-bottom: 30px;
  color: #1a4d8f;
}

/* Message box for success/error */
.term-week-message {
  background-color: #e7f5e6;
  border: 1.5px solid #27ae60;
  color: #27ae60;
  padding: 12px 18px;
  border-radius: 6px;
  margin-bottom: 20px;
  font-weight: 600;
  text-align: center;
}

/* Forms */
.term-week-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Form headings inside the forms */
.term-week-form h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #2980b9;
  margin-bottom: 12px;
  border-bottom: 2.5px solid #2980b9;
  padding-bottom: 5px;
}

/* Labels */
.term-week-form label {
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 6px;
  color: #34495e;
  display: block;
}

/* Inputs (number, date, text) */
.term-week-form input[type="number"],
.term-week-form input[type="date"],
.term-week-form input[type="text"] {
  padding: 10px 14px;
  font-size: 1rem;
  border: 2px solid #2980b9;
  border-radius: 7px;
  outline-offset: 2px;
  transition: border-color 0.3s ease;
  width: 100%;
  box-sizing: border-box;
  color: #2c3e50;
}

.term-week-form input[type="number"]:read-only,
.term-week-form input[type="text"]:read-only {
  background-color: #f3f8ff;
  cursor: default;
}

.term-week-form input[type="number"]:focus,
.term-week-form input[type="date"]:focus,
.term-week-form input[type="text"]:focus {
  border-color: #1b6699;
  outline: none;
}

/* Checkbox label */
.term-week-form label > input[type="checkbox"] {
  margin-right: 10px;
  transform: scale(1.2);
  cursor: pointer;
  vertical-align: middle;
}

/* Submit buttons */
.term-week-form button[type="submit"],
.term-week-edit-btn {
  background-color: #2980b9;
  color: #fff;
  font-weight: 700;
  font-size: 1.15rem;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  align-self: flex-start;
  transition: background-color 0.3s ease;
}

.term-week-form button[type="submit"]:hover,
.term-week-edit-btn:hover {
  background-color: #1b6699;
}

/* Term & week header with term info and edit button */
.term-week-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  font-size: 1rem;
  font-weight: 600;
  color: #34495e;
}

.term-week-edit-btn {
  padding: 8px 15px;
  font-size: 1rem;
  border-radius: 6px;
}

/* Popup overlay for edit form */
.term-week-popup {
  display: none; /* Hidden by default */
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 380px;
  max-width: 90%;
  background: white;
  padding: 25px 30px;
  border-radius: 12px;
  box-shadow: 0 6px 15px rgba(0,0,0,0.25);
  z-index: 2000;
}

/* Popup close button */
.term-week-popup-close {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 1.8rem;
  font-weight: 700;
  color: #888;
  cursor: pointer;
  transition: color 0.2s ease;
  user-select: none;
}

.term-week-popup-close:hover {
  color: #2980b9;
}

/* Responsive */
@media (max-width: 520px) {
  .term-week-container {
    max-width: 95%;
    padding: 20px 15px;
  }

  .term-week-popup {
    width: 90%;
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
            <div class="term-week-container">
                <h1><i class='bx bx-calendar'></i> CBC Term & Week Management</h1>

                <?php if (!empty($message)): ?>
                    <div class="term-week-message"><?= $message ?></div>
                <?php endif; ?>

                <?php if ($term_required || isset($_GET['register_new_term'])): ?>
                    <!-- TERM REGISTRATION -->
                    <form method="post" class="term-week-form">
                        <h3><i class='bx bx-task'></i> Register New Term</h3>

                        <label for="term_number">Term Number</label>
                        <input type="number" name="term_number" id="term_number" required>

                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" required>

                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" required>

                        <button type="submit" name="register_term">Register Term</button>
                    </form>

                <?php else: ?>
                    <!-- WEEK REGISTRATION -->
                    <form method="post" class="term-week-form">
                       <h3><i class='bx bx-calendar-check'></i> Week Registration</h3>

                        <div class="term-week-header">
                            <div>
                                <strong>Term <?= $current_term['term_number'] ?></strong><br>
                                <?= $current_term['start_date'] ?> to <?= $current_term['end_date'] ?>
                            </div>
                                <button type="button" class="term-week-edit-btn" onclick="showEditPopup()"><i class='bx bx-edit'></i> Edit Term</button>
                        </div>

                        <label for="week_number">Week Number (Suggested)</label>
                        <input type="number" name="week_number" id="week_number" value="<?= $suggested_week ?>" readonly>

                        <label for="selected_day">Next Day (Auto Suggested)</label>
                        <input type="text" name="selected_day_display" id="selected_day_display" 
                            value="<?= $suggested_day ?>" readonly>
                        <input type="hidden" name="selected_day" value="<?= $suggested_day ?>">

                        <label>
                        <input type="checkbox" name="is_holiday"> <i class='bx bx-flag'></i> Mark as Public Holiday
                        </label>


                        <button type="submit" name="register_day"><i class='bx bx-calendar-plus'></i> Register Day</button>
                    </form>

                    <!-- EDIT TERM POP-UP FORM -->
                    <div class="term-week-popup" id="termEditPopup">
                        <span class="term-week-popup-close" onclick="closeEditPopup()">&times;</span>
                        <form method="post" class="term-week-form">
                            <h3><i class='bx bx-edit'></i> Edit Current Term</h3>

                            <label for="edit_start_date">Start Date</label>
                            <input type="date" name="edit_start_date" id="edit_start_date" 
                                value="<?= $current_term['start_date'] ?>" required>

                            <label for="edit_end_date">End Date</label>
                            <input type="date" name="edit_end_date" id="edit_end_date" 
                                value="<?= $current_term['end_date'] ?>" required>

                            <button type="submit" name="edit_term">Save Changes</button>
                        </form>
                    </div>
                <?php endif; ?>
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

</html>
