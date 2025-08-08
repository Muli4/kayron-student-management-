<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../php/db.php';

// ===================== SAVE ATTENDANCE =====================
if($_SERVER['REQUEST_METHOD']=='POST'){
    $day_id = intval($_POST['day_id']);
    $attendance = $_POST['attendance'] ?? [];

    foreach($attendance as $admission_no=>$status){
        $stmt = $conn->prepare("
            INSERT INTO attendance (admission_no, day_id, status) 
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status)
        ");
        $stmt->bind_param("sis", $admission_no, $day_id, $status);
        $stmt->execute();
    }
    echo "<script>alert('Attendance Saved Successfully!'); window.location.href='attendance_register.php?class={$_POST['class']}&day_id={$day_id}';</script>";
    exit;
}

// ===================== FETCH DATA =====================
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

$selected_day_id = $_GET['day_id'] ?? ($days[0]['day_id'] ?? null);

$classes = ['babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6'];
$selected_class = $_GET['class'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Attendance Register</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="website icon" type="png" href="photos/Logo.jpg">
    <style>
        /* === Attendance Container & Layout === */
        .attendance-container { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            width: 100%;
        }
h2{
    text-align: center;
}
        .attendance-filters {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #eef3ff;
            border-radius: 8px;
            margin-top: 20px;
            width: 70%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .attendance-filters label {
            font-weight: bold;
        }
        .attendance-filters select {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            width: 200px;
        }

        /* === Attendance Table === */
        .attendance-table {
            border-collapse: collapse;
            width: 90%;
            margin-top: 20px;
        }
        .attendance-table th, 
        .attendance-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        .attendance-table th {
            background: #f0f0f0;
            font-weight: bold;
        }

        /* === Status Colors === */
        .present { color: green; font-weight: bold; }
        .absent { color: red; font-weight: bold; }
        .disabled { background-color: #f9f9f9; color:#999; }

        /* === Save Button === */
        .btn-save { 
            margin-top: 15px; 
            padding: 10px 20px; 
            background: #28a745; 
            color: white; 
            border: none; 
            cursor: pointer; 
            border-radius: 5px;
            font-weight: bold;
            width: 100%;
        }
        .btn-save:hover { background: #218838; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">

        <h2>Daily Attendance Register</h2>

        <div class="attendance-container">

            <!-- Filters -->
            <form method="GET" class="attendance-filters">
                <label>Select Class:</label>
                <select name="class" onchange="this.form.submit()">
                    <option value="">--Select Class--</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c ?>" <?= $selected_class==$c?'selected':'' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Select Day:</label>
                <select name="day_id" onchange="this.form.submit()">
                    <?php foreach($days as $d): 
                        $label = "Term {$d['term_number']} ({$d['year']}) - Week {$d['week_number']} - {$d['day_name']}";
                    ?>
                        <option value="<?= $d['day_id'] ?>" <?= $selected_day_id==$d['day_id']?'selected':'' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php
            if($selected_class && $selected_day_id){
                // Header Info
                $dayDetails = array_values(array_filter($days, fn($d)=>$d['day_id']==$selected_day_id))[0] ?? null;
                if($dayDetails){
                    echo "<p><strong>Term:</strong> Term {$dayDetails['term_number']} ({$dayDetails['year']}) |
                        <strong>Week:</strong> {$dayDetails['week_number']} |
                        <strong>Day:</strong> {$dayDetails['day_name']}</p>";
                }

                // Students Ordered by Admission Number
                $stmt = $conn->prepare("
                    SELECT admission_no, name 
                    FROM student_records 
                    WHERE class=? 
                    ORDER BY CAST(admission_no AS UNSIGNED) ASC
                ");
                $stmt->bind_param("s", $selected_class);
                $stmt->execute();
                $students = $stmt->get_result();

                if($students->num_rows>0){

                    // Existing Attendance
                    $attStmt = $conn->prepare("SELECT admission_no, status FROM attendance WHERE day_id=?");
                    $attStmt->bind_param("i", $selected_day_id);
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
                    
                    $i=1; $studentsToMark=0;
                    while($row=$students->fetch_assoc()){
                        $admission = $row['admission_no'];
                        $marked = isset($existingAttendance[$admission]);
                        $status = $existingAttendance[$admission] ?? '';
                        $presentChecked = $status=="Present"?"checked":""; 
                        $absentChecked = $status=="Absent"?"checked":""; 
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

                    if($studentsToMark>0){
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

<?php include '../includes/footer.php'; ?>
</body>
</html>
