<?php
// attendance_register.php (smart future week or carry forward)

// Debug logging (disable display_errors on production)
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../php/db.php';

// Helper logger
function dbg_log($message, $conn = null) {
    $prefix = '[' . date('Y-m-d H:i:s') . '] ';
    if ($conn && $conn instanceof mysqli && $conn->error) {
        $message .= " | MySQL error: " . $conn->error;
    }
    error_log($prefix . $message . PHP_EOL, 3, __DIR__ . '/php-error.log');
}

// Validate DB connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    dbg_log("Database connection missing or invalid.");
    die("Database connection error. Check php-error.log for details.");
}

// Classes
$classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];
$selected_class = $_GET['class'] ?? '';
$selected_day_id = $_GET['day_id'] ?? null;

// ===================== AUTO-SELECT CURRENT DAY =====================
$weekendFlag = false;
$today = date('Y-m-d');
$todayWeekday = date('N'); // 1=Mon .. 7=Sun
$default_day_id = null;

if (!$selected_day_id) {
    if ($todayWeekday == 6 || $todayWeekday == 7) {
        $weekendFlag = true;
    } else {
        $sql = "SELECT * FROM terms WHERE start_date <= '$today' AND end_date >= '$today' ORDER BY id DESC LIMIT 1";
        $termRes = $conn->query($sql);
        if ($termRes && $termRes->num_rows > 0) {
            $term = $termRes->fetch_assoc();

            $daysSinceStart = floor((strtotime($today) - strtotime($term['start_date'])) / (60*60*24));
            $currentWeek = floor($daysSinceStart / 7) + 1;
            $dayName = date('l');

            $dsql = "SELECT d.id 
                     FROM days d
                     JOIN weeks w ON d.week_id = w.id
                     WHERE w.term_id = {$term['id']} 
                       AND w.week_number = $currentWeek
                       AND d.day_name = '" . $conn->real_escape_string($dayName) . "'
                     LIMIT 1";
            $dayRes = $conn->query($dsql);
            if ($dayRes && $dayRes->num_rows > 0) {
                $default_day_id = $dayRes->fetch_assoc()['id'];
            }
        }
    }

    if (!$default_day_id && !$weekendFlag) {
        $lastDayRes = $conn->query("SELECT id FROM days ORDER BY id DESC LIMIT 1");
        if ($lastDayRes && $lastDayRes->num_rows > 0) {
            $default_day_id = $lastDayRes->fetch_assoc()['id'];
        }
    }

    $selected_day_id = $default_day_id;
}

// ===================== SAVE ATTENDANCE =====================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($weekendFlag) {
        echo "<script>alert('Today is Weekend. Attendance not recorded.'); window.history.back();</script>";
        exit;
    }

    $day_id = intval($_POST['day_id']);
    $attendance = $_POST['attendance'] ?? [];

    $dayQuery = $conn->prepare("
        SELECT d.day_name, w.week_number, t.term_number, t.id as term_id, t.start_date, t.end_date
        FROM days d
        JOIN weeks w ON d.week_id = w.id
        JOIN terms t ON w.term_id = t.id
        WHERE d.id = ?
    ");
    $dayQuery->bind_param("i", $day_id);
    $dayQuery->execute();
    $dayResult = $dayQuery->get_result();

    if ($dayResult->num_rows === 0) {
        echo "<script>alert('Invalid day selected.'); window.history.back();</script>";
        exit;
    }

    $dayData = $dayResult->fetch_assoc();
    $day_name = $dayData['day_name'];
    $week_number = $dayData['week_number'];
    $term_number = $dayData['term_number'];
    $term_id = $dayData['term_id'];
    $term_start = $dayData['start_date'];
    $term_end = $dayData['end_date'];

    $daysOrder = ['monday','tuesday','wednesday','thursday','friday'];

    foreach ($attendance as $admission_no => $status) {
        // Insert/update attendance
        $stmt = $conn->prepare("
            INSERT INTO attendance (admission_no, term_number, week_number, day_id, day_name, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("siiiss", $admission_no, $term_number, $week_number, $day_id, $day_name, $status);
        $stmt->execute();

        // Handle lunch fees
        if ($status === "Absent") {
            $dayColumn = strtolower($day_name);

            $lq = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=? LIMIT 1");
            $lq->bind_param("sii", $admission_no, $term_id, $week_number);
            $lq->execute();
            $lres = $lq->get_result();

            if ($lrow = $lres->fetch_assoc()) {
                $currentAmt = isset($lrow[$dayColumn]) ? floatval($lrow[$dayColumn]) : 0.0;
                if ($currentAmt > 0) {
                    // Zero out today's column
                    $update = $conn->prepare("UPDATE lunch_fees SET `$dayColumn`=0 WHERE id=?");
                    $update->bind_param("i", $lrow['id']);
                    $update->execute();

                    $amountToMove = $currentAmt;

                    // Reallocate within SAME week only
                    $dayIndex = array_search($dayColumn, $daysOrder);
                    if ($dayIndex === false) $dayIndex = 0;
                    for ($i = $dayIndex + 1; $i < count($daysOrder) && $amountToMove > 0; $i++) {
                        $d = $daysOrder[$i];
                        $existingVal = isset($lrow[$d]) ? floatval($lrow[$d]) : 0.0;
                        if ($existingVal < 70) {
                            $needed = 70 - $existingVal;
                            $alloc = min($needed, $amountToMove);
                            $newVal = $existingVal + $alloc;

                            $upd = $conn->prepare("UPDATE lunch_fees SET `$d`=? WHERE id=?");
                            $upd->bind_param("di", $newVal, $lrow['id']);
                            $upd->execute();

                            $amountToMove -= $alloc;
                            $lrow[$d] = $newVal;
                        }
                    }

                    // If still remaining → try pushing to future weeks within term
                    if ($amountToMove > 0) {
                        $futureWeek = $week_number + 1;
                        $moved = false;

                        while ($amountToMove > 0) {
                            // Calculate the start date of that future week
                            $futureStart = date('Y-m-d', strtotime($term_start . " +" . ($futureWeek - 1) . " week"));

                            if ($futureStart > $term_end) {
                                // No more weeks → store carry forward
                                $newCarry = (isset($lrow['carry_forward']) ? floatval($lrow['carry_forward']) : 0.0) + $amountToMove;
                                $upd = $conn->prepare("UPDATE lunch_fees SET carry_forward=? WHERE id=?");
                                $upd->bind_param("di", $newCarry, $lrow['id']);
                                $upd->execute();
                                dbg_log("Carried forward $amountToMove for $admission_no since term ended");
                                break;
                            }

                            // Check if lunch_fees exists for this week
                            $fq = $conn->prepare("SELECT * FROM lunch_fees WHERE admission_no=? AND term_id=? AND week_number=? LIMIT 1");
                            $fq->bind_param("sii", $admission_no, $term_id, $futureWeek);
                            $fq->execute();
                            $fres = $fq->get_result();

                            if ($frow = $fres->fetch_assoc()) {
                                // Allocate into that week
                                foreach ($daysOrder as $d) {
                                    if ($amountToMove <= 0) break;
                                    $fExisting = isset($frow[$d]) ? floatval($frow[$d]) : 0.0;
                                    if ($fExisting < 70) {
                                        $needed = 70 - $fExisting;
                                        $alloc = min($needed, $amountToMove);
                                        $newVal = $fExisting + $alloc;

                                        $upd = $conn->prepare("UPDATE lunch_fees SET `$d`=?, total_paid=total_paid+?, balance=balance-? WHERE id=?");
                                        $upd->bind_param("diii", $newVal, $alloc, $alloc, $frow['id']);
                                        $upd->execute();

                                        $amountToMove -= $alloc;
                                        $moved = true;
                                    }
                                }
                            } else {
                                // Create a new lunch_fees record for this week
                                $insLunch = $conn->prepare("
                                    INSERT INTO lunch_fees (admission_no, term_id, week_number, total_paid, balance, total_amount)
                                    VALUES (?, ?, ?, 0, 350, 350)
                                ");
                                $insLunch->bind_param("sii", $admission_no, $term_id, $futureWeek);
                                $insLunch->execute();
                                dbg_log("Created lunch_fees record for $admission_no week $futureWeek");

                                // Next loop will catch this new record
                                continue;
                            }

                            $futureWeek++;
                        }
                    }
                }
            }
        }
    }

    echo "<script>alert('Attendance Saved Successfully!'); window.location.href='attendance_register.php?class=" . urlencode($_POST['class']) . "&day_id={$day_id}';</script>";
    exit;
}

// ===================== FETCH DAYS =====================
$daysQuery = "
    SELECT d.id as day_id, d.day_name, w.week_number, t.term_number, t.year
    FROM days d
    JOIN weeks w ON d.week_id = w.id
    JOIN terms t ON w.term_id = t.id
    ORDER BY t.year DESC, t.term_number DESC, w.week_number DESC,
             FIELD(d.day_name,'Monday','Tuesday','Wednesday','Thursday','Friday') DESC
";
$daysRes = $conn->query($daysQuery);
$days = $daysRes ? $daysRes->fetch_all(MYSQLI_ASSOC) : [];

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Attendance Register</title>
    <link rel="stylesheet" href="../style/style-sheet.css">
    <link rel="website icon" type="png" href="../images/school-logo.jpg">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
    /* Container for attendance */
.attendance-container {
  max-width: 900px;
  margin: 20px auto;
  background: #fff;
  padding: 20px 25px;
  border-radius: 8px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.12);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Heading */
.attendance-container h2 {
  text-align: center;
  color: #2c3e50;
  margin-bottom: 25px;
  font-weight: 700;
  font-size: 20px;
}

/* Filter form styling */
.attendance-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  justify-content: center;
  margin-bottom: 30px;
}

.attendance-filters label {
  font-weight: 600;
  color: #34495e;
  align-self: center;
}

.attendance-filters select {
  padding: 7px 12px;
  border: 2px solid #2980b9;
  border-radius: 6px;
  font-size: 1rem;
  color: #2c3e50;
  min-width: 180px;
  transition: border-color 0.3s ease;
}

.attendance-filters select:focus {
  border-color: #1b6699;
  outline: none;
}
.attendance-container p {
  text-align: center;
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 20px;
  color: #2c3e50;
}

/* Attendance table */
.attendance-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
  font-size: 1rem;
  color: #34495e;
}

.attendance-table th,
.attendance-table td {
  border: 1.5px solid #2980b9;
  padding: 10px 12px;
  text-align: center;
}

.attendance-table th {
  background-color: #2980b9;
  color: white;
  font-weight: 700;
  user-select: none;
}

/* Radio buttons alignment */
.attendance-table input[type="radio"] {
  transform: scale(1.2);
  cursor: pointer;
}

/* Status text */
.present {
  color: #27ae60;
  font-weight: 600;
}

.absent {
  color: #c0392b;
  font-weight: 600;
}

/* Save button */
.btn-save {
  display: block;
  margin: 0 auto 10px auto;
  background-color: #2980b9;
  color: white;
  font-weight: 700;
  font-size: 1.1rem;
  padding: 12px 30px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.btn-save:hover {
  background-color: #1b6699;
}

/* Responsive tweaks */
@media (max-width: 600px) {
  .attendance-filters {
    flex-direction: column;
    gap: 15px;
  }

  .attendance-filters select {
    min-width: 100%;
  }

  .attendance-table th, .attendance-table td {
    padding: 8px 6px;
    font-size: 0.9rem;
  }
}

</style>

</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">

        <div class="attendance-container">
            <h2>Daily Attendance Register</h2>

            <!-- Filters -->
            <form method="GET" class="attendance-filters">
                <label>Select Class:</label>
                <select name="class" onchange="this.form.submit()">
                    <option value="">--Select Class--</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c ?>" <?= $selected_class == $c ? 'selected' : '' ?>>
                            <?= ucfirst($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Select Day:</label>
                <select name="day_id" onchange="this.form.submit()">

                    <?php if ($weekendFlag): ?>
                        <option value="" selected disabled style="color:red;">
                            Today is Weekend (<?= date('l') ?>)
                        </option>
                    <?php endif; ?>

                    <?php
                    // Fetch current term
                    $today = date('Y-m-d');
                    $termRes = $conn->query("
                        SELECT * FROM terms
                        WHERE start_date <= '$today' AND end_date >= '$today'
                        ORDER BY id DESC LIMIT 1
                    ");
                    if ($termRes && $termRes->num_rows > 0) {
                        $currentTerm = $termRes->fetch_assoc();
                        $termId = $currentTerm['id'];
                        $termNumber = $currentTerm['term_number'];
                        $termYear = $currentTerm['year'];

                        // Fetch all weeks and days for this term
                        $weekDaysRes = $conn->query("
                            SELECT w.week_number, d.id as day_id, d.day_name
                            FROM weeks w
                            JOIN days d ON d.week_id = w.id
                            WHERE w.term_id = $termId
                            ORDER BY w.week_number ASC,
                                    FIELD(d.day_name,'Monday','Tuesday','Wednesday','Thursday','Friday') ASC
                        ");

                        $weeks = [];
                        while ($row = $weekDaysRes->fetch_assoc()) {
                            $weeks[$row['week_number']][] = $row;
                        }

                        foreach ($weeks as $weekNum => $daysInWeek) {
                            echo "<optgroup label='Term $termNumber - Week $weekNum'>";
                            foreach ($daysInWeek as $d) {
                                $selected = (!$weekendFlag && $selected_day_id == $d['day_id']) ? 'selected' : '';
                                echo "<option value='{$d['day_id']}' $selected>{$d['day_name']}</option>";
                            }
                            echo "</optgroup>";
                        }
                    }
                    ?>
                </select>
            </form>

            <?php
            if($selected_class && $selected_day_id){
                $dayDetails = array_values(array_filter($days, fn($d)=>$d['day_id']==$selected_day_id))[0] ?? null;
                if($dayDetails){
                    echo "<p><strong>Term:</strong> Term {$dayDetails['term_number']} ({$dayDetails['year']}) |
                        <strong>Week:</strong> {$dayDetails['week_number']} |
                        <strong>Day:</strong> {$dayDetails['day_name']}</p>";
                }

                $stmt = $conn->prepare("
                    SELECT admission_no, name 
                    FROM student_records 
                    WHERE class=? 
                    ORDER BY CAST(admission_no AS UNSIGNED) ASC
                ");
                $stmt->bind_param("s", $selected_class);
                $stmt->execute();
                $students = $stmt->get_result();

                if($students->num_rows > 0){

                    // Existing Attendance
                    $attStmt = $conn->prepare("SELECT admission_no, status FROM attendance WHERE term_number=? AND week_number=? AND day_name=?");
                    $attStmt->bind_param("iis", $dayDetails['term_number'], $dayDetails['week_number'], $dayDetails['day_name']);
                    $attStmt->execute();
                    $attResult = $attStmt->get_result();
                    $existingAttendance = [];
                    while($r = $attResult->fetch_assoc()){
                        $existingAttendance[$r['admission_no']] = $r['status'];
                    }

                    echo '<form method="POST" action="">
                        <input type="hidden" name="day_id" value="'.$selected_day_id.'">
                        <input type="hidden" name="class" value="'.$selected_class.'">
                        <table class="attendance-table">
                            <tr>
                                <th>#</th>
                                <th>Admission No</th>
                                <th>Name</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Status</th>
                            </tr>';

                    $i = 1; $studentsToMark = 0;
                    while($row = $students->fetch_assoc()){
                        $admission = $row['admission_no'];
                        $marked = isset($existingAttendance[$admission]);
                        $status = $existingAttendance[$admission] ?? '';
                        $presentChecked = $status == "Present" ? "checked" : ""; 
                        $absentChecked = $status == "Absent" ? "checked" : ""; 
                        $disabled = $marked ? "disabled" : "";

                        if(!$marked) $studentsToMark++;

                        echo "<tr>
                                <td>$i</td>
                                <td>{$row['admission_no']}</td>
                                <td>{$row['name']}</td>
                                <td><input type='radio' name='attendance[{$admission}]' value='Present' $disabled $presentChecked></td>
                                <td><input type='radio' name='attendance[{$admission}]' value='Absent' $disabled $absentChecked></td>
                                <td>".($marked ? "<span class='".strtolower($status)."'>$status</span>" : "Not Marked")."</td>
                            </tr>";
                        $i++;
                    }

                    echo '</table>';

                    if($studentsToMark > 0){
                        echo '<button type="submit" class="btn-save">Save Attendance</button>';
                    } else {
                        echo '<p style="color:green;font-weight:bold;">Attendance already marked for all students.</p>';
                    }

                    echo '</form>';
                } else {
                    echo '<p style="color:red;">No students found for this class.</p>';
                }
            }
            ?>
        </div>
    </main>
</div>

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

    /* ===== Keep dropdown open based on current page ===== */
    const currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll(".dropdown").forEach(drop => {
      const links = drop.querySelectorAll("a");
      links.forEach(link => {
        const href = link.getAttribute("href");
        if (href && href.includes(currentPage)) {
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

    /* ===== Close sidebar on outside click ===== */
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
    });

    /* ===== Auto logout after 5 minutes inactivity ===== */
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
  });
</script>
</body>
</html>
