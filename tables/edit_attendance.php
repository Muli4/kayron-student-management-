<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../php/db.php';

// List of classes
$classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];
$selected_class = $_GET['class'] ?? '';
$selected_day_id = $_GET['day_id'] ?? null;

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day_id = intval($_POST['day_id']);
    $attendance = $_POST['attendance'] ?? [];

    // Get day, week, and term info
    $dayQuery = $conn->prepare("
        SELECT d.day_name, w.week_number, t.term_number
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

    // Insert or update each student's attendance
    foreach ($attendance as $admission_no => $status) {
        $stmt = $conn->prepare("
            INSERT INTO attendance (admission_no, term_number, week_number, day_id, day_name, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("siiiss", $admission_no, $term_number, $week_number, $day_id, $day_name, $status);
        $stmt->execute();
    }

    echo "<script>alert('Attendance updated successfully!'); window.location.href='edit_attendance.php?class={$_POST['class']}&day_id={$day_id}';</script>";
    exit;
}

// Fetch available days
$daysQuery = "
    SELECT d.id as day_id, d.day_name, w.week_number, t.term_number, t.year
    FROM days d
    JOIN weeks w ON d.week_id = w.id
    JOIN terms t ON w.term_id = t.id
    ORDER BY t.year DESC, t.term_number DESC, w.week_number DESC,
             FIELD(d.day_name,'Monday','Tuesday','Wednesday','Thursday','Friday') DESC
";
$daysRes = $conn->query($daysQuery);
$days = $daysRes->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Attendance</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f8f8; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        form.selectors { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; margin-bottom: 20px; }
        form.selectors select { padding: 6px 10px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        th { background: #3498db; color: white; }
        .btn-save { background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; display: block; margin-left: auto; margin-right: auto; }
        .btn-save:hover { background: #219150; }
        p.info { text-align: center; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Attendance</h2>

    <form method="GET" class="selectors">
        <select name="class" onchange="this.form.submit()">
            <option value="">-- Select Class --</option>
            <?php foreach($classes as $c): ?>
                <option value="<?= $c ?>" <?= $selected_class == $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="day_id" onchange="this.form.submit()">
            <option value="">-- Select Day --</option>
            <?php foreach($days as $d): 
                $label = "Term {$d['term_number']} ({$d['year']}) - Week {$d['week_number']} - {$d['day_name']}";
            ?>
                <option value="<?= $d['day_id'] ?>" <?= $selected_day_id == $d['day_id'] ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php
    if ($selected_class && $selected_day_id) {
        $dayDetails = array_values(array_filter($days, fn($d) => $d['day_id'] == $selected_day_id))[0] ?? null;

        if ($dayDetails) {
            echo "<p class='info'>Term {$dayDetails['term_number']} ({$dayDetails['year']}) — Week {$dayDetails['week_number']} — {$dayDetails['day_name']}</p>";
        }

        $stmt = $conn->prepare("
            SELECT admission_no, name 
            FROM student_records 
            WHERE class=? 
            ORDER BY CAST(admission_no AS UNSIGNED)
        ");
        $stmt->bind_param("s", $selected_class);
        $stmt->execute();
        $students = $stmt->get_result();

        if ($students->num_rows > 0) {
            // Get existing attendance
            $attStmt = $conn->prepare("SELECT admission_no, status FROM attendance WHERE term_number=? AND week_number=? AND day_id=?");
            $attStmt->bind_param("iii", $dayDetails['term_number'], $dayDetails['week_number'], $dayDetails['day_id']);
            $attStmt->execute();
            $attResult = $attStmt->get_result();
            $existingAttendance = [];
            while ($r = $attResult->fetch_assoc()) {
                $existingAttendance[$r['admission_no']] = $r['status'];
            }

            echo '<form method="POST">
                <input type="hidden" name="day_id" value="'.$selected_day_id.'">
                <input type="hidden" name="class" value="'.$selected_class.'">
                <table>
                    <tr>
                        <th>#</th>
                        <th>Admission No</th>
                        <th>Name</th>
                        <th>Present</th>
                        <th>Absent</th>
                    </tr>';

            $i = 1;
            while ($row = $students->fetch_assoc()) {
                $admission = $row['admission_no'];
                $status = $existingAttendance[$admission] ?? '';
                $presentChecked = $status == "Present" ? "checked" : "";
                $absentChecked = $status == "Absent" ? "checked" : "";

                echo "<tr>
                        <td>$i</td>
                        <td>{$admission}</td>
                        <td>{$row['name']}</td>
                        <td><input type='radio' name='attendance[{$admission}]' value='Present' $presentChecked></td>
                        <td><input type='radio' name='attendance[{$admission}]' value='Absent' $absentChecked></td>
                    </tr>";
                $i++;
            }

            echo '</table>
                <button type="submit" class="btn-save">Update Attendance</button>
            </form>';
        } else {
            echo "<p class='info' style='color: red;'>No students found in class <strong>{$selected_class}</strong>.</p>";
        }
    }
    ?>
</div>
<a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>
</body>
</html>
